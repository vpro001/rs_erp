<?php
// marketer/students.php - a marketer submits students they brought in (name + email);
// an admin confirms each one in admin/marketers.php, which records the commission.
require_once __DIR__ . '/../includes/marketer_context.php';

$page_title = "Student Referrals";

$errors = [];
$form = ['full_name' => '', 'email' => '', 'phone' => '', 'course_id' => '', 'note' => ''];

$courses = $conn->query("SELECT id, title, price FROM courses WHERE status = 'ACTIVE' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
$course_ids = array_column($courses, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refer_student') {
    if (!csrf_ok()) {
        die("CSRF validation failed.");
    }
    foreach ($form as $k => $_) {
        $form[$k] = trim($_POST[$k] ?? '');
    }
    $form['email'] = strtolower($form['email']);

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 200) {
        $errors[] = "Please enter the student's full name.";
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 191) {
        $errors[] = "Please enter a valid email address for the student.";
    }
    if (mb_strlen($form['phone']) > 20 || mb_strlen($form['note']) > 1000) {
        $errors[] = "Phone number or note is too long.";
    }
    if ($form['course_id'] !== '' && !in_array($form['course_id'], $course_ids, true)) {
        $errors[] = "Please choose a course from the list.";
    }

    if (empty($errors)) {
        // A student can only be credited to one marketer
        $stmt = $conn->prepare("SELECT marketer_id FROM marketer_student_referrals WHERE email = ? AND status IN ('PENDING', 'CONFIRMED') LIMIT 1");
        $stmt->bind_param("s", $form['email']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $errors[] = $existing['marketer_id'] === $marketer_id
                ? "You have already referred this student."
                : "This student has already been referred by another marketer.";
        }
    }

    if (empty($errors)) {
        $id = generate_uuid();
        $course_id = $form['course_id'] !== '' ? $form['course_id'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $note = $form['note'] !== '' ? $form['note'] : null;
        $stmt = $conn->prepare("INSERT INTO marketer_student_referrals (id, marketer_id, full_name, email, phone, course_id, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $id, $marketer_id, $form['full_name'], $form['email'], $phone, $course_id, $note);
        $stmt->execute();
        $stmt->close();

        notify_admins(
            "Student referral to confirm",
            $marketer['first_name'] . ' ' . $marketer['last_name'] . " referred {$form['full_name']} ({$form['email']}). Confirm once they have registered.",
            "/admin/marketers.php?tab=referrals"
        );
        log_activity("Referred a student", ["email" => $form['email']]);
        set_flash_message('success', sanitize($form['full_name']) . " has been submitted. Your commission is recorded once the admin confirms the referral.");
        header("Location: " . BASE_URL . "/marketer/students.php");
        exit();
    }
}

$stmt = $conn->prepare("SELECT r.*, c.title AS course_title, mc.amount AS commission
                        FROM marketer_student_referrals r
                        LEFT JOIN courses c ON c.id = r.course_id
                        LEFT JOIN marketer_commissions mc ON mc.referral_id = r.id AND mc.status <> 'CANCELLED'
                        WHERE r.marketer_id = ? ORDER BY r.created_at DESC");
$stmt->bind_param("s", $marketer_id);
$stmt->execute();
$referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?>
            <p><?php echo sanitize($err); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <form method="POST" action="students.php" class="glass-card p-6 rounded-2xl space-y-4 h-fit">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="action" value="refer_student">

        <div>
            <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="user-plus" class="w-4 h-4 text-indigo-500"></i><span>Refer a student</span>
            </h4>
            <p class="text-xs text-slate-400 mt-1">Enter the details of a student you brought to us. The admin confirms it once they register, and your commission is recorded then.</p>
        </div>

        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Full name</label>
            <input type="text" name="full_name" required maxlength="200" value="<?php echo sanitize($form['full_name']); ?>"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Email address</label>
            <input type="email" name="email" required maxlength="191" value="<?php echo sanitize($form['email']); ?>"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
            <p class="text-[10px] text-slate-400 mt-1">Use the email the student registers with, so the admin can match them.</p>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Phone (optional)</label>
            <input type="text" name="phone" maxlength="20" value="<?php echo sanitize($form['phone']); ?>" placeholder="+234..."
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Course of interest (optional)</label>
            <select name="course_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                <option value="">Not sure yet</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $form['course_id'] === $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Note for the admin (optional)</label>
            <textarea name="note" rows="2" maxlength="1000" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500"><?php echo sanitize($form['note']); ?></textarea>
        </div>
        <button type="submit" class="w-full px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-sm">Submit referral</button>
    </form>

    <div class="lg:col-span-2 glass-card p-6 rounded-2xl">
        <h4 class="text-base font-bold text-slate-800 mb-4 flex items-center space-x-2">
            <i data-lucide="graduation-cap" class="w-5 h-5 text-cyan-600"></i><span>Students you referred</span>
        </h4>
        <?php if (empty($referrals)): ?>
            <div class="text-center py-8 text-sm text-slate-400">No student referrals yet.</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($referrals as $r):
                    $badge = ['PENDING' => 'badge-warning', 'CONFIRMED' => 'badge-success', 'REJECTED' => 'badge-info'][$r['status']];
                    $label = ['PENDING' => 'Awaiting confirmation', 'CONFIRMED' => 'Confirmed', 'REJECTED' => 'Not confirmed'][$r['status']]; ?>
                    <div class="p-4 border border-slate-100 rounded-xl flex flex-wrap justify-between items-start gap-3">
                        <div>
                            <h5 class="text-sm font-bold text-slate-800"><?php echo sanitize($r['full_name']); ?></h5>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                <?php echo sanitize($r['email']); ?>
                                <?php if ($r['course_title']): ?> &bull; <?php echo sanitize($r['course_title']); ?><?php endif; ?>
                                &bull; <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                            </p>
                            <?php if ($r['status'] === 'REJECTED' && $r['review_note']): ?>
                                <p class="text-[11px] text-slate-500 mt-1">Reason: <?php echo sanitize($r['review_note']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] font-mono font-bold px-2.5 py-1 rounded-full uppercase <?php echo $badge; ?>"><?php echo $label; ?></span>
                            <?php if ($r['commission'] !== null): ?>
                                <p class="text-sm font-bold text-emerald-600 mt-1.5"><?php echo format_currency($r['commission']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
