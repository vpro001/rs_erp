<?php
// api/notifications_stream.php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering on Nginx/Apache if enabled

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

// Turn off session locks quickly to prevent locking other pages
session_write_close();

$user = current_user();
if (!$user) {
    echo "data: " . json_encode(["error" => "Unauthorized"]) . "\n\n";
    exit();
}

$user_id = $user['id'];
$last_checked = date('Y-m-d H:i:s');

// Run loop for about 45 seconds to stay within standard PHP timeouts
$limit = 22; 
for ($i = 0; $i < $limit; $i++) {
    if (connection_aborted()) {
        break;
    }
    
    try {
        // Query any unread notification created after $last_checked
        $stmt = $conn->prepare("SELECT id, title, message, type, priority, link, created_at 
                                FROM notifications 
                                WHERE user_id = ? AND is_read = 0 AND archived = 0 AND created_at > ?
                                ORDER BY created_at ASC");
        $stmt->bind_param("ss", $user_id, $last_checked);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $new_notifications = [];
        while ($row = $res->fetch_assoc()) {
            $new_notifications[] = $row;
            // Update last checked to the latest notification's timestamp
            $last_checked = $row['created_at'];
        }
        $stmt->close();
        
        if (!empty($new_notifications)) {
            foreach ($new_notifications as $notif) {
                echo "data: " . json_encode($notif) . "\n\n";
            }
            ob_flush();
            flush();
        }
        
    } catch (Exception $e) {
        // Log or exit
        echo "data: " . json_encode(["error" => $e->getMessage()]) . "\n\n";
        ob_flush();
        flush();
        break;
    }
    
    sleep(2);
}
