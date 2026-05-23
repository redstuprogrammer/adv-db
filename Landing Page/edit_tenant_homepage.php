<?php
/**
 * Edit Tenant Homepage — Inline Visual Editor
 * Tenant-isolated: all reads/writes are scoped to the logged-in tenant's ID.
 */
require_once '../includes/session_utils.php';
require_once '../includes/connect.php';

$session = SessionManager::getInstance();
$tenantSlug = $session->getCurrentTenantSlug();

if (!$session->isTenantUser()) {
    $slugParam = $tenantSlug ? '?tenant=' . urlencode($tenantSlug) : '';
    header('Location: ../tenant_login.php' . $slugParam);
    exit;
}

$tenant_id = $session->getTenantId();
if (!$tenant_id) {
    header('Location: ../tenant_login.php');
    exit;
}

// ── Handle AJAX save ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    
    try {
        // Ensure all newer columns exist before any INSERT/UPDATE is attempted.
        // Use SHOW COLUMNS to check first — avoids ALTER TABLE errors on any MySQL version.
        $existingCols = [];
        $colResult = $conn->query("SHOW COLUMNS FROM clinic_settings");
        while ($colRow = $colResult->fetch_assoc()) {
            $existingCols[] = $colRow['Field'];
        }
        $newColumns = [
            'announcements_json' => 'TEXT',
            'team_json' => 'TEXT',
            'hero_image' => 'TEXT',
            'about_image_1' => 'TEXT',
            'about_image_2' => 'TEXT',
            'team_title' => 'VARCHAR(255)',
            'team_subtitle' => 'TEXT'
        ];
        foreach ($newColumns as $col => $type) {
            if (!in_array($col, $existingCols)) {
                if (!$conn->query("ALTER TABLE clinic_settings ADD COLUMN `$col` $type")) {
                    throw new Exception("Migration failed for $col: " . $conn->error);
                }
            }
        }

        $allowed = [
            'clinic_name', 'hero_title', 'hero_description',
            'about_description', 'footer_copyright',
            'badge_visible', 'badge_text',
            'checklist_1', 'checklist_2', 'checklist_3',
            'cta_primary',
            'accent_color',
            'announcements_json', 'team_json',
            'hero_image', 'about_image_1', 'about_image_2',
            'team_title', 'team_subtitle'
        ];

        $fields = [];
        $params = [];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $fields[] = "`$key` = ?";
                $params[] = $_POST[$key];
            }
        }

        if ($fields) {
            $check = $conn->prepare("SELECT id FROM clinic_settings WHERE tenant_id = ?");
            $check->bind_param("i", $tenant_id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $sql = "";
            if ($exists) {
                $params[] = $tenant_id;
                $sql = "UPDATE clinic_settings SET " . implode(', ', $fields) . " WHERE tenant_id = ?";
            } else {
                $params[] = $tenant_id;
                $colNames = array_map(fn($f) => str_replace('`', '', explode(' =', $f)[0]), $fields);
                $colNames[] = 'tenant_id';
                $placeholders = implode(', ', array_fill(0, count($params), '?'));
                $sql = "INSERT INTO clinic_settings (" . implode(', ', array_map(fn($c) => "`$c`", $colNames)) . ") VALUES ($placeholders)";
            }

            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $types = str_repeat('s', count($params) - 1) . 'i';
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception("Execute failed: " . $err);
            }
            if ($stmt) $stmt->close();
        }


        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log("Editor Save Error: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


// ── Fetch this tenant's homepage settings ─────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM clinic_settings WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$clinic = $stmt->get_result()->fetch_assoc() ?: [];

// ── Fetch tenant details from 'tenants' table for real defaults ─────────────
$stmtT = $conn->prepare("SELECT * FROM tenants WHERE tenant_id = ?");
$stmtT->bind_param("i", $tenant_id);
$stmtT->execute();
$tenantData = $stmtT->get_result()->fetch_assoc() ?: [];
$stmtT->close();

$realClinicName = $tenantData['company_name'] ?? 'Your Clinic';
$realAddress = ($tenantData['address'] ?? '') . ", " . ($tenantData['city'] ?? '') . ", " . ($tenantData['province'] ?? '');
$realPhone = $tenantData['phone'] ?? '+1 (555) 890-2344';
$realEmail = $tenantData['contact_email'] ?? $tenantData['email'] ?? 'concierge@yourclinic.com';

$c = [
    'clinic_name'      => $clinic['clinic_name']      ?? $realClinicName,
    'hero_title'       => $clinic['hero_title']        ?? $realClinicName,
    'hero_description' => $clinic['hero_description']  ?? ("Welcome to " . $realClinicName . ". Experience a new standard of dental care where precision meets serenity."),
    'about_description'=> $clinic['about_description'] ?? ("Serving the community in " . ($tenantData['city'] ?? 'your city') . ". We believe world-class dentistry should never feel clinical."),
    'contact_phone'    => $clinic['contact_phone']     ?? $realPhone,
    'contact_email'    => $clinic['contact_email']     ?? $realEmail,
    'contact_address'  => $clinic['contact_address']   ?? $realAddress,
    'footer_copyright' => $clinic['footer_copyright']  ?? ("© " . date('Y') . " " . $realClinicName . ". Professional Dental Serenity."),
    'badge_visible'    => $clinic['badge_visible']     ?? '1',
    'badge_text'       => $clinic['badge_text']        ?? 'Clinical Serenity',
    'stat_number'      => $clinic['stat_number']       ?? '98%',
    'stat_label'       => $clinic['stat_label']        ?? 'Patient Comfort Index',
    'checklist_1'      => $clinic['checklist_1']       ?? 'Curated Acoustic Environments',
    'checklist_2'      => $clinic['checklist_2']       ?? 'Bio-compatible Premium Materials',
    'checklist_3'      => $clinic['checklist_3']       ?? 'Post-treatment Serenity Lounges',
    'cta_primary'      => $clinic['cta_primary']       ?? 'Book Appointment',
    'accent_color'     => $clinic['accent_color']      ?? '#004872',
    'announcements_json' => $clinic['announcements_json'] ?? '[]',
    'team_json'        => $clinic['team_json']        ?? '[]',
    'hero_image'       => $clinic['hero_image']       ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuC1xIbTG7yvJ_dnNR_6565TiT6x37q1WBDSpLC-6orwCNFBV8PNvU1LG8MBljTwI6ykaAo1sk0apu72Fwnx8Kd34sY0QjrnWbLd4u4wsri9CrmkfTq5WemVWkOzq5-yO0T4FYAC-jJ0qCiXBY-qIXe8WtFskhQrPOF-E24-m9ydQZ6L1BK7Xz0QLixe9njuH_EwsSX_WFl4tYmNI4Xi68Np-4ROrt-ulUYA0yI7T1gejLd0VIZ4giBQsRVRvFb1tZNqF5ptfCDKNuo',
    'about_image_1'    => $clinic['about_image_1']    ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuAFClTvpcJUUHoP5IhpaOwWPPgz8GG6P7H5xT7efg3XWtfz-01tG_XTvOTItatWorrgb4N4vOlyH9_abFeVbVyQJGmNW8keiVgjd5cguQJCy0fU3FW09mBwcP21Y6w7VyCnogTKiwY544oFdoeIhmszgf3kgTdiX9CQQfXdbVpq1oT2b5F2TXunM1WHN0FRUL_O6ogUn2vj5IwOYtpxCyGNTDYjAUiRkt45GLttu1WNn0z2WHGhjzyFT1ZozeNWmMBLy-L4nPuF-1g',
    'about_image_2'    => $clinic['about_image_2']    ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfL5XxGL2fbsN5rWest-yN7ja8_3q1ZbAiT_yuzB2Fgx5ys1N5W9tBmfwFCQkQgHn0cqNxRsnDX-_YPKxO7-X0HSr8Zeodhe9Zg5LM6KuHoBvrxhQMDkb8QovcTugn_OUH1ZqiFfJJQX-PBr6dihZPL6v7Fe1BldTgtYfpdZ3TWsXCvvMjRyqJ3NmzQM1vyhjj3Tb6gFhPhondxzUJqMifmdm-1PgDRq-wq5JS6FjLUZH24CsmKabNUrpikLejFVuUogJWKoJvc10',
    'team_title'       => $clinic['team_title']       ?? 'The Architects of Your Smile',
    'team_subtitle'    => $clinic['team_subtitle']    ?? 'Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection.',
];

function php_e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Page Editor — <?= php_e($c['clinic_name']) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "on-background": "#191c1d",
                "secondary": "#006a62",
                "on-secondary-container": "#006f66",
                "secondary-container": "#81f3e5",
                "surface-dim": "#d8dadb",
                "primary-container": "#006097",
                "surface-container-highest": "#e1e3e4",
                "on-surface-variant": "#41474f",
                "primary-fixed-dim": "#97cbff",
                "primary-fixed": "#cee5ff",
                "surface": "#f8fafb",
                "primary": "#004872",
                "surface-container-low": "#f2f4f5",
                "surface-container-high": "#e6e8e9",
                "on-primary": "#ffffff",
                "on-primary-fixed-variant": "#004a76",
                "inverse-primary": "#97cbff",
                "secondary-fixed": "#84f5e8",
                "outline": "#717880",
                "on-secondary-fixed": "#00201d",
                "surface-variant": "#e1e3e4",
                "outline-variant": "#c0c7d1",
                "surface-container-lowest": "#ffffff",
                "on-primary-fixed": "#001d33",
                "background": "#f8fafb",
                "on-surface": "#191c1d",
                "inverse-on-surface": "#eff1f2",
                "on-error": "#ffffff",
                "error": "#ba1a1a"
            },
        },
    },
}
</script>
<style>
* { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; margin: 0; overflow: hidden; }
h1,h2,h3,h4 { font-family: 'Manrope', sans-serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; font-family: 'Material Symbols Outlined'; }

#editor-shell { display: flex; height: 100vh; width: 100vw; background: #f0f4f8; }

#sidebar { width: 272px; min-width: 272px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow: hidden; z-index: 20; }
#sidebar-header { padding: 18px 20px 14px; border-bottom: 1px solid #e2e8f0; }
#sidebar-header .editor-title { font-size: 15px; font-weight: 700; color: #0f172a; font-family: 'Manrope', sans-serif; }
#sidebar-header .tenant-label { font-size: 11px; color: #94a3b8; margin-top: 2px; }

.sidebar-tabs { display: flex; gap: 4px; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
.sidebar-tab { flex: 1; font-size: 11px; font-weight: 500; padding: 5px 0; border-radius: 6px; border: none; cursor: pointer; background: transparent; color: #64748b; text-align: center; transition: all 0.15s; }
.sidebar-tab.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }

#field-list { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 6px; }
.field-section-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.09em; color: #94a3b8; font-weight: 600; padding: 8px 4px 2px; }
.field-item { padding: 9px 12px; border-radius: 8px; border: 1px solid #f1f5f9; background: #f8fafc; cursor: pointer; transition: all 0.12s; }
.field-item:hover { border-color: #bfdbfe; background: #eff6ff; }
.field-item.active { border-color: #3b82f6; background: #eff6ff; }
.field-item .fi-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; font-weight: 600; margin-bottom: 2px; }
.field-item .fi-preview { font-size: 12px; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

#sync-btn { margin: 12px; padding: 11px; border: none; border-radius: 10px; background: #004872; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: 'Manrope', sans-serif; display: flex; align-items: center; justify-content: center; gap: 6px; }
#sync-btn:hover { background: #003a5c; }
#sync-btn.saving { opacity: 0.7; pointer-events: none; }
#sync-btn .sync-icon { font-size: 16px; }

#preview-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
#preview-topbar { height: 44px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: #fff; border-bottom: 1px solid #e2e8f0; flex-shrink: 0; }
.view-tabs { display: flex; gap: 2px; background: #f1f5f9; border-radius: 8px; padding: 3px; }
.view-tab { font-size: 11px; padding: 3px 12px; border-radius: 6px; border: none; cursor: pointer; background: transparent; color: #64748b; font-weight: 500; }
.view-tab.active { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

.topbar-right { display: flex; align-items: center; gap: 10px; }
#unsaved-badge { font-size: 11px; color: #f59e0b; font-weight: 500; display: none; }
#unsaved-badge.visible { display: block; }
.preview-action-btn { font-size: 11px; padding: 4px 12px; border-radius: 20px; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-weight: 500; transition: all 0.12s; }
.preview-action-btn:hover { border-color: #004872; color: #004872; }
.preview-action-btn.primary { background: #004872; color: #fff; border-color: #004872; }
.preview-action-btn.primary:hover { background: #003a5c; }

#preview-viewport { flex: 1; overflow: hidden; position: relative; }
#preview-iframe { width: 100%; height: 100%; border: none; display: block; background: #fff; transition: width 0.3s ease; }
#preview-viewport.mobile-view #preview-iframe { width: 390px; margin: 0 auto; box-shadow: 0 0 0 1px #e2e8f0, 0 8px 40px rgba(0,0,0,0.12); border-radius: 0 0 8px 8px; height: calc(100% - 12px); margin-top: 12px; }

#right-panel { width: 248px; min-width: 248px; background: #fff; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow: hidden; }
#rp-header { padding: 14px 16px 12px; border-bottom: 1px solid #e2e8f0; }
#rp-header h3 { font-size: 13px; font-weight: 700; color: #0f172a; font-family: 'Manrope', sans-serif; }
#rp-header p { font-size: 11px; color: #94a3b8; margin-top: 2px; }
#rp-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 12px; }

.rp-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #94a3b8; text-align: center; padding: 24px; }
.rp-empty .rp-empty-icon { font-size: 32px; opacity: 0.4; }
.rp-empty p { font-size: 12px; line-height: 1.6; }

.rp-field { display: flex; flex-direction: column; gap: 5px; }
.rp-field label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 600; }
.rp-field input, .rp-field textarea { font-size: 12px; padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 7px; background: #f8fafc; color: #1e293b; width: 100%; font-family: 'Inter', sans-serif; }
.rp-field input:focus, .rp-field textarea:focus { outline: none; border-color: #3b82f6; background: #fff; }
.rp-field textarea { min-height: 72px; resize: vertical; }

.color-swatches { display: flex; gap: 7px; flex-wrap: wrap; padding: 4px 0; }
.color-swatch { width: 24px; height: 24px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: transform 0.12s; }
.color-swatch:hover { transform: scale(1.15); }
.color-swatch.selected { outline: 2px solid #1e293b; outline-offset: 2px; }

.toggle-row { display: flex; align-items: center; justify-content: space-between; }
.toggle-label { font-size: 12px; color: #1e293b; }
.toggle-switch { width: 36px; height: 20px; background: #e2e8f0; border-radius: 10px; cursor: pointer; position: relative; transition: background 0.2s; border: none; }
.toggle-switch.on { background: #004872; }
.toggle-switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: left 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.toggle-switch.on::after { left: 18px; }

.apply-btn { margin: 0 14px 14px; padding: 9px; border: none; border-radius: 8px; background: #004872; color: #fff; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: 'Manrope', sans-serif; }
.apply-btn:hover { background: #003a5c; }

#toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(16px); background: #1e293b; color: #fff; font-size: 13px; font-weight: 500; padding: 10px 20px; border-radius: 8px; opacity: 0; pointer-events: none; z-index: 999; transition: all 0.25s; display: flex; align-items: center; gap: 6px; }
#toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
#toast.error { background: #b91c1c; }
</style>
</head>
<body>

<div id="editor-shell">
    <div id="sidebar">
        <div id="sidebar-header">
            <div class="editor-title">Page Editor</div>
            <div class="tenant-label"><?= php_e($c['clinic_name']) ?></div>
        </div>
        <div class="sidebar-tabs">
            <button class="sidebar-tab active" data-tab="content">Content</button>
            <button class="sidebar-tab" data-tab="colors">Colors</button>
            <button class="sidebar-tab" data-tab="team">Team</button>
        </div>
        <div id="field-list">
            <div class="field-section-label">Navigation</div>
            <div class="field-item" data-field="clinic_name">
                <div class="fi-label">Clinic Name</div>
                <div class="fi-preview"><?= php_e($c['clinic_name']) ?></div>
            </div>
            <div class="field-item" data-field="cta_primary">
                <div class="fi-label">Book Button</div>
                <div class="fi-preview"><?= php_e($c['cta_primary']) ?></div>
            </div>
            <div class="field-section-label">Hero Section</div>
            <div class="field-item" data-field="badge">
                <div class="fi-label">Badge</div>
                <div class="fi-preview"><?= php_e($c['badge_text']) ?></div>
            </div>
            <div class="field-item" data-field="hero_title">
                <div class="fi-label">Headline</div>
                <div class="fi-preview"><?= php_e($c['hero_title']) ?></div>
            </div>
            <div class="field-item" data-field="hero_image">
                <div class="fi-label">Hero Image</div>
                <div class="fi-preview">Update Image</div>
            </div>
            <div class="field-section-label">About Section</div>
            <div class="field-item" data-field="about_description">
                <div class="fi-label">About Text</div>
                <div class="fi-preview"><?= php_e($c['about_description']) ?></div>
            </div>
            <div class="field-item" data-field="about_images">
                <div class="fi-label">Gallery</div>
                <div class="fi-preview">Update Images</div>
            </div>
            <div class="field-item" data-field="checklist">
                <div class="fi-label">Checklist</div>
                <div class="fi-preview"><?= php_e($c['checklist_1']) ?>...</div>
            </div>
            <div class="field-section-label">Announcements</div>
            <div class="field-item" data-field="announcements">
                <div class="fi-label">Pulse Posts</div>
                <div class="fi-preview">Edit Posts</div>
            </div>
            <div class="field-section-label">Footer</div>
            <div class="field-item" data-field="footer_copyright">
                <div class="fi-label">Copyright</div>
                <div class="fi-preview"><?= php_e($c['footer_copyright']) ?></div>
            </div>
        </div>
        <button id="sync-btn"><span class="material-symbols-outlined sync-icon">sync</span>Sync to Live Site</button>
    </div>

    <div id="preview-wrap">
        <div id="preview-topbar">
            <div class="view-tabs">
                <button class="view-tab active" data-view="desktop">Desktop</button>
                <button class="view-tab" data-view="mobile">Mobile</button>
            </div>
            <div class="topbar-right">
                <span id="unsaved-badge">● Unsaved changes</span>
                <button class="preview-action-btn" onclick="window.close()">Back</button>
                <button class="preview-action-btn" id="undo-btn">Undo</button>
                <button class="preview-action-btn primary" onclick="window.open('tenant_homepage.php?tenant=<?= urlencode($tenantSlug) ?>', '_blank')">View Live</button>
            </div>
        </div>
        <div id="preview-viewport">
            <iframe id="preview-iframe" src="tenant_homepage.php?tenant=<?= urlencode($tenantSlug) ?>" sandbox="allow-same-origin allow-scripts"></iframe>
        </div>
    </div>

    <div id="right-panel">
        <div id="rp-header"><h3 id="rp-title">Select a field</h3><p id="rp-subtitle">Click anywhere to start</p></div>
        <div id="rp-body">
            <div class="rp-empty" id="rp-empty-state"><span class="material-symbols-outlined rp-empty-icon">edit_note</span><p>Select a field to begin editing.</p></div>
        </div>
        <button class="apply-btn" id="apply-btn" style="display:none">Apply Changes</button>
    </div>
</div>

<input type="file" id="hidden-file-input" style="display:none" accept="image/*">

<div id="toast"><span class="material-symbols-outlined" style="font-size:16px;">check_circle</span><span id="toast-msg">Saved.</span></div>

<script>
// ════════════════════════════════════════════════════════════════════════
//  STATE & HELPERS
// ════════════════════════════════════════════════════════════════════════
const tenantSlug = '<?= urlencode($tenantSlug) ?>';
const INITIAL = <?= json_encode($c) ?>;

let state = { ...INITIAL };
let history = [{ ...INITIAL }];
let historyIdx = 0;
let activeField = null;
let unsaved = false;
let toastTimer;
let nestedSaveTimer = null; // Debounce timer for nested field saves

const iframe    = document.getElementById('preview-iframe');
const rpBody    = document.getElementById('rp-body');
const rpTitle   = document.getElementById('rp-title');
const rpSub     = document.getElementById('rp-subtitle');
const applyBtn  = document.getElementById('apply-btn');
const syncBtn   = document.getElementById('sync-btn');
const undoBadge = document.getElementById('unsaved-badge');
const toast     = document.getElementById('toast');
const toastMsg  = document.getElementById('toast-msg');
const viewport  = document.getElementById('preview-viewport');

function e(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function field(key, label, type, value) {
    const tag = type === 'textarea' ? 'textarea' : 'input';
    return `<div class="rp-field"><label>${label}</label><${tag} data-key="${key}">${tag==='textarea'?e(value):''}</${tag}></div>`;
}

function imageField(key, label, value) {
    return `
        <div class="rp-field">
            <label>${label}</label>
            <div class="flex gap-2">
                <input type="text" data-key="${key}" value="${e(value)}" class="flex-1 text-xs">
                <button onclick="triggerUpload('${key}')" class="p-1 px-2 bg-slate-100 rounded border hover:bg-slate-200 transition-colors" title="Upload from device">
                    <span class="material-symbols-outlined text-sm mt-1">upload</span>
                </button>
            </div>
        </div>
    `;
}

function toggleRow(key, label, checked) {
    return `<div class="rp-field"><div class="toggle-row"><span class="toggle-label">${label}</span><button class="toggle-switch ${checked ? 'on' : ''}" data-toggle="${key}"></button></div></div>`;
}

// ════════════════════════════════════════════════════════════════════════
//  FIELD DEFINITIONS
// ════════════════════════════════════════════════════════════════════════
const FIELDS = {
    clinic_name: { label: 'Clinic Name', sub: 'Nav & Footer', render: () => field('clinic_name', 'Name', 'input', state.clinic_name) },
    cta_primary: { label: 'Book Button', sub: 'Primary CTA', render: () => field('cta_primary', 'Label', 'input', state.cta_primary) },
    badge: { label: 'Hero Badge', sub: 'Tagline', render: () => toggleRow('badge_visible', 'Show', state.badge_visible==='1') + field('badge_text', 'Text', 'input', state.badge_text) },
    hero_title: { label: 'Headline', sub: 'Hero main text', render: () => field('hero_title', 'Title', 'textarea', state.hero_title) },
    hero_image: { label: 'Hero Image', sub: 'Main image', render: () => imageField('hero_image', 'Image', state.hero_image) },
    about_description: { label: 'About Text', sub: 'Intro paragraph', render: () => field('about_description', 'Content', 'textarea', state.about_description) },
    about_images: { label: 'Gallery', sub: 'Section images', render: () => imageField('about_image_1', 'Image 1', state.about_image_1) + imageField('about_image_2', 'Image 2', state.about_image_2) },
    checklist: { label: 'Checklist', sub: '3 bullet points', render: () => field('checklist_1', 'Item 1', 'input', state.checklist_1) + field('checklist_2', 'Item 2', 'input', state.checklist_2) + field('checklist_3', 'Item 3', 'input', state.checklist_3) },
    footer_copyright: { label: 'Copyright', sub: 'Bottom line', render: () => field('footer_copyright', 'Text', 'input', state.footer_copyright) },
    announcements: {
        label: 'Pulse Posts', sub: 'News feed',
        render: () => {
            let data = []; try { data = JSON.parse(state.announcements_json || '[]'); } catch(err){}
            return `<div class="space-y-4">${data.map((it, i) => `
                <div class="bg-slate-50 p-3 rounded-lg border relative">
                    <button onclick="removeListItem('announcements_json', ${i})" class="absolute top-1 right-1 text-red-500"><span class="material-symbols-outlined text-xs">delete</span></button>
                    ${field(`announcements_json.${i}.title`, 'Title', 'input', it.title)}
                    ${field(`announcements_json.${i}.description`, 'Text', 'textarea', it.description)}
                </div>`).join('')}
                <button onclick="addListItem('announcements_json')" class="w-full py-2 border-2 border-dashed rounded">+ Add</button>
            </div>`;
        }
    }
};

const COLOR_FIELDS = {
    accent_color: {
        label: 'Brand Color', sub: 'Primary accent',
        render: () => {
            const presets = ['#004872','#006a62','#7c3aed','#be185d','#b45309','#0f172a','#0369a1','#059669','#dc2626','#d97706','#7e22ce','#0f766e'];
            const cur = state.accent_color || '#004872';
            return '<div class="rp-field"><label>Color Picker</label><div style="display:flex;align-items:center;gap:10px;">'
                + '<input type="color" id="accent-color-picker" value="' + cur + '" style="width:48px;height:36px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;padding:2px;background:#f8fafc;">'
                + '<input type="text" data-key="accent_color" value="' + cur + '" style="flex:1;font-size:12px;font-family:monospace;" placeholder="#000000" maxlength="7">'
                + '<div style="width:36px;height:36px;border-radius:8px;border:1px solid #e2e8f0;flex-shrink:0;background:' + cur + '" id="accent-color-preview"></div>'
                + '</div></div>'
                + '<div class="rp-field"><label>Preset Colors</label><div class="color-swatches">'
                + presets.map(c => '<div class="color-swatch ' + (cur===c?'selected':'') + '" style="background:' + c + '" data-color="' + c + '" title="' + c + '"></div>').join('')
                + '</div></div>';
        }
    }
};

const TEAM_FIELDS = {
    team: {
        label: 'Team Section', sub: 'Headline & Staff profiles',
        render: () => {
            let data = []; try { data = JSON.parse(state.team_json || '[]'); } catch(err){}
            return `
                <div class="mb-6 p-4 bg-blue-50/50 rounded-xl border border-blue-100 space-y-4">
                    <div class="font-bold text-xs uppercase tracking-wider text-blue-900 mb-1">Section Header</div>
                    ${field('team_title', 'Team Headline', 'input', state.team_title)}
                    ${field('team_subtitle', 'Team Subtitle', 'textarea', state.team_subtitle)}
                </div>
                <div class="font-bold text-xs uppercase tracking-wider text-slate-700 mb-2 px-1">Team Members</div>
                <div class="space-y-4">${data.map((it, i) => `
                    <div class="bg-slate-50 p-3 rounded-lg border relative">
                        <button onclick="removeListItem('team_json', ${i})" class="absolute top-1 right-1 text-red-500"><span class="material-symbols-outlined text-xs">delete</span></button>
                        ${field(`team_json.${i}.name`, 'Name', 'input', it.name)}
                        ${field(`team_json.${i}.role`, 'Role', 'input', it.role)}
                        ${imageField(`team_json.${i}.image`, 'Profile Image', it.image)}
                    </div>`).join('')}
                    <button onclick="addListItem('team_json')" class="w-full py-2 border-2 border-dashed rounded">+ Add Member</button>
                </div>`;
        }
    }
};

// ════════════════════════════════════════════════════════════════════════
//  CORE LOGIC
// ════════════════════════════════════════════════════════════════════════
function openField(key, tab = 'content') {
    activeField = key;
    document.querySelectorAll('.field-item').forEach(el => el.classList.toggle('active', el.dataset.field === key));
    
    // Determine target tab based on the key
    let determinedTab = tab;
    if (key === 'accent_color') determinedTab = 'colors';
    else if (key === 'team' || key === 'team_title' || key === 'team_subtitle') determinedTab = 'team';
    
    // Dynamically update active tab styling in the left sidebar
    document.querySelectorAll('.sidebar-tab').forEach(el => {
        el.classList.toggle('active', el.dataset.tab === determinedTab);
    });
    
    let def;
    if (determinedTab === 'colors' || key === 'accent_color') { def = COLOR_FIELDS.accent_color; }
    else if (determinedTab === 'team' || key === 'team') { def = TEAM_FIELDS.team; }
    else { def = FIELDS[key]; }
    
    if (!def) return;
    rpTitle.textContent = def.label;
    rpSub.textContent = def.sub;
    rpBody.innerHTML = def.render();
    applyBtn.style.display = 'block';

    // Send postMessage to scroll to the field in the live preview
    iframe.contentWindow?.postMessage({ type: 'scroll-to-field', key: key }, '*');

    // Wire inputs (skip accent_color — handled by the color picker block below)
    rpBody.querySelectorAll('input, textarea').forEach(el => {
        if (!el.dataset.key) return;
        if (el.dataset.key === 'accent_color') return;
        el.value = getVal(el.dataset.key);
        el.addEventListener('focus', pushToHistory);
        el.addEventListener('input', async () => {
            const oldClinicName = state.clinic_name;
            setVal(el.dataset.key, el.value);
            
            if (el.dataset.key === 'clinic_name') {
                const newName = el.value;
                
                // Auto-propagate clinic name changes to key templates if they mention the old name
                if (state.hero_title === oldClinicName) {
                    state.hero_title = newName;
                    updatePreview('hero_title', newName);
                    refreshSidebarItem('hero_title');
                }
                
                if (state.hero_description.includes(oldClinicName)) {
                    state.hero_description = state.hero_description.replaceAll(oldClinicName, newName);
                    updatePreview('hero_description', state.hero_description);
                    refreshSidebarItem('hero_description');
                }
                
                if (state.about_description.includes(oldClinicName)) {
                    state.about_description = state.about_description.replaceAll(oldClinicName, newName);
                    updatePreview('about_description', state.about_description);
                    refreshSidebarItem('about_description');
                }
                
                if (state.footer_copyright.includes(oldClinicName)) {
                    state.footer_copyright = state.footer_copyright.replaceAll(oldClinicName, newName);
                    updatePreview('footer_copyright', state.footer_copyright);
                    refreshSidebarItem('footer_copyright');
                }
                
                // Keep the sidebar header clinic name in sync
                const tenantLabel = document.querySelector('.tenant-label');
                if (tenantLabel) tenantLabel.textContent = newName;
            }
            
            // For nested fields (announcements, team), save and refresh the preview immediately
            if (el.dataset.key.includes('.')) {
                markUnsaved();
                // Debounce the save and apply incremental preview updates for nested content
                clearTimeout(nestedSaveTimer);
                nestedSaveTimer = setTimeout(async () => {
                    await autoSaveQuietly();
                    if (el.dataset.key.startsWith('announcements_json.')) {
                        const [, itemIndex, itemField] = el.dataset.key.split('.');
                        iframe.contentWindow?.postMessage({
                            type: 'update-announcement',
                            index: Number(itemIndex),
                            field: itemField,
                            value: el.value
                        }, '*');
                    } else if (el.dataset.key.startsWith('team_json.')) {
                        const [, itemIndex, itemField] = el.dataset.key.split('.');
                        iframe.contentWindow?.postMessage({
                            type: 'update-team-member',
                            index: Number(itemIndex),
                            field: itemField,
                            value: el.value
                        }, '*');
                    } else {
                        iframe.contentWindow?.postMessage({ type: 'refresh' }, '*');
                    }
                }, 500);
            } else {
                markUnsaved();
                updatePreview(el.dataset.key, el.value);
                refreshSidebarItem(el.dataset.key);
            }
        });
    });

    // Wire toggles
    rpBody.querySelectorAll('[data-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            pushToHistory();
            const k = btn.dataset.toggle;
            const isOn = btn.classList.toggle('on');
            state[k] = isOn ? '1' : '0';
            markUnsaved();
            updatePreview(k, state[k]);
        });
    });

    // Wire color picker + hex input + swatches
    const colorPicker = rpBody.querySelector('#accent-color-picker');
    const colorPreviewBox = rpBody.querySelector('#accent-color-preview');
    function applyAccentColor(hex) {
        if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return;
        pushToHistory();
        state.accent_color = hex;
        if (colorPicker) colorPicker.value = hex;
        const hexIn = rpBody.querySelector('[data-key="accent_color"]');
        if (hexIn) hexIn.value = hex;
        if (colorPreviewBox) colorPreviewBox.style.background = hex;
        rpBody.querySelectorAll('.color-swatch').forEach(sw => sw.classList.toggle('selected', sw.dataset.color === hex));
        markUnsaved();
        updatePreview('accent_color', hex);
    }
    if (colorPicker) {
        colorPicker.addEventListener('input', () => applyAccentColor(colorPicker.value));
    }
    const hexTextInput = rpBody.querySelector('[data-key="accent_color"]');
    if (hexTextInput) {
        hexTextInput.addEventListener('input', () => applyAccentColor(hexTextInput.value));
    }
    rpBody.querySelectorAll('.color-swatch').forEach(sw => {
        sw.addEventListener('click', () => applyAccentColor(sw.dataset.color));
    });
}

let uploadTargetKey = null;
function triggerUpload(key) {
    uploadTargetKey = key;
    document.getElementById('hidden-file-input').click();
}

document.getElementById('hidden-file-input').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file || !uploadTargetKey) return;

    const formData = new FormData();
    formData.append('image', file);

    showToast('Uploading image...');
    try {
        const res = await fetch(`upload_homepage_image.php?tenant=${encodeURIComponent(tenantSlug)}`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            setVal(uploadTargetKey, data.url);
            markUnsaved();
            updatePreview(uploadTargetKey, data.url);
            refreshSidebarItem(uploadTargetKey);
            // Refresh the current field UI to show the new URL
            if (activeField) openField(activeField); 
            showToast('Uploaded successfully!');
        } else {
            showToast(data.message || 'Upload failed', true);
        }
    } catch (err) {
        showToast('Upload error occurred', true);
    }
    e.target.value = ''; // Reset file input
});

function getVal(path) {
    if (!path.includes('.')) return state[path];
    const p = path.split('.');
    let d = JSON.parse(state[p[0]] || '[]');
    return d[p[1]]?.[p[2]] || '';
}

function setVal(path, val) {
    if (!path.includes('.')) { state[path] = val; return; }
    const p = path.split('.');
    let d = JSON.parse(state[p[0]] || '[]');
    if (!d[p[1]]) d[p[1]] = {};
    d[p[1]][p[2]] = val;
    state[p[0]] = JSON.stringify(d);
}

async function addListItem(key) {
    pushToHistory();
    let d = JSON.parse(state[key] || '[]');
    if (key === 'team_json') d.push({ name: 'New Member', role: 'Role', description: '', image: '', tags: [] });
    else d.push({ date: new Date().toLocaleDateString(), title: 'New Post', description: '' });
    state[key] = JSON.stringify(d);
    openField(key === 'team_json' ? 'team' : 'announcements');
    markUnsaved();
    
    // Save before updating preview state
    await autoSaveQuietly();
    if (key === 'announcements_json') sendAnnouncementsState();
    else if (key === 'team_json') sendTeamState();
}

async function removeListItem(key, i) {
    pushToHistory();
    let d = JSON.parse(state[key] || '[]');
    d.splice(i, 1);
    state[key] = JSON.stringify(d);
    openField(key === 'team_json' ? 'team' : 'announcements');
    markUnsaved();
    
    // Save before updating preview state
    await autoSaveQuietly();
    if (key === 'announcements_json') sendAnnouncementsState();
    else if (key === 'team_json') sendTeamState();
}

function sendAnnouncementsState() {
    iframe.contentWindow?.postMessage({ type: 'set-announcements', announcements: JSON.parse(state.announcements_json || '[]') }, '*');
}

function sendTeamState() {
    iframe.contentWindow?.postMessage({ type: 'set-team', team: JSON.parse(state.team_json || '[]'), title: state.team_title, subtitle: state.team_subtitle }, '*');
}

function updatePreview(key, value) {
    if (key === 'announcements_json') {
        iframe.contentWindow?.postMessage({ type: 'set-announcements', announcements: JSON.parse(value || '[]') }, '*');
        return;
    }
    if (key === 'team_json') {
        iframe.contentWindow?.postMessage({ type: 'set-team', team: JSON.parse(value || '[]'), title: state.team_title, subtitle: state.team_subtitle }, '*');
        return;
    }
    iframe.contentWindow?.postMessage({ type: 'update', key, value }, '*');
}

function refreshAllPreview() {
    // Send accent_color first so element overrides are applied before other updates
    if (state.accent_color) updatePreview('accent_color', state.accent_color);
    Object.entries(state).forEach(([k, v]) => { if (k !== 'accent_color') updatePreview(k, v); });
}

// ════════════════════════════════════════════════════════════════════════
//  IFRAME INJECTION
// ════════════════════════════════════════════════════════════════════════
iframe.addEventListener('load', () => {
    try {
        const doc = iframe.contentDocument; if (!doc) return;
        const script = doc.createElement('script');
        script.textContent = `
        (function() {
            const MAP = {
                clinic_name: ['nav .text-2xl', 'footer .text-xl'],
                hero_title: ['section h1'],
                hero_description: ['section h1 ~ p'],
                cta_primary: ['nav button', 'section .flex button:first-child'],
                badge_text: ['.inline-flex .text-xs.uppercase'],
                badge_visible: ['.inline-flex[class*="bg-secondary-fixed"]'],
                about_description: ['#about p.text-on-surface-variant'],
                checklist_1: ['#about .space-y-4 > div:nth-child(1) span:last-child'],
                checklist_2: ['#about .space-y-4 > div:nth-child(2) span:last-child'],
                checklist_3: ['#about .space-y-4 > div:nth-child(3) span:last-child'],
                footer_copyright: ['footer .text-slate-400'],
                hero_image: ['#hero-image'],
                about_image_1: ['#about-image-1'],
                about_image_2: ['#about-image-2'],
                team_title: ['#team h2'],
                team_subtitle: ['#team p.font-body']
            };
            window.addEventListener('message', e => {
                const { type, key, value, announcements, index, field, team, title, subtitle } = e.data || {};
                if (type === 'refresh') { location.reload(); return; }
                if (type === 'scroll-to-field') {
                    let selector = (MAP[key] || [])[0];
                    if (key === 'about_images') selector = '#about-image-1';
                    else if (key === 'checklist') selector = '#about .space-y-4';
                    else if (key === 'announcements') selector = '#schedule';
                    else if (key === 'team' || key === 'team_title' || key === 'team_subtitle') selector = '#team';
                    else if (key === 'badge') selector = '.inline-flex[class*="bg-secondary-fixed"]';
                    
                    if (selector) {
                        const targetEl = document.querySelector(selector);
                        if (targetEl) {
                            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            const oldOutline = targetEl.style.outline;
                            const oldOffset = targetEl.style.outlineOffset;
                            targetEl.style.outline = '3px solid #3b82f6';
                            targetEl.style.outlineOffset = '4px';
                            setTimeout(() => {
                                targetEl.style.outline = oldOutline;
                                targetEl.style.outlineOffset = oldOffset;
                            }, 1500);
                        }
                    }
                    return;
                }
                if (type === 'set-announcements') {
                    const wrapper = document.querySelector('.bg-primary .space-y-8');
                    if (!wrapper) return;
                    wrapper.innerHTML = (announcements || []).map(function(announcement, idx) {
                        return '<div class="announcement-item" data-index="' + idx + '">' +
                            '<span class="text-xs uppercase font-bold tracking-widest opacity-60">' + (announcement.date ? announcement.date : '') + '</span>' +
                            '<h4 class="font-bold text-lg mt-1">' + (announcement.title ? announcement.title : '') + '</h4>' +
                            '<p class="text-on-primary/70 text-sm mt-2">' + (announcement.description ? announcement.description : '') + '</p>' +
                        '</div>';
                    }).join('');
                    return;
                }
                if (type === 'update-announcement') {
                    const item = document.querySelector('.announcement-item[data-index="' + index + '"]');
                    if (!item) return;
                    if (field === 'title') {
                        const heading = item.querySelector('h4');
                        if (heading) heading.textContent = value;
                    } else if (field === 'description') {
                        const paragraph = item.querySelector('p');
                        if (paragraph) paragraph.textContent = value;
                    } else if (field === 'date') {
                        const span = item.querySelector('span');
                        if (span) span.textContent = value;
                    }
                    return;
                }
                if (type !== 'update') return;
                if (key === 'accent_color') {
                    function darken(hex, pct) {
                        let n = parseInt(hex.replace('#',''), 16);
                        let r = Math.max(0, (n>>16) - Math.round(((n>>16)*pct)/100));
                        let g = Math.max(0, ((n>>8)&0xff) - Math.round((((n>>8)&0xff)*pct)/100));
                        let b = Math.max(0, (n&0xff) - Math.round(((n&0xff)*pct)/100));
                        return '#' + [r,g,b].map(x=>x.toString(16).padStart(2,'0')).join('');
                    }
                    const dark = darken(value, 18);
                    // Apply color directly to elements — Tailwind gradient classes can't be overridden via CSS vars at runtime
                    document.querySelectorAll('.bg-primary').forEach(el => { el.style.backgroundColor = value; });
                    document.querySelectorAll('.text-primary').forEach(el => { el.style.color = value; });
                    document.querySelectorAll('.border-primary').forEach(el => { el.style.borderColor = value; });
                    // Nav CTA button uses bg-gradient-to-r from-primary to-primary-container
                    document.querySelectorAll('nav button').forEach(el => { el.style.background = 'linear-gradient(to right, ' + value + ', ' + dark + ')'; });
                    // Hero CTA button
                    document.querySelectorAll('section .flex button:first-child').forEach(el => { el.style.backgroundColor = value; });
                    // About stat block
                    document.querySelectorAll('#about .bg-primary').forEach(el => { el.style.backgroundColor = value; });
                    // Announcements panel
                    document.querySelectorAll('.bg-primary.text-on-primary').forEach(el => { el.style.backgroundColor = value; });
                    return;
                }
                if (key === 'badge_visible') { 
                    const b = document.querySelector('.inline-flex[class*="bg-secondary-fixed"]');
                    if (b) b.style.display = value === '1' ? '' : 'none';
                    return;
                }
                (MAP[key]||[]).forEach(sel => {
                    document.querySelectorAll(sel).forEach(el => {
                        if (el.tagName === 'IMG') el.src = value;
                        else el.textContent = value;
                    });
                });
            });
            const EDITABLE = [
                { sel: 'nav .text-2xl', k: 'clinic_name' },
                { sel: 'section h1', k: 'hero_title' },
                { sel: 'section h1 ~ p', k: 'hero_description' },
                { sel: '#about p.text-on-surface-variant', k: 'about_description' },
                { sel: '#about-image-1', k: 'about_images' },
                { sel: '#about-image-2', k: 'about_images' },
                { sel: '#hero-image', k: 'hero_image' },
                { sel: '.team-member-card', k: 'team' },
                { sel: '.announcement-item', k: 'announcements' },
                { sel: '#team h2', k: 'team' },
                { sel: '#team p.font-body', k: 'team' }
            ];
            const s = document.createElement('style');
            s.textContent = '.ez-edit { cursor: pointer !important; outline: 2px dashed transparent; transition: 0.2s; position: relative; } .ez-edit:hover { outline-color: #3b82f6; outline-offset: 2px; z-index: 50; }';
            document.head.appendChild(s);
            EDITABLE.forEach(ed => {
                document.querySelectorAll(ed.sel).forEach(el => {
                    el.classList.add('ez-edit');
                    if (el.tagName === 'IMG') el.style.display = 'block';
                    el.addEventListener('click', e => {
                        e.preventDefault(); e.stopPropagation();
                        window.parent.postMessage({ type: 'field-click', key: ed.k }, '*');
                    });
                });
            });
        })();`;
        doc.head.appendChild(script);
    } catch(err){}
});

window.addEventListener('message', e => { if (e.data?.type === 'field-click') openField(e.data.key); });

// ════════════════════════════════════════════════════════════════════════
//  UI EVENTS
// ════════════════════════════════════════════════════════════════════════
document.querySelectorAll('.field-item').forEach(it => it.addEventListener('click', () => openField(it.dataset.field)));
document.querySelectorAll('.sidebar-tab').forEach(t => t.addEventListener('click', () => {
    document.querySelectorAll('.sidebar-tab').forEach(el => el.classList.remove('active'));
    t.classList.add('active');
    openField(t.dataset.tab === 'colors' ? 'accent_color' : (t.dataset.tab === 'team' ? 'team' : 'clinic_name'), t.dataset.tab);
}));

document.querySelectorAll('.view-tab').forEach(t => t.addEventListener('click', () => {
    document.querySelectorAll('.view-tab').forEach(el => el.classList.remove('active'));
    t.classList.add('active');
    viewport.classList.toggle('mobile-view', t.dataset.view === 'mobile');
}));

applyBtn.addEventListener('click', () => { pushToHistory(); refreshAllPreview(); showToast('Applied.'); });
document.getElementById('undo-btn').addEventListener('click', () => {
    if (historyIdx > 0) { historyIdx--; state = { ...history[historyIdx] }; refreshAllPreview(); if (activeField) openField(activeField); showToast('Undone.'); }
});

async function autoSaveQuietly() {
    try {
        await fetch('', { method: 'POST', body: new URLSearchParams({ ajax_save: '1', ...state }) });
    } catch (err) { console.error('Auto-save failed', err); }
}

syncBtn.addEventListener('click', async () => {
    syncBtn.classList.add('saving'); syncBtn.querySelector('.sync-icon').textContent = 'hourglass_empty';
    try {
        const res = await fetch('', { method: 'POST', body: new URLSearchParams({ ajax_save: '1', ...state }) });
        const res_1 = await res.json();
        if (res_1.ok) { markSaved(); showToast('Synced!'); } else showToast('Error: ' + (res_1.error || 'Unknown'), true);
    } catch { showToast('Network Error', true); } finally {
        syncBtn.classList.remove('saving'); syncBtn.querySelector('.sync-icon').textContent = 'sync';
    }
});

function pushToHistory() {
    if (JSON.stringify(state) !== JSON.stringify(history[historyIdx])) {
        history = history.slice(0, historyIdx + 1);
        history.push({ ...state });
        historyIdx++;
    }
}
function markUnsaved() { unsaved = true; undoBadge.classList.add('visible'); }
function markSaved() { unsaved = false; undoBadge.classList.remove('visible'); }
function showToast(msg, err = false) {
    clearTimeout(toastTimer); toastMsg.textContent = msg; toast.classList.toggle('error', err); toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2000);
}
function refreshSidebarItem(key) {
    const el = document.querySelector(`.field-item[data-field="${key}"] .fi-preview`);
    if (el) el.textContent = state[key];
}
window.addEventListener('beforeunload', e => { if (unsaved) { e.preventDefault(); e.returnValue = ''; } });
</script>
</body>
</html>
