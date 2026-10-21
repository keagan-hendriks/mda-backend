<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["trusted_contacts"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    sendJsonResponse(false, "Only POST requests are allowed", null, 405);
}

try {
    $data = readJsonBody();
    $created = createResource($connection, $resource, $data);
    sendJsonResponse(true, "Trusted contact created successfully", $created, 201);
} catch (Throwable $error) {
    error_log("Create trusted_contacts failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
