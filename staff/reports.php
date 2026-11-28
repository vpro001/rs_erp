<?php
// staff/reports.php
// Staff daily/weekly report logging. Captures independent productivity, assigned
// responsibilities, task progress/completion, and additional records.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "My Reports";
$errors = [];
$success = '';

// Admins/managers use the admin staff reports page instead.
if (is_admin_user()) {
    header("Location: " . BASE_URL . "/admin/staff_reports.php");
    exit();
}

// Resolve the staff row for the logged-in user.
$staff = null;
try {
    $stmt = $conn->prepare("SELECT s.*, u.email, u.first_name, u.last_name FROM staff s JOIN users_staff u ON u.id = s.user_id WHERE s.user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $errors[] = "Unable to load your staff record.";
}
$staff_id = $staff ? $staff['id'] : null;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

// ---- Handle report submission (create or update a draft / submit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    if (!$staff_id) {
        $errors[] = "Your staff record is not available.";
    } else {
        $action = $_POST['action'];
        try {
            if ($action === 'delete') {
                // Delete only needs the report pk; skip full-form validation.
                $report_pk = sanitize($_POST['report_pk'] ?? '');
                $stmt = $conn->prepare("SELECT id, status FROM staff_reports WHERE id = ? AND staff_id = ? AND status = 'DRAFT'");
                $stmt->bind_param("ss", $report_pk, $staff_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$existing) {
                    throw new Exception("Only draft reports can be deleted.");
                }
                $stmt = $conn->prepare("DELETE FROM staff_reports WHERE id = ? AND staff_id = ?");
                $stmt->bind_param("ss", $report_pk, $staff_id);
                $stmt->execute();
                $stmt->close();
                log_activity("Staff deleted draft report", ["report_pk" => $report_pk]);
                set_flash_message('success', "Draft report deleted.");
                header("Location: reports.php");
                exit();
            }

            $report_type = $_POST['report_type'] ?? '';
            if (!in_array($report_type, ['DAILY', 'WEEKLY'], true)) {
                throw new Exception("Choose a valid report type.");
            }
            $report_date = $_POST['report_date'] ?? '';
            if ($report_date === '') {
                throw new Exception("A report date is required.");
            }
            $week_start_date = '';
            if ($report_type === 'WEEKLY') {
                $week_start_date = $_POST['week_start_date'] ?? '';
                if ($week_start_date === '') {
                    // Default to Monday of the report week.
                    $week_start_date = date('Y-m-d', strtotime('monday this week', strtotime($report_date)));
                }
            }
            $week_start_db = $week_start_date !== '' ? $week_start_date : null;

            $independent_productivity = trim($_POST['independent_productivity'] ?? '');
            $assigned_responsibilities = trim($_POST['assigned_responsibilities'] ?? '');
            $task_progress_completion = trim($_POST['task_progress_completion'] ?? '');
            $additional_records = trim($_POST['additional_records'] ?? '');

            if ($independent_productivity === '' || $assigned_responsibilities === '' ||
                $task_progress_completion === '' || $additional_records === '') {
                throw new Exception("All four report sections are required.");
            }

            $status = $_POST['status'] ?? 'DRAFT';
            if (!in_array($status, ['DRAFT', 'SUBMITTED'], true)) {
                $status = 'DRAFT';
            }

            if ($action === 'create') {
                // Prevent duplicate report for the same staff/type/date.
                $stmt = $conn->prepare("SELECT id FROM staff_reports WHERE staff_id = ? AND report_type = ? AND report_date = ?");
                $stmt->bind_param("sss", $staff_id, $report_type, $report_date);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    throw new Exception("A " . strtolower($report_type) . " report for " . date('M d, Y', strtotime($report_date)) . " already exists. Edit it instead.");
                }
                $stmt->close();

                $id = generate_uuid();
                $stmt = $conn->prepare("INSERT INTO staff_reports (id, staff_id, report_type, report_date, week_start_date, independent_productivity, assigned_responsibilities, task_progress_completion, additional_records, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssss", $id, $staff_id, $report_type, $report_date, $week_start_db, $independent_productivity, $assigned_responsibilities, $task_progress_completion, $additional_records, $status);
                $stmt->execute();
                $stmt->close();

                log_activity("Staff submitted " . strtolower($report_type) . " report", ["report_date" => $report_date, "status" => $status]);

                // Notify admins/managers when a report is submitted.
                if ($status === 'SUBMITTED') {
                    notify_admins("New " . strtolower($report_type) . " staff report", $staff['first_name'] . ' ' . $staff['last_name'] . " submitted a " . strtolower($report_type) . " report for " . date('M d, Y', strtotime($report_date)) . ".", "/admin/staff_reports.php");
                }

                set_flash_message('success', "Your " . strtolower($report_type) . " report has been " . ($status === 'SUBMITTED' ? 'submitted' : 'saved as draft') . ".");
                header("Location: reports.php");
                exit();
            } elseif ($action === 'update') {
                $report_pk = sanitize($_POST['report_pk'] ?? '');
                $stmt = $conn->prepare("SELECT id, status FROM staff_reports WHERE id = ? AND staff_id = ?");
                $stmt->bind_param("ss", $report_pk, $staff_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$existing) {
                    throw new Exception("Report not found.");
                }

                // Prevent moving onto a date that already has a report (different row).
                $stmt = $conn->prepare("SELECT id FROM staff_reports WHERE staff_id = ? AND report_type = ? AND report_date = ? AND id <> ?");
                $stmt->bind_param("ssss", $staff_id, $report_type, $report_date, $report_pk);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    throw new Exception("A " . strtolower($report_type) . " report for " . date('M d, Y', strtotime($report_date)) . " already exists.");
                }
                $stmt->close();

                $stmt = $conn->prepare("UPDATE staff_reports SET report_type = ?, report_date = ?, week_start_date = ?, independent_productivity = ?, assigned_responsibilities = ?, task_progress_completion = ?, additional_records = ?, status = ?, updated_at = NOW() WHERE id = ? AND staff_id = ?");
                $stmt->bind_param("ssssssssss", $report_type, $report_date, $week_start_db, $independent_productivity, $assigned_responsibilities, $task_progress_completion, $additional_records, $status, $report_pk, $staff_id);
                $stmt->execute();
                $stmt->close();

                log_activity("Staff updated " . strtolower($report_type) . " report", ["report_date" => $report_date, "status" => $status]);

                if ($status === 'SUBMITTED') {
                    notify_admins("New " . strtolower($report_type) . " staff report", $staff['first_name'] . ' ' . $staff['last_name'] . " submitted a " . strtolower($report_type) . " report for " . date('M d, Y', strtotime($report_date)) . ".", "/admin/staff_reports.php");
                }

                set_flash_message('success', "Your " . strtolower($report_type) . " report has been " . ($status === 'SUBMITTED' ? 'submitted' : 'saved as draft') . ".");
                header("Location: reports.php");
                exit();
            } else {
                throw new Exception("Unknown action.");
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// ---- Load the staff member's reports ----
$reports = [];
try {
    $stmt = $conn->prepare("SELECT * FROM staff_reports WHERE staff_id = ? ORDER BY report_date DESC, submitted_at DESC");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $errors[] = "Unable to load your reports.";
}

// Pre-fill values for the form (from GET for a new report, or from an existing report being edited).
$edit_report = null;
if (isset($_GET['edit']) && $staff_id) {
    $rp = $conn->real_escape_string($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM staff_reports WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("ss", $rp, $staff_id);
    $stmt->execute();
    $edit_report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$default_date = date('Y-m-d');
$default_week_start = date('Y-m-d', strtotime('monday this week'));

$report_types = [
    'DAILY' => 'Daily Report',
    'WEEKLY' => 'Weekly Report',
];
$status_badge = [
    'DRAFT' => 'bg-amber-50 text-amber-700 border-amber-200',
    'SUBMITTED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ── Report Form ── -->
<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8 max-w-3xl">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-base font-bold text-slate-900 flex items-center space-x-2">
            <i data-lucide="file-text" class="w-5 h-5 text-[#17264A]"></i>
            <?php echo $edit_report ? 'Edit Report' : 'Submit a New Report'; ?>
        </h4>
        <a href="reports.php" class="text-xs font-semibold text-slate-500 hover:text-[#17264A] underline underline-offset-4">View all reports</a>
    </div>
    <p class="text-[11px] text-slate-500 mb-6">Log your daily or weekly work. Each report captures four sections: independent productivity, assigned responsibilities, task progress & completion, and additional records.</p>

    <form method="POST" class="space-y-4">
        <?php echo $csrf; ?>
        <?php if ($edit_report): ?><input type="hidden" name="action" value="update"><input type="hidden" name="report_pk" value="<?php echo $edit_report['id']; ?>"><?php else: ?><input type="hidden" name="action" value="create"><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Report type</label>
                <select name="report_type" id="report-type" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400">
                    <?php foreach ($report_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo (($edit_report ? $edit_report['report_type'] : $_POST['report_type'] ?? 'DAILY') === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Report date</label>
                <input type="date" name="report_date" id="report-date" required
                    value="<?php echo $edit_report ? $edit_report['report_date'] : ($_POST['report_date'] ?? $default_date); ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400">
            </div>
        </div>

        <div id="week-start-field" class="<?php echo (($edit_report ? $edit_report['report_type'] : $_POST['report_type'] ?? 'DAILY') !== 'WEEKLY') ? 'hidden' : ''; ?>">
            <div>
                <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Week start (Monday)</label>
                <input type="date" name="week_start_date"
                    value="<?php echo $edit_report ? ($edit_report['week_start_date'] ?: $default_week_start) : ($_POST['week_start_date'] ?? $default_week_start); ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400">
            </div>
        </div>

        <!-- Section 1: Independent Productivity -->
        <div class="border-t border-slate-100 pt-4">
            <h5 class="text-sm font-bold text-slate-900 mb-2 flex items-center space-x-2">
                <i data-lucide="zap" class="w-4 h-4 text-[#17264A]"></i>
                1. Independent Productivity
            </h5>
            <p class="text-[11px] text-slate-500 mb-2">What productive activities did you carry out on your own without supervision?</p>
            <textarea name="independent_productivity" rows="4" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-400"><?php echo sanitize($edit_report ? $edit_report['independent_productivity'] : ($_POST['independent_productivity'] ?? '')); ?></textarea>
        </div>

        <!-- Section 2: Assigned Responsibilities -->
        <div class="border-t border-slate-100 pt-4">
            <h5 class="text-sm font-bold text-slate-900 mb-2 flex items-center space-x-2">
                <i data-lucide="check-square" class="w-4 h-4 text-[#17264A]"></i>
                2. Assigned Responsibilities
            </h5>
            <p class="text-[11px] text-slate-500 mb-2">What specific tasks were assigned to you during this report period?</p>
            <textarea name="assigned_responsibilities" rows="4" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-400"><?php echo sanitize($edit_report ? $edit_report['assigned_responsibilities'] : ($_POST['assigned_responsibilities'] ?? '')); ?></textarea>
        </div>

        <!-- Section 3: Task Progress and Completion -->
        <div class="border-t border-slate-100 pt-4">
            <h5 class="text-sm font-bold text-slate-900 mb-2 flex items-center space-x-2">
                <i data-lucide="clipboard-check" class="w-4 h-4 text-[#17264A]"></i>
                3. Task Progress and Completion
            </h5>
            <p class="text-[11px] text-slate-500 mb-2">What tasks did you initiate, work on, and successfully complete?</p>
            <textarea name="task_progress_completion" rows="4" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-400"><?php echo sanitize($edit_report ? $edit_report['task_progress_completion'] : ($_POST['task_progress_completion'] ?? '')); ?></textarea>
        </div>

        <!-- Section 4: Additional Records -->
        <div class="border-t border-slate-100 pt-4">
            <h5 class="text-sm font-bold text-slate-900 mb-2 flex items-center space-x-2">
                <i data-lucide="folder-text" class="w-4 h-4 text-[#17264A]"></i>
                4. Additional Records
            </h5>
            <p class="text-[11px] text-slate-500 mb-2">Any other relevant activities, achievements, challenges, or information that needs to be documented.</p>
            <textarea name="additional_records" rows="4" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-400"><?php echo sanitize($edit_report ? $edit_report['additional_records'] : ($_POST['additional_records'] ?? '')); ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <div class="flex items-center space-x-3 text-xs text-slate-500">
                <span><i data-lucide="clock" class="w-3 h-3 mr-1"></i> Reports are saved as drafts by default.</span>
            </div>
            <div class="flex space-x-2">
                <button type="submit" onclick="document.getElementById('status-input').value='DRAFT'" class="px-3 py-1.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Save as Draft</button>
                <input type="hidden" name="status" id="status-input" value="<?php echo $edit_report ? $edit_report['status'] : 'DRAFT'; ?>">
                <button type="submit" onclick="document.getElementById('status-input').value='SUBMITTED'" class="px-4 py-1.5 bg-[#17264A] hover:bg-[#22355f] text-white font-bold rounded-lg text-xs">Submit Report</button>
            </div>
        </div>
    </form>
</div>

<!-- ── Report History ── -->
<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
    <div class="flex items-center justify-between mb-6">
        <h4 class="text-base font-bold text-slate-900 flex items-center space-x-2">
            <i data-lucide="clock" class="w-5 h-5 text-[#17264A]"></i>
            Report History
        </h4>
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?php echo count($reports); ?> report(s)</span>
    </div>

    <?php if (empty($reports)): ?>
        <div class="text-center py-10 text-sm text-slate-400">
            <i data-lucide="archive" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
            <p>No reports yet. Submit your first daily or weekly report above.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] text-slate-400 uppercase font-semibold">
                        <th class="pb-3 font-medium text-slate-700">Date</th>
                        <th class="pb-3 font-medium text-slate-700">Type</th>
                        <th class="pb-3 font-medium text-slate-700">Status</th>
                        <th class="pb-3 font-medium text-slate-700">Submitted</th>
                        <th class="pb-3 font-medium text-slate-700 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($reports as $r): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 text-slate-800 font-medium">
                                <?php echo date('M d, Y', strtotime($r['report_date'])); ?>
                                <?php if ($r['report_type'] === 'WEEKLY' && $r['week_start_date']): ?>
                                    <br><span class="text-[10px] text-slate-500">Week of <?php echo date('M d', strtotime($r['week_start_date'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-slate-600">
                                <span class="px-2 py-0.5 rounded border text-[10px] font-bold <?php echo $r['report_type'] === 'DAILY' ? 'bg-cyan-50 text-cyan-700 border-cyan-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?>">
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
                                <?php if ($r['status'] === 'DRAFT'): ?>
                                    <a href="reports.php?edit=<?php echo $r['id']; ?>" class="text-xs text-blue-600 hover:underline mr-3">Edit</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this draft report?');">
                                        <?php echo $csrf; ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="report_pk" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <a href="reports.php?edit=<?php echo $r['id']; ?>" class="text-xs text-blue-600 hover:underline">View / Edit</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    // Show/hide the week-start field based on report type.
    (function () {
        const typeSelect = document.getElementById('report-type');
        const weekField = document.getElementById('week-start-field');
        if (!typeSelect || !weekField) return;
        const toggle = () => {
            weekField.classList.toggle('hidden', typeSelect.value !== 'WEEKLY');
        };
        typeSelect.addEventListener('change', toggle);
        toggle();
    })();
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
