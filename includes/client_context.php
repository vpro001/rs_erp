<?php
// includes/client_context.php
// Resolves the signed-in client's `clients` row (creating it on first visit) for every client page.
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user = current_user();
$client = null;

$stmt = $conn->prepare("SELECT id, client_id, company_name, contact_person FROM clients WHERE user_id = ?");
$stmt->bind_param("s", $user['id']);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$client) {
    $new_id = generate_uuid();
    $new_ref = 'CL-' . strtoupper(substr(str_replace('-', '', $new_id), 0, 8));
    $company = $user['first_name'] . "'s Enterprise";
    $contact = $user['first_name'] . ' ' . $user['last_name'];
    $stmt = $conn->prepare("INSERT INTO clients (id, user_id, client_id, company_name, contact_person) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $new_id, $user['id'], $new_ref, $company, $contact);
    $stmt->execute();
    $stmt->close();
    $client = ['id' => $new_id, 'client_id' => $new_ref, 'company_name' => $company, 'contact_person' => $contact];
}

$client_id = $client['id'];

/** Notify every active admin/manager (used when a client needs staff attention). */
function notify_admins($title, $message, $link = null)
{
    global $conn;
    $res = $conn->query("SELECT id FROM users_admin WHERE role IN ('ADMIN','MANAGER') AND status = 'ACTIVE' LIMIT 5");
    while ($row = $res->fetch_assoc()) {
        send_notification($row['id'], $title, $message, 'CRM', 'HIGH', $link);
    }
}

function csrf_ok()
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
