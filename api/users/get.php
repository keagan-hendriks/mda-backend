
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../includes/db_connect.php";

// This endpoint uses GET.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    sendJsonResponse(false, "Only GET requests are allowed.", null, 405);
}

// Kotlin sends the user ID as ?userId=3.
$userId = filter_input(INPUT_GET, "userId", FILTER_VALIDATE_INT);

if ($userId === false || $userId === null || $userId < 1) {
    sendJsonResponse(false, "A valid userId is required.", null, 400);
}

try {
    // Select only fields the Android app needs.
    // Never return password_hash.
    $stmt = $connection->prepare(
        "SELECT user_id, full_name, email, created_at
         FROM users
         WHERE user_id = ?"
    );

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $stmt->bind_result($dbUserId, $dbFullName, $dbEmail, $dbCreatedAt);

    if (!$stmt->fetch()) {
        $stmt->close();
        sendJsonResponse(false, "User not found.", null, 404);
    }

    $stmt->close();

    // Use the camelCase property names expected by Kotlin's User model.
    sendJsonResponse(
        true,
        "User retrieved successfully.",
        [
            "userId" => (int) $dbUserId,
            "fullName" => $dbFullName,
            "email" => $dbEmail,
            "createdAt" => $dbCreatedAt
        ],
        200
    );

} catch (Throwable $e) {
    error_log("Get user error: " . $e->getMessage());

    sendJsonResponse(
        false,
        "Could not retrieve user.",
        null,
        500
    );
}
