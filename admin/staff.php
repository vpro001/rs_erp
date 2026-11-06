<?php
// admin/staff.php
// Staff accounts are created here by admins only (the staff portal has no sign-up).
// Each staff member has one role: Instructor, Marketing & Sales Manager, Customer Service or Head Admin.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Staff Management";
$errors = [];

/** Next STF-#### / EMP-#### pair. */
function next_staff_numbers($conn)
{
    $row = $conn->query("SELECT MAX(CAST(SUBSTRING(staff_id, 5) AS UNSIGNED)) AS n FROM staff WHERE staff_id REGEXP '^STF-[0-9]+$'")->fetch_assoc();
    $n = max(1000, intval($row['n'] ?? 0)) + 1;
    do {
        $stmt = $conn->prepare("SELECT 1 FROM staff WHERE staff_id = ? OR employee_id = ?");
        $s = "STF-$n";
        $e = "EMP-$n";
        $stmt->bind_param("ss", $s, $e);
        $stmt->execute();
        $taken = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($taken) $n++;
    } while ($taken);
    return ["STF-$n", "EMP-$n"];
}

function load_staff_member($conn, $staff_pk)
{
    $stmt = $conn->prepare("SELECT s.*, u.email, u.first_name, u.last_name FROM staff s JOIN users_staff u ON u.id = s.user_id WHERE s.id = ?");
    $stmt->bind_param("s", $staff_pk);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception("Staff member not found.");
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'];
    try {
        $role = $_POST['staff_role'] ?? '';
        if (in_array($action, ['create', 'update'], true) && !isset(STAFF_ROLES[$role])) {
            throw new Exception("Choose a valid staff role.");
        }

        if ($action === 'create') {
            $first = trim($_POST['first_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $dept = trim($_POST['department'] ?? '') ?: STAFF_ROLE_DEFAULTS[$role][0];
            $position = trim($_POST['position'] ?? '') ?: STAFF_ROLE_DEFAULTS[$role][1];
            $hire = strtotime($_POST['hire_date'] ?? '') ?: time();

            if ($first === '' || $last === '') throw new Exception("First and last name are required.");
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("A valid email is required.");
            if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters.");

            $stmt = $conn->prepare("SELECT 1 FROM users_staff WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) throw new Exception("A staff account with this email already exists.");
            $stmt->close();

            $conn->begin_transaction();
            try {
                [$staff_no, $emp_no] = next_staff_numbers($conn);
                $user_id = generate_uuid();
                $staff_pk = generate_uuid();
                $phone_val = $phone !== '' ? $phone : null;
                $hire_dt = date('Y-m-d H:i:s', $hire);
                // Dev convention: portal logins compare passwords directly (see includes/portal_login_template.php).
                $stmt = $conn->prepare("INSERT INTO users_staff (id, email, first_name, last_name, phone, status, email_verified, password_hash) VALUES (?, ?, ?, ?, ?, 'ACTIVE', 1, ?)");
                $stmt->bind_param("ssssss", $user_id, $email, $first, $last, $phone_val, $password);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO staff (id, user_id, staff_id, employee_id, department, position, staff_role, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')");
                $stmt->bind_param("ssssssss", $staff_pk, $user_id, $staff_no, $emp_no, $dept, $position, $role, $hire_dt);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            log_activity("Admin created staff account", ["staff_id" => $staff_no, "role" => $role]);
            set_flash_message('success', sanitize("Staff account created for $first $last ($staff_no, " . STAFF_ROLES[$role] . "). They sign in at the staff portal with $email."));

        } elseif ($action === 'update') {
            $s = load_staff_member($conn, sanitize($_POST['staff_pk'] ?? ''));
            $first = trim($_POST['first_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $dept = trim($_POST['department'] ?? '') ?: STAFF_ROLE_DEFAULTS[$role][0];
            $position = trim($_POST['position'] ?? '') ?: STAFF_ROLE_DEFAULTS[$role][1];
            $password = $_POST['new_password'] ?? '';
            if ($first === '' || $last === '') throw new Exception("First and last name are required.");
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("A valid email is required.");
            if ($password !== '' && strlen($password) < 6) throw new Exception("New password must be at least 6 characters.");

            $stmt = $conn->prepare("SELECT 1 FROM users_staff WHERE email = ? AND id <> ?");
            $stmt->bind_param("ss", $email, $s['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) throw new Exception("Another staff account already uses that email.");
            $stmt->close();

            $conn->begin_transaction();
            try {
                $phone_val = $phone !== '' ? $phone : null;
                $stmt = $conn->prepare("UPDATE users_staff SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->bind_param("sssss", $first, $last, $email, $phone_val, $s['user_id']);
                $stmt->execute();
                $stmt->close();
                if ($password !== '') {
                    $stmt = $conn->prepare("UPDATE users_staff SET password_hash = ? WHERE id = ?");
                    $stmt->bind_param("ss", $password, $s['user_id']);
                    $stmt->execute();
                    $stmt->close();
                }
                $stmt = $conn->prepare("UPDATE staff SET department = ?, position = ?, staff_role = ? WHERE id = ?");
                $stmt->bind_param("ssss", $dept, $position, $role, $s['id']);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            if ($role !== $s['staff_role']) {
                send_notification($s['user_id'], "Your role has changed", "You are now assigned as " . STAFF_ROLES[$role] . ". Your staff portal menu has been updated.", "GENERAL", "HIGH", "/staff/index.php");
            }
            log_activity("Admin updated staff account", ["staff_pk" => $s['id'], "role" => $role]);
            set_flash_message('success', "Staff account updated.");

        } elseif ($action === 'set_status') {
            $s = load_staff_member($conn, sanitize($_POST['staff_pk'] ?? ''));
            $active = ($_POST['status'] ?? '') === 'ACTIVE';
            $login_status = $active ? 'ACTIVE' : 'SUSPENDED';
            $staff_status = $active ? 'ACTIVE' : 'INACTIVE';
            $stmt = $conn->prepare("UPDATE users_staff SET status = ? WHERE id = ?");
            $stmt->bind_param("ss", $login_status, $s['user_id']);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE staff SET status = ? WHERE id = ?");
            $stmt->bind_param("ss", $staff_status, $s['id']);
            $stmt->execute();
            $stmt->close();
            log_activity("Admin changed staff status", ["staff_pk" => $s['id'], "status" => $login_status]);
            set_flash_message('success', sanitize($s['first_name'] . ' ' . $s['last_name']) . ($active ? ' can sign in again.' : ' has been suspended and can no longer sign in.'));

        } elseif ($action === 'assign_batches') {
            $s = load_staff_member($conn, sanitize($_POST['staff_pk'] ?? ''));
            if ($s['staff_role'] !== 'INSTRUCTOR') throw new Exception("Only instructors can be assigned to class batches.");
            $wanted = array_values(array_filter(array_map('sanitize', (array) ($_POST['batch_ids'] ?? []))));
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM class_staff WHERE staff_id = ?");
                $stmt->bind_param("s", $s['id']);
                $stmt->execute();
                $stmt->close();
                foreach ($wanted as $class_id) {
                    $id = generate_uuid();
                    $stmt = $conn->prepare("INSERT INTO class_staff (id, class_id, staff_id, role) SELECT ?, id, ?, 'Instructor' FROM classes WHERE id = ?");
                    $stmt->bind_param("sss", $id, $s['id'], $class_id);
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            send_notification($s['user_id'], "Class batches updated", "You are now assigned to " . count($wanted) . " class batch(es).", "LMS", "MEDIUM", "/staff/index.php");
            log_activity("Admin assigned instructor batches", ["staff_pk" => $s['id'], "batches" => $wanted]);
            set_flash_message('success', "Batch assignments saved for " . sanitize($s['first_name'] . ' ' . $s['last_name']) . ".");
        } else {
            throw new Exception("Unknown action.");
        }
        header("Location: staff.php" . (!empty($_GET['role']) ? '?role=' . urlencode($_GET['role']) : ''));
        exit();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$f_role = isset(STAFF_ROLES[$_GET['role'] ?? '']) ? $_GET['role'] : '';
$sql = "SELECT s.*, u.email, u.first_name, u.last_name, u.phone, u.status AS login_status, u.last_active,
               (SELECT COUNT(*) FROM class_staff cs WHERE cs.staff_id = s.id) AS batch_count
        FROM staff s JOIN users_staff u ON u.id = s.user_id";
if ($f_role !== '') $sql .= " WHERE s.staff_role = '" . $conn->real_escape_string($f_role) . "'";
$sql .= " ORDER BY u.first_name, u.last_name";
$staff_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$role_counts = [];
foreach ($conn->query("SELECT staff_role, COUNT(*) AS n FROM staff GROUP BY staff_role") as $r) {
    $role_counts[$r['staff_role']] = (int) $r['n'];
}

$batches = $conn->query("SELECT b.id, b.name, b.status, b.start_date, c.title AS course_title FROM classes b JOIN courses c ON c.id = b.course_id ORDER BY b.status = 'ACTIVE' DESC, b.start_date DESC")->fetch_all(MYSQLI_ASSOC);
$assigned = [];
foreach ($conn->query("SELECT staff_id, class_id FROM class_staff") as $r) {
    $assigned[$r['staff_id']][] = $r['class_id'];
}
$batch_names = array_column($batches, 'name', 'id');

$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";
$csrf = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
$role_badge = [
    'INSTRUCTOR' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'MARKETING' => 'bg-amber-50 text-amber-700 border-amber-200',
    'CUSTOMER_SERVICE' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'HEAD_ADMIN' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
];
$old = fn($k, $d = '') => sanitize($errors ? ($_POST[$k] ?? $d) : $d);

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php foreach (STAFF_ROLES as $key => $name): ?>
        <a href="staff.php?role=<?php echo $key; ?>" class="bg-white border rounded-2xl p-4 shadow-sm hover:border-slate-300 <?php echo $f_role === $key ? 'border-blue-400 ring-1 ring-blue-200' : 'border-slate-200'; ?>">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block"><?php echo $name; ?></span>
            <span class="text-2xl font-bold text-slate-900"><?php echo $role_counts[$key] ?? 0; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div>
        <form method="POST" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3.5">
            <?php echo $csrf; ?>
            <input type="hidden" name="action" value="create">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Create Staff Account</h4>
            <p class="text-[11px] text-slate-500">Staff cannot register themselves. Share the email and password with them; they sign in at the staff portal.</p>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="<?php echo $label; ?>">First name</label><input name="first_name" required value="<?php echo $old('first_name'); ?>" class="<?php echo $input; ?>"></div>
                <div><label class="<?php echo $label; ?>">Last name</label><input name="last_name" required value="<?php echo $old('last_name'); ?>" class="<?php echo $input; ?>"></div>
            </div>
            <div><label class="<?php echo $label; ?>">Email</label><input type="email" name="email" required value="<?php echo $old('email'); ?>" class="<?php echo $input; ?>"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="<?php echo $label; ?>">Phone</label><input name="phone" value="<?php echo $old('phone'); ?>" class="<?php echo $input; ?>"></div>
                <div><label class="<?php echo $label; ?>">Password</label><input type="text" name="password" required minlength="6" class="<?php echo $input; ?>"></div>
            </div>
            <div>
                <label class="<?php echo $label; ?>">Role</label>
                <select name="staff_role" id="create-role" required class="<?php echo $input; ?>">
                    <?php foreach (STAFF_ROLES as $key => $name): ?>
                        <option value="<?php echo $key; ?>" data-dept="<?php echo STAFF_ROLE_DEFAULTS[$key][0]; ?>" data-pos="<?php echo STAFF_ROLE_DEFAULTS[$key][1]; ?>" <?php echo $old('staff_role', $f_role) === $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
                <ul class="text-[10px] text-slate-500 mt-2 space-y-0.5 list-disc pl-4">
                    <li><b>Instructor</b>: assigned batches, assignments, grading, attendance, messaging students.</li>
                    <li><b>Marketing &amp; Sales</b>: prospects, appointments/demos, sales, marketers.</li>
                    <li><b>Customer Service</b>: student registration, enrollments, batches, payments &amp; negotiated fees.</li>
                    <li><b>Head Admin</b>: student records, payment certification, certificates.</li>
                </ul>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="<?php echo $label; ?>">Department</label><input name="department" id="create-dept" value="<?php echo $old('department'); ?>" placeholder="Auto from role" class="<?php echo $input; ?>"></div>
                <div><label class="<?php echo $label; ?>">Position</label><input name="position" id="create-pos" value="<?php echo $old('position'); ?>" placeholder="Auto from role" class="<?php echo $input; ?>"></div>
            </div>
            <div><label class="<?php echo $label; ?>">Hire date</label><input type="date" name="hire_date" value="<?php echo $old('hire_date', date('Y-m-d')); ?>" class="<?php echo $input; ?>"></div>
            <button class="w-full py-2.5 bg-[#17264A] hover:bg-[#22355f] text-white font-semibold rounded-xl text-xs">Create Staff Account</button>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-bold text-slate-900"><?php echo $f_role ? STAFF_ROLES[$f_role] . 's' : 'All Staff'; ?> (<?php echo count($staff_list); ?>)</h4>
            <?php if ($f_role): ?><a href="staff.php" class="text-[11px] font-semibold text-blue-600 hover:underline">Show all roles</a><?php endif; ?>
        </div>

        <?php if (!$staff_list): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-xs text-slate-400">No staff accounts yet. Create one with the form.</div>
        <?php endif; ?>

        <?php foreach ($staff_list as $s):
            $active = $s['login_status'] === 'ACTIVE'; ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3 <?php echo $active ? '' : 'opacity-70'; ?>">
                <div class="flex flex-wrap justify-between items-start gap-3">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo sanitize(avatar_url($s['avatar'] ?? null, $s['first_name'] . ' ' . $s['last_name'])); ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200" alt="">
                        <div>
                            <span class="text-sm font-bold text-slate-900 block"><?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?> <span class="font-mono text-[10px] text-slate-400"><?php echo sanitize($s['staff_id']); ?></span></span>
                            <span class="text-[11px] text-slate-500 block"><?php echo sanitize($s['email']); ?><?php echo $s['phone'] ? ' &bull; ' . sanitize($s['phone']) : ''; ?></span>
                            <span class="text-[10px] text-slate-400 block"><?php echo sanitize($s['position']); ?>, <?php echo sanitize($s['department']); ?> &bull; Last active <?php echo $s['last_active'] ? date('d M Y H:i', strtotime($s['last_active'])) : 'never'; ?></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded border text-[10px] font-bold <?php echo $role_badge[$s['staff_role']] ?? ''; ?>"><?php echo STAFF_ROLES[$s['staff_role']] ?? $s['staff_role']; ?></span>
                        <form method="POST" onsubmit="return confirm('<?php echo $active ? 'Suspend this staff account?' : 'Reactivate this staff account?'; ?>');">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="staff_pk" value="<?php echo $s['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $active ? 'SUSPENDED' : 'ACTIVE'; ?>">
                            <button class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo $active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'; ?>"><?php echo $active ? 'Active' : 'Suspended'; ?></button>
                        </form>
                    </div>
                </div>

                <?php if ($s['staff_role'] === 'INSTRUCTOR'): ?>
                    <div class="text-[11px] text-slate-600">
                        <span class="font-semibold">Batches:</span>
                        <?php $mine = $assigned[$s['id']] ?? [];
                        echo $mine ? implode(', ', array_map(fn($id) => sanitize($batch_names[$id] ?? '?'), $mine)) : '<span class="text-amber-600">none assigned</span>'; ?>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-4 text-[11px] font-bold">
                    <?php if ($s['staff_role'] === 'INSTRUCTOR'): ?>
                        <details class="w-full">
                            <summary class="cursor-pointer text-cyan-700">Assign class batches</summary>
                            <form method="POST" class="mt-2 space-y-2">
                                <?php echo $csrf; ?>
                                <input type="hidden" name="action" value="assign_batches">
                                <input type="hidden" name="staff_pk" value="<?php echo $s['id']; ?>">
                                <?php if (!$batches): ?>
                                    <p class="font-normal text-slate-400">No batches yet. Create one under <a href="batches.php" class="text-blue-600 hover:underline">Class Batches</a>.</p>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-56 overflow-y-auto font-normal">
                                        <?php foreach ($batches as $b): ?>
                                            <label class="flex items-start gap-2 p-2 border border-slate-100 rounded-lg hover:bg-slate-50">
                                                <input type="checkbox" name="batch_ids[]" value="<?php echo $b['id']; ?>" <?php echo in_array($b['id'], $mine, true) ? 'checked' : ''; ?> class="mt-0.5">
                                                <span><span class="font-semibold text-slate-800"><?php echo sanitize($b['name']); ?></span><?php echo $b['status'] !== 'ACTIVE' ? ' <span class="text-slate-400">(' . strtolower($b['status']) . ')</span>' : ''; ?><br><span class="text-[10px] text-slate-500"><?php echo sanitize($b['course_title']); ?></span></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="px-4 py-1.5 bg-cyan-700 text-white rounded-lg">Save batches</button>
                                <?php endif; ?>
                            </form>
                        </details>
                    <?php endif; ?>
                    <details class="w-full">
                        <summary class="cursor-pointer text-slate-600">Edit details / role / password</summary>
                        <form method="POST" class="mt-2 grid grid-cols-2 gap-2 font-normal">
                            <?php echo $csrf; ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="staff_pk" value="<?php echo $s['id']; ?>">
                            <div><label class="<?php echo $label; ?>">First name</label><input name="first_name" required value="<?php echo sanitize($s['first_name']); ?>" class="<?php echo $input; ?>"></div>
                            <div><label class="<?php echo $label; ?>">Last name</label><input name="last_name" required value="<?php echo sanitize($s['last_name']); ?>" class="<?php echo $input; ?>"></div>
                            <div><label class="<?php echo $label; ?>">Email</label><input type="email" name="email" required value="<?php echo sanitize($s['email']); ?>" class="<?php echo $input; ?>"></div>
                            <div><label class="<?php echo $label; ?>">Phone</label><input name="phone" value="<?php echo sanitize($s['phone']); ?>" class="<?php echo $input; ?>"></div>
                            <div>
                                <label class="<?php echo $label; ?>">Role</label>
                                <select name="staff_role" class="<?php echo $input; ?>">
                                    <?php foreach (STAFF_ROLES as $key => $name): ?><option value="<?php echo $key; ?>" <?php echo $s['staff_role'] === $key ? 'selected' : ''; ?>><?php echo $name; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="<?php echo $label; ?>">New password</label><input type="text" name="new_password" minlength="6" placeholder="Leave blank to keep" class="<?php echo $input; ?>"></div>
                            <div><label class="<?php echo $label; ?>">Department</label><input name="department" value="<?php echo sanitize($s['department']); ?>" class="<?php echo $input; ?>"></div>
                            <div><label class="<?php echo $label; ?>">Position</label><input name="position" value="<?php echo sanitize($s['position']); ?>" class="<?php echo $input; ?>"></div>
                            <button class="col-span-2 py-1.5 bg-slate-800 text-white rounded-lg font-bold">Save changes</button>
                        </form>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    (function () {
        const role = document.getElementById('create-role');
        const dept = document.getElementById('create-dept');
        const pos = document.getElementById('create-pos');
        const fill = () => {
            const o = role.selectedOptions[0];
            dept.placeholder = o.dataset.dept;
            pos.placeholder = o.dataset.pos;
        };
        role.addEventListener('change', fill);
        fill();
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
