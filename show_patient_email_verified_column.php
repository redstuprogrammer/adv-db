<?php
// Quick DB inspector: show column definition for patient.email_verified
// URL:
//   /adv db/show_patient_email_verified_column.php
// Optional:
//   ?format=json

ob_start();

$format = strtolower(trim($_GET['format'] ?? 'html'));

header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/connect.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Server error</h1><p>Database connection not available.</p>';
    }
    exit;
}

$sql = "SHOW COLUMNS FROM patient LIKE 'email_verified'";

$result = $conn->query($sql);

if ($result === false) {
    http_response_code(500);
    $err = $conn->error ?: 'Unknown MySQL error';

    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $err, 'query' => $sql]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Query error</h1>';
        echo '<p><code>' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '</code></p>';
        echo '<p>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'query' => $sql,
        'rows' => $rows,
        'found' => count($rows) > 0,
    ]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');

?><!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>patient.email_verified column</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 24px; }
        code { background:#f6f6f6; padding:2px 6px; border-radius:4px; }
        table { border-collapse: collapse; width: 100%; max-width: 900px; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #fafafa; }
        .muted { color: #666; }
        .notfound { padding: 12px; background: #fff3f3; border: 1px solid #ffcdd2; border-radius: 8px; max-width: 900px; }
    </style>
</head>
<body>
    <h1>SHOW COLUMNS result</h1>
    <p class="muted">Query: <code><?php echo htmlspecialchars($sql, ENT_QUOTES, 'UTF-8'); ?></code></p>

    <?php if (count($rows) === 0): ?>
        <div class="notfound">
            <strong>Column not found.</strong>
            <div class="muted">No matching column named <code>email_verified</code> exists in table <code>patient</code>.</div>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <?php
                    // Use column names returned by MySQL for the "SHOW COLUMNS" output
                    $headers = array_keys($rows[0]);
                    foreach ($headers as $h) {
                        echo '<th>' . htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <td><?php echo htmlspecialchars((string)($r[$h] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="muted" style="margin-top:16px;">
        JSON output: <a href="?format=json">?format=json</a>
    </p>
</body>
</html>
<?php
ob_end_flush();

