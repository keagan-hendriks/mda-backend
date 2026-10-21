<?php
// Allowed tables, columns and validations are defined here, never supplied by clients.
// Fields: type int (positive), nonNegativeInt, bool, string, nullableString,
//         datetime, time, enum, nullableInt.
return [
    'badges' => [
        'table' => 'badges',
        'keys' => ['badgeId'],
        'autoId' => 'badgeId',
        'fields' => [
            'badgeId' => ['column' => 'badge_id', 'type' => 'int', 'write' => false],
            'badgeName' => ['column' => 'badge_name', 'type' => 'string', 'max' => 100, 'required' => true],
            'description' => ['column' => 'description', 'type' => 'string', 'max' => 255, 'required' => true],
            'iconName' => ['column' => 'icon_name', 'type' => 'nullableString', 'max' => 100],
            'pointsReward' => ['column' => 'points_reward', 'type' => 'nonNegativeInt'],
            'requirementDescription' => ['column' => 'requirement_description', 'type' => 'string', 'max' => 255, 'required' => true],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime', 'write' => false],
        ],
        'list' => ['filter' => null, 'joins' => '', 'order' => 't.`badge_id` DESC'],
    ],
    'medications' => [
        'table' => 'medications',
        'keys' => ['medicationId'],
        'autoId' => 'medicationId',
        'fields' => [
            'medicationId' => ['column' => 'medication_id', 'type' => 'int', 'write' => false],
            'userId' => ['column' => 'user_id', 'type' => 'int', 'required' => true, 'update' => false],
            'medicationName' => ['column' => 'medication_name', 'type' => 'string', 'max' => 150, 'required' => true],
            'notes' => ['column' => 'notes', 'type' => 'nullableString', 'max' => 255],
            'isActive' => ['column' => 'is_active', 'type' => 'bool'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime', 'write' => false],
        ],
        'list' => ['filter' => 't.`user_id`', 'joins' => '', 'order' => 't.`medication_id` DESC'],
    ],
    'reminders' => [
        'table' => 'reminders',
        'keys' => ['reminderId'],
        'autoId' => 'reminderId',
        'fields' => [
            'reminderId' => ['column' => 'reminder_id', 'type' => 'int', 'write' => false],
            'medicationId' => ['column' => 'medication_id', 'type' => 'int', 'required' => true, 'update' => false],
            'reminderTime' => ['column' => 'reminder_time', 'type' => 'time', 'required' => true],
            'repeatDaily' => ['column' => 'repeat_daily', 'type' => 'bool'],
            'isEnabled' => ['column' => 'is_enabled', 'type' => 'bool'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime', 'write' => false],
        ],
        'list' => [
            'filter' => 'm.`user_id`',
            'joins' => 'INNER JOIN `medications` m ON m.`medication_id` = t.`medication_id`',
            'order' => 't.`reminder_id` DESC',
        ],
    ],
    'medication_logs' => [
        'table' => 'medication_logs',
        'keys' => ['logId'],
        'autoId' => 'logId',
        'fields' => [
            'logId' => ['column' => 'log_id', 'type' => 'int', 'write' => false],
            'medicationId' => ['column' => 'medication_id', 'type' => 'int', 'required' => true, 'update' => false],
            'reminderId' => ['column' => 'reminder_id', 'type' => 'nullableInt'],
            'scheduledAt' => ['column' => 'scheduled_at', 'type' => 'datetime', 'required' => true],
            'status' => ['column' => 'status', 'type' => 'enum', 'options' => ['taken', 'skipped', 'missed'], 'required' => true],
            'recordedAt' => ['column' => 'recorded_at', 'type' => 'datetime', 'write' => false],
            'notes' => ['column' => 'notes', 'type' => 'nullableString', 'max' => 255],
        ],
        'list' => [
            'filter' => 'm.`user_id`',
            'joins' => 'INNER JOIN `medications` m ON m.`medication_id` = t.`medication_id`',
            'order' => 't.`log_id` DESC',
        ],
    ],
    'user_badges' => [
        'table' => 'user_badges',
        'keys' => ['userId', 'badgeId'],
        'fields' => [
            'userId' => ['column' => 'user_id', 'type' => 'int', 'required' => true, 'update' => false],
            'badgeId' => ['column' => 'badge_id', 'type' => 'int', 'required' => true, 'update' => false],
            'earnedAt' => ['column' => 'earned_at', 'type' => 'datetime'],
        ],
        'list' => ['filter' => 't.`user_id`', 'joins' => '', 'order' => 't.`earned_at` DESC'],
    ],
    'user_rewards' => [
        'table' => 'user_rewards',
        'keys' => ['userId'],
        'fields' => [
            'userId' => ['column' => 'user_id', 'type' => 'int', 'required' => true, 'update' => false],
            'totalPoints' => ['column' => 'total_points', 'type' => 'nonNegativeInt'],
            'currentStreak' => ['column' => 'current_streak', 'type' => 'nonNegativeInt'],
            'longestStreak' => ['column' => 'longest_streak', 'type' => 'nonNegativeInt'],
            'updatedAt' => ['column' => 'updated_at', 'type' => 'datetime', 'write' => false],
        ],
        'list' => ['filter' => 't.`user_id`', 'joins' => '', 'order' => 't.`user_id` ASC'],
    ],
];
