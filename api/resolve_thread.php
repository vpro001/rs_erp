<?php
// api/resolve_thread.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
if (!$user) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$post_id = sanitize($input['post_id'] ?? '');
$reply_id = sanitize($input['reply_id'] ?? '');

if (empty($post_id) || empty($reply_id)) {
    echo json_encode(["success" => false, "error" => "Required parameters missing."]);
    exit();
}

try {
    // 1. Verify that either:
    //    - The user is the author of the post (student_id matches user's student_id)
    //    - The user is STAFF or ADMIN
    $is_authorized = false;
    
    // Check if staff/admin
    if (in_array($user['role'], ['STAFF', 'ADMIN', 'MANAGER'])) {
        $is_authorized = true;
    } else {
        // Fetch student_id of the logged in user
        $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt->bind_param("s", $user['id']);
        $stmt->execute();
        $student_res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($student_res) {
            $student_id = $student_res['id'];
            
            // Check if post belongs to this student
            $stmt = $conn->prepare("SELECT student_id FROM forum_posts WHERE id = ?");
            $stmt->bind_param("s", $post_id);
            $stmt->execute();
            $post_res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($post_res && $post_res['student_id'] === $student_id) {
                $is_authorized = true;
            }
        }
    }
    
    if (!$is_authorized) {
        echo json_encode(["success" => false, "error" => "You are not authorized to mark this thread as resolved."]);
        exit();
    }
    
    // 2. Check current state of the reply
    $stmt = $conn->prepare("SELECT is_solution, student_id FROM forum_replies WHERE id = ? AND post_id = ?");
    $stmt->bind_param("ss", $reply_id, $post_id);
    $stmt->execute();
    $reply_res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$reply_res) {
        echo json_encode(["success" => false, "error" => "Reply not found or does not belong to this thread."]);
        exit();
    }
    
    $currently_solution = (int)$reply_res['is_solution'] === 1;
    $reply_student_id = $reply_res['student_id'];
    
    $conn->begin_transaction();
    
    if ($currently_solution) {
        // Unmark this reply
        $stmt = $conn->prepare("UPDATE forum_replies SET is_solution = 0 WHERE id = ?");
        $stmt->bind_param("s", $reply_id);
        $stmt->execute();
        $stmt->close();
        
        // Mark post as unresolved
        $stmt = $conn->prepare("UPDATE forum_posts SET is_resolved = 0 WHERE id = ?");
        $stmt->bind_param("s", $post_id);
        $stmt->execute();
        $stmt->close();
        
        $action = "unresolved";
    } else {
        // Clear any other solution for this post
        $stmt = $conn->prepare("UPDATE forum_replies SET is_solution = 0 WHERE post_id = ?");
        $stmt->bind_param("s", $post_id);
        $stmt->execute();
        $stmt->close();
        
        // Mark selected reply as solution
        $stmt = $conn->prepare("UPDATE forum_replies SET is_solution = 1 WHERE id = ?");
        $stmt->bind_param("s", $reply_id);
        $stmt->execute();
        $stmt->close();
        
        // Mark post as resolved
        $stmt = $conn->prepare("UPDATE forum_posts SET is_resolved = 1 WHERE id = ?");
        $stmt->bind_param("s", $post_id);
        $stmt->execute();
        $stmt->close();
        
        $action = "resolved";
        
        // Send a notification to the author of the reply
        // First find user_id of the reply creator
        $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->bind_param("s", $reply_student_id);
        $stmt->execute();
        $reply_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($reply_user && $reply_user['user_id'] !== $user['id']) {
            send_notification(
                $reply_user['user_id'],
                "Answer marked as solution!",
                "Your reply on the thread has been selected as the correct solution.",
                "LMS",
                "MEDIUM",
                "/student/forum.php?post_id=" . urlencode($post_id)
            );
        }
    }
    
    $conn->commit();
    log_activity("Updated thread resolution state", ["post_id" => $post_id, "action" => $action]);
    
    echo json_encode(["success" => true, "action" => $action]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(["success" => false, "error" => "Execution failed: " . $e->getMessage()]);
}
