<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["adherence_reports"];

if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    header("Allow: DELETE");
    sendJsonResponse(false, "Only DELETE requests are allowed", null, 405);
}

try {
    $data = readJsonBody();

    if (!isset($data["reportId"]) || !is_int($data["reportId"])) {
        sendJsonResponse(false, "reportId is required", null, 400);
    }

    deleteResource($connection, $resource, $data["reportId"]);
    sendJsonResponse(true, "Adherence report deleted successfully", null, 200);
} catch (Throwable $error) {
    error_log("Delete adherence_reports failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
