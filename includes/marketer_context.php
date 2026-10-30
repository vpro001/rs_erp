<?php
// includes/marketer_context.php
// Resolves the signed-in marketer's record (referral code, commission rates) for every marketer page.
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user = current_user();

$stmt = $conn->prepare("SELECT * FROM users_marketer WHERE id = ?");
$stmt->bind_param("s", $user['id']);
$stmt->execute();
$marketer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$marketer) {
    header("Location: " . BASE_URL . "/logout.php");
    exit();
}

if (empty($marketer['referral_code'])) {
    $marketer['referral_code'] = generate_referral_code($conn);
    $stmt = $conn->prepare("UPDATE users_marketer SET referral_code = ? WHERE id = ?");
    $stmt->bind_param("ss", $marketer['referral_code'], $marketer['id']);
    $stmt->execute();
    $stmt->close();
}

$marketer_id = $marketer['id'];

function csrf_ok()
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
