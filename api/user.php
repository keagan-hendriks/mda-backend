
<?php

header("Content-Type: application/json; charset=UTF-8");

// Authenticate the request using the existing session token.
require_once __DIR__ . "/../includes/auth.php";

// Return details for the authenticated user only.
sendJsonResponse(
    true,
    "User details retrieved successfully.",
    [
        "user_id" => (int) $authenticatedUser["user_id"],
        "full_name" => $authenticatedUser["full_name"],
        "email" => $authenticatedUser["email"]
    ],
    200
);
