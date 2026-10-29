<?php
// admin/students.php
// Student registration (auto-generated reg numbers) and import of already
// manually-registered students (admin-typed reg numbers).
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Student Registration";

$errors = [];
$success_msg = "";
$tab = ($_GET['tab'] ?? $_POST['mode'] ?? 'new') === 'existing' ? 'existing' : 'new';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $lecture_mode = $_POST['lecture_mode'] ?? 'ONLINE';
    $reg_date = trim($_POST['enrollment_date'] ?? '');
    $manual_reg = preg_replace('/\s+/', '', trim($_POST['student_id'] ?? ''));

    if ($first_name === '' || $last_name === '') $errors[] = "First and last name are required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if (!in_array($lecture_mode, ['ONLINE', 'OFFLINE', 'HYBRID'], true)) $lecture_mode = 'ONLINE';

    $ts = $reg_date !== '' ? strtotime($reg_date) : time();
    if ($ts === false) {
        $errors[] = "Invalid registration date.";
        $ts = time();
    }

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
                $stmt = $conn->prepare("SELECT 1 FROM students WHERE student_id = ?");
                $stmt->bind_param("s", $reg_no);
                $stmt->execute();
                $dup = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($dup) throw new Exception("Registration number $reg_no is already assigned to another student.");
            } else {
                $reg_no = next_registration_number($conn, (int) date('Y', $ts), (int) date('n', $ts));
            }

            $user_id = generate_uuid();
            $student_pk = generate_uuid();
            $enrolled_at = date('Y-m-d H:i:s', $ts);
            $phone_val = $phone !== '' ? $phone : null;

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

// Students list
$students = [];
$search = trim($_GET['q'] ?? '');
$sql = "SELECT s.student_id, s.enrollment_date, s.lecture_mode, u.first_name, u.last_name, u.email, u.phone, u.status
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
$old = fn($k, $d = '') => sanitize($_POST[$k] ?? $d);
$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";

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

    <!-- Registration form -->
    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex space-x-1 mb-5 bg-slate-100 p-1 rounded-lg text-xs font-semibold">
                <a href="students.php?tab=new" class="flex-1 text-center py-1.5 rounded-md <?php echo $tab === 'new' ? 'bg-white shadow text-slate-900' : 'text-slate-500'; ?>">New Student</a>
                <a href="students.php?tab=existing" class="flex-1 text-center py-1.5 rounded-md <?php echo $tab === 'existing' ? 'bg-white shadow text-slate-900' : 'text-slate-500'; ?>">Existing Student</a>
            </div>

            <form action="students.php?tab=<?php echo $tab; ?>" method="POST" class="space-y-3.5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="mode" value="<?php echo $tab; ?>">

                <?php if ($tab === 'new'): ?>
                    <div class="p-3 bg-blue-500/5 border border-blue-500/20 rounded-lg">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Next registration number</span>
                        <span class="font-mono text-sm font-bold text-slate-900"><?php echo sanitize($preview_reg); ?></span>
                        <span class="text-[9px] text-slate-400 block mt-0.5">Format: <?php echo REG_PREFIX; ?>/year/month/number. Assigned automatically on save.</span>
                    </div>
                <?php else: ?>
                    <div>
                        <label class="<?php echo $label; ?>">Registration Number *</label>
                        <input type="text" name="student_id" required maxlength="50" value="<?php echo $old('student_id'); ?>" placeholder="e.g. RIV/PH/WJ/2024/03/015" class="<?php echo $input; ?> font-mono">
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

    <!-- Students list -->
    <div class="lg:col-span-2">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5 border-b border-slate-100 pb-3">
                <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <i data-lucide="graduation-cap" class="w-4 h-4 text-emerald-500"></i>
                    <span>Student Records (<?php echo count($students); ?>)</span>
                </h4>
                <form method="GET" action="students.php" class="flex">
                    <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                    <input type="text" name="q" value="<?php echo sanitize($search); ?>" placeholder="Search name, email, reg no" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="pb-2.5">Reg. No</th>
                            <th class="pb-2.5">Student</th>
                            <th class="pb-2.5">Mode</th>
                            <th class="pb-2.5">Registered</th>
                            <th class="pb-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($students)): ?>
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">No students found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td class="py-3 font-mono font-semibold text-slate-800"><?php echo sanitize($s['student_id']); ?></td>
                                <td class="py-3">
                                    <span class="font-bold text-slate-900 block"><?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo sanitize($s['email']); ?></span>
                                </td>
                                <td class="py-3"><?php echo ucfirst(strtolower($s['lecture_mode'])); ?></td>
                                <td class="py-3"><?php echo date('d M Y', strtotime($s['enrollment_date'])); ?></td>
                                <td class="py-3"><?php echo sanitize($s['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
