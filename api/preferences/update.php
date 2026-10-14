<?php

require_once __DIR__ . "/../../includes/api-response.php";

date_default_timezone_set("Africa/Johannesburg");

$logFile = __DIR__ . "/../../logs/mock-api.log";

// Only accept PUT
if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    //what to log in the log file
    @file_put_contents( //@ sign modifies function to supress PHP warning so that it does not mess up HTTP request/responses
        $logFile,
        date("c") . "failed to update preferences, wrong HTTP method: "
        . $_SERVER["REQUEST_METHOD"]
        . PHP_EOL,
        FILE_APPEND
    );

    header("Allow: PUT");

    //what PHP tells android
    sendJsonResponse(
        false,
        "Only PUT requests are allowed",
        null,
        405
    );
}

// Read JSON request body
$json = file_get_contents("php://input");

if (empty($json)) {

    @file_put_contents(
        $logFile,
        date("c") . " failed to update preferences, empty request body "
        . PHP_EOL,
        FILE_APPEND
    );

    sendJsonResponse(
        false,
        "No request body received",
        null,
        400
    );
}

// Convert JSON into PHP array
$data = json_decode($json, true);

if ($data === null) {

    @file_put_contents(
        $logFile,
        date("c") . " failed to update preferences, invalid JSON received "
        . PHP_EOL,
        FILE_APPEND
    );

    sendJsonResponse(
        false,
        "Invalid JSON received",
        null,
        400
    );
}

//TODO: change validation depending on actual db logic and add db update method
//Validate required fields
if (
    !array_key_exists("notificationsEnabled", $data) ||
    !array_key_exists("theme", $data)
) {

    @file_put_contents(
        $logFile,
        date("c") . "failed to update preferences, missing field(s)"
        . PHP_EOL,
        FILE_APPEND
    );

    sendJsonResponse(
        false,
        "Missing preference fields",
        null,
        400
    );
}

// Basic validation
if (!is_bool($data["notificationsEnabled"])) {

    sendJsonResponse(
        false,
        "notificationsEnabled must be true or false",
        null,
        400
    );
}

$allowedThemes = ["light", "dark"];

if (!in_array($data["theme"], $allowedThemes, true)) {

    sendJsonResponse(
        false,
        "Invalid theme",
        null,
        400
    );
}


$updatedPreferences = [
    "userId" => 123,
    "notificationsEnabled" => $data["notificationsEnabled"],
    "theme" => $data["theme"]
];


//TODO: remove when done because it'll cost unnecessary resources to write to log file when we can validate in SQL db
//Temporary server-side confirmation
@file_put_contents( //@ sign supresses warning because success case included warniong
    $logFile,
    date("c")
    . "succes case"
    . "which user: user placeholder"
    . " | notificationsEnabled="
    . ($data["notificationsEnabled"] ? "true" : "false")
    . " | theme="
    . $data["theme"]
    . PHP_EOL,
    FILE_APPEND
);


//Return standardized response to Android, please always follow this pattern for response
sendJsonResponse(
    true,
    "Preferences updated successfully",
    $updatedPreferences,
    200
);