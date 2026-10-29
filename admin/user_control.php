<?php
// admin/user_control.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/admission_letter.php';

$user = current_user();
$page_title = "Global User Administration";

$errors = [];
$success_msg = "";

// enrollments/payments reference students.id, not the login id; create the record on first use.
function student_record_id($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return $row['id'];

    $id = generate_uuid();
    $reg = next_registration_number($conn, (int) date('Y'), (int) date('n'));
    $stmt = $conn->prepare("INSERT INTO students (id, user_id, student_id, lecture_mode, profile_complete) VALUES (?, ?, ?, 'ONLINE', 1)");
    $stmt->bind_param("sss", $id, $user_id, $reg);
    $stmt->execute();
    $stmt->close();
    return $id;
}

/**
 * Resolve the batch and start/end dates for an enrollment from the posted form.
 * Dates default to the batch's dates; either can be overridden per student.
 *
 * @return array [batch_id|null, start Y-m-d, end Y-m-d]
 */
function resolve_enrollment_schedule($conn, $course_id, $exclude_enrollment_id = '')
{
    $batch_id = sanitize($_POST['batch_id'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');

    if ($batch_id !== '') {
        $stmt = $conn->prepare("SELECT b.start_date, b.end_date, b.capacity, b.status,
                                       (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.id <> ?) AS enrolled
                                FROM classes b WHERE b.id = ? AND b.course_id = ?");
        $stmt->bind_param("sss", $exclude_enrollment_id, $batch_id, $course_id);
        $stmt->execute();
        $batch = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$batch) throw new Exception("Selected batch does not belong to this course.");
        if ($batch['status'] !== 'ACTIVE') throw new Exception("Selected batch is not open for enrollment.");
        if ($batch['capacity'] && $batch['enrolled'] >= $batch['capacity']) throw new Exception("Selected batch is full ({$batch['capacity']} students).");
        if ($start === '' && $batch['start_date']) $start = $batch['start_date'];
        if ($end === '' && $batch['end_date']) $end = $batch['end_date'];
    }

    if (!strtotime($start) || !strtotime($end)) throw new Exception("Start and end dates are required (choose a batch or enter them).");
    if (strtotime($end) < strtotime($start)) throw new Exception("End date cannot be before the start date.");
    return [$batch_id !== '' ? $batch_id : null, date('Y-m-d', strtotime($start)), date('Y-m-d', strtotime($end))];
}

// Keep the batch roster (class_students, used by staff attendance) in step with the enrollment's batch.
function sync_batch_roster($conn, $student_pk, $course_id, $batch_id)
{
    $stmt = $conn->prepare("DELETE cs FROM class_students cs JOIN classes cl ON cl.id = cs.class_id WHERE cs.student_id = ? AND cl.course_id = ? AND (? IS NULL OR cs.class_id <> ?)");
    $stmt->bind_param("ssss", $student_pk, $course_id, $batch_id, $batch_id);
    $stmt->execute();
    $stmt->close();
    if ($batch_id) {
        $id = generate_uuid();
        $by = current_user()['email'] ?? 'admin';
        $stmt = $conn->prepare("INSERT IGNORE INTO class_students (id, class_id, student_id, assigned_by, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
        $stmt->bind_param("ssss", $id, $batch_id, $student_pk, $by);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle user updates (Status toggle / Role edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $target_user_id = sanitize($_POST['user_id'] ?? '');
    $action = $_POST['action'];
    
    if (empty($target_user_id)) {
        $errors[] = "Invalid user selection.";
    } else {
        try {
            if ($action === 'toggle_status') {
                $new_status = sanitize($_POST['status'] ?? 'ACTIVE');
                if (!in_array($new_status, ['ACTIVE', 'INACTIVE', 'SUSPENDED'])) {
                    throw new Exception("Invalid status selection.");
                }
                
                $table = find_user_table($conn, $target_user_id);
                if (!$table) throw new Exception("User not found.");
                $stmt = $conn->prepare("UPDATE `$table` SET status = ? WHERE id = ?");
                $stmt->bind_param("ss", $new_status, $target_user_id);
                $stmt->execute();
                $stmt->close();
                
                log_activity("Admin toggled user status", ["target_user_id" => $target_user_id, "status" => $new_status]);
                $success_msg = "User status updated successfully!";
            } 
            
            else if ($action === 'change_role') {
                $new_role = sanitize($_POST['role'] ?? 'STUDENT');
                if (!in_array($new_role, ['ADMIN', 'MANAGER'])) {
                    throw new Exception("Only Admin and Manager roles can be switched here; other roles are tied to their own portal account.");
                }
                if (find_user_table($conn, $target_user_id) !== 'users_admin') {
                    throw new Exception("Role can only be changed for Admin/Manager accounts.");
                }
                if ($target_user_id === $user['id']) {
                    throw new Exception("You cannot change your own role.");
                }
                
                $stmt = $conn->prepare("UPDATE users_admin SET role = ? WHERE id = ?");
                $stmt->bind_param("ss", $new_role, $target_user_id);
                $stmt->execute();
                $stmt->close();
                
                log_activity("Admin changed user role", ["target_user_id" => $target_user_id, "role" => $new_role]);
                $success_msg = "User role modified successfully!";
            } else if ($action === 'enroll_student') {
                $course_id = sanitize($_POST['course_id'] ?? '');
                $enrollment_date = sanitize($_POST['enrollment_date'] ?? date('Y-m-d H:i:s'));
                $payment_type = sanitize($_POST['payment_type'] ?? 'FULL');
                $total_paid = floatval($_POST['total_paid'] ?? 0);
                
                if (empty($course_id)) {
                    throw new Exception("Course selection is required.");
                }
                
                // Fetch course details
                $stmt = $conn->prepare("SELECT price, allow_installment, installment_count, installment_amount FROM courses WHERE id = ?");
                $stmt->bind_param("s", $course_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $course_data = $res->fetch_assoc();
                $stmt->close();
                
                if (!$course_data) {
                    throw new Exception("Invalid course.");
                }
                
                $price = floatval($course_data['price']);
                $amount_due = max(0, $price - $total_paid);
                [$batch_id, $start_date, $end_date] = resolve_enrollment_schedule($conn, $course_id);

                $id = generate_uuid();
                $conn->begin_transaction();
                try {
                    $student_pk = student_record_id($conn, $target_user_id);
                    $stmt = $conn->prepare("INSERT INTO enrollments (id, student_id, course_id, enrollment_date, status, payment_type, total_paid, amount_due, batch_id, start_date, end_date) VALUES (?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssddsss", $id, $student_pk, $course_id, $enrollment_date, $payment_type, $total_paid, $amount_due, $batch_id, $start_date, $end_date);
                    $stmt->execute();
                    $stmt->close();
                    sync_batch_roster($conn, $student_pk, $course_id, $batch_id);
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e->getCode() === 1062 ? new Exception("Student is already enrolled in this course.") : $e;
                }

                log_activity("Admin enrolled student in course", ["target_user_id" => $target_user_id, "course_id" => $course_id, "batch_id" => $batch_id]);
                $success_msg = "Student enrolled successfully!";
                if (isset($_POST['send_admission'])) {
                    $success_msg .= " " . admission_send_summary(send_admission_letter($conn, $id));
                }
            } else if ($action === 'update_enrollment_schedule') {
                $enrollment_id = sanitize($_POST['enrollment_id'] ?? '');
                $stmt = $conn->prepare("SELECT e.student_id, e.course_id FROM enrollments e JOIN students st ON st.id = e.student_id WHERE e.id = ? AND st.user_id = ?");
                $stmt->bind_param("ss", $enrollment_id, $target_user_id);
                $stmt->execute();
                $enr = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$enr) throw new Exception("Enrollment not found.");

                [$batch_id, $start_date, $end_date] = resolve_enrollment_schedule($conn, $enr['course_id'], $enrollment_id);
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE enrollments SET batch_id = ?, start_date = ?, end_date = ? WHERE id = ?");
                    $stmt->bind_param("ssss", $batch_id, $start_date, $end_date, $enrollment_id);
                    $stmt->execute();
                    $stmt->close();
                    sync_batch_roster($conn, $enr['student_id'], $enr['course_id'], $batch_id);
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }

                log_activity("Admin updated enrollment batch/dates", ["target_user_id" => $target_user_id, "enrollment_id" => $enrollment_id]);
                $success_msg = "Enrollment schedule updated.";
                if (isset($_POST['send_admission'])) {
                    $success_msg .= " " . admission_send_summary(send_admission_letter($conn, $enrollment_id));
                }
            } else if ($action === 'send_admission_letter') {
                $enrollment_id = sanitize($_POST['enrollment_id'] ?? '');
                $stmt = $conn->prepare("SELECT 1 FROM enrollments e JOIN students st ON st.id = e.student_id WHERE e.id = ? AND st.user_id = ?");
                $stmt->bind_param("ss", $enrollment_id, $target_user_id);
                $stmt->execute();
                $found = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if (!$found) throw new Exception("Enrollment not found.");

                $result = send_admission_letter($conn, $enrollment_id);
                if (!$result['ok']) throw new Exception(admission_send_summary($result));
                log_activity("Admin sent admission letter", ["target_user_id" => $target_user_id, "enrollment_id" => $enrollment_id]);
                $success_msg = admission_send_summary($result);
            } else if ($action === 'record_flexible_payment') {
                $amount = floatval($_POST['amount'] ?? 0);
                $enrollment_id = sanitize($_POST['enrollment_id'] ?? '');
                $course_id = sanitize($_POST['course_id'] ?? '');
                
                if (empty($enrollment_id) || $amount <= 0) {
                    throw new Exception("Invalid payment data or amount.");
                }
                
                $conn->begin_transaction();
                try {
                    $student_pk = student_record_id($conn, $target_user_id);

                    // Insert payment record into payments table
                    $payment_id = generate_uuid();
                    $trans_id = 'TXN-' . mt_rand(1000000, 9999999);
                    $desc = "Manual Installment Payment";
                    $stmt = $conn->prepare("INSERT INTO payments (id, transaction_id, type, amount, method, status, student_id, course_id, description, paid_at) 
                                            VALUES (?, ?, 'COURSE_FEE', ?, 'CASH', 'COMPLETED', ?, ?, ?, NOW())");
                    $stmt->bind_param("ssdsss", $payment_id, $trans_id, $amount, $student_pk, $course_id, $desc);
                    $stmt->execute();
                    $stmt->close();

                    // Insert income ledger record
                    $income_id = generate_uuid();
                    $stmt = $conn->prepare("INSERT INTO income_records (id, source, amount, description, category) VALUES (?, 'TRAINING', ?, ?, 'LMS Course Fee')");
                    $stmt->bind_param("sds", $income_id, $amount, $desc);
                    $stmt->execute();
                    $stmt->close();

                    // Update enrollment totals
                    $stmt = $conn->prepare("UPDATE enrollments SET total_paid = total_paid + ?, amount_due = amount_due - ? WHERE id = ?");
                    $stmt->bind_param("dds", $amount, $amount, $enrollment_id);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    log_activity("Admin recorded installment payment", ["target_user_id" => $target_user_id, "enrollment_id" => $enrollment_id]);
                    $success_msg = "Installment payment recorded successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Operation failed: " . $e->getMessage();
        }
    }
}

// Fetch all system users
$users = [];
try {
    $parts = [];
    foreach (USER_TABLES as $table) {
        $parts[] = "SELECT id, email, username, first_name, last_name, phone, role, status, last_active, created_at FROM `$table`";
    }
    $res = $conn->query("SELECT x.*, st.student_id AS reg_no FROM (" . implode(" UNION ALL ", $parts) . ") x LEFT JOIN students st ON st.user_id = x.id ORDER BY x.created_at DESC");
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch user activities if requested
$view_user_id = sanitize($_GET['view_user_id'] ?? '');
$target_user_info = null;
$activities = [];

if (!empty($view_user_id)) {
    // Get target user info
    foreach ($users as $u) {
        if ($u['id'] === $view_user_id) {
            $target_user_info = $u;
            break;
        }
    }
    
    if ($target_user_info) {
        try {
            $stmt = $conn->prepare("SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
            $stmt->bind_param("s", $view_user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $activities[] = $row;
            }
            $stmt->close();
        } catch (Exception $e) {
            // Ignore
        }
        
        $target_enrollments = [];
        $all_courses = [];
        $all_batches = [];
        $flexible_installments = [];
        if ($target_user_info['role'] === 'STUDENT') {
            try {
                $stmt = $conn->prepare("SELECT e.*, c.title as course_title, b.name as batch_name FROM enrollments e JOIN courses c ON e.course_id = c.id JOIN students st ON st.id = e.student_id LEFT JOIN classes b ON b.id = e.batch_id WHERE st.user_id = ? ORDER BY e.enrollment_date DESC");
                $stmt->bind_param("s", $view_user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $target_enrollments[] = $row;
                }
                $stmt->close();
                
                $res_courses = $conn->query("SELECT id, title, price FROM courses"); 
                while ($row = $res_courses->fetch_assoc()) {
                    $all_courses[] = $row;
                }
                $res_batches = $conn->query("SELECT b.id, b.course_id, b.name, b.start_date, b.end_date, b.capacity, b.status,
                                                    (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id) AS enrolled
                                             FROM classes b ORDER BY b.start_date ASC");
                while ($row = $res_batches->fetch_assoc()) {
                    $all_batches[] = $row;
                }
                $stmt = $conn->prepare("SELECT e.id as enr_id, e.course_id as c_id, e.amount_due, e.total_paid, c.title as course_title 
                                        FROM enrollments e 
                                        JOIN courses c ON e.course_id = c.id 
                                        JOIN students st ON st.id = e.student_id
                                        WHERE st.user_id = ? AND e.amount_due > 0");
                $stmt->bind_param("s", $view_user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $flexible_installments = [];
                while ($row = $res->fetch_assoc()) {
                    $flexible_installments[] = $row;
                }
                $stmt->close();
            } catch (Exception $e) { }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Alerts -->
<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600  rounded-xl text-sm font-medium">
        <?php foreach($errors as $err): ?>
            <p><?php echo $err; ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600  rounded-xl text-sm font-medium">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Workspace: Users Table list (Col span 2) -->
    <div class="lg:col-span-2 space-y-6">
        
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="users" class="w-4 h-4 text-blue-500"></i>
                <span>Registered System Users</span>
            </h4>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200  text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="pb-2.5">User Details</th>
                            <th class="pb-2.5">Role Mode</th>
                            <th class="pb-2.5">Status Check</th>
                            <th class="pb-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100  text-xs">
                        <?php foreach ($users as $u): ?>
                            <tr class="<?php echo $u['id'] === $view_user_id ? 'bg-blue-500/5' : ''; ?>">
                                <td class="py-3.5">
                                    <span class="font-bold text-slate-905  block text-sm"><?php echo sanitize($u['first_name'] . ' ' . $u['last_name']); ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?php echo sanitize($u['email']); ?></span>
                                    <?php if (!empty($u['reg_no'])): ?><span class="text-[10px] text-slate-500 block font-mono"><?php echo sanitize($u['reg_no']); ?></span><?php endif; ?>
                                    <span class="text-[9px] text-slate-450 block font-mono">Last active: <?php echo $u['last_active'] ? date('d M H:i', strtotime($u['last_active'])) : 'Never'; ?></span>
                                </td>
                                
                                <td class="py-3.5">
                                    <?php if (in_array($u['role'], ['ADMIN', 'MANAGER']) && $u['id'] !== $user['id']): ?>
                                    <form action="user_control.php" method="POST" class="flex items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <select name="role" onchange="this.form.submit()" class="px-2 py-1 bg-slate-50 border border-slate-200 rounded text-[10px] font-semibold outline-none text-slate-655">
                                            <option value="ADMIN" <?php echo $u['role'] === 'ADMIN' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="MANAGER" <?php echo $u['role'] === 'MANAGER' ? 'selected' : ''; ?>>Manager</option>
                                        </select>
                                    </form>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-slate-50 border border-slate-200 rounded text-[10px] font-semibold text-slate-600"><?php echo ucfirst(strtolower($u['role'])); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="py-3.5">
                                    <form action="user_control.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        
                                        <?php if ($u['status'] === 'ACTIVE'): ?>
                                            <input type="hidden" name="status" value="SUSPENDED">
                                            <button type="submit" class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600  border border-emerald-500/20">
                                                Active
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="status" value="ACTIVE">
                                            <button type="submit" class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-red-500/10 text-red-600  border border-red-500/20">
                                                Suspended
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                
                                <td class="py-3.5 text-right">
                                    <a href="user_control.php?view_user_id=<?php echo $u['id']; ?>" class="text-[10px] font-bold text-blue-600  hover:underline">
                                        Audit trail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    
    <!-- Right Panel: User Activity Audit Trail log -->
    <div class="space-y-6">
        
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="history" class="w-4 h-4 text-indigo-500"></i>
                <span>User Audit log trails</span>
            </h4>
            
            <?php if (!$target_user_info): ?>
                <div class="text-center py-12 border border-dashed border-slate-200  rounded-xl text-xs text-slate-400">
                    Select a user from the table and click "Audit trail" to populate recent activity feeds.
                </div>
            <?php else: ?>
                <div class="space-y-3.5">
                    <div class="p-3 bg-slate-50  rounded-xl">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Auditing user</span>
                        <h5 class="text-xs font-bold text-slate-900  mt-1"><?php echo sanitize($target_user_info['first_name'] . ' ' . $target_user_info['last_name']); ?></h5>
                    </div>
                    
                    <?php if (empty($activities)): ?>
                        <p class="text-xs text-slate-400 text-center py-4">No activities logged on file.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($activities as $act): ?>
                                <div class="pb-2 border-b border-slate-100  last:border-0 last:pb-0 text-xs">
                                    <div class="flex justify-between items-center text-[9px] text-slate-400">
                                        <span><?php echo sanitize($act['action']); ?></span>
                                        <span><?php echo date('d M, H:i', strtotime($act['created_at'])); ?></span>
                                    </div>
                                    <?php if ($act['ip_address']): ?>
                                        <span class="text-[8px] text-slate-400 block mt-0.5 font-mono">IP: <?php echo sanitize($act['ip_address']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($target_user_info && $target_user_info['role'] === 'STUDENT'): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mt-6">
            <h4 class="text-sm font-bold text-slate-900 mb-6 flex items-center space-x-2 border-b border-slate-100 pb-3">
                <i data-lucide="graduation-cap" class="w-4 h-4 text-emerald-500"></i>
                <span>Course Registration</span>
            </h4>
            
            <form action="user_control.php?view_user_id=<?php echo $target_user_info['id']; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="enroll_student">
                <input type="hidden" name="user_id" value="<?php echo $target_user_info['id']; ?>">
                
                <div>
                    <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Select Course</label>
                    <select name="course_id" id="enroll-course" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        <option value="">-- Choose Course --</option>
                        <?php foreach($all_courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['title']) . ' (' . format_currency($c['price']) . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Class Batch</label>
                    <select name="batch_id" id="enroll-batch" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        <option value="">-- No batch (enter dates) --</option>
                        <?php foreach($all_batches as $b): if ($b['status'] !== 'ACTIVE') continue; ?>
                            <option value="<?php echo $b['id']; ?>" data-course="<?php echo $b['course_id']; ?>"
                                    data-start="<?php echo $b['start_date'] ? date('Y-m-d', strtotime($b['start_date'])) : ''; ?>"
                                    data-end="<?php echo $b['end_date'] ? date('Y-m-d', strtotime($b['end_date'])) : ''; ?>"
                                    <?php echo $b['capacity'] && $b['enrolled'] >= $b['capacity'] ? 'disabled' : ''; ?>>
                                <?php echo letter_e($b['name']) . ' (' . ($b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : 'TBD') . ')' . ($b['capacity'] && $b['enrolled'] >= $b['capacity'] ? ' - full' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[9px] text-slate-400 mt-1">Manage batches under <a href="batches.php" class="text-blue-600 hover:underline">Class Batches</a>.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Start Date</label>
                        <input type="date" name="start_date" id="enroll-start" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">End Date</label>
                        <input type="date" name="end_date" id="enroll-end" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Registration Date</label>
                    <input type="datetime-local" name="enrollment_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Payment Type</label>
                        <select name="payment_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                            <option value="FULL">Full Payment</option>
                            <option value="INSTALLMENT">Installment</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Total Paid (<?php echo sanitize(getenv('CURRENCY_CODE') ?? 'NGN'); ?>)</label>
                        <input type="number" step="0.01" name="total_paid" value="0.00" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                    </div>
                </div>
                
                <label class="flex items-center space-x-2 text-xs text-slate-600">
                    <input type="checkbox" name="send_admission" value="1" checked>
                    <span>Email admission letter to <?php echo sanitize($target_user_info['email']); ?></span>
                </label>

                <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-colors">
                    Enroll Student
                </button>
            </form>
            <script>
                (function () {
                    const course = document.getElementById('enroll-course');
                    const batch = document.getElementById('enroll-batch');
                    const start = document.getElementById('enroll-start');
                    const end = document.getElementById('enroll-end');
                    course.addEventListener('change', () => {
                        for (const opt of batch.options) {
                            if (opt.value) opt.hidden = opt.dataset.course !== course.value;
                        }
                        if (batch.selectedOptions[0]?.hidden) batch.value = '';
                    });
                    batch.addEventListener('change', () => {
                        const opt = batch.selectedOptions[0];
                        if (opt && opt.value) {
                            start.value = opt.dataset.start;
                            end.value = opt.dataset.end;
                        }
                    });
                    course.dispatchEvent(new Event('change'));
                })();
            </script>

            <?php if (!empty($target_enrollments)): ?>
                <div class="mt-6">
                    <h5 class="text-[10px] text-slate-400 uppercase font-bold mb-3">Current Enrollments</h5>
                    <div class="space-y-2">
                        <?php foreach($target_enrollments as $te): ?>
                            <div class="p-3 border border-slate-100 rounded-xl bg-slate-50 text-xs space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-bold block text-slate-800"><?php echo letter_e($te['course_title']); ?></span>
                                        <span class="text-[9px] text-slate-400 block mt-0.5">Enrolled: <?php echo date('d M Y, H:i', strtotime($te['enrollment_date'])); ?></span>
                                        <span class="text-[9px] text-slate-500 block mt-0.5">Batch: <?php echo $te['batch_name'] ? letter_e($te['batch_name']) : 'None'; ?></span>
                                        <span class="text-[9px] text-slate-500 block mt-0.5 font-semibold">
                                            <?php echo $te['start_date'] ? date('d M Y', strtotime($te['start_date'])) : 'No start date'; ?>
                                            &rarr;
                                            <?php echo $te['end_date'] ? date('d M Y', strtotime($te['end_date'])) : 'No end date'; ?>
                                        </span>
                                        <span class="text-[9px] block mt-0.5 <?php echo $te['admission_sent_at'] ? 'text-emerald-600' : 'text-amber-600'; ?>">
                                            <?php echo $te['admission_sent_at'] ? 'Admission letter sent ' . date('d M Y, H:i', strtotime($te['admission_sent_at'])) : 'Admission letter not sent'; ?>
                                        </span>
                                    </div>
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[9px] font-bold"><?php echo sanitize($te['status']); ?></span>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <a href="<?php echo BASE_URL; ?>/student/admission_letter.php?enrollment_id=<?php echo $te['id']; ?>" target="_blank" class="text-[10px] font-bold text-blue-600 hover:underline">View letter</a>
                                    <form action="user_control.php?view_user_id=<?php echo $target_user_info['id']; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="send_admission_letter">
                                        <input type="hidden" name="user_id" value="<?php echo $target_user_info['id']; ?>">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $te['id']; ?>">
                                        <button type="submit" class="text-[10px] font-bold text-emerald-600 hover:underline"><?php echo $te['admission_sent_at'] ? 'Resend letter' : 'Send letter'; ?></button>
                                    </form>
                                </div>

                                <details>
                                    <summary class="text-[10px] font-bold text-slate-500 cursor-pointer">Change batch / dates</summary>
                                    <form action="user_control.php?view_user_id=<?php echo $target_user_info['id']; ?>" method="POST" class="space-y-2 mt-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="update_enrollment_schedule">
                                        <input type="hidden" name="user_id" value="<?php echo $target_user_info['id']; ?>">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $te['id']; ?>">
                                        <select name="batch_id" onchange="const o = this.selectedOptions[0]; if (o.value) { this.form.start_date.value = o.dataset.start; this.form.end_date.value = o.dataset.end; }" class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs outline-none">
                                            <option value="">-- No batch --</option>
                                            <?php foreach($all_batches as $b): if ($b['course_id'] !== $te['course_id']) continue; ?>
                                                <option value="<?php echo $b['id']; ?>" data-start="<?php echo $b['start_date'] ? date('Y-m-d', strtotime($b['start_date'])) : ''; ?>" data-end="<?php echo $b['end_date'] ? date('Y-m-d', strtotime($b['end_date'])) : ''; ?>" <?php echo $b['id'] === $te['batch_id'] ? 'selected' : ''; ?>><?php echo letter_e($b['name']) . ($b['status'] !== 'ACTIVE' ? ' (' . strtolower($b['status']) . ')' : ''); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="date" name="start_date" value="<?php echo $te['start_date']; ?>" title="Start date (blank = batch start)" class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs outline-none">
                                            <input type="date" name="end_date" value="<?php echo $te['end_date']; ?>" title="End date (blank = batch end)" class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs outline-none">
                                        </div>
                                        <p class="text-[9px] text-slate-400">Clear a date to use the batch's date.</p>
                                        <label class="flex items-center space-x-2 text-[10px] text-slate-600">
                                            <input type="checkbox" name="send_admission" value="1" checked>
                                            <span>Email updated admission letter</span>
                                        </label>
                                        <button type="submit" class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded text-[10px] font-bold">Save schedule</button>
                                    </form>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($flexible_installments)): ?>
                <div class="mt-6 border-t border-slate-100 pt-6">
                    <h5 class="text-[10px] text-amber-500 uppercase font-bold mb-3 flex items-center space-x-1">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        <span>Record Flexible Payment (Balances)</span>
                    </h5>
                    <div class="space-y-3">
                        <?php foreach($flexible_installments as $fi): ?>
                            <div class="p-3 border border-amber-200/50 rounded-xl bg-amber-50/30 text-xs flex flex-col space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold block text-slate-800"><?php echo sanitize($fi['course_title']); ?></span>
                                    <span class="text-[9px] text-slate-500 font-bold uppercase">Balance: <?php echo format_currency($fi['amount_due']); ?></span>
                                </div>
                                
                                <form action="user_control.php" method="POST" class="flex items-center space-x-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="record_flexible_payment">
                                    <input type="hidden" name="user_id" value="<?php echo $target_user_info['id']; ?>">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $fi['enr_id']; ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $fi['c_id']; ?>">
                                    
                                    <div class="flex-1 flex items-center space-x-2">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Amount:</span>
                                        <input type="number" step="0.01" name="amount" value="<?php echo $fi['amount_due']; ?>" max="<?php echo $fi['amount_due']; ?>" class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-amber-500">
                                    </div>
                                    
                                    <button type="submit" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-white rounded text-[9px] font-bold shadow-sm flex items-center space-x-1 flex-shrink-0">
                                        <i data-lucide="plus-circle" class="w-3 h-3"></i>
                                        <span>Record</span>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
