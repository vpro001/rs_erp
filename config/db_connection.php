<?php
// config/db_connection.php

// Enable error reporting for development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Load base configuration
$config = require __DIR__ . '/app_config.php';

// Check for local overrides - manually merge database config to avoid nested arrays
// from array_merge_recursive. We only override the database section.
$local_config_path = __DIR__ . '/app_config.local.php';
if (file_exists($local_config_path)) {
    $local_config = require $local_config_path;
    // Only merge database section, overriding values instead of creating nested arrays
    if (isset($local_config['database'])) {
        $config['database'] = array_merge($config['database'], $local_config['database']);
    }
    // For other sections, we can skip or use a simple approach
    // that doesn't create nested arrays
}

$db = $config['database'];

try {
    // Initial connection to MySQL server
    $conn = @new mysqli($db['host'], $db['user'], $db['pass']);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET {$db['charset']} COLLATE {$db['collation']}");

    // Select the database
    $conn->select_db($db['name']);

    // Set charset
    $conn->set_charset($db['charset']);

    // Auto-initialize schema and seed default data if not already present
    require_once __DIR__ . '/db_init.php';
    init_database($conn);

} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

/**
 * Execute a prepared query securely.
 *
 * @param string $query The SQL query with placeholders
 * @param string $types Type definition characters (s, i, d, b)
 * @param array $params Array of values to bind
 * @return mysqli_result|bool Result set for SELECT, true/false for write operations
 */
function db_query($query, $types = "", $params = [])
{
    global $conn;

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("SQL Preparation failed: " . $conn->error);
    }

    if (!empty($params) && !empty($types)) {
        // Prepare references for bind_param
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    // Check if it's a select query
    $result = $stmt->get_result();

    // If it's an insert or update query, we return true on success or insertion ID
    if ($result === false) {
        $affected = $stmt->affected_rows;
        $insert_id = $stmt->insert_id;
        $stmt->close();
        if ($insert_id > 0) {
            return $insert_id;
        }
        return $affected >= 0;
    }

    $stmt->close();
    return $result;
}