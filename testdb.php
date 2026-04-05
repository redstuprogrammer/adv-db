<?php
require_once __DIR__ . '/includes/connect.php';

$action = $_GET["action"] ?? $_POST["action"] ?? "";

if ($action === "login" && $_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    $identifier = $_POST["identifier"] ?? "";
    $password = $_POST["password"] ?? "";

    if (empty($identifier)) {
        $json = file_get_contents("php://input");
        $data = json_decode($json, true);
        $identifier = $data["identifier"] ?? "";
        $password = $data["password"] ?? "";
    }

    if (empty($identifier) || empty($password)) {
        echo json_encode(["success" => false, "message" => "Credentials required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT p.*, t.company_name FROM patient p JOIN tenants t ON p.tenant_id = t.tenant_id WHERE (p.email = ? OR p.username = ?) AND t.status = 'active'");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();

    if (!$patient) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    if (!password_verify($password, $patient["password"])) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    echo json_encode(["success" => true, "message" => "Login successful", "patient" => ["patient_id" => $patient["patient_id"], "first_name" => $patient["first_name"], "last_name" => $patient["last_name"], "email" => $patient["email"], "phone" => $patient["contact_number"], "clinic" => $patient["company_name"], "tenant_id" => $patient["tenant_id"]]]);
    exit;
}

$result = mysqli_query($conn, "SELECT NOW() AS server_time");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Database is online! Current server time: " . $row["server_time"];
} else {
    echo "Database test failed: " . mysqli_error($conn);
}
?>