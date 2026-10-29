<?php
// config/installment_cron.php
// This script simulates a cron job checking for upcoming installment payment deadlines.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/db_connection.php';

// Verify access
$is_cli = (php_sapi_name() === 'cli');
$is_authorized = $is_cli || (current_user() && in_array(current_user()['role'], ['ADMIN', 'MANAGER']));

if (!$is_authorized) {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

try {
    // Fetch installments due in next 3 days that haven't received a reminder
    $query = "SELECT ip.id as installment_id, ip.installment_number, ip.amount, ip.due_date, 
                     c.title as course_title, u.email as student_email, u.first_name, u.last_name, u.id as user_id
              FROM installment_payments ip
              JOIN enrollments e ON ip.enrollment_id = e.id
              JOIN courses c ON e.course_id = c.id
              JOIN students s ON e.student_id = s.id
              JOIN users_student u ON s.user_id = u.id
              WHERE ip.status = 'PENDING' 
                AND ip.reminder_sent = 0 
                AND ip.due_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)
                AND ip.due_date >= NOW()";
                
    $res = $conn->query($query);
    $reminders_sent = 0;
    
    $log_dir = __DIR__ . '/../uploads';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . '/simulated_emails.log';
    
    $conn->begin_transaction();
    
    while ($row = $res->fetch_assoc()) {
        $student_email = $row['student_email'];
        $first_name = $row['first_name'];
        $last_name = $row['last_name'];
        $course_title = $row['course_title'];
        $amount = format_currency($row['amount']);
        $due_date = date('F d, Y', strtotime($row['due_date']));
        $inst_num = $row['installment_number'];
        
        // Generate simulated HTML email template
        $email_html = "
--------------------------------------------------------------------------------
[SIMULATED EMAIL DISPATCH]
Date: " . date('Y-m-d H:i:s') . "
To: $first_name $last_name <$student_email>
Subject: Upcoming Tuition Installment Payment Reminder - Royal Solutions ERP

Dear $first_name,

This is an automated reminder that your upcoming installment payment #$inst_num 
for the course '$course_title' is due on $due_date.

Installment Details:
- Course: $course_title
- Installment Number: #$inst_num
- Due Amount: $amount
- Deadline: $due_date

To complete your payment, please login to your student dashboard:
" . BASE_URL . "/student/billing.php

Please ignore if you have already completed this payment.

Best regards,
Finance Department,
Royal Solutions ERP.
--------------------------------------------------------------------------------
\n";
        
        // Save to simulated emails log
        file_put_contents($log_file, $email_html, FILE_APPEND);
        
        // Send internal system notification as well
        send_notification(
            $row['user_id'],
            "Tuition Installment due soon!",
            "Your tuition installment #$inst_num for '$course_title' is due on $due_date. Amount: $amount.",
            "FINANCE",
            "HIGH",
            "/student/billing.php"
        );
        
        // Mark reminder as sent
        $stmt = $conn->prepare("UPDATE installment_payments SET reminder_sent = 1, reminder_sent_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $row['installment_id']);
        $stmt->execute();
        $stmt->close();
        
        $reminders_sent++;
    }
    
    $conn->commit();
    
    if ($is_cli) {
        echo "Cron executed successfully. Reminders sent: $reminders_sent\n";
    } else {
        header("Content-Type: application/json");
        echo json_encode(["success" => true, "reminders_sent" => $reminders_sent]);
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    if ($is_cli) {
        echo "Cron error: " . $e->getMessage() . "\n";
    } else {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}
