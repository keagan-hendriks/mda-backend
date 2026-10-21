
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

//changed: Temporary fixed user until authentication is implemented.
//now checks for logged in userID
if (
    !array_key_exists("userId", $data) ||
    !is_int($data["userId"])
) {
    sendJsonResponse(
        false,
        "userId must be an integer",
        null,
        400
    );
}

$userId = $data["userId"];

// Read incoming JSON.
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

// Require all three preference fields as JSON booleans.
$requiredFields = [
    "notificationsEnabled",
    "soundEnabled",
    "trustedContactSharing"
];

foreach ($requiredFields as $field) {

    if (
        !array_key_exists($field, $data) ||
        !is_bool($data[$field])
    ) {
        sendJsonResponse(
            false,
            "$field must be true or false",
            null,
            400
        );
    }
}

try {

    require_once __DIR__ . "/../../db_connect.php";
    require_once __DIR__ . "/../../includes/preferences-db.php";

    // Make sure the preferences row exists.
    $existingPreferences = getPreferencesForUser(
        $connection,
        $userId
    );

    if ($existingPreferences === null) {
        sendJsonResponse(
            false,
            "Preferences not found",
            null,
            404
        );
    }

    // Save the new preferences in the database.
    updatePreferencesForUser(
        $connection,
        $userId,
        $data["notificationsEnabled"],
        $data["soundEnabled"],
        $data["trustedContactSharing"]
    );

    // Read back the values actually stored.
    $updatedPreferences = getPreferencesForUser(
        $connection,
        $userId
    );

    sendJsonResponse(
        true,
        "Preferences updated successfully",
        $updatedPreferences,
        200
    );

} catch (Throwable $error) {

    error_log(
        "PUT preferences failed: " . $error->getMessage()
    );

    sendJsonResponse(
        false,
        "Internal server error",
        null,
        500
    );
}
