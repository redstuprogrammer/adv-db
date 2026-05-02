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

    $allowed = [
        'clinic_name', 'hero_title', 'hero_description',
        'about_description', 'footer_copyright',
        'badge_visible', 'badge_text',
        'stat_number', 'stat_label',
        'checklist_1', 'checklist_2', 'checklist_3',
        'cta_primary', 'cta_secondary',
        'accent_color',
    ];

    $fields = [];
    $params = [];
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $fields[] = "$key = ?";
            $params[] = $_POST[$key];
        }
    }

    if ($fields) {
        // Upsert: insert row for this tenant if it doesn't exist yet
        $check = $conn->prepare("SELECT id FROM clinic_settings WHERE tenant_id = ?");
        $check->bind_param("i", $tenant_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists) {
            $params[] = $tenant_id;
            $sql = "UPDATE clinic_settings SET " . implode(', ', $fields) . " WHERE tenant_id = ?";
            $stmt = $conn->prepare($sql);
        } else {
            // Build INSERT … ON DUPLICATE KEY UPDATE or plain insert
            $params[] = $tenant_id;
            $colNames = array_map(fn($f) => explode(' =', $f)[0], $fields);
            $colNames[] = 'tenant_id';
            $placeholders = implode(', ', array_fill(0, count($params), '?'));
            $sql = "INSERT INTO clinic_settings (" . implode(', ', $colNames) . ") VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
        }

        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    echo json_encode(['ok' => true]);
    exit;
}

// ── Fetch this tenant's homepage settings ─────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM clinic_settings WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$clinic = $stmt->get_result()->fetch_assoc() ?: [];

// Fallback defaults
$c = [
    'clinic_name'      => $clinic['clinic_name']      ?? 'Your Clinic',
    'hero_title'       => $clinic['hero_title']        ?? 'Exhale the Ordinary.',
    'hero_description' => $clinic['hero_description']  ?? 'Experience a new standard of dental care where precision engineering meets a curated, calming environment.',
    'about_description'=> $clinic['about_description'] ?? 'At The Curated Breath, we believe that world-class dentistry should never feel clinical.',
    'contact_phone'    => $clinic['contact_phone']     ?? '+1 (555) 890-2344',
    'contact_email'    => $clinic['contact_email']     ?? 'concierge@yourclinic.com',
    'contact_address'  => $clinic['contact_address']   ?? '1422 Serenity Blvd, Suite 400, Medical District',
    'footer_copyright' => $clinic['footer_copyright']  ?? '© 2024 Your Clinic. Professional Dental Serenity.',
    'badge_visible'    => $clinic['badge_visible']     ?? '1',
    'badge_text'       => $clinic['badge_text']        ?? 'Clinical Serenity',
    'stat_number'      => $clinic['stat_number']       ?? '98%',
    'stat_label'       => $clinic['stat_label']        ?? 'Patient Comfort Index',
    'checklist_1'      => $clinic['checklist_1']       ?? 'Curated Acoustic Environments',
    'checklist_2'      => $clinic['checklist_2']       ?? 'Bio-compatible Premium Materials',
    'checklist_3'      => $clinic['checklist_3']       ?? 'Post-treatment Serenity Lounges',
    'cta_primary'      => $clinic['cta_primary']       ?? 'Book Appointment',
    'cta_secondary'    => $clinic['cta_secondary']     ?? 'Explore Services',
    'accent_color'     => $clinic['accent_color']      ?? '#004872',
];

function e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Page Editor — <?= e($c['clinic_name']) ?></title>
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

/* ── Layout shell ── */
#editor-shell { display: flex; height: 100vh; width: 100vw; background: #f0f4f8; }

/* ── Left sidebar ── */
#sidebar {
    width: 272px; min-width: 272px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    display: flex; flex-direction: column;
    overflow: hidden;
    z-index: 20;
}
#sidebar-header {
    padding: 18px 20px 14px;
    border-bottom: 1px solid #e2e8f0;
}
#sidebar-header .editor-title { font-size: 15px; font-weight: 700; color: #0f172a; font-family: 'Manrope', sans-serif; }
#sidebar-header .tenant-label { font-size: 11px; color: #94a3b8; margin-top: 2px; }

.sidebar-tabs { display: flex; gap: 4px; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
.sidebar-tab {
    flex: 1; font-size: 11px; font-weight: 500; padding: 5px 0;
    border-radius: 6px; border: none; cursor: pointer;
    background: transparent; color: #64748b; text-align: center;
    transition: all 0.15s;
}
.sidebar-tab.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }

#field-list { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 6px; }

.field-section-label {
    font-size: 10px; text-transform: uppercase; letter-spacing: 0.09em;
    color: #94a3b8; font-weight: 600; padding: 8px 4px 2px;
}
.field-item {
    padding: 9px 12px; border-radius: 8px;
    border: 1px solid #f1f5f9;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.12s;
}
.field-item:hover { border-color: #bfdbfe; background: #eff6ff; }
.field-item.active { border-color: #3b82f6; background: #eff6ff; }
.field-item .fi-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; font-weight: 600; margin-bottom: 2px; }
.field-item .fi-preview { font-size: 12px; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

#sync-btn {
    margin: 12px; padding: 11px; border: none; border-radius: 10px;
    background: #004872; color: #fff; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.15s; font-family: 'Manrope', sans-serif;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
#sync-btn:hover { background: #003a5c; }
#sync-btn.saving { opacity: 0.7; pointer-events: none; }
#sync-btn .sync-icon { font-size: 16px; }

/* ── Center preview ── */
#preview-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

#preview-topbar {
    height: 44px; display: flex; align-items: center; justify-content: space-between;
    padding: 0 16px;
    background: #fff; border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.view-tabs { display: flex; gap: 2px; background: #f1f5f9; border-radius: 8px; padding: 3px; }
.view-tab { font-size: 11px; padding: 3px 12px; border-radius: 6px; border: none; cursor: pointer; background: transparent; color: #64748b; font-weight: 500; }
.view-tab.active { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

.topbar-right { display: flex; align-items: center; gap: 10px; }
#unsaved-badge { font-size: 11px; color: #f59e0b; font-weight: 500; display: none; }
#unsaved-badge.visible { display: block; }
.preview-action-btn {
    font-size: 11px; padding: 4px 12px; border-radius: 20px; cursor: pointer;
    border: 1px solid #e2e8f0; background: #fff; color: #475569; font-weight: 500;
    transition: all 0.12s;
}
.preview-action-btn:hover { border-color: #004872; color: #004872; }
.preview-action-btn.primary { background: #004872; color: #fff; border-color: #004872; }
.preview-action-btn.primary:hover { background: #003a5c; }

#preview-viewport { flex: 1; overflow: hidden; position: relative; }
#preview-iframe {
    width: 100%; height: 100%;
    border: none; display: block;
    background: #fff;
    transition: width 0.3s ease;
}
#preview-viewport.mobile-view #preview-iframe {
    width: 390px; margin: 0 auto;
    box-shadow: 0 0 0 1px #e2e8f0, 0 8px 40px rgba(0,0,0,0.12);
    border-radius: 0 0 8px 8px;
    height: calc(100% - 12px);
    margin-top: 12px;
}

/* ── Right panel ── */
#right-panel {
    width: 248px; min-width: 248px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    display: flex; flex-direction: column;
    overflow: hidden;
}
#rp-header { padding: 14px 16px 12px; border-bottom: 1px solid #e2e8f0; }
#rp-header h3 { font-size: 13px; font-weight: 700; color: #0f172a; font-family: 'Manrope', sans-serif; }
#rp-header p { font-size: 11px; color: #94a3b8; margin-top: 2px; }
#rp-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 12px; }

.rp-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 8px;
    color: #94a3b8; text-align: center; padding: 24px;
}
.rp-empty .rp-empty-icon { font-size: 32px; opacity: 0.4; }
.rp-empty p { font-size: 12px; line-height: 1.6; }

.rp-field { display: flex; flex-direction: column; gap: 5px; }
.rp-field label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 600; }
.rp-field input, .rp-field textarea, .rp-field select {
    font-size: 12px; padding: 7px 10px;
    border: 1px solid #e2e8f0; border-radius: 7px;
    background: #f8fafc; color: #1e293b; width: 100%;
    font-family: 'Inter', sans-serif; resize: vertical;
    transition: border-color 0.12s;
}
.rp-field input:focus, .rp-field textarea:focus, .rp-field select:focus {
    outline: none; border-color: #3b82f6; background: #fff;
}
.rp-field textarea { min-height: 72px; }

.rp-divider { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 600; padding-top: 10px; border-top: 1px solid #f1f5f9; }

.color-swatches { display: flex; gap: 7px; flex-wrap: wrap; padding: 4px 0; }
.color-swatch {
    width: 24px; height: 24px; border-radius: 50%; cursor: pointer;
    border: 2px solid transparent; transition: transform 0.12s;
}
.color-swatch:hover { transform: scale(1.15); }
.color-swatch.selected { outline: 2px solid #1e293b; outline-offset: 2px; }

.toggle-row { display: flex; align-items: center; justify-content: space-between; }
.toggle-label { font-size: 12px; color: #1e293b; }
.toggle-switch {
    width: 36px; height: 20px; background: #e2e8f0; border-radius: 10px;
    cursor: pointer; position: relative; transition: background 0.2s;
    border: none; flex-shrink: 0;
}
.toggle-switch.on { background: #004872; }
.toggle-switch::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 16px; height: 16px; border-radius: 50%; background: #fff;
    transition: left 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-switch.on::after { left: 18px; }

.apply-btn {
    margin: 0 14px 14px; padding: 9px; border: none; border-radius: 8px;
    background: #004872; color: #fff; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.15s; font-family: 'Manrope', sans-serif;
}
.apply-btn:hover { background: #003a5c; }

/* ── Editable zone overlays inside iframe ── */
/* (injected via postMessage into iframe's parent doc) */

/* ── Toast ── */
#toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(16px);
    background: #1e293b; color: #fff; font-size: 13px; font-weight: 500;
    padding: 10px 20px; border-radius: 8px; opacity: 0;
    pointer-events: none; z-index: 999; transition: all 0.25s;
    display: flex; align-items: center; gap: 6px;
}
#toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
#toast.error { background: #b91c1c; }
</style>
</head>
<body>

<div id="editor-shell">

    <!-- ══════════════════════════════════════════════════════ LEFT PANEL -->
    <div id="sidebar">
        <div id="sidebar-header">
            <div class="editor-title">Page Editor</div>
            <div class="tenant-label"><?= e($c['clinic_name']) ?></div>
        </div>

        <div class="sidebar-tabs">
            <button class="sidebar-tab active" data-tab="content">Content</button>
            <button class="sidebar-tab" data-tab="colors">Colors</button>
            <button class="sidebar-tab" data-tab="team">Team</button>
        </div>

        <div id="field-list">
            <!-- ─ Navigation ─ -->
            <div class="field-section-label">Navigation</div>
            <div class="field-item" data-field="clinic_name">
                <div class="fi-label">Clinic Name / Logo</div>
                <div class="fi-preview"><?= e($c['clinic_name']) ?></div>
            </div>
            <div class="field-item" data-field="cta_primary">
                <div class="fi-label">Book Button Label</div>
                <div class="fi-preview"><?= e($c['cta_primary']) ?></div>
            </div>

            <!-- ─ Hero ─ -->
            <div class="field-section-label">Hero Section</div>
            <div class="field-item" data-field="badge">
                <div class="fi-label">Badge</div>
                <div class="fi-preview"><?= e($c['badge_text']) ?></div>
            </div>
            <div class="field-item" data-field="hero_title">
                <div class="fi-label">Hero Headline</div>
                <div class="fi-preview"><?= e($c['hero_title']) ?></div>
            </div>
            <div class="field-item" data-field="hero_description">
                <div class="fi-label">Hero Description</div>
                <div class="fi-preview"><?= e($c['hero_description']) ?></div>
            </div>
            <div class="field-item" data-field="cta_secondary">
                <div class="fi-label">Secondary Button</div>
                <div class="fi-preview"><?= e($c['cta_secondary']) ?></div>
            </div>

            <!-- ─ About ─ -->
            <div class="field-section-label">About Section</div>
            <div class="field-item" data-field="about_description">
                <div class="fi-label">About Body Text</div>
                <div class="fi-preview"><?= e($c['about_description']) ?></div>
            </div>
            <div class="field-item" data-field="checklist">
                <div class="fi-label">Feature Checklist (3 items)</div>
                <div class="fi-preview"><?= e($c['checklist_1']) ?> …</div>
            </div>
            <div class="field-item" data-field="stat">
                <div class="fi-label">Stat Highlight</div>
                <div class="fi-preview"><?= e($c['stat_number']) ?> · <?= e($c['stat_label']) ?></div>
            </div>

            <!-- ─ Announcements ─ -->
            <div class="field-section-label">Announcements</div>
            <div class="field-item" data-field="announcements">
                <div class="fi-label">Latest Pulse Posts</div>
                <div class="fi-preview">Manage posts →</div>
            </div>



            <!-- ─ Footer ─ -->
            <div class="field-section-label">Footer</div>
            <div class="field-item" data-field="footer_copyright">
                <div class="fi-label">Copyright Line</div>
                <div class="fi-preview"><?= e($c['footer_copyright']) ?></div>
            </div>
        </div>

        <button id="sync-btn">
            <span class="material-symbols-outlined sync-icon">sync</span>
            Sync to Live Site
        </button>
    </div>

    <!-- ══════════════════════════════════════════════════════ CENTER PREVIEW -->
    <div id="preview-wrap">
        <div id="preview-topbar">
            <div class="view-tabs">
                <button class="view-tab active" data-view="desktop">Desktop</button>
                <button class="view-tab" data-view="mobile">Mobile</button>
            </div>
            <div class="topbar-right">
                <span id="unsaved-badge">● Unsaved changes</span>
                <button class="preview-action-btn" id="undo-btn">Undo</button>
                <button class="preview-action-btn primary" onclick="window.open('tenant_homepage.php?tenant=<?= urlencode($tenantSlug) ?>', '_blank')">
                    View Live ↗
                </button>
            </div>
        </div>

        <div id="preview-viewport">
            <iframe id="preview-iframe" src="tenant_homepage.php?tenant=<?= urlencode($tenantSlug) ?>" sandbox="allow-same-origin allow-scripts"></iframe>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════ RIGHT PANEL -->
    <div id="right-panel">
        <div id="rp-header">
            <h3 id="rp-title">Select a field</h3>
            <p id="rp-subtitle">Click any field in the left panel or in the preview</p>
        </div>
        <div id="rp-body">
            <div class="rp-empty" id="rp-empty-state">
                <span class="material-symbols-outlined rp-empty-icon">edit_note</span>
                <p>Click a field on the left or tap directly on the preview to start editing.</p>
            </div>
            <!-- field panels injected by JS -->
        </div>
        <button class="apply-btn" id="apply-btn" style="display:none">Apply Changes</button>
    </div>

</div>

<div id="toast">
    <span class="material-symbols-outlined" style="font-size:16px;">check_circle</span>
    <span id="toast-msg">Saved successfully.</span>
</div>

<script>
// ════════════════════════════════════════════════════════════════════════
//  EDITOR STATE
// ════════════════════════════════════════════════════════════════════════
const INITIAL = <?= json_encode($c) ?>;
let state = { ...INITIAL };
let history = [{ ...INITIAL }];
let historyIdx = 0;
let activeField = null;
let unsaved = false;

// ── DOM refs ──
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

// ════════════════════════════════════════════════════════════════════════
//  FIELD DEFINITIONS — what each field shows in the right panel
// ════════════════════════════════════════════════════════════════════════
const FIELDS = {
    clinic_name: {
        label: 'Clinic Name / Logo',
        sub: 'Shown in nav and footer',
        render() {
            return field('clinic_name', 'Clinic Name', 'input', state.clinic_name);
        }
    },
    cta_primary: {
        label: 'Book Button',
        sub: 'Navigation CTA',
        render() {
            return field('cta_primary', 'Button Label', 'input', state.cta_primary);
        }
    },
    badge: {
        label: 'Hero Badge',
        sub: 'Small tag above the headline',
        render() {
            return `
                ${toggleRow('badge_visible', 'Show badge', state.badge_visible === '1')}
                ${field('badge_text', 'Badge text', 'input', state.badge_text)}
            `;
        }
    },
    hero_title: {
        label: 'Hero Headline',
        sub: 'Main heading · large display text',
        render() {
            return field('hero_title', 'Headline', 'textarea', state.hero_title);
        }
    },
    hero_description: {
        label: 'Hero Description',
        sub: 'Paragraph below the headline',
        render() {
            return field('hero_description', 'Description', 'textarea', state.hero_description);
        }
    },
    cta_secondary: {
        label: 'Secondary Button',
        sub: 'Hero section · outline button',
        render() {
            return field('cta_secondary', 'Button Label', 'input', state.cta_secondary);
        }
    },
    about_description: {
        label: 'About Body Text',
        sub: 'Paragraph in the About section',
        render() {
            return field('about_description', 'Body text', 'textarea', state.about_description);
        }
    },
    checklist: {
        label: 'Feature Checklist',
        sub: '3 bullet points in About section',
        render() {
            return `
                ${field('checklist_1', 'Item 1', 'input', state.checklist_1)}
                ${field('checklist_2', 'Item 2', 'input', state.checklist_2)}
                ${field('checklist_3', 'Item 3', 'input', state.checklist_3)}
            `;
        }
    },
    stat: {
        label: 'Stat Highlight',
        sub: 'Number + label on the about card',
        render() {
            return `
                ${field('stat_number', 'Number', 'input', state.stat_number)}
                ${field('stat_label', 'Label', 'input', state.stat_label)}
            `;
        }
    },
    announcements: {
        label: 'Announcements',
        sub: 'Manage posts separately',
        render() {
            return `<div style="padding:12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;font-size:12px;color:#475569;line-height:1.6;">
                Announcements are managed from the <strong>Posts Manager</strong>.
                <br/><br/>
                <a href="manage_posts.php" style="color:#004872;font-weight:600;text-decoration:underline;">Open Posts Manager →</a>
            </div>`;
        }
    },

    footer_copyright: {
        label: 'Copyright Line',
        sub: 'Footer · bottom of the page',
        render() {
            return field('footer_copyright', 'Copyright text', 'input', state.footer_copyright);
        }
    },
};

// ── Color tab fields ──
const COLOR_FIELDS = {
    accent_color: {
        label: 'Brand Accent Color',
        sub: 'Primary color used across the page',
        render() {
            const swatches = [
                '#004872','#006a62','#7c3aed','#be185d',
                '#b45309','#0f172a','#0369a1','#15803d',
            ];
            return `
                <div class="rp-field">
                    <label>Accent Color</label>
                    <div class="color-swatches">
                        ${swatches.map(c => `
                            <div class="color-swatch ${state.accent_color === c ? 'selected' : ''}"
                                 style="background:${c};"
                                 data-color="${c}"
                                 title="${c}"></div>
                        `).join('')}
                    </div>
                </div>
                <div class="rp-field">
                    <label>Custom hex</label>
                    <input type="text" data-key="accent_color" value="${e(state.accent_color)}" placeholder="#004872"/>
                </div>
            `;
        }
    }
};

// ════════════════════════════════════════════════════════════════════════
//  HTML HELPERS
// ════════════════════════════════════════════════════════════════════════
function field(key, label, type, value) {
    const tag = type === 'textarea' ? 'textarea' : 'input';
    const attrs = type === 'textarea' ? '' : `type="text"`;
    return `<div class="rp-field">
        <label>${label}</label>
        ${tag === 'textarea'
            ? `<textarea data-key="${key}">${e(value)}</textarea>`
            : `<input ${attrs} data-key="${key}" value="${e(value)}"/>`
        }
    </div>`;
}

function toggleRow(key, label, checked) {
    return `<div class="rp-field">
        <div class="toggle-row">
            <span class="toggle-label">${label}</span>
            <button class="toggle-switch ${checked ? 'on' : ''}" data-toggle="${key}"></button>
        </div>
    </div>`;
}

function e(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ════════════════════════════════════════════════════════════════════════
//  PANEL RENDERING
// ════════════════════════════════════════════════════════════════════════
function openField(key, tab = 'content') {
    activeField = key;

    // highlight sidebar item
    document.querySelectorAll('.field-item').forEach(el => el.classList.remove('active'));
    const sidebarItem = document.querySelector(`.field-item[data-field="${key}"]`);
    if (sidebarItem) sidebarItem.classList.add('active');

    const def = tab === 'colors' ? COLOR_FIELDS[key] : FIELDS[key];
    if (!def) return;

    rpTitle.textContent = def.label;
    rpSub.textContent = def.sub;

    rpBody.innerHTML = def.render();
    applyBtn.style.display = 'block';

    // wire inputs
    rpBody.querySelectorAll('[data-key]').forEach(el => {
        el.addEventListener('input', () => {
            const k = el.dataset.key;
            state[k] = el.value;
            markUnsaved();
            updatePreview(k, el.value);
            refreshSidebarItem(k);
        });
    });

    // wire toggles
    rpBody.querySelectorAll('[data-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const k = btn.dataset.toggle;
            const isOn = btn.classList.toggle('on');
            state[k] = isOn ? '1' : '0';
            markUnsaved();
            updatePreview(k, state[k]);
        });
    });

    // wire color swatches
    rpBody.querySelectorAll('.color-swatch').forEach(sw => {
        sw.addEventListener('click', () => {
            rpBody.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
            sw.classList.add('selected');
            const color = sw.dataset.color;
            state.accent_color = color;
            const hex = rpBody.querySelector('[data-key="accent_color"]');
            if (hex) hex.value = color;
            markUnsaved();
            updatePreview('accent_color', color);
        });
    });
}

// ════════════════════════════════════════════════════════════════════════
//  LIVE PREVIEW UPDATE  (postMessage into iframe)
// ════════════════════════════════════════════════════════════════════════
function updatePreview(key, value) {
    iframe.contentWindow?.postMessage({ type: 'update', key, value }, '*');
}

function refreshAllPreview() {
    Object.entries(state).forEach(([k, v]) => updatePreview(k, v));
}

// ════════════════════════════════════════════════════════════════════════
//  INJECT LISTENER INTO IFRAME on load
// ════════════════════════════════════════════════════════════════════════
iframe.addEventListener('load', () => {
    try {
        const doc = iframe.contentDocument;
        if (!doc) return;

        // Inject the receiver script into the iframe's page
        const script = doc.createElement('script');
        script.textContent = `
        (function() {
            // Map of data-key → selector(s) in the tenant homepage
            const MAP = {
                clinic_name:       ['nav .text-2xl', 'footer .text-xl'],
                hero_title:        ['section h1'],
                hero_description:  ['section h1 ~ p'],
                cta_primary:       ['nav button', 'section .flex button:first-child'],
                cta_secondary:     ['section .flex button:last-child'],
                badge_text:        ['.inline-flex .text-xs.uppercase'],
                badge_visible:     ['.inline-flex[class*="bg-secondary-fixed"]'],
                about_description: ['#about p.text-on-surface-variant'],
                checklist_1:       ['#about .space-y-4 > div:nth-child(1) span:last-child'],
                checklist_2:       ['#about .space-y-4 > div:nth-child(2) span:last-child'],
                checklist_3:       ['#about .space-y-4 > div:nth-child(3) span:last-child'],
                stat_number:       ['.text-4xl.font-black'],
                stat_label:        ['.text-sm.uppercase.tracking-widest.opacity-80'],
                contact_phone:     ['#location .text-on-surface-variant:first-of-type'],
                contact_email:     ['#location .text-on-surface-variant'],
                contact_address:   ['#location h4 + p:first-of-type'],
                footer_copyright:  ['footer .text-slate-400'],
                accent_color:      [], // handled via CSS variable
            };

            window.addEventListener('message', function(e) {
                const { type, key, value } = e.data || {};
                if (type !== 'update') return;

                if (key === 'accent_color') {
                    document.documentElement.style.setProperty('--accent', value);
                    document.querySelectorAll('.text-primary, .bg-primary, .border-primary').forEach(el => {
                        // light touch — just update inline so we don't break TW classes
                    });
                    return;
                }

                if (key === 'badge_visible') {
                    const badge = document.querySelector('.inline-flex[class*="bg-secondary-fixed"]');
                    if (badge) badge.style.display = value === '1' ? '' : 'none';
                    return;
                }

                const selectors = MAP[key] || [];
                selectors.forEach(sel => {
                    document.querySelectorAll(sel).forEach(el => {
                        el.textContent = value;
                    });
                });
            });

            // ── Editable hover outlines ──
            const EDITABLE = [
                { sel: 'nav .text-2xl',                                key: 'clinic_name' },
                { sel: 'section h1',                                   key: 'hero_title' },
                { sel: 'section h1 ~ p',                               key: 'hero_description' },
                { sel: '.inline-flex[class*="bg-secondary-fixed"]',    key: 'badge' },
                { sel: '#about p.text-on-surface-variant',             key: 'about_description' },
                { sel: '#about .space-y-4',                            key: 'checklist' },
                { sel: '.text-4xl.font-black',                         key: 'stat' },
                { sel: '#location .text-on-surface-variant',           key: 'contact_phone' },
                { sel: 'footer .text-slate-400',                       key: 'footer_copyright' },
                { sel: '#schedule',                                     key: null }, // read-only
            ];

            const style = document.createElement('style');
            style.textContent = \`
                .ez-editable { cursor: pointer; position: relative; }
                .ez-editable:hover { outline: 1.5px dashed #3b82f6 !important; border-radius: 3px; }
                .ez-editable::before {
                    content: attr(data-ez-label);
                    display: none; position: absolute; top: -18px; left: 0;
                    font-size: 10px; background: #3b82f6; color: #fff;
                    padding: 1px 6px; border-radius: 3px;
                    font-family: Inter, sans-serif; white-space: nowrap;
                    pointer-events: none; z-index: 9999;
                }
                .ez-editable:hover::before { display: block; }
                .ez-readonly { cursor: default; }
                .ez-readonly:hover { outline: 1px dashed #94a3b8 !important; border-radius: 3px; }
            \`;
            document.head.appendChild(style);

            EDITABLE.forEach(({ sel, key }) => {
                document.querySelectorAll(sel).forEach(el => {
                    if (key === null) {
                        el.classList.add('ez-readonly');
                        return;
                    }
                    el.classList.add('ez-editable');
                    el.dataset.ezLabel = key.replace(/_/g,' ');
                    el.addEventListener('click', () => {
                        window.parent.postMessage({ type: 'field-click', key }, '*');
                    });
                });
            });
        })();
        `;
        doc.head.appendChild(script);

        // Push current state into iframe on load
        setTimeout(refreshAllPreview, 100);
    } catch(err) {
        // cross-origin guard (shouldn't happen on same origin)
        console.warn('iframe inject skipped:', err);
    }
});

// ── Receive field-click from iframe ──
window.addEventListener('message', e => {
    if (e.data?.type === 'field-click') {
        openField(e.data.key);
    }
});

// ════════════════════════════════════════════════════════════════════════
//  SIDEBAR FIELD CLICK
// ════════════════════════════════════════════════════════════════════════
document.querySelectorAll('.field-item').forEach(item => {
    item.addEventListener('click', () => openField(item.dataset.field));
});

// ════════════════════════════════════════════════════════════════════════
//  SIDEBAR TABS
// ════════════════════════════════════════════════════════════════════════
document.querySelectorAll('.sidebar-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const t = tab.dataset.tab;
        if (t === 'colors') {
            // Show color panel directly
            rpBody.innerHTML = '';
            applyBtn.style.display = 'block';
            rpTitle.textContent = 'Brand Colors';
            rpSub.textContent = 'Applied across the whole page';
            openField('accent_color', 'colors');
        } else if (t === 'team') {
            rpBody.innerHTML = `<div class="rp-empty">
                <span class="material-symbols-outlined rp-empty-icon">group</span>
                <p>Team members are managed from the Team Manager.<br/><br/>
                <a href="manage_team.php" style="color:#004872;font-weight:600;text-decoration:underline;">Open Team Manager →</a></p>
            </div>`;
            applyBtn.style.display = 'none';
            rpTitle.textContent = 'Team';
            rpSub.textContent = 'Doctors & specialists';
        } else {
            // back to content
            rpBody.innerHTML = `<div class="rp-empty" id="rp-empty-state">
                <span class="material-symbols-outlined rp-empty-icon">edit_note</span>
                <p>Click a field on the left or tap directly on the preview to start editing.</p>
            </div>`;
            applyBtn.style.display = 'none';
            rpTitle.textContent = 'Select a field';
            rpSub.textContent = 'Click any field in the left panel or in the preview';
        }
    });
});

// ════════════════════════════════════════════════════════════════════════
//  VIEW TABS (desktop / mobile)
// ════════════════════════════════════════════════════════════════════════
document.querySelectorAll('.view-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        if (tab.dataset.view === 'mobile') {
            viewport.classList.add('mobile-view');
        } else {
            viewport.classList.remove('mobile-view');
        }
    });
});

// ════════════════════════════════════════════════════════════════════════
//  APPLY BUTTON
// ════════════════════════════════════════════════════════════════════════
applyBtn.addEventListener('click', () => {
    // snapshot for undo
    history = history.slice(0, historyIdx + 1);
    history.push({ ...state });
    historyIdx = history.length - 1;
    refreshAllPreview();
    showToast('Changes applied to preview.');
});

// ════════════════════════════════════════════════════════════════════════
//  UNDO
// ════════════════════════════════════════════════════════════════════════
document.getElementById('undo-btn').addEventListener('click', () => {
    if (historyIdx > 0) {
        historyIdx--;
        state = { ...history[historyIdx] };
        refreshAllPreview();
        if (activeField) openField(activeField);
        showToast('Undone.');
    }
});

// ════════════════════════════════════════════════════════════════════════
//  SYNC TO LIVE (AJAX save)
// ════════════════════════════════════════════════════════════════════════
syncBtn.addEventListener('click', async () => {
    syncBtn.classList.add('saving');
    syncBtn.querySelector('.sync-icon').textContent = 'hourglass_empty';

    const body = new URLSearchParams({ ajax_save: '1', ...state });
    try {
        const res = await fetch('', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) {
            markSaved();
            showToast('Synced to live site!');
        } else {
            showToast('Save failed. Try again.', true);
        }
    } catch {
        showToast('Network error. Try again.', true);
    } finally {
        syncBtn.classList.remove('saving');
        syncBtn.querySelector('.sync-icon').textContent = 'sync';
    }
});

// ════════════════════════════════════════════════════════════════════════
//  UNSAVED STATE
// ════════════════════════════════════════════════════════════════════════
function markUnsaved() {
    unsaved = true;
    undoBadge.classList.add('visible');
}
function markSaved() {
    unsaved = false;
    undoBadge.classList.remove('visible');
}

// warn on nav away
window.addEventListener('beforeunload', e => {
    if (unsaved) { e.preventDefault(); e.returnValue = ''; }
});

// ════════════════════════════════════════════════════════════════════════
//  SIDEBAR PREVIEW TEXT refresh
// ════════════════════════════════════════════════════════════════════════
function refreshSidebarItem(key) {
    const map = {
        clinic_name: 'clinic_name', hero_title: 'hero_title',
        hero_description: 'hero_description', badge_text: 'badge',
        about_description: 'about_description', contact_phone: 'contact_phone',
        contact_email: 'contact_email', contact_address: 'contact_address',
        footer_copyright: 'footer_copyright', cta_primary: 'cta_primary',
        cta_secondary: 'cta_secondary', checklist_1: 'checklist',
        stat_number: 'stat',
    };
    const field = map[key];
    if (!field) return;
    const el = document.querySelector(`.field-item[data-field="${field}"] .fi-preview`);
    if (el) el.textContent = state[key];
}

// ════════════════════════════════════════════════════════════════════════
//  TOAST
// ════════════════════════════════════════════════════════════════════════
let toastTimer;
function showToast(msg, isError = false) {
    clearTimeout(toastTimer);
    toastMsg.textContent = msg;
    toast.classList.toggle('error', isError);
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
}
</script>
</body>
</html>
