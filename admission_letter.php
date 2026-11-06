<?php
// admission_letter.php?enrollment_id=...[&download=pdf]
// Admission letter for one enrollment, shared by every portal. Students may open their own;
// admins, customer service and head admin (anyone who can view student records) may open any.
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/admission_letter.php';

$enrollment_id = sanitize($_GET['enrollment_id'] ?? '');
$letter = admission_letter_data($conn, $enrollment_id);

if (!can_view_admission_letter($letter)) {
    set_flash_message('error', 'Admission letter not found.');
    header("Location: " . (has_role('STUDENT') ? BASE_URL . '/student/my_courses.php' : portal_home_url()));
    exit;
}

// The letter's issue date is when it was first generated (sent at enrollment); stamp it on first open otherwise.
if (empty($letter['admission_sent_at'])) {
    $letter['admission_sent_at'] = date('Y-m-d H:i:s');
}

if (($_GET['download'] ?? '') === 'pdf') {
    log_activity("Downloaded admission letter", ["enrollment_id" => $enrollment_id]);
    send_pdf(admission_letter_pdf($letter), admission_letter_filename($letter), isset($_GET['inline']));
}
$pdf_url = BASE_URL . '/admission_letter.php?enrollment_id=' . urlencode($enrollment_id) . '&download=pdf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Letter - <?php echo letter_e($letter['first_name'] . ' ' . $letter['last_name']); ?></title>
    <style>
        body { margin: 0; background: #f1f5f9; }
        .toolbar { max-width: 640px; margin: 16px auto 0; padding: 0 12px; display: flex; justify-content: flex-end; gap: 8px; font-family: Arial, sans-serif; }
        .toolbar a, .toolbar button { background: #17264A; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #fff; color: #17264A; border: 1px solid #17264A; }
        @media print { .toolbar { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        <a href="<?php echo $pdf_url; ?>">Download PDF</a>
    </div>
    <?php echo admission_letter_html($letter); ?>
</body>
</html>
