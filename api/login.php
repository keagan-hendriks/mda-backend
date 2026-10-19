
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/api-response.php";
require_once __DIR__ . "/../includes/db_connect.php";

// Login must use POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(false, "Only POST requests are allowed.", null, 405);
}

// Read JSON sent by the Android app.
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    sendJsonResponse(false, "Invalid JSON request.", null, 400);
}

$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

if (
    $email === "" ||
    !is_string($password) ||
    $password === ""
) {
    sendJsonResponse(
        false,
        "Email and password are required.",
        null,
        400
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(
        false,
        "Invalid email or password.",
        null,
        401
    );
}

try {
    // Find the account by email.
    $stmt = $connection->prepare(
        "SELECT user_id, full_name, email, password_hash
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    // Do not reveal whether the email or password was incorrect.
    if (
        !$user ||
        !password_verify($password, $user["password_hash"])
    ) {
        sendJsonResponse(
            false,
            "Invalid email or password.",
            null,
            401
        );
    }

    // Generate a secure random token for the session.
    $token = bin2hex(random_bytes(32));

    // Store only the token hash in the database.
    $tokenHash = hash("sha256", $token);
    $userId = (int) $user["user_id"];

    $stmt = $connection->prepare(
        "INSERT INTO auth_tokens
            (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))"
    );

    $stmt->bind_param("is", $userId, $tokenHash);
    $stmt->execute();
    $stmt->close();

    // Return the original token to the app once.
    sendJsonResponse(
        true,
        "Login successful.",
        [
            "token" => $token,
            "expires_in" => 3600,
            "user" => [
                "user_id" => $userId,
                "full_name" => $user["full_name"],
                "email" => $user["email"]
            ]
        ],
        200
    );

} catch (Throwable $e) {
    error_log("Login API error: " . $e->getMessage());

    sendJsonResponse(
        false,
        "Login could not be completed.",
        null,
        500
    );
}
