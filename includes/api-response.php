<?php

//response handler for API endpoints, only formats and sends responses do not use for other backend work or SQL
function sendJsonResponse(
    bool   $success,
    string $message,
           $data = null,
    int    $statusCode = 200
): void
{
    //tell android HTTP body contains JSON
    header("Content-Type: application/json");

    //set actual HTTP response status
    http_response_code($statusCode);

    // Standardize JSON response structure
    $response = [
        "success" => $success,
        "message" => $message,
        "data" => $data
    ];

    // Return JSON to client
    echo json_encode($response);

    exit;
}
