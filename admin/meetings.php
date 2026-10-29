<?php
// admin/meetings.php - client meeting requests: confirm, reschedule, decline, and choose the host.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Client Meetings";

$errors = [];
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'];

    try {
        $stmt = $conn->prepare("SELECT m.*, cl.user_id AS client_user_id FROM meetings m JOIN clients cl ON cl.id = m.client_id WHERE m.id = ?");
        $stmt->bind_param("s", $_POST['meeting_id']);
        $stmt->execute();
        $m = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$m) {
            throw new Exception("Meeting not found.");
        }

        $host = $_POST['staff_id'] ?? '';
        if (in_array($action, ['confirm', 'reschedule'], true)) {
            $chk = $conn->prepare("SELECT id FROM staff WHERE id = ? AND status = 'ACTIVE'");
            $chk->bind_param("s", $host);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                throw new Exception("Choose who will host this meeting.");
            }
            $chk->close();
        }

        $room = "https://meet.jit.si/RoyalSolutionsERP_" . md5($m['id']);
        $when_label = date('D, M d \a\t H:i', strtotime($m['meeting_date']));

        if ($action === 'confirm') {
            $stmt = $conn->prepare("UPDATE meetings SET status = 'SCHEDULED', staff_id = ?, location = 'Jitsi Virtual Conference Room', meeting_url = ? WHERE id = ?");
            $stmt->bind_param("sss", $host, $room, $m['id']);
            $stmt->execute();
            $stmt->close();
            send_notification($m['client_user_id'], "Meeting confirmed", "Your meeting '{$m['title']}' is confirmed for $when_label.", "CRM", "HIGH", "/client/meetings.php");
            $success_msg = "Meeting confirmed for $when_label.";
        } elseif ($action === 'reschedule') {
            $ts = strtotime($_POST['meeting_date'] ?? '');
            if ($ts === false || $ts < time()) {
                throw new Exception("Choose a new date and time in the future.");
            }
            $new = date('Y-m-d H:i:s', $ts);
            $stmt = $conn->prepare("UPDATE meetings SET status = 'RESCHEDULED', staff_id = ?, meeting_date = ?, location = 'Jitsi Virtual Conference Room', meeting_url = ? WHERE id = ?");
            $stmt->bind_param("ssss", $host, $new, $room, $m['id']);
            $stmt->execute();
            $stmt->close();
            $note = trim($_POST['note'] ?? '');
            send_notification($m['client_user_id'], "Meeting rescheduled", "Your meeting '{$m['title']}' was moved to " . date('D, M d \a\t H:i', $ts) . ($note !== '' ? ". Note: " . mb_substr($note, 0, 200) : ''), "CRM", "HIGH", "/client/meetings.php");
            $success_msg = "Meeting rescheduled and the client notified.";
        } elseif ($action === 'decline') {
            $stmt = $conn->prepare("UPDATE meetings SET status = 'CANCELLED' WHERE id = ?");
            $stmt->bind_param("s", $m['id']);
            $stmt->execute();
            $stmt->close();
            $note = trim($_POST['note'] ?? '');
            send_notification($m['client_user_id'], "Meeting request declined", "We couldn't take your meeting '{$m['title']}'." . ($note !== '' ? " " . mb_substr($note, 0, 200) : " Please request another time."), "CRM", "MEDIUM", "/client/meetings.php");
            $success_msg = "Request declined.";
        } elseif ($action === 'complete') {
            $stmt = $conn->prepare("UPDATE meetings SET status = 'COMPLETED' WHERE id = ?");
            $stmt->bind_param("s", $m['id']);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Marked as completed.";
        } else {
            throw new Exception("Unknown action.");
        }
        log_activity("Meeting $action", ["meeting_id" => $m['id']]);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$staff_members = $conn->query("SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users_staff u ON s.user_id = u.id WHERE s.status = 'ACTIVE'")->fetch_all(MYSQLI_ASSOC);

$meetings = $conn->query("SELECT m.*, cl.company_name, cl.contact_person, u.first_name AS staff_first, u.last_name AS staff_last
                          FROM meetings m
                          JOIN clients cl ON cl.id = m.client_id
                          LEFT JOIN staff s ON m.staff_id = s.id
                          LEFT JOIN users_staff u ON s.user_id = u.id
                          WHERE m.status IN ('REQUESTED','SCHEDULED','RESCHEDULED','IN_PROGRESS')
                          ORDER BY FIELD(m.status,'REQUESTED') DESC, m.meeting_date ASC")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="mb-4 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium"><?php echo sanitize($err); ?></div>
<?php endforeach; ?>
<?php if ($success_msg): ?>
    <div class="mb-4 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-sm font-medium"><?php echo sanitize($success_msg); ?></div>
<?php endif; ?>

<div class="space-y-5">
    <?php if (empty($meetings)): ?>
        <div class="bg-white border border-slate-200 p-12 text-center rounded-2xl text-sm text-slate-400">No open meeting requests or upcoming meetings.</div>
    <?php endif; ?>

    <?php foreach ($meetings as $m):
        $is_req = $m['status'] === 'REQUESTED';
        ?>
        <div class="bg-white border <?php echo $is_req ? 'border-amber-300' : 'border-slate-200'; ?> rounded-2xl p-6 shadow-sm">
            <div class="flex flex-wrap justify-between items-start gap-3">
                <div>
                    <h4 class="text-sm font-bold text-slate-900"><?php echo sanitize($m['title']); ?></h4>
                    <p class="text-[11px] text-slate-400 mt-0.5"><?php echo sanitize($m['company_name'] ?: $m['contact_person']); ?>
                        <?php if ($m['staff_first']): ?> &bull; Host: <?php echo sanitize($m['staff_first'] . ' ' . $m['staff_last']); ?><?php endif; ?></p>
                    <?php if ($m['description']): ?><p class="text-xs text-slate-500 mt-2"><?php echo nl2br(sanitize($m['description'])); ?></p><?php endif; ?>
                </div>
                <div class="text-right">
                    <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase <?php echo $is_req ? 'bg-amber-500/10 text-amber-600' : 'bg-blue-500/10 text-blue-600'; ?>"><?php echo $is_req ? 'Needs confirmation' : sanitize($m['status']); ?></span>
                    <p class="text-sm font-bold text-slate-900 mt-1"><?php echo date('D, M d, Y - H:i', strtotime($m['meeting_date'])); ?></p>
                    <p class="text-[10px] text-slate-400"><?php echo intval($m['duration']); ?> min</p>
                </div>
            </div>

            <form method="POST" action="meetings.php" class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Host</label>
                    <select name="staff_id" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                        <option value="">Choose host...</option>
                        <?php foreach ($staff_members as $sm): ?>
                            <option value="<?php echo $sm['id']; ?>" <?php echo $m['staff_id'] === $sm['id'] ? 'selected' : ''; ?>><?php echo sanitize($sm['first_name'] . ' ' . $sm['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">New time (to reschedule)</label>
                    <input type="datetime-local" name="meeting_date" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Note to client (optional)</label>
                    <input type="text" name="note" maxlength="200" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if ($is_req): ?>
                        <button name="action" value="confirm" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-[10px] font-bold">Confirm</button>
                    <?php else: ?>
                        <button name="action" value="complete" class="px-3 py-1.5 bg-slate-700 text-white rounded-lg text-[10px] font-bold">Mark done</button>
                    <?php endif; ?>
                    <button name="action" value="reschedule" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-[10px] font-bold">Reschedule</button>
                    <button name="action" value="decline" class="px-3 py-1.5 border border-red-500/30 text-red-600 rounded-lg text-[10px] font-bold" onclick="return confirm('Cancel this meeting?');">Decline</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
