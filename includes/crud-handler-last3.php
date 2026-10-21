<?php

function readJsonBody(): array {
    $raw = file_get_contents("php://input");

    if ($raw === false || trim($raw) === "") {
        sendJsonResponse(false, "Request body is required", null, 400);
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        sendJsonResponse(false, "Invalid JSON request body", null, 400);
    }

    if (!is_array($data)) {
        sendJsonResponse(false, "Request body must be a JSON object", null, 400);
    }

    return $data;
}

function validateValue(string $name, $value, string $type): void {
    $ok = match ($type) {
        "int" => is_int($value),
        "float" => is_int($value) || is_float($value),
        "string" => is_string($value) && trim($value) !== "",
        "nullable_string" => $value === null || is_string($value),
        default => false
    };

    if (!$ok) {
        sendJsonResponse(false, "Invalid value for $name", null, 400);
    }
}

function mapDbRow(array $row, array $responseMap): array {
    $out = [];

    foreach ($responseMap as $dbColumn => $jsonField) {
        if (array_key_exists($dbColumn, $row)) {
            $out[$jsonField] = $row[$dbColumn];
        }
    }

    return $out;
}

function fetchRowById(mysqli $connection, array $resource, int $id): ?array {
    $table = $resource["table"];
    $pk = $resource["primaryKey"];

    $sql = "SELECT * FROM `$table` WHERE `$pk` = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

function fetchRows(mysqli $connection, array $resource, ?int $userId = null): array {
    $table = $resource["table"];

    if ($userId !== null && isset($resource["responseMap"]["user_id"])) {
        $sql = "SELECT * FROM `$table` WHERE `user_id` = ? ORDER BY 1";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $userId);
    } else {
        $sql = "SELECT * FROM `$table` ORDER BY 1";
        $stmt = $connection->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = mapDbRow($row, $resource["responseMap"]);
    }

    $stmt->close();

    return $rows;
}

function createResource(mysqli $connection, array $resource, array $data): array {
    $columns = [];
    $placeholders = [];
    $values = [];
    $types = "";

    foreach ($resource["fields"] as $jsonField => $spec) {
        $required = $spec["required"] ?? false;

        if (!array_key_exists($jsonField, $data)) {
            if ($required) {
                sendJsonResponse(false, "$jsonField is required", null, 400);
            }
            continue;
        }

        validateValue($jsonField, $data[$jsonField], $spec["type"]);

        $columns[] = "`" . $spec["column"] . "`";
        $placeholders[] = "?";
        $values[] = $data[$jsonField];

        $types .= match ($spec["type"]) {
            "int" => "i",
            "float" => "d",
            default => "s"
        };
    }

    $table = $resource["table"];
    $sql = "INSERT INTO `$table` (" . implode(", ", $columns) . ")
            VALUES (" . implode(", ", $placeholders) . ")";

    $stmt = $connection->prepare($sql);

    $bindValues = [];
    foreach ($values as $i => $value) {
        $bindValues[$i] = $value;
    }

    $refs = [];
    foreach ($bindValues as $i => &$value) {
        $refs[$i] = &$value;
    }

    $stmt->bind_param($types, ...$refs);
    $stmt->execute();

    $id = $stmt->insert_id;
    $stmt->close();

    $row = fetchRowById($connection, $resource, $id);

    if ($row === null) {
        throw new RuntimeException("Created record could not be retrieved");
    }

    return mapDbRow($row, $resource["responseMap"]);
}

function updateResource(mysqli $connection, array $resource, int $id, array $data): array {
    $assignments = [];
    $values = [];
    $types = "";

    foreach ($resource["fields"] as $jsonField => $spec) {
        if (!array_key_exists($jsonField, $data)) {
            continue;
        }

        validateValue($jsonField, $data[$jsonField], $spec["type"]);

        $assignments[] = "`" . $spec["column"] . "` = ?";
        $values[] = $data[$jsonField];

        $types .= match ($spec["type"]) {
            "int" => "i",
            "float" => "d",
            default => "s"
        };
    }

    if (count($assignments) === 0) {
        sendJsonResponse(false, "No fields supplied for update", null, 400);
    }

    $existing = fetchRowById($connection, $resource, $id);
    if ($existing === null) {
        sendJsonResponse(false, "Record not found", null, 404);
    }

    $table = $resource["table"];
    $pk = $resource["primaryKey"];

    $sql = "UPDATE `$table`
            SET " . implode(", ", $assignments) . "
            WHERE `$pk` = ?";

    $values[] = $id;
    $types .= "i";

    $stmt = $connection->prepare($sql);

    $bindValues = [];
    foreach ($values as $i => $value) {
        $bindValues[$i] = $value;
    }

    $refs = [];
    foreach ($bindValues as $i => &$value) {
        $refs[$i] = &$value;
    }

    $stmt->bind_param($types, ...$refs);
    $stmt->execute();
    $stmt->close();

    $row = fetchRowById($connection, $resource, $id);

    return mapDbRow($row, $resource["responseMap"]);
}

function deleteResource(mysqli $connection, array $resource, int $id): void {
    if (fetchRowById($connection, $resource, $id) === null) {
        sendJsonResponse(false, "Record not found", null, 404);
    }

    $table = $resource["table"];
    $pk = $resource["primaryKey"];

    $sql = "DELETE FROM `$table` WHERE `$pk` = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}
