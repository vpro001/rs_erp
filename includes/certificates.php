<?php
// includes/certificates.php
// Payment certification and certificate authorization for enrollments.
// Flow: balance reaches zero -> Head Admin / Admin certifies full payment -> once the enrollment's
// end date has passed, they authorize the certificate, which appears on the student's portal.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/admission_letter.php'; // ACADEMY_NAME, letter_date()
require_once __DIR__ . '/enrollment_helpers.php'; // sync_roster_status()

/** Why an enrollment can't have its payment certified yet, or null if it can. */
function payment_certify_blocker($e)
{
    if (!empty($e['payment_certified_at'])) return 'Payment already certified.';
    if ((float) $e['amount_due'] > 0.005) return 'Outstanding balance of ' . format_currency($e['amount_due']) . '.';
    if (in_array($e['status'], ['DROPPED', 'SUSPENDED'], true)) return 'Enrollment is ' . strtolower($e['status']) . '.';
    return null;
}

/** Why a certificate can't be authorized yet, or null if it can. Expects cert_id (existing certificate) in $e. */
function certificate_blocker($e)
{
    if (!empty($e['cert_id'])) return 'Certificate already issued.';
    if (empty($e['payment_certified_at'])) return 'Full payment not yet certified.';
    if (in_array($e['status'], ['DROPPED', 'SUSPENDED'], true)) return 'Enrollment is ' . strtolower($e['status']) . '.';
    if (empty($e['end_date'])) return 'No end date set.';
    if (strtotime($e['end_date']) > strtotime(date('Y-m-d'))) return 'Course ends ' . date('d M Y', strtotime($e['end_date'])) . '.';
    return null;
}

/** Enrollment row with the fields the blockers need. */
function certification_enrollment($conn, $enrollment_id)
{
    $stmt = $conn->prepare("SELECT e.*, s.user_id, c.title AS course_title,
                                   (SELECT cert.id FROM certificates cert WHERE cert.student_id = e.student_id AND cert.course_id = e.course_id AND cert.is_valid = 1 LIMIT 1) AS cert_id
                            FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id WHERE e.id = ?");
    $stmt->bind_param("s", $enrollment_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception("Enrollment not found.");
    return $row;
}

function certify_payment($conn, $enrollment_id)
{
    $e = certification_enrollment($conn, $enrollment_id);
    if ($why = payment_certify_blocker($e)) throw new Exception($why);
    $by = current_user()['id'];
    $stmt = $conn->prepare("UPDATE enrollments SET payment_certified_at = NOW(), payment_certified_by = ? WHERE id = ? AND payment_certified_at IS NULL");
    $stmt->bind_param("ss", $by, $enrollment_id);
    $stmt->execute();
    $stmt->close();
    send_notification($e['user_id'], "Payment confirmed", "Your full payment for " . $e['course_title'] . " has been certified. Thank you!", "FINANCE", "MEDIUM", "/student/billing.php");
    return $e;
}

function revoke_payment_certification($conn, $enrollment_id)
{
    $e = certification_enrollment($conn, $enrollment_id);
    if (!empty($e['cert_id'])) throw new Exception("A certificate has already been issued for this enrollment.");
    $stmt = $conn->prepare("UPDATE enrollments SET payment_certified_at = NULL, payment_certified_by = NULL WHERE id = ?");
    $stmt->bind_param("s", $enrollment_id);
    $stmt->execute();
    $stmt->close();
    return $e;
}

/** Issue the certificate for an eligible enrollment and complete it. Returns the certificate number. */
function authorize_certificate($conn, $enrollment_id)
{
    $e = certification_enrollment($conn, $enrollment_id);
    if ($why = certificate_blocker($e)) throw new Exception($why);

    $id = generate_uuid();
    $cert_no = 'RSA-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $qr = 'QR-' . strtoupper(bin2hex(random_bytes(6)));
    $signature = hash('sha256', implode('|', [$cert_no, $e['student_id'], $e['course_id'], date('Y-m-d'), 'ROYAL_SOLUTIONS_ACADEMY']));
    $by = current_user()['id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO certificates (id, student_id, course_id, certificate_id, qr_code, digital_signature, issue_date, is_valid, enrollment_id, authorized_by) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, ?, ?)");
        $stmt->bind_param("ssssssss", $id, $e['student_id'], $e['course_id'], $cert_no, $qr, $signature, $enrollment_id, $by);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE enrollments SET status = 'COMPLETED', completion_date = COALESCE(completion_date, NOW()) WHERE id = ?");
        $stmt->bind_param("s", $enrollment_id);
        $stmt->execute();
        $stmt->close();
        sync_roster_status($conn, $enrollment_id); // e.g. a Frontend student leaves the shared Fullstack batch
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }
    send_notification($e['user_id'], "Your certificate is ready", "Congratulations! Your certificate for " . $e['course_title'] . " ($cert_no) is now available to download.", "LMS", "HIGH", "/student/results.php");
    return $cert_no;
}

/** Certificate with student/course details, by certificates.id. */
function certificate_data($conn, $cert_pk)
{
    $stmt = $conn->prepare("SELECT cert.*, s.student_id AS reg_no, s.user_id, u.first_name, u.last_name,
                                   c.title AS course_title, c.duration, c.level, e.start_date, e.end_date
                            FROM certificates cert
                            JOIN students s ON s.id = cert.student_id
                            JOIN users_student u ON u.id = s.user_id
                            JOIN courses c ON c.id = cert.course_id
                            LEFT JOIN enrollments e ON e.student_id = cert.student_id AND e.course_id = cert.course_id
                            WHERE cert.id = ? AND cert.is_valid = 1");
    $stmt->bind_param("s", $cert_pk);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Landscape A4 certificate of completion. */
function certificate_pdf($d)
{
    $name = $d['first_name'] . ' ' . $d['last_name'];
    $pdf = new SimplePdf('L', 'Certificate - ' . $name);
    $pdf->addPage();
    $W = $pdf->width;
    $H = $pdf->height;
    $navy = '17264A';
    $gold = 'B8913A';

    $pdf->setFillColor('FFFDF7');
    $pdf->rect(0, 0, $W, $H, 'F');
    $pdf->setDrawColor($navy);
    $pdf->rect(22, 22, $W - 44, $H - 44, 'S', 3);
    $pdf->setDrawColor($gold);
    $pdf->rect(32, 32, $W - 64, $H - 64, 'S', 1);

    $pdf->setTextColor($navy);
    $pdf->setFont('bold', 15);
    $pdf->textAligned(0, 92, $W, strtoupper(ACADEMY_NAME), 'C');
    $pdf->setTextColor($gold);
    $pdf->setFont('bold', 34);
    $pdf->textAligned(0, 150, $W, 'CERTIFICATE', 'C');
    $pdf->setFont('regular', 14);
    $pdf->textAligned(0, 174, $W, 'OF COMPLETION', 'C');

    $pdf->setTextColor('475569');
    $pdf->setFont('italic', 12);
    $pdf->textAligned(0, 222, $W, 'This is to certify that', 'C');
    $pdf->setTextColor('0F172A');
    $pdf->setFont('bold', 30);
    $pdf->textAligned(0, 268, $W, $name, 'C');
    $pdf->setDrawColor($gold);
    $pdf->line($W / 2 - 190, 282, $W / 2 + 190, 282, 1);

    $pdf->setTextColor('475569');
    $pdf->setFont('regular', 12);
    $pdf->textAligned(0, 310, $W, 'Registration No. ' . $d['reg_no'], 'C');
    $pdf->setFont('italic', 12);
    $pdf->textAligned(0, 336, $W, 'has successfully completed the programme', 'C');
    $pdf->setTextColor($navy);
    $pdf->setFont('bold', 18);
    $y = $pdf->paragraph(110, 366, $W - 220, $d['course_title'], 22, 'C');
    $pdf->setTextColor('475569');
    $pdf->setFont('regular', 11);
    $period = ($d['start_date'] && $d['end_date'])
        ? date('j F Y', strtotime($d['start_date'])) . ' to ' . date('j F Y', strtotime($d['end_date']))
        : '';
    $details = trim(($d['duration'] ? $d['duration'] . ' programme' : '') . ($period ? ', ' . $period : ''), ', ');
    if ($details !== '') {
        $pdf->textAligned(0, $y + 2, $W, $details, 'C');
    }

    // Signature blocks
    $sy = $H - 118;
    $pdf->setDrawColor('94A3B8');
    $pdf->line(120, $sy, 320, $sy, 0.8);
    $pdf->line($W - 320, $sy, $W - 120, $sy, 0.8);
    $pdf->setTextColor('0F172A');
    $pdf->setFont('bold', 11);
    $pdf->textAligned(120, $sy + 16, 200, date('j F Y', strtotime($d['issue_date'])), 'C');
    $pdf->textAligned($W - 320, $sy + 16, 200, 'Head Admin', 'C');
    $pdf->setTextColor('64748B');
    $pdf->setFont('regular', 9);
    $pdf->textAligned(120, $sy + 30, 200, 'Date of Issue', 'C');
    $pdf->textAligned($W - 320, $sy + 30, 200, ACADEMY_NAME, 'C');

    $pdf->setFont('regular', 8.5);
    $pdf->textAligned(0, $H - 52, $W, 'Certificate No. ' . $d['certificate_id'] . '   |   Verify at ' . BASE_URL . '/verify-certificate.php?id=' . $d['certificate_id'], 'C');
    return $pdf->output();
}
