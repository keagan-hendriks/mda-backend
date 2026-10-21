<?php

require_once __DIR__ . "/../../includes/api-response.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");

    sendJsonResponse(
        false,
        "Only GET requests are allowed",
        null,
        405
    );
}

if (
    !isset($_GET["userId"]) ||
    !ctype_digit($_GET["userId"])
) {
    sendJsonResponse(
        false,
        "Valid userId is required",
        null,
        400
    );
}

$userId = (int) $_GET["userId"];

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

    sendJsonResponse(
        true,
        "User retrieved successfully",
        $user,
        200
    );

} catch (Throwable $error) {

    error_log(
        "Get user failed: " .
        $error->getMessage()
    );

    sendJsonResponse(
        false,
        "Internal server error",
        null,
        500
    );
}