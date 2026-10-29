<?php
// student/index.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Student Dashboard";

// Get student ID
$student_id = null;
try {
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $student_id = $row['id'];
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

if (!$student_id) {
    $student_id = generate_uuid();
    $conn->begin_transaction();
    $s_reg = next_registration_number($conn, (int) date('Y'), (int) date('n'));
    $stmt = $conn->prepare("INSERT INTO students (id, user_id, student_id, lecture_mode, payment_method, profile_complete) VALUES (?, ?, ?, 'ONLINE', 'PAYSTACK', 1)");
    $stmt->bind_param("sss", $student_id, $user['id'], $s_reg);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
}

// Fetch active enrollments & progress
$enrollments = [];
try {
    $stmt = $conn->prepare("SELECT e.*, c.title as course_title, c.duration, c.level, c.price, b.name as batch_name FROM enrollments e JOIN courses c ON e.course_id = c.id LEFT JOIN classes b ON b.id = e.batch_id WHERE e.student_id = ? AND e.status = 'ACTIVE'");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $enrollments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch assignments count and items
$assignments = [];
$overdue_count = 0;
$pending_count = 0;
try {
    $stmt = $conn->prepare("SELECT a.*, c.title as course_title FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.student_id = ? ORDER BY a.due_date ASC");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $assignments[] = $row;
        if ($row['status'] === 'PENDING') {
            $pending_count++;
            if ($row['due_date'] && strtotime($row['due_date']) < time()) {
                $overdue_count++;
            }
        }
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch upcoming classes
$classes = [];
try {
    $stmt = $conn->prepare("SELECT cl.*, c.title as course_title FROM class_students cs JOIN classes cl ON cs.class_id = cl.id JOIN courses c ON cl.course_id = c.id WHERE cs.student_id = ? AND cl.status = 'ACTIVE' ORDER BY cl.start_date ASC LIMIT 3");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch flexible dues
$dues = [];
$total_due = 0.00;
try {
    $stmt = $conn->prepare("SELECT e.*, c.title as course_title FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND e.amount_due > 0");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dues[] = $row;
        $total_due += floatval($row['amount_due']);
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch forum activity
$forum_posts = [];
try {
    $res = $conn->query("SELECT fp.*, u.first_name, u.last_name, c.title as course_title, 
                         (SELECT COUNT(*) FROM forum_replies fr WHERE fr.post_id = fp.id) as replies_count
                         FROM forum_posts fp 
                         JOIN students s ON fp.student_id = s.id 
                         JOIN users_student u ON s.user_id = u.id 
                         LEFT JOIN courses c ON fp.course_id = c.id 
                         ORDER BY fp.created_at DESC LIMIT 3");
    while ($row = $res->fetch_assoc()) {
        $forum_posts[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ── Grid Indicators ── -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

    <!-- Active Courses Card -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Active
                Courses</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo count($enrollments); ?></h3>
            <p class="text-xs text-slate-500 ">Enrolled class tracks</p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-cyan-50 text-cyan-600 border border-cyan-200">
            <i data-lucide="graduation-cap" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Pending Assignments Card -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Awaiting
                Tasks</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo $pending_count; ?></h3>
            <p class="text-xs text-[#B42318] font-semibold flex items-center">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mr-1"></i>
                <?php echo $overdue_count; ?> Overdue submissions
            </p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-[#9A7A2E]/8 text-[#9A7A2E] border border-[#9A7A2E]/15">
            <i data-lucide="file-edit" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Upcoming Lectures Card -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Upcoming
                Classes</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo count($classes); ?></h3>
            <p class="text-xs text-slate-500 ">Scheduled active cohorts</p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-[#17264A]/10 text-[#17264A] border border-[#17264A]/15">
            <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Balance Due Card -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Outstanding
                Dues</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo format_currency($total_due); ?></h3>
            <p class="text-xs text-slate-500 ">Pending installment fees</p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600 border border-emerald-200">
            <i data-lucide="credit-card" class="w-5 h-5"></i>
        </div>
    </div>

</div>

<!-- ── Student Workspace Bento Grid ── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace (Progress & Upcoming classes) -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Active Courses Progress Tracker -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="play-circle" class="w-5 h-5 text-cyan-600"></i>
                <span>Active Course Enrolment Trackers</span>
            </h4>

            <?php if (empty($enrollments)): ?>
                <div class="text-center py-8 text-sm text-slate-400 ">
                    <i data-lucide="graduation-cap" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                    No active course enrollments. Contact the admin to get assigned to a class.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($enrollments as $e): ?>
                        <div class="p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-all duration-200">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h5 class="text-sm font-bold text-slate-800 ">
                                        <?php echo sanitize($e['course_title']); ?></h5>
                                    <div
                                        class="flex items-center space-x-2 mt-1 text-[11px] text-slate-400  font-mono">
                                        <span
                                            class="px-2 py-0.5 rounded uppercase badge-info"><?php echo sanitize($e['level']); ?></span>
                                        <span>&bull;</span>
                                        <span><?php echo sanitize($e['duration']); ?></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1.5">
                                        <?php if ($e['batch_name']): ?><?php echo sanitize($e['batch_name']); ?> &bull; <?php endif; ?>
                                        <?php echo $e['start_date'] ? date('d M Y', strtotime($e['start_date'])) : 'Start TBA'; ?>
                                        &ndash;
                                        <?php echo $e['end_date'] ? date('d M Y', strtotime($e['end_date'])) : 'End TBA'; ?>
                                    </p>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/student/learning_view.php?course_id=<?php echo $e['course_id']; ?>"
                                    class="btn-neon px-3.5 py-1.5 rounded-xl text-xs flex items-center space-x-1.5">
                                    <span>Enter Classroom</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Classes -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="video" class="w-5 h-5 text-[#17264A]"></i>
                <span>Upcoming Lecture Schedule Cohorts</span>
            </h4>

            <?php if (empty($classes)): ?>
                <div class="text-center py-6 text-sm text-slate-400 ">
                    No classes currently scheduled.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($classes as $cl): ?>
                        <div class="py-3.5 flex items-center justify-between first:pt-0 last:pb-0">
                            <div>
                                <h5 class="text-xs font-bold text-slate-800 ">
                                    <?php echo sanitize($cl['name']); ?></h5>
                                <p class="text-[11px] text-slate-400  mt-0.5">
                                    <?php echo sanitize($cl['course_title']); ?></p>
                                <div class="flex items-center space-x-2 text-[10px] text-cyan-600/70 font-mono mt-1">
                                    <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                    <span><?php echo $cl['start_date'] ? date('M d, Y - H:i', strtotime($cl['start_date'])) : 'TBD'; ?></span>
                                </div>
                            </div>
                            <span
                                class="text-[9px] font-mono font-bold px-2.5 py-1 rounded-full badge-info uppercase tracking-wider">
                                <?php echo sanitize($cl['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Workspace (Assignments & Finance & Forum) -->
    <div class="space-y-6">

        <!-- Overdue & Upcoming Assignments -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="file-text" class="w-5 h-5 text-[#9A7A2E]"></i>
                <span>Academic Tasks & Submissions</span>
            </h4>

            <?php if (empty($assignments)): ?>
                <div class="text-center py-6 text-sm text-slate-400 ">
                    No academic assignments found.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($assignments as $a): ?>
                        <div class="p-3.5 rounded-xl border border-slate-100 flex items-center justify-between">
                            <div>
                                <h5 class="text-xs font-semibold text-slate-800  line-clamp-1">
                                    <?php echo sanitize($a['title']); ?></h5>
                                <p class="text-[10px] text-slate-400  mt-0.5 truncate">
                                    <?php echo sanitize($a['course_title']); ?></p>
                                <span class="text-[10px] text-slate-400  font-mono flex items-center mt-1">
                                    <i data-lucide="calendar" class="w-3 h-3 mr-1"></i>
                                    Due: <?php echo date('M d, Y', strtotime($a['due_date'])); ?>
                                </span>
                            </div>

                            <div class="text-right">
                                <?php if ($a['status'] === 'GRADED'): ?>
                                    <span class="text-xs font-mono font-bold text-emerald-600"><?php echo $a['grade']; ?>/100</span>
                                <?php elseif ($a['status'] === 'SUBMITTED'): ?>
                                    <span class="text-[9px] font-mono badge-info px-2 py-0.5 rounded">Submitted</span>
                                <?php else: ?>
                                    <?php if (strtotime($a['due_date']) < time()): ?>
                                        <span class="text-[9px] font-mono badge-danger px-2 py-0.5 rounded">Overdue</span>
                                    <?php else: ?>
                                        <span class="text-[9px] font-mono badge-warning px-2 py-0.5 rounded">Pending</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Billing & Installments Summary -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="landmark" class="w-5 h-5 text-emerald-600"></i>
                <span>Finance & Installments</span>
            </h4>

            <?php if (empty($dues)): ?>
                <div class="text-center py-6 text-sm text-slate-400 ">
                    <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-emerald-600 opacity-60"></i>
                    All installment billing balances are fully cleared!
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <div class="p-3.5 rounded-xl border border-slate-100" style="background: rgba(255,42,85,0.035);">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400 ">Pending Balance:</span>
                            <span
                                class="font-mono font-bold text-[#B42318] text-sm"><?php echo format_currency($total_due); ?></span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <?php foreach ($dues as $d): ?>
                            <div class="flex justify-between items-center text-xs py-2 border-b border-slate-100 last:border-0">
                                <div>
                                    <span class="font-semibold text-slate-800 ">Course Balance</span>
                                    <p class="text-[10px] text-slate-400 ">
                                        <?php echo sanitize($d['course_title']); ?></p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span
                                        class="font-mono font-semibold text-slate-800 "><?php echo format_currency($d['amount_due']); ?></span>
                                    <a href="<?php echo BASE_URL; ?>/student/billing.php"
                                        class="btn-neon px-2.5 py-1 rounded text-[10px]">
                                        Pay
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Discussion Forum Activity -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="message-square" class="w-5 h-5 text-[#17264A]"></i>
                <span>Community Discussion Activity</span>
            </h4>

            <?php if (empty($forum_posts)): ?>
                <div class="text-center py-6 text-sm text-slate-400 ">
                    No discussion posts found.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($forum_posts as $post): ?>
                        <div class="text-xs">
                            <a href="<?php echo BASE_URL; ?>/student/forum.php?post_id=<?php echo $post['id']; ?>"
                                class="font-semibold text-slate-800  hover:text-purple-600 transition-colors line-clamp-1">
                                <?php echo sanitize($post['title']); ?>
                            </a>
                            <div class="flex justify-between items-center text-[10px] text-slate-400  mt-1">
                                <span>By <?php echo sanitize($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                <span class="badge-info px-1.5 py-0.5 rounded font-mono">
                                    <?php echo $post['replies_count']; ?> Replies
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>