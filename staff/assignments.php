<?php
// staff/assignments.php - send assignments to a class batch and track submissions.
// Instructors work with the batches they are assigned to; admins see every batch (admin/assignments.php).
// Each student in the batch gets their own `assignments` row (the student portal works per row).
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/mailer.php';
require_permission('teach');

$user = current_user();
$page_title = "Assignments";
$errors = [];
$scope_batches = teaching_batch_ids(); // null = all (admin)

$batch_sql = "SELECT b.id, b.name, b.course_id, b.status, c.title AS course_title,
                     (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = b.id AND cs.status = 'ACTIVE') AS students
              FROM classes b JOIN courses c ON c.id = b.course_id"
    . ($scope_batches === null ? "" : " WHERE b.id IN " . sql_in_list($scope_batches))
    . " ORDER BY b.status = 'ACTIVE' DESC, b.start_date DESC";
$batches = $conn->query($batch_sql)->fetch_all(MYSQLI_ASSOC);
$batch_by_id = array_column($batches, null, 'id');

/** Assignment rows belonging to one "sent" assignment (same batch + title + due date). */
function assignment_group_where($conn, $class_id, $title, $due)
{
    return "a.class_id = '" . $conn->real_escape_string($class_id) . "' AND a.title = '" . $conn->real_escape_string($title) . "' AND "
        . ($due === '' ? "a.due_date IS NULL" : "a.due_date = '" . $conn->real_escape_string($due) . "'");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    try {
        $class_id = sanitize($_POST['class_id'] ?? '');
        if (!isset($batch_by_id[$class_id])) throw new Exception("Choose one of your class batches.");
        $batch = $batch_by_id[$class_id];

        if ($_POST['action'] === 'send') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $due_raw = trim($_POST['due_date'] ?? '');
            if ($title === '') throw new Exception("Give the assignment a title.");
            if (mb_strlen($title) > 191) throw new Exception("Title is too long.");
            $due = $due_raw !== '' && strtotime($due_raw) ? date('Y-m-d H:i:s', strtotime($due_raw)) : null;
            if ($due && strtotime($due) < time()) throw new Exception("The due date is in the past.");

            // In a shared batch each student's assignment is filed under their own course (e.g. Frontend, not Fullstack).
            $stmt = $conn->prepare("SELECT s.id AS student_pk, u.id AS user_id, u.email, u.first_name, u.last_name,
                                           (SELECT e.course_id FROM enrollments e WHERE e.student_id = s.id AND e.batch_id = cs.class_id LIMIT 1) AS course_id
                                    FROM class_students cs JOIN students s ON s.id = cs.student_id JOIN users_student u ON u.id = s.user_id
                                    WHERE cs.class_id = ? AND cs.status = 'ACTIVE'");
            $stmt->bind_param("s", $class_id);
            $stmt->execute();
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (!$students) throw new Exception("There are no active students in " . $batch['name'] . " yet.");

            $creator = current_staff()['id'] ?? $user['id'];
            $conn->begin_transaction();
            try {
                foreach ($students as $st) {
                    $id = generate_uuid();
                    $stmt = $conn->prepare("INSERT INTO assignments (id, course_id, student_id, title, description, due_date, status, class_id, created_by) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)");
                    $course_for = $st['course_id'] ?: $batch['course_id'];
                    $stmt->bind_param("ssssssss", $id, $course_for, $st['student_pk'], $title, $description, $due, $class_id, $creator);
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }

            $due_text = $due ? ' Due ' . date('D, j M Y g:ia', strtotime($due)) . '.' : '';
            $emailed = 0;
            foreach ($students as $st) {
                send_notification($st['user_id'], "New assignment: $title", "Your instructor posted a new assignment for " . html_entity_decode($batch['name']) . "." . $due_text, "LMS", "HIGH", "/student/assignments.php");
                if (isset($_POST['email'])) {
                    $html = '<p>Hello ' . htmlspecialchars($st['first_name']) . ',</p><p>A new assignment has been posted for <strong>' . $batch['name'] . '</strong>:</p>'
                        . '<h3 style="margin:0 0 8px;">' . htmlspecialchars($title) . '</h3>'
                        . ($description !== '' ? '<p style="white-space:pre-line;">' . htmlspecialchars($description) . '</p>' : '')
                        . ($due ? '<p><strong>Due:</strong> ' . date('l, j F Y g:ia', strtotime($due)) . '</p>' : '')
                        . '<p>Submit it from the Assignments page of your student portal: <a href="' . BASE_URL . '/login.php">' . BASE_URL . '/login.php</a></p>'
                        . '<p>- ' . htmlspecialchars(actor_name()) . '</p>';
                    if (send_email($st['email'], $st['first_name'] . ' ' . $st['last_name'], "New assignment: $title", $html)['ok']) $emailed++;
                }
            }
            log_activity("Sent assignment to batch", ["class_id" => $class_id, "title" => $title, "students" => count($students)]);
            set_flash_message('success', "Assignment sent to " . count($students) . " student(s) in " . sanitize(html_entity_decode($batch['name'])) . "." . (isset($_POST['email']) ? " Emailed $emailed." : ''));

        } elseif ($_POST['action'] === 'delete') {
            $where = assignment_group_where($conn, $class_id, $_POST['title'] ?? '', $_POST['due'] ?? '');
            $submitted = (int) $conn->query("SELECT COUNT(*) AS n FROM assignments a WHERE $where AND a.status IN ('SUBMITTED','GRADED')")->fetch_assoc()['n'];
            if ($submitted > 0) throw new Exception("Students have already submitted this assignment, so it can't be withdrawn.");
            $conn->query("DELETE a FROM assignments a WHERE $where");
            log_activity("Withdrew assignment", ["class_id" => $class_id, "title" => $_POST['title'] ?? '']);
            set_flash_message('success', "Assignment withdrawn.");
        } else {
            throw new Exception("Unknown action.");
        }
        header("Location: assignments.php" . (!empty($_GET['class_id']) ? '?class_id=' . urlencode($_GET['class_id']) : ''));
        exit();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$f_class = isset($batch_by_id[$_GET['class_id'] ?? '']) ? $_GET['class_id'] : '';
$groups = [];
if ($batches) {
    $in = $f_class !== '' ? sql_in_list([$f_class]) : sql_in_list(array_keys($batch_by_id));
    $groups = $conn->query("SELECT a.class_id, a.title, MAX(a.description) AS description, a.due_date, MIN(a.created_at) AS sent_at,
                                   COUNT(*) AS total, SUM(a.status = 'SUBMITTED') AS submitted, SUM(a.status = 'GRADED') AS graded,
                                   SUM(a.status IN ('PENDING','OVERDUE')) AS outstanding, ROUND(AVG(a.grade)) AS avg_grade
                            FROM assignments a WHERE a.class_id IN $in
                            GROUP BY a.class_id, a.title, a.due_date ORDER BY sent_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
}
$detail = [];
foreach ($groups as $g) {
    $where = assignment_group_where($conn, $g['class_id'], $g['title'], $g['due_date'] ?? '');
    $detail[] = $conn->query("SELECT a.id, a.status, a.grade, a.submitted_at, a.submission, u.first_name, u.last_name, s.student_id AS reg_no
                              FROM assignments a JOIN students s ON s.id = a.student_id JOIN users_student u ON u.id = s.user_id
                              WHERE $where ORDER BY u.first_name")->fetch_all(MYSQLI_ASSOC);
}

$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";
$csrf = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
$status_cls = ['PENDING' => 'bg-slate-100 text-slate-600', 'OVERDUE' => 'bg-red-50 text-red-600', 'SUBMITTED' => 'bg-amber-50 text-amber-700', 'GRADED' => 'bg-emerald-50 text-emerald-700'];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?><p><?php echo sanitize($err); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$batches): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-sm text-slate-500">
        <?php echo is_admin_user() ? 'No class batches exist yet.' : 'You have not been assigned to a class batch yet. Ask an admin to assign you.'; ?>
    </div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div>
        <form method="POST" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3.5">
            <?php echo $csrf; ?>
            <input type="hidden" name="action" value="send">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Send Assignment to a Batch</h4>
            <div>
                <label class="<?php echo $label; ?>">Class batch</label>
                <select name="class_id" required class="<?php echo $input; ?>">
                    <?php foreach ($batches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($f_class ?: ($_POST['class_id'] ?? '')) === $b['id'] ? 'selected' : ''; ?>><?php echo sanitize($b['name']); ?> (<?php echo (int) $b['students']; ?> students)<?php echo $b['status'] !== 'ACTIVE' ? ' - ' . strtolower($b['status']) : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="<?php echo $label; ?>">Title</label><input name="title" required maxlength="191" value="<?php echo sanitize($errors ? ($_POST['title'] ?? '') : ''); ?>" class="<?php echo $input; ?>"></div>
            <div><label class="<?php echo $label; ?>">Instructions</label><textarea name="description" rows="6" class="<?php echo $input; ?>" placeholder="What students should do, how it will be graded, links to resources..."><?php echo sanitize($errors ? ($_POST['description'] ?? '') : ''); ?></textarea></div>
            <div><label class="<?php echo $label; ?>">Due date</label><input type="datetime-local" name="due_date" value="<?php echo sanitize($errors ? ($_POST['due_date'] ?? '') : ''); ?>" class="<?php echo $input; ?>"></div>
            <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="email" value="1" checked> Also email each student</label>
            <button class="w-full py-2.5 bg-[#17264A] hover:bg-[#22355f] text-white font-semibold rounded-xl text-xs">Send Assignment</button>
            <p class="text-[10px] text-slate-400">Every active student in the batch gets the assignment on their portal and a notification. Grade submissions in the <a href="grading.php" class="text-blue-600 hover:underline">Grading Center</a>.</p>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-4">
        <form method="GET" class="flex gap-2">
            <select name="class_id" onchange="this.form.submit()" class="<?php echo $input; ?> sm:w-72">
                <option value="">All my batches</option>
                <?php foreach ($batches as $b): ?><option value="<?php echo $b['id']; ?>" <?php echo $f_class === $b['id'] ? 'selected' : ''; ?>><?php echo sanitize($b['name']); ?></option><?php endforeach; ?>
            </select>
        </form>

        <?php if (!$groups): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-xs text-slate-400">No assignments sent yet.</div>
        <?php endif; ?>

        <?php foreach ($groups as $i => $g): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <span class="text-sm font-bold text-slate-900 block"><?php echo sanitize($g['title']); ?></span>
                        <span class="text-[11px] text-slate-500 block"><?php echo sanitize($batch_by_id[$g['class_id']]['name'] ?? ''); ?> &bull; sent <?php echo date('d M Y', strtotime($g['sent_at'])); ?></span>
                        <span class="text-[11px] font-semibold <?php echo $g['due_date'] && strtotime($g['due_date']) < time() ? 'text-red-600' : 'text-slate-600'; ?>">Due <?php echo $g['due_date'] ? date('D d M Y, g:ia', strtotime($g['due_date'])) : 'no deadline'; ?></span>
                    </div>
                    <div class="flex gap-2 text-center">
                        <div class="px-3 py-1.5 bg-slate-50 rounded-lg"><span class="block text-sm font-bold text-slate-800"><?php echo (int) $g['outstanding']; ?></span><span class="text-[9px] uppercase text-slate-400 font-bold">Not in</span></div>
                        <div class="px-3 py-1.5 bg-amber-50 rounded-lg"><span class="block text-sm font-bold text-amber-700"><?php echo (int) $g['submitted']; ?></span><span class="text-[9px] uppercase text-amber-600 font-bold">To grade</span></div>
                        <div class="px-3 py-1.5 bg-emerald-50 rounded-lg"><span class="block text-sm font-bold text-emerald-700"><?php echo (int) $g['graded']; ?></span><span class="text-[9px] uppercase text-emerald-600 font-bold">Graded<?php echo $g['avg_grade'] !== null ? ' &middot; avg ' . (int) $g['avg_grade'] : ''; ?></span></div>
                    </div>
                </div>
                <?php if ($g['description']): ?><p class="text-xs text-slate-600 whitespace-pre-line"><?php echo sanitize($g['description']); ?></p><?php endif; ?>
                <details>
                    <summary class="text-[11px] font-bold text-slate-500 cursor-pointer">Students (<?php echo (int) $g['total']; ?>)</summary>
                    <div class="mt-2 divide-y divide-slate-100 text-xs">
                        <?php foreach ($detail[$i] as $d): ?>
                            <div class="py-2 flex justify-between items-center gap-2">
                                <span><b class="text-slate-800"><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></b> <span class="font-mono text-[10px] text-slate-400"><?php echo sanitize($d['reg_no']); ?></span></span>
                                <span class="flex items-center gap-2">
                                    <?php if ($d['status'] === 'GRADED'): ?><span class="font-mono font-bold text-emerald-700"><?php echo (int) $d['grade']; ?>/100</span><?php endif; ?>
                                    <?php if ($d['status'] === 'SUBMITTED'): ?><a href="grading.php?id=<?php echo $d['id']; ?>" class="text-[10px] font-bold text-blue-600 hover:underline">Grade</a><?php endif; ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold <?php echo $status_cls[$d['status']] ?? ''; ?>"><?php echo $d['status']; ?></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php if ((int) $g['submitted'] + (int) $g['graded'] === 0): ?>
                    <form method="POST" onsubmit="return confirm('Withdraw this assignment from all students?');">
                        <?php echo $csrf; ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="class_id" value="<?php echo $g['class_id']; ?>">
                        <input type="hidden" name="title" value="<?php echo htmlspecialchars($g['title']); ?>">
                        <input type="hidden" name="due" value="<?php echo $g['due_date'] ?? ''; ?>">
                        <button class="text-[11px] font-bold text-red-600 hover:underline">Withdraw assignment</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
