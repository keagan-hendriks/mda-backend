<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["adherence_reports"];

if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    header("Allow: PUT");
    sendJsonResponse(false, "Only PUT requests are allowed", null, 405);
}

try {
    $data = readJsonBody();

    if (!isset($data["reportId"]) || !is_int($data["reportId"])) {
        sendJsonResponse(false, "reportId is required", null, 400);
    }

    $id = $data["reportId"];
    unset($data["reportId"]);

    $updated = updateResource($connection, $resource, $id, $data);
    sendJsonResponse(true, "Adherence report updated successfully", $updated, 200);
} catch (Throwable $error) {
    error_log("Update adherence_reports failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
