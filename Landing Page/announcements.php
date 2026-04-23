<?php 
require_once "../includes/connect.php"; 
$current_tenant = 1; // Simulation
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY publish_date DESC");
$stmt->execute([$current_tenant]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/><meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Announcements | Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <style>body { font-family: 'Inter'; } h1,h2 { font-family: 'Manrope'; }</style>
</head>
<body class="bg-slate-50 text-slate-900">

<aside class="h-screen w-64 fixed left-0 top-0 bg-[#f2f4f5] flex flex-col py-8 px-4 z-50">
    <div class="mb-10 px-4">
        <div class="text-lg font-black text-sky-900 uppercase tracking-widest">OralSync</div>
        <div class="text-xs text-slate-500 font-medium">Clinical Suite</div>
    </div>
    <nav class="flex-1 space-y-1">
        <a class="flex items-center text-slate-500 px-4 py-3" href="#"><span class="material-symbols-outlined mr-3">dashboard</span> Dashboard</a>
        <a class="flex items-center bg-white text-sky-900 rounded-lg shadow-sm font-semibold px-4 py-3" href="#">
            <span class="material-symbols-outlined mr-3">campaign</span> Announcements
        </a>
    </nav>
</aside>

<main class="ml-64 p-12">
    <header class="mb-12">
        <h1 class="text-4xl font-extrabold text-sky-900 tracking-tight">Announcements</h1>
        <p class="text-slate-500 mt-2">Manage clinic-wide broadcasts and patient updates.</p>
    </header>

    <div class="grid grid-cols-12 gap-8">
        <section class="col-span-5">
            <div class="bg-white rounded-xl p-8 shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">add_circle</span> Compose Update
                </h2>
                <form action="publish_announcement.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="tenant_id" value="<?= $current_tenant ?>">
                    
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Title</label>
                        <input name="title" required class="w-full rounded-lg border-slate-200 focus:ring-sky-500" type="text">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Date</label>
                            <input name="publish_date" required class="w-full rounded-lg border-slate-200" type="date">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Category</label>
                            <select name="category" class="w-full rounded-lg border-slate-200">
                                <option>Clinical Update</option>
                                <option>Patient Care</option>
                                <option>Facility News</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Content</label>
                        <textarea name="content" required rows="4" class="w-full rounded-lg border-slate-200"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Hero Image</label>
                        <input type="file" name="hero_image" class="text-sm">
                    </div>

                    <button type="submit" class="w-full py-4 bg-sky-900 text-white rounded-full font-bold hover:bg-sky-800 transition-all">
                        Publish Broadcast
                    </button>
                </form>
            </div>
        </section>

        <section class="col-span-7 space-y-4">
            <h2 class="text-xl font-bold px-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-400">history</span> Communication History
            </h2>
            
            <?php foreach($history as $item): ?>
            <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-slate-100 flex group">
                <div class="w-32 bg-slate-200">
                    <?php if($item['image_path']): ?>
                        <img src="<?= $item['image_path'] ?>" class="w-full h-full object-cover">
                    <?php endif; ?>
                </div>
                <div class="p-5 flex-1">
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-sky-50 text-sky-700 rounded uppercase"><?= $item['category'] ?></span>
                        <span class="text-xs text-slate-400"><?= $item['publish_date'] ?></span>
                    </div>
                    <h3 class="font-bold text-slate-800"><?= htmlspecialchars($item['title']) ?></h3>
                    <p class="text-sm text-slate-500 line-clamp-1"><?= htmlspecialchars($item['content']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
    </div>
</main>
</body>
</html>