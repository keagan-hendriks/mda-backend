<?php
//preferences api response page
//TODO: replace hardcoded temporary variables with db calls

//please always use response handler
require_once __DIR__ . "/../../includes/api-response.php";

//GET requests only
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");

    sendJsonResponse(
        false,
        "Only GET requests are allowed",
        null,
        405
    );
}

//TODO: repalce with db call TEMPORARY: Simulated database result
$preferences = [
    "userId" => 123,
    "notificationsEnabled" => true,
    "theme" => "dark"
];

//success response
sendJsonResponse(
    true,
    "Preferences retrieved successfully",
    $preferences,
    200
);