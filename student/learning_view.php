<?php
// student/learning_view.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Classroom Player";

// Self-healing check for lesson_completions table
try {
    $conn->query("CREATE TABLE IF NOT EXISTS lesson_completions (
      id VARCHAR(36) PRIMARY KEY,
      student_id VARCHAR(36) NOT NULL,
      lesson_id VARCHAR(36) NOT NULL,
      completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
      FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
      UNIQUE KEY uq_student_lesson (student_id, lesson_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // Ignore
}

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

$course_id = sanitize($_GET['course_id'] ?? '');
if (empty($course_id)) {
    header("Location: " . BASE_URL . "/student/my_courses.php");
    exit();
}

// Verify enrollment
$enrollment = null;
try {
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'ACTIVE'");
    $stmt->bind_param("ss", $student_id, $course_id);
    $stmt->execute();
    $enrollment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

if (!$enrollment) {
    set_flash_message('error', 'You must be enrolled in this course to access the classroom.');
    header("Location: " . BASE_URL . "/student/my_courses.php");
    exit();
}

// Fetch Course Metadata
$course = null;
try {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->bind_param("s", $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch Modules and Lessons
$modules = [];
try {
    $stmt = $conn->prepare("SELECT * FROM modules WHERE course_id = ? AND is_active = 1 ORDER BY order_num ASC");
    $stmt->bind_param("s", $course_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $modules[$row['id']] = $row;
        $modules[$row['id']]['lessons'] = [];
    }
    $stmt->close();

    if (!empty($modules)) {
        $mod_ids = implode("','", array_keys($modules));
        $res = $conn->query("SELECT l.*, 
                             (SELECT COUNT(*) FROM lesson_completions lc WHERE lc.lesson_id = l.id AND lc.student_id = '$student_id') as is_completed
                             FROM lessons l 
                             WHERE l.module_id IN ('$mod_ids') 
                             ORDER BY l.order_num ASC");
        while ($row = $res->fetch_assoc()) {
            $modules[$row['module_id']]['lessons'][] = $row;
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Determine active lesson
$active_lesson = null;
$active_lesson_id = sanitize($_GET['lesson_id'] ?? '');

if (!empty($active_lesson_id)) {
    // Look up active lesson
    foreach ($modules as $m) {
        foreach ($m['lessons'] as $l) {
            if ($l['id'] === $active_lesson_id) {
                $active_lesson = $l;
                break 2;
            }
        }
    }
}

// If no active lesson selected, load first lesson of first module
if (!$active_lesson && !empty($modules)) {
    $first_module = reset($modules);
    if (!empty($first_module['lessons'])) {
        $active_lesson = reset($first_module['lessons']);
        $active_lesson_id = $active_lesson['id'];
    }
}

// Fetch Course Resources
$resources = [];
try {
    $stmt = $conn->prepare("SELECT * FROM course_resources WHERE course_id = ?");
    $stmt->bind_param("s", $course_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $resources[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>
<!-- Page Header -->
<div class="mb-8 flex justify-between items-end mt-4">
    <div>
        <a href="my_courses.php" class="text-[10px] font-bold text-slate-400 hover:text-blue-600 uppercase tracking-wider mb-2 inline-flex items-center space-x-1 transition-colors">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            <span>Back to My Courses</span>
        </a>
        <h2 class="text-2xl font-bold text-slate-900 flex items-center space-x-3 mt-2">
            <span><?php echo sanitize($course['title']); ?></span>
            <span class="px-2.5 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold tracking-widest uppercase align-middle ml-3">
                Classroom
            </span>
        </h2>
        <p class="text-xs text-slate-500 mt-1"><?php echo sanitize($course['description'] ?? 'No description available.'); ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <!-- Left Panel: Assignments -->
    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center space-x-2">
                <i data-lucide="file-edit" class="w-4 h-4 text-emerald-500"></i>
                <span>Assignments</span>
            </h4>
            <div class="text-center py-8">
                <p class="text-xs text-slate-500">Go to the <a href="assignments.php" class="text-blue-600 font-bold hover:underline">Assignments tab</a> to view your tasks.</p>
            </div>
        </div>
    </div>

    <!-- Right Panel: Resources -->
    <div class="space-y-6">
        <!-- Attachments Handouts -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center space-x-2">
                <i data-lucide="paperclip" class="w-4 h-4 text-indigo-500"></i>
                <span>Course Resources</span>
            </h4>

            <?php if (empty($resources)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No attachments uploaded yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($resources as $res): ?>
                        <a href="<?php echo $res['file_path'] ? sanitize($res['file_path']) : '#'; ?>" download
                            class="w-full flex items-center justify-between p-3 bg-slate-50 hover:bg-slate-100 border border-slate-200/50 rounded-xl transition-all">
                            <div class="flex items-center space-x-3 truncate">
                                <div class="w-8 h-8 rounded bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="file-text" class="w-4 h-4 text-indigo-600"></i>
                                </div>
                                <span class="text-xs font-semibold text-slate-700 truncate"><?php echo sanitize($res['title']); ?></span>
                            </div>
                            <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
