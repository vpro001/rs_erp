<?php
// logout.php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db_connection.php';

$user = current_user();
$login_path = match ($user['role'] ?? '') {
    'ADMIN', 'MANAGER' => '/admin/login.php',
    'STAFF' => '/staff/login.php',
    'CLIENT' => '/client/login.php',
    'MARKETER' => '/marketer/login.php',
    default => '/login.php',
};
if ($user) {
    try {
        log_activity("User Logged Out", ["email" => $user['email']]);
    } catch (Exception $e) {
        // Ignore
    }
}

// Destroy session variables and cookies
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Restart session to set flash message
session_start();
set_flash_message('success', 'You have successfully signed out.');
header("Location: " . BASE_URL . $login_path);
exit();
