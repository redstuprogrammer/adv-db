from flask import Flask, jsonify, request

app = Flask(__name__)


@app.route("/", methods=["GET"])
def index():
    return jsonify({"status": "groq-ocr-backend placeholder", "message": "OCR backend disabled — placeholder app"})


@app.route("/api/verify-document", methods=["POST"])
def verify_document():
    # Placeholder endpoint: responds quickly and does not perform OCR
    return jsonify({"ok": False, "reason": "OCR backend disabled (placeholder)."}), 503


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
