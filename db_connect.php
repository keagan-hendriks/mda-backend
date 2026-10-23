<?php
//local env connection to xampp, will differ for each team member based on ports
$dbHost = "127.0.0.1";
$dbUsername = "root";
$dbPassword = ""; //please keep var names like this to avoid messing with API and security code (avoid generic names like password)
$dbName = "medication_app";
$dbPort = 3306; //may be different check in your XAMPP

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

try {

    $connection = new mysqli(
        $dbHost,
        $dbUsername,
        $dbPassword,
        $dbName,
        $dbPort
    );

    $connection->set_charset("utf8mb4");

} catch (mysqli_sql_exception $error) {

    error_log(
        "Database connection failed: "
        . $error->getMessage()
    );

    throw new RuntimeException(
        "Database connection failed."
    );
}