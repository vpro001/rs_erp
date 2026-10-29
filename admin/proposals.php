<?php
// admin/proposals.php - review client project requests (DRAFT), set the real price and send the quote (PENDING).
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Client Proposals";

$errors = [];
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }

    $id = $_POST['proposal_id'] ?? '';
    $action = $_POST['action'];

    try {
        $stmt = $conn->prepare("SELECT p.*, cl.user_id AS client_user_id FROM proposals p JOIN clients cl ON cl.id = p.client_id WHERE p.id = ? AND p.status IN ('DRAFT','PENDING')");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $prop = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$prop) {
            throw new Exception("Proposal not found or already answered by the client.");
        }

        $title = trim($_POST['title'] ?? '');
        $amount = round(floatval($_POST['amount'] ?? 0), 2);
        $timeline = trim($_POST['timeline'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $services = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $_POST['services'] ?? ''))));
        $valid_days = max(1, min(90, intval($_POST['valid_days'] ?? 14)));
        $assigned = $_POST['assigned_to'] ?? '';

        if ($title === '' || $amount <= 0 || $timeline === '' || empty($services)) {
            throw new Exception("Title, a price above zero, a timeline and at least one included service are required.");
        }
        if ($assigned !== '') {
            $chk = $conn->prepare("SELECT id FROM staff WHERE id = ?");
            $chk->bind_param("s", $assigned);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                throw new Exception("Selected staff member does not exist.");
            }
            $chk->close();
        } else {
            $assigned = null;
        }

        $services_json = json_encode($services);
        $send = ($action === 'send');
        $status = $send ? 'PENDING' : $prop['status'];
        $valid_until = date('Y-m-d H:i:s', strtotime("+$valid_days days"));

        $stmt = $conn->prepare("UPDATE proposals SET title = ?, amount = ?, timeline = ?, description = ?, services = ?, assigned_to = ?, status = ?, valid_until = ? WHERE id = ?");
        $stmt->bind_param("sdsssssss", $title, $amount, $timeline, $description, $services_json, $assigned, $status, $valid_until, $id);
        $stmt->execute();
        $stmt->close();

        if ($send) {
            send_notification($prop['client_user_id'], "Your proposal is ready", "We've prepared a quote for '$title' (" . format_currency($amount) . "). Review and accept it to get started.", "CRM", "HIGH", "/client/proposals.php");
            log_activity("Sent proposal to client", ["proposal" => $prop['proposal_id'], "amount" => $amount]);
            $success_msg = "Proposal {$prop['proposal_id']} sent to the client.";
        } else {
            $success_msg = "Changes saved.";
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$staff_members = [];
$res = $conn->query("SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users_staff u ON s.user_id = u.id WHERE s.status = 'ACTIVE'");
while ($row = $res->fetch_assoc()) {
    $staff_members[] = $row;
}

$proposals = [];
$res = $conn->query("SELECT p.*, cl.company_name, cl.contact_person FROM proposals p JOIN clients cl ON cl.id = p.client_id
                     ORDER BY FIELD(p.status,'DRAFT','PENDING','APPROVED','REJECTED','EXPIRED'), p.created_at DESC");
while ($row = $res->fetch_assoc()) {
    $proposals[] = $row;
}

include __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="mb-4 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium"><?php echo sanitize($err); ?></div>
<?php endforeach; ?>
<?php if ($success_msg): ?>
    <div class="mb-4 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-sm font-medium"><?php echo sanitize($success_msg); ?></div>
<?php endif; ?>

<div class="space-y-5">
    <?php if (empty($proposals)): ?>
        <div class="bg-white border border-slate-200 p-12 text-center rounded-2xl text-sm text-slate-400">No client requests yet.</div>
    <?php endif; ?>

    <?php foreach ($proposals as $p):
        $editable = in_array($p['status'], ['DRAFT', 'PENDING'], true);
        $services = json_decode($p['services'], true) ?: [];
        ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex flex-wrap justify-between items-start gap-3">
                <div>
                    <span class="text-[9px] bg-slate-100 px-2 py-0.5 rounded font-mono text-slate-500 uppercase font-semibold"><?php echo sanitize($p['proposal_id']); ?></span>
                    <h4 class="text-sm font-bold text-slate-900 mt-1"><?php echo sanitize($p['title']); ?></h4>
                    <p class="text-[11px] text-slate-400 mt-0.5"><?php echo sanitize($p['company_name'] ?: $p['contact_person']); ?> &bull; requested <?php echo date('M d, Y', strtotime($p['created_at'])); ?></p>
                </div>
                <div class="text-right">
                    <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase <?php echo $p['status'] === 'DRAFT' ? 'bg-indigo-500/10 text-indigo-600' : ($p['status'] === 'PENDING' ? 'bg-amber-500/10 text-amber-600' : ($p['status'] === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-slate-100 text-slate-500')); ?>">
                        <?php echo $p['status'] === 'DRAFT' ? 'Needs quote' : ($p['status'] === 'PENDING' ? 'Sent to client' : sanitize($p['status'])); ?>
                    </span>
                    <p class="text-sm font-bold text-slate-900 mt-1"><?php echo format_currency($p['amount']); ?></p>
                </div>
            </div>

            <?php if ($editable): ?>
                <form method="POST" action="proposals.php" class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="proposal_id" value="<?php echo $p['id']; ?>">
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Title</label>
                        <input type="text" name="title" required value="<?php echo sanitize($p['title']); ?>" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Price (USD)</label>
                            <input type="number" name="amount" step="0.01" min="1" required value="<?php echo $p['amount']; ?>" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Timeline</label>
                            <input type="text" name="timeline" required value="<?php echo sanitize($p['timeline']); ?>" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Valid (days)</label>
                            <input type="number" name="valid_days" min="1" max="90" value="14" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Client brief / scope</label>
                        <textarea name="description" rows="4" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none"><?php echo sanitize($p['description']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Included services (one per line)</label>
                        <textarea name="services" rows="4" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none"><?php echo sanitize(implode("\n", $services)); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Account lead</label>
                        <select name="assigned_to" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                            <option value="">Unassigned</option>
                            <?php foreach ($staff_members as $sm): ?>
                                <option value="<?php echo $sm['id']; ?>" <?php echo $p['assigned_to'] === $sm['id'] ? 'selected' : ''; ?>><?php echo sanitize($sm['first_name'] . ' ' . $sm['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <button type="submit" name="action" value="save" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">Save changes</button>
                        <button type="submit" name="action" value="send" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold">
                            <?php echo $p['status'] === 'DRAFT' ? 'Send quote to client' : 'Re-send updated quote'; ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
