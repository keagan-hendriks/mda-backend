<?php

require_once __DIR__ . "/../../includes/api-response.php";

if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    header("Allow: PUT");

    sendJsonResponse(
        false,
        "Only PUT requests are allowed",
        null,
        405
    );
}

$rawBody = file_get_contents("php://input");

try {
    $data = json_decode(
        $rawBody,
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $error) {
    sendJsonResponse(
        false,
        "Invalid JSON request body",
        null,
        400
    );
}

if (!is_array($data)) {
    sendJsonResponse(
        false,
        "Request body must be a JSON object",
        null,
        400
    );
}

$requiredFields = [
    "userId",
    "fullName",
    "email"
];

foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $data)) {
        sendJsonResponse(
            false,
            "$field is required",
            null,
            400
        );
    }
}

if (!is_int($data["userId"])) {
    sendJsonResponse(
        false,
        "userId must be an integer",
        null,
        400
    );
}

if (
    !is_string($data["fullName"]) ||
    trim($data["fullName"]) === ""
) {
    sendJsonResponse(
        false,
        "fullName is required",
        null,
        400
    );
}

if (
    !is_string($data["email"]) ||
    trim($data["email"]) === ""
) {
    sendJsonResponse(
        false,
        "email is required",
        null,
        400
    );
}

$userId = $data["userId"];
$fullName = trim($data["fullName"]);
$email = trim($data["email"]);

try {

    require_once __DIR__ . "/../../db_connect.php";
    require_once __DIR__ . "/../../includes/users-db.php";

    $existingUser = getUserById(
        $connection,
        $userId
    );

    if ($existingUser === null) {
        sendJsonResponse(
            false,
            "User not found",
            null,
            404
        );
    }

    $emailOwner = getUserByEmail(
        $connection,
        $email
    );

    if (
        $emailOwner !== null &&
        $emailOwner["userId"] !== $userId
    ) {
        sendJsonResponse(
            false,
            "Email is already used by another user",
            null,
            409
        );
    }

    updateUser(
        $connection,
        $userId,
        $fullName,
        $email
    );

    $updatedUser = getUserById(
        $connection,
        $userId
    );

    sendJsonResponse(
        true,
        "User updated successfully",
        sanitizeUserForResponse($updatedUser),
        200
    );

} catch (Throwable $error) {

    error_log(
        "Update user failed: " .
        $error->getMessage()
    );

    sendJsonResponse(
        false,
        "Internal server error",
        null,
        500
    );
}
