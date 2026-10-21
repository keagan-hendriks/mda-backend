<?php
// No database needed. Run from backend root: C:\xampp\php\php.exe tests\Test-Contract.php
require_once __DIR__ . '/../includes/crud-handler.php';
$configs = require __DIR__ . '/../includes/crud-resources.php';

$cases = [
    'badges' => ['badgeName' => 'First Dose', 'description' => 'Record a dose', 'requirementDescription' => 'One dose'],
    'medications' => ['userId' => 128, 'medicationName' => 'Aspirin', 'isActive' => true],
    'reminders' => ['medicationId' => 3, 'reminderTime' => '08:30:00', 'isEnabled' => false],
    'medication_logs' => ['medicationId' => 3, 'scheduledAt' => '2026-10-19 12:00:00', 'status' => 'taken', 'reminderId' => null],
    'user_badges' => ['userId' => 128, 'badgeId' => 3],
    'user_rewards' => ['userId' => 128, 'totalPoints' => 0, 'longestStreak' => 2],
];

foreach ($cases as $resource => $input) {
    $fields = crudFields($configs[$resource], $input, true);
    if (count($fields) !== count($input)) throw new RuntimeException("$resource: field count mismatch");
    $names = array_keys($configs[$resource]['fields']);
    foreach ($configs[$resource]['keys'] as $key) {
        if (!in_array($key, $names, true)) throw new RuntimeException("$resource: missing key config $key");
    }
    foreach ($configs[$resource]['fields'] as $name => $field) {
        if (!preg_match('/^[a-z_]+$/', $field['column'])) throw new RuntimeException("$resource invalid SQL identifier");
    }
    echo "[PASS] $resource creation field contract\n";
}

$invalidCases = [
    ['reminders', ['medicationId' => 3, 'reminderTime' => '32:12:00']],
    ['medication_logs', ['medicationId' => 3, 'scheduledAt' => '2026-02-30 09:00:00', 'status' => 'taken']],
    ['medication_logs', ['medicationId' => 3, 'scheduledAt' => '2026-10-19 09:00:00', 'status' => 'unknown']],
    ['medications', ['userId' => 128, 'medicationName' => 'x', 'isActive' => 'true']],
    ['user_rewards', ['userId' => 128, 'totalPoints' => -2]],
    ['badges', ['badgeName' => 'test']],
    ['badges', ['badgeId' => 44, 'badgeName' => 'test', 'description' => 'test', 'requirementDescription' => 'test']],
];
foreach ($invalidCases as [$resource, $fields]) {
    try {
        crudFields($configs[$resource], $fields, true);
        throw new RuntimeException("$resource incorrectly accepted invalid input");
    } catch (CrudValidationException $e) {
        echo "[PASS] $resource rejects invalid input\n";
    }
}

echo "All contract tests passed. This does not test the live database.\n";
