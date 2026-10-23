<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/api-response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sendJsonResponse(false, 'Only POST requests are allowed', null, 405);
}

try {
    // Apache and PHP installations vary in how Authorization is exposed.
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    if (!is_string($header) || !preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $matches)) {
        sendJsonResponse(false, 'Bearer token required', null, 401);
    }

    $tokenHash = hash('sha256', $matches[1]);
    require __DIR__ . '/../db_connect.php';
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new RuntimeException('Database connection missing');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $stmt = $connection->prepare('DELETE FROM auth_tokens WHERE token_hash = ?');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    if ($affectedRows === 0) {
        sendJsonResponse(false, 'Invalid or already revoked token', null, 401);
    }
    sendJsonResponse(true, 'Logout successful', null, 200);
} catch (Throwable $e) {
    error_log('Logout error: ' . $e->getMessage());
    sendJsonResponse(false, 'Internal server error', null, 500);
}
