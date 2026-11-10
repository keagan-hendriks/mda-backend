<?php
//local env connection to xampp, will differ for each team member based on ports

//commented out to try docker version - do not delete commented version
//$dbHost = "127.0.0.1";
//$dbUsername = "root";
//$dbPassword = ""; //please keep var names like this to avoid messing with API and security code (avoid generic names like password)
//$dbName = "medication_app";
//$dbPort = 3306; //may be different check in your XAMPP
//
//mysqli_report(
//    MYSQLI_REPORT_ERROR |
//    MYSQLI_REPORT_STRICT
//);
//
//try {
//
//    $connection = new mysqli(
//        $dbHost,
//        $dbUsername,
//        $dbPassword,
//        $dbName,
//        $dbPort
//    );
//
//    $connection->set_charset("utf8mb4");
//
//} catch (mysqli_sql_exception $error) {
//
//    error_log(
//        "Database connection failed: "
//        . $error->getMessage()
//    );
//
//    throw new RuntimeException(
//        "Database connection failed."
//    );
//}


// Uses Docker Compose environment variables; retains original variable names.
// For legacy XAMPP usage, unset variables fall back to the original local values.
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUsername = getenv('DB_USERNAME') ?: 'root';
$dbPassword = getenv('DB_PASSWORD');
if ($dbPassword === false) $dbPassword = '';
$dbName = getenv('DB_NAME') ?: 'medication_app';
$dbPort = (int)(getenv('DB_PORT') ?: '3306');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $connection = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName, $dbPort);
    $connection->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    error_log('Database connection failed: ' . $error->getMessage());
    throw new RuntimeException('Database connection failed.');
}
