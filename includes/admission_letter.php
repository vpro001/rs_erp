<?php
// includes/admission_letter.php
// Admission letter for a single enrollment: data lookup, HTML rendering (email-safe inline
// styles, also used by the printable page) and delivery to the student's email.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';

const ACADEMY_NAME = 'Royal Solutions Academy';

// Some stored text is already HTML-escaped (sanitize() on insert); don't double-encode it.
function letter_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
}

function letter_date($date)
{
    return $date ? date('l, j F Y', strtotime($date)) : 'To be announced';
}

function admission_letter_data($conn, $enrollment_id)
{
    $stmt = $conn->prepare("SELECT e.id AS enrollment_id, e.enrollment_date, e.start_date, e.end_date, e.payment_type,
                                   e.total_paid, e.amount_due, e.admission_sent_at, e.status,
                                   s.id AS student_pk, s.student_id AS reg_no, s.user_id,
                                   u.first_name, u.last_name, u.email,
                                   c.title AS course_title, c.duration, c.level, c.mode, c.price,
                                   b.name AS batch_name
                            FROM enrollments e
                            JOIN students s ON s.id = e.student_id
                            JOIN users_student u ON u.id = s.user_id
                            JOIN courses c ON c.id = e.course_id
                            LEFT JOIN classes b ON b.id = e.batch_id
                            WHERE e.id = ?");
    $stmt->bind_param("s", $enrollment_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function admission_letter_html($d)
{
    $name = letter_e($d['first_name'] . ' ' . $d['last_name']);
    $rows = [
        'Registration No.' => letter_e($d['reg_no']),
        'Course' => letter_e($d['course_title']),
        'Class Batch' => $d['batch_name'] ? letter_e($d['batch_name']) : 'Not yet assigned',
        'Start Date' => letter_date($d['start_date']),
        'End Date' => letter_date($d['end_date']),
        'Duration' => letter_e($d['duration']),
        'Mode of Study' => ucfirst(strtolower($d['mode'])),
        'Level' => ucfirst(strtolower($d['level'])),
        'Tuition Fee' => format_currency($d['price']),
        'Payment Plan' => $d['payment_type'] === 'INSTALLMENT' ? 'Installment' : 'Full payment',
        'Amount Paid' => format_currency($d['total_paid']),
        'Outstanding Balance' => format_currency($d['amount_due']),
    ];
    $table = '';
    foreach ($rows as $label => $value) {
        $table .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:42%;">' . $label
            . '</td><td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:13px;font-weight:600;">' . $value . '</td></tr>';
    }
    $issued = date('j F Y', strtotime($d['admission_sent_at'] ?? 'now'));
    $portal = BASE_URL . '/login.php';
    $balance_note = (float) $d['amount_due'] > 0
        ? '<p style="margin:0 0 14px;">Kindly note the outstanding balance above. Payments can be made from the <strong>Billing & Installments</strong> section of your student portal.</p>'
        : '';

    return '<div style="background:#f1f5f9;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
        . '<div style="background:#17264A;color:#ffffff;padding:22px 28px;">'
        . '<div style="font-size:20px;font-weight:bold;letter-spacing:.5px;">' . ACADEMY_NAME . '</div>'
        . '<div style="font-size:12px;opacity:.8;margin-top:4px;">Office of Admissions</div></div>'
        . '<div style="padding:28px;color:#334155;font-size:14px;line-height:1.6;">'
        . '<div style="text-align:right;font-size:12px;color:#64748b;margin-bottom:18px;">' . $issued . '<br>Ref: ' . letter_e($d['reg_no']) . '</div>'
        . '<h2 style="margin:0 0 18px;font-size:17px;color:#0f172a;text-transform:uppercase;letter-spacing:1px;text-align:center;">Letter of Admission</h2>'
        . '<p style="margin:0 0 14px;">Dear ' . $name . ',</p>'
        . '<p style="margin:0 0 14px;">We are pleased to inform you that you have been offered admission into <strong>' . letter_e($d['course_title']) . '</strong> at ' . ACADEMY_NAME
        . '. Your programme begins on <strong>' . letter_date($d['start_date']) . '</strong> and ends on <strong>' . letter_date($d['end_date']) . '</strong>.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:18px 0;border:1px solid #e2e8f0;">' . $table . '</table>'
        . $balance_note
        . '<p style="margin:0 0 14px;">Please sign in to your student portal before the start date to review your class schedule and course materials:<br>'
        . '<a href="' . $portal . '" style="color:#1d4ed8;">' . $portal . '</a> (use ' . letter_e($d['email']) . ').</p>'
        . '<p style="margin:0 0 14px;">Congratulations, and welcome to ' . ACADEMY_NAME . '.</p>'
        . '<p style="margin:24px 0 0;">Yours faithfully,<br><strong>Admissions Office</strong><br>' . ACADEMY_NAME . '</p>'
        . '</div></div></div>';
}

/**
 * Email the admission letter for an enrollment and stamp admission_sent_at on success.
 *
 * @return array ['ok' => bool, 'simulated' => bool, 'error' => string|null, 'email' => string]
 */
function send_admission_letter($conn, $enrollment_id)
{
    $d = admission_letter_data($conn, $enrollment_id);
    if (!$d) {
        return ['ok' => false, 'simulated' => false, 'error' => 'Enrollment not found.', 'email' => ''];
    }
    $d['admission_sent_at'] = date('Y-m-d H:i:s');
    $subject = 'Admission Letter: ' . html_entity_decode($d['course_title'], ENT_QUOTES, 'UTF-8') . ' (' . ACADEMY_NAME . ')';
    $result = send_email($d['email'], $d['first_name'] . ' ' . $d['last_name'], $subject, admission_letter_html($d));

    if ($result['ok']) {
        $stmt = $conn->prepare("UPDATE enrollments SET admission_sent_at = ? WHERE id = ?");
        $stmt->bind_param("ss", $d['admission_sent_at'], $enrollment_id);
        $stmt->execute();
        $stmt->close();
    }
    return $result + ['email' => $d['email']];
}

// Human-readable outcome for admin flash messages.
function admission_send_summary($result)
{
    if (!$result['ok']) {
        return "Admission letter could not be emailed: " . sanitize($result['error'] ?? 'unknown error');
    }
    if ($result['simulated']) {
        return "Admission letter generated for " . sanitize($result['email']) . " (SMTP not configured, saved to uploads/mail_outbox).";
    }
    return "Admission letter emailed to " . sanitize($result['email']) . ".";
}
