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
        'about_image_2' => $settings['about_image_2'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfL5XxGL2fbsN5rWest-yN7ja8_3q1ZbAiT_yuzB2Fgx5ys1N5W9tBmfwFCQkQgHn0cqNxRsnDX-_YPKxO7-X0HSr8Zeodhe9Zg5LM6KuHoBvrxhQMDkb8QovcTugn_OUH1ZqiFfJJQX-PBr6dihZPL6v7Fe1BldTgtYfpdZ3TWsXCvvMjRyqJ3NmzQM1vyhjj3Tb6gFhPhondxzUJqMifmdm-1PgDRq-wq5JS6FjLUZH24CsmKabNUrpikLejFVuUogJWKoJvc10',
        'team_title' => $settings['team_title'] ?? 'The Architects of Your Smile',
        'team_subtitle' => $settings['team_subtitle'] ?? 'Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection.'
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

    // Fetch services for this tenant
    $services = [];
    try {
        $serviceTableResult = $conn->query("SHOW COLUMNS FROM service LIKE 'category'");
        $categoryExists = ($serviceTableResult && $serviceTableResult->num_rows > 0);
        
        $selectSql = "SELECT service_name, price, description" . ($categoryExists ? ", category" : "") . " FROM service WHERE tenant_id = ? ORDER BY category, service_name";
        $stmtServ = $conn->prepare($selectSql);
        if ($stmtServ) {
            $stmtServ->bind_param("i", $tenantId);
            $stmtServ->execute();
            $resServ = $stmtServ->get_result();
            while ($row = $resServ->fetch_assoc()) {
                $services[] = $row;
            }
            $stmtServ->close();
        }
    } catch (Exception $e) {
        $services = [];
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
        'accent_color' => '#004872',
        'team_title' => 'The Architects of Your Smile',
        'team_subtitle' => 'Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection.'
    ];
}

if (empty($services)) {
    $services = [
        [
            'service_name' => 'Ultrasonic Teeth Cleaning',
            'category' => 'Preventive',
            'price' => 1500.00,
            'description' => 'Advanced, pain-free ultrasonic scaling and polishing to remove plaque and maintain optimal oral hygiene.'
        ],
        [
            'service_name' => 'Laser Teeth Whitening',
            'category' => 'Cosmetic',
            'price' => 5000.00,
            'description' => 'State-of-the-art Mint-Glow series laser whitening that brightens teeth by up to 8 shades in a single session without sensitivity.'
        ],
        [
            'service_name' => 'Clear Orthodontic Aligners',
            'category' => 'Orthodontics',
            'price' => 75000.00,
            'description' => 'Discreet, removable, custom-made orthodontic aligners to align your teeth comfortably and invisibly.'
        ],
        [
            'service_name' => 'Bio-Compatible Dental Implants',
            'category' => 'Restorative',
            'price' => 45000.00,
            'description' => 'High-grade titanium implants topped with natural-looking crowns to restore function and beauty to your smile.'
        ],
        [
            'service_name' => 'Pediatric Preventive Care',
            'category' => 'Pediatric',
            'price' => 1200.00,
            'description' => 'Gentle, friendly checkups, dental sealants, and fluoride treatments tailored specially for young patients.'
        ],
        [
            'service_name' => 'Restorative Composite Fillings',
            'category' => 'Restorative',
            'price' => 2000.00,
            'description' => 'Durable, tooth-colored composite restorations that blend seamlessly with your natural teeth.'
        ]
    ];
}

// Function to map categories to modern icons
if (!function_exists('getCategoryIcon')) {
    function getCategoryIcon($category) {
        $category = strtolower(trim((string)$category));
        switch ($category) {
            case 'preventive':
                return 'shield';
            case 'pediatric':
                return 'child_care';
            case 'prosthodontics':
                return 'spa';
            case 'cosmetic':
                return 'auto_awesome';
            case 'orthodontics':
                return 'align_horizontal_center';
            case 'surgery':
                return 'medical_services';
            case 'restorative':
                return 'healing';
            default:
                return 'health_and_safety';
        }
    }
}

// Extract unique categories for the filters
$categories = ['All'];
foreach ($services as $service) {
    $cat = $service['category'] ?? 'General';
    if (!in_array($cat, $categories)) {
        $categories[] = $cat;
    }
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
<a class="text-sky-900 font-bold border-b-2 border-sky-900 pb-1 hover:text-sky-700 transition-colors duration-300 flex items-center gap-2" href="tenant_homepage.php?tenant=<?= urlencode($slug) ?>">
    <span class="material-symbols-outlined text-sm">arrow_back</span>
    Back to Homepage
</a>
</div>
<button onclick="openModal()" class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-6 py-2.5 rounded-full font-semibold shadow-md active:scale-95 transition-all duration-200">
                <?= htmlspecialchars($clinic['cta_primary']) ?>
            </button>
</nav>
</header>
<main class="pt-24">


<!-- Services Section -->
<section class="py-24 bg-surface" id="services">
    <div class="max-w-7xl mx-auto px-8">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-primary text-sm font-bold uppercase tracking-widest block mb-3">Our Clinical Offerings</span>
            <h2 class="text-4xl font-extrabold text-on-surface mb-4">Exceptional Dental Services</h2>
            <p class="text-on-surface-variant font-body font-light text-lg">Discover our curated selection of state-of-the-art treatments designed around your comfort and clinical excellence.</p>
        </div>

        <?php if (count($categories) > 1): ?>
        <!-- Category Filter Tabs -->
        <div class="flex flex-wrap justify-center gap-3 mb-12">
            <?php foreach ($categories as $cat): ?>
                <button 
                    onclick="filterServices('<?= htmlspecialchars($cat) ?>', this)" 
                    class="category-tab px-6 py-2.5 rounded-full text-sm font-semibold tracking-wide transition-all duration-300 <?= $cat === 'All' ? 'bg-primary text-on-primary shadow-md font-bold' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container-high' ?>">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Services Grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($services as $service): ?>
                <div class="bg-surface-container-lowest rounded-[2rem] p-8 shadow-sm border border-outline-variant/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group service-card" data-category="<?= htmlspecialchars($service['category'] ?? 'General') ?>">
                    <div>
                        <div class="flex items-center justify-between mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary group-hover:scale-110 transition-transform duration-300">
                                <span class="material-symbols-outlined text-2xl"><?= getCategoryIcon($service['category'] ?? 'General') ?></span>
                            </div>
                            <span class="px-3 py-1 bg-secondary-fixed text-on-secondary-container rounded-full text-xs font-bold uppercase tracking-wider">
                                <?= htmlspecialchars($service['category'] ?? 'General') ?>
                            </span>
                        </div>
                        <h3 class="text-xl font-bold text-on-surface mb-3 group-hover:text-primary transition-colors duration-300">
                            <?= htmlspecialchars($service['service_name']) ?>
                        </h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed font-light mb-6 line-clamp-3">
                            <?= htmlspecialchars($service['description'] ?? 'No description available for this service.') ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-outline-variant/10">
                        <div>
                            <span class="text-xs text-on-surface-variant block uppercase tracking-wider">Investment</span>
                            <span class="text-xl font-extrabold text-primary">₱<?= number_format($service['price'], 2) ?></span>
                        </div>
                        <button onclick="openModal()" class="w-10 h-10 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center opacity-0 group-hover:opacity-100 group-hover:bg-primary group-hover:text-on-primary transition-all duration-300 shadow-md">
                            <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
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
    function filterServices(category, element) {
        // Update active tab styling
        const tabs = document.querySelectorAll('.category-tab');
        tabs.forEach(tab => {
            tab.classList.remove('bg-primary', 'text-on-primary', 'shadow-md', 'font-bold');
            tab.classList.add('bg-surface-container-low', 'text-on-surface-variant', 'hover:bg-surface-container-high');
        });
        
        element.classList.remove('bg-surface-container-low', 'text-on-surface-variant', 'hover:bg-surface-container-high');
        element.classList.add('bg-primary', 'text-on-primary', 'shadow-md', 'font-bold');
        
        // Filter cards
        const cards = document.querySelectorAll('.service-card');
        cards.forEach(card => {
            if (category === 'All' || card.getAttribute('data-category') === category) {
                card.classList.remove('hidden');
                // Subtle fade-in animation
                card.style.opacity = '0';
                card.style.transform = 'translateY(8px)';
                card.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 50);
            } else {
                card.classList.add('hidden');
            }
        });
    }
</script>
</body></html>