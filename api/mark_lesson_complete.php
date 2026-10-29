<?php
// api/mark_lesson_complete.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

// Verify session login
$user = current_user();
if (!$user) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$lesson_id = sanitize($input['lesson_id'] ?? '');
$student_id = sanitize($input['student_id'] ?? '');
$course_id = sanitize($input['course_id'] ?? '');

if (empty($lesson_id) || empty($student_id) || empty($course_id)) {
    echo json_encode(["success" => false, "error" => "Required parameters missing."]);
    exit();
}

$conn->begin_transaction();
try {
    // 1. Insert completion record
    $completion_id = generate_uuid();
    $stmt = $conn->prepare("INSERT IGNORE INTO lesson_completions (id, student_id, lesson_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $completion_id, $student_id, $lesson_id);
    $stmt->execute();
    $stmt->close();
    
    // 2. Count total lessons in course
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM lessons l JOIN modules m ON l.module_id = m.id WHERE m.course_id = ? AND m.is_active = 1");
    $stmt->bind_param("s", $course_id);
    $stmt->execute();
    $total_lessons = intval($stmt->get_result()->fetch_assoc()['total']);
    $stmt->close();
    
    // 3. Count completed lessons for this student in this course
    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM lesson_completions lc 
                            JOIN lessons l ON lc.lesson_id = l.id 
                            JOIN modules m ON l.module_id = m.id 
                            WHERE lc.student_id = ? AND m.course_id = ? AND m.is_active = 1");
    $stmt->bind_param("ss", $student_id, $course_id);
    $stmt->execute();
    $completed_lessons = intval($stmt->get_result()->fetch_assoc()['completed']);
    $stmt->close();
    
    // 4. Calculate progress percentage
    $progress = 0;
    if ($total_lessons > 0) {
        $progress = intval(round(($completed_lessons / $total_lessons) * 100));
    }
    
    // 5. Update enrollments table
    $stmt = $conn->prepare("UPDATE enrollments SET progress = ? WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("iss", $progress, $student_id, $course_id);
    $stmt->execute();
    $stmt->close();
    
    // 6. Check if 100% completed and award certificate
    if ($progress === 100) {
        // Look up if certificate already awarded
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE student_id = ? AND course_id = ?");
        $stmt->bind_param("ss", $student_id, $course_id);
        $stmt->execute();
        $cert_awarded = $stmt->get_result()->fetch_assoc()['count'] > 0;
        $stmt->close();
        
        if (!$cert_awarded) {
            $cert_id = generate_uuid();
            $cert_serial = 'CERT-' . strtoupper(substr(md5(uniqid()), 0, 10));
            $qr_code = 'QR-' . strtoupper(substr(md5(uniqid()), 0, 10));
            $digital_sig = hash('sha256', $student_id . $course_id . 'ROYAL_SIGNATURE');
            
            $stmt = $conn->prepare("INSERT INTO certificates (id, student_id, course_id, certificate_id, qr_code, digital_signature) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $cert_id, $student_id, $course_id, $cert_serial, $qr_code, $digital_sig);
            $stmt->execute();
            $stmt->close();
            
            // Send notification
            send_notification($user['id'], "Course Completed!", "Congratulations! You have completed the course and earned a certificate.", "LMS", "HIGH", "/student/results.php");
        }
    }
    
    $conn->commit();
    echo json_encode(["success" => true, "progress" => $progress]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Execution failed: " . $e->getMessage()]);
}
