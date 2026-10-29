<?php
// api/client_payment.php - settles one of the signed-in client's invoices.
// The amount always comes from the invoice row, never from the browser. The gateway is simulated
// (same as the student checkout); swap the reference generation for a Paystack verify call to go live.
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'CLIENT') {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true) ?: [];
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($input['csrf_token'] ?? ''))) {
    echo json_encode(["success" => false, "error" => "Session expired. Please refresh the page."]);
    exit();
}

$payment_id = (string) ($input['payment_id'] ?? '');
if ($payment_id === '') {
    echo json_encode(["success" => false, "error" => "No invoice selected."]);
    exit();
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT p.* FROM payments p JOIN clients c ON c.id = p.client_id
                            WHERE p.id = ? AND c.user_id = ? AND p.status IN ('PENDING','OVERDUE') FOR UPDATE");
    $stmt->bind_param("ss", $payment_id, $user['id']);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pay) {
        throw new Exception("This invoice is not payable (already paid or not yours).");
    }

    $reference = 'PSK-' . strtoupper(bin2hex(random_bytes(6)));
    $amount = floatval($pay['amount']);

    $stmt = $conn->prepare("UPDATE payments SET status = 'COMPLETED', paid_at = NOW(), paystack_reference = ?, receipt_url = NULL WHERE id = ?");
    $stmt->bind_param("ss", $reference, $payment_id);
    $stmt->execute();
    $stmt->close();

    $income_id = generate_uuid();
    $desc = $pay['description'] . " ($reference)";
    $stmt = $conn->prepare("INSERT INTO income_records (id, source, amount, description, category) VALUES (?, 'SERVICES', ?, ?, 'Client Project Payment')");
    $stmt->bind_param("sds", $income_id, $amount, $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    log_activity("Client paid invoice", ["payment_id" => $payment_id, "reference" => $reference, "amount" => $amount]);
    send_notification($user['id'], "Payment received", "We received your payment of " . format_currency($amount) . ". Thank you!", "FINANCE", "MEDIUM", "/client/billing.php");

    $admins = $conn->query("SELECT id FROM users_admin WHERE role IN ('ADMIN','MANAGER') AND status = 'ACTIVE' LIMIT 5");
    $next = $pay['type'] === 'DEPOSIT' ? " Deposit cleared - the project is ready to kick off." : "";
    while ($a = $admins->fetch_assoc()) {
        send_notification($a['id'], "Client payment received", format_currency($amount) . " paid: " . $pay['description'] . $next, "FINANCE", "HIGH", "/admin/project_control.php");
    }

    echo json_encode(["success" => true, "data" => ["reference" => $reference, "amount" => $amount]]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
