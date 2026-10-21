<?php

function getUserById(
    mysqli $connection,
    int $userId
): ?array {

    $sql = "
        SELECT
            user_id,
            full_name,
            email,
            password_hash,
            created_at
        FROM users
        WHERE user_id = ?
    ";

    $statement = $connection->prepare($sql);
    $statement->bind_param("i", $userId);
    $statement->execute();

    $statement->bind_result(
        $dbUserId,
        $dbFullName,
        $dbEmail,
        $dbPasswordHash,
        $dbCreatedAt
    );

    $found = $statement->fetch();

    $statement->close();

    if ($found !== true) {
        return null;
    }

    return [
        "userId" => (int) $dbUserId,
        "fullName" => $dbFullName,
        "email" => $dbEmail,
        "passwordHash" => $dbPasswordHash,
        "createdAt" => $dbCreatedAt
    ];
}


function getUserByEmail(
    mysqli $connection,
    string $email
): ?array {

    $sql = "
        SELECT
            user_id,
            full_name,
            email,
            password_hash,
            created_at
        FROM users
        WHERE email = ?
    ";

    $statement = $connection->prepare($sql);
    $statement->bind_param("s", $email);
    $statement->execute();

    $statement->bind_result(
        $dbUserId,
        $dbFullName,
        $dbEmail,
        $dbPasswordHash,
        $dbCreatedAt
    );

    $found = $statement->fetch();

    $statement->close();

    if ($found !== true) {
        return null;
    }

    return [
        "userId" => (int) $dbUserId,
        "fullName" => $dbFullName,
        "email" => $dbEmail,
        "passwordHash" => $dbPasswordHash,
        "createdAt" => $dbCreatedAt
    ];
}


function createUser(
    mysqli $connection,
    string $fullName,
    string $email,
    string $passwordHash
): int {

    $sql = "
        INSERT INTO users (
            full_name,
            email,
            password_hash
        )
        VALUES (?, ?, ?)
    ";

    $statement = $connection->prepare($sql);

    $statement->bind_param(
        "sss",
        $fullName,
        $email,
        $passwordHash
    );

    $statement->execute();

    $userId = $statement->insert_id;

    $statement->close();

    return $userId;
}


function updateUser(
    mysqli $connection,
    int $userId,
    string $fullName,
    string $email
): void {

    $sql = "
        UPDATE users
        SET
            full_name = ?,
            email = ?
        WHERE user_id = ?
    ";

    $statement = $connection->prepare($sql);

    $statement->bind_param(
        "ssi",
        $fullName,
        $email,
        $userId
    );

    $statement->execute();
    $statement->close();
}

//specifically to update the user's password without changing anything else
function updateUserPassword(
    mysqli $connection,
    int $userId,
    string $passwordHash
): void {

    $sql = "
        UPDATE users
        SET password_hash = ?
        WHERE user_id = ?
    ";

    $statement = $connection->prepare($sql);

    $statement->bind_param(
        "si",
        $passwordHash,
        $userId
    );

    $statement->execute();
    $statement->close();
}

//use this function when you want to use user data in kotlin but wihtout using the password
function sanitizeUserForResponse(
    array $user
): array {

    return [
        "userId" => $user["userId"],
        "fullName" => $user["fullName"],
        "email" => $user["email"],
        "createdAt" => $user["createdAt"]
    ];
}