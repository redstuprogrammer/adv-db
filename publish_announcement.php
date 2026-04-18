<?php
require_once "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // In a real app, get this from $_SESSION['tenant_id']
    $tenant_id = $_POST['tenant_id'] ?? 1; 
    $upload_dir = "uploads/tenant_" . $tenant_id . "/announcements/";
    
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $image_path = "";
    if (!empty($_FILES['hero_image']['name'])) {
        $file_ext = pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION);
        $image_path = $upload_dir . bin2hex(random_bytes(8)) . "." . $file_ext;
        move_uploaded_file($_FILES['hero_image']['tmp_name'], $image_path);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (tenant_id, title, content, category, image_path, publish_date) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tenant_id, 
            $_POST['title'], 
            $_POST['content'], 
            $_POST['category'], 
            $image_path, 
            $_POST['publish_date']
        ]);
        header("Location: announcements.php?status=success");
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}