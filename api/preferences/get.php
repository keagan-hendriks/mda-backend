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

// Temporary hardcoded user until authentication is implemented
//completed to do: replace hardcoded user once login and auth works <-- serious security vulnerability
//checks if user_id is set
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

$userId = (int) $_GET["userId"]; //this line can be replaced when Auth works

try {

    require_once __DIR__ . "/../../db_connect.php";
    require_once __DIR__ . "/../../includes/preferences-db.php";

    $preferences = getPreferencesForUser(
        $connection,
        $userId
    );

    if ($preferences === null) {
        sendJsonResponse(
            false,
            "Preferences not found",
            null,
            404
        );
    }

    sendJsonResponse(
        true,
        "Preferences retrieved successfully",
        $preferences,
        200
    );

} catch (Throwable $error) {

    error_log(
        "GET preferences failed: " . $error->getMessage()
    );

    sendJsonResponse(
        false,
        "Internal server error",
        null,
        500
    );
}
