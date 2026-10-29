<?php
// student/assignments.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "My Assignments";

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

// Handle Assignment Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assignment') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $assignment_id = sanitize($_POST['assignment_id'] ?? '');
    
    if (empty($assignment_id)) {
        $errors[] = "Invalid assignment selection.";
    } else {
        // Handle file upload
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['submission_file']['name'];
            $file_tmp = $_FILES['submission_file']['tmp_name'];
            $file_size = $_FILES['submission_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['pdf', 'zip', 'rar'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = "Only PDF, ZIP, or RAR file formats are allowed.";
            } else if ($file_size > 10 * 1024 * 1024) { // 10MB limit
                $errors[] = "The submitted file size exceeds the 10MB limit.";
            } else {
                // Save file
                $upload_dir = __DIR__ . '/../uploads/assignments';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                
                $new_file_name = $assignment_id . '_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . '/' . $new_file_name;
                $db_file_path = '/uploads/assignments/' . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    try {
                        // Update assignment
                        $stmt = $conn->prepare("UPDATE assignments SET submission = ?, status = 'SUBMITTED', submitted_at = NOW() WHERE id = ? AND student_id = ?");
                        $stmt->bind_param("sss", $db_file_path, $assignment_id, $student_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        log_activity("Submitted Homework Assignment", ["assignment_id" => $assignment_id]);
                        $success_msg = "Your homework task assignment has been successfully submitted!";
                    } catch (Exception $e) {
                        $errors[] = "Failed to update assignment submission: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Could not save uploaded file. Check directory write permissions.";
                }
            }
        } else {
            $errors[] = "Please select a valid file to upload.";
        }
    }
}

// Fetch Student Assignments
$assignments = [];
try {
    $stmt = $conn->prepare("SELECT a.*, c.title as course_title FROM assignments a 
                            JOIN courses c ON a.course_id = c.id 
                            WHERE a.student_id = ? 
                            ORDER BY a.due_date ASC");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Alerts -->
<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600  rounded-xl text-sm font-medium">
        <?php foreach($errors as $err): ?>
            <p><?php echo $err; ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600  rounded-xl text-sm font-medium">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<!-- Assignments Listing -->
<div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
    <h4 class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
        <i data-lucide="file-text" class="w-4 h-4 text-blue-500"></i>
        <span>Homework Assignments Roster</span>
    </h4>
    
    <?php if (empty($assignments)): ?>
        <div class="text-center py-12 text-slate-500 ">
            <i data-lucide="file-check-2" class="w-12 h-12 mx-auto mb-3 opacity-40"></i>
            No assignments assigned to you. Keep learning!
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200  text-xs font-semibold text-slate-450 uppercase tracking-wider">
                        <th class="pb-3">Course / Title</th>
                        <th class="pb-3">Due Date</th>
                        <th class="pb-3">Status</th>
                        <th class="pb-3">Grade</th>
                        <th class="pb-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100  text-xs">
                    <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td class="py-4">
                                <span class="font-bold text-slate-900  block text-sm"><?php echo sanitize($a['title']); ?></span>
                                <span class="text-[10px] text-slate-400 mt-0.5 block"><?php echo sanitize($a['course_title']); ?></span>
                            </td>
                            
                            <td class="py-4 text-slate-500  font-semibold">
                                <?php echo date('M d, Y - H:i', strtotime($a['due_date'])); ?>
                            </td>
                            
                            <td class="py-4">
                                <?php 
                                    $status = $a['status'];
                                    $badge_class = 'bg-slate-100 text-slate-600  ';
                                    if ($status === 'GRADED') $badge_class = 'bg-emerald-500/10 text-emerald-600 ';
                                    else if ($status === 'SUBMITTED') $badge_class = 'bg-blue-500/10 text-blue-600 ';
                                    else if ($status === 'PENDING') {
                                        if (strtotime($a['due_date']) < time()) {
                                            $status = 'OVERDUE';
                                            $badge_class = 'bg-red-500/10 text-red-600 ';
                                        } else {
                                            $badge_class = 'bg-amber-500/10 text-amber-600 ';
                                        }
                                    }
                                ?>
                                <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            
                            <td class="py-4">
                                <?php if ($a['status'] === 'GRADED'): ?>
                                    <span class="font-mono text-sm font-bold text-slate-800 "><?php echo $a['grade']; ?>/100</span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="py-4 text-right">
                                <?php if ($a['status'] === 'PENDING' || (strtotime($a['due_date']) > time() && $a['status'] !== 'GRADED')): ?>
                                    <!-- Upload button triggers file input -->
                                    <form action="assignments.php" method="POST" enctype="multipart/form-data" class="flex items-center justify-end space-x-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="submit_assignment">
                                        <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                        
                                        <label class="px-3 py-1.5 bg-slate-100  text-slate-700  rounded font-semibold hover:bg-slate-200 cursor-pointer border border-slate-200  text-[10px] transition-colors">
                                            <span>Choose File</span>
                                            <input type="file" name="submission_file" required class="hidden" onchange="updateFileName(this)">
                                        </label>
                                        <span class="file-name-span text-[9px] text-slate-400 hidden max-w-[80px] truncate"></span>
                                        
                                        <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded font-semibold text-[10px] transition-colors shadow-sm shadow-blue-500/10">
                                            Submit
                                        </button>
                                    </form>
                                <?php elseif ($a['status'] === 'SUBMITTED'): ?>
                                    <span class="text-[10px] text-slate-400 font-medium">Awaiting Grade Review</span>
                                <?php elseif ($a['status'] === 'GRADED'): ?>
                                    <button class="text-xs text-blue-600  hover:underline font-semibold" onclick="showFeedback('<?php echo sanitize(json_encode($a['feedback'])); ?>')">
                                        View Feedback
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Feedback Modal -->
<div id="feedback-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white  border border-slate-200  w-full max-w-md rounded-2xl p-6 shadow-xl space-y-4 m-4">
        <div class="flex justify-between items-center border-b border-slate-100  pb-3">
            <h4 class="text-sm font-bold text-slate-900 ">Teacher's Evaluation Feedback</h4>
            <button class="text-slate-400 hover:text-slate-655" onclick="closeFeedback()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <p id="feedback-text" class="text-xs text-slate-655  leading-relaxed whitespace-pre-line"></p>
        <div class="flex justify-end">
            <button class="px-4 py-2 bg-slate-105  text-slate-700  font-semibold rounded-xl text-xs" onclick="closeFeedback()">Close</button>
        </div>
    </div>
</div>

<script>
    function updateFileName(input) {
        const span = input.parentElement.nextElementSibling;
        if (input.files && input.files[0]) {
            span.textContent = input.files[0].name;
            span.classList.remove('hidden');
        } else {
            span.textContent = "";
            span.classList.add('hidden');
        }
    }
    
    function showFeedback(feedbackJson) {
        try {
            const feedback = JSON.parse(feedbackJson);
            document.getElementById('feedback-text').textContent = feedback || "No comments written.";
            document.getElementById('feedback-modal').classList.remove('hidden');
        } catch(e) {}
    }
    
    function closeFeedback() {
        document.getElementById('feedback-modal').classList.add('hidden');
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
