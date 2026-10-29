<?php
// admin/enrollments.php
// Register students to courses and manage each enrollment: start/end dates, batch,
// status, payment plan (full / installment), installment schedule and payments.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/enrollment_helpers.php';

$user = current_user();
$page_title = "Course Enrollments";

$errors = [];
$success_msg = "";

const ENROLLMENT_STATUSES = ['ACTIVE', 'COMPLETED', 'DROPPED', 'SUSPENDED'];
const PAYMENT_METHODS = ['CASH', 'BANK_TRANSFER', 'USSD', 'MOBILE_MONEY', 'CREDIT_CARD', 'PAYSTACK'];

function load_enrollment($conn, $id)
{
    $stmt = $conn->prepare("SELECT e.*, c.price, c.title AS course_title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception("Enrollment not found.");
    return $row;
}

/** Split $price into $count monthly installments starting on $start (last one absorbs rounding). */
function create_installment_schedule($conn, $enrollment_id, $price, $count, $start)
{
    $count = max(1, $count);
    $each = round($price / $count, 2);
    for ($i = 1; $i <= $count; $i++) {
        $amount = $i === $count ? round($price - $each * ($count - 1), 2) : $each;
        $due = date('Y-m-d 23:59:59', strtotime($start . " +" . ($i - 1) . " month"));
        $id = generate_uuid();
        $stmt = $conn->prepare("INSERT INTO installment_payments (id, enrollment_id, installment_number, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'PENDING')");
        $stmt->bind_param("ssids", $id, $enrollment_id, $i, $amount, $due);
        $stmt->execute();
        $stmt->close();
    }
}

/** Mark installments PAID in order as far as the enrollment's total_paid covers them. */
function sync_installments($conn, $enrollment_id)
{
    $stmt = $conn->prepare("SELECT total_paid FROM enrollments WHERE id = ?");
    $stmt->bind_param("s", $enrollment_id);
    $stmt->execute();
    $paid = floatval($stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, amount, due_date FROM installment_payments WHERE enrollment_id = ? AND status <> 'CANCELLED' ORDER BY installment_number");
    $stmt->bind_param("s", $enrollment_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cum = 0.0;
    foreach ($rows as $r) {
        $cum += floatval($r['amount']);
        if ($paid + 0.005 >= $cum) {
            $stmt = $conn->prepare("UPDATE installment_payments SET status = 'PAID', paid_date = COALESCE(paid_date, NOW()) WHERE id = ?");
        } elseif (strtotime($r['due_date']) < time()) {
            $stmt = $conn->prepare("UPDATE installment_payments SET status = 'OVERDUE', paid_date = NULL WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE installment_payments SET status = 'PENDING', paid_date = NULL WHERE id = ?");
        }
        $stmt->bind_param("s", $r['id']);
        $stmt->execute();
        $stmt->close();
    }
}

/** Record a completed payment: payments row, income ledger row and enrollment totals. */
function record_enrollment_payment($conn, $enr, $amount, $method, $note)
{
    $payment_id = generate_uuid();
    $trans_id = 'TXN-' . mt_rand(1000000, 9999999);
    $type = 'COURSE_FEE';
    $desc = $note !== '' ? $note : ($enr['payment_type'] === 'INSTALLMENT' ? 'Installment payment' : 'Course fee payment');
    $stmt = $conn->prepare("INSERT INTO payments (id, transaction_id, type, amount, method, status, student_id, course_id, description, paid_at)
                            VALUES (?, ?, ?, ?, ?, 'COMPLETED', ?, ?, ?, NOW())");
    $stmt->bind_param("sssdssss", $payment_id, $trans_id, $type, $amount, $method, $enr['student_id'], $enr['course_id'], $desc);
    $stmt->execute();
    $stmt->close();

    $income_id = generate_uuid();
    $stmt = $conn->prepare("INSERT INTO income_records (id, source, amount, description, category) VALUES (?, 'TRAINING', ?, ?, 'LMS Course Fee')");
    $stmt->bind_param("sds", $income_id, $amount, $desc);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE enrollments SET total_paid = total_paid + ?, amount_due = GREATEST(amount_due - ?, 0) WHERE id = ?");
    $stmt->bind_param("dds", $amount, $amount, $enr['id']);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'];

    try {
        $conn->begin_transaction();

        if ($action === 'register') {
            $student_pk = sanitize($_POST['student_pk'] ?? '');
            $course_id = sanitize($_POST['course_id'] ?? '');
            $payment_type = ($_POST['payment_type'] ?? '') === 'INSTALLMENT' ? 'INSTALLMENT' : 'FULL';
            $initial = max(0, floatval($_POST['initial_payment'] ?? 0));
            $method = in_array($_POST['method'] ?? '', PAYMENT_METHODS, true) ? $_POST['method'] : 'CASH';

            $stmt = $conn->prepare("SELECT 1 FROM students WHERE id = ?");
            $stmt->bind_param("s", $student_pk);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) throw new Exception("Please select a student.");
            $stmt->close();

            $stmt = $conn->prepare("SELECT price, allow_installment, installment_count, status FROM courses WHERE id = ?");
            $stmt->bind_param("s", $course_id);
            $stmt->execute();
            $course = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$course) throw new Exception("Please select a course.");
            if ($course['status'] !== 'ACTIVE') throw new Exception("This course is not active.");
            if ($payment_type === 'INSTALLMENT' && !$course['allow_installment']) throw new Exception("This course does not allow installment payments.");

            $price = floatval($course['price']);
            if ($initial > $price + 0.005) throw new Exception("Initial payment cannot exceed the course price.");
            $count = max(1, intval($_POST['installment_count'] ?? 0) ?: intval($course['installment_count']) ?: 2);

            [$batch_id, $start, $end] = resolve_enrollment_schedule($conn, $course_id);

            $id = generate_uuid();
            try {
                $stmt = $conn->prepare("INSERT INTO enrollments (id, student_id, course_id, enrollment_date, status, payment_type, total_paid, amount_due, batch_id, start_date, end_date) VALUES (?, ?, ?, NOW(), 'ACTIVE', ?, 0, ?, ?, ?, ?)");
                $stmt->bind_param("ssssdsss", $id, $student_pk, $course_id, $payment_type, $price, $batch_id, $start, $end);
                $stmt->execute();
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                throw $e->getCode() === 1062 ? new Exception("Student is already enrolled in this course.") : $e;
            }
            sync_batch_roster($conn, $student_pk, $course_id, $batch_id);
            if ($payment_type === 'INSTALLMENT') create_installment_schedule($conn, $id, $price, $count, $start);
            if ($initial > 0) {
                record_enrollment_payment($conn, load_enrollment($conn, $id), $initial, $method, 'Payment at registration');
                if ($payment_type === 'INSTALLMENT') sync_installments($conn, $id);
            }
            log_activity("Admin registered student to course", ["enrollment_id" => $id, "course_id" => $course_id, "student_id" => $student_pk]);
            $success_msg = "Student registered to course.";

        } elseif ($action === 'update') {
            $enr = load_enrollment($conn, sanitize($_POST['enrollment_id'] ?? ''));
            $status = in_array($_POST['status'] ?? '', ENROLLMENT_STATUSES, true) ? $_POST['status'] : $enr['status'];
            $payment_type = ($_POST['payment_type'] ?? '') === 'INSTALLMENT' ? 'INSTALLMENT' : 'FULL';
            [$batch_id, $start, $end] = resolve_enrollment_schedule($conn, $enr['course_id'], $enr['id']);

            $stmt = $conn->prepare("UPDATE enrollments SET status = ?, payment_type = ?, batch_id = ?, start_date = ?, end_date = ? WHERE id = ?");
            $stmt->bind_param("ssssss", $status, $payment_type, $batch_id, $start, $end, $enr['id']);
            $stmt->execute();
            $stmt->close();
            sync_batch_roster($conn, $enr['student_id'], $enr['course_id'], $batch_id);

            if ($payment_type !== $enr['payment_type']) {
                if ($payment_type === 'INSTALLMENT') {
                    $stmt = $conn->prepare("SELECT allow_installment, installment_count FROM courses WHERE id = ?");
                    $stmt->bind_param("s", $enr['course_id']);
                    $stmt->execute();
                    $course = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$course['allow_installment']) throw new Exception("This course does not allow installment payments.");
                    $stmt = $conn->prepare("DELETE FROM installment_payments WHERE enrollment_id = ?");
                    $stmt->bind_param("s", $enr['id']);
                    $stmt->execute();
                    $stmt->close();
                    $count = max(1, intval($_POST['installment_count'] ?? 0) ?: intval($course['installment_count']) ?: 2);
                    // Schedule only the unpaid balance; payments already made stay on record.
                    create_installment_schedule($conn, $enr['id'], floatval($enr['total_paid']) + floatval($enr['amount_due']), $count, $start);
                    sync_installments($conn, $enr['id']);
                } else {
                    $stmt = $conn->prepare("UPDATE installment_payments SET status = 'CANCELLED' WHERE enrollment_id = ? AND status <> 'PAID'");
                    $stmt->bind_param("s", $enr['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            log_activity("Admin updated enrollment", ["enrollment_id" => $enr['id']]);
            $success_msg = "Enrollment updated.";

        } elseif ($action === 'pay') {
            $enr = load_enrollment($conn, sanitize($_POST['enrollment_id'] ?? ''));
            $amount = round(floatval($_POST['amount'] ?? 0), 2);
            $method = in_array($_POST['method'] ?? '', PAYMENT_METHODS, true) ? $_POST['method'] : 'CASH';
            if ($amount <= 0) throw new Exception("Enter a payment amount greater than zero.");
            if ($amount > floatval($enr['amount_due']) + 0.005) throw new Exception("Payment exceeds the outstanding balance of " . format_currency($enr['amount_due']) . ".");
            record_enrollment_payment($conn, $enr, $amount, $method, trim($_POST['note'] ?? ''));
            if ($enr['payment_type'] === 'INSTALLMENT') sync_installments($conn, $enr['id']);
            log_activity("Admin recorded course payment", ["enrollment_id" => $enr['id'], "amount" => $amount]);
            $success_msg = "Payment of " . format_currency($amount) . " recorded.";

        } elseif ($action === 'delete') {
            $enr = load_enrollment($conn, sanitize($_POST['enrollment_id'] ?? ''));
            if (floatval($enr['total_paid']) > 0) throw new Exception("Cannot remove an enrollment that has payments. Set its status to DROPPED instead.");
            sync_batch_roster($conn, $enr['student_id'], $enr['course_id'], null);
            $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
            $stmt->bind_param("s", $enr['id']);
            $stmt->execute();
            $stmt->close();
            log_activity("Admin removed enrollment", ["enrollment_id" => $enr['id']]);
            $success_msg = "Enrollment removed.";

        } else {
            throw new Exception("Unknown action.");
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

// Lookups
$students = $courses = $batches = [];
$res = $conn->query("SELECT s.id, s.student_id AS reg_no, u.first_name, u.last_name FROM students s JOIN users_student u ON u.id = s.user_id ORDER BY u.first_name, u.last_name");
while ($r = $res->fetch_assoc()) $students[] = $r;
$res = $conn->query("SELECT id, title, price, allow_installment, installment_count FROM courses WHERE status = 'ACTIVE' ORDER BY title");
while ($r = $res->fetch_assoc()) $courses[] = $r;
$res = $conn->query("SELECT b.id, b.name, b.course_id, b.start_date, b.end_date, b.status, b.capacity,
                            (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id) AS enrolled
                     FROM classes b ORDER BY b.start_date DESC");
while ($r = $res->fetch_assoc()) $batches[] = $r;

// Enrollment list (filters)
$f_course = sanitize($_GET['course'] ?? '');
$f_status = in_array($_GET['status'] ?? '', ENROLLMENT_STATUSES, true) ? $_GET['status'] : '';
$f_q = trim($_GET['q'] ?? '');
$sql = "SELECT e.*, c.title AS course_title, c.price, c.allow_installment, b.name AS batch_name,
               s.student_id AS reg_no, u.first_name, u.last_name
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN students s ON s.id = e.student_id
        JOIN users_student u ON u.id = s.user_id
        LEFT JOIN classes b ON b.id = e.batch_id WHERE 1=1";
$types = '';
$params = [];
if ($f_course !== '') { $sql .= " AND e.course_id = ?"; $types .= 's'; $params[] = $f_course; }
if ($f_status !== '') { $sql .= " AND e.status = ?"; $types .= 's'; $params[] = $f_status; }
if ($f_q !== '') {
    $sql .= " AND (s.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $types .= 'ssss';
    $like = '%' . $f_q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= " ORDER BY e.enrollment_date DESC LIMIT 300";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Installment schedules and payment history for the listed enrollments
$schedules = $history = [];
if ($enrollments) {
    $ids = "'" . implode("','", array_map([$conn, 'real_escape_string'], array_column($enrollments, 'id'))) . "'";
    $res = $conn->query("SELECT * FROM installment_payments WHERE enrollment_id IN ($ids) AND status <> 'CANCELLED' ORDER BY installment_number");
    while ($r = $res->fetch_assoc()) $schedules[$r['enrollment_id']][] = $r;
    foreach ($enrollments as $e) {
        $stmt = $conn->prepare("SELECT amount, method, description, paid_at FROM payments WHERE student_id = ? AND course_id = ? AND type = 'COURSE_FEE' AND status = 'COMPLETED' ORDER BY paid_at DESC");
        $stmt->bind_param("ss", $e['student_id'], $e['course_id']);
        $stmt->execute();
        $history[$e['id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";
$csrf = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
$batch_opts = function ($course_id, $selected = '') use ($batches) {
    $out = '<option value="">-- No batch (enter dates) --</option>';
    foreach ($batches as $b) {
        if ($course_id !== null && $b['course_id'] !== $course_id) continue;
        $full = $b['capacity'] && $b['enrolled'] >= $b['capacity'] && $b['id'] !== $selected;
        $out .= '<option value="' . $b['id'] . '" data-course="' . $b['course_id'] . '"'
            . ' data-start="' . ($b['start_date'] ? date('Y-m-d', strtotime($b['start_date'])) : '') . '"'
            . ' data-end="' . ($b['end_date'] ? date('Y-m-d', strtotime($b['end_date'])) : '') . '"'
            . ($b['id'] === $selected ? ' selected' : '') . ($full ? ' disabled' : '') . '>'
            . sanitize($b['name']) . ($full ? ' (full)' : '') . '</option>';
    }
    return $out;
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($success_msg): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-sm font-medium"><?php echo sanitize($success_msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Register student to course -->
    <div>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900 mb-4 border-b border-slate-100 pb-3">Register Student to Course</h4>
            <form method="POST" class="space-y-3.5" id="register-form">
                <?php echo $csrf; ?>
                <input type="hidden" name="action" value="register">
                <div>
                    <label class="<?php echo $label; ?>">Student</label>
                    <select name="student_pk" required class="<?php echo $input; ?>">
                        <option value="">Select student...</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['reg_no'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Course</label>
                    <select name="course_id" id="reg-course" required class="<?php echo $input; ?>">
                        <option value="">Select course...</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-installment="<?php echo (int) $c['allow_installment']; ?>" data-count="<?php echo (int) $c['installment_count']; ?>"><?php echo sanitize($c['title']) . ' - ' . format_currency($c['price']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Batch (optional)</label>
                    <select name="batch_id" id="reg-batch" class="<?php echo $input; ?>"><?php echo $batch_opts(null); ?></select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="<?php echo $label; ?>">Start date</label><input type="date" name="start_date" id="reg-start" required class="<?php echo $input; ?>"></div>
                    <div><label class="<?php echo $label; ?>">End date</label><input type="date" name="end_date" id="reg-end" required class="<?php echo $input; ?>"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="<?php echo $label; ?>">Payment plan</label>
                        <select name="payment_type" id="reg-plan" class="<?php echo $input; ?>">
                            <option value="FULL">Full payment</option>
                            <option value="INSTALLMENT">Installments</option>
                        </select>
                    </div>
                    <div id="reg-count-wrap" class="hidden">
                        <label class="<?php echo $label; ?>">No. of installments</label>
                        <input type="number" min="2" name="installment_count" id="reg-count" class="<?php echo $input; ?>">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="<?php echo $label; ?>">Payment received now</label><input type="number" step="0.01" min="0" name="initial_payment" value="0" class="<?php echo $input; ?>"></div>
                    <div>
                        <label class="<?php echo $label; ?>">Method</label>
                        <select name="method" class="<?php echo $input; ?>">
                            <?php foreach (PAYMENT_METHODS as $m): ?><option value="<?php echo $m; ?>"><?php echo str_replace('_', ' ', $m); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="text-[9px] text-slate-400">Installments split the course price equally, due monthly from the start date.</p>
                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-xl text-xs">Register Student</button>
            </form>
        </div>
    </div>

    <!-- Enrollments -->
    <div class="lg:col-span-2 space-y-4">
        <form method="GET" class="flex flex-wrap gap-2 bg-white border border-slate-200 rounded-2xl p-4">
            <input type="text" name="q" value="<?php echo sanitize($f_q); ?>" placeholder="Search name, reg no, email" class="<?php echo $input; ?> flex-1 min-w-[10rem]">
            <select name="course" class="<?php echo $input; ?> sm:w-48">
                <option value="">All courses</option>
                <?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $f_course === $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['title']); ?></option><?php endforeach; ?>
            </select>
            <select name="status" class="<?php echo $input; ?> sm:w-36">
                <option value="">Any status</option>
                <?php foreach (ENROLLMENT_STATUSES as $st): ?><option <?php echo $f_status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option><?php endforeach; ?>
            </select>
            <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold">Filter</button>
        </form>

        <?php if (!$enrollments): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-xs text-slate-400">No enrollments found.</div>
        <?php endif; ?>

        <?php foreach ($enrollments as $e):
            $fee = floatval($e['total_paid']) + floatval($e['amount_due']);
            $settled = floatval($e['amount_due']) <= 0.005;
        ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <span class="text-sm font-bold text-slate-900 block"><?php echo sanitize($e['first_name'] . ' ' . $e['last_name']); ?> <span class="font-mono text-[10px] text-slate-400"><?php echo sanitize($e['reg_no']); ?></span></span>
                        <span class="text-xs text-slate-600 block"><?php echo sanitize($e['course_title']); ?><?php echo $e['batch_name'] ? ' &bull; ' . sanitize($e['batch_name']) : ''; ?></span>
                        <span class="text-[10px] text-slate-500 font-semibold block mt-1">
                            <?php echo $e['start_date'] ? date('d M Y', strtotime($e['start_date'])) : 'No start date'; ?> &rarr; <?php echo $e['end_date'] ? date('d M Y', strtotime($e['end_date'])) : 'No end date'; ?>
                        </span>
                    </div>
                    <div class="text-right space-y-1">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[9px] font-bold"><?php echo $e['status']; ?></span>
                        <span class="block text-[9px] font-bold <?php echo $settled ? 'text-emerald-600' : 'text-amber-600'; ?>"><?php echo $e['payment_type']; ?> &bull; <?php echo $settled ? 'PAID IN FULL' : 'BALANCE ' . format_currency($e['amount_due']); ?></span>
                        <span class="block text-[9px] text-slate-400">Paid <?php echo format_currency($e['total_paid']); ?> of <?php echo format_currency($fee); ?></span>
                    </div>
                </div>

                <?php if (!empty($schedules[$e['id']])): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($schedules[$e['id']] as $ip):
                            $cls = $ip['status'] === 'PAID' ? 'bg-emerald-50 text-emerald-700' : ($ip['status'] === 'OVERDUE' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600'); ?>
                            <span class="px-2 py-1 rounded text-[9px] font-semibold <?php echo $cls; ?>">#<?php echo $ip['installment_number']; ?> &middot; <?php echo format_currency($ip['amount']); ?> &middot; due <?php echo date('d M Y', strtotime($ip['due_date'])); ?> &middot; <?php echo $ip['status']; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-4 text-[10px] font-bold">
                    <?php if (!$settled): ?>
                        <details class="w-full">
                            <summary class="cursor-pointer text-amber-600">Record payment</summary>
                            <form method="POST" class="grid grid-cols-2 gap-2 mt-2">
                                <?php echo $csrf; ?>
                                <input type="hidden" name="action" value="pay">
                                <input type="hidden" name="enrollment_id" value="<?php echo $e['id']; ?>">
                                <input type="number" step="0.01" min="0.01" max="<?php echo $e['amount_due']; ?>" name="amount" value="<?php echo $e['amount_due']; ?>" required class="<?php echo $input; ?>">
                                <select name="method" class="<?php echo $input; ?>"><?php foreach (PAYMENT_METHODS as $m): ?><option value="<?php echo $m; ?>"><?php echo str_replace('_', ' ', $m); ?></option><?php endforeach; ?></select>
                                <input type="text" name="note" placeholder="Note (optional)" class="<?php echo $input; ?> col-span-2">
                                <button class="col-span-2 py-1.5 bg-amber-500 text-white rounded">Record payment</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <details class="w-full">
                        <summary class="cursor-pointer text-slate-600">Edit dates / plan / status</summary>
                        <form method="POST" class="space-y-2 mt-2">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="enrollment_id" value="<?php echo $e['id']; ?>">
                            <select name="batch_id" class="<?php echo $input; ?> edit-batch"><?php echo $batch_opts($e['course_id'], $e['batch_id'] ?? ''); ?></select>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" name="start_date" value="<?php echo $e['start_date']; ?>" required class="<?php echo $input; ?>">
                                <input type="date" name="end_date" value="<?php echo $e['end_date']; ?>" required class="<?php echo $input; ?>">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="status" class="<?php echo $input; ?>"><?php foreach (ENROLLMENT_STATUSES as $st): ?><option <?php echo $e['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option><?php endforeach; ?></select>
                                <select name="payment_type" class="<?php echo $input; ?>">
                                    <option value="FULL" <?php echo $e['payment_type'] === 'FULL' ? 'selected' : ''; ?>>Full payment</option>
                                    <option value="INSTALLMENT" <?php echo $e['payment_type'] === 'INSTALLMENT' ? 'selected' : ''; ?>>Installments</option>
                                </select>
                            </div>
                            <p class="text-[9px] text-slate-400 font-normal">Switching to installments rebuilds the schedule (monthly from the start date); switching to full cancels unpaid installments.</p>
                            <button class="w-full py-1.5 bg-slate-800 text-white rounded">Save changes</button>
                        </form>
                    </details>

                    <?php if (!empty($history[$e['id']])): ?>
                        <details class="w-full">
                            <summary class="cursor-pointer text-slate-600">Payment history (<?php echo count($history[$e['id']]); ?>)</summary>
                            <ul class="mt-2 space-y-1 font-normal text-slate-600">
                                <?php foreach ($history[$e['id']] as $p): ?>
                                    <li><?php echo $p['paid_at'] ? date('d M Y', strtotime($p['paid_at'])) : '-'; ?> &mdash; <?php echo format_currency($p['amount']); ?> (<?php echo str_replace('_', ' ', $p['method']); ?>)<?php echo $p['description'] ? ' &mdash; ' . sanitize($p['description']) : ''; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>

                    <?php if (floatval($e['total_paid']) <= 0): ?>
                        <form method="POST" onsubmit="return confirm('Remove this enrollment?');">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="enrollment_id" value="<?php echo $e['id']; ?>">
                            <button class="text-red-600 hover:underline">Remove enrollment</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    (function () {
        const course = document.getElementById('reg-course');
        const batch = document.getElementById('reg-batch');
        const start = document.getElementById('reg-start');
        const end = document.getElementById('reg-end');
        const plan = document.getElementById('reg-plan');
        const countWrap = document.getElementById('reg-count-wrap');
        const count = document.getElementById('reg-count');

        const fillDates = (sel, form) => {
            const o = sel.selectedOptions[0];
            if (o && o.value) {
                form.querySelector('[name=start_date]').value = o.dataset.start;
                form.querySelector('[name=end_date]').value = o.dataset.end;
            }
        };
        course.addEventListener('change', () => {
            for (const opt of batch.options) if (opt.value) opt.hidden = opt.dataset.course !== course.value;
            if (batch.selectedOptions[0]?.hidden) batch.value = '';
            const o = course.selectedOptions[0];
            const allowed = o && o.dataset.installment === '1';
            plan.querySelector('[value=INSTALLMENT]').disabled = !allowed;
            if (!allowed) plan.value = 'FULL';
            if (o && o.dataset.count) count.value = o.dataset.count;
            plan.dispatchEvent(new Event('change'));
        });
        plan.addEventListener('change', () => countWrap.classList.toggle('hidden', plan.value !== 'INSTALLMENT'));
        batch.addEventListener('change', () => fillDates(batch, batch.form));
        document.querySelectorAll('.edit-batch').forEach(s => s.addEventListener('change', () => fillDates(s, s.form)));
        course.dispatchEvent(new Event('change'));
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
