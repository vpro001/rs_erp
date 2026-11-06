<?php
// certificate.php?id=<certificates.id> - certificate PDF download. Students may download their own;
// anyone who can view student records may download any.
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/certificates.php';

$cert = certificate_data($conn, sanitize($_GET['id'] ?? ''));
if (!$cert || ($cert['user_id'] !== current_user()['id'] && !can('students.view'))) {
    set_flash_message('error', 'Certificate not found.');
    header("Location: " . (has_role('STUDENT') ? BASE_URL . '/student/results.php' : portal_home_url()));
    exit;
}
send_pdf(certificate_pdf($cert), 'Certificate_' . $cert['certificate_id'] . '.pdf', isset($_GET['inline']));
