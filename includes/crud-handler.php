<?php
/** Shared CRUD HTTP handler. Only intended for local development until auth/ownership checks are integrated. */
require_once __DIR__ . '/api-response.php';

final class CrudValidationException extends RuntimeException {}

function crudError(string $message): never {
    throw new CrudValidationException($message);
}

function crudPositiveId($value, string $name): int {
    // Query strings contain numeric text; JSON must use actual integer values.
    if (is_int($value) && $value > 0) return $value;
    if (is_string($value) && ctype_digit($value) && (int) $value > 0) return (int) $value;
    crudError("$name must be a positive integer");
}

function crudParseField($value, string $name, array $field) {
    $type = $field['type'];
    if ($type === 'int') {
        if (!is_int($value) || $value <= 0) crudError("$name must be a positive integer");
        return $value;
    }
    if ($type === 'nullableInt') {
        if ($value === null) return null;
        if (!is_int($value) || $value <= 0) crudError("$name must be null or a positive integer");
        return $value;
    }
    if ($type === 'nonNegativeInt') {
        if (!is_int($value) || $value < 0) crudError("$name must be a non-negative integer");
        return $value;
    }
    if ($type === 'bool') {
        if (!is_bool($value)) crudError("$name must be true or false");
        return $value ? 1 : 0;
    }
    if ($type === 'string' || $type === 'nullableString') {
        if ($value === null && $type === 'nullableString') return null;
        if (!is_string($value)) crudError("$name must be a string" . ($type === 'nullableString' ? ' or null' : ''));
        $value = trim($value);
        if ($type === 'string' && $value === '') crudError("$name cannot be empty");
        if (strlen($value) > $field['max']) crudError("$name is too long (maximum {$field['max']} bytes)");
        return $value;
    }
    if ($type === 'enum') {
        if (!is_string($value) || !in_array($value, $field['options'], true)) {
            crudError("$name must be one of: " . implode(', ', $field['options']));
        }
        return $value;
    }
    if ($type === 'time') {
        if (!is_string($value) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D', $value)) {
            crudError("$name must use HH:MM:SS (24-hour format)");
        }
        return $value;
    }
    if ($type === 'datetime') {
        if (!is_string($value)) crudError("$name must use YYYY-MM-DD HH:MM:SS");
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $value) {
            crudError("$name must use a valid YYYY-MM-DD HH:MM:SS datetime");
        }
        return $value;
    }
    throw new LogicException('Unsupported field type');
}

function crudBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') crudError('A JSON request body is required');
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        crudError('Invalid JSON request body');
    }
    // Reject JSON arrays; JSON object required.
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) crudError('Request body must be a JSON object');
    return $data;
}

function crudFields(array $config, array $input, bool $create): array {
    $values = [];
    foreach ($input as $name => $value) {
        if (!array_key_exists($name, $config['fields'])) crudError("Unknown field: $name");
        $field = $config['fields'][$name];
        if (($field['write'] ?? true) === false) crudError("$name is read-only");
        if (!$create && ($field['update'] ?? true) === false) {
            // Keys will already have been extracted and removed by UPDATE.
            crudError("$name cannot be updated");
        }
        $values[$name] = crudParseField($value, $name, $field);
    }
    if ($create) {
        foreach ($config['fields'] as $name => $field) {
            if (($field['required'] ?? false) && !array_key_exists($name, $values)) {
                crudError("$name is required");
            }
        }
    } elseif ($values === []) {
        crudError('At least one field to update is required');
    }
    return $values;
}

function crudBind(mysqli_stmt $stmt, array &$values): void {
    if ($values === []) return;
    $types = '';
    $args = [];
    foreach ($values as $idx => &$value) {
        $types .= (is_int($value) ? 'i' : 's');
        $args[] = &$value;
    }
    unset($value);
    $stmt->bind_param($types, ...$args);
}

function crudRun(mysqli $db, string $sql, array $values = []): mysqli_stmt {
    $stmt = $db->prepare($sql);
    crudBind($stmt, $values);
    $stmt->execute();
    return $stmt;
}

function crudSelectList(mysqli_stmt $stmt, array $config): array {
    $result = $stmt->get_result(); // XAMPP's PHP/MySQLi uses mysqlnd.
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        foreach ($config['fields'] as $name => $field) {
            if ($row[$name] === null) continue;
            if ($field['type'] === 'bool') $row[$name] = (bool) $row[$name];
            if (in_array($field['type'], ['int', 'nullableInt', 'nonNegativeInt'], true)) {
                $row[$name] = (int) $row[$name];
            }
        }
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function crudColumns(array $config): string {
    $cols = [];
    foreach ($config['fields'] as $name => $field) {
        $cols[] = 't.`' . $field['column'] . '` AS `' . $name . '`';
    }
    return implode(', ', $cols);
}

function crudWhere(array $config, array $keys): array {
    $parts = [];
    $values = [];
    foreach ($config['keys'] as $name) {
        $parts[] = 't.`' . $config['fields'][$name]['column'] . '` = ?';
        $values[] = $keys[$name];
    }
    return [implode(' AND ', $parts), $values];
}

function crudLoad(mysqli $db, array $config, array $keys): ?array {
    [$where, $values] = crudWhere($config, $keys);
    $sql = 'SELECT ' . crudColumns($config) . ' FROM `' . $config['table'] . '` t WHERE ' . $where . ' LIMIT 1';
    $stmt = crudRun($db, $sql, $values);
    $rows = crudSelectList($stmt, $config);
    return $rows[0] ?? null;
}

function crudParseKeys(array $config, array $source, bool $requireAll = true): ?array {
    $keys = [];
    foreach ($config['keys'] as $name) {
        if (!array_key_exists($name, $source)) {
            if ($requireAll) crudError("$name is required");
            return null;
        }
        $keys[$name] = crudPositiveId($source[$name], $name);
    }
    return $keys;
}

function crudCreate(mysqli $db, array $config): void {
    $fields = crudFields($config, crudBody(), true);
    $columns = [];
    $params = [];
    foreach ($fields as $name => $value) {
        $columns[] = '`' . $config['fields'][$name]['column'] . '`';
        $params[] = $value;
    }
    $sql = 'INSERT INTO `' . $config['table'] . '` (' . implode(', ', $columns) . ') VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $stmt = crudRun($db, $sql, $params);
    $newId = $stmt->insert_id;
    $stmt->close();
    $keys = [];
    foreach ($config['keys'] as $name) {
        $keys[$name] = ($config['autoId'] ?? null) === $name ? $newId : $fields[$name];
    }
    sendJsonResponse(true, 'Created successfully', crudLoad($db, $config, $keys), 201);
}

function crudRead(mysqli $db, array $config): void {
    $forceList = isset($_GET['list']) && $_GET['list'] === '1';
    $keys = crudParseKeys($config, $_GET, false);
    if (!$forceList && $keys !== null) {
        $row = crudLoad($db, $config, $keys);
        if ($row === null) sendJsonResponse(false, 'Not found', null, 404);
        sendJsonResponse(true, 'Retrieved successfully', $row, 200);
    }
    // Never list user-owned resources without a userId filter, until auth is added.
    $list = $config['list'];
    $params = [];
    $where = '';
    if ($list['filter'] !== null) {
        if (!isset($_GET['userId'])) crudError('userId is required for list requests');
        $params[] = crudPositiveId($_GET['userId'], 'userId');
        $where = ' WHERE ' . $list['filter'] . ' = ?';
    }
    $limit = 100;
    $offset = 0;
    foreach (['limit', 'offset'] as $name) {
        if (isset($_GET[$name])) {
            if (!ctype_digit((string) $_GET[$name])) crudError("$name must be a non-negative integer");
            if ($name === 'limit') $limit = (int) $_GET[$name];
            else $offset = (int) $_GET[$name];
        }
    }
    if ($limit < 1 || $limit > 100) crudError('limit must be between 1 and 100');
    $params[] = $limit;
    $params[] = $offset;
    $sql = 'SELECT ' . crudColumns($config) . ' FROM `' . $config['table'] . '` t '
        . $list['joins'] . $where . ' ORDER BY ' . $list['order'] . ' LIMIT ? OFFSET ?';
    $stmt = crudRun($db, $sql, $params);
    sendJsonResponse(true, 'List retrieved successfully', crudSelectList($stmt, $config), 200);
}

function crudUpdate(mysqli $db, array $config): void {
    $body = crudBody();
    $keys = crudParseKeys($config, $body);
    foreach ($config['keys'] as $name) unset($body[$name]);
    $fields = crudFields($config, $body, false);
    if (crudLoad($db, $config, $keys) === null) sendJsonResponse(false, 'Not found', null, 404);
    $set = [];
    $params = [];
    foreach ($fields as $name => $value) {
        $set[] = '`' . $config['fields'][$name]['column'] . '` = ?';
        $params[] = $value;
    }
    [$where, $keyValues] = crudWhere($config, $keys);
    array_push($params, ...$keyValues);
    $sql = 'UPDATE `' . $config['table'] . '` t SET ' . implode(', ', $set) . ' WHERE ' . $where;
    $stmt = crudRun($db, $sql, $params);
    $stmt->close();
    sendJsonResponse(true, 'Updated successfully', crudLoad($db, $config, $keys), 200);
}

function crudDelete(mysqli $db, array $config): void {
    $keys = crudParseKeys($config, $_GET);
    if (crudLoad($db, $config, $keys) === null) sendJsonResponse(false, 'Not found', null, 404);
    [$where, $params] = crudWhere($config, $keys);
    $sql = 'DELETE t FROM `' . $config['table'] . '` t WHERE ' . $where;
    $stmt = crudRun($db, $sql, $params);
    $stmt->close();
    sendJsonResponse(true, 'Deleted successfully', null, 200);
}

function crudHandle(string $resource, string $operation): void {
    $methods = ['create' => 'POST', 'get' => 'GET', 'update' => 'PUT', 'delete' => 'DELETE'];
    if (!isset($methods[$operation])) throw new LogicException('Invalid operation');
    $method = $methods[$operation];
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        sendJsonResponse(false, "Only $method requests are allowed", null, 405);
    }
    try {
        $resources = require __DIR__ . '/crud-resources.php';
        if (!isset($resources[$resource])) throw new LogicException('Invalid resource');
        $config = $resources[$resource];
        // mysqli exceptions also cover invalid FKs / duplicate unique keys.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/../db_connect.php';
        if (!isset($connection) || !($connection instanceof mysqli)) {
            throw new RuntimeException('Database connection variable $connection is missing');
        }
        switch ($operation) {
            case 'create': crudCreate($connection, $config); break;
            case 'get': crudRead($connection, $config); break;
            case 'update': crudUpdate($connection, $config); break;
            case 'delete': crudDelete($connection, $config); break;
        }
    } catch (CrudValidationException $e) {
        sendJsonResponse(false, $e->getMessage(), null, 400);
    } catch (mysqli_sql_exception $e) {
        error_log("CRUD $resource/$operation SQL error: " . $e->getMessage());
        if ($e->getCode() === 1062) sendJsonResponse(false, 'Duplicate record', null, 409);
        if ($e->getCode() === 1452) sendJsonResponse(false, 'Referenced record does not exist', null, 422);
        if ($e->getCode() === 1451) sendJsonResponse(false, 'Cannot delete: referenced by another record', null, 409);
        sendJsonResponse(false, 'Database error', null, 500);
    } catch (Throwable $e) {
        error_log("CRUD $resource/$operation error: " . $e->getMessage());
        sendJsonResponse(false, 'Internal server error', null, 500);
    }
}
