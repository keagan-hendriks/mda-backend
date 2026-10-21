<?php

require_once __DIR__ . "/../../includes/api-response.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendJsonResponse(
        false,
        "Only POST requests are allowed",
        null,
        405
    );
}

//read JSON response body
$rawBody = file_get_contents("php://input");

if ($rawBody === false || trim($rawBody) === "") {
    sendJsonResponse(
        false,
        "Request body is required",
        null,
        400
    );
}

try {
    $data = json_decode(
        $rawBody,
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $error) {
    sendJsonResponse(
        false,
        "Invalid JSON request body",
        null,
        400
    );
}

if (!is_array($data)) {
    sendJsonResponse(
        false,
        "Request body must be a JSON object",
        null,
        400
    );
}


//validation(fullName)
if (
    !array_key_exists("fullName", $data) ||
    !is_string($data["fullName"]) ||
    trim($data["fullName"]) === ""
) {
    sendJsonResponse(
        false,
        "fullName is required",
        null,
        400
    );
}


if (
    !array_key_exists("email", $data) ||
    !is_string($data["email"]) ||
    trim($data["email"]) === ""
) {
    sendJsonResponse(
        false,
        "email is required",
        null,
        400
    );
}

//password validation
if (
    !array_key_exists("password", $data) ||
    !is_string($data["password"])
) {
    sendJsonResponse(
        false,
        "password is required",
        null,
        400
    );
}

//$password = $data["password"]; //removed due to naming conflict that caused a bug
$plainPassword = $data["password"];

if (strlen($plainPassword) === 0) {
    sendJsonResponse(
        false,
        "password cannot be empty",
        null,
        400
    );
}

//trim valid values
$fullName = trim($data["fullName"]);
$email = trim($data["email"]);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(
        false,
        "Invalid email address",
        null,
        400
    );
}


//db changes
try {

    require_once __DIR__ . "/../../db_connect.php";
    require_once __DIR__ . "/../../includes/users-db.php";


    // Check for duplicate email
    $existingUser = getUserByEmail(
        $connection,
        $email
    );

    if ($existingUser !== null) {
        sendJsonResponse(
            false,
            "A user with this email already exists",
            null,
            409
        );
    }


    //Hash plain-text password
    $passwordHash = password_hash(
        $plainPassword,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        throw new RuntimeException(
            "Password hashing failed"
        );
    }


    // Temporary safe debugging.
    // Does NOT log the password itself.
//    error_log(
//        "CREATE USER DEBUG"
//        . " | script=" . __FILE__
//        . " | email=" . $email
//        . " | passwordLength=" . strlen($plainPassword)
//        . " | hashLength=" . strlen($passwordHash)
//        . " | verifiesImmediately="
//        . (
//        password_verify(
//            $plainPassword,
//            $passwordHash
//        )
//            ? "YES"
//            : "NO"
//        )
//    );


    // User + default preferences must succeed together.
    $connection->begin_transaction();

    try {

        $userId = createUser(
            $connection,
            $fullName,
            $email,
            $passwordHash
        );


        // Create default preferences
        $preferencesSql = "
            INSERT INTO user_preferences (
                user_id,
                notifications_enabled,
                sound_enabled,
                trusted_contact_sharing
            )
            VALUES (?, 1, 1, 0)
        ";

        $statement = $connection->prepare(
            $preferencesSql
        );

        $statement->bind_param(
            "i",
            $userId
        );

        $statement->execute();
        $statement->close();


        // Read the user back from the database
        $user = getUserById(
            $connection,
            $userId
        );

        if ($user === null) {
            throw new RuntimeException(
                "Created user could not be retrieved"
            );
        }


        // Verify that the stored hash still matches.
        if (
            !password_verify(
                $plainPassword,
                $user["passwordHash"]
            )
        ) {
            throw new RuntimeException(
                "Stored password hash verification failed"
            );
        }

//        error_log(
//            "CREATE USER DATABASE DEBUG"
//            . " | userId=" . $userId
//            . " | storedHashLength="
//            . strlen($user["passwordHash"])
//            . " | verifiesAfterDatabase=YES"
//        );


        $connection->commit();


        sendJsonResponse(
            true,
            "User created successfully",
            sanitizeUserForResponse($user),
            201
        );

    } catch (Throwable $error) {

        $connection->rollback();

        throw $error;
    }

} catch (Throwable $error) {

    error_log(
        "Create user failed: "
        . $error->getMessage()
    );

    sendJsonResponse(
        false,
        "Internal server error",
        null,
        500
    );
}