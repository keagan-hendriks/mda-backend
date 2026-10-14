
<?php

// Only allow this setup script to run from the command line.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Access denied. Run this script from the command line.");
}

// Connect to the existing medication_app database.
require_once __DIR__ . '/../db_connect.php';

// Create tables in dependency order so foreign keys work correctly.
$tables = [

    // 1. Users
    'users' => "
        CREATE TABLE IF NOT EXISTS `users` (
            `user_id` INT(11) NOT NULL AUTO_INCREMENT,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 2. Medications
    'medications' => "
        CREATE TABLE IF NOT EXISTS `medications` (
            `medication_id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `medication_name` VARCHAR(150) NOT NULL,
            `notes` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`medication_id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `fk_medications_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 3. Reminders
    'reminders' => "
        CREATE TABLE IF NOT EXISTS `reminders` (
            `reminder_id` INT(11) NOT NULL AUTO_INCREMENT,
            `medication_id` INT(11) NOT NULL,
            `reminder_time` TIME NOT NULL,
            `repeat_daily` TINYINT(1) NOT NULL DEFAULT 1,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`reminder_id`),
            KEY `medication_id` (`medication_id`),
            CONSTRAINT `fk_reminders_medication`
                FOREIGN KEY (`medication_id`)
                REFERENCES `medications` (`medication_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 4. Medication logs
    'medication_logs' => "
        CREATE TABLE IF NOT EXISTS `medication_logs` (
            `log_id` INT(11) NOT NULL AUTO_INCREMENT,
            `medication_id` INT(11) NOT NULL,
            `reminder_id` INT(11) DEFAULT NULL,
            `scheduled_at` DATETIME NOT NULL,
            `status` ENUM('taken', 'skipped', 'missed') NOT NULL,
            `recorded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `notes` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`log_id`),
            KEY `medication_id` (`medication_id`),
            KEY `reminder_id` (`reminder_id`),
            CONSTRAINT `fk_logs_medication`
                FOREIGN KEY (`medication_id`)
                REFERENCES `medications` (`medication_id`)
                ON DELETE CASCADE,
            CONSTRAINT `fk_logs_reminder`
                FOREIGN KEY (`reminder_id`)
                REFERENCES `reminders` (`reminder_id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 5. User rewards
    'user_rewards' => "
        CREATE TABLE IF NOT EXISTS `user_rewards` (
            `user_id` INT(11) NOT NULL,
            `total_points` INT(11) NOT NULL DEFAULT 0,
            `current_streak` INT(11) NOT NULL DEFAULT 0,
            `longest_streak` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`),
            CONSTRAINT `fk_rewards_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 6. Badges
    'badges' => "
        CREATE TABLE IF NOT EXISTS `badges` (
            `badge_id` INT(11) NOT NULL AUTO_INCREMENT,
            `badge_name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `icon_name` VARCHAR(100) DEFAULT NULL,
            `points_reward` INT(11) NOT NULL DEFAULT 0,
            `requirement_description` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`badge_id`),
            UNIQUE KEY `badge_name` (`badge_name`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 7. User badges
    'user_badges' => "
        CREATE TABLE IF NOT EXISTS `user_badges` (
            `user_id` INT(11) NOT NULL,
            `badge_id` INT(11) NOT NULL,
            `earned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`, `badge_id`),
            KEY `badge_id` (`badge_id`),
            CONSTRAINT `fk_user_badges_badge`
                FOREIGN KEY (`badge_id`)
                REFERENCES `badges` (`badge_id`)
                ON DELETE CASCADE,
            CONSTRAINT `fk_user_badges_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 8. User preferences
    'user_preferences' => "
        CREATE TABLE IF NOT EXISTS `user_preferences` (
            `user_id` INT(11) NOT NULL,
            `notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `trusted_contact_sharing` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`),
            CONSTRAINT `fk_preferences_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 9. Trusted contacts
    'trusted_contacts' => "
        CREATE TABLE IF NOT EXISTS `trusted_contacts` (
            `contact_id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `contact_name` VARCHAR(100) NOT NULL,
            `contact_email` VARCHAR(255) NOT NULL,
            `relationship` VARCHAR(50) DEFAULT NULL,
            `invitation_status` ENUM(
                'pending', 'accepted', 'declined'
            ) NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`contact_id`),
            UNIQUE KEY `uq_user_trusted_email`
                (`user_id`, `contact_email`),
            CONSTRAINT `fk_trusted_contacts_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 10. Adherence reports
    'adherence_reports' => "
        CREATE TABLE IF NOT EXISTS `adherence_reports` (
            `report_id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `period_start` DATE NOT NULL,
            `period_end` DATE NOT NULL,
            `total_scheduled` INT(11) NOT NULL DEFAULT 0,
            `total_taken` INT(11) NOT NULL DEFAULT 0,
            `total_skipped` INT(11) NOT NULL DEFAULT 0,
            `total_missed` INT(11) NOT NULL DEFAULT 0,
            `adherence_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`report_id`),
            KEY `fk_reports_user` (`user_id`),
            CONSTRAINT `fk_reports_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ",

    // 11. Authentication tokens
    'auth_tokens' => "
        CREATE TABLE IF NOT EXISTS `auth_tokens` (
            `token_id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`token_id`),
            UNIQUE KEY `token_hash` (`token_hash`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `fk_auth_tokens_user`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`user_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    "
];

// Execute each CREATE TABLE statement.
try {
    foreach ($tables as $tableName => $sql) {
        $connection->query($sql);
        echo "[READY] Table '{$tableName}' exists or was created."
            . PHP_EOL;
    }

    echo PHP_EOL . "Database setup completed successfully."
        . PHP_EOL;

} catch (Throwable $error) {
    error_log("Database setup failed: " . $error->getMessage());

    fwrite(
        STDERR,
        "Database setup failed. Check the PHP error log for details."
        . PHP_EOL
    );

    exit(1);
}

$connection->close();
