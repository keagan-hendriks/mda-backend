<?php

function getPreferencesForUser(
    mysqli $connection,
    int    $userId
): ?array
{

    $sql = "
        SELECT
            user_id,
            notifications_enabled,
            sound_enabled,
            trusted_contact_sharing
        FROM user_preferences
        WHERE user_id = ?
    ";

    $statement = $connection->prepare($sql);

    $statement->bind_param("i", $userId);
    $statement->execute();

    $statement->bind_result(
        $dbUserId,
        $dbNotifications,
        $dbSound,
        $dbSharing
    );

    $found = $statement->fetch();

    $statement->close();

    if ($found !== true) {
        return null;
    }

    return [
        "userId" => (int)$dbUserId,
        "notificationsEnabled" => (bool)$dbNotifications,
        "soundEnabled" => (bool)$dbSound,
        "trustedContactSharing" => (bool)$dbSharing
    ];
}

function updatePreferencesForUser(
    mysqli $connection,
    int    $userId,
    bool   $notificationsEnabled,
    bool   $soundEnabled,
    bool   $trustedContactSharing
): void
{

    $sql = "
        UPDATE user_preferences
        SET
            notifications_enabled = ?,
            sound_enabled = ?,
            trusted_contact_sharing = ?
        WHERE user_id = ?
    ";

    $notifications = (int)$notificationsEnabled;
    $sound = (int)$soundEnabled;
    $sharing = (int)$trustedContactSharing;

    $statement = $connection->prepare($sql);

    $statement->bind_param(
        "iiii",
        $notifications,
        $sound,
        $sharing,
        $userId
    );

    $statement->execute();
    $statement->close();
}
