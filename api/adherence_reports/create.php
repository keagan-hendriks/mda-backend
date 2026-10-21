<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["adherence_reports"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    sendJsonResponse(false, "Only POST requests are allowed", null, 405);
}

try {
    $data = readJsonBody();
    $created = createResource($connection, $resource, $data);
    sendJsonResponse(true, "Adherence report created successfully", $created, 201);
} catch (Throwable $error) {
    error_log("Create adherence_reports failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
