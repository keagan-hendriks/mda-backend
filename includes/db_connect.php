
<?php

$host = "localhost";
$dbname = "medication_app";
$username = "root";
$password = ""; // Local XAMPP default; configure securely for deployment.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $connection = new mysqli(
        $host,
        $username,
        $password,
        $dbname
    );

    $connection->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log("Database connection error: " . $e->getMessage());

    require_once __DIR__ . "/api-response.php";
    sendJsonResponse(
        false,
        "Database connection could not be established.",
        null,
        500
    );
}
