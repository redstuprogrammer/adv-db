<?php
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
echo "mkdir test: ";
$testDir = __DIR__ . '/test_mkdir';
if (mkdir($testDir, 0755, true)) {
    echo "SUCCESS\n";
    rmdir($testDir);
} else {
    echo "FAILED\n";
}
?>
