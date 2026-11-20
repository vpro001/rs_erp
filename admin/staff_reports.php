<?php
// admin/staff_reports.php
// Admin/manager view of all staff daily/weekly reports.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Staff Reports";
$errors = [];

// Only ADMIN/MANAGER can view this page.
if (!is_admin_user()) {
    set_flash_message('error', 'Access Denied: you do not have permission to view that resource.');
    header("Location: " . BASE_URL . "/admin/index.php");
    exit();
}

// ---- Filters ----
$filter_staff = $_GET['staff_id'] ?? '';
$filter_type = $_GET['report_type'] ?? '';
$filter_status = $_GET['report_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT r.*, s.staff_id, s.employee_id, s.department, s.position,
               u.first_name, u.last_name, u.email
        FROM staff_reports r
        JOIN staff s ON s.id = r.staff_id
        JOIN users_staff u ON u.id = s.user_id
        WHERE 1=1";
$params = [];
$types = '';

if ($filter_staff !== '') {
    $sql .= " AND r.staff_id = ?";
    $params[] = $filter_staff;
    $types .= 's';
}
if ($filter_type !== '') {
    $sql .= " AND r.report_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}
if ($filter_status !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($date_from !== '') {
    $sql .= " AND r.report_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $sql .= " AND r.report_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}
$sql .= " ORDER BY r.report_date DESC, r.submitted_at DESC";

$reports = [];
try {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Load all staff for the filter dropdown.
$staff_list = [];
try {
    $res = $conn->query("SELECT s.id, s.staff_id, u.first_name, u.last_name FROM staff s JOIN users_staff u ON u.id = s.user_id WHERE s.status = 'ACTIVE' ORDER BY u.first_name, u.last_name");
    while ($row = $res->fetch_assoc()) {
        $staff_list[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

$report_types = ['DAILY' => 'Daily', 'WEEKLY' => 'Weekly'];
$statuses = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Submitted'];
$status_badge = [
    'DRAFT' => 'bg-amber-50 text-amber-700 border-amber-200',
    'SUBMITTED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
];
$type_badge = [
    'DAILY' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'WEEKLY' => 'bg-amber-50 text-amber-700 border-amber-200',
];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ── Filter Bar ── -->
<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8 max-w-5xl">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Staff member</label>
            <select name="staff_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400">
                <option value="">All staff</option>
                <?php foreach ($staff_list as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $filter_staff === $s['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?> (<?php echo sanitize($s['staff_id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Report type</label>
            <select name="report_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400">
                <option value="">All</option>
                <?php foreach ($report_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filter_type === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Status</label>
            <select name="report_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400">
                <option value="">All</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button class="w-full py-2.5 bg-[#17264A] hover:bg-[#22355f] text-white font-bold rounded-lg text-xs">Apply filters</button>
        </div>
    </form>

    <?php if ($date_from !== '' || $date_to !== '' || $filter_staff !== '' || $filter_type !== '' || $filter_status !== ''): ?>
        <a href="staff_reports.php" class="mt-3 inline-block text-xs font-semibold text-slate-500 hover:underline">Clear filters</a>
    <?php endif; ?>
</div>

<!-- ── Reports Table ── -->
<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
    <div class="flex items-center justify-between mb-6">
        <h4 class="text-base font-bold text-slate-900 flex items-center space-x-2">
            <i data-lucide="file-text" class="w-5 h-5 text-[#17264A]"></i>
            All Staff Reports
        </h4>
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?php echo count($reports); ?> report(s)</span>
    </div>

    <?php if (empty($reports)): ?>
        <div class="text-center py-10 text-sm text-slate-400">
            <i data-lucide="archive" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
            <p>No staff reports found.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] text-slate-400 uppercase font-semibold">
                        <th class="pb-3 font-medium text-slate-700">Staff</th>
                        <th class="pb-3 font-medium text-slate-700">Date</th>
                        <th class="pb-3 font-medium text-slate-700">Type</th>
                        <th class="pb-3 font-medium text-slate-700">Status</th>
                        <th class="pb-3 font-medium text-slate-700">Submitted</th>
                        <th class="pb-3 font-medium text-slate-700 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($reports as $r): ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="py-3">
                                <div class="text-slate-800 font-medium"><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></div>
                                <div class="text-[10px] text-slate-500 font-mono"><?php echo sanitize($r['staff_id']); ?></div>
                            </td>
                            <td class="py-3 text-slate-800">
                                <?php echo date('M d, Y', strtotime($r['report_date'])); ?>
                                <?php if ($r['report_type'] === 'WEEKLY' && $r['week_start_date']): ?>
                                    <br><span class="text-[10px] text-slate-500">Week of <?php echo date('M d', strtotime($r['week_start_date'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-slate-600">
                                <span class="px-2 py-0.5 rounded border text-[10px] font-bold <?php echo $type_badge[$r['report_type']] ?? ''; ?>">
                                    <?php echo ucfirst(strtolower($r['report_type'])); ?>
                                </span>
                            </td>
                            <td class="py-3 text-slate-600">
                                <span class="px-2 py-0.5 rounded border text-[10px] font-bold <?php echo $status_badge[$r['status']] ?? ''; ?>">
                                    <?php echo ucfirst(strtolower($r['status'])); ?>
                                </span>
                            </td>
                            <td class="py-3 text-slate-500 text-[11px]">
                                <?php echo $r['submitted_at'] ? date('M d, Y H:i', strtotime($r['submitted_at'])) : '—'; ?>
                            </td>
                            <td class="py-3 text-right">
                                <button onclick="toggleReportDetail('detail-<?php echo $r['id']; ?>')" class="text-xs text-blue-600 hover:underline">View details</button>
                            </td>
                        </tr>
                        <!-- Detail row -->
                        <tr id="detail-<?php echo $r['id']; ?>" class="hidden bg-slate-50 border-t border-slate-200">
                            <td colspan="6" class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <h6 class="text-[10px] font-bold text-slate-400 uppercase mb-1">1. Independent Productivity</h6>
                                        <p class="text-sm text-slate-700 whitespace-pre-line mb-3"><?php echo nl2br(sanitize($r['independent_productivity'])); ?></p>
                                        <h6 class="text-[10px] font-bold text-slate-400 uppercase mb-1">2. Assigned Responsibilities</h6>
                                        <p class="text-sm text-slate-700 whitespace-pre-line"><?php echo nl2br(sanitize($r['assigned_responsibilities'])); ?></p>
                                    </div>
                                    <div>
                                        <h6 class="text-[10px] font-bold text-slate-400 uppercase mb-1">3. Task Progress and Completion</h6>
                                        <p class="text-sm text-slate-700 whitespace-pre-line mb-3"><?php echo nl2br(sanitize($r['task_progress_completion'])); ?></p>
                                        <h6 class="text-[10px] font-bold text-slate-400 uppercase mb-1">4. Additional Records</h6>
                                        <p class="text-sm text-slate-700 whitespace-pre-line"><?php echo nl2br(sanitize($r['additional_records'])); ?></p>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t border-slate-200 flex justify-between items-center text-xs text-slate-500">
                                    <span>Report type: <?php echo ucfirst(strtolower($r['report_type'])); ?> &bull; Status: <?php echo ucfirst(strtolower($r['status'])); ?></span>
                                    <span>Week start: <?php echo $r['week_start_date'] ? date('M d, Y', strtotime($r['week_start_date'])) : '—'; ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleReportDetail(id) {
        const row = document.getElementById(id);
        if (!row) return;
        const isHidden = row.classList.contains('hidden');
        // Close all other open detail rows
        document.querySelectorAll('tr[id^="detail-"]').forEach(r => r.classList.add('hidden'));
        if (isHidden) row.classList.remove('hidden');
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
