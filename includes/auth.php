
<?php

require_once __DIR__ . "/api-response.php";
require_once __DIR__ . "/db_connect.php";

// Read the Authorization header.
$authorization = "";

if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
    $authorization = trim($_SERVER["HTTP_AUTHORIZATION"]);
} elseif (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
    $authorization = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
} elseif (function_exists("getallheaders")) {
    foreach (getallheaders() as $name => $value) {
        if (strtolower($name) === "authorization") {
            $authorization = trim($value);
            break;
        }
    }
}

// Require: Authorization: Bearer <token>
if (
    !preg_match(
        '/^Bearer\s+([a-f0-9]{64})$/i',
        $authorization,
        $matches
    )
) {
    sendJsonResponse(
        false,
        "A valid bearer token is required.",
        null,
        401
    );
}

// Hash the supplied token before checking the database.
$tokenHash = hash("sha256", $matches[1]);
$authenticatedTokenHash = $tokenHash;

try {
    $stmt = $connection->prepare(
        "SELECT u.user_id, u.full_name, u.email
         FROM auth_tokens AS t
         INNER JOIN users AS u ON u.user_id = t.user_id
         WHERE t.token_hash = ?
           AND t.expires_at > UTC_TIMESTAMP()
         LIMIT 1"
    );

    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();

    $result = $stmt->get_result();
    $authenticatedUser = $result->fetch_assoc();

    $stmt->close();

    if (!$authenticatedUser) {
        sendJsonResponse(
            false,
            "Invalid or expired token.",
            null,
            401
        );
    }

    // Available to the protected endpoint that includes this file.
    $authenticatedUserId = (int) $authenticatedUser["user_id"];

} catch (Throwable $e) {
    error_log("Authentication error: " . $e->getMessage());

    sendJsonResponse(
        false,
        "Authentication could not be completed.",
        null,
        500
    );
}
