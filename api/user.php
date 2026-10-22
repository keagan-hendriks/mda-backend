
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/auth.php";

// Return details only after the session token has been validated.
sendJsonResponse(
    true,
    "User details retrieved successfully.",
    [
        "userId" => (int) $authenticatedUser["user_id"],
        "fullName" => $authenticatedUser["full_name"],
        "email" => $authenticatedUser["email"]
    ],
    200
);
