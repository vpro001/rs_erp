<?php
// api/notifications_mark_read.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
if (!$user) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Execution failed: " . $e->getMessage()]);
}
