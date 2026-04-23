<?php
/**
 * Edit Tenant Homepage - Management Panel
 * Allows updating of clinic information displayed on the landing page.
 */
require_once '../includes/connect.php';

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_clinic'])) {
    $sql = "UPDATE clinic_info 
            SET hero_title = ?, hero_subtitle = ?, about_text = ?, phone = ? 
            WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['hero_title'],
        $_POST['hero_subtitle'],
        $_POST['about_text'],
        $_POST['phone']
    ]);
    $message = 'Clinic information updated successfully.';
}

// Fetch current clinic data
$clinic = $pdo->query("SELECT * FROM clinic_info WHERE id = 1")->fetch() ?: [];
$clinic_name = $clinic['name'] ?? 'Your Clinic';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Panel | <?= htmlspecialchars($clinic_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-[#f8fafb] text-slate-800 font-sans">

    <div class="max-w-5xl mx-auto py-12 px-6">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-extrabold font-['Manrope'] text-sky-900">Editor Terminal</h1>
                <p class="text-slate-500">Connected to: oralsync-db.mysql.database.azure.com</p>
            </div>
            <a href="index.php" class="bg-white border border-slate-200 px-6 py-2 rounded-full font-bold hover:bg-slate-50 transition">View Live Site</a>
        </header>

        <?php if ($message): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-xl mb-8 flex items-center gap-3">
                <span class="font-bold">Success:</span> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <section class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
                <h2 class="text-xl font-bold mb-6 text-sky-900 flex items-center gap-2">
                    <span class="w-2 h-6 bg-sky-500 rounded-full"></span> Hero Section
                </h2>
                <div class="grid gap-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Main Headline</label>
                        <input type="text" name="hero_title" value="<?= htmlspecialchars($clinic['hero_title']) ?>" 
                               class="w-full bg-slate-50 border-0 rounded-xl p-4 focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Hero Description</label>
                        <textarea name="hero_subtitle" rows="3" 
                                  class="w-full bg-slate-50 border-0 rounded-xl p-4 focus:ring-2 focus:ring-sky-500"><?= htmlspecialchars($clinic['hero_subtitle']) ?></textarea>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
                <h2 class="text-xl font-bold mb-6 text-sky-900 flex items-center gap-2">
                    <span class="w-2 h-6 bg-sky-500 rounded-full"></span> Contact Details
                </h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Clinic Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($clinic['phone']) ?>" 
                               class="w-full bg-slate-50 border-0 rounded-xl p-4 focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">About Section Text</label>
                        <textarea name="about_text" rows="3" 
                                  class="w-full bg-slate-50 border-0 rounded-xl p-4 focus:ring-2 focus:ring-sky-500"><?= htmlspecialchars($clinic['about_text']) ?></textarea>
                    </div>
                </div>
            </section>

            <div class="pt-4">
                <button type="submit" name="update_clinic" 
                        class="w-full bg-sky-900 text-white font-bold py-5 rounded-2xl shadow-xl shadow-sky-900/20 hover:bg-sky-800 active:scale-[0.98] transition-all">
                    Sync Changes to Live Site
                </button>
            </div>
        </form>
    </div>

</body>
</html>