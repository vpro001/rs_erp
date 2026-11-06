<?php
// admin/certification.php
// Student records, payment certification and certificate authorization.
// Shared with Head Admin staff via staff/certification.php.
//   1. A student's balance reaches zero  -> certify full payment
//   2. The enrollment's end date passes  -> authorize the certificate (appears on the student portal)
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/certificates.php';
require_permission('certify');

$user = current_user();
$page_title = "Payment Certification & Certificates";
$errors = [];

$views = [
    'awaiting' => 'Awaiting payment certification',
    'ready' => 'Ready for certificate',
    'in_progress' => 'Certified, course running',
    'balance' => 'Outstanding balance',
    'issued' => 'Certificates issued',
    'all' => 'All enrollments',
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'awaiting';
$f_q = trim($_GET['q'] ?? '');
$f_course = sanitize($_GET['course'] ?? '');
$back = 'certification.php?' . http_build_query(array_filter(['view' => $view, 'q' => $f_q, 'course' => $f_course]));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'];
    $enrollment_id = sanitize($_POST['enrollment_id'] ?? '');
    try {
        if ($action === 'certify_payment') {
            $e = certify_payment($conn, $enrollment_id);
            log_activity("Certified full payment", ["enrollment_id" => $enrollment_id]);
            set_flash_message('success', "Full payment certified for " . sanitize($e['course_title']) . ".");
        } elseif ($action === 'revoke_payment') {
            revoke_payment_certification($conn, $enrollment_id);
            log_activity("Revoked payment certification", ["enrollment_id" => $enrollment_id]);
            set_flash_message('success', "Payment certification revoked.");
        } elseif ($action === 'authorize_certificate') {
            $no = authorize_certificate($conn, $enrollment_id);
            log_activity("Authorized certificate", ["enrollment_id" => $enrollment_id, "certificate" => $no]);
            set_flash_message('success', "Certificate $no issued. It is now available on the student's portal.");
        } elseif ($action === 'authorize_all') {
            $ids = array_map('sanitize', (array) ($_POST['enrollment_ids'] ?? []));
            $done = 0;
            $skipped = [];
            foreach ($ids as $id) {
                try {
                    authorize_certificate($conn, $id);
                    $done++;
                } catch (Exception $ex) {
                    $skipped[] = $ex->getMessage();
                }
            }
            log_activity("Bulk authorized certificates", ["count" => $done]);
            set_flash_message($done ? 'success' : 'warning', "$done certificate(s) issued." . ($skipped ? ' ' . count($skipped) . ' skipped.' : ''));
        } else {
            throw new Exception("Unknown action.");
        }
        header("Location: $back");
        exit();
    } catch (Exception $ex) {
        $errors[] = $ex->getMessage();
    }
}

$base = "FROM enrollments e
         JOIN students s ON s.id = e.student_id
         JOIN users_student u ON u.id = s.user_id
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN classes b ON b.id = e.batch_id
         LEFT JOIN certificates cert ON cert.id = (SELECT c2.id FROM certificates c2 WHERE c2.student_id = e.student_id AND c2.course_id = e.course_id AND c2.is_valid = 1 ORDER BY c2.issue_date DESC LIMIT 1)";
$conds = [
    'awaiting' => "e.payment_certified_at IS NULL AND e.amount_due <= 0.005 AND e.status NOT IN ('DROPPED','SUSPENDED') AND cert.id IS NULL",
    'ready' => "e.payment_certified_at IS NOT NULL AND cert.id IS NULL AND e.end_date IS NOT NULL AND e.end_date <= CURDATE() AND e.status NOT IN ('DROPPED','SUSPENDED')",
    'in_progress' => "e.payment_certified_at IS NOT NULL AND cert.id IS NULL AND (e.end_date IS NULL OR e.end_date > CURDATE())",
    'balance' => "e.amount_due > 0.005 AND e.status NOT IN ('DROPPED')",
    'issued' => "cert.id IS NOT NULL",
    'all' => "1=1",
];
$counts = [];
foreach ($conds as $k => $cond) {
    $counts[$k] = (int) $conn->query("SELECT COUNT(*) AS n $base WHERE $cond")->fetch_assoc()['n'];
}

$sql = "SELECT e.*, COALESCE(e.agreed_fee, e.total_paid + e.amount_due) AS fee, c.title AS course_title, c.price, c.duration,
               b.name AS batch_name, s.student_id AS reg_no, s.lecture_mode, u.first_name, u.last_name, u.email, u.phone, u.status AS account_status,
               cert.id AS cert_id, cert.certificate_id AS cert_no, cert.issue_date AS cert_date,
               COALESCE(CONCAT(ua.first_name, ' ', ua.last_name), CONCAT(us.first_name, ' ', us.last_name)) AS certified_by_name
        $base
        LEFT JOIN users_admin ua ON ua.id = e.payment_certified_by
        LEFT JOIN users_staff us ON us.id = e.payment_certified_by
        WHERE {$conds[$view]}";
$types = '';
$params = [];
if ($f_course !== '') { $sql .= " AND e.course_id = ?"; $types .= 's'; $params[] = $f_course; }
if ($f_q !== '') {
    $sql .= " AND (s.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = '%' . $f_q . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
$sql .= " ORDER BY " . ($view === 'ready' ? 'e.end_date ASC' : 'e.enrollment_date DESC') . " LIMIT 300";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$history = [];
foreach ($rows as $r) {
    $stmt = $conn->prepare("SELECT amount, method, description, paid_at FROM payments WHERE student_id = ? AND course_id = ? AND type = 'COURSE_FEE' AND status = 'COMPLETED' ORDER BY paid_at DESC");
    $stmt->bind_param("ss", $r['student_id'], $r['course_id']);
    $stmt->execute();
    $history[$r['id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$courses = $conn->query("SELECT id, title FROM courses ORDER BY title")->fetch_all(MYSQLI_ASSOC);
$ready_ids = array_column(array_filter($rows, fn($r) => certificate_blocker($r) === null), 'id');

$input = "px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$csrf = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <?php foreach ($views as $k => $name): ?>
        <a href="certification.php?view=<?php echo $k; ?>" class="bg-white border rounded-2xl p-4 shadow-sm hover:border-slate-300 <?php echo $view === $k ? 'border-blue-400 ring-1 ring-blue-200' : 'border-slate-200'; ?>">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block leading-tight"><?php echo $name; ?></span>
            <span class="text-2xl font-bold <?php echo $k === 'ready' && $counts[$k] ? 'text-emerald-600' : 'text-slate-900'; ?>"><?php echo $counts[$k]; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4 flex flex-wrap gap-2 items-center justify-between">
    <form method="GET" class="flex flex-wrap gap-2 flex-1">
        <input type="hidden" name="view" value="<?php echo $view; ?>">
        <input type="text" name="q" value="<?php echo sanitize($f_q); ?>" placeholder="Search name, reg no, email, phone" class="<?php echo $input; ?> flex-1 min-w-[12rem]">
        <select name="course" class="<?php echo $input; ?>">
            <option value="">All courses</option>
            <?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $f_course === $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['title']); ?></option><?php endforeach; ?>
        </select>
        <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold">Filter</button>
    </form>
    <?php if ($view === 'ready' && $ready_ids): ?>
        <form method="POST" action="<?php echo sanitize($back); ?>" onsubmit="return confirm('Issue certificates for all <?php echo count($ready_ids); ?> listed students?');">
            <?php echo $csrf; ?>
            <input type="hidden" name="action" value="authorize_all">
            <?php foreach ($ready_ids as $id): ?><input type="hidden" name="enrollment_ids[]" value="<?php echo $id; ?>"><?php endforeach; ?>
            <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold">Authorize all <?php echo count($ready_ids); ?> certificates</button>
        </form>
    <?php endif; ?>
</div>

<p class="text-[11px] text-slate-500 mb-4">
    <?php echo [
        'awaiting' => 'These students have no outstanding balance. Check their payment history, then certify that they have paid in full.',
        'ready' => 'Full payment is certified and the course end date has passed. Authorizing issues the certificate on the student portal.',
        'in_progress' => 'Payment is certified; the certificate can be authorized once the course end date passes.',
        'balance' => 'These students still owe part of their agreed fee. Payments are recorded by Customer Service under Enrollments.',
        'issued' => 'Certificates already issued.',
        'all' => 'Every enrollment.',
    ][$view]; ?>
</p>

<?php if (!$rows): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-xs text-slate-400">Nothing here.</div>
<?php endif; ?>

<div class="space-y-3">
<?php foreach ($rows as $r):
    $pay_block = payment_certify_blocker($r);
    $cert_block = certificate_blocker($r);
    $ended = $r['end_date'] && strtotime($r['end_date']) <= strtotime(date('Y-m-d'));
    $days_left = $r['end_date'] ? (int) round((strtotime($r['end_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
                <span class="text-sm font-bold text-slate-900 block"><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></span>
                <span class="font-mono text-[11px] text-slate-600 block"><?php echo sanitize($r['reg_no']); ?></span>
                <span class="text-[11px] text-slate-500 block"><?php echo sanitize($r['email']); ?><?php echo $r['phone'] ? ' &bull; ' . sanitize($r['phone']) : ''; ?></span>
                <span class="text-[10px] text-slate-400 block">Account <?php echo strtolower($r['account_status']); ?> &bull; <?php echo ucfirst(strtolower($r['lecture_mode'] ?? 'online')); ?></span>
            </div>
            <div class="md:col-span-4">
                <span class="text-xs font-semibold text-slate-800 block"><?php echo sanitize($r['course_title']); ?></span>
                <span class="text-[11px] text-slate-500 block"><?php echo $r['batch_name'] ? sanitize($r['batch_name']) : 'No batch'; ?> &bull; <?php echo sanitize($r['status']); ?></span>
                <span class="text-[11px] font-semibold text-slate-700 block mt-1">
                    Start <?php echo $r['start_date'] ? date('d M Y', strtotime($r['start_date'])) : '—'; ?> &rarr; End <?php echo $r['end_date'] ? date('d M Y', strtotime($r['end_date'])) : '—'; ?>
                </span>
                <?php if ($days_left !== null): ?>
                    <span class="text-[10px] font-bold <?php echo $ended ? 'text-emerald-600' : 'text-slate-400'; ?>"><?php echo $ended ? 'Course ended' : $days_left . ' day(s) left'; ?></span>
                <?php endif; ?>
            </div>
            <div class="md:col-span-4 text-left md:text-right space-y-0.5">
                <span class="text-[11px] text-slate-500 block">Agreed fee <b class="text-slate-800"><?php echo format_currency($r['fee']); ?></b><?php echo abs($r['fee'] - $r['price']) > 0.005 ? ' <span class="text-indigo-600">(list ' . format_currency($r['price']) . ')</span>' : ''; ?></span>
                <span class="text-[11px] text-slate-500 block">Paid <b class="text-slate-800"><?php echo format_currency($r['total_paid']); ?></b></span>
                <span class="text-[11px] block font-bold <?php echo $r['amount_due'] > 0.005 ? 'text-amber-600' : 'text-emerald-600'; ?>">Balance <?php echo format_currency($r['amount_due']); ?></span>
                <?php if ($r['payment_certified_at']): ?>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold">Paid in full, certified <?php echo date('d M Y', strtotime($r['payment_certified_at'])); ?><?php echo $r['certified_by_name'] ? ' by ' . sanitize($r['certified_by_name']) : ''; ?></span>
                <?php endif; ?>
                <?php if ($r['cert_id']): ?>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded bg-indigo-50 border border-indigo-200 text-indigo-700 text-[10px] font-bold">Certificate <?php echo sanitize($r['cert_no']); ?> &bull; <?php echo date('d M Y', strtotime($r['cert_date'])); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-slate-100 text-[11px] font-bold">
            <?php if ($pay_block === null): ?>
                <form method="POST" action="<?php echo sanitize($back); ?>" onsubmit="return confirm('Certify that this student has paid <?php echo format_currency($r['fee']); ?> in full?');">
                    <?php echo $csrf; ?>
                    <input type="hidden" name="action" value="certify_payment">
                    <input type="hidden" name="enrollment_id" value="<?php echo $r['id']; ?>">
                    <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg">Certify full payment</button>
                </form>
            <?php elseif (!$r['payment_certified_at']): ?>
                <span class="px-3 py-1.5 bg-slate-100 text-slate-400 rounded-lg" title="<?php echo sanitize($pay_block); ?>">Certify payment: <?php echo sanitize($pay_block); ?></span>
            <?php endif; ?>

            <?php if ($r['payment_certified_at'] && !$r['cert_id']): ?>
                <?php if ($cert_block === null): ?>
                    <form method="POST" action="<?php echo sanitize($back); ?>" onsubmit="return confirm('Issue the certificate for this student now?');">
                        <?php echo $csrf; ?>
                        <input type="hidden" name="action" value="authorize_certificate">
                        <input type="hidden" name="enrollment_id" value="<?php echo $r['id']; ?>">
                        <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg">Authorize certificate</button>
                    </form>
                <?php else: ?>
                    <span class="px-3 py-1.5 bg-slate-100 text-slate-400 rounded-lg">Certificate: <?php echo sanitize($cert_block); ?></span>
                <?php endif; ?>
                <form method="POST" action="<?php echo sanitize($back); ?>" onsubmit="return confirm('Revoke the payment certification?');">
                    <?php echo $csrf; ?>
                    <input type="hidden" name="action" value="revoke_payment">
                    <input type="hidden" name="enrollment_id" value="<?php echo $r['id']; ?>">
                    <button class="px-3 py-1.5 text-red-600 hover:underline">Revoke certification</button>
                </form>
            <?php endif; ?>

            <?php if ($r['cert_id']): ?>
                <a href="<?php echo BASE_URL; ?>/certificate.php?id=<?php echo $r['cert_id']; ?>" class="px-3 py-1.5 border border-indigo-200 text-indigo-700 rounded-lg hover:bg-indigo-50">Download certificate</a>
            <?php endif; ?>

            <span class="flex-1"></span>
            <a href="<?php echo BASE_URL; ?>/admission_letter.php?enrollment_id=<?php echo $r['id']; ?>" target="_blank" class="text-blue-600 hover:underline">Admission letter</a>
            <a href="<?php echo BASE_URL; ?>/admission_letter.php?enrollment_id=<?php echo $r['id']; ?>&download=pdf" class="text-blue-600 hover:underline">PDF</a>
        </div>

        <details class="mt-2">
            <summary class="text-[11px] font-bold text-slate-500 cursor-pointer">Payment history (<?php echo count($history[$r['id']]); ?>)</summary>
            <?php if (!$history[$r['id']]): ?>
                <p class="text-[11px] text-slate-400 mt-2">No payments recorded.</p>
            <?php else: ?>
                <ul class="mt-2 space-y-1 text-[11px] text-slate-600">
                    <?php foreach ($history[$r['id']] as $p): ?>
                        <li><?php echo $p['paid_at'] ? date('d M Y', strtotime($p['paid_at'])) : '-'; ?> &mdash; <b><?php echo format_currency($p['amount']); ?></b> (<?php echo str_replace('_', ' ', $p['method']); ?>)<?php echo $p['description'] ? ' &mdash; ' . sanitize($p['description']) : ''; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </details>
    </div>
<?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
