<?php 
require_once '../includes/connect.php';

$slug = $_GET['tenant'] ?? '';
$tenant = null;

if ($slug) {
    $stmt = $conn->prepare("SELECT * FROM tenants WHERE subdomain_slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
}

if ($tenant) {
    $tenantId = $tenant['tenant_id'];
    
    // Fetch settings from clinic_settings for this tenant
    $stmtSet = $conn->prepare("SELECT * FROM clinic_settings WHERE tenant_id = ?");
    $stmtSet->bind_param("i", $tenantId);
    $stmtSet->execute();
    $settings = $stmtSet->get_result()->fetch_assoc() ?: [];
    $stmtSet->close();

    // Map tenant fields and settings to the $clinic array
    // Core details (name, phone, email, address) always come from 'tenants' table
    $clinic = [
        'clinic_name' => $settings['clinic_name'] ?? $tenant['company_name'],
        'name' => $settings['clinic_name'] ?? $tenant['company_name'],
        'hero_title' => $settings['hero_title'] ?? $tenant['company_name'], 
        'hero_description' => $settings['hero_description'] ?? ("Welcome to " . $tenant['company_name'] . ". Professional care for your dental health."),
        'about_description' => $settings['about_description'] ?? ("Serving the community in " . $tenant['city'] . ", " . $tenant['province'] . "."),
        'contact_phone' => $tenant['phone'],
        'contact_email' => $tenant['contact_email'] ?? $tenant['email'] ?? 'support@oralsync.com',
        'contact_address' => $tenant['address'] . ", " . $tenant['city'] . ", " . $tenant['province'],
        'accent_color' => $settings['accent_color'] ?? '#004872',
        'badge_text' => $settings['badge_text'] ?? 'Clinical Serenity',
        'badge_visible' => ($settings['badge_visible'] ?? '1') === '1',
        'stat_number' => $settings['stat_number'] ?? '98%',
        'stat_label' => $settings['stat_label'] ?? 'Patient Comfort Index',
        'checklist_1' => $settings['checklist_1'] ?? 'Curated Acoustic Environments',
        'checklist_2' => $settings['checklist_2'] ?? 'Bio-compatible Premium Materials',
        'checklist_3' => $settings['checklist_3'] ?? 'Post-treatment Serenity Lounges',
        'cta_primary' => $settings['cta_primary'] ?? 'Book Appointment',
        'footer_copyright' => $settings['footer_copyright'] ?? ("© 2024 " . $tenant['company_name'] . ". Professional Dental Serenity."),
        'announcements' => json_decode($settings['announcements_json'] ?? '[]', true),
        'team' => json_decode($settings['team_json'] ?? '[]', true),
        'hero_image' => $settings['hero_image'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuC1xIbTG7yvJ_dnNR_6565TiT6x37q1WBDSpLC-6orwCNFBV8PNvU1LG8MBljTwI6ykaAo1sk0apu72Fwnx8Kd34sY0QjrnWbLd4u4wsri9CrmkfTq5WemVWkOzq5-yO0T4FYAC-jJ0qCiXBY-qIXe8WtFskhQrPOF-E24-m9ydQZ6L1BK7Xz0QLixe9njuH_EwsSX_WFl4tYmNI4Xi68Np-4ROrt-ulUYA0yI7T1gejLd0VIZ4giBQsRVRvFb1tZNqF5ptfCDKNuo',
        'about_image_1' => $settings['about_image_1'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuAFClTvpcJUUHoP5IhpaOwWPPgz8GG6P7H5xT7efg3XWtfz-01tG_XTvOTItatWorrgb4N4vOlyH9_abFeVbVyQJGmNW8keiVgjd5cguQJCy0fU3FW09mBwcP21Y6w7VyCnogTKiwY544oFdoeIhmszgf3kgTdiX9CQQfXdbVpq1oT2b5F2TXunM1WHN0FRUL_O6ogUn2vj5IwOYtpxCyGNTDYjAUiRkt45GLttu1WNn0z2WHGhjzyFT1ZozeNWmMBLy-L4nPuF-1g',
        'about_image_2' => $settings['about_image_2'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfL5XxGL2fbsN5rWest-yN7ja8_3q1ZbAiT_yuzB2Fgx5ys1N5W9tBmfwFCQkQgHn0cqNxRsnDX-_YPKxO7-X0HSr8Zeodhe9Zg5LM6KuHoBvrxhQMDkb8QovcTugn_OUH1ZqiFfJJQX-PBr6dihZPL6v7Fe1BldTgtYfpdZ3TWsXCvvMjRyqJ3NmzQM1vyhjj3Tb6gFhPhondxzUJqMifmdm-1PgDRq-wq5JS6FjLUZH24CsmKabNUrpikLejFVuUogJWKoJvc10'
    ];

    // Fetch clinic schedule for this tenant
    $schedule = [];
    $stmtSched = $conn->prepare("SELECT day_of_week, is_closed, opening_time, closing_time FROM clinic_schedules WHERE tenant_id = ?");
    $stmtSched->bind_param("i", $tenantId);
    $stmtSched->execute();
    $resultSched = $stmtSched->get_result();
    while ($row = $resultSched->fetch_assoc()) {
        $schedule[$row['day_of_week']] = $row;
    }
    $stmtSched->close();

    // If no schedule set, provide sensible defaults (Mon-Fri 9-5, Sat 9-1, Sun Closed)
    if (empty($schedule)) {
        $defaultHours = [
            'Monday' => ['is_closed' => 0, 'opening_time' => '09:00:00', 'closing_time' => '17:00:00'],
            'Tuesday' => ['is_closed' => 0, 'opening_time' => '09:00:00', 'closing_time' => '17:00:00'],
            'Wednesday' => ['is_closed' => 0, 'opening_time' => '09:00:00', 'closing_time' => '17:00:00'],
            'Thursday' => ['is_closed' => 0, 'opening_time' => '09:00:00', 'closing_time' => '17:00:00'],
            'Friday' => ['is_closed' => 0, 'opening_time' => '09:00:00', 'closing_time' => '17:00:00'],
            'Saturday' => ['is_closed' => 0, 'opening_time' => '09:00:00', 'closing_time' => '13:00:00'],
            'Sunday' => ['is_closed' => 1, 'opening_time' => '09:00:00', 'closing_time' => '17:00:00'],
        ];
        $schedule = $defaultHours;
    }
} else {
    // Fallback if tenant not found
    $clinic = [
        'name' => 'Professional Dental Care',
        'clinic_name' => 'Professional Dental Care',
        'hero_title' => 'Exhale the Ordinary.',
        'hero_description' => 'Experience a new standard of dental care where precision engineering meets a curated, calming environment.',
        'about_description' => 'At our clinic, we believe that world-class dentistry should never feel clinical.',
        'contact_phone' => '+1 (555) 000-0000',
        'contact_email' => 'support@oralsync.com',
        'contact_address' => '123 Serenity Blvd, Medical District',
        'accent_color' => '#004872'
    ];
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($clinic['name'] ?? 'Professional Dental Care') ?> | Dental Serenity</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;family=Inter:wght@300;400;500;600;700&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
                    "on-background": "#191c1d",
                    "secondary": "#006a62",
                    "on-secondary-container": "#006f66",
                    "on-secondary-fixed-variant": "#005049",
                    "secondary-container": "#81f3e5",
                    "surface-dim": "#d8dadb",
                    "primary-container": "#006097",
                    "surface-container-highest": "#e1e3e4",
                    "on-surface-variant": "#41474f",
                    "inverse-surface": "#2e3132",
                    "on-tertiary-fixed": "#051e28",
                    "primary-fixed-dim": "#97cbff",
                    "primary-fixed": "#cee5ff",
                    "error-container": "#ffdad6",
                    "surface": "#f8fafb",
                    "tertiary": "#304752",
                    "secondary-fixed-dim": "#66d9cc",
                    "primary": "<?= $clinic['accent_color'] ?>",
                    "surface-container-low": "#f2f4f5",
                    "surface-container-high": "#e6e8e9",
                    "on-primary": "#ffffff",
                    "on-primary-container": "#b3d8ff",
                    "on-secondary": "#ffffff",
                    "on-primary-fixed-variant": "#004a76",
                    "inverse-primary": "#97cbff",
                    "on-tertiary-container": "#bfd8e5",
                    "secondary-fixed": "#84f5e8",
                    "outline": "#717880",
                    "on-secondary-fixed": "#00201d",
                    "surface-variant": "#e1e3e4",
                    "on-tertiary-fixed-variant": "#334a55",
                    "tertiary-fixed-dim": "#b1cad7",
                    "outline-variant": "#c0c7d1",
                    "on-error-container": "#93000a",
                    "surface-container-lowest": "#ffffff",
                    "tertiary-container": "#485f6a",
                    "on-primary-fixed": "#001d33",
                    "background": "#f8fafb",
                    "surface-bright": "#f8fafb",
                    "on-tertiary": "#ffffff",
                    "surface-container": "#eceeef",
                    "on-surface": "#191c1d",
                    "tertiary-fixed": "#cde6f4",
                    "inverse-on-surface": "#eff1f2",
                    "surface-tint": "#08639a",
                    "on-error": "#ffffff",
                    "error": "#ba1a1a"
            },
            "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
            },
            "fontFamily": {
                    "headline": ["Manrope"],
                    "body": ["Inter"],
                    "label": ["Inter"]
            }
          },
        },
      }
    </script>
<style>
    body { 
        font-family: 'Inter', sans-serif; 
    }
    h1, h2, h3, h4 { 
        font-family: 'Manrope', sans-serif; 
    }
    .material-symbols-outlined {
        font-variation-settings: 
            'FILL' 0, 
            'wght' 400, 
            'GRAD' 0, 
            'opsz' 24;
    }
</style>
</head>
<body class="bg-surface text-on-surface selection:bg-secondary-container selection:text-on-secondary-container">
<header class="fixed top-0 w-full z-50 bg-[#f8fafb]/80 backdrop-blur-md shadow-[0_8px_24px_rgba(25,28,29,0.06)] font-['Manrope'] tracking-tight">
<nav class="flex justify-between items-center w-full px-8 py-4 max-w-7xl mx-auto">
<div class="text-2xl font-bold text-sky-900"><?= htmlspecialchars($clinic['name'] ?? 'Your Clinic') ?></div>

<div class="hidden md:flex items-center space-x-8">
<a class="text-sky-900 font-bold border-b-2 border-sky-900 pb-1 hover:text-sky-700 transition-colors duration-300" href="#">Home</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#about">About</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#team">Team</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#schedule">Schedule</a>
<a class="text-slate-600 font-medium hover:text-sky-700 transition-colors duration-300" href="#location">Location</a>
</div>
<button onclick="openModal()" class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-6 py-2.5 rounded-full font-semibold shadow-md active:scale-95 transition-all duration-200">
                <?= htmlspecialchars($clinic['cta_primary']) ?>
            </button>
</nav>
</header>
<main class="pt-24">
<!-- Hero Section -->
<section class="relative overflow-hidden min-h-[870px] flex items-center px-8 max-w-7xl mx-auto">
<div class="absolute top-0 right-0 -z-10 w-2/3 h-full opacity-10 pointer-events-none">
<div class="w-full h-full bg-secondary-container rounded-full blur-3xl scale-150 transform translate-x-1/2 -translate-y-1/4"></div>
</div>
<div class="grid md:grid-cols-2 gap-12 items-center">
<div class="space-y-8">
<?php if ($clinic['badge_visible']): ?>
<div class="inline-flex items-center space-x-2 bg-secondary-fixed text-on-secondary-fixed px-4 py-1.5 rounded-full">
<span class="material-symbols-outlined text-sm">spa</span>
<span class="text-xs font-bold uppercase tracking-widest"><?= htmlspecialchars($clinic['badge_text']) ?></span>
</div>
<?php endif; ?>
<h1 class="text-5xl md:text-7xl font-extrabold text-on-surface leading-[1.1] tracking-tight">
                        <?= htmlspecialchars($clinic['hero_title'] ?? 'Exhale the <span class="text-primary">Ordinary.</span>') ?>
</h1>
<p class="text-xl text-on-surface-variant max-w-lg leading-relaxed font-light">
                        <?= htmlspecialchars($clinic['hero_description'] ?? 'Experience a new standard of dental care where precision engineering meets a curated, calming environment. Your comfort is our primary clinical protocol.') ?>
                    </p>
<div class="flex flex-wrap gap-4">
<button onclick="openModal()" class="bg-primary text-on-primary px-8 py-4 rounded-full font-bold shadow-lg hover:bg-on-primary-fixed-variant active:scale-95 transition-all">
                            <?= htmlspecialchars($clinic['cta_primary']) ?>
                        </button>
</div>
</div>
<div class="relative">
<div class="aspect-[4/5] rounded-[2rem] overflow-hidden shadow-2xl relative z-10">
<img id="hero-image" class="w-full h-full object-cover" data-alt="Modern high-end dental clinic" src="<?= htmlspecialchars($clinic['hero_image']) ?>"/>
</div>
<div class="absolute -bottom-6 -left-6 bg-surface-container-lowest p-6 rounded-xl shadow-xl z-20 flex items-center gap-4 max-w-xs">
<div class="w-12 h-12 bg-secondary-container rounded-full flex items-center justify-center text-on-secondary-container">
<span class="material-symbols-outlined">verified_user</span>
</div>
<div>
<p class="font-bold text-primary">Certified Quality</p>
<p class="text-xs text-on-surface-variant">Top-rated patient experience 2024</p>
</div>
</div>
</div>
</div>
</section>
<!-- Philosophy / About Section -->
<section class="py-24 bg-surface-container-low" id="about">
<div class="max-w-7xl mx-auto px-8">
<div class="grid md:grid-cols-12 gap-12 items-center">
<div class="md:col-span-5">
<h2 class="text-4xl font-extrabold text-on-surface mb-6 leading-tight">Professionalism<br/>Refined through Art.</h2>
<p class="text-on-surface-variant leading-relaxed text-lg mb-8">
                            <?= htmlspecialchars($clinic['about_description'] ?? 'At ' . ($clinic['name'] ?? 'our clinic') . ', we believe that world-class dentistry should never feel clinical.') ?>
                        </p>
<div class="space-y-4">
<div class="flex items-center gap-4">
<span class="material-symbols-outlined text-secondary">check_circle</span>
<span class="font-medium"><?= htmlspecialchars($clinic['checklist_1']) ?></span>
</div>
<div class="flex items-center gap-4">
<span class="material-symbols-outlined text-secondary">check_circle</span>
<span class="font-medium"><?= htmlspecialchars($clinic['checklist_2']) ?></span>
</div>
<div class="flex items-center gap-4">
<span class="material-symbols-outlined text-secondary">check_circle</span>
<span class="font-medium"><?= htmlspecialchars($clinic['checklist_3']) ?></span>
</div>
</div>
</div>
<div class="md:col-span-7 grid grid-cols-2 gap-4">
<div class="space-y-4 pt-12">
<div class="rounded-2xl overflow-hidden shadow-lg h-64">
<img id="about-image-1" class="w-full h-full object-cover" data-alt="Abstract macro shot" src="<?= htmlspecialchars($clinic['about_image_1']) ?>"/>
</div>
<div class="bg-primary p-8 rounded-2xl text-on-primary">
<p class="text-4xl font-black mb-2"><?= htmlspecialchars($clinic['stat_number']) ?></p>
<p class="text-sm font-medium uppercase tracking-widest opacity-80"><?= htmlspecialchars($clinic['stat_label']) ?></p>
</div>
</div>
<div class="rounded-2xl overflow-hidden shadow-lg h-[450px]">
<img id="about-image-2" class="w-full h-full object-cover" data-alt="Interior of a luxury clinic" src="<?= htmlspecialchars($clinic['about_image_2']) ?>"/>
</div>
</div>
</div>
</div>
</section>
<!-- Team Section -->
<section class="py-24" id="team">
<div class="max-w-7xl mx-auto px-8">
<div class="text-center max-w-2xl mx-auto mb-16">
<h2 class="text-4xl font-extrabold text-on-surface mb-4">The Architects of Your Smile</h2>
<p class="text-on-surface-variant font-body">Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection.</p>
</div>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
<?php 
$teamMembers = $clinic['team'];
if (empty($teamMembers)) {
    // Default team members
    $teamMembers = [
        [
            'name' => 'Dr. Julian Vance',
            'role' => 'Chief Prosthodontist',
            'description' => 'Expert in reconstructive aesthetic dentistry with 15 years of clinical excellence.',
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAL6y-PIHQgnWOeLZ5oQKYGgDndV6mBtY3McI8O3gNlbATmsN2z2HgaxB-ro25d2kQLdLGYhOU-mXkv8H1ymAXABErj7Ln5TcPKwyl4vUKofbL7zTdrh1qMT7qHTaUX5Vdp6_XodPSrNA6ITzHaqKly1bE44rPUXUcGVU4Uk4l3stZltFc-XUdPrAY-kWiZ1WkUnwinm2qQLIeboU5FEBIbIngq4zL38-_-JJFPxiIKlpFt4ZfZ-mmfZEXnMz2iprXW9ITFVCdm6ec',
            'tags' => ['Implants', 'Veneers']
        ],
        [
            'name' => 'Dr. Elena Rostova',
            'role' => 'Lead Orthodontist',
            'description' => 'Specializing in invisible alignment systems and holistic jaw alignment therapies.',
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBg81LjkOL5PJs-3Td8bb1ZgBCLAPvxEAdqtUpn67cGf3NLmthtQeSDEQuTK34bONhUB0kdmmBZiPLjbDi8yGyEO-Tsyz1-dRm2R1xJS0pR09srYU5-qxYt_zlgOvCPVXV0-dMwj3nskjmm23Untq7MI233FD1FLJLQJALp8M7fhspLqa7YGnut6EmSYskyCLavjOQgdDoNiZLugEawq3GJp28iLds9YBHjDhgoOHV_W535CyhByi1fyrDdLI5jJnmze3TR7u0WgXE',
            'tags' => ['Invisalign', 'Alignment']
        ],
        [
            'name' => 'Marcus Chen',
            'role' => 'Oral Hygienist',
            'description' => 'Pioneer of pain-free ultrasonic cleaning protocols and preventive care wellness.',
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuA0eWtPM78tu6eWdZGq4m28rrX6alFZJgIexcRarCMngAQLEPexwrUY-IK_jv6PMQBsCy94KBDN3RYbOxrHW0lIj5adOrqhlGLXUnpygo86x9VOIDFqDuZxHbybXTjCQsZ9eQ02aqP-_-_dXUG2LJm3DqdZcGaskOIiAvJR7Mhm1EoSYxVVpMraYoYNL0rsIfnd4pRPoVLM6PfJZEQCSkAybceC2G9uJVZZbyQd9_VK5JaB3J28og-YJnJOFEZjxmGw7fmItxQG-eQ',
            'tags' => ['Cleaning', 'Prevention']
        ]
    ];
}

foreach ($teamMembers as $index => $member):
?>
<div class="bg-surface-container-lowest rounded-xl overflow-hidden group hover:shadow-xl transition-all duration-300 team-member-card" data-index="<?= $index ?>">
<div class="aspect-square overflow-hidden">
<img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="Professional portrait" src="<?= htmlspecialchars($member['image']) ?>"/>
</div>
<div class="p-8">
<span class="text-secondary text-xs font-bold uppercase tracking-widest block mb-2"><?= htmlspecialchars($member['role']) ?></span>
<h3 class="text-xl font-bold text-on-surface mb-2"><?= htmlspecialchars($member['name']) ?></h3>
<p class="text-on-surface-variant text-sm leading-relaxed mb-6"><?= htmlspecialchars($member['description']) ?></p>
<div class="flex gap-2">
<?php foreach ($member['tags'] as $tag): ?>
<span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-semibold"><?= htmlspecialchars($tag) ?></span>
<?php endforeach; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</section>
<!-- Bento Grid: Schedule & Announcements -->
<section class="py-24 bg-surface-container-low" id="schedule">
<div class="max-w-7xl mx-auto px-8">
<div class="grid lg:grid-cols-3 gap-8">
<!-- Weekly Schedule -->
<div class="lg:col-span-2 bg-surface-container-lowest p-10 rounded-3xl shadow-sm border border-outline-variant/10">
<div class="flex items-center justify-between mb-8">
<h2 class="text-3xl font-bold text-on-surface">Weekly Schedule</h2>
<span class="material-symbols-outlined text-primary text-3xl">calendar_month</span>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
<?php 
$daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
foreach ($daysOrder as $day):
    $dayData = $schedule[$day] ?? null;
    $dayName = ucfirst($day);
    $timeStr = '';
    $isSpecial = false;

    if (!$dayData || $dayData['is_closed']) {
        $timeStr = 'Closed';
        $isSpecial = true;
    } else {
        $open = date('g:i A', strtotime($dayData['opening_time']));
        $close = date('g:i A', strtotime($dayData['closing_time']));
        $timeStr = "$open — $close";
    }
?>
<div class="flex justify-between items-center py-3 border-b border-outline-variant/20">
    <span class="font-semibold"><?= $dayName ?></span>
    <span class="<?= $isSpecial ? 'text-outline italic' : 'text-secondary font-medium' ?>"><?= $timeStr ?></span>
</div>
<?php endforeach; ?>
<div class="flex justify-between items-center py-3">
<span class="font-bold text-primary">Public Holidays</span>
<span class="text-outline">Closed</span>
</div>
</div>
</div>
<!-- Announcements Feed -->
<div class="bg-primary text-on-primary p-10 rounded-3xl flex flex-col justify-between">
<div>
<div class="flex items-center gap-3 mb-8">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">campaign</span>
<h2 class="text-2xl font-bold">Latest Pulse</h2>
</div>
<div class="space-y-8">
<?php 
$announcements = $clinic['announcements'];
if (empty($announcements)) {
    // Default announcements
    $announcements = [
        [
            'date' => 'Sept 12, 2024',
            'title' => 'New Aesthetic Laser Protocol',
            'description' => 'Introducing the Mint-Glow series for 20-minute whitening without sensitivity.'
        ],
        [
            'date' => 'Sept 05, 2024',
            'title' => 'Evening Hours Extended',
            'description' => 'Thursday nights are now open until 9 PM for our executive patients.'
        ]
    ];
}

foreach ($announcements as $index => $announcement):
?>
<div class="announcement-item" data-index="<?= $index ?>">
<span class="text-xs uppercase font-bold tracking-widest opacity-60"><?= htmlspecialchars($announcement['date']) ?></span>
<h4 class="font-bold text-lg mt-1"><?= htmlspecialchars($announcement['title']) ?></h4>
<p class="text-on-primary/70 text-sm mt-2"><?= htmlspecialchars($announcement['description']) ?></p>
</div>
<?php endforeach; ?>
</div>
</div>

</div>
</div>
</div>
</section>
<!-- Location & Map Section -->
<section class="py-24" id="location">
<div class="max-w-7xl mx-auto px-8">
<div class="bg-surface-container-lowest rounded-[3rem] overflow-hidden shadow-2xl flex flex-col md:flex-row">
<div class="md:w-1/2 p-12 lg:p-20">
<h2 class="text-4xl font-extrabold text-on-surface mb-8">Visit the Oasis.</h2>
<div class="space-y-10">
<div class="flex gap-6">
<div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary">location_on</span>
</div>
<div>
<h4 class="font-bold text-lg mb-1">Our Studio</h4>
<p class="text-on-surface-variant"><?= htmlspecialchars($clinic['contact_address'] ?? 'Address not set') ?></p>
</div>
</div>
<div class="flex gap-6">
<div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary">call</span>
</div>
<div>
<h4 class="font-bold text-lg mb-1">Contact Concierge</h4>
<p class="text-on-surface-variant"><?= htmlspecialchars($clinic['contact_phone'] ?? 'Phone not set') ?><br/><?= htmlspecialchars($clinic['contact_email'] ?? 'support@oralsync.com') ?></p>
</div>
</div>
</div>
</div>
<div class="md:w-1/2 min-h-[400px] relative grayscale">
<img class="w-full h-full object-cover" data-alt="Minimalist street map of a modern metropolitan city area with light blue and grey tones" data-location="Metropolis" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCsRWlBMULDv8ZnFrVini31nZRjWisxLoSoCvPlhqTaYW1oha1LJOtM5QRjEL4gVZ7LY0yLkmEinpX7VwlovQ5A_fr-4t_xqzCNscyhC397pxjreAzCHVv0RLHWu5jO_ztfQHM7c-CpHbspqYgJP4VaitiUv64Rw4BKXGw8jppiikSBupnkawgrCDETven0rquGxgC5bfYixBXcS97M-YIHOMGo0qvM40sKe5rD58HztoXTsusTUCDWxG5mjoL9ASS3fFpDu-RaYu0"/>
<!-- Glassmorphic Overlay -->
<div class="absolute inset-0 bg-primary/10 pointer-events-none"></div>
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-surface/80 backdrop-blur-md p-6 rounded-2xl shadow-2xl border border-white/20 text-center">
<span class="material-symbols-outlined text-primary text-4xl mb-2" style="font-variation-settings: 'FILL' 1;">location_on</span>
<p class="font-bold text-on-surface"><?= htmlspecialchars($clinic['name']) ?></p>
<p class="text-xs text-secondary mt-1">Now Arriving</p>
</div>
</div>
</div>
</div>
</section>
</main>
<footer class="w-full py-16 px-8 bg-[#f2f4f5] dark:bg-slate-950 font-['Inter'] text-xs uppercase tracking-widest mt-12">
<div class="flex flex-col items-center text-center space-y-6 w-full max-w-7xl mx-auto">
<div class="text-xl font-bold text-sky-900 mb-4"><?= htmlspecialchars($clinic['name']) ?></div>
<div class="flex flex-wrap justify-center gap-x-8 gap-y-4">
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Privacy Policy</a>
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Terms of Service</a>
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Patient Portal</a>
<a class="text-slate-500 hover:text-sky-600 transition-colors" href="#">Accessibility</a>
</div>
<div class="pt-8 text-slate-400 border-t border-outline-variant/20 w-full">
                <?= htmlspecialchars($clinic['footer_copyright']) ?>
            </div>
</div>
</footer>
<!-- Modal -->
<div id="appointmentModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-surface p-8 rounded-3xl shadow-2xl mx-4">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-on-surface">Book Appointment</h2>
            <button onclick="closeModal()" class="material-symbols-outlined text-outline hover:text-on-surface transition-colors">close</button>
        </div>
        
        <div class="space-y-6">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-primary mb-3">Mobile Application</p>
                <div class="bg-surface-container-low p-4 rounded-2xl flex items-center gap-4 border border-outline-variant/10">
                    <div class="w-12 h-12 bg-primary-fixed rounded-xl flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary">install_mobile</span>
                    </div>
                    <div>
                        <p class="font-bold text-on-surface">Install the app</p>
                        <a href="https://drive.google.com/drive/folders/199ac2H14VbdUJSwrAsn3uJEL9Shbw_Xp?usp=sharing" target="_blank" class="text-primary text-sm font-medium hover:underline flex items-center gap-1">
                            Download APK via GDrive
                            <span class="material-symbols-outlined text-xs">open_in_new</span>
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-primary mb-3">Contact Us</p>
                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="w-10 h-10 bg-surface-container-high rounded-full flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary">call</span>
                        </div>
                        <div>
                            <p class="font-bold text-sm">Phone</p>
                            <p class="text-on-surface-variant text-sm"><?= htmlspecialchars($clinic['contact_phone']) ?></p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-10 h-10 bg-surface-container-high rounded-full flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary">mail</span>
                        </div>
                        <div>
                            <p class="font-bold text-sm">Email</p>
                            <p class="text-on-surface-variant text-sm"><?= htmlspecialchars($clinic['contact_email']) ?></p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-10 h-10 bg-surface-container-high rounded-full flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary">location_on</span>
                        </div>
                        <div>
                            <p class="font-bold text-sm">Address</p>
                            <p class="text-on-surface-variant text-sm"><?= htmlspecialchars($clinic['contact_address']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <button onclick="closeModal()" class="w-full mt-8 bg-primary text-on-primary py-4 rounded-full font-bold shadow-lg hover:opacity-90 active:scale-[0.98] transition-all">
            Close
        </button>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('appointmentModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('appointmentModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
</script>
</body></html>