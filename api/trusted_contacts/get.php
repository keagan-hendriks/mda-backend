<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["trusted_contacts"];

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    sendJsonResponse(false, "Only GET requests are allowed", null, 405);
}

try {
    if (isset($_GET["contactId"])) {
        if (!ctype_digit($_GET["contactId"])) {
            sendJsonResponse(false, "Valid contactId is required", null, 400);
        }

        $id = (int) $_GET["contactId"];
        $row = fetchRowById($connection, $resource, $id);

        if ($row === null) {
            sendJsonResponse(false, "Record not found", null, 404);
        }

        sendJsonResponse(
            true,
            "Trusted contact retrieved successfully",
            mapDbRow($row, $resource["responseMap"]),
            200
        );
    }

    $userId = null;
    if (isset($_GET["userId"])) {
        if (!ctype_digit($_GET["userId"])) {
            sendJsonResponse(false, "Valid userId is required", null, 400);
        }
        $userId = (int) $_GET["userId"];
    }

    $rows = fetchRows($connection, $resource, $userId);
    sendJsonResponse(true, "Trusted contacts retrieved successfully", $rows, 200);
} catch (Throwable $error) {
    error_log("Get trusted_contacts failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
