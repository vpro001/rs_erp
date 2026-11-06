<?php
// staff/role_dashboard.php - dashboard for Marketing & Sales, Customer Service and Head Admin staff.
// Rendered by staff/index.php (instructors keep the class dashboard there).
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$role = staff_role();
if (!$role || $role === 'INSTRUCTOR') {
    header("Location: " . BASE_URL . "/staff/index.php");
    exit();
}
$page_title = STAFF_ROLES[$role] . " Dashboard";
$one = fn($sql) => $conn->query($sql)->fetch_assoc();
$all = fn($sql) => $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$month_start = date('Y-m-01');

$cards = [];   // [label, value, sub, icon, link]
$lists = [];   // [title, link, rows[[main, sub, right]] , empty text]
$actions = []; // [url, icon, label]

if ($role === 'MARKETING') {
    $open = $one("SELECT COUNT(*) AS n FROM prospects WHERE status NOT IN ('WON','LOST')");
    $sales = $one("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS v FROM sales_records WHERE sale_date >= '$month_start'");
    $appts = $one("SELECT COUNT(*) AS n FROM sales_appointments WHERE status = 'BOOKED' AND scheduled_at >= NOW()");
    $refs = $one("SELECT COUNT(*) AS n FROM marketer_student_referrals WHERE status = 'PENDING'");
    $mk = $one("SELECT COUNT(*) AS n FROM users_marketer WHERE status = 'ACTIVE'");
    $cards = [
        ['Open prospects', $open['n'], 'in the pipeline', 'target', '/staff/sales.php'],
        ['Upcoming meetings', $appts['n'], 'appointments & demos', 'calendar-clock', '/staff/sales.php?tab=appointments'],
        ['Sales this month', format_currency($sales['v']), $sales['n'] . ' sale(s)', 'trending-up', '/staff/sales.php?tab=sales'],
        ['Marketers', $mk['n'], $refs['n'] . ' referral(s) to review', 'megaphone', '/staff/marketers.php'],
    ];
    $rows = [];
    foreach ($all("SELECT a.type, a.scheduled_at, a.location, p.full_name, p.phone FROM sales_appointments a JOIN prospects p ON p.id = a.prospect_id WHERE a.status = 'BOOKED' AND a.scheduled_at >= NOW() ORDER BY a.scheduled_at LIMIT 6") as $r) {
        $rows[] = [$r['full_name'], ucfirst(strtolower($r['type'])) . ($r['location'] ? ' · ' . $r['location'] : ''), date('D d M, g:ia', strtotime($r['scheduled_at']))];
    }
    $lists[] = ['Upcoming appointments & demos', '/staff/sales.php?tab=appointments', $rows, 'Nothing booked.'];
    $rows = [];
    foreach ($all("SELECT full_name, phone, email, next_follow_up, status FROM prospects WHERE status NOT IN ('WON','LOST') AND next_follow_up IS NOT NULL AND next_follow_up <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) ORDER BY next_follow_up LIMIT 6") as $r) {
        $rows[] = [$r['full_name'], $r['phone'] ?: $r['email'], date('d M', strtotime($r['next_follow_up']))];
    }
    $lists[] = ['Follow-ups due', '/staff/sales.php', $rows, 'No follow-ups due in the next 3 days.'];
    $actions = [['/staff/sales.php', 'user-plus', 'Add a prospect'], ['/staff/sales.php?tab=sales', 'badge-dollar-sign', 'Record a sale'], ['/staff/marketers.php?tab=referrals', 'megaphone', 'Review marketer referrals']];

} elseif ($role === 'CUSTOMER_SERVICE') {
    $students = $one("SELECT COUNT(*) AS n FROM students");
    $new = $one("SELECT COUNT(*) AS n FROM enrollments WHERE enrollment_date >= '$month_start'");
    $bal = $one("SELECT COUNT(*) AS n, COALESCE(SUM(amount_due),0) AS v FROM enrollments WHERE amount_due > 0.005 AND status NOT IN ('DROPPED')");
    $nobatch = $one("SELECT COUNT(*) AS n FROM enrollments WHERE batch_id IS NULL AND status = 'ACTIVE'");
    $cards = [
        ['Students', $students['n'], 'registered', 'users', '/staff/students.php'],
        ['New enrollments', $new['n'], 'this month', 'clipboard-list', '/staff/enrollments.php'],
        ['Outstanding balances', format_currency($bal['v']), $bal['n'] . ' enrollment(s)', 'wallet', '/staff/enrollments.php'],
        ['Without a batch', $nobatch['n'], 'active enrollments', 'calendar-x', '/staff/enrollments.php?status=ACTIVE'],
    ];
    $rows = [];
    foreach ($all("SELECT ip.amount, ip.due_date, u.first_name, u.last_name, s.student_id AS reg_no, c.title FROM installment_payments ip JOIN enrollments e ON e.id = ip.enrollment_id JOIN students s ON s.id = e.student_id JOIN users_student u ON u.id = s.user_id JOIN courses c ON c.id = e.course_id WHERE ip.status IN ('PENDING','OVERDUE') AND ip.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY ip.due_date LIMIT 6") as $r) {
        $rows[] = [$r['first_name'] . ' ' . $r['last_name'], $r['reg_no'] . ' · ' . html_entity_decode($r['title']), format_currency($r['amount']) . ' · ' . date('d M', strtotime($r['due_date']))];
    }
    $lists[] = ['Installments due / overdue', '/staff/enrollments.php', $rows, 'No installments due this week.'];
    $rows = [];
    foreach ($all("SELECT e.enrollment_date, u.first_name, u.last_name, s.student_id AS reg_no, c.title, e.start_date FROM enrollments e JOIN students s ON s.id = e.student_id JOIN users_student u ON u.id = s.user_id JOIN courses c ON c.id = e.course_id ORDER BY e.enrollment_date DESC LIMIT 6") as $r) {
        $rows[] = [$r['first_name'] . ' ' . $r['last_name'], $r['reg_no'] . ' · ' . html_entity_decode($r['title']), $r['start_date'] ? 'starts ' . date('d M', strtotime($r['start_date'])) : ''];
    }
    $lists[] = ['Recent enrollments', '/staff/enrollments.php', $rows, 'No enrollments yet.'];
    $actions = [['/staff/students.php', 'user-plus', 'Register a student'], ['/staff/enrollments.php', 'clipboard-list', 'Enroll in a course / record payment'], ['/staff/batches.php', 'calendar-range', 'Manage class batches']];

} else { // HEAD_ADMIN
    $cert = "LEFT JOIN certificates cert ON cert.student_id = e.student_id AND cert.course_id = e.course_id AND cert.is_valid = 1";
    $awaiting = $one("SELECT COUNT(*) AS n FROM enrollments e $cert WHERE e.payment_certified_at IS NULL AND e.amount_due <= 0.005 AND e.status NOT IN ('DROPPED','SUSPENDED') AND cert.id IS NULL");
    $ready = $one("SELECT COUNT(*) AS n FROM enrollments e $cert WHERE e.payment_certified_at IS NOT NULL AND cert.id IS NULL AND e.end_date <= CURDATE() AND e.status NOT IN ('DROPPED','SUSPENDED')");
    $running = $one("SELECT COUNT(*) AS n FROM enrollments e $cert WHERE e.payment_certified_at IS NOT NULL AND cert.id IS NULL AND (e.end_date IS NULL OR e.end_date > CURDATE())");
    $issued = $one("SELECT COUNT(*) AS n FROM certificates WHERE is_valid = 1 AND issue_date >= '$month_start'");
    $cards = [
        ['Awaiting payment certification', $awaiting['n'], 'balance cleared', 'wallet', '/staff/certification.php?view=awaiting'],
        ['Ready for certificate', $ready['n'], 'paid & course ended', 'badge-check', '/staff/certification.php?view=ready'],
        ['Certified, still running', $running['n'], 'certificate after end date', 'hourglass', '/staff/certification.php?view=in_progress'],
        ['Certificates issued', $issued['n'], 'this month', 'award', '/staff/certification.php?view=issued'],
    ];
    $rows = [];
    foreach ($all("SELECT e.end_date, u.first_name, u.last_name, s.student_id AS reg_no, c.title FROM enrollments e $cert JOIN students s ON s.id = e.student_id JOIN users_student u ON u.id = s.user_id JOIN courses c ON c.id = e.course_id WHERE e.payment_certified_at IS NOT NULL AND cert.id IS NULL AND e.end_date <= CURDATE() AND e.status NOT IN ('DROPPED','SUSPENDED') ORDER BY e.end_date LIMIT 6") as $r) {
        $rows[] = [$r['first_name'] . ' ' . $r['last_name'], $r['reg_no'] . ' · ' . html_entity_decode($r['title']), 'ended ' . date('d M Y', strtotime($r['end_date']))];
    }
    $lists[] = ['Ready for certificate', '/staff/certification.php?view=ready', $rows, 'No one is waiting for a certificate.'];
    $rows = [];
    foreach ($all("SELECT e.end_date, e.start_date, u.first_name, u.last_name, s.student_id AS reg_no, c.title FROM enrollments e JOIN students s ON s.id = e.student_id JOIN users_student u ON u.id = s.user_id JOIN courses c ON c.id = e.course_id WHERE e.status = 'ACTIVE' AND e.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY e.end_date LIMIT 6") as $r) {
        $rows[] = [$r['first_name'] . ' ' . $r['last_name'], $r['reg_no'] . ' · ' . html_entity_decode($r['title']), 'ends ' . date('d M Y', strtotime($r['end_date']))];
    }
    $lists[] = ['Courses ending in the next 30 days', '/staff/certification.php?view=all', $rows, 'No courses ending soon.'];
    $actions = [['/staff/certification.php?view=awaiting', 'wallet', 'Certify payments'], ['/staff/certification.php?view=ready', 'badge-check', 'Authorize certificates'], ['/staff/certification.php?view=all', 'users', 'Browse student records']];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
    <?php foreach ($cards as [$label, $value, $sub, $icon, $link]): ?>
        <a href="<?php echo BASE_URL . $link; ?>" class="glass-card card-hoverable p-5 rounded-2xl flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-display font-semibold text-slate-400 uppercase tracking-[0.12em] block"><?php echo $label; ?></span>
                <h3 class="text-2xl font-display font-bold text-slate-900"><?php echo $value; ?></h3>
                <p class="text-xs text-slate-500"><?php echo sanitize($sub); ?></p>
            </div>
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-[#17264A]/10 text-[#17264A] border border-[#17264A]/15">
                <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <?php foreach ($lists as [$title, $link, $rows, $empty]): ?>
        <div class="glass-card p-6 rounded-2xl">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-sm font-display font-bold text-slate-800"><?php echo $title; ?></h4>
                <a href="<?php echo BASE_URL . $link; ?>" class="text-[11px] font-semibold text-blue-600 hover:underline">View all</a>
            </div>
            <?php if (!$rows): ?>
                <p class="text-xs text-slate-400 text-center py-6"><?php echo $empty; ?></p>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($rows as [$main, $sub, $right]): ?>
                        <div class="py-2.5 flex justify-between gap-3 text-xs">
                            <div class="min-w-0"><span class="font-semibold text-slate-800 block truncate"><?php echo sanitize($main); ?></span><span class="text-[11px] text-slate-500 block truncate"><?php echo sanitize($sub); ?></span></div>
                            <span class="text-[11px] font-semibold text-slate-600 whitespace-nowrap"><?php echo sanitize($right); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="glass-card p-6 rounded-2xl space-y-2">
        <h4 class="text-sm font-display font-bold text-slate-800 mb-2">Quick actions</h4>
        <?php foreach ($actions as [$url, $icon, $text]): ?>
            <a href="<?php echo BASE_URL . $url; ?>" class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-slate-300 text-xs font-semibold text-slate-700">
                <span class="flex items-center gap-2"><i data-lucide="<?php echo $icon; ?>" class="w-4 h-4"></i><?php echo $text; ?></span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
