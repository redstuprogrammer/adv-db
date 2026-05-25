<?php
// Runs the requested tenant_activity_logs queries and outputs results as HTML.
// Access: ensure your DB permissions allow reading tenant_activity_logs.

session_start();

require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';

// Prefer mysqli (already initialized in connect.php as $conn)
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database connection ($conn) is not available.';
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function run_query_mysqli(mysqli $conn, string $sql, string $title): void {
    echo '<h2>' . h($title) . '</h2>';
    echo '<pre style="white-space:pre-wrap;word-break:break-word;background:#f7f7f7;border:1px solid #ddd;padding:10px;">' . h($sql) . '</pre>';

    $res = $conn->query($sql);
    if ($res === false) {
        echo '<div style="color:#b00020;font-weight:600;">Query failed: ' . h($conn->error) . '</div>';
        return;
    }

    if ($res->num_rows === 0) {
        echo '<div>No rows.</div>';
        return;
    }

    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:1100px;">';

    // Header
    $cols = [];
    $fieldMeta = $res->fetch_fields();
    foreach ($fieldMeta as $f) {
        $cols[] = $f->name;
    }
    echo '<thead><tr>';
    foreach ($cols as $c) {
        echo '<th>' . h($c) . '</th>';
    }
    echo '</tr></thead>';

    echo '<tbody>';
    while ($row = $res->fetch_assoc()) {
        echo '<tr>';
        foreach ($cols as $c) {
            $val = $row[$c];
            if ($val === null) $val = '';
            echo '<td>' . h((string)$val) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';

    $res->free();
}

$sql1 = "SELECT DISTINCT activity_type FROM tenant_activity_logs";
$sql2 = "SELECT activity_description, COUNT(*) AS cnt
         FROM tenant_activity_logs
         GROUP BY activity_description
         ORDER BY cnt DESC
         LIMIT 50";
$sql3 = "SELECT *
         FROM tenant_activity_logs
         WHERE log_date = CURDATE()
         ORDER BY log_time DESC
         LIMIT 50";

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>tenant_activity_logs Query Runner</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif; margin:20px;">
  <h1>tenant_activity_logs Query Runner</h1>

  <?php
    run_query_mysqli($conn, $sql1, '1) DISTINCT activity_type');
    run_query_mysqli($conn, $sql2, '2) Top 50 activity_description by COUNT(*)');
    run_query_mysqli($conn, $sql3, '3) Logs for today (log_date = CURDATE()), latest first (50)');
  ?>
</body>
</html>

