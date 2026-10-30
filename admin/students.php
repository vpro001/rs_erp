<?php
// admin/students.php
// Student management: Create, Read, Update, Delete (CRUD) with full modal UI.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user       = current_user();
$page_title = "Student Management";

$errors      = [];
$success_msg = "";
$tab         = ($_GET['tab'] ?? $_POST['mode'] ?? 'new') === 'existing' ? 'existing' : 'new';

// ──────────────────────────────────────────────
//  POST ROUTING
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $action = $_POST['_action'] ?? ($_POST['mode'] === 'existing' ? 'create_existing' : 'create');

    // ── UPDATE ────────────────────────────────
    if ($action === 'update') {
        $student_pk    = trim($_POST['student_pk']   ?? '');
        $user_pk       = trim($_POST['user_pk']      ?? '');
        $first_name    = trim($_POST['first_name']   ?? '');
        $last_name     = trim($_POST['last_name']    ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $phone         = trim($_POST['phone']        ?? '');
        $status        = trim($_POST['status']       ?? '');
        $lecture_mode  = trim($_POST['lecture_mode'] ?? '');
        $enroll_date   = trim($_POST['enrollment_date'] ?? '');
        $new_password  = $_POST['new_password'] ?? '';

        $allowed_statuses = ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION'];
        $allowed_modes    = ['ONLINE', 'OFFLINE', 'HYBRID'];

        if ($first_name === '' || $last_name === '') $errors[] = "First and last name are required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = "A valid email is required.";
        if (!in_array($status, $allowed_statuses, true)) $errors[] = "Invalid status value.";
        if (!in_array($lecture_mode, $allowed_modes, true)) $lecture_mode = 'ONLINE';

        $ts = $enroll_date !== '' ? strtotime($enroll_date) : time();
        if ($ts === false) { $errors[] = "Invalid enrollment date."; $ts = time(); }
        $enroll_dt = date('Y-m-d H:i:s', $ts);

        if (empty($errors)) {
            try {
                // Check email uniqueness (exclude current user)
                $stmt = $conn->prepare("SELECT 1 FROM users_student WHERE email = ? AND id != ?");
                $stmt->bind_param("ss", $email, $user_pk);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) throw new Exception("Another student is already using that email address.");
                $stmt->close();

                $conn->begin_transaction();

                // Update users_student
                $stmt = $conn->prepare(
                    "UPDATE users_student SET first_name = ?, last_name = ?, email = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ?"
                );
                $phone_val = $phone !== '' ? $phone : null;
                $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone_val, $status, $user_pk);
                $stmt->execute();
                $stmt->close();

                // Optional password update
                if ($new_password !== '' && strlen($new_password) >= 6) {
                    $stmt = $conn->prepare("UPDATE users_student SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("ss", $new_password, $user_pk);
                    $stmt->execute();
                    $stmt->close();
                }

                // Update students
                $stmt = $conn->prepare("UPDATE students SET lecture_mode = ?, enrollment_date = ? WHERE id = ?");
                $stmt->bind_param("sss", $lecture_mode, $enroll_dt, $student_pk);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                log_activity("Admin updated student", ["student_pk" => $student_pk, "user_pk" => $user_pk]);
                $success_msg = "Student record updated successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Update failed: " . $e->getMessage();
            }
        }
    }

    // ── DELETE ────────────────────────────────
    elseif ($action === 'delete') {
        $student_pk = trim($_POST['student_pk'] ?? '');
        $user_pk    = trim($_POST['user_pk']    ?? '');

        if ($student_pk === '' || $user_pk === '') {
            $errors[] = "Missing student identifiers.";
        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
                $stmt->bind_param("s", $student_pk);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM users_student WHERE id = ?");
                $stmt->bind_param("s", $user_pk);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                log_activity("Admin deleted student", ["student_pk" => $student_pk, "user_pk" => $user_pk]);
                $success_msg = "Student has been permanently deleted.";
            } catch (Exception $e) {
                $conn->rollback();
                // Friendly message for FK constraint violations
                if (strpos($e->getMessage(), 'foreign key') !== false || strpos($e->getMessage(), 'Cannot delete') !== false) {
                    $errors[] = "Cannot delete this student because they have related records (e.g. enrollments or payments). Remove those first.";
                } else {
                    $errors[] = "Delete failed: " . $e->getMessage();
                }
            }
        }
    }

    // ── CREATE (new or existing) ──────────────
    else {
        $first_name   = trim($_POST['first_name']     ?? '');
        $last_name    = trim($_POST['last_name']      ?? '');
        $email        = strtolower(trim($_POST['email'] ?? ''));
        $phone        = trim($_POST['phone']          ?? '');
        $password     = $_POST['password']            ?? '';
        $lecture_mode = $_POST['lecture_mode']        ?? 'ONLINE';
        $reg_date     = trim($_POST['enrollment_date'] ?? '');
        $manual_reg   = preg_replace('/\s+/', '', trim($_POST['student_id'] ?? ''));

        if ($first_name === '' || $last_name === '') $errors[] = "First and last name are required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
        if (!in_array($lecture_mode, ['ONLINE', 'OFFLINE', 'HYBRID'], true)) $lecture_mode = 'ONLINE';

        $ts = $reg_date !== '' ? strtotime($reg_date) : time();
        if ($ts === false) { $errors[] = "Invalid registration date."; $ts = time(); }

        if ($tab === 'existing') {
            if ($manual_reg === '') $errors[] = "Registration number is required for existing students.";
            elseif (strlen($manual_reg) > 50) $errors[] = "Registration number is too long (max 50 characters).";
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("SELECT 1 FROM users_student WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($exists) throw new Exception("A student with this email already exists.");

                if ($tab === 'existing') {
                    $reg_no = $manual_reg;
                    $stmt   = $conn->prepare("SELECT 1 FROM students WHERE student_id = ?");
                    $stmt->bind_param("s", $reg_no);
                    $stmt->execute();
                    $dup = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    if ($dup) throw new Exception("Registration number $reg_no is already assigned to another student.");
                } else {
                    $reg_no = next_registration_number($conn, (int) date('Y', $ts), (int) date('n', $ts));
                }

                $user_id    = generate_uuid();
                $student_pk = generate_uuid();
                $enrolled_at = date('Y-m-d H:i:s', $ts);
                $phone_val  = $phone !== '' ? $phone : null;

                // Dev convention: login.php compares passwords directly (see login.php).
                $stmt = $conn->prepare("INSERT INTO users_student (id, email, first_name, last_name, phone, status, email_verified, password_hash) VALUES (?, ?, ?, ?, ?, 'ACTIVE', 1, ?)");
                $stmt->bind_param("ssssss", $user_id, $email, $first_name, $last_name, $phone_val, $password);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO students (id, user_id, student_id, enrollment_date, lecture_mode, profile_complete) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssss", $student_pk, $user_id, $reg_no, $enrolled_at, $lecture_mode);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                log_activity($tab === 'existing' ? "Admin added existing student record" : "Admin registered new student", ["student_id" => $reg_no, "user_id" => $user_id]);
                $success_msg = ($tab === 'existing' ? "Existing student added" : "Student registered") . " with registration number <span class=\"font-mono font-bold\">" . sanitize($reg_no) . "</span>.";
                $_POST = [];
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Operation failed: " . $e->getMessage();
            }
        }
    }
}

// ──────────────────────────────────────────────
//  READ: fetch student list (with PKs for CRUD)
// ──────────────────────────────────────────────
$students = [];
$search   = trim($_GET['q'] ?? '');
$sql = "SELECT s.id AS student_pk, u.id AS user_pk,
               s.student_id, s.enrollment_date, s.lecture_mode,
               u.first_name, u.last_name, u.email, u.phone, u.status
        FROM students s JOIN users_student u ON u.id = s.user_id";
if ($search !== '') {
    $sql .= " WHERE s.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?";
}
$sql .= " ORDER BY s.enrollment_date DESC LIMIT 200";
try {
    $stmt = $conn->prepare($sql);
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param("ssss", $like, $like, $like, $like);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
} catch (Exception $e) {
    $errors[] = "Could not load students: " . $e->getMessage();
}

$preview_reg = next_registration_number($conn, (int) date('Y'), (int) date('n'));
$old   = fn($k, $d = '') => sanitize($_POST[$k] ?? $d);
$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";

// Status badge helper
function status_badge(string $status): string {
    $map = [
        'ACTIVE'               => 'bg-emerald-100 text-emerald-700',
        'INACTIVE'             => 'bg-slate-100 text-slate-500',
        'SUSPENDED'            => 'bg-red-100 text-red-600',
        'PENDING_VERIFICATION' => 'bg-amber-100 text-amber-700',
    ];
    $cls   = $map[$status] ?? 'bg-slate-100 text-slate-500';
    $label = ucwords(strtolower(str_replace('_', ' ', $status)));
    return "<span class=\"inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold $cls\">$label</span>";
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($success_msg): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-sm font-medium">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- ══ Col 1: Registration form ══ -->
    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex space-x-1 mb-5 bg-slate-100 p-1 rounded-lg text-xs font-semibold">
                <a href="students.php?tab=new"      class="flex-1 text-center py-1.5 rounded-md <?php echo $tab === 'new'      ? 'bg-white shadow text-slate-900' : 'text-slate-500'; ?>">New Student</a>
                <a href="students.php?tab=existing" class="flex-1 text-center py-1.5 rounded-md <?php echo $tab === 'existing' ? 'bg-white shadow text-slate-900' : 'text-slate-500'; ?>">Existing Student</a>
            </div>

            <form action="students.php?tab=<?php echo $tab; ?>" method="POST" class="space-y-3.5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="mode"       value="<?php echo $tab; ?>">

                <?php if ($tab === 'new'): ?>
                    <div class="p-3 bg-blue-500/5 border border-blue-500/20 rounded-lg">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Next registration number</span>
                        <span class="font-mono text-sm font-bold text-slate-900"><?php echo sanitize($preview_reg); ?></span>
                        <span class="text-[9px] text-slate-400 block mt-0.5">Format: <?php echo REG_PREFIX; ?>/year/month/number. Assigned automatically on save.</span>
                    </div>
                <?php else: ?>
                    <div>
                        <label class="<?php echo $label; ?>">Registration Number *</label>
                        <input type="text" name="student_id" required maxlength="50"
                               value="<?php echo $old('student_id'); ?>"
                               placeholder="e.g. RIV/PH/WJ/2024/03/015"
                               class="<?php echo $input; ?> font-mono">
                        <p class="text-[9px] text-slate-400 mt-1">Enter the number exactly as it was issued manually.</p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="<?php echo $label; ?>">First Name *</label>
                        <input type="text" name="first_name" required value="<?php echo $old('first_name'); ?>" class="<?php echo $input; ?>">
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>">Last Name *</label>
                        <input type="text" name="last_name" required value="<?php echo $old('last_name'); ?>" class="<?php echo $input; ?>">
                    </div>
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Email *</label>
                    <input type="email" name="email" required value="<?php echo $old('email'); ?>" class="<?php echo $input; ?>">
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Phone</label>
                    <input type="text" name="phone" maxlength="20" value="<?php echo $old('phone'); ?>" class="<?php echo $input; ?>">
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Login Password *</label>
                    <input type="text" name="password" required minlength="6" class="<?php echo $input; ?>">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="<?php echo $label; ?>">Lecture Mode</label>
                        <select name="lecture_mode" class="<?php echo $input; ?>">
                            <?php foreach (['ONLINE', 'OFFLINE', 'HYBRID'] as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($_POST['lecture_mode'] ?? 'ONLINE') === $m ? 'selected' : ''; ?>><?php echo ucfirst(strtolower($m)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>"><?php echo $tab === 'new' ? 'Registration Date' : 'Original Reg. Date'; ?></label>
                        <input type="date" name="enrollment_date" value="<?php echo $old('enrollment_date', date('Y-m-d')); ?>" class="<?php echo $input; ?>">
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg">
                    <?php echo $tab === 'new' ? 'Register Student' : 'Add to Student Records'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ══ Col 2-3: Student records table ══ -->
    <div class="lg:col-span-2">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5 border-b border-slate-100 pb-3">
                <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <i data-lucide="graduation-cap" class="w-4 h-4 text-emerald-500"></i>
                    <span>Student Records (<?php echo count($students); ?>)</span>
                </h4>
                <form method="GET" action="students.php" class="flex">
                    <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                    <input type="text" name="q" value="<?php echo sanitize($search); ?>"
                           placeholder="Search name, email, reg no"
                           class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="pb-2.5 pr-3">Reg. No</th>
                            <th class="pb-2.5 pr-3">Student</th>
                            <th class="pb-2.5 pr-3">Mode</th>
                            <th class="pb-2.5 pr-3">Registered</th>
                            <th class="pb-2.5 pr-3">Status</th>
                            <th class="pb-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($students)): ?>
                            <tr><td colspan="6" class="py-8 text-center text-slate-400">No students found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($students as $s):
                            $enroll_ymd = date('Y-m-d', strtotime($s['enrollment_date']));
                            $js_data = json_encode([
                                'student_pk'      => $s['student_pk'],
                                'user_pk'         => $s['user_pk'],
                                'first_name'      => $s['first_name'],
                                'last_name'       => $s['last_name'],
                                'email'           => $s['email'],
                                'phone'           => $s['phone'] ?? '',
                                'status'          => $s['status'],
                                'lecture_mode'    => $s['lecture_mode'],
                                'enrollment_date' => $enroll_ymd,
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            $full_name = sanitize($s['first_name'] . ' ' . $s['last_name']);
                            $reg_no    = sanitize($s['student_id']);
                        ?>
                            <tr>
                                <td class="py-3 pr-3 font-mono font-semibold text-slate-800"><?php echo $reg_no; ?></td>
                                <td class="py-3 pr-3">
                                    <span class="font-bold text-slate-900 block"><?php echo $full_name; ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo sanitize($s['email']); ?></span>
                                </td>
                                <td class="py-3 pr-3"><?php echo ucfirst(strtolower($s['lecture_mode'])); ?></td>
                                <td class="py-3 pr-3"><?php echo date('d M Y', strtotime($s['enrollment_date'])); ?></td>
                                <td class="py-3 pr-3"><?php echo status_badge($s['status']); ?></td>
                                <td class="py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button"
                                                class="p-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors"
                                                title="Edit student"
                                                onclick="openEditModal(<?php echo htmlspecialchars($js_data, ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i data-lucide="pencil" class="w-3 h-3"></i>
                                        </button>
                                        <button type="button"
                                                class="p-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors"
                                                title="Delete student"
                                                onclick="openDeleteModal(
                                                    '<?php echo addslashes($s['student_pk']); ?>',
                                                    '<?php echo addslashes($s['user_pk']); ?>',
                                                    '<?php echo addslashes($s['first_name'] . ' ' . $s['last_name']); ?>',
                                                    '<?php echo addslashes($s['student_id']); ?>'
                                                )">
                                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════════ -->
<div id="edit-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-bold text-slate-900">Edit Student</h3>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="edit-form" method="POST" action="students.php" class="space-y-3.5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="_action"    value="update">
            <input type="hidden" name="student_pk" id="edit_student_pk">
            <input type="hidden" name="user_pk"    id="edit_user_pk">

            <!-- Name row -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="edit_first_name" class="<?php echo $label; ?>">First Name *</label>
                    <input type="text" id="edit_first_name" name="first_name" required
                           class="<?php echo $input; ?>">
                </div>
                <div>
                    <label for="edit_last_name" class="<?php echo $label; ?>">Last Name *</label>
                    <input type="text" id="edit_last_name" name="last_name" required
                           class="<?php echo $input; ?>">
                </div>
            </div>

            <!-- Email -->
            <div>
                <label for="edit_email" class="<?php echo $label; ?>">Email *</label>
                <input type="email" id="edit_email" name="email" required
                       class="<?php echo $input; ?>">
            </div>

            <!-- Phone -->
            <div>
                <label for="edit_phone" class="<?php echo $label; ?>">Phone</label>
                <input type="text" id="edit_phone" name="phone" maxlength="20"
                       class="<?php echo $input; ?>">
            </div>

            <!-- Status + Lecture Mode -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="edit_status" class="<?php echo $label; ?>">Status</label>
                    <select id="edit_status" name="status" class="<?php echo $input; ?>">
                        <?php foreach (['ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION'] as $st): ?>
                            <option value="<?php echo $st; ?>"><?php echo ucwords(strtolower(str_replace('_', ' ', $st))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit_lecture_mode" class="<?php echo $label; ?>">Lecture Mode</label>
                    <select id="edit_lecture_mode" name="lecture_mode" class="<?php echo $input; ?>">
                        <?php foreach (['ONLINE', 'OFFLINE', 'HYBRID'] as $m): ?>
                            <option value="<?php echo $m; ?>"><?php echo ucfirst(strtolower($m)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Enrollment Date -->
            <div>
                <label for="edit_enrollment_date" class="<?php echo $label; ?>">Enrollment Date</label>
                <input type="date" id="edit_enrollment_date" name="enrollment_date"
                       class="<?php echo $input; ?>">
            </div>

            <!-- New Password (optional) -->
            <div>
                <label for="edit_new_password" class="<?php echo $label; ?>">New Password</label>
                <input type="text" id="edit_new_password" name="new_password" minlength="6"
                       placeholder="Leave blank to keep current password"
                       class="<?php echo $input; ?>">
                <p class="text-[9px] text-slate-400 mt-1">Leave blank to keep current password. Minimum 6 characters if changing.</p>
            </div>

            <!-- Submit -->
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-2.5 border border-slate-200 text-slate-600 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DELETE CONFIRMATION MODAL
══════════════════════════════════════════ -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4">
        <div class="text-center mb-5">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i data-lucide="trash-2" class="w-6 h-6 text-red-500"></i>
            </div>
            <h3 class="text-sm font-bold text-slate-900">Delete Student?</h3>
            <p class="text-xs text-slate-500 mt-1">
                This will permanently remove <strong id="delete-student-name"></strong>
                (<span id="delete-reg-no" class="font-mono"></span>) and all associated data.
                This cannot be undone.
            </p>
        </div>
        <form method="POST" action="students.php">
            <input type="hidden" name="csrf_token"  value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="_action"      value="delete">
            <input type="hidden" name="student_pk"   id="delete_student_pk">
            <input type="hidden" name="user_pk"      id="delete_user_pk">
            <div class="flex gap-3">
                <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 py-2.5 border border-slate-200 text-slate-600 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition-colors">
                    Delete Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════ -->
<script>
function openEditModal(data) {
    document.getElementById('edit_student_pk').value      = data.student_pk;
    document.getElementById('edit_user_pk').value         = data.user_pk;
    document.getElementById('edit_first_name').value      = data.first_name;
    document.getElementById('edit_last_name').value       = data.last_name;
    document.getElementById('edit_email').value           = data.email;
    document.getElementById('edit_phone').value           = data.phone;
    document.getElementById('edit_status').value          = data.status;
    document.getElementById('edit_lecture_mode').value    = data.lecture_mode;
    document.getElementById('edit_enrollment_date').value = data.enrollment_date;
    document.getElementById('edit_new_password').value    = '';
    document.getElementById('edit-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}

function openDeleteModal(student_pk, user_pk, name, reg_no) {
    document.getElementById('delete_student_pk').value        = student_pk;
    document.getElementById('delete_user_pk').value           = user_pk;
    document.getElementById('delete-student-name').textContent = name;
    document.getElementById('delete-reg-no').textContent      = reg_no;
    document.getElementById('delete-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

// Close modals on backdrop click
document.addEventListener('click', function(e) {
    const editModal   = document.getElementById('edit-modal');
    const deleteModal = document.getElementById('delete-modal');
    if (e.target === editModal)   closeEditModal();
    if (e.target === deleteModal) closeDeleteModal();
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeDeleteModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
