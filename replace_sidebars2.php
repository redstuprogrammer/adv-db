<?php
$root = new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($root);
$tenantReplacement = "<?php include __DIR__ . '/includes/sidebar_main.php'; ?>";
$superadminReplacement = "<?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>";
$modified = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $content = file_get_contents($path);
    $new = $content;
    if (strpos($content, '<nav class="tenant-sidebar">') !== false && strpos($content, "include __DIR__ . '/includes/sidebar_main.php'") === false) {
        $new = preg_replace('/<nav class="tenant-sidebar">.*?<\/nav>/s', $tenantReplacement, $new, 1);
    }
    if (strpos(basename($path), 'superadmin_') !== false && strpos($content, '<aside class="sidebar">') !== false && strpos($content, "include __DIR__ . '/includes/sidebar_superadmin.php'") === false) {
        $new = preg_replace('/<aside class="sidebar">.*?<\/aside>/s', $superadminReplacement, $new, 1);
    }
    if ($new !== $content) {
        file_put_contents($path, $new);
        $modified[] = $path;
    }
}
echo "Modified " . count($modified) . " files\n";
foreach ($modified as $file) {
    echo $file . "\n";
}
