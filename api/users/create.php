
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../includes/db_connect.php";

// This endpoint must receive a POST request.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(false, "Only POST requests are allowed.", null, 405);
}

// Read the JSON sent by Kotlin/Gson.
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    sendJsonResponse(false, "Invalid JSON request.", null, 400);
}

// Gson sends fullName by default, not full_name.
$fullName = trim($input["fullName"] ?? "");
$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

// Validate required fields.
if (
    $fullName === "" ||
    $email === "" ||
    !is_string($password) ||
    $password === ""
) {
    sendJsonResponse(
        false,
        "Full name, email and password are required.",
        null,
        400
    );
}

if (strlen($fullName) > 100 || strlen($email) > 255) {
    sendJsonResponse(false, "Full name or email is too long.", null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, "Please provide a valid email address.", null, 400);
}

if (strlen($password) < 8 || strlen($password) > 72) {
    sendJsonResponse(
        false,
        "Password must be between 8 and 72 bytes.",
        null,
        400
    );
}

// Never store the original password.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($passwordHash === false) {
    sendJsonResponse(false, "Could not process registration.", null, 500);
}

try {
    $connection->begin_transaction();

    // Create the user with a securely hashed password.
    $stmt = $connection->prepare(
        "INSERT INTO users (full_name, email, password_hash)
         VALUES (?, ?, ?)"
    );

    $stmt->bind_param("sss", $fullName, $email, $passwordHash);
    $stmt->execute();

    $userId = (int) $connection->insert_id;
    $stmt->close();

    // Initialize the user's default rewards.
    $stmt = $connection->prepare(
        "INSERT INTO user_rewards (user_id) VALUES (?)"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    // Initialize the user's default preferences.
    $stmt = $connection->prepare(
        "INSERT INTO user_preferences (user_id) VALUES (?)"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    // Retrieve the actual creation timestamp for Kotlin's User model.
    $stmt = $connection->prepare(
        "SELECT created_at FROM users WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($createdAt);
    $stmt->fetch();
    $stmt->close();

    $connection->commit();

    // Use camelCase field names expected by Kotlin.
    sendJsonResponse(
        true,
        "Registration successful.",
        [
            "userId" => $userId,
            "fullName" => $fullName,
            "email" => $email,
            "createdAt" => (string) $createdAt
        ],
        201
    );

} catch (mysqli_sql_exception $e) {
    $connection->rollback();

    if ($e->getCode() === 1062) {
        sendJsonResponse(
            false,
            "An account with this email already exists.",
            null,
            409
        );
    }

    error_log("User creation error: " . $e->getMessage());

    sendJsonResponse(
        false,
        "Registration could not be completed.",
        null,
        500
    );

} catch (Throwable $e) {
    $connection->rollback();

    error_log("User creation error: " . $e->getMessage());

    sendJsonResponse(
        false,
        "Registration could not be completed.",
        null,
        500
    );
}
