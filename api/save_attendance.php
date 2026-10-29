<?php
// api/save_attendance.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['STAFF', 'ADMIN', 'MANAGER'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$student_id = sanitize($input['student_id'] ?? '');
$date = sanitize($input['date'] ?? '');
$status = sanitize($input['status'] ?? '');

if (empty($student_id) || empty($date) || empty($status)) {
    echo json_encode(["success" => false, "error" => "Required parameters missing."]);
    exit();
}

if (!in_array($status, ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED'])) {
    echo json_encode(["success" => false, "error" => "Invalid attendance status values."]);
    exit();
}

try {
    $attendance_id = generate_uuid();
    $stmt = $conn->prepare("INSERT INTO attendance (id, student_id, date, status) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()");
    $stmt->bind_param("ssss", $attendance_id, $student_id, $date, $status);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(["success" => true]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Execution failed: " . $e->getMessage()]);
}
