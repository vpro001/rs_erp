<?php
// admin/marketers.php - marketer accounts, student referrals to confirm, and commission payouts.
// Project commissions are recorded automatically at kickoff (admin/project_control.php).
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Marketers & Referrals";

$tabs = ['referrals' => 'Student referrals', 'commissions' => 'Commissions', 'marketers' => 'Marketers'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'referrals';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'];

    try {
        if ($action === 'create_marketer') {
            $first = trim($_POST['first_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $p_rate = round(floatval($_POST['project_commission_rate'] ?? 0), 2);
            $s_rate = round(floatval($_POST['student_commission_rate'] ?? 0), 2);

            if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("First name, last name and a valid email are required.");
            }
            if (strlen($password) < 6) {
                throw new Exception("Password must be at least 6 characters.");
            }
            if ($p_rate < 0 || $p_rate > 100 || $s_rate < 0 || $s_rate > 100) {
                throw new Exception("Commission rates must be between 0 and 100%.");
            }
            $stmt = $conn->prepare("SELECT 1 FROM users_marketer WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("A marketer with this email already exists.");
            }
            $stmt->close();

            $conn->begin_transaction();
            try {
                $id = generate_uuid();
                $code = generate_referral_code($conn);
                $phone = $phone !== '' ? $phone : null;
                $stmt = $conn->prepare("INSERT INTO users_marketer (id, email, first_name, last_name, phone, referral_code, project_commission_rate, student_commission_rate, password_hash)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssdds", $id, $email, $first, $last, $phone, $code, $p_rate, $s_rate, $password);
                $stmt->execute();
                $stmt->close();

                $settings_id = generate_uuid();
                $stmt = $conn->prepare("INSERT INTO user_settings (id, user_id, user_type) VALUES (?, ?, 'MARKETER')");
                $stmt->bind_param("ss", $settings_id, $id);
                $stmt->execute();
                $stmt->close();

                $profile_id = generate_uuid();
                $full_name = "$first $last";
                $stmt = $conn->prepare("INSERT INTO profiles (id, user_id, user_type, full_name) VALUES (?, ?, 'MARKETER', ?)");
                $stmt->bind_param("sss", $profile_id, $id, $full_name);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            log_activity("Created marketer account", ["email" => $email]);
            set_flash_message('success', "Marketer account created for " . sanitize($full_name) . ". They sign in at /marketer/login.php with referral code $code.");
            $tab = 'marketers';
        }

        elseif ($action === 'update_marketer') {
            $id = $_POST['marketer_id'] ?? '';
            $p_rate = round(floatval($_POST['project_commission_rate'] ?? 0), 2);
            $s_rate = round(floatval($_POST['student_commission_rate'] ?? 0), 2);
            $status = $_POST['status'] ?? '';
            if ($p_rate < 0 || $p_rate > 100 || $s_rate < 0 || $s_rate > 100 || !in_array($status, ['ACTIVE', 'INACTIVE', 'SUSPENDED'], true)) {
                throw new Exception("Invalid rate or status.");
            }
            $stmt = $conn->prepare("UPDATE users_marketer SET project_commission_rate = ?, student_commission_rate = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ddss", $p_rate, $s_rate, $status, $id);
            $stmt->execute();
            $stmt->close();
            set_flash_message('success', "Marketer updated. New rates apply to commissions recorded from now on.");
            $tab = 'marketers';
        }

        elseif ($action === 'link_client') {
            $client_id = $_POST['client_id'] ?? '';
            $marketer_id = $_POST['marketer_id'] ?? '';
            $stmt = $conn->prepare("SELECT id FROM users_marketer WHERE id = ?");
            $stmt->bind_param("s", $marketer_id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                throw new Exception("Choose a marketer.");
            }
            $stmt->close();
            $stmt = $conn->prepare("UPDATE clients SET referred_by = ? WHERE id = ? AND referred_by IS NULL");
            $stmt->bind_param("ss", $marketer_id, $client_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception("That client is already credited to a marketer.");
            }
            $stmt->close();
            log_activity("Credited client to marketer", ["client" => $client_id, "marketer" => $marketer_id]);
            set_flash_message('success', "Client credited. Projects they kick off from now on earn the marketer a commission.");
            $tab = 'marketers';
        }

        elseif ($action === 'confirm_referral') {
            $ref_id = $_POST['referral_id'] ?? '';
            $course_id = $_POST['course_id'] ?? '';
            $amount = round(floatval($_POST['amount'] ?? 0), 2);
            if ($amount < 0) {
                throw new Exception("Commission cannot be negative.");
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT r.*, m.student_commission_rate FROM marketer_student_referrals r JOIN users_marketer m ON m.id = r.marketer_id
                                        WHERE r.id = ? AND r.status = 'PENDING' FOR UPDATE");
                $stmt->bind_param("s", $ref_id);
                $stmt->execute();
                $ref = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$ref) {
                    throw new Exception("Referral not found or already reviewed.");
                }

                $base = 0.00;
                $rate = null;
                if ($course_id !== '') {
                    $stmt = $conn->prepare("SELECT title, price FROM courses WHERE id = ?");
                    $stmt->bind_param("s", $course_id);
                    $stmt->execute();
                    $course = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$course) {
                        throw new Exception("Course not found.");
                    }
                    $base = floatval($course['price']);
                    $rate = floatval($ref['student_commission_rate']);
                } else {
                    $course_id = null;
                }

                $stmt = $conn->prepare("UPDATE marketer_student_referrals SET status = 'CONFIRMED', course_id = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->bind_param("sss", $course_id, $user['id'], $ref_id);
                $stmt->execute();
                $stmt->close();

                $c_id = generate_uuid();
                $desc = "Student referral: " . $ref['full_name'] . (isset($course) ? " (" . $course['title'] . ")" : "");
                $desc = mb_substr($desc, 0, 255);
                $stmt = $conn->prepare("INSERT INTO marketer_commissions (id, marketer_id, source, referral_id, description, base_amount, rate, amount) VALUES (?, ?, 'STUDENT', ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssddd", $c_id, $ref['marketer_id'], $ref_id, $desc, $base, $rate, $amount);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            send_notification($ref['marketer_id'], "Student referral confirmed", "{$ref['full_name']} has been confirmed. " . format_currency($amount) . " has been added to your earnings.", "FINANCE", "HIGH", "/marketer/students.php");
            log_activity("Confirmed student referral", ["email" => $ref['email'], "amount" => $amount]);
            set_flash_message('success', "Referral confirmed - " . format_currency($amount) . " commission recorded.");
        }

        elseif ($action === 'reject_referral') {
            $ref_id = $_POST['referral_id'] ?? '';
            $note = mb_substr(trim($_POST['review_note'] ?? ''), 0, 255);
            $stmt = $conn->prepare("SELECT marketer_id, full_name FROM marketer_student_referrals WHERE id = ? AND status = 'PENDING'");
            $stmt->bind_param("s", $ref_id);
            $stmt->execute();
            $ref = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$ref) {
                throw new Exception("Referral not found or already reviewed.");
            }
            $note = $note !== '' ? $note : null;
            $stmt = $conn->prepare("UPDATE marketer_student_referrals SET status = 'REJECTED', review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("sss", $note, $user['id'], $ref_id);
            $stmt->execute();
            $stmt->close();
            send_notification($ref['marketer_id'], "Student referral not confirmed", "{$ref['full_name']} could not be confirmed" . ($note ? ": $note" : "."), "CRM", "MEDIUM", "/marketer/students.php");
            set_flash_message('success', "Referral marked as not confirmed.");
        }

        elseif ($action === 'mark_paid' || $action === 'cancel_commission') {
            $c_id = $_POST['commission_id'] ?? '';
            $stmt = $conn->prepare("SELECT marketer_id, amount, description FROM marketer_commissions WHERE id = ? AND status = 'EARNED'");
            $stmt->bind_param("s", $c_id);
            $stmt->execute();
            $com = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$com) {
                throw new Exception("Commission not found or already settled.");
            }
            if ($action === 'mark_paid') {
                $payout_ref = mb_substr(trim($_POST['payout_ref'] ?? ''), 0, 100);
                $payout_ref = $payout_ref !== '' ? $payout_ref : null;
                $stmt = $conn->prepare("UPDATE marketer_commissions SET status = 'PAID', payout_ref = ?, paid_at = NOW() WHERE id = ?");
                $stmt->bind_param("ss", $payout_ref, $c_id);
                $stmt->execute();
                $stmt->close();
                send_notification($com['marketer_id'], "Commission paid", format_currency($com['amount']) . " for \"{$com['description']}\" has been paid to you.", "FINANCE", "MEDIUM", "/marketer/commissions.php");
                set_flash_message('success', format_currency($com['amount']) . " marked as paid.");
            } else {
                $stmt = $conn->prepare("UPDATE marketer_commissions SET status = 'CANCELLED' WHERE id = ?");
                $stmt->bind_param("s", $c_id);
                $stmt->execute();
                $stmt->close();
                send_notification($com['marketer_id'], "Commission cancelled", "The commission for \"{$com['description']}\" was cancelled. Contact the admin if you have questions.", "FINANCE", "MEDIUM", "/marketer/commissions.php");
                set_flash_message('success', "Commission cancelled.");
            }
            log_activity($action === 'mark_paid' ? "Paid marketer commission" : "Cancelled marketer commission", ["commission" => $c_id]);
            $tab = 'commissions';
        }

        header("Location: " . BASE_URL . "/admin/marketers.php?tab=$tab");
        exit();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// ---- Data ----
$marketers = $conn->query("SELECT m.*,
        (SELECT COUNT(*) FROM clients cl WHERE cl.referred_by = m.id) AS clients,
        (SELECT COUNT(*) FROM marketer_student_referrals r WHERE r.marketer_id = m.id AND r.status = 'CONFIRMED') AS students,
        (SELECT COALESCE(SUM(amount), 0) FROM marketer_commissions mc WHERE mc.marketer_id = m.id AND mc.status = 'EARNED') AS unpaid,
        (SELECT COALESCE(SUM(amount), 0) FROM marketer_commissions mc WHERE mc.marketer_id = m.id AND mc.status = 'PAID') AS paid
    FROM users_marketer m ORDER BY m.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Pending referrals, with whatever we know about the student on our side to help the admin decide
$pending = $conn->query("SELECT r.*, m.first_name AS m_first, m.last_name AS m_last, m.student_commission_rate,
        c.title AS course_title, us.created_at AS student_since,
        (SELECT GROUP_CONCAT(co.title SEPARATOR ', ') FROM students s JOIN enrollments e ON e.student_id = s.id JOIN courses co ON co.id = e.course_id WHERE s.user_id = us.id) AS enrolled_in
    FROM marketer_student_referrals r
    JOIN users_marketer m ON m.id = r.marketer_id
    LEFT JOIN courses c ON c.id = r.course_id
    LEFT JOIN users_student us ON us.email = r.email
    WHERE r.status = 'PENDING' ORDER BY r.created_at ASC")->fetch_all(MYSQLI_ASSOC);

$reviewed = $conn->query("SELECT r.*, m.first_name AS m_first, m.last_name AS m_last, mc.amount AS commission
    FROM marketer_student_referrals r JOIN users_marketer m ON m.id = r.marketer_id
    LEFT JOIN marketer_commissions mc ON mc.referral_id = r.id
    WHERE r.status <> 'PENDING' ORDER BY r.reviewed_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

$commissions = $conn->query("SELECT mc.*, m.first_name AS m_first, m.last_name AS m_last, pr.project_id AS project_ref
    FROM marketer_commissions mc JOIN users_marketer m ON m.id = mc.marketer_id
    LEFT JOIN projects pr ON pr.id = mc.project_id
    ORDER BY FIELD(mc.status, 'EARNED', 'PAID', 'CANCELLED'), mc.created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

$courses = $conn->query("SELECT id, title, price FROM courses WHERE status = 'ACTIVE' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
$unlinked_clients = $conn->query("SELECT id, company_name, contact_person FROM clients WHERE referred_by IS NULL ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

$unpaid_total = array_sum(array_map(fn($c) => $c['status'] === 'EARNED' ? $c['amount'] : 0, $commissions));
$paid_total = array_sum(array_column($marketers, 'paid'));

function rate_label($r)
{
    return rtrim(rtrim((string) $r, '0'), '.') . '%';
}

include __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="mb-4 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium"><?php echo sanitize($err); ?></div>
<?php endforeach; ?>

<!-- Metrics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Marketers</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo count($marketers); ?></h3>
    </div>
    <a href="?tab=referrals" class="glass-card p-6 rounded-2xl block">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Referrals to confirm</span>
        <h3 class="text-3xl font-bold mt-1 <?php echo $pending ? 'text-amber-600' : 'text-slate-900'; ?>"><?php echo count($pending); ?></h3>
    </a>
    <a href="?tab=commissions" class="glass-card p-6 rounded-2xl block">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Commissions owed</span>
        <h3 class="text-3xl font-bold text-[#B42318] mt-1"><?php echo format_currency($unpaid_total); ?></h3>
    </a>
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Paid to marketers</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo format_currency($paid_total); ?></h3>
    </div>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-6 border-b border-slate-200">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="?tab=<?php echo $k; ?>" class="px-4 py-2 text-xs font-bold border-b-2 -mb-px <?php echo $tab === $k ? 'border-[#17264A] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-700'; ?>">
            <?php echo $label; ?><?php if ($k === 'referrals' && $pending): ?> <span class="ml-1 px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-[9px]"><?php echo count($pending); ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'referrals'): ?>
    <div class="space-y-4">
        <?php if (empty($pending)): ?>
            <div class="glass-card p-12 text-center rounded-2xl text-sm text-slate-400">No student referrals waiting for confirmation.</div>
        <?php endif; ?>
        <?php foreach ($pending as $r):
            $default_course = $r['course_id'] ?? '';
            $default_price = 0;
            foreach ($courses as $c) {
                if ($c['id'] === $default_course) $default_price = $c['price'];
            }
            $default_amount = round($default_price * $r['student_commission_rate'] / 100, 2); ?>
            <div class="glass-card p-6 rounded-2xl">
                <div class="flex flex-wrap justify-between items-start gap-3">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900"><?php echo sanitize($r['full_name']); ?></h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            <?php echo sanitize($r['email']); ?><?php echo $r['phone'] ? ' &bull; ' . sanitize($r['phone']) : ''; ?>
                            &bull; referred by <b class="text-slate-600"><?php echo sanitize($r['m_first'] . ' ' . $r['m_last']); ?></b> on <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                        </p>
                        <?php if ($r['course_title']): ?><p class="text-[11px] text-slate-500 mt-1">Interested in: <?php echo sanitize($r['course_title']); ?></p><?php endif; ?>
                        <?php if ($r['note']): ?><p class="text-[11px] text-slate-500 mt-1">Note: <?php echo sanitize($r['note']); ?></p><?php endif; ?>
                    </div>
                    <div class="text-right text-[11px]">
                        <?php if (!$r['student_since']): ?>
                            <span class="px-2.5 py-1 rounded font-bold uppercase text-[10px] bg-slate-100 text-slate-500">No student account with this email yet</span>
                        <?php elseif (strtotime($r['student_since']) < strtotime($r['created_at'])): ?>
                            <span class="px-2.5 py-1 rounded font-bold uppercase text-[10px] bg-amber-500/10 text-amber-600">Was already a student before this referral</span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded font-bold uppercase text-[10px] bg-emerald-500/10 text-emerald-600">Registered <?php echo date('M d, Y', strtotime($r['student_since'])); ?></span>
                        <?php endif; ?>
                        <?php if ($r['enrolled_in']): ?><p class="text-slate-500 mt-1.5">Enrolled: <?php echo sanitize($r['enrolled_in']); ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 flex flex-col lg:flex-row gap-3 lg:items-end">
                    <form method="POST" action="marketers.php?tab=referrals" class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2 items-end confirm-form" data-rate="<?php echo $r['student_commission_rate']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="confirm_referral">
                        <input type="hidden" name="referral_id" value="<?php echo $r['id']; ?>">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Course enrolled</label>
                            <select name="course_id" class="course-sel w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                                <option value="" data-price="0">No course / flat amount</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-price="<?php echo $c['price']; ?>" <?php echo $default_course === $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['title']); ?> (<?php echo format_currency($c['price']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Commission (₦) &bull; <?php echo rate_label($r['student_commission_rate']); ?> of fee</label>
                            <input type="number" name="amount" step="0.01" min="0" required value="<?php echo $default_amount; ?>" class="amount-in w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold">Confirm & record commission</button>
                    </form>
                    <form method="POST" action="marketers.php?tab=referrals" class="flex gap-2 items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="reject_referral">
                        <input type="hidden" name="referral_id" value="<?php echo $r['id']; ?>">
                        <input type="text" name="review_note" maxlength="255" placeholder="Reason (optional)" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        <button type="submit" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700 whitespace-nowrap" onclick="return confirm('Mark this referral as not confirmed?')">Reject</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($reviewed): ?>
            <div class="glass-card p-6 rounded-2xl">
                <h4 class="text-sm font-bold text-slate-800 mb-3">Recently reviewed</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <tbody>
                            <?php foreach ($reviewed as $r): ?>
                                <tr class="border-b border-slate-50">
                                    <td class="py-2 pr-4"><b class="text-slate-800"><?php echo sanitize($r['full_name']); ?></b> <span class="text-slate-400"><?php echo sanitize($r['email']); ?></span></td>
                                    <td class="py-2 pr-4 text-slate-500"><?php echo sanitize($r['m_first'] . ' ' . $r['m_last']); ?></td>
                                    <td class="py-2 pr-4"><span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded-full uppercase <?php echo $r['status'] === 'CONFIRMED' ? 'badge-success' : 'badge-info'; ?>"><?php echo $r['status']; ?></span></td>
                                    <td class="py-2 pr-4 text-right font-bold"><?php echo $r['commission'] !== null ? format_currency($r['commission']) : '-'; ?></td>
                                    <td class="py-2 text-right text-slate-400"><?php echo $r['reviewed_at'] ? date('M d, Y', strtotime($r['reviewed_at'])) : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'commissions'): ?>
    <div class="glass-card p-6 rounded-2xl">
        <?php if (empty($commissions)): ?>
            <div class="text-center py-8 text-sm text-slate-400">No commissions recorded yet. They appear when a referred client's project kicks off or a student referral is confirmed.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                            <th class="py-2 pr-4 font-semibold">Date</th>
                            <th class="py-2 pr-4 font-semibold">Marketer</th>
                            <th class="py-2 pr-4 font-semibold">For</th>
                            <th class="py-2 pr-4 font-semibold text-right">Amount</th>
                            <th class="py-2 font-semibold">Status / payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commissions as $c): ?>
                            <tr class="border-b border-slate-50 align-top <?php echo $c['status'] === 'CANCELLED' ? 'opacity-60' : ''; ?>">
                                <td class="py-3 pr-4 text-slate-500 whitespace-nowrap"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                <td class="py-3 pr-4 font-bold text-slate-800 whitespace-nowrap"><?php echo sanitize($c['m_first'] . ' ' . $c['m_last']); ?></td>
                                <td class="py-3 pr-4">
                                    <?php echo sanitize($c['description']); ?>
                                    <span class="block text-[10px] text-slate-400">
                                        <?php echo $c['project_ref'] ? sanitize($c['project_ref']) . ' &bull; ' : ''; ?>
                                        <?php echo $c['base_amount'] > 0 ? format_currency($c['base_amount']) . ($c['rate'] !== null ? ' @ ' . rate_label($c['rate']) : '') : 'Flat amount'; ?>
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-right font-bold text-slate-900 whitespace-nowrap"><?php echo format_currency($c['amount']); ?></td>
                                <td class="py-3">
                                    <?php if ($c['status'] === 'EARNED'): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="marketers.php?tab=commissions" class="flex gap-1.5">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="commission_id" value="<?php echo $c['id']; ?>">
                                                <input type="text" name="payout_ref" maxlength="100" placeholder="Transfer ref (optional)" class="w-36 px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-[11px] outline-none">
                                                <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[11px] font-bold whitespace-nowrap">Mark paid</button>
                                            </form>
                                            <form method="POST" action="marketers.php?tab=commissions">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="cancel_commission">
                                                <input type="hidden" name="commission_id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="px-3 py-1 border border-slate-300 rounded-lg text-[11px] font-bold text-slate-600" onclick="return confirm('Cancel this commission? The marketer will be notified.')">Cancel</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[9px] font-mono font-bold px-2.5 py-1 rounded-full uppercase <?php echo $c['status'] === 'PAID' ? 'badge-success' : 'badge-info'; ?>"><?php echo $c['status']; ?></span>
                                        <?php if ($c['status'] === 'PAID'): ?>
                                            <span class="block text-[10px] text-slate-400 mt-1"><?php echo date('M d, Y', strtotime($c['paid_at'])); ?><?php echo $c['payout_ref'] ? ' &bull; ' . sanitize($c['payout_ref']) : ''; ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
            <?php if (empty($marketers)): ?>
                <div class="glass-card p-12 text-center rounded-2xl text-sm text-slate-400">No marketers yet. Create the first account on the right.</div>
            <?php endif; ?>
            <?php foreach ($marketers as $m): ?>
                <div class="glass-card p-6 rounded-2xl">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900"><?php echo sanitize($m['first_name'] . ' ' . $m['last_name']); ?>
                                <?php if ($m['status'] !== 'ACTIVE'): ?><span class="ml-1 text-[9px] font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-500"><?php echo $m['status']; ?></span><?php endif; ?>
                            </h4>
                            <p class="text-[11px] text-slate-400 mt-0.5"><?php echo sanitize($m['email']); ?><?php echo $m['phone'] ? ' &bull; ' . sanitize($m['phone']) : ''; ?></p>
                            <p class="text-[10px] font-mono text-slate-500 mt-1 break-all"><?php echo $m['referral_code'] ? sanitize(marketer_referral_link($m['referral_code'])) : 'Referral code is generated on first sign-in'; ?></p>
                        </div>
                        <div class="grid grid-cols-4 gap-4 text-center">
                            <div><p class="text-lg font-bold text-slate-900"><?php echo $m['clients']; ?></p><p class="text-[9px] uppercase text-slate-400">Clients</p></div>
                            <div><p class="text-lg font-bold text-slate-900"><?php echo $m['students']; ?></p><p class="text-[9px] uppercase text-slate-400">Students</p></div>
                            <div><p class="text-sm font-bold text-[#B42318] mt-1"><?php echo format_currency($m['unpaid']); ?></p><p class="text-[9px] uppercase text-slate-400">Owed</p></div>
                            <div><p class="text-sm font-bold text-slate-900 mt-1"><?php echo format_currency($m['paid']); ?></p><p class="text-[9px] uppercase text-slate-400">Paid</p></div>
                        </div>
                    </div>
                    <form method="POST" action="marketers.php?tab=marketers" class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_marketer">
                        <input type="hidden" name="marketer_id" value="<?php echo $m['id']; ?>">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Project rate %</label>
                            <input type="number" name="project_commission_rate" step="0.01" min="0" max="100" value="<?php echo $m['project_commission_rate']; ?>" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Student rate %</label>
                            <input type="number" name="student_commission_rate" step="0.01" min="0" max="100" value="<?php echo $m['student_commission_rate']; ?>" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                                <?php foreach (['ACTIVE', 'INACTIVE', 'SUSPENDED'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $m['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">Save</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="space-y-6">
            <form method="POST" action="marketers.php?tab=marketers" class="glass-card p-6 rounded-2xl space-y-3">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create_marketer">
                <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2"><i data-lucide="user-plus" class="w-4 h-4 text-indigo-500"></i>New marketer</h4>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="first_name" required placeholder="First name" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    <input type="text" name="last_name" required placeholder="Last name" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                </div>
                <input type="email" name="email" required placeholder="Email" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                <input type="text" name="phone" placeholder="Phone (optional)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                <input type="text" name="password" required minlength="6" placeholder="Initial password (min. 6)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Project rate %</label>
                        <input type="number" name="project_commission_rate" step="0.01" min="0" max="100" value="10" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Student rate %</label>
                        <input type="number" name="student_commission_rate" step="0.01" min="0" max="100" value="10" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    </div>
                </div>
                <p class="text-[10px] text-slate-400">Project rate: % of the accepted quote, earned at kickoff. Student rate: % of the course fee, suggested when you confirm a referral.</p>
                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold">Create marketer</button>
            </form>

            <?php if ($marketers && $unlinked_clients): ?>
                <form method="POST" action="marketers.php?tab=marketers" class="glass-card p-6 rounded-2xl space-y-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="link_client">
                    <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2"><i data-lucide="link" class="w-4 h-4 text-indigo-500"></i>Credit a client to a marketer</h4>
                    <p class="text-[10px] text-slate-400">For clients a marketer brought in who registered without their link.</p>
                    <select name="client_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        <?php foreach ($unlinked_clients as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>"><?php echo sanitize($cl['company_name'] ?: $cl['contact_person']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="marketer_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        <?php foreach ($marketers as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo sanitize($m['first_name'] . ' ' . $m['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">Credit client</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
    // Suggest the commission from the chosen course's fee and the marketer's student rate
    document.querySelectorAll('.confirm-form').forEach(form => {
        const sel = form.querySelector('.course-sel');
        const amount = form.querySelector('.amount-in');
        sel.addEventListener('change', () => {
            const price = parseFloat(sel.selectedOptions[0].dataset.price || '0');
            amount.value = (price * parseFloat(form.dataset.rate) / 100).toFixed(2);
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
