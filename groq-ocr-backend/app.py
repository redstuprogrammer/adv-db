"""
Flask backend: /api/verify-document
- Accepts multipart/form-data: clinic_name, tenant_id (optional), document (image or PDF)
- Converts PDFs to images with pypdfum2 (pure-Python, no external Poppler needed)
- Preprocesses images with Pillow: EXIF orientation, max width 1024px, quality 70%
- Calls Groq Vision model with strict JSON response format
- Auto-approves matching docs or flags for manual admin review
- Updates Azure Database with results
"""

import os
import io
import json
import base64
import datetime
from PIL import Image, ImageOps
import requests
import pymysql
from flask import Flask, request, jsonify
import pypdfium2 as pdfium

app = Flask(__name__)

# ========== CONFIG (read from Azure Application Settings) ==========
GROQ_API_KEY = os.environ.get("GROQ_API_KEY")
GROQ_MODEL_ID = os.environ.get("GROQ_MODEL_ID", "llama-3.2-11b-vision-preview")
GROQ_API_URL = os.environ.get("GROQ_API_URL", "https://api.groq.com/openai/v1/chat/completions")

# Azure Database
DB_HOST = os.environ.get("DB_HOST")
DB_USER = os.environ.get("DB_USER")
DB_PASS = os.environ.get("DB_PASS")
DB_NAME = os.environ.get("DB_NAME")

# Thresholds
CONFIDENCE_THRESHOLD = float(os.environ.get("CONFIDENCE_THRESHOLD", "0.80"))

if not GROQ_API_KEY:
    print("WARNING: GROQ_API_KEY not found in environment variables")

# ========== DATABASE FUNCTIONS ==========

def db_connect():
    """Connect to Azure Database"""
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        autocommit=True,
        charset='utf8mb4'
    )

def update_registration_status(tenant_id, clinic_name, groq_result, groq_raw):
    """Update tenant registration_status and OCR results in DB"""
    status = "APPROVED" if groq_result["decision"] == "AUTOMATIC" else "PENDING_ADMIN_REVIEW"
    now = datetime.datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")

    conn = db_connect()
    try:
        with conn.cursor() as cur:
            # Update tenants table
            if tenant_id:
                sql = """
                    UPDATE tenants
                    SET registration_status=%s,
                        ocr_extracted=%s,
                        ocr_confidence=%s,
                        ocr_raw_text=%s,
                        ocr_processed_at=%s
                    WHERE id=%s
                """
                cur.execute(sql, (
                    status,
                    json.dumps(groq_result),
                    groq_result["confidence_score"],
                    json.dumps(groq_raw),
                    now,
                    tenant_id
                ))
            else:
                # Fallback: match by clinic_name
                sql = """
                    UPDATE tenants
                    SET registration_status=%s,
                        ocr_extracted=%s,
                        ocr_confidence=%s,
                        ocr_raw_text=%s,
                        ocr_processed_at=%s
                    WHERE clinic_name=%s
                    LIMIT 1
                """
                cur.execute(sql, (
                    status,
                    json.dumps(groq_result),
                    groq_result["confidence_score"],
                    json.dumps(groq_raw),
                    now,
                    clinic_name
                ))

            # If manual review needed, enqueue for admin
            if status == "PENDING_ADMIN_REVIEW":
                queue_sql = """
                    INSERT INTO document_review_queue 
                    (tenant_id, clinic_name, created_at, reason, model_output, status)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """
                cur.execute(queue_sql, (
                    tenant_id,
                    clinic_name,
                    now,
                    groq_result["reason_for_decision"],
                    json.dumps(groq_raw),
                    "PENDING"
                ))

            conn.commit()
    finally:
        conn.close()

# ========== IMAGE PREPROCESSING ==========

def convert_pdf_to_image(pdf_bytes):
    """
    Convert first page of PDF to PIL Image using pypdfium2 (pure-Python).
    Returns PIL Image or None if conversion fails.
    """
    try:
        # Load PDF from bytes
        pdf = pdfium.PdfDocument.new(io.BytesIO(pdf_bytes))
        
        if len(pdf) == 0:
            return None
        
        # Get first page
        page = pdf[0]
        
        # Render page to image (300 DPI)
        bitmap = page.render(scale=300/72)
        
        # Convert bitmap to PIL Image
        img = bitmap.to_pil()
        
        return img
    except Exception as e:
        print(f"PDF conversion error: {e}")
    
    return None

def preprocess_image(file_stream, filename):
    """
    - Load image (or convert from PDF if needed)
    - Fix EXIF orientation
    - Resize to max width 1024px
    - Convert to RGB
    - Compress to quality=70
    - Return JPEG bytes
    """
    # Check if PDF or image
    is_pdf = filename.lower().endswith(".pdf")

    if is_pdf:
        pdf_data = file_stream.read()
        img = convert_pdf_to_image(pdf_data)
        if not img:
            raise ValueError("Failed to convert PDF to image")
    else:
        file_stream.seek(0)
        img = Image.open(file_stream)

    # Fix EXIF orientation
    img = ImageOps.exif_transpose(img)
    
    # Convert to RGB (handle RGBA, grayscale, etc.)
    if img.mode != "RGB":
        img = img.convert("RGB")

    # Resize if width > 1024
    max_width = 1024
    if img.width > max_width:
        ratio = max_width / float(img.width)
        new_height = int(float(img.height) * ratio)
        img = img.resize((max_width, new_height), Image.LANCZOS)

    # Compress to JPEG quality=70
    out_buffer = io.BytesIO()
    img.save(out_buffer, format="JPEG", quality=70, optimize=True)
    out_buffer.seek(0)
    return out_buffer.read()

# ========== GROQ API CALL ==========

def call_groq_vision(image_bytes, clinic_name):
    """
    Call Groq Vision model with strict JSON response format.
    Returns parsed JSON response and raw response.
    """
    b64_image = base64.b64encode(image_bytes).decode("utf-8")

    # Strict instruction for model
    instruction = f"""You are a document verification system. Analyze this image of a Philippine government document (DTI Certificate or BIR Form 2303).

Extract the following information and return ONLY a JSON object (no other text):
- document_type: either "DTI", "BIR", or "Unknown"
- extracted_name: the business/clinic name on the document
- is_match: boolean - does extracted_name match the clinic name "{clinic_name}"?
- confidence_score: float between 0.0 and 1.0 for overall extraction confidence
- decision: either "AUTOMATIC" or "MANUAL_REVIEW"
- reason_for_decision: brief explanation of the decision

If extracted text is unclear, confidence is low, or names don't match, set decision to "MANUAL_REVIEW".
If all fields are clear, confidence is high (>= 0.85), and names match, set decision to "AUTOMATIC".

Return ONLY the JSON object."""

    payload = {
        "model": GROQ_MODEL_ID,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "image_url",
                        "image_url": {
                            "url": f"data:image/jpeg;base64,{b64_image}"
                        }
                    },
                    {
                        "type": "text",
                        "text": instruction
                    }
                ]
            }
        ],
        "response_format": {"type": "json_object"},
        "temperature": 0.3,
        "max_tokens": 500
    }

    headers = {
        "Authorization": f"Bearer {GROQ_API_KEY}",
        "Content-Type": "application/json"
    }

    response = requests.post(GROQ_API_URL, headers=headers, json=payload, timeout=60)
    response.raise_for_status()

    result = response.json()
    
    # Extract content from Groq response
    if "choices" in result and len(result["choices"]) > 0:
        message = result["choices"][0].get("message", {})
        content = message.get("content", "{}")
        # Parse JSON from content
        try:
            parsed = json.loads(content)
        except json.JSONDecodeError:
            # If not pure JSON, try to extract JSON from text
            import re
            match = re.search(r'\{.*\}', content, re.DOTALL)
            if match:
                parsed = json.loads(match.group())
            else:
                parsed = {}
        return parsed, result
    
    return {}, result

# ========== PARSE & DECIDE ==========

def normalize_groq_result(groq_json, clinic_name):
    """
    Normalize Groq response to our strict format and apply decision logic.
    """
    result = groq_json if isinstance(groq_json, dict) else {}

    # Validate and normalize required keys
    required_keys = ["document_type", "extracted_name", "is_match", "confidence_score", "decision", "reason_for_decision"]
    normalized = {k: result.get(k) for k in required_keys}

    # Sanitize document_type
    doc_type = normalized.get("document_type")
    if doc_type not in ("DTI", "BIR"):
        normalized["document_type"] = "Unknown"

    # Sanitize extracted_name
    extracted_name = (normalized.get("extracted_name") or "").strip()

    # Sanitize is_match (boolean)
    is_match = bool(normalized.get("is_match"))

    # Sanitize confidence_score (0.0 to 1.0)
    try:
        confidence = float(normalized.get("confidence_score", 0.0))
        confidence = max(0.0, min(1.0, confidence))
    except (TypeError, ValueError):
        confidence = 0.0

    # Validate decision
    decision = normalized.get("decision")
    if decision not in ("AUTOMATIC", "MANUAL_REVIEW"):
        # Apply our logic: auto if conditions met
        if normalized["document_type"] in ("DTI", "BIR") and is_match and confidence >= CONFIDENCE_THRESHOLD:
            decision = "AUTOMATIC"
            reason = "Document valid, name match, confidence threshold met"
        else:
            decision = "MANUAL_REVIEW"
            reason = f"Low confidence ({confidence:.2f}) or name mismatch or unrecognized document"
    else:
        reason = normalized.get("reason_for_decision", "")

    final = {
        "document_type": normalized["document_type"],
        "extracted_name": extracted_name,
        "is_match": is_match,
        "confidence_score": confidence,
        "decision": decision,
        "reason_for_decision": reason
    }

    return final

# ========== ENDPOINT ==========

@app.route("/api/verify-document", methods=["POST"])
def verify_document():
    """
    POST /api/verify-document
    Multipart form data:
      - clinic_name (required): string
      - tenant_id (optional): integer
      - document (required): file (image or PDF)
    
    Returns JSON with OCR result and decision.
    """
    try:
        # Validate inputs
        clinic_name = request.form.get("clinic_name", "").strip()
        tenant_id = request.form.get("tenant_id")
        
        if not clinic_name:
            return jsonify({"error": "clinic_name is required"}), 400

        if "document" not in request.files:
            return jsonify({"error": "document file is required"}), 400

        file_obj = request.files["document"]
        if not file_obj.filename:
            return jsonify({"error": "document file is empty"}), 400

        # Validate file type
        allowed_ext = {"jpg", "jpeg", "png", "gif", "pdf"}
        file_ext = file_obj.filename.split(".")[-1].lower() if "." in file_obj.filename else ""
        if file_ext not in allowed_ext:
            return jsonify({"error": f"File type not allowed. Allowed: {', '.join(allowed_ext)}"}), 400

        # Validate file size (max 10MB)
        file_obj.seek(0, 2)  # Seek to end
        file_size = file_obj.tell()
        file_obj.seek(0)  # Seek back to start
        
        max_size = 10 * 1024 * 1024  # 10MB
        if file_size > max_size:
            return jsonify({"error": f"File too large. Max size: 10MB"}), 400

        # Convert tenant_id to int or None
        try:
            tenant_id = int(tenant_id) if tenant_id else None
        except ValueError:
            tenant_id = None

        # Preprocess image (handles PDFs too)
        print(f"Preprocessing document: {file_obj.filename}")
        image_bytes = preprocess_image(file_obj.stream, file_obj.filename)

        # Call Groq Vision model
        print(f"Calling Groq with model: {GROQ_MODEL_ID}")
        groq_result, groq_raw = call_groq_vision(image_bytes, clinic_name)

        # Normalize and decide
        final_result = normalize_groq_result(groq_result, clinic_name)

        # Update database
        print(f"Updating database for tenant_id={tenant_id}, clinic_name={clinic_name}")
        update_registration_status(tenant_id, clinic_name, final_result, groq_raw)

        # Return response
        return jsonify({
            "document_type": final_result["document_type"],
            "extracted_name": final_result["extracted_name"],
            "is_match": final_result["is_match"],
            "confidence_score": final_result["confidence_score"],
            "decision": final_result["decision"],
            "reason_for_decision": final_result["reason_for_decision"],
            "registration_status": "APPROVED" if final_result["decision"] == "AUTOMATIC" else "PENDING_ADMIN_REVIEW"
        }), 200

    except requests.HTTPError as e:
        error_detail = str(e)
        if hasattr(e, 'response') and e.response is not None:
            try:
                error_detail = e.response.json()
            except:
                error_detail = e.response.text
        return jsonify({"error": "Groq API error", "details": error_detail, "status_code": e.response.status_code if hasattr(e, 'response') else None}), 502
    
    except Exception as ex:
        print(f"Error in verify_document: {str(ex)}")
        return jsonify({"error": "Internal server error", "details": str(ex)}), 500

# ========== HEALTH CHECK ==========

@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "model": GROQ_MODEL_ID}), 200

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 8000))
    app.run(host="0.0.0.0", port=port, debug=False)
