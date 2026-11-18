<?php
// admin/batches.php
// Class batches (cohorts) per course. A batch is a row in `classes`; enrollments point at it
// through enrollments.batch_id and copy its start/end dates (overridable per student).
// A batch can also take students of other courses (class_courses), e.g. Frontend students in a
// Fullstack batch, who leave on that course's own end date.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/admission_letter.php';
require_permission('students.manage'); // shared with Customer Service via staff/batches.php

$user = current_user();
$page_title = "Class Batches";

$errors = [];
$success_msg = "";
const BATCH_STATUSES = ['ACTIVE', 'INACTIVE', 'COMPLETED', 'CANCELLED'];

/** Make the batch's instructors exactly $staff_ids (instructor staff only). */
function set_batch_instructors($conn, $batch_id, $staff_ids)
{
    $stmt = $conn->prepare("DELETE FROM class_staff WHERE class_id = ?");
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $stmt->close();
    foreach ($staff_ids as $sid) {
        $id = generate_uuid();
        $stmt = $conn->prepare("INSERT INTO class_staff (id, class_id, staff_id, role) SELECT ?, ?, id, 'Instructor' FROM staff WHERE id = ? AND staff_role = 'INSTRUCTOR'");
        $stmt->bind_param("sss", $id, $batch_id, $sid);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Posted extra courses for a shared batch: [course_id => leave date Y-m-d, or null = batch end].
 * $course_titles is [course_id => title]; dates must fall within the batch.
 */
function posted_batch_tracks($course_titles, $main_course, $start, $end)
{
    $tracks = [];
    $ends = (array) ($_POST['track_end'] ?? []);
    foreach ((array) ($_POST['track_courses'] ?? []) as $cid) {
        $cid = sanitize($cid);
        if ($cid === $main_course || !isset($course_titles[$cid])) continue;
        $title = html_entity_decode($course_titles[$cid]);
        $raw = trim($ends[$cid] ?? '');
        $date = null;
        if ($raw !== '') {
            if (!strtotime($raw)) throw new Exception("Enter a valid end date for $title.");
            $date = date('Y-m-d', strtotime($raw));
            if ($date < $start || $date > $end) throw new Exception("The end date for $title must fall within the batch's start and end dates.");
            if ($date === $end) $date = null;
        }
        $tracks[$cid] = $date;
    }
    return $tracks;
}

/** Make the batch's extra courses exactly $tracks; refuses to drop a course that still has students here. */
function set_batch_tracks($conn, $batch_id, $tracks)
{
    $stmt = $conn->prepare("SELECT c.id, c.title, COUNT(*) AS n FROM enrollments e
                            JOIN classes b ON b.id = e.batch_id JOIN courses c ON c.id = e.course_id
                            WHERE e.batch_id = ? AND e.course_id <> b.course_id GROUP BY c.id, c.title");
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $in_use) {
        if (!array_key_exists($in_use['id'], $tracks)) {
            throw new Exception("Cannot remove " . html_entity_decode($in_use['title']) . ": {$in_use['n']} of its student(s) are in this batch. Move them to another batch first.");
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM class_courses WHERE class_id = ?");
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $stmt->close();
    foreach ($tracks as $cid => $date) {
        $stmt = $conn->prepare("INSERT INTO class_courses (class_id, course_id, end_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $batch_id, $cid, $date);
        $stmt->execute();
        $stmt->close();
    }
}

$courses = [];
$res = $conn->query("SELECT id, title FROM courses ORDER BY title ASC");
while ($row = $res->fetch_assoc()) $courses[] = $row;
$course_titles = array_column($courses, 'title', 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $action = $_POST['action'];
    $batch_id = sanitize($_POST['batch_id'] ?? '');
    $course_id = sanitize($_POST['course_id'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $capacity = ($_POST['capacity'] ?? '') === '' ? null : max(1, intval($_POST['capacity']));
    $status = in_array($_POST['status'] ?? '', BATCH_STATUSES, true) ? $_POST['status'] : 'ACTIVE';

    try {
        if (!in_array($action, ['create_batch', 'update_batch'], true)) {
            throw new Exception("Unknown action.");
        }
        if ($name === '') throw new Exception("Batch name is required.");
        if (!strtotime($start_date) || !strtotime($end_date)) throw new Exception("Valid start and end dates are required.");
        if (strtotime($end_date) < strtotime($start_date)) throw new Exception("End date cannot be before the start date.");
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));

        if ($action === 'create_batch') {
            $stmt = $conn->prepare("SELECT 1 FROM courses WHERE id = ?");
            $stmt->bind_param("s", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) throw new Exception("Select a valid course.");
            $stmt->close();

            $tracks = posted_batch_tracks($course_titles, $course_id, $start_date, $end_date);
            $id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO classes (id, course_id, name, description, start_date, end_date, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssis", $id, $course_id, $name, $description, $start_date, $end_date, $capacity, $status);
            $stmt->execute();
            $stmt->close();

            set_batch_instructors($conn, $id, array_map('sanitize', (array) ($_POST['instructor_ids'] ?? [])));
            set_batch_tracks($conn, $id, $tracks);
            log_activity("Created class batch", ["batch_id" => $id, "course_id" => $course_id]);
            $success_msg = "Batch created successfully.";
        } else {
            $stmt = $conn->prepare("SELECT course_id FROM classes WHERE id = ?");
            $stmt->bind_param("s", $batch_id);
            $stmt->execute();
            $main_course = $stmt->get_result()->fetch_assoc()['course_id'] ?? null;
            $stmt->close();
            if (!$main_course) throw new Exception("Batch not found.");
            // Before touching the batch, so a refused course removal leaves everything unchanged
            set_batch_tracks($conn, $batch_id, posted_batch_tracks($course_titles, $main_course, $start_date, $end_date));

            $stmt = $conn->prepare("UPDATE classes SET name = ?, description = ?, start_date = ?, end_date = ?, capacity = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssiss", $name, $description, $start_date, $end_date, $capacity, $status, $batch_id);
            $stmt->execute();
            $stmt->close();
            set_batch_instructors($conn, $batch_id, array_map('sanitize', (array) ($_POST['instructor_ids'] ?? [])));
            $success_msg = "Batch updated successfully.";

            if (isset($_POST['apply_to_students'])) {
                // Students of an extra course get that course's leave date instead of the batch end
                $stmt = $conn->prepare("UPDATE enrollments e LEFT JOIN class_courses cc ON cc.class_id = e.batch_id AND cc.course_id = e.course_id
                                        SET e.start_date = ?, e.end_date = COALESCE(cc.end_date, ?) WHERE e.batch_id = ?");
                $stmt->bind_param("sss", $start_date, $end_date, $batch_id);
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();
                $success_msg .= " Dates applied to $updated enrolled student(s).";

                if (isset($_POST['resend_letters'])) {
                    $stmt = $conn->prepare("SELECT id FROM enrollments WHERE batch_id = ?");
                    $stmt->bind_param("s", $batch_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $sent = 0;
                    $failed = 0;
                    while ($row = $res->fetch_assoc()) {
                        send_admission_letter($conn, $row['id'])['ok'] ? $sent++ : $failed++;
                    }
                    $stmt->close();
                    $success_msg .= " Updated admission letters sent: $sent" . ($failed ? ", failed: $failed" : "") . ".";
                }
            }
            log_activity("Admin updated class batch", ["batch_id" => $batch_id]);
        }
    } catch (Exception $e) {
        $errors[] = "Operation failed: " . $e->getMessage();
    }
}

$filter_course = sanitize($_GET['course_id'] ?? '');
$sql = "SELECT b.*, c.title AS course_title,
               (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id) AS enrolled
        FROM classes b JOIN courses c ON c.id = b.course_id";
if ($filter_course !== '') $sql .= " WHERE (b.course_id = ? OR EXISTS (SELECT 1 FROM class_courses cc WHERE cc.class_id = b.id AND cc.course_id = ?))";
$sql .= " ORDER BY b.start_date DESC";
$stmt = $conn->prepare($sql);
if ($filter_course !== '') $stmt->bind_param("ss", $filter_course, $filter_course);
$stmt->execute();
$batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$instructors = $conn->query("SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users_staff u ON u.id = s.user_id WHERE s.staff_role = 'INSTRUCTOR' AND u.status = 'ACTIVE' ORDER BY u.first_name")->fetch_all(MYSQLI_ASSOC);
$batch_instructors = [];
foreach ($conn->query("SELECT cs.class_id, s.id, u.first_name, u.last_name FROM class_staff cs JOIN staff s ON s.id = cs.staff_id JOIN users_staff u ON u.id = s.user_id") as $r) {
    $batch_instructors[$r['class_id']][$r['id']] = $r['first_name'] . ' ' . $r['last_name'];
}

// Extra courses per batch and how many students each course has in it
$batch_tracks = $batch_course_counts = [];
foreach ($conn->query("SELECT cc.class_id, cc.course_id, cc.end_date, c.title FROM class_courses cc JOIN courses c ON c.id = cc.course_id ORDER BY c.title") as $r) {
    $batch_tracks[$r['class_id']][$r['course_id']] = $r;
}
foreach ($conn->query("SELECT batch_id, course_id, COUNT(*) AS n FROM enrollments WHERE batch_id IS NOT NULL GROUP BY batch_id, course_id") as $r) {
    $batch_course_counts[$r['batch_id']][$r['course_id']] = (int) $r['n'];
}

$edit = null;
$edit_id = sanitize($_GET['edit'] ?? '');
foreach ($batches as $b) {
    if ($b['id'] === $edit_id) $edit = $b;
}

$val = fn($k, $d = '') => $edit ? letter_e($edit[$k] ?? $d) : sanitize($errors ? ($_POST[$k] ?? $d) : $d);
$date_val = fn($k) => $edit && $edit[$k] ? date('Y-m-d', strtotime($edit[$k])) : sanitize($errors ? ($_POST[$k] ?? '') : '');
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

    <!-- Create / edit batch -->
    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900 mb-5 flex items-center space-x-2 border-b border-slate-100 pb-3">
                <i data-lucide="<?php echo $edit ? 'pencil' : 'calendar-plus'; ?>" class="w-4 h-4 text-blue-500"></i>
                <span><?php echo $edit ? 'Edit Batch' : 'New Class Batch'; ?></span>
            </h4>

            <form action="batches.php<?php echo $filter_course ? '?course_id=' . urlencode($filter_course) : ''; ?>" method="POST" class="space-y-3.5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="<?php echo $edit ? 'update_batch' : 'create_batch'; ?>">
                <?php if ($edit): ?>
                    <input type="hidden" name="batch_id" value="<?php echo $edit['id']; ?>">
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Course</span>
                        <span class="text-xs font-bold text-slate-900"><?php echo letter_e($edit['course_title']); ?></span>
                    </div>
                <?php else: ?>
                    <div>
                        <label class="<?php echo $label; ?>">Course *</label>
                        <select name="course_id" id="batch-main-course" required class="<?php echo $input; ?>">
                            <option value="">-- Choose Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($_POST['course_id'] ?? $filter_course) === $c['id'] ? 'selected' : ''; ?>><?php echo letter_e($c['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="<?php echo $label; ?>">Batch Name *</label>
                    <input type="text" name="name" required maxlength="191" value="<?php echo $val('name'); ?>" placeholder="e.g. Batch A - January 2027" class="<?php echo $input; ?>">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="<?php echo $label; ?>">Start Date *</label>
                        <input type="date" name="start_date" required value="<?php echo $date_val('start_date'); ?>" class="<?php echo $input; ?>">
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>">End Date *</label>
                        <input type="date" name="end_date" required value="<?php echo $date_val('end_date'); ?>" class="<?php echo $input; ?>">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="<?php echo $label; ?>">Capacity</label>
                        <input type="number" min="1" name="capacity" value="<?php echo $val('capacity'); ?>" placeholder="Unlimited" class="<?php echo $input; ?>">
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>">Status</label>
                        <select name="status" class="<?php echo $input; ?>">
                            <?php foreach (BATCH_STATUSES as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($edit['status'] ?? 'ACTIVE') === $s ? 'selected' : ''; ?>><?php echo ucfirst(strtolower($s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Description</label>
                    <textarea name="description" rows="2" class="<?php echo $input; ?>"><?php echo $val('description'); ?></textarea>
                </div>
                <div>
                    <label class="<?php echo $label; ?>">Instructor(s)</label>
                    <?php if (!$instructors): ?>
                        <p class="text-[10px] text-slate-400">No active instructors yet<?php echo is_admin_user() ? ' (create them under <a href="staff.php" class="text-blue-600 hover:underline">Staff Management</a>)' : ''; ?>.</p>
                    <?php else: $current = $edit ? array_keys($batch_instructors[$edit['id']] ?? []) : (array) ($_POST['instructor_ids'] ?? []); ?>
                        <div class="max-h-32 overflow-y-auto border border-slate-200 rounded-lg p-2 space-y-1 bg-slate-50">
                            <?php foreach ($instructors as $in): ?>
                                <label class="flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" name="instructor_ids[]" value="<?php echo $in['id']; ?>" <?php echo in_array($in['id'], $current, true) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize($in['first_name'] . ' ' . $in['last_name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="<?php echo $label; ?>">Also open to (shared batch)</label>
                    <p class="text-[10px] text-slate-400 mb-1.5">Tick courses whose students learn alongside this batch, e.g. Frontend students in a Fullstack batch. Set the date they leave, or leave it blank if they stay to the end.</p>
                    <?php
                    $main = $edit['course_id'] ?? ($_POST['course_id'] ?? $filter_course);
                    if ($edit) {
                        $picked = array_map(fn($t) => $t['end_date'] ?? '', $batch_tracks[$edit['id']] ?? []);
                    } else {
                        $picked = [];
                        if ($errors) foreach ((array) ($_POST['track_courses'] ?? []) as $cid) $picked[$cid] = sanitize($_POST['track_end'][$cid] ?? '');
                    }
                    ?>
                    <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg p-2 space-y-1.5 bg-slate-50">
                        <?php foreach ($courses as $c): if ($edit && $c['id'] === $edit['course_id']) continue; ?>
                            <div class="track-row flex items-center gap-2 <?php echo $c['id'] === $main ? 'hidden' : ''; ?>" data-course="<?php echo $c['id']; ?>">
                                <label class="flex items-center gap-2 text-xs text-slate-700 flex-1 min-w-0">
                                    <input type="checkbox" name="track_courses[]" value="<?php echo $c['id']; ?>" <?php echo array_key_exists($c['id'], $picked) ? 'checked' : ''; ?>>
                                    <span class="truncate"><?php echo letter_e($c['title']); ?></span>
                                    <?php if ($edit && !empty($batch_course_counts[$edit['id']][$c['id']])): ?>
                                        <span class="text-[9px] text-slate-400 shrink-0">(<?php echo $batch_course_counts[$edit['id']][$c['id']]; ?>)</span>
                                    <?php endif; ?>
                                </label>
                                <input type="date" name="track_end[<?php echo $c['id']; ?>]" value="<?php echo $picked[$c['id']] ?? ''; ?>" title="Date these students leave the batch (blank = batch end date)" aria-label="Leave date for <?php echo letter_e($c['title']); ?>" class="w-32 shrink-0 px-2 py-1 bg-white border border-slate-200 rounded text-[10px] outline-none">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($edit && $edit['enrolled'] > 0): ?>
                    <div class="p-3 bg-amber-50/50 border border-amber-200/60 rounded-lg space-y-2 text-xs text-slate-600">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="apply_to_students" value="1" onchange="document.getElementById('resend-wrap').classList.toggle('hidden', !this.checked)">
                            <span>Apply new dates to the <?php echo intval($edit['enrolled']); ?> enrolled student(s)<?php echo !empty($batch_tracks[$edit['id']]) ? ' (students of extra courses get their course\'s leave date)' : ''; ?></span>
                        </label>
                        <label id="resend-wrap" class="flex items-center space-x-2 hidden">
                            <input type="checkbox" name="resend_letters" value="1">
                            <span>Email them updated admission letters</span>
                        </label>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg">
                    <?php echo $edit ? 'Save Changes' : 'Create Batch'; ?>
                </button>
                <?php if ($edit): ?>
                    <a href="batches.php<?php echo $filter_course ? '?course_id=' . urlencode($filter_course) : ''; ?>" class="block text-center text-[10px] font-bold text-slate-500 hover:underline">Cancel editing</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Batch list -->
    <div class="lg:col-span-2">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5 border-b border-slate-100 pb-3">
                <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <i data-lucide="calendar-range" class="w-4 h-4 text-emerald-500"></i>
                    <span>Batches (<?php echo count($batches); ?>)</span>
                </h4>
                <form method="GET" action="batches.php">
                    <select name="course_id" onchange="this.form.submit()" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filter_course === $c['id'] ? 'selected' : ''; ?>><?php echo letter_e($c['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="pb-2.5">Batch</th>
                            <th class="pb-2.5">Start</th>
                            <th class="pb-2.5">End</th>
                            <th class="pb-2.5">Students</th>
                            <th class="pb-2.5">Status</th>
                            <th class="pb-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($batches)): ?>
                            <tr><td colspan="6" class="py-8 text-center text-slate-400">No batches yet. Create one to start enrolling students into it.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($batches as $b): ?>
                            <tr class="<?php echo $b['id'] === $edit_id ? 'bg-blue-500/5' : ''; ?>">
                                <td class="py-3">
                                    <span class="font-bold text-slate-900 block"><?php echo letter_e($b['name']); ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?php echo letter_e($b['course_title']); ?></span>
                                    <?php foreach ($batch_tracks[$b['id']] ?? [] as $t): ?>
                                        <span class="text-[10px] text-indigo-600 block">+ <?php echo letter_e($t['title']); ?><?php echo $t['end_date'] ? ', leaves ' . date('d M Y', strtotime($t['end_date'])) : ''; ?></span>
                                    <?php endforeach; ?>
                                    <span class="text-[10px] <?php echo empty($batch_instructors[$b['id']]) ? 'text-amber-600' : 'text-cyan-700'; ?>">
                                        <?php echo empty($batch_instructors[$b['id']]) ? 'No instructor' : 'Instructor: ' . sanitize(implode(', ', $batch_instructors[$b['id']])); ?>
                                    </span>
                                </td>
                                <td class="py-3"><?php echo $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '-'; ?></td>
                                <td class="py-3"><?php echo $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '-'; ?></td>
                                <td class="py-3">
                                    <?php echo intval($b['enrolled']) . ($b['capacity'] ? ' / ' . intval($b['capacity']) : ''); ?>
                                    <?php if (!empty($batch_tracks[$b['id']]) && $b['enrolled'] > 0): ?>
                                        <?php foreach ($batch_course_counts[$b['id']] ?? [] as $cid => $n): ?>
                                            <span class="text-[9px] text-slate-400 block"><?php echo $n . ' ' . letter_e($course_titles[$cid] ?? ''); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3"><span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px] font-bold"><?php echo sanitize($b['status']); ?></span></td>
                                <td class="py-3 text-right">
                                    <a href="batches.php?edit=<?php echo $b['id']; ?><?php echo $filter_course ? '&course_id=' . urlencode($filter_course) : ''; ?>" class="text-[10px] font-bold text-blue-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // On a new batch, the main course can't also be an extra course
    (function () {
        const main = document.getElementById('batch-main-course');
        if (!main) return;
        const sync = () => document.querySelectorAll('.track-row').forEach(row => {
            const isMain = row.dataset.course === main.value;
            row.classList.toggle('hidden', isMain);
            if (isMain) row.querySelector('input[type=checkbox]').checked = false;
        });
        main.addEventListener('change', sync);
        sync();
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
