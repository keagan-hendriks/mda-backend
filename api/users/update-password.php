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

if (
    !isset($data["userId"]) ||
    !is_int($data["userId"])
) {
    sendJsonResponse(
        false,
        "Valid userId is required",
        null,
        400
    );
}

if (
    !isset($data["currentPassword"]) ||
    !is_string($data["currentPassword"]) ||
    $data["currentPassword"] === ""
) {
    sendJsonResponse(
        false,
        "Current password is required",
        null,
        400
    );
}

if (
    !isset($data["newPassword"]) ||
    !is_string($data["newPassword"]) ||
    $data["newPassword"] === ""
) {
    sendJsonResponse(
        false,
        "New password is required",
        null,
        400
    );
}

$userId = $data["userId"];
$currentPassword = $data["currentPassword"];
$newPassword = $data["newPassword"];

try {

    require_once __DIR__ . "/../../db_connect.php";
    require_once __DIR__ . "/../../includes/users-db.php";

    $user = getUserById(
        $connection,
        $userId
    );

    if ($user === null) {
        sendJsonResponse(
            false,
            "User not found",
            null,
            404
        );
    }

    if (
        !password_verify(
            $currentPassword,
            $user["passwordHash"]
        )
    ) {
        sendJsonResponse(
            false,
            "Current password is incorrect",
            null,
            401
        );
    }

    $newPasswordHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    updateUserPassword(
        $connection,
        $userId,
        $newPasswordHash
    );

    sendJsonResponse(
        true,
        "Password updated successfully",
        null,
        200
    );

} catch (Throwable $error) {

    error_log(
        "Update password failed: " .
        $error->getMessage()
    );

    sendJsonResponse(
        false,
        "Internal server error",
        null,
        500
    );
}
