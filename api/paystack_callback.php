<?php
// api/paystack_callback.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
if (!$user) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$installment_id = sanitize($input['installment_id'] ?? '');
$amount = floatval($input['amount'] ?? 0);
$reference = sanitize($input['reference'] ?? '');
$student_id = sanitize($input['student_id'] ?? '');
$course_id = sanitize($input['course_id'] ?? '');

if (empty($installment_id) || empty($reference) || empty($student_id) || empty($course_id)) {
    echo json_encode(["success" => false, "error" => "Incomplete request parameters."]);
    exit();
}

// Validate amount is positive
if ($amount <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid payment amount."]);
    exit();
}

$conn->begin_transaction();
try {
    // ========== FIX #1: Idempotency Check ==========
    // Check if this Paystack reference was already processed successfully
    $stmt = $conn->prepare("SELECT id, status FROM payments WHERE paystack_reference = ?");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $existing_payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing_payment) {
        // Return success but indicate already processed (idempotent response)
        if ($existing_payment['status'] === 'COMPLETED') {
            echo json_encode([
                "success" => true,
                "data" => [
                    "alreadyProcessed" => true,
                    "message" => "Payment already processed",
                    "payment_id" => $existing_payment['id']
                ]
            ]);
            $conn->rollback(); // No actual changes needed
            exit();
        } else {
            throw new Exception("Payment reference exists with status: " . $existing_payment['status']);
        }
    }

    // ========== FIX #2: Ownership Verification ==========
    // Verify the student belongs to the authenticated user
    if ($user['role'] === 'STUDENT') {
        $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ss", $student_id, $user['id']);
        $stmt->execute();
        $student_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student_check) {
            throw new Exception("Unauthorized: Student ID does not belong to authenticated user");
        }
    }

    // ========== Fetch installment schedule details ==========
    $stmt = $conn->prepare("SELECT * FROM installment_payments WHERE id = ?");
    $stmt->bind_param("s", $installment_id);
    $stmt->execute();
    $inst = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inst) {
        throw new Exception("Installment record not found.");
    }

    // Verify installment belongs to the correct student
    if ($inst['enrollment_id']) {
        $stmt = $conn->prepare("SELECT student_id FROM enrollments WHERE id = ?");
        $stmt->bind_param("s", $inst['enrollment_id']);
        $stmt->execute();
        $enrollment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$enrollment || $enrollment['student_id'] !== $student_id) {
            throw new Exception("Installment does not belong to the specified student");
        }
    }

    if ($inst['status'] === 'PAID') {
        throw new Exception("Installment has already been marked as PAID.");
    }

    // ========== FIX #3: Amount Verification (Exact Match) ==========
    // Compare exact amounts to prevent fraud (using integer comparison in cents/kobo)
    $expected_amount_cents = round(floatval($inst['amount']) * 100);
    $paid_amount_cents = round($amount * 100);

    if ($paid_amount_cents !== $expected_amount_cents) {
        throw new Exception(
            "Amount mismatch: Expected " . number_format($expected_amount_cents / 100, 2) . 
            ", Received " . number_format($paid_amount_cents / 100, 2)
        );
    }

    // ========== FIX #4: Transaction Wrapping (All operations in single transaction) ==========
    // 1. Update installment payment record
    $stmt = $conn->prepare("UPDATE installment_payments SET status = 'PAID', paid_date = NOW(), paystack_reference = ? WHERE id = ?");
    $stmt->bind_param("ss", $reference, $installment_id);
    $stmt->execute();
    $stmt->close();

    // 2. Insert payment record into payments table
    $payment_id = generate_uuid();
    $trans_id = 'TXN-' . mt_rand(1000000, 9999999);
    $desc = "Installment #" . $inst['installment_number'] . " Course payment: $reference";
    $stmt = $conn->prepare("INSERT INTO payments (id, transaction_id, type, amount, method, status, student_id, course_id, paystack_reference, description, paid_at) 
                            VALUES (?, ?, 'COURSE_FEE', ?, 'PAYSTACK', 'COMPLETED', ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssdssss", $payment_id, $trans_id, $amount, $student_id, $course_id, $reference, $desc);
    $stmt->execute();
    $stmt->close();

    // 3. Insert income ledger record
    $income_id = generate_uuid();
    $stmt = $conn->prepare("INSERT INTO income_records (id, source, amount, description, category) VALUES (?, 'TRAINING', ?, ?, 'LMS Course Fee')");
    $stmt->bind_param("sds", $income_id, $amount, $desc);
    $stmt->execute();
    $stmt->close();

    // 4. Update enrollment totals (race condition safe)
    $stmt = $conn->prepare("UPDATE enrollments SET total_paid = total_paid + ?, amount_due = amount_due - ? WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ddss", $amount, $amount, $student_id, $course_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Log activity
    log_activity("Processed Paystack Installment Payment", ["reference" => $reference, "amount" => $amount, "payment_id" => $payment_id]);

    // Notify
    send_notification($user['id'], "Installment Payment Successful", "Payment of " . format_currency($amount) . " has been successfully processed.", "FINANCE", "MEDIUM");

    echo json_encode([
        "success" => true,
        "data" => [
            "payment_id" => $payment_id,
            "transaction_id" => $trans_id,
            "amount" => $amount
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    // Conditional logging for development only
    dev_log("Payment callback error: " . $e->getMessage(), 'error');
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}