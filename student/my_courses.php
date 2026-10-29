<?php
// student/my_courses.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Course Catalog & Enrolments";

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

$errors = [];
$success_msg = "";

// Get enrolled courses only
$courses = [];
try {
    $query = "SELECT c.*, cat.name as category_name, u.first_name as staff_first, u.last_name as staff_last, 1 as is_enrolled,
                     e.id as enrollment_id, e.start_date as enr_start, e.end_date as enr_end, b.name as batch_name
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              LEFT JOIN classes b ON b.id = e.batch_id
              LEFT JOIN course_categories cat ON c.category_id = cat.id 
              LEFT JOIN staff s ON c.instructor_id = s.id 
              LEFT JOIN users_staff u ON s.user_id = u.id
              WHERE e.student_id = ? AND c.status = 'ACTIVE'
              ORDER BY e.enrollment_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $errors[] = "Failed to load courses.";
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Info Alerts -->
<?php if (!empty($errors)): ?>
    <div
        class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600  rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?>
            <p><?php echo $err; ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div
        class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600  rounded-xl text-sm font-medium">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<!-- Catalog Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($courses)): ?>
        <div class="col-span-full text-center py-12 text-slate-500 ">
            <i data-lucide="search" class="w-12 h-12 mx-auto mb-3 opacity-40"></i>
            No active courses found matching your selections.
        </div>
    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <div
                class="bg-white  border border-slate-200  rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between hover:scale-[1.01] transition-transform duration-200">
                <div>
                    <!-- Header image placeholder -->
                    <div
                        class="h-40 bg-gradient-to-tr from-slate-900 to-slate-800 p-6 flex flex-col justify-between text-white relative">
                        <div class="absolute inset-0 bg-cover bg-center opacity-30"
                            style="background-image: url('<?php echo $c['thumbnail'] ? sanitize($c['thumbnail']) : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=600&auto=format&fit=crop'; ?>')">
                        </div>

                        <div class="flex justify-between items-start relative z-10">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-blue-600/90 text-white">
                                <?php echo sanitize($c['category_name'] ?? 'LMS'); ?>
                            </span>
                            <span class="text-xs font-semibold"><?php echo sanitize($c['duration']); ?></span>
                        </div>

                        <h4 class="text-lg font-bold line-clamp-2 relative z-10 drop-shadow-md">
                            <?php echo sanitize($c['title']); ?></h4>
                    </div>

                    <div class="p-5 space-y-4">
                        <p class="text-xs text-slate-500  line-clamp-3 leading-relaxed">
                            <?php echo sanitize($c['description']); ?></p>

                        <!-- Metadata features -->
                        <div
                            class="flex justify-between text-[11px] text-slate-400 font-semibold border-y border-slate-100  py-2.5">
                            <span class="flex items-center">
                                <i data-lucide="layers" class="w-3.5 h-3.5 mr-1 text-slate-500"></i>
                                <?php echo sanitize($c['level']); ?>
                            </span>
                            <span class="flex items-center">
                                <i data-lucide="video" class="w-3.5 h-3.5 mr-1 text-slate-500"></i>
                                <?php echo sanitize($c['mode']); ?>
                            </span>
                            <span class="flex items-center">
                                <i data-lucide="user" class="w-3.5 h-3.5 mr-1 text-slate-500"></i>
                                Instructor: <?php echo sanitize($c['staff_first'] ?? 'Staff'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-4 space-y-1 text-[11px] text-slate-500">
                    <div class="flex justify-between"><span>Batch</span><span class="font-semibold text-slate-700"><?php echo $c['batch_name'] ? sanitize($c['batch_name']) : 'Not assigned'; ?></span></div>
                    <div class="flex justify-between"><span>Start date</span><span class="font-semibold text-slate-700"><?php echo $c['enr_start'] ? date('d M Y', strtotime($c['enr_start'])) : 'TBA'; ?></span></div>
                    <div class="flex justify-between"><span>End date</span><span class="font-semibold text-slate-700"><?php echo $c['enr_end'] ? date('d M Y', strtotime($c['enr_end'])) : 'TBA'; ?></span></div>
                </div>

                <div class="p-5 pt-0 flex items-center justify-between">
                    <a href="<?php echo BASE_URL; ?>/student/admission_letter.php?enrollment_id=<?php echo $c['enrollment_id']; ?>" target="_blank"
                        class="text-xs font-semibold text-blue-600 hover:underline flex items-center space-x-1">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>Admission Letter</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/student/learning_view.php?course_id=<?php echo $c['id']; ?>"
                        class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-semibold rounded-xl flex items-center space-x-1.5 transition-all shadow-md shadow-blue-500/10">
                        <span>Enter Classroom</span>
                        <i data-lucide="play" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>