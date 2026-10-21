<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["trusted_contacts"];

if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    header("Allow: PUT");
    sendJsonResponse(false, "Only PUT requests are allowed", null, 405);
}

try {
    $data = readJsonBody();

    if (!isset($data["contactId"]) || !is_int($data["contactId"])) {
        sendJsonResponse(false, "contactId is required", null, 400);
    }

    $id = $data["contactId"];
    unset($data["contactId"]);

    $updated = updateResource($connection, $resource, $id, $data);
    sendJsonResponse(true, "Trusted contact updated successfully", $updated, 200);
} catch (Throwable $error) {
    error_log("Update trusted_contacts failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
