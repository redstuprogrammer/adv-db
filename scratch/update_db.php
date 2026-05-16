<?php
require_once 'includes/connect.php';
$conn->query("ALTER TABLE clinic_settings ADD COLUMN IF NOT EXISTS announcements_json TEXT");
$conn->query("ALTER TABLE clinic_settings ADD COLUMN IF NOT EXISTS team_json TEXT");
echo "Columns added or already exist.\n";
