<?php
// 1. DATABASE CONNECTION
$host = "oralsync-db.mysql.database.azure.com";
$user = "oralsync";
$pass = "Oralsync1";
$db   = "oral";
$port = 3306;
$ssl_cert = __DIR__ . "/azure-combined-2026.pem";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA => $ssl_cert,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]);
} catch (PDOException $e) {
    // Silently fail or log error for production
    $announcements = [];
}

// 2. GET CURRENT TENANT (Change this based on your login session)
$current_tenant_id = 1; 

// 3. FETCH LATEST 2 ANNOUNCEMENTS FOR THE FEED
try {
    $stmt = $pdo->prepare("SELECT title, content, publish_date FROM announcements WHERE tenant_id = ? AND status = 'active' ORDER BY publish_date DESC LIMIT 2");
    $stmt->execute([$current_tenant_id]);
    $announcements = $stmt->fetchAll();
} catch (Exception $e) {
    $announcements = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>The Curated Breath | Professional Dental Serenity</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4 { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-[#f8fafb] text-[#191c1d]">

<header class="fixed top-0 w-full z-50 bg-[#f8fafb]/80 backdrop-blur-md shadow-sm">
    <nav class="flex justify-between items-center w-full px-8 py-4 max-w-7xl mx-auto">
        <div class="text-2xl font-bold text-sky-900 tracking-tighter">ToothFairy</div>
        <div class="hidden md:flex items-center space-x-8">
            <a class="text-sky-900 font-bold border-b-2 border-sky-900 pb-1" href="#">Home</a>
            <a class="text-slate-600 font-medium hover:text-sky-700 transition-colors" href="#about">About</a>
            <a class="text-slate-600 font-medium hover:text-sky-700 transition-colors" href="#team">Team</a>
            <a class="text-slate-600 font-medium hover:text-sky-700 transition-colors" href="#schedule">Schedule</a>
        </div>
        <button class="bg-sky-900 text-white px-6 py-2.5 rounded-full font-semibold shadow-md active:scale-95 transition-all">
            Book Appointment
        </button>
    </nav>
</header>

<main class="pt-24">
    <section class="relative overflow-hidden min-h-[800px] flex items-center px-8 max-w-7xl mx-auto">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="space-y-8">
                <div class="inline-flex items-center space-x-2 bg-emerald-100 text-emerald-900 px-4 py-1.5 rounded-full">
                    <span class="material-symbols-outlined text-sm">spa</span>
                    <span class="text-xs font-bold uppercase tracking-widest">Clinical Serenity</span>
                </div>
                <h1 class="text-6xl md:text-7xl font-extrabold text-slate-900 leading-tight">
                    Exhale the <span class="text-sky-800">Ordinary.</span>
                </h1>
                <p class="text-xl text-slate-500 max-w-lg leading-relaxed font-light">
                    Experience a new standard of dental care where precision engineering meets a curated, calming environment.
                </p>
                <div class="flex gap-4">
                    <button class="bg-sky-900 text-white px-8 py-4 rounded-full font-bold shadow-lg hover:bg-sky-800 transition-all">
                        Book Appointment
                    </button>
                </div>
            </div>
            <div class="relative">
                <div class="aspect-[4/5] rounded-[2rem] overflow-hidden shadow-2xl">
                    <img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC1xIbTG7yvJ_dnNR_6565TiT6x37q1WBDSpLC-6orwCNFBV8PNvU1LG8MBljTwI6ykaAo1sk0apu72Fwnx8Kd34sY0QjrnWbLd4u4wsri9CrmkfTq5WemVWkOzq5-yO0T4FYAC-jJ0qCiXBY-qIXe8WtFskhQrPOF-E24-m9ydQZ6L1BK7Xz0QLixe9njuH_EwsSX_WFl4tYmNI4Xi68Np-4ROrt-ulUYA0yI7T1gejLd0VIZ4giBQsRVRvFb1tZNqF5ptfCDKNuo"/>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 bg-slate-50" id="schedule">
        <div class="max-w-7xl mx-auto px-8">
            <div class="grid lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 bg-white p-10 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-3xl font-bold text-slate-900">Weekly Schedule</h2>
                        <span class="material-symbols-outlined text-sky-700 text-3xl">calendar_month</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="font-semibold text-slate-700">Monday — Wednesday</span>
                            <span class="text-emerald-700 font-medium">8:00 AM — 7:00 PM</span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="font-semibold text-slate-700">Thursday</span>
                            <span class="text-emerald-700 font-medium">8:00 AM — 9:00 PM</span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-slate-100">
                            <span class="font-semibold text-slate-700">Friday</span>
                            <span class="text-emerald-700 font-medium">8:00 AM — 5:00 PM</span>
                        </div>
                        <div class="flex justify-between items-center py-3">
                            <span class="font-bold text-sky-900">Saturday</span>
                            <span class="text-sky-700 font-bold">10:00 AM — 3:00 PM</span>
                        </div>
                    </div>
                </div>

                <div class="bg-sky-900 text-white p-10 rounded-3xl flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-8">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">campaign</span>
                            <h2 class="text-2xl font-bold">Latest Pulse</h2>
                        </div>
                        
                        <div class="space-y-8">
                            <?php if (empty($announcements)): ?>
                                <p class="text-sky-200/50 text-sm">No recent updates. Check back soon!</p>
                            <?php else: ?>
                                <?php foreach ($announcements as $post): ?>
                                    <div>
                                        <span class="text-xs uppercase font-bold tracking-widest opacity-60">
                                            <?= date('M d, Y', strtotime($post['publish_date'])) ?>
                                        </span>
                                        <h4 class="font-bold text-lg mt-1"><?= htmlspecialchars($post['title']) ?></h4>
                                        <p class="text-sky-100/70 text-sm mt-2 line-clamp-2">
                                            <?= htmlspecialchars($post['content']) ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <a href="announcements.php" class="mt-12 group flex items-center gap-2 font-bold hover:gap-4 transition-all">
                        View All Updates
                        <span class="material-symbols-outlined">arrow_right_alt</span>
                    </a>
                </div>

            </div>
        </div>
    </section>
</main>

<footer class="py-16 px-8 bg-slate-100 text-center text-xs text-slate-400 uppercase tracking-widest">
    © 2026 The Curated Breath • Professional Dental Serenity
</footer>

</body>
</html>