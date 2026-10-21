<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["trusted_contacts"];

if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    header("Allow: DELETE");
    sendJsonResponse(false, "Only DELETE requests are allowed", null, 405);
}

try {
    $data = readJsonBody();

    if (!isset($data["contactId"]) || !is_int($data["contactId"])) {
        sendJsonResponse(false, "contactId is required", null, 400);
    }

    deleteResource($connection, $resource, $data["contactId"]);
    sendJsonResponse(true, "Trusted contact deleted successfully", null, 200);
} catch (Throwable $error) {
    error_log("Delete trusted_contacts failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
