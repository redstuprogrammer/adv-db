} catch (Throwable $e) {
    error_log("FATAL ERROR: " . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    die("Error loading dependencies");
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
