<?php
// student/admission_letter.php
// Printable admission letter. Students may only open their own; admins/managers may open any.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/admission_letter.php';

$user = current_user();
$enrollment_id = sanitize($_GET['enrollment_id'] ?? '');
$letter = admission_letter_data($conn, $enrollment_id);

if (!$letter || (!has_role(['ADMIN', 'MANAGER']) && $letter['user_id'] !== $user['id'])) {
    set_flash_message('error', 'Admission letter not found.');
    header("Location: " . BASE_URL . "/student/my_courses.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Letter - <?php echo letter_e($letter['first_name'] . ' ' . $letter['last_name']); ?></title>
    <style>
        body { margin: 0; background: #f1f5f9; }
        .toolbar { max-width: 640px; margin: 16px auto 0; text-align: right; font-family: Arial, sans-serif; }
        .toolbar button { background: #17264A; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
    <?php echo admission_letter_html($letter); ?>
</body>
</html>
