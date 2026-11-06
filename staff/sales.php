<?php
// staff/sales.php - Marketing & Sales: prospects (with where they came from), appointments and demos
// booked with them, and sales made (including students brought in). Admins use it via admin/sales.php.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_permission('sales');

$user = current_user();
$page_title = "Sales & Prospects";
$errors = [];

const PROSPECT_SOURCES = [
    'FIELD_VISIT' => 'Field visit / outreach', 'EMAIL_MARKETING' => 'Email marketing', 'SOCIAL_MEDIA' => 'Social media',
    'PHONE_CALL' => 'Phone call', 'WALK_IN' => 'Walk-in', 'REFERRAL' => 'Referral', 'WEBSITE' => 'Website', 'EVENT' => 'Event / seminar', 'OTHER' => 'Other',
];
const PROSPECT_STATUSES = ['NEW' => 'New', 'CONTACTED' => 'Contacted', 'APPOINTMENT' => 'Appointment booked', 'DEMO' => 'Demo booked', 'NEGOTIATION' => 'Negotiation', 'WON' => 'Won', 'LOST' => 'Lost'];
const APPOINTMENT_STATUSES = ['BOOKED' => 'Booked', 'HELD' => 'Held', 'NO_SHOW' => 'No-show', 'CANCELLED' => 'Cancelled'];
const SALE_TYPES = ['COURSE' => 'Course / training (student)', 'SERVICE' => 'Service (client project)', 'OTHER' => 'Other'];

$tabs = ['prospects' => 'Prospects', 'appointments' => 'Appointments & Demos', 'sales' => 'Sales'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'prospects';

$courses = $conn->query("SELECT id, title, price FROM courses WHERE status = 'ACTIVE' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
$course_ids = array_column($courses, 'id');
$services = array_column(SERVICE_CATALOG, 'label');

function load_prospect($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM prospects WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception("Prospect not found.");
    return $row;
}

/** Validated prospect fields from POST. */
function posted_prospect($course_ids, $services)
{
    $p = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'phone' => trim($_POST['phone'] ?? ''),
        'company' => trim($_POST['company'] ?? ''),
        'source' => $_POST['source'] ?? 'OTHER',
        'interest_type' => $_POST['interest_type'] ?? 'COURSE',
        'course_id' => $_POST['course_id'] ?? '',
        'service' => $_POST['service'] ?? '',
        'status' => $_POST['status'] ?? 'NEW',
        'estimated_value' => trim($_POST['estimated_value'] ?? ''),
        'next_follow_up' => trim($_POST['next_follow_up'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    if ($p['full_name'] === '') throw new Exception("The prospect's name is required.");
    if ($p['email'] === '' && $p['phone'] === '') throw new Exception("Enter a phone number or email so the prospect can be followed up.");
    if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) throw new Exception("That email address is not valid.");
    if (!isset(PROSPECT_SOURCES[$p['source']])) $p['source'] = 'OTHER';
    if (!in_array($p['interest_type'], ['COURSE', 'SERVICE', 'OTHER'], true)) $p['interest_type'] = 'OTHER';
    if (!isset(PROSPECT_STATUSES[$p['status']])) $p['status'] = 'NEW';
    $p['course_id'] = $p['interest_type'] === 'COURSE' && in_array($p['course_id'], $course_ids, true) ? $p['course_id'] : null;
    $p['service'] = $p['interest_type'] === 'SERVICE' && in_array($p['service'], $services, true) ? $p['service'] : null;
    $p['estimated_value'] = $p['estimated_value'] !== '' ? max(0, round(floatval($p['estimated_value']), 2)) : null;
    $p['next_follow_up'] = $p['next_follow_up'] !== '' && strtotime($p['next_follow_up']) ? date('Y-m-d', strtotime($p['next_follow_up'])) : null;
    foreach (['email', 'phone', 'company', 'notes'] as $k) {
        if ($p[$k] === '') $p[$k] = null;
    }
    return $p;
}

/** Move a prospect forward to $status unless it is already further along (won/lost are final). */
function advance_prospect($conn, $id, $status)
{
    $order = array_keys(PROSPECT_STATUSES);
    $p = load_prospect($conn, $id);
    if (in_array($p['status'], ['WON', 'LOST'], true) && $status !== 'WON') return;
    if (array_search($status, $order, true) > array_search($p['status'], $order, true) || $status === 'WON') {
        $stmt = $conn->prepare("UPDATE prospects SET status = ? WHERE id = ?");
        $stmt->bind_param("ss", $status, $id);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'];
    try {
        if ($action === 'add_prospect' || $action === 'update_prospect') {
            $p = posted_prospect($course_ids, $services);
            if ($action === 'add_prospect') {
                $id = generate_uuid();
                $stmt = $conn->prepare("INSERT INTO prospects (id, full_name, email, phone, company, source, interest_type, course_id, service, status, estimated_value, next_follow_up, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssssdsss", $id, $p['full_name'], $p['email'], $p['phone'], $p['company'], $p['source'], $p['interest_type'], $p['course_id'], $p['service'], $p['status'], $p['estimated_value'], $p['next_follow_up'], $p['notes'], $user['id']);
                $msg = "Prospect " . sanitize($p['full_name']) . " added.";
            } else {
                $id = load_prospect($conn, sanitize($_POST['prospect_id'] ?? ''))['id'];
                $stmt = $conn->prepare("UPDATE prospects SET full_name = ?, email = ?, phone = ?, company = ?, source = ?, interest_type = ?, course_id = ?, service = ?, status = ?, estimated_value = ?, next_follow_up = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("sssssssssdsss", $p['full_name'], $p['email'], $p['phone'], $p['company'], $p['source'], $p['interest_type'], $p['course_id'], $p['service'], $p['status'], $p['estimated_value'], $p['next_follow_up'], $p['notes'], $id);
                $msg = "Prospect updated.";
            }
            $stmt->execute();
            $stmt->close();
            log_activity($action === 'add_prospect' ? "Added sales prospect" : "Updated sales prospect", ["prospect_id" => $id]);
            set_flash_message('success', $msg);

        } elseif ($action === 'set_status') {
            $p = load_prospect($conn, sanitize($_POST['prospect_id'] ?? ''));
            $status = isset(PROSPECT_STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : $p['status'];
            $stmt = $conn->prepare("UPDATE prospects SET status = ? WHERE id = ?");
            $stmt->bind_param("ss", $status, $p['id']);
            $stmt->execute();
            $stmt->close();
            set_flash_message('success', sanitize($p['full_name']) . " moved to " . PROSPECT_STATUSES[$status] . ".");

        } elseif ($action === 'delete_prospect') {
            $p = load_prospect($conn, sanitize($_POST['prospect_id'] ?? ''));
            $stmt = $conn->prepare("DELETE FROM prospects WHERE id = ?");
            $stmt->bind_param("s", $p['id']);
            $stmt->execute();
            $stmt->close();
            log_activity("Deleted sales prospect", ["prospect_id" => $p['id']]);
            set_flash_message('success', "Prospect deleted.");

        } elseif ($action === 'book_appointment') {
            $p = load_prospect($conn, sanitize($_POST['prospect_id'] ?? ''));
            $type = ($_POST['type'] ?? '') === 'DEMO' ? 'DEMO' : 'APPOINTMENT';
            $when = strtotime($_POST['scheduled_at'] ?? '');
            if (!$when) throw new Exception("Choose the date and time of the " . strtolower($type) . ".");
            $when = date('Y-m-d H:i:s', $when);
            $location = trim($_POST['location'] ?? '') ?: null;
            $notes = trim($_POST['notes'] ?? '') ?: null;
            $id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO sales_appointments (id, prospect_id, type, scheduled_at, location, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $id, $p['id'], $type, $when, $location, $notes, $user['id']);
            $stmt->execute();
            $stmt->close();
            advance_prospect($conn, $p['id'], $type);
            log_activity("Booked sales " . strtolower($type), ["prospect_id" => $p['id'], "appointment_id" => $id]);
            set_flash_message('success', ucfirst(strtolower($type)) . " with " . sanitize($p['full_name']) . " booked for " . date('D d M Y, g:ia', strtotime($when)) . ".");

        } elseif ($action === 'update_appointment') {
            $id = sanitize($_POST['appointment_id'] ?? '');
            $status = isset(APPOINTMENT_STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : 'BOOKED';
            $outcome = trim($_POST['outcome'] ?? '') ?: null;
            $when = strtotime($_POST['scheduled_at'] ?? '');
            $stmt = $conn->prepare("UPDATE sales_appointments SET status = ?, outcome = ?" . ($when ? ", scheduled_at = '" . date('Y-m-d H:i:s', $when) . "'" : "") . " WHERE id = ?");
            $stmt->bind_param("sss", $status, $outcome, $id);
            $stmt->execute();
            $stmt->close();
            set_flash_message('success', "Appointment updated.");

        } elseif ($action === 'record_sale') {
            $type = isset(SALE_TYPES[$_POST['sale_type'] ?? '']) ? $_POST['sale_type'] : 'OTHER';
            $prospect_id = sanitize($_POST['prospect_id'] ?? '') ?: null;
            $prospect = $prospect_id ? load_prospect($conn, $prospect_id) : null;
            $customer = trim($_POST['customer_name'] ?? '') ?: ($prospect['full_name'] ?? '');
            $course_id = $type === 'COURSE' && in_array($_POST['course_id'] ?? '', $course_ids, true) ? $_POST['course_id'] : null;
            $student_pk = sanitize($_POST['student_pk'] ?? '') ?: null;
            $amount = round(floatval($_POST['amount'] ?? 0), 2);
            $date = strtotime($_POST['sale_date'] ?? '') ?: time();
            $description = trim($_POST['description'] ?? '') ?: null;
            $notes = trim($_POST['notes'] ?? '') ?: null;
            if ($customer === '') throw new Exception("Enter the customer's name (or pick a prospect).");
            if ($amount <= 0) throw new Exception("Enter the sale amount.");
            if ($student_pk) {
                $stmt = $conn->prepare("SELECT 1 FROM students WHERE id = ?");
                $stmt->bind_param("s", $student_pk);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) $student_pk = null;
                $stmt->close();
            }
            $id = generate_uuid();
            $sale_date = date('Y-m-d', $date);
            $stmt = $conn->prepare("INSERT INTO sales_records (id, prospect_id, sale_type, course_id, student_id, customer_name, description, amount, sale_date, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssdsss", $id, $prospect_id, $type, $course_id, $student_pk, $customer, $description, $amount, $sale_date, $notes, $user['id']);
            $stmt->execute();
            $stmt->close();
            if ($prospect) advance_prospect($conn, $prospect['id'], 'WON');
            log_activity("Recorded sale", ["sale_id" => $id, "amount" => $amount]);
            set_flash_message('success', "Sale of " . format_currency($amount) . " to " . sanitize($customer) . " recorded.");
            $tab = 'sales';

        } elseif ($action === 'delete_sale') {
            $id = sanitize($_POST['sale_id'] ?? '');
            $stmt = $conn->prepare("DELETE FROM sales_records WHERE id = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $stmt->close();
            log_activity("Deleted sale record", ["sale_id" => $id]);
            set_flash_message('success', "Sale record deleted.");
        } else {
            throw new Exception("Unknown action.");
        }
        header("Location: sales.php?tab=" . (isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : $tab));
        exit();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// ---- Stats ----
$month_start = date('Y-m-01');
$stat = fn($sql) => $conn->query($sql)->fetch_assoc();
$s_open = $stat("SELECT COUNT(*) AS n, COALESCE(SUM(estimated_value),0) AS v FROM prospects WHERE status NOT IN ('WON','LOST')");
$s_upcoming = $stat("SELECT COUNT(*) AS n FROM sales_appointments WHERE status = 'BOOKED' AND scheduled_at >= NOW()");
$s_demos = $stat("SELECT COUNT(*) AS n FROM sales_appointments WHERE type = 'DEMO' AND status <> 'CANCELLED' AND scheduled_at >= '$month_start'");
$s_sales = $stat("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS v FROM sales_records WHERE sale_date >= '$month_start'");
$s_students = $stat("SELECT COUNT(*) AS n FROM sales_records WHERE sale_type = 'COURSE' AND sale_date >= '$month_start'");
$s_conv = $stat("SELECT SUM(status = 'WON') AS won, SUM(status IN ('WON','LOST')) AS closed FROM prospects");
$conv = $s_conv['closed'] ? round(100 * $s_conv['won'] / $s_conv['closed']) . '%' : '—';
$s_follow = $stat("SELECT COUNT(*) AS n FROM prospects WHERE status NOT IN ('WON','LOST') AND next_follow_up IS NOT NULL AND next_follow_up <= CURDATE()");

$who = "COALESCE(CONCAT(ua.first_name, ' ', ua.last_name), CONCAT(us.first_name, ' ', us.last_name))";

// ---- Prospects ----
$f_status = isset(PROSPECT_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$f_source = isset(PROSPECT_SOURCES[$_GET['source'] ?? '']) ? $_GET['source'] : '';
$f_q = trim($_GET['q'] ?? '');
$prospects = [];
$all_prospects = $conn->query("SELECT id, full_name, phone, status FROM prospects ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
if ($tab === 'prospects') {
    $sql = "SELECT p.*, c.title AS course_title, $who AS logged_by,
                   (SELECT COUNT(*) FROM sales_appointments a WHERE a.prospect_id = p.id) AS appts,
                   (SELECT MIN(a.scheduled_at) FROM sales_appointments a WHERE a.prospect_id = p.id AND a.status = 'BOOKED' AND a.scheduled_at >= NOW()) AS next_appt
            FROM prospects p LEFT JOIN courses c ON c.id = p.course_id
            LEFT JOIN users_admin ua ON ua.id = p.created_by LEFT JOIN users_staff us ON us.id = p.created_by WHERE 1=1";
    $types = '';
    $params = [];
    if ($f_status !== '') { $sql .= " AND p.status = ?"; $types .= 's'; $params[] = $f_status; }
    elseif (!isset($_GET['status'])) { $sql .= " AND p.status NOT IN ('WON','LOST')"; }
    if ($f_source !== '') { $sql .= " AND p.source = ?"; $types .= 's'; $params[] = $f_source; }
    if ($f_q !== '') {
        $sql .= " AND (p.full_name LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.company LIKE ?)";
        $like = "%$f_q%";
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }
    $sql .= " ORDER BY (p.next_follow_up IS NULL), p.next_follow_up, p.created_at DESC LIMIT 300";
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $prospects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ---- Appointments ----
$appointments = [];
if ($tab === 'appointments') {
    $appointments = $conn->query("SELECT a.*, p.full_name, p.phone, p.email, p.company, $who AS booked_by
                                  FROM sales_appointments a JOIN prospects p ON p.id = a.prospect_id
                                  LEFT JOIN users_admin ua ON ua.id = a.created_by LEFT JOIN users_staff us ON us.id = a.created_by
                                  ORDER BY (a.status = 'BOOKED' AND a.scheduled_at >= NOW()) DESC,
                                           CASE WHEN a.status = 'BOOKED' AND a.scheduled_at >= NOW() THEN a.scheduled_at END ASC, a.scheduled_at DESC
                                  LIMIT 200")->fetch_all(MYSQLI_ASSOC);
}

// ---- Sales ----
$sales = [];
$students = [];
$f_month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : '';
if ($tab === 'sales') {
    $sql = "SELECT r.*, c.title AS course_title, st.student_id AS reg_no, $who AS recorded_by_name
            FROM sales_records r LEFT JOIN courses c ON c.id = r.course_id LEFT JOIN students st ON st.id = r.student_id
            LEFT JOIN users_admin ua ON ua.id = r.recorded_by LEFT JOIN users_staff us ON us.id = r.recorded_by";
    if ($f_month !== '') $sql .= " WHERE DATE_FORMAT(r.sale_date, '%Y-%m') = '" . $conn->real_escape_string($f_month) . "'";
    $sales = $conn->query($sql . " ORDER BY r.sale_date DESC, r.created_at DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
    $students = $conn->query("SELECT s.id, s.student_id AS reg_no, u.first_name, u.last_name FROM students s JOIN users_student u ON u.id = s.user_id ORDER BY s.enrollment_date DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
}

$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";
$csrf = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
$status_cls = ['NEW' => 'bg-slate-100 text-slate-600', 'CONTACTED' => 'bg-sky-50 text-sky-700', 'APPOINTMENT' => 'bg-violet-50 text-violet-700', 'DEMO' => 'bg-indigo-50 text-indigo-700', 'NEGOTIATION' => 'bg-amber-50 text-amber-700', 'WON' => 'bg-emerald-50 text-emerald-700', 'LOST' => 'bg-red-50 text-red-600'];
$old = fn($k, $d = '') => sanitize($errors ? ($_POST[$k] ?? $d) : $d);

/** Prospect form fields (add + edit share them). */
$prospect_fields = function ($p = null) use ($input, $label, $courses, $services) {
    $v = fn($k, $d = '') => sanitize((string) ($p !== null ? ($p[$k] ?? '') : ($_POST[$k] ?? $d)));
    ob_start(); ?>
    <div><label class="<?php echo $label; ?>">Full name *</label><input name="full_name" required value="<?php echo $v('full_name'); ?>" class="<?php echo $input; ?>"></div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="<?php echo $label; ?>">Phone</label><input name="phone" value="<?php echo $v('phone'); ?>" class="<?php echo $input; ?>"></div>
        <div><label class="<?php echo $label; ?>">Email</label><input type="email" name="email" value="<?php echo $v('email'); ?>" class="<?php echo $input; ?>"></div>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="<?php echo $label; ?>">Company / school</label><input name="company" value="<?php echo $v('company'); ?>" class="<?php echo $input; ?>"></div>
        <div><label class="<?php echo $label; ?>">Source *</label>
            <select name="source" class="<?php echo $input; ?>"><?php foreach (PROSPECT_SOURCES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $v('source', 'FIELD_VISIT') === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="<?php echo $label; ?>">Interested in</label>
            <select name="interest_type" class="<?php echo $input; ?> interest-type">
                <option value="COURSE" <?php echo $v('interest_type', 'COURSE') === 'COURSE' ? 'selected' : ''; ?>>A course</option>
                <option value="SERVICE" <?php echo $v('interest_type') === 'SERVICE' ? 'selected' : ''; ?>>A service</option>
                <option value="OTHER" <?php echo $v('interest_type') === 'OTHER' ? 'selected' : ''; ?>>Other / not sure</option>
            </select>
        </div>
        <div>
            <label class="<?php echo $label; ?>">Which</label>
            <select name="course_id" class="<?php echo $input; ?> interest-course"><option value="">Choose course...</option><?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $v('course_id') === $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['title']); ?></option><?php endforeach; ?></select>
            <select name="service" class="<?php echo $input; ?> interest-service"><option value="">Choose service...</option><?php foreach ($services as $sv): ?><option <?php echo $v('service') === $sv ? 'selected' : ''; ?>><?php echo sanitize($sv); ?></option><?php endforeach; ?></select>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-2">
        <div><label class="<?php echo $label; ?>">Stage</label><select name="status" class="<?php echo $input; ?>"><?php foreach (PROSPECT_STATUSES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $v('status', 'NEW') === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></div>
        <div><label class="<?php echo $label; ?>">Est. value</label><input type="number" step="0.01" min="0" name="estimated_value" value="<?php echo $v('estimated_value'); ?>" class="<?php echo $input; ?>"></div>
        <div><label class="<?php echo $label; ?>">Follow up on</label><input type="date" name="next_follow_up" value="<?php echo $v('next_follow_up'); ?>" class="<?php echo $input; ?>"></div>
    </div>
    <div><label class="<?php echo $label; ?>">Notes</label><textarea name="notes" rows="3" placeholder="Where you met them, what they need, budget, objections..." class="<?php echo $input; ?>"><?php echo $v('notes'); ?></textarea></div>
    <?php return ob_get_clean();
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <?php foreach ([
        ['Open prospects', $s_open['n'], $s_open['v'] > 0 ? format_currency($s_open['v']) . ' est.' : ''],
        ['Follow-ups due', $s_follow['n'], 'today or overdue'],
        ['Upcoming appointments', $s_upcoming['n'], 'booked'],
        ['Demos this month', $s_demos['n'], date('F')],
        ['Sales this month', format_currency($s_sales['v']), $s_sales['n'] . ' sale(s)'],
        ['Students brought in', $s_students['n'], 'this month · win rate ' . $conv],
    ] as [$t, $n, $sub]): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block"><?php echo $t; ?></span>
            <span class="text-xl font-bold text-slate-900 block"><?php echo $n; ?></span>
            <span class="text-[10px] text-slate-400"><?php echo $sub; ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="flex gap-1 p-1 bg-slate-100 rounded-xl mb-6 w-fit text-xs font-semibold">
    <?php foreach ($tabs as $k => $n): ?>
        <a href="sales.php?tab=<?php echo $k; ?>" class="px-4 py-2 rounded-lg <?php echo $tab === $k ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-800'; ?>"><?php echo $n; ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'prospects'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div>
        <form method="POST" action="sales.php?tab=prospects" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3 prospect-form">
            <?php echo $csrf; ?>
            <input type="hidden" name="action" value="add_prospect">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Add Prospect</h4>
            <?php echo $prospect_fields(); ?>
            <button class="w-full py-2.5 bg-[#17264A] hover:bg-[#22355f] text-white font-semibold rounded-xl text-xs">Save Prospect</button>
        </form>
    </div>
    <div class="lg:col-span-2 space-y-3">
        <form method="GET" class="flex flex-wrap gap-2 bg-white border border-slate-200 rounded-2xl p-3">
            <input type="hidden" name="tab" value="prospects">
            <input name="q" value="<?php echo sanitize($f_q); ?>" placeholder="Search name, phone, email, company" class="<?php echo $input; ?> flex-1 min-w-[10rem]">
            <select name="status" class="<?php echo $input; ?> sm:w-40">
                <option value="">Open (not won/lost)</option>
                <?php foreach (PROSPECT_STATUSES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $f_status === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?>
            </select>
            <select name="source" class="<?php echo $input; ?> sm:w-40">
                <option value="">Any source</option>
                <?php foreach (PROSPECT_SOURCES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $f_source === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?>
            </select>
            <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold">Filter</button>
        </form>

        <?php if (!$prospects): ?><div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-xs text-slate-400">No prospects here yet.</div><?php endif; ?>

        <?php foreach ($prospects as $p):
            $due = $p['next_follow_up'] && strtotime($p['next_follow_up']) <= strtotime(date('Y-m-d')) && !in_array($p['status'], ['WON', 'LOST'], true); ?>
            <div class="bg-white border <?php echo $due ? 'border-amber-300' : 'border-slate-200'; ?> rounded-2xl p-4 shadow-sm space-y-2">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <span class="text-sm font-bold text-slate-900"><?php echo sanitize($p['full_name']); ?></span><?php echo $p['company'] ? ' <span class="text-[11px] text-slate-500">· ' . sanitize($p['company']) . '</span>' : ''; ?>
                        <span class="text-[11px] text-slate-500 block"><?php echo sanitize(implode(' · ', array_filter([$p['phone'], $p['email']]))); ?></span>
                        <span class="text-[11px] text-slate-600 block">
                            <span class="font-semibold"><?php echo PROSPECT_SOURCES[$p['source']]; ?></span> &bull;
                            <?php echo $p['interest_type'] === 'COURSE' ? sanitize($p['course_title'] ?? 'A course') : ($p['interest_type'] === 'SERVICE' ? sanitize($p['service'] ?? 'A service') : 'Not specified'); ?>
                            <?php echo $p['estimated_value'] ? ' &bull; ~' . format_currency($p['estimated_value']) : ''; ?>
                        </span>
                        <span class="text-[10px] text-slate-400 block">Logged <?php echo date('d M Y', strtotime($p['created_at'])); ?> by <?php echo sanitize($p['logged_by'] ?? '—'); ?>
                            <?php echo $p['next_follow_up'] ? ' &bull; <span class="' . ($due ? 'text-amber-600 font-bold' : '') . '">follow up ' . date('d M Y', strtotime($p['next_follow_up'])) . '</span>' : ''; ?>
                            <?php echo $p['next_appt'] ? ' &bull; <span class="text-violet-600 font-semibold">next meeting ' . date('d M, g:ia', strtotime($p['next_appt'])) . '</span>' : ''; ?>
                        </span>
                    </div>
                    <form method="POST" action="sales.php?tab=prospects">
                        <?php echo $csrf; ?>
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="prospect_id" value="<?php echo $p['id']; ?>">
                        <select name="status" onchange="this.form.submit()" class="px-2 py-1 rounded-lg text-[11px] font-bold border-0 <?php echo $status_cls[$p['status']]; ?>">
                            <?php foreach (PROSPECT_STATUSES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $p['status'] === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php if ($p['notes']): ?><p class="text-[11px] text-slate-600 whitespace-pre-line"><?php echo sanitize($p['notes']); ?></p><?php endif; ?>
                <div class="flex flex-wrap gap-4 text-[11px] font-bold">
                    <details class="w-full">
                        <summary class="cursor-pointer text-violet-700">Book appointment / demo</summary>
                        <form method="POST" action="sales.php?tab=prospects" class="grid grid-cols-2 gap-2 mt-2 font-normal">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="book_appointment">
                            <input type="hidden" name="prospect_id" value="<?php echo $p['id']; ?>">
                            <select name="type" class="<?php echo $input; ?>"><option value="APPOINTMENT">Appointment</option><option value="DEMO">Demo</option></select>
                            <input type="datetime-local" name="scheduled_at" required class="<?php echo $input; ?>">
                            <input name="location" placeholder="Location or meeting link" class="<?php echo $input; ?> col-span-2">
                            <input name="notes" placeholder="Agenda / notes" class="<?php echo $input; ?> col-span-2">
                            <button class="col-span-2 py-1.5 bg-violet-700 text-white rounded-lg font-bold">Book</button>
                        </form>
                    </details>
                    <?php if ($p['status'] !== 'WON'): ?>
                        <details class="w-full">
                            <summary class="cursor-pointer text-emerald-700">Record sale (mark won)</summary>
                            <form method="POST" action="sales.php?tab=prospects" class="grid grid-cols-2 gap-2 mt-2 font-normal">
                                <?php echo $csrf; ?>
                                <input type="hidden" name="action" value="record_sale">
                                <input type="hidden" name="prospect_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="customer_name" value="<?php echo sanitize($p['full_name']); ?>">
                                <select name="sale_type" class="<?php echo $input; ?>"><?php foreach (SALE_TYPES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $p['interest_type'] === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select>
                                <select name="course_id" class="<?php echo $input; ?>"><option value="">Course (if any)</option><?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $p['course_id'] === $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['title']); ?></option><?php endforeach; ?></select>
                                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="Amount" value="<?php echo $p['estimated_value'] ? number_format($p['estimated_value'], 2, '.', '') : ''; ?>" class="<?php echo $input; ?>">
                                <input type="date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" class="<?php echo $input; ?>">
                                <input name="description" placeholder="What was sold" value="<?php echo $p['interest_type'] === 'SERVICE' ? sanitize($p['service'] ?? '') : ''; ?>" class="<?php echo $input; ?> col-span-2">
                                <button class="col-span-2 py-1.5 bg-emerald-700 text-white rounded-lg font-bold">Record sale</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <details class="w-full">
                        <summary class="cursor-pointer text-slate-600">Edit prospect</summary>
                        <form method="POST" action="sales.php?tab=prospects" class="space-y-2 mt-2 font-normal prospect-form">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="update_prospect">
                            <input type="hidden" name="prospect_id" value="<?php echo $p['id']; ?>">
                            <?php echo $prospect_fields($p); ?>
                            <button class="w-full py-1.5 bg-slate-800 text-white rounded-lg font-bold">Save changes</button>
                        </form>
                        <form method="POST" action="sales.php?tab=prospects" class="mt-2" onsubmit="return confirm('Delete this prospect and its appointments?');">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="delete_prospect">
                            <input type="hidden" name="prospect_id" value="<?php echo $p['id']; ?>">
                            <button class="text-red-600 hover:underline">Delete prospect</button>
                        </form>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($tab === 'appointments'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div>
        <form method="POST" action="sales.php?tab=appointments" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3">
            <?php echo $csrf; ?>
            <input type="hidden" name="action" value="book_appointment">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Book Appointment / Demo</h4>
            <?php if (!$all_prospects): ?>
                <p class="text-xs text-slate-500">Add the person as a <a href="sales.php?tab=prospects" class="text-blue-600 hover:underline">prospect</a> first.</p>
            <?php else: ?>
                <div><label class="<?php echo $label; ?>">Prospect</label>
                    <select name="prospect_id" required class="<?php echo $input; ?>"><option value="">Choose...</option><?php foreach ($all_prospects as $ap): ?><option value="<?php echo $ap['id']; ?>"><?php echo sanitize($ap['full_name'] . ($ap['phone'] ? ' (' . $ap['phone'] . ')' : '')); ?></option><?php endforeach; ?></select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="<?php echo $label; ?>">Type</label><select name="type" class="<?php echo $input; ?>"><option value="APPOINTMENT">Appointment</option><option value="DEMO">Demo</option></select></div>
                    <div><label class="<?php echo $label; ?>">When</label><input type="datetime-local" name="scheduled_at" required class="<?php echo $input; ?>"></div>
                </div>
                <div><label class="<?php echo $label; ?>">Location / link</label><input name="location" class="<?php echo $input; ?>"></div>
                <div><label class="<?php echo $label; ?>">Notes</label><textarea name="notes" rows="2" class="<?php echo $input; ?>"></textarea></div>
                <button class="w-full py-2.5 bg-violet-700 hover:bg-violet-600 text-white font-semibold rounded-xl text-xs">Book</button>
            <?php endif; ?>
        </form>
    </div>
    <div class="lg:col-span-2 space-y-3">
        <?php if (!$appointments): ?><div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-xs text-slate-400">No appointments or demos booked yet.</div><?php endif; ?>
        <?php foreach ($appointments as $a):
            $upcoming = $a['status'] === 'BOOKED' && strtotime($a['scheduled_at']) >= time();
            $overdue = $a['status'] === 'BOOKED' && !$upcoming; ?>
            <div class="bg-white border <?php echo $overdue ? 'border-amber-300' : 'border-slate-200'; ?> rounded-2xl p-4 shadow-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $a['type'] === 'DEMO' ? 'bg-indigo-50 text-indigo-700' : 'bg-violet-50 text-violet-700'; ?>"><?php echo $a['type']; ?></span>
                        <span class="text-sm font-bold text-slate-900 ml-1"><?php echo sanitize($a['full_name']); ?></span>
                        <span class="text-[11px] text-slate-500 block mt-1"><?php echo sanitize(implode(' · ', array_filter([$a['phone'], $a['email'], $a['company']]))); ?></span>
                        <span class="text-xs font-semibold <?php echo $upcoming ? 'text-slate-800' : 'text-slate-500'; ?> block"><?php echo date('D d M Y, g:ia', strtotime($a['scheduled_at'])); ?><?php echo $a['location'] ? ' &bull; ' . sanitize($a['location']) : ''; ?></span>
                        <?php if ($a['notes']): ?><span class="text-[11px] text-slate-500 block"><?php echo sanitize($a['notes']); ?></span><?php endif; ?>
                        <span class="text-[10px] text-slate-400">Booked by <?php echo sanitize($a['booked_by'] ?? '—'); ?><?php echo $overdue ? ' &bull; <b class="text-amber-600">update the outcome</b>' : ''; ?></span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-500 uppercase"><?php echo APPOINTMENT_STATUSES[$a['status']]; ?></span>
                </div>
                <?php if ($a['outcome']): ?><p class="text-[11px] text-slate-600 mt-2"><b>Outcome:</b> <?php echo sanitize($a['outcome']); ?></p><?php endif; ?>
                <details class="mt-2">
                    <summary class="text-[11px] font-bold text-slate-600 cursor-pointer">Update status / outcome</summary>
                    <form method="POST" action="sales.php?tab=appointments" class="grid grid-cols-2 gap-2 mt-2">
                        <?php echo $csrf; ?>
                        <input type="hidden" name="action" value="update_appointment">
                        <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                        <select name="status" class="<?php echo $input; ?>"><?php foreach (APPOINTMENT_STATUSES as $k => $n): ?><option value="<?php echo $k; ?>" <?php echo $a['status'] === $k ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select>
                        <input type="datetime-local" name="scheduled_at" value="<?php echo date('Y-m-d\TH:i', strtotime($a['scheduled_at'])); ?>" class="<?php echo $input; ?>" title="Reschedule">
                        <textarea name="outcome" rows="2" placeholder="What happened, next steps" class="<?php echo $input; ?> col-span-2"><?php echo sanitize($a['outcome'] ?? ''); ?></textarea>
                        <button class="col-span-2 py-1.5 bg-slate-800 text-white rounded-lg text-[11px] font-bold">Save</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div>
        <form method="POST" action="sales.php?tab=sales" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3">
            <?php echo $csrf; ?>
            <input type="hidden" name="action" value="record_sale">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Record a Sale</h4>
            <div><label class="<?php echo $label; ?>">From prospect (optional)</label>
                <select name="prospect_id" class="<?php echo $input; ?>"><option value="">— None —</option><?php foreach ($all_prospects as $ap): if ($ap['status'] === 'WON') continue; ?><option value="<?php echo $ap['id']; ?>"><?php echo sanitize($ap['full_name']); ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="<?php echo $label; ?>">Customer name</label><input name="customer_name" value="<?php echo $old('customer_name'); ?>" placeholder="Leave blank to use the prospect's name" class="<?php echo $input; ?>"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="<?php echo $label; ?>">Type</label><select name="sale_type" class="<?php echo $input; ?>"><?php foreach (SALE_TYPES as $k => $n): ?><option value="<?php echo $k; ?>"><?php echo $n; ?></option><?php endforeach; ?></select></div>
                <div><label class="<?php echo $label; ?>">Course</label><select name="course_id" class="<?php echo $input; ?>"><option value="">—</option><?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['title']); ?></option><?php endforeach; ?></select></div>
            </div>
            <div><label class="<?php echo $label; ?>">Student brought in (if registered)</label>
                <select name="student_pk" class="<?php echo $input; ?>"><option value="">— Not linked —</option><?php foreach ($students as $st): ?><option value="<?php echo $st['id']; ?>"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['reg_no'] . ')'); ?></option><?php endforeach; ?></select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="<?php echo $label; ?>">Amount *</label><input type="number" step="0.01" min="0.01" name="amount" required value="<?php echo $old('amount'); ?>" class="<?php echo $input; ?>"></div>
                <div><label class="<?php echo $label; ?>">Date</label><input type="date" name="sale_date" value="<?php echo $old('sale_date', date('Y-m-d')); ?>" class="<?php echo $input; ?>"></div>
            </div>
            <div><label class="<?php echo $label; ?>">Description</label><input name="description" value="<?php echo $old('description'); ?>" class="<?php echo $input; ?>"></div>
            <div><label class="<?php echo $label; ?>">Notes</label><textarea name="notes" rows="2" class="<?php echo $input; ?>"><?php echo $old('notes'); ?></textarea></div>
            <button class="w-full py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold rounded-xl text-xs">Record Sale</button>
        </form>
    </div>
    <div class="lg:col-span-2 space-y-3">
        <form method="GET" class="flex gap-2 items-center">
            <input type="hidden" name="tab" value="sales">
            <input type="month" name="month" value="<?php echo $f_month; ?>" class="<?php echo $input; ?> w-48">
            <button class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold">Filter</button>
            <?php if ($f_month): ?><a href="sales.php?tab=sales" class="text-[11px] text-blue-600 hover:underline">All time</a><?php endif; ?>
            <span class="flex-1"></span>
            <span class="text-xs text-slate-600"><?php echo count($sales); ?> sale(s), <b><?php echo format_currency(array_sum(array_column($sales, 'amount'))); ?></b></span>
        </form>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm overflow-x-auto">
            <?php if (!$sales): ?><p class="text-xs text-slate-400 text-center py-6">No sales recorded<?php echo $f_month ? ' for this month' : ''; ?>.</p><?php else: ?>
            <table class="w-full text-left text-xs">
                <thead><tr class="text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100"><th class="pb-2">Date</th><th class="pb-2">Customer</th><th class="pb-2">What</th><th class="pb-2">By</th><th class="pb-2 text-right">Amount</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($sales as $r): ?>
                        <tr>
                            <td class="py-2.5 whitespace-nowrap"><?php echo date('d M Y', strtotime($r['sale_date'])); ?></td>
                            <td class="py-2.5"><b class="text-slate-800"><?php echo sanitize($r['customer_name']); ?></b><?php echo $r['reg_no'] ? '<span class="block font-mono text-[10px] text-emerald-700">Student ' . sanitize($r['reg_no']) . '</span>' : ''; ?></td>
                            <td class="py-2.5 text-slate-600"><?php echo SALE_TYPES[$r['sale_type']]; ?><?php echo $r['course_title'] ? '<span class="block text-[10px]">' . sanitize($r['course_title']) . '</span>' : ''; ?><?php echo $r['description'] ? '<span class="block text-[10px]">' . sanitize($r['description']) . '</span>' : ''; ?></td>
                            <td class="py-2.5 text-slate-500"><?php echo sanitize($r['recorded_by_name'] ?? '—'); ?></td>
                            <td class="py-2.5 text-right font-bold text-slate-900"><?php echo format_currency($r['amount']); ?></td>
                            <td class="py-2.5 text-right">
                                <form method="POST" action="sales.php?tab=sales" onsubmit="return confirm('Delete this sale record?');">
                                    <?php echo $csrf; ?>
                                    <input type="hidden" name="action" value="delete_sale">
                                    <input type="hidden" name="sale_id" value="<?php echo $r['id']; ?>">
                                    <button class="text-[10px] text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Show the course or service picker that matches "Interested in".
    document.querySelectorAll('.prospect-form').forEach(form => {
        const type = form.querySelector('.interest-type');
        const course = form.querySelector('.interest-course');
        const service = form.querySelector('.interest-service');
        if (!type) return;
        const sync = () => {
            course.classList.toggle('hidden', type.value !== 'COURSE');
            service.classList.toggle('hidden', type.value !== 'SERVICE');
        };
        type.addEventListener('change', sync);
        sync();
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
