<?php
// api/like_post.php
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
$student_id = sanitize($input['student_id'] ?? '');

if (empty($post_id) || empty($student_id)) {
    echo json_encode(["success" => false, "error" => "Required parameters missing."]);
    exit();
}

try {
    // Check if like exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM forum_post_likes WHERE post_id = ? AND student_id = ?");
    $stmt->bind_param("ss", $post_id, $student_id);
    $stmt->execute();
    $liked = $stmt->get_result()->fetch_assoc()['count'] > 0;
    $stmt->close();
    
    if ($liked) {
        // Unlike post
        $stmt = $conn->prepare("DELETE FROM forum_post_likes WHERE post_id = ? AND student_id = ?");
        $stmt->bind_param("ss", $post_id, $student_id);
        $stmt->execute();
        $stmt->close();
        $is_liked = false;
    } else {
        // Like post
        $like_id = generate_uuid();
        $stmt = $conn->prepare("INSERT INTO forum_post_likes (id, post_id, student_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $like_id, $post_id, $student_id);
        $stmt->execute();
        $stmt->close();
        $is_liked = true;
    }
    
    // Get updated total count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM forum_post_likes WHERE post_id = ?");
    $stmt->bind_param("s", $post_id);
    $stmt->execute();
    $likes_count = intval($stmt->get_result()->fetch_assoc()['count']);
    $stmt->close();
    
    echo json_encode([
        "success" => true,
        "liked" => $is_liked,
        "likes_count" => $likes_count
    ]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Execution failed: " . $e->getMessage()]);
}
