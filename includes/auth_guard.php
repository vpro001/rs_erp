<?php
// includes/auth_guard.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

// Each portal has its own login page; send logged-out visitors to the one for the area they asked for.
function portal_login_url($uri)
{
    foreach (['admin', 'staff', 'client'] as $portal) {
        if (strpos($uri, "/$portal/") !== false) {
            return BASE_URL . "/$portal/login.php";
        }
    }
    return BASE_URL . "/login.php";
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    }
    header("Location: " . portal_login_url($_SERVER['REQUEST_URI']));
    exit();
}

$user = $_SESSION['user'];

// Session timeout check
$timeout_minutes = 30; // Default
if (isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT session_timeout FROM user_settings WHERE user_id = ?");
        $stmt->bind_param("s", $user['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $timeout_minutes = intval($row['session_timeout']);
        }
        $stmt->close();
    } catch (Exception $e) {
        // Fallback
    }
}

$timeout_seconds = $timeout_minutes * 60;
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout_seconds)) {
    // Session expired
    unset($_SESSION['user']);
    unset($_SESSION['last_active']);
    session_destroy();
    session_start();
    set_flash_message('warning', 'Your session has expired. Please log in again.');
    header("Location: " . portal_login_url($_SERVER['REQUEST_URI']));
    exit();
}

// Update last active in session and database
$_SESSION['last_active'] = time();
$user_table = user_table_for_role($user['role'] ?? '');
if (isset($conn) && $user_table) {
    try {
        $stmt = $conn->prepare("UPDATE `$user_table` SET last_active = NOW() WHERE id = ?");
        $stmt->bind_param("s", $user['id']);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Ignore
    }
}

// Check role permissions based on current URL path
$current_uri = $_SERVER['REQUEST_URI'];

if (!check_portal_access($user['role'], $current_uri)) {
    set_flash_message('error', 'Access Denied: You do not have permission to view that resource.');
    
    // Redirect to their respective dashboard
    switch ($user['role']) {
        case 'ADMIN':
        case 'MANAGER':
            header("Location: " . BASE_URL . "/admin/index.php");
            break;
        case 'STAFF':
            header("Location: " . BASE_URL . "/staff/index.php");
            break;
        case 'STUDENT':
            header("Location: " . BASE_URL . "/student/index.php");
            break;
        case 'CLIENT':
            header("Location: " . BASE_URL . "/client/index.php");
            break;
        default:
            header("Location: " . BASE_URL . "/login.php");
            break;
    }
    exit();
}
