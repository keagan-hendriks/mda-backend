<?php

date_default_timezone_set("Africa/Johannesburg");

header("Content-Type: application/json");

//raw JSON sent in the HTTP request body
$json = file_get_contents("php://input");

//JSON into a PHP associative array
$data = json_decode($json, true);

//basic check, !NB sends message to client if failed
if ($data === null) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing JSON"
    ]);

    exit;
}

$message = $data["message"] ?? null;

// Check that the expected field exists
if ($message === null) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Missing message field"
    ]);

    exit;
}

// Successful response
http_response_code(200);

echo json_encode([
    "success" => true,
    "received" => $message,
    "timestamp" => date("c")
]);

exit;