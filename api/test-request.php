<?php

date_default_timezone_set("Africa/Johannesburg");

header("Content-Type: application/json");

// Only allow POST requests, don't look at browser message look if success case shows up in the text file
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed"
    ]);

    exit;
}

$json = file_get_contents("php://input");

if (empty($json)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "No request body received"
    ]);

    exit;
}

$data = json_decode($json, true);

if ($data === null) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON received"
    ]);

    exit;
}

$message = $data["message"] ?? null;

if ($message === null) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Missing message field"
    ]);

    exit;
}

// SUCCESS CASE
http_response_code(200);

//simple success case logger because cannot show success case in browser(browser makes GET requests)
file_put_contents(
    __DIR__ . "/request-log.txt",
    date("c") . " - Received: " . $message . PHP_EOL,
    FILE_APPEND
);

echo json_encode([
    "success" => true,
    "received" => $message,
    "timestamp" => date("c")
]);

exit;