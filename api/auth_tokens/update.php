<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["auth_tokens"];

if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    header("Allow: PUT");
    sendJsonResponse(false, "Only PUT requests are allowed", null, 405);
}

try {
    $data = readJsonBody();

    if (!isset($data["tokenId"]) || !is_int($data["tokenId"])) {
        sendJsonResponse(false, "tokenId is required", null, 400);
    }

    $id = $data["tokenId"];
    unset($data["tokenId"]);

    $updated = updateResource($connection, $resource, $id, $data);
    sendJsonResponse(true, "Auth token updated successfully", $updated, 200);
} catch (Throwable $error) {
    error_log("Update auth_tokens failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
