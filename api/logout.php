
<?php

require_once __DIR__ . "/../includes/auth.php";

// Logout must use POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(
        false,
        "Only POST requests are allowed.",
        null,
        405
    );
}

try {
    // Revoke the current session token.
    $stmt = $connection->prepare(
        "DELETE FROM auth_tokens WHERE token_hash = ?"
    );

    $stmt->bind_param("s", $authenticatedTokenHash);
    $stmt->execute();
    $stmt->close();

    sendJsonResponse(
        true,
        "Logout successful.",
        null,
        200
    );

} catch (Throwable $e) {
    error_log("Logout API error: " . $e->getMessage());

    sendJsonResponse(
        false,
        "Logout could not be completed.",
        null,
        500
    );
}
