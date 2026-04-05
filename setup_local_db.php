<?php
echo "OralSync Local Database Setup\n";
echo "==============================\n\n";

// Check if we can connect to local MySQL
$host = "localhost";
$user = "root";
$pass = "";
$db = "oral";

echo "1. Checking MySQL connection...\n";
$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    echo "❌ Cannot connect to MySQL. Please:\n";
    echo "   - Start XAMPP Control Panel\n";
    echo "   - Start MySQL service\n";
    echo "   - Or install MySQL server\n\n";
    exit(1);
}

echo "✅ MySQL connection successful\n\n";

echo "2. Checking if 'oral' database exists...\n";
$dbExists = mysqli_select_db($conn, $db);

if (!$dbExists) {
    echo "❌ Database 'oral' does not exist.\n";
    echo "   Creating database 'oral'...\n";

    if (mysqli_query($conn, "CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        echo "✅ Database 'oral' created successfully\n\n";
    } else {
        echo "❌ Failed to create database: " . mysqli_error($conn) . "\n";
        exit(1);
    }
} else {
    echo "✅ Database 'oral' exists\n\n";
}

echo "3. Checking tables...\n";

// Check super_admins table
$result = mysqli_query($conn, "SHOW TABLES LIKE 'super_admins'");
if (mysqli_num_rows($result) == 0) {
    echo "   Creating super_admins table...\n";
    $sql = "CREATE TABLE super_admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        password_reset_token VARCHAR(255) NULL,
        password_reset_expires TIMESTAMP NULL
    )";
    if (mysqli_query($conn, $sql)) {
        echo "   ✅ super_admins table created\n";
    } else {
        echo "   ❌ Failed to create super_admins table: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✅ super_admins table exists\n";
}

// Check if super admin user exists
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM super_admins");
$row = mysqli_fetch_assoc($result);
if ($row['count'] == 0) {
    echo "   Creating default super admin user...\n";
    // Default password: admin123 (you should change this)
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO super_admins (username, password_hash, email) VALUES ('admin', '$hashedPassword', 'admin@oralsync.com')";
    if (mysqli_query($conn, $sql)) {
        echo "   ✅ Default super admin created\n";
        echo "      Username: admin\n";
        echo "      Password: admin123\n";
        echo "      ⚠️  CHANGE THIS PASSWORD IN PRODUCTION!\n";
    } else {
        echo "   ❌ Failed to create super admin: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✅ Super admin user(s) exist\n";
}

echo "\n4. Setup complete!\n";
echo "   You can now login with:\n";
echo "   Username: admin\n";
echo "   Password: admin123\n\n";

mysqli_close($conn);
?>