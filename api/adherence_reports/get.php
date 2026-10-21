<?php

require_once __DIR__ . "/../../includes/api-response.php";
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../includes/crud-handler-last3.php";

$resources = require __DIR__ . "/../../includes/crud-resources-last3.php";
$resource = $resources["adherence_reports"];

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    sendJsonResponse(false, "Only GET requests are allowed", null, 405);
}

try {
    if (isset($_GET["reportId"])) {
        if (!ctype_digit($_GET["reportId"])) {
            sendJsonResponse(false, "Valid reportId is required", null, 400);
        }

        $id = (int) $_GET["reportId"];
        $row = fetchRowById($connection, $resource, $id);

        if ($row === null) {
            sendJsonResponse(false, "Record not found", null, 404);
        }

        sendJsonResponse(
            true,
            "Adherence report retrieved successfully",
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
    sendJsonResponse(true, "Adherence reports retrieved successfully", $rows, 200);
} catch (Throwable $error) {
    error_log("Get adherence_reports failed: " . $error->getMessage());
    sendJsonResponse(false, "Internal server error", null, 500);
}
