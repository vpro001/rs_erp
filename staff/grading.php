<?php
// staff/grading.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Staff - Grading Center";

// Get staff ID
$staff_id = null;
try {
    $stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $staff_id = $row['id'];
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

$errors = [];
$success_msg = "";

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $assignment_id = sanitize($_POST['assignment_id'] ?? '');
    $score = intval($_POST['score'] ?? 0);
    $feedback = sanitize($_POST['feedback'] ?? '');

    if (empty($assignment_id)) {
        $errors[] = "Invalid assignment selection.";
    } else if ($score < 0 || $score > 100) {
        $errors[] = "Grade score must be between 0 and 100.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE assignments SET grade = ?, feedback = ?, status = 'GRADED', graded_at = NOW() WHERE id = ?");
            $stmt->bind_param("iss", $score, $feedback, $assignment_id);
            $stmt->execute();
            $stmt->close();

            // Send student notification
            $stmt = $conn->prepare("SELECT student_id, title FROM assignments WHERE id = ?");
            $stmt->bind_param("s", $assignment_id);
            $stmt->execute();
            $as_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($as_info) {
                // Find student's user ID
                $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = ?");
                $stmt->bind_param("s", $as_info['student_id']);
                $stmt->execute();
                $stud_user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($stud_user) {
                    send_notification($stud_user['user_id'], "Assignment Graded", "Your submission for '" . $as_info['title'] . "' has been evaluated. Score: $score/100", "LMS", "HIGH", "/student/assignments.php");
                }
            }

            log_activity("Graded Student Assignment", ["assignment_id" => $assignment_id, "score" => $score]);
            $success_msg = "Grade and evaluation feedback submitted successfully!";
        } catch (Exception $e) {
            $errors[] = "Grading update failed: " . $e->getMessage();
        }
    }
}

// Fetch active assignments awaiting grading
$submissions = [];
try {
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, c.title as course_title, s.student_id as student_serial 
                            FROM assignments a 
                            JOIN students s ON a.student_id = s.id 
                            JOIN users_student u ON s.user_id = u.id 
                            JOIN courses c ON a.course_id = c.id 
                            WHERE c.instructor_id = ? AND a.status = 'SUBMITTED' 
                            ORDER BY a.submitted_at ASC");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $submissions[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch graded assignments log
$graded_history = [];
try {
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, c.title as course_title, s.student_id as student_serial 
                            FROM assignments a 
                            JOIN students s ON a.student_id = s.id 
                            JOIN users_student u ON s.user_id = u.id 
                            JOIN courses c ON a.course_id = c.id 
                            WHERE c.instructor_id = ? AND a.status = 'GRADED' 
                            ORDER BY a.graded_at DESC LIMIT 10");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $graded_history[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Active grading element
$active_sub_id = sanitize($_GET['id'] ?? '');
$active_sub = null;
if (!empty($active_sub_id)) {
    foreach ($submissions as $sub) {
        if ($sub['id'] === $active_sub_id) {
            $active_sub = $sub;
            break;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Alerts -->
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace: Submissions lists -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Awaiting Grading -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="inbox" class="w-4 h-4 text-blue-500"></i>
                <span>Awaiting Review Submissions</span>
            </h4>

            <?php if (empty($submissions)): ?>
                <div class="text-center py-8 text-sm text-slate-500 ">
                    <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-2 text-emerald-500 opacity-60"></i>
                    All submitted assignments are fully evaluated!
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200  text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="pb-2">Student</th>
                                <th class="pb-2">Task Title</th>
                                <th class="pb-2">Submitted</th>
                                <th class="pb-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100  text-xs">
                            <?php foreach ($submissions as $sub): ?>
                                <tr class="<?php echo $sub['id'] === $active_sub_id ? 'bg-blue-500/5' : ''; ?>">
                                    <td class="py-3 font-semibold text-slate-900 ">
                                        <span><?php echo sanitize($sub['first_name'] . ' ' . $sub['last_name']); ?></span>
                                        <span
                                            class="text-[9px] text-slate-400 block font-mono"><?php echo sanitize($sub['student_serial']); ?></span>
                                    </td>
                                    <td class="py-3">
                                        <span
                                            class="block truncate w-40 font-bold"><?php echo sanitize($sub['title']); ?></span>
                                        <span
                                            class="text-[9px] text-slate-400 block truncate w-40"><?php echo sanitize($sub['course_title']); ?></span>
                                    </td>
                                    <td class="py-3 text-slate-500 ">
                                        <?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?>
                                    </td>
                                    <td class="py-3 text-right">
                                        <a href="grading.php?id=<?php echo $sub['id']; ?>"
                                            class="px-3 py-1.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white rounded font-bold text-[10px] transition-all">
                                            Evaluate
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Graded History -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="history" class="w-4 h-4 text-slate-400"></i>
                <span>Recently Evaluated Log</span>
            </h4>

            <?php if (empty($graded_history)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No graded logs found on file.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-250  text-[10px] font-semibold text-slate-450 uppercase tracking-wider">
                                <th class="pb-2">Student / Task</th>
                                <th class="pb-2">Graded Date</th>
                                <th class="pb-2 text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100  text-xs text-slate-500 ">
                            <?php foreach ($graded_history as $gh): ?>
                                <tr>
                                    <td class="py-3">
                                        <span
                                            class="font-bold text-slate-805  block"><?php echo sanitize($gh['first_name'] . ' ' . $gh['last_name']); ?></span>
                                        <span class="text-[9px] block"><?php echo sanitize($gh['title']); ?></span>
                                    </td>
                                    <td class="py-3"><?php echo date('M d, Y', strtotime($gh['graded_at'])); ?></td>
                                    <td
                                        class="py-3 text-right font-bold text-emerald-600  font-mono text-sm">
                                        <?php echo $gh['grade']; ?>/100</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Panel: Grading Form workspace -->
    <div class="space-y-6">

        <?php if ($active_sub): ?>
            <div
                class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
                <h4
                    class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-150  pb-3">
                    <i data-lucide="edit-3" class="w-4 h-4 text-blue-500"></i>
                    <span>Evaluation Workplace</span>
                </h4>

                <div class="space-y-2">
                    <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">Assigned student</span>
                    <h5 class="text-sm font-bold text-slate-950 ">
                        <?php echo sanitize($active_sub['first_name'] . ' ' . $active_sub['last_name']); ?></h5>
                    <p class="text-xs text-slate-450 leading-none"><?php echo sanitize($active_sub['title']); ?></p>
                </div>

                <!-- Download Submission link -->
                <div class="pt-2">
                    <a href="<?php echo upload_url($active_sub['submission']); ?>" download
                        class="w-full flex items-center justify-between p-3 bg-blue-500/5 hover:bg-blue-500/10 border border-blue-500/20 text-blue-600  rounded-xl transition-all font-semibold text-xs">
                        <span class="flex items-center">
                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                            Download Assignment Archive
                        </span>
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <form action="grading.php" method="POST"
                    class="space-y-4 pt-2 border-t border-slate-100 ">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="grade_submission">
                    <input type="hidden" name="assignment_id" value="<?php echo $active_sub['id']; ?>">

                    <div>
                        <label for="score"
                            class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Grade Score (0
                            - 100)</label>
                        <input type="number" name="score" id="score" required min="0" max="100" placeholder="85"
                            class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all font-mono font-bold">
                    </div>

                    <div>
                        <label for="feedback"
                            class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Qualitative
                            review feedback</label>
                        <textarea name="feedback" id="feedback"
                            placeholder="Provide notes on their architecture, formatting or bugs..." rows="5"
                            class="w-full px-4 py-3 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all"></textarea>
                    </div>

                    <button type="submit"
                        class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-blue-500/10">
                        Submit Evaluation Grade
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div
                class="bg-white  border border-slate-200  p-12 text-center rounded-2xl flex flex-col justify-center items-center min-h-[300px]">
                <i data-lucide="edit-3" class="w-12 h-12 opacity-30 text-slate-400 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-705 ">Select Submission</h4>
                <p class="text-xs text-slate-500  mt-0.5 max-w-xs">Select any incoming homework files on
                    the left queue to open the review card workspace.</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>