<?php
// staff/index.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Staff Portal Dashboard";

// Get staff ID
$staff_id = null;
try {
    $stmt = $conn->prepare("SELECT id, department, position FROM staff WHERE user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $staff_id = $row['id'];
        $department = $row['department'];
        $position = $row['position'];
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

if (!$staff_id) {
    $staff_id = generate_uuid();
    $s_serial = 'STF-' . mt_rand(1000, 9999);
    $emp_serial = 'EMP-' . mt_rand(1000, 9999);
    $department = "Engineering & Instruction";
    $position = "Instructor";
    $stmt = $conn->prepare("INSERT INTO staff (id, user_id, staff_id, employee_id, department, position, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'ACTIVE')");
    $stmt->bind_param("ssssss", $staff_id, $user['id'], $s_serial, $emp_serial, $department, $position);
    $stmt->execute();
    $stmt->close();
}

// 1. Total Assigned Classes
$assigned_classes_count = 0;
$classes = [];
try {
    $stmt = $conn->prepare("SELECT c.*, co.title as course_title, 
                            (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as students_count
                            FROM class_staff cs 
                            JOIN classes c ON cs.class_id = c.id 
                            JOIN courses co ON c.course_id = co.id 
                            WHERE cs.staff_id = ? AND c.status = 'ACTIVE'");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $classes[] = $row;
        $assigned_classes_count++;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// 2. Total unique student count
$unique_students_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT cs.student_id) as count 
                            FROM class_staff cst 
                            JOIN class_students cs ON cst.class_id = cs.class_id 
                            WHERE cst.staff_id = ? AND cs.status = 'ACTIVE'");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $unique_students_count = intval($row['count']);
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// 3. Assignments awaiting grading
$awaiting_grading_count = 0;
$submissions = [];
try {
    // Submissions awaiting grading
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, c.title as course_title 
                            FROM assignments a 
                            JOIN students s ON a.student_id = s.id 
                            JOIN users_student u ON s.user_id = u.id 
                            JOIN courses c ON a.course_id = c.id 
                            WHERE c.instructor_id = ? AND a.status = 'SUBMITTED' 
                            ORDER BY a.submitted_at DESC LIMIT 5");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $submissions[] = $row;
        $awaiting_grading_count++;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Let's also check all pending assignments count to display in general stats
$pending_assignments_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
                            FROM assignments a 
                            JOIN courses c ON a.course_id = c.id 
                            WHERE c.instructor_id = ? AND a.status = 'PENDING'");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $pending_assignments_count = intval($row['count']);
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ── Grid Indicators ── -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <!-- Assigned Classes -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Assigned
                Classes</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo $assigned_classes_count; ?></h3>
            <p class="text-xs text-slate-500 ">Active cohort lecture tracks</p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-cyan-50 text-cyan-600 border border-cyan-200">
            <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Assigned Students -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Total
                Students</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo $unique_students_count; ?></h3>
            <p class="text-xs text-slate-500 ">Distinct enrolled student seats</p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-[#17264A]/10 text-[#17264A] border border-[#17264A]/15">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Awaiting Grading -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span
                class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Awaiting
                Grading</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight">
                <?php echo $awaiting_grading_count; ?></h3>
            <p class="text-xs text-[#9A7A2E] font-semibold"><?php echo $pending_assignments_count; ?> Pending
                submissions</p>
        </div>
        <div
            class="w-12 h-12 rounded-xl flex items-center justify-center bg-[#9A7A2E]/8 text-[#9A7A2E] border border-[#9A7A2E]/15">
            <i data-lucide="award" class="w-5 h-5"></i>
        </div>
    </div>

</div>

<!-- ── Staff Portal Workspace Grid ── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace (Assigned classes detail listing) -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Active Classes list -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="calendar" class="w-5 h-5 text-cyan-600"></i>
                <span>Assigned Active Cohort Groups</span>
            </h4>

            <?php if (empty($classes)): ?>
                <div class="text-center py-8 text-sm text-slate-400 ">
                    <i data-lucide="calendar-off" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                    No classes currently assigned.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($classes as $cl): ?>
                        <div class="p-4 rounded-xl border border-slate-100 flex items-center justify-between hover:border-slate-200 transition-all duration-200">
                            <div>
                                <h5 class="text-sm font-bold text-slate-800 ">
                                    <?php echo sanitize($cl['name']); ?></h5>
                                <p class="text-xs text-slate-400  mt-0.5">
                                    <?php echo sanitize($cl['course_title']); ?></p>
                                <div
                                    class="flex items-center space-x-3 mt-2 text-[10px] text-slate-400  font-mono">
                                    <span class="flex items-center">
                                        <i data-lucide="users" class="w-3.5 h-3.5 mr-1 text-cyan-600/70"></i>
                                        <?php echo $cl['students_count']; ?> Students
                                    </span>
                                    <span>&bull;</span>
                                    <span class="flex items-center">
                                        <i data-lucide="clock" class="w-3.5 h-3.5 mr-1 text-[#17264A]/70"></i>
                                        Start: <?php echo date('M d, Y', strtotime($cl['start_date'])); ?>
                                    </span>
                                </div>
                            </div>

                            <a href="<?php echo BASE_URL; ?>/staff/classes.php?class_id=<?php echo $cl['id']; ?>"
                                class="btn-neon px-3.5 py-1.5 rounded-xl text-xs flex items-center space-x-1">
                                <span>Take Attendance</span>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Workspace ( grading queue quick links & resource setups ) -->
    <div class="space-y-6">

        <!-- Grading Queue Quick Actions -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="award" class="w-5 h-5 text-[#9A7A2E]"></i>
                <span>Grading Queue Submissions</span>
            </h4>

            <?php if (empty($submissions)): ?>
                <div class="text-center py-6 text-sm text-slate-400 ">
                    <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-emerald-600 opacity-60"></i>
                    All assignments are graded! Good job.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($submissions as $sub): ?>
                        <div class="p-3.5 rounded-xl border border-slate-100 flex justify-between items-center">
                            <div>
                                <h5 class="text-xs font-bold text-slate-800 ">
                                    <?php echo sanitize($sub['first_name'] . ' ' . $sub['last_name']); ?></h5>
                                <p class="text-[10px] text-slate-400  truncate w-40">
                                    <?php echo sanitize($sub['title']); ?></p>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/staff/grading.php?id=<?php echo $sub['id']; ?>"
                                class="btn-neon px-2.5 py-1 rounded text-[10px]">
                                Grade
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resource Upload Launcher -->
        <div class="glass-card p-6 rounded-2xl">
            <h4
                class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="folder-up" class="w-5 h-5 text-[#17264A]"></i>
                <span>Resource Repository</span>
            </h4>

            <p class="text-xs text-slate-400  mb-3">Distribute handouts, module templates, guidelines
                documents, or links to student cohorts.</p>

            <a href="<?php echo BASE_URL; ?>/staff/upload_resource.php"
                class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-purple-400 transition-all duration-200">
                <div class="flex items-center space-x-3">
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center bg-[#17264A]/10 text-[#17264A] border border-[#17264A]/10">
                        <i data-lucide="file-plus" class="w-4 h-4"></i>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 ">Upload Lecture Material</span>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 "></i>
            </a>
        </div>

    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>