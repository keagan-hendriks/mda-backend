<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/api-response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sendJsonResponse(false, 'Only POST requests are allowed', null, 405);
}

function loginFail(int $status, string $message): void {
    sendJsonResponse(false, $message, null, $status);
}

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') loginFail(400, 'Request body is required');
    try {
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        loginFail(400, 'Invalid JSON request body');
    }
    if (!is_array($body) || !str_starts_with(ltrim($raw), '{')) {
        loginFail(400, 'Request body must be a JSON object');
    }
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;
    if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) ||
        !is_string($password) || $password === '') {
        loginFail(400, 'Valid email and password are required');
    }

    require __DIR__ . '/../db_connect.php';
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new RuntimeException('Database connection missing');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $email = trim($email);
    $stmt = $connection->prepare('SELECT user_id, full_name, email, password_hash, created_at FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        loginFail(401, 'Invalid email or password');
    }

    // Return the bearer token only to the client; store its SHA-256 hash in MariaDB.
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresIn = 3600; // seconds
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . $expiresIn . ' seconds')->format('Y-m-d H:i:s');
    $userId = (int) $user['user_id'];

    $stmt = $connection->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $tokenHash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    sendJsonResponse(true, 'Login successful', [
        'token' => $token,
        'expiresIn' => $expiresIn,
        'user' => [
            'userId' => $userId,
            'fullName' => $user['full_name'],
            'email' => $user['email'],
            'createdAt' => $user['created_at'],
        ],
    ], 200);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    sendJsonResponse(false, 'Internal server error', null, 500);
}
