<?php
// staff/messages.php - reach out to students: message a whole batch or one student
// (portal notification + optional email), with the batch roster and contact details.
// Instructors see their assigned batches; admins see all (admin/messages.php).
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/mailer.php';
require_permission('teach');

$user = current_user();
$page_title = "Message Students";
$errors = [];
$scope_batches = teaching_batch_ids();

$batches = $conn->query("SELECT b.id, b.name, b.status, b.start_date, b.end_date, c.title AS course_title,
                                (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = b.id AND cs.status = 'ACTIVE') AS students
                         FROM classes b JOIN courses c ON c.id = b.course_id"
    . ($scope_batches === null ? "" : " WHERE b.id IN " . sql_in_list($scope_batches))
    . " ORDER BY b.status = 'ACTIVE' DESC, b.start_date DESC")->fetch_all(MYSQLI_ASSOC);
$batch_by_id = array_column($batches, null, 'id');
$class_id = isset($batch_by_id[$_GET['class_id'] ?? '']) ? $_GET['class_id'] : ($batches[0]['id'] ?? '');

function batch_roster($conn, $class_id)
{
    $stmt = $conn->prepare("SELECT s.id AS student_pk, s.student_id AS reg_no, u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.last_active,
                                   (SELECT COUNT(*) FROM attendance at WHERE at.student_id = s.id AND at.status IN ('PRESENT','LATE')) AS attended,
                                   (SELECT COUNT(*) FROM attendance at WHERE at.student_id = s.id) AS sessions
                            FROM class_students cs JOIN students s ON s.id = cs.student_id JOIN users_student u ON u.id = s.user_id
                            WHERE cs.class_id = ? AND cs.status = 'ACTIVE' ORDER BY u.first_name, u.last_name");
    $stmt->bind_param("s", $class_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("CSRF validation failed.");
    }
    try {
        $post_class = sanitize($_POST['class_id'] ?? '');
        if (!isset($batch_by_id[$post_class])) throw new Exception("Choose one of your class batches.");
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($subject === '' || $message === '') throw new Exception("Subject and message are required.");
        if (mb_strlen($subject) > 200) throw new Exception("Subject is too long.");

        $roster = batch_roster($conn, $post_class);
        $to = sanitize($_POST['student_pk'] ?? '');
        if ($to !== '') {
            $roster = array_values(array_filter($roster, fn($r) => $r['student_pk'] === $to));
            if (!$roster) throw new Exception("That student is not in this batch.");
        }
        if (!$roster) throw new Exception("There are no active students in this batch.");

        $batch_name = html_entity_decode($batch_by_id[$post_class]['name']);
        $emailed = 0;
        foreach ($roster as $r) {
            send_notification($r['user_id'], $subject, $message, "LMS", "MEDIUM", "/student/messages.php");
            if (isset($_POST['email'])) {
                $html = '<p>Hello ' . htmlspecialchars($r['first_name']) . ',</p>'
                    . '<p style="white-space:pre-line;">' . htmlspecialchars($message) . '</p>'
                    . '<p>- ' . htmlspecialchars(actor_name()) . '<br><span style="color:#64748b;font-size:12px;">' . htmlspecialchars($batch_name) . ', Royal Solutions Academy</span></p>';
                if (send_email($r['email'], $r['first_name'] . ' ' . $r['last_name'], $subject, $html)['ok']) $emailed++;
            }
        }
        $id = generate_uuid();
        $student_pk = $to !== '' ? $to : null;
        $sender = actor_name();
        $count = count($roster);
        $was_emailed = isset($_POST['email']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO class_messages (id, class_id, student_id, sender_id, sender_name, subject, message, recipients, emailed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssii", $id, $post_class, $student_pk, $user['id'], $sender, $subject, $message, $count, $was_emailed);
        $stmt->execute();
        $stmt->close();

        log_activity("Messaged students", ["class_id" => $post_class, "recipients" => $count]);
        set_flash_message('success', "Message sent to $count student(s)" . ($was_emailed ? ", $emailed emailed" : "") . ".");
        header("Location: messages.php?class_id=" . urlencode($post_class));
        exit();
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$roster = $class_id ? batch_roster($conn, $class_id) : [];
$history = [];
if ($class_id) {
    $stmt = $conn->prepare("SELECT m.*, u.first_name, u.last_name FROM class_messages m
                            LEFT JOIN students s ON s.id = m.student_id LEFT JOIN users_student u ON u.id = s.user_id
                            WHERE m.class_id = ? ORDER BY m.created_at DESC LIMIT 30");
    $stmt->bind_param("s", $class_id);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$input = "w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:border-blue-400";
$label = "block text-[10px] text-slate-400 uppercase font-semibold mb-1";
$preselect = sanitize($_GET['student'] ?? ($_POST['student_pk'] ?? ''));

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
<?php else: $batch = $batch_by_id[$class_id]; ?>

<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($batches as $b): ?>
        <a href="messages.php?class_id=<?php echo $b['id']; ?>" class="px-3 py-2 rounded-xl border text-xs font-semibold <?php echo $b['id'] === $class_id ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'; ?>">
            <?php echo sanitize($b['name']); ?> <span class="text-slate-400 font-normal">(<?php echo (int) $b['students']; ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="space-y-6">
        <form method="POST" action="messages.php?class_id=<?php echo urlencode($class_id); ?>" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3.5" id="compose">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">New Message</h4>
            <div>
                <label class="<?php echo $label; ?>">To</label>
                <select name="student_pk" id="to" class="<?php echo $input; ?>">
                    <option value="">Everyone in <?php echo sanitize($batch['name']); ?> (<?php echo count($roster); ?>)</option>
                    <?php foreach ($roster as $r): ?>
                        <option value="<?php echo $r['student_pk']; ?>" <?php echo $preselect === $r['student_pk'] ? 'selected' : ''; ?>><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="<?php echo $label; ?>">Subject</label><input name="subject" required maxlength="200" value="<?php echo sanitize($errors ? ($_POST['subject'] ?? '') : ''); ?>" class="<?php echo $input; ?>"></div>
            <div><label class="<?php echo $label; ?>">Message</label><textarea name="message" required rows="7" class="<?php echo $input; ?>"><?php echo sanitize($errors ? ($_POST['message'] ?? '') : ''); ?></textarea></div>
            <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="email" value="1" checked> Also send by email</label>
            <button class="w-full py-2.5 bg-[#17264A] hover:bg-[#22355f] text-white font-semibold rounded-xl text-xs">Send Message</button>
            <p class="text-[10px] text-slate-400">Students see messages in their portal notifications and on their Messages page.</p>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex flex-wrap justify-between items-start gap-2 border-b border-slate-100 pb-3 mb-3">
                <div>
                    <h4 class="text-sm font-bold text-slate-900"><?php echo sanitize($batch['name']); ?> &mdash; Students</h4>
                    <span class="text-[11px] text-slate-500"><?php echo sanitize($batch['course_title']); ?> &bull; <?php echo $batch['start_date'] ? date('d M Y', strtotime($batch['start_date'])) : '?'; ?> to <?php echo $batch['end_date'] ? date('d M Y', strtotime($batch['end_date'])) : '?'; ?></span>
                </div>
                <a href="classes.php?class_id=<?php echo $class_id; ?>" class="text-[11px] font-bold text-blue-600 hover:underline <?php echo is_admin_user() ? 'hidden' : ''; ?>">Take attendance &rarr;</a>
            </div>
            <?php if (!$roster): ?>
                <p class="text-xs text-slate-400 text-center py-6">No active students in this batch yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead><tr class="text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100"><th class="pb-2">Student</th><th class="pb-2">Contact</th><th class="pb-2">Attendance</th><th class="pb-2 text-right"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($roster as $r): ?>
                                <tr>
                                    <td class="py-2.5"><b class="text-slate-800 block"><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></b><span class="font-mono text-[10px] text-slate-400"><?php echo sanitize($r['reg_no']); ?></span></td>
                                    <td class="py-2.5">
                                        <a href="mailto:<?php echo sanitize($r['email']); ?>" class="text-blue-600 hover:underline block"><?php echo sanitize($r['email']); ?></a>
                                        <?php if ($r['phone']): ?><a href="tel:<?php echo sanitize($r['phone']); ?>" class="text-slate-500 block"><?php echo sanitize($r['phone']); ?></a><?php endif; ?>
                                    </td>
                                    <td class="py-2.5 text-slate-600"><?php echo $r['sessions'] ? (int) $r['attended'] . ' / ' . (int) $r['sessions'] : '—'; ?></td>
                                    <td class="py-2.5 text-right"><button type="button" class="text-[10px] font-bold text-blue-600 hover:underline" onclick="document.getElementById('to').value='<?php echo $r['student_pk']; ?>'; document.querySelector('#compose [name=subject]').focus();">Message</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3 mb-3">Sent to this batch</h4>
            <?php if (!$history): ?>
                <p class="text-xs text-slate-400 text-center py-4">No messages yet.</p>
            <?php endif; ?>
            <div class="space-y-3">
                <?php foreach ($history as $m): ?>
                    <div class="p-3 rounded-xl border border-slate-100">
                        <div class="flex justify-between text-[10px] text-slate-400">
                            <span><?php echo $m['student_id'] ? 'To ' . sanitize($m['first_name'] . ' ' . $m['last_name']) : 'To whole batch (' . (int) $m['recipients'] . ')'; ?> &bull; by <?php echo sanitize($m['sender_name']); ?><?php echo $m['emailed'] ? ' &bull; emailed' : ''; ?></span>
                            <span><?php echo date('d M Y, H:i', strtotime($m['created_at'])); ?></span>
                        </div>
                        <span class="text-xs font-bold text-slate-800 block mt-1"><?php echo sanitize($m['subject']); ?></span>
                        <p class="text-xs text-slate-600 whitespace-pre-line mt-0.5"><?php echo sanitize($m['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
