<?php
// client/proposals.php - quotes from our team: accept (issues the 50% deposit invoice), ask for changes, or decline.
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Proposals & Quotes";

$errors = [];
$success_msg = "";

// Quotes past their validity date can no longer be accepted
$stmt = $conn->prepare("UPDATE proposals SET status = 'EXPIRED' WHERE client_id = ? AND status = 'PENDING' AND valid_until < NOW()");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_ok()) {
        die("CSRF validation failed.");
    }

    $proposal_id = $_POST['proposal_id'] ?? '';
    $action = $_POST['action'];

    $conn->begin_transaction();
    try {
        // Lock the row so a double click cannot issue two deposit invoices
        $stmt = $conn->prepare("SELECT * FROM proposals WHERE id = ? AND client_id = ? AND status = 'PENDING' FOR UPDATE");
        $stmt->bind_param("ss", $proposal_id, $client_id);
        $stmt->execute();
        $prop = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prop) {
            throw new Exception("This proposal is no longer open for a response.");
        }

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE proposals SET status = 'APPROVED', response_date = NOW() WHERE id = ?");
            $stmt->bind_param("s", $proposal_id);
            $stmt->execute();
            $stmt->close();

            $deposit = round(floatval($prop['amount']) * 0.50, 2);
            $payment_id = generate_uuid();
            $trans_id = 'INV-' . strtoupper(substr(str_replace('-', '', $payment_id), 0, 10));
            $desc = "Upfront deposit (50%) [" . $prop['proposal_id'] . "] " . $prop['title'];

            $stmt = $conn->prepare("INSERT INTO payments (id, transaction_id, type, amount, method, status, client_id, description, due_date)
                                    VALUES (?, ?, 'DEPOSIT', ?, 'PAYSTACK', 'PENDING', ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
            $stmt->bind_param("ssdss", $payment_id, $trans_id, $deposit, $client_id, $desc);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            notify_admins("Proposal accepted", ($client['company_name'] ?: 'A client') . " accepted '{$prop['title']}'. Deposit invoice issued - kick off the project once it is paid.", "/admin/project_control.php");
            log_activity("Approved Sales Proposal", ["proposal_id" => $proposal_id]);
            set_flash_message('success', "Proposal accepted. Pay the " . format_currency($deposit) . " deposit to get your project scheduled.");
            header("Location: " . BASE_URL . "/client/billing.php");
            exit();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE proposals SET status = 'REJECTED', response_date = NOW() WHERE id = ?");
            $stmt->bind_param("s", $proposal_id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            notify_admins("Proposal declined", ($client['company_name'] ?: 'A client') . " declined '{$prop['title']}'.", "/admin/proposals.php");
            log_activity("Rejected Sales Proposal", ["proposal_id" => $proposal_id]);
            $success_msg = "You have declined the proposal.";
        } elseif ($action === 'negotiate') {
            $note = trim($_POST['note'] ?? '');
            if ($note === '') {
                throw new Exception("Tell us what you would like changed.");
            }
            $conn->commit();
            notify_admins("Client requested changes", ($client['company_name'] ?: 'A client') . " on '{$prop['title']}' ({$prop['proposal_id']}): " . mb_substr($note, 0, 500), "/admin/proposals.php");
            log_activity("Requested proposal changes", ["proposal_id" => $proposal_id]);
            $success_msg = "Your request has been sent. We'll update the proposal and notify you.";
        } else {
            throw new Exception("Unknown action.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

$proposals = [];
$stmt = $conn->prepare("SELECT p.*, u.first_name AS staff_first, u.last_name AS staff_last,
                               (SELECT pay.status FROM payments pay WHERE pay.client_id = p.client_id AND pay.type = 'DEPOSIT'
                                  AND pay.description LIKE CONCAT('%[', p.proposal_id, ']%') ORDER BY pay.created_at DESC LIMIT 1) AS deposit_status,
                               (SELECT pr.id FROM projects pr WHERE pr.proposal_id = p.id LIMIT 1) AS project_uuid
                        FROM proposals p
                        LEFT JOIN staff s ON p.assigned_to = s.id
                        LEFT JOIN users_staff u ON s.user_id = u.id
                        WHERE p.client_id = ?
                        ORDER BY p.created_at DESC");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $proposals[] = $row;
}
$stmt->close();

$status_style = [
    'DRAFT' => ['In review', 'bg-slate-100 text-slate-600'],
    'PENDING' => ['Awaiting your answer', 'bg-amber-500/10 text-amber-600'],
    'APPROVED' => ['Accepted', 'bg-emerald-500/10 text-emerald-600'],
    'REJECTED' => ['Declined', 'bg-red-500/10 text-red-600'],
    'EXPIRED' => ['Expired', 'bg-slate-100 text-slate-500'],
];

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?>
            <p><?php echo sanitize($err); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($success_msg): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-sm font-medium">
        <?php echo sanitize($success_msg); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php if (empty($proposals)): ?>
        <div class="col-span-full bg-white border border-slate-200 p-12 text-center rounded-2xl">
            <i data-lucide="file-text" class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-400"></i>
            <h4 class="text-sm font-bold text-slate-700">You have no proposals yet.</h4>
            <p class="text-xs text-slate-400 mt-1">Tell us what you need built and we'll send you a quote.</p>
            <a href="onboarding.php" class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Request a project</a>
        </div>
    <?php else: ?>
        <?php foreach ($proposals as $prop):
            [$status_label, $status_class] = $status_style[$prop['status']] ?? [$prop['status'], 'bg-slate-100 text-slate-500'];
            $services = json_decode($prop['services'], true) ?: [];
            ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="flex justify-between items-start gap-3">
                        <div>
                            <span class="text-[9px] bg-slate-100 px-2.5 py-0.5 rounded font-mono text-slate-500 uppercase font-semibold"><?php echo sanitize($prop['proposal_id']); ?></span>
                            <h4 class="text-base font-bold text-slate-900 mt-1.5"><?php echo sanitize($prop['title']); ?></h4>
                        </div>
                        <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider whitespace-nowrap <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                    </div>

                    <p class="text-xs text-slate-500 leading-relaxed"><?php echo nl2br(sanitize($prop['description'])); ?></p>

                    <?php if ($services): ?>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1.5">What's included</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($services as $srv): ?>
                                    <span class="bg-indigo-500/5 text-indigo-600 px-2.5 py-0.5 rounded text-[10px] border border-indigo-500/10 font-medium"><?php echo sanitize($srv); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-4 text-[10px] text-slate-400 border-t border-slate-100 pt-3">
                        <div>
                            <span class="font-bold block uppercase tracking-wider">Timeline</span>
                            <span class="text-slate-700 font-semibold mt-0.5 block"><?php echo sanitize($prop['timeline']); ?></span>
                        </div>
                        <div>
                            <span class="font-bold block uppercase tracking-wider">
                                <?php echo $prop['status'] === 'PENDING' ? 'Valid until' : 'Your contact'; ?>
                            </span>
                            <span class="text-slate-700 font-semibold mt-0.5 block">
                                <?php echo $prop['status'] === 'PENDING'
                                    ? date('M d, Y', strtotime($prop['valid_until']))
                                    : sanitize(trim(($prop['staff_first'] ?? '') . ' ' . ($prop['staff_last'] ?? '')) ?: 'Royal Solutions team'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 mt-5 pt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[9px] text-slate-400 uppercase font-bold block"><?php echo $prop['status'] === 'DRAFT' ? 'Estimate (not final)' : 'Price'; ?></span>
                            <span class="text-base font-bold text-slate-900"><?php echo format_currency($prop['amount']); ?></span>
                        </div>

                        <?php if ($prop['status'] === 'PENDING'): ?>
                            <div class="flex space-x-2">
                                <form action="proposals.php" method="POST" onsubmit="return confirm('Decline this proposal?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="proposal_id" value="<?php echo $prop['id']; ?>">
                                    <button type="submit" class="px-3.5 py-2 border border-red-500/30 hover:bg-red-500/5 text-red-600 font-bold rounded-xl text-xs">Decline</button>
                                </form>
                                <form action="proposals.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="proposal_id" value="<?php echo $prop['id']; ?>">
                                    <button type="submit" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-sm">
                                        Accept &amp; pay deposit (<?php echo format_currency($prop['amount'] * 0.5); ?>)
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($prop['status'] === 'APPROVED'): ?>
                            <?php if ($prop['project_uuid']): ?>
                                <a href="projects.php?project_id=<?php echo $prop['project_uuid']; ?>" class="px-3.5 py-2 bg-indigo-600 text-white font-bold rounded-xl text-xs">View project</a>
                            <?php elseif ($prop['deposit_status'] === 'PENDING' || $prop['deposit_status'] === 'OVERDUE'): ?>
                                <a href="billing.php" class="px-3.5 py-2 bg-emerald-600 text-white font-bold rounded-xl text-xs">Pay deposit</a>
                            <?php elseif ($prop['deposit_status'] === 'COMPLETED'): ?>
                                <span class="text-[10px] text-emerald-600 font-semibold">Deposit paid - project being scheduled</span>
                            <?php endif; ?>
                        <?php elseif ($prop['status'] === 'DRAFT'): ?>
                            <span class="text-[10px] text-slate-400">Our team is preparing your quote</span>
                        <?php else: ?>
                            <a href="onboarding.php" class="text-[10px] font-bold text-indigo-600">Request a new quote</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($prop['status'] === 'PENDING'): ?>
                        <details class="text-xs">
                            <summary class="cursor-pointer text-indigo-600 font-semibold">Want something changed?</summary>
                            <form action="proposals.php" method="POST" class="mt-2 space-y-2">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="negotiate">
                                <input type="hidden" name="proposal_id" value="<?php echo $prop['id']; ?>">
                                <textarea name="note" rows="2" required placeholder="Scope, price or timeline changes you'd like"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none"></textarea>
                                <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-[10px] font-bold">Send to our team</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
