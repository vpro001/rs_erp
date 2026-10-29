<?php
// staff/upload_resource.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Staff - Resource Repository";

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

// Handle Upload Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_resource') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $course_id = sanitize($_POST['course_id'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $type = sanitize($_POST['type'] ?? 'DOCUMENT');
    $external_url = sanitize($_POST['url'] ?? '');

    if (empty($course_id) || empty($title) || empty($type)) {
        $errors[] = "Please fill in all required fields.";
    } else {
        $db_file_path = null;
        $formatted_size = null;

        // Handle file upload if present
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['resource_file']['name'];
            $file_tmp = $_FILES['resource_file']['tmp_name'];
            $file_size = $_FILES['resource_file']['size'];

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'zip', 'png', 'jpg', 'jpeg', 'ppt', 'pptx', 'mp4', 'mp3', 'doc', 'docx', 'xlsx'];

            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = "Unsupported file extension format.";
            } else if ($file_size > 50 * 1024 * 1024) { // 50MB limit
                $errors[] = "Files cannot exceed the 50MB size limit.";
            } else {
                $upload_dir = __DIR__ . '/../uploads/resources';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }

                $clean_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $file_name);
                $new_file_name = uniqid() . '_' . $clean_name;
                $dest_path = $upload_dir . '/' . $new_file_name;
                $db_file_path = '/uploads/resources/' . $new_file_name;

                $formatted_size = round($file_size / 1024, 1) . ' KB';
                if ($file_size > 1024 * 1024) {
                    $formatted_size = round($file_size / (1024 * 1024), 1) . ' MB';
                }

                if (!move_uploaded_file($file_tmp, $dest_path)) {
                    $errors[] = "Could not save uploaded file.";
                }
            }
        }

        if (empty($errors)) {
            try {
                $resource_id = generate_uuid();
                $final_url = empty($external_url) ? null : $external_url;

                $stmt = $conn->prepare("INSERT INTO course_resources (id, course_id, title, type, url, file_path, size) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $resource_id, $course_id, $title, $type, $final_url, $db_file_path, $formatted_size);
                $stmt->execute();
                $stmt->close();

                log_activity("Uploaded Course Lecture Resource", ["title" => $title]);
                $success_msg = "Course resource successfully uploaded to syllabus repository!";
            } catch (Exception $e) {
                $errors[] = "Insertion failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch Staff's courses to populate dropdown select
$courses = [];
try {
    $stmt = $conn->prepare("SELECT id, title FROM courses WHERE instructor_id = ? AND status = 'ACTIVE'");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch active resources uploaded by this staff member (checking course list)
$resources = [];
if (!empty($courses)) {
    $c_ids = implode("','", array_column($courses, 'id'));
    try {
        $res = $conn->query("SELECT cr.*, c.title as course_title FROM course_resources cr JOIN courses c ON cr.course_id = c.id WHERE cr.course_id IN ('$c_ids') ORDER BY cr.title ASC");
        while ($row = $res->fetch_assoc()) {
            $resources[] = $row;
        }
    } catch (Exception $e) {
        // Ignore
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

    <!-- Left Workspace: Upload form -->
    <div class="space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="plus-circle" class="w-4 h-4 text-blue-500"></i>
                <span>Add Syllabus Resource</span>
            </h4>

            <form action="upload_resource.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="upload_resource">

                <div>
                    <label for="course"
                        class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Target
                        Course Track</label>
                    <select name="course_id" id="course" required
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                        <option value="">Choose Course...</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="title"
                        class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Resource
                        Title</label>
                    <input type="text" name="title" id="title" required placeholder="Lecture 1 handout, syllabus PDF..."
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                </div>

                <div>
                    <label for="type"
                        class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Resource
                        Material Type</label>
                    <select name="type" id="type"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                        <option value="DOCUMENT">Handout Document (PDF, Doc)</option>
                        <option value="VIDEO">Video Lecture (MP4)</option>
                        <option value="PRESENTATION">Presentation Slides (PPT, Keynote)</option>
                        <option value="LINK">External reference link</option>
                        <option value="OTHER">Other resource material</option>
                    </select>
                </div>

                <div>
                    <label for="url"
                        class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">External
                        URL (If link type)</label>
                    <input type="text" name="url" id="url" placeholder="https://vimeo.com/... or https://github.com/..."
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Upload
                        Resource File (If local file)</label>
                    <label
                        class="w-full flex flex-col items-center px-4 py-5 bg-slate-50  border-2 border-dashed border-slate-200  rounded-xl cursor-pointer hover:border-blue-500 transition-colors">
                        <i data-lucide="file-up" class="w-7 h-7 text-slate-400 mb-1"></i>
                        <span id="file-label-text"
                            class="text-[10px] font-semibold text-slate-550 ">Choose resource file
                            from disk</span>
                        <input type="file" name="resource_file" id="resource-input" class="hidden"
                            onchange="updateFileLabel()">
                    </label>
                </div>

                <button type="submit"
                    class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-blue-500/10">
                    Upload Resource
                </button>
            </form>
        </div>

    </div>

    <!-- Right Panel: Resources list -->
    <div class="lg:col-span-2 space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="archive" class="w-4 h-4 text-indigo-500"></i>
                <span>Uploaded Course Handouts Repository</span>
            </h4>

            <?php if (empty($resources)): ?>
                <div class="text-center py-12 text-slate-500 ">
                    <i data-lucide="folder-off" class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-400"></i>
                    No resources uploaded.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 ">
                    <?php foreach ($resources as $res): ?>
                        <div class="py-3 flex items-center justify-between first:pt-0 last:pb-0">
                            <div class="flex items-center space-x-3 truncate pr-4">
                                <div class="bg-blue-500/10 p-2 rounded-xl text-blue-600 ">
                                    <i data-lucide="<?php
                                    switch ($res['type']) {
                                        case 'VIDEO':
                                            echo 'video';
                                            break;
                                        case 'LINK':
                                            echo 'link-2';
                                            break;
                                        default:
                                            echo 'file-text';
                                    }
                                    ?>" class="w-4 h-4"></i>
                                </div>
                                <div class="truncate">
                                    <h5 class="text-xs font-bold text-slate-900  truncate">
                                        <?php echo sanitize($res['title']); ?></h5>
                                    <div class="flex items-center space-x-2 text-[9px] text-slate-400 mt-0.5">
                                        <span>Type: <?php echo sanitize($res['type']); ?></span>
                                        <span>&bull;</span>
                                        <span>Course: <?php echo sanitize($res['course_title']); ?></span>
                                        <?php if ($res['size']): ?>
                                            <span>&bull;</span>
                                            <span>Size: <?php echo sanitize($res['size']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($res['file_path']): ?>
                                <a href="<?php echo upload_url($res['file_path']); ?>" download
                                    class="p-2 text-slate-450 hover:text-slate-700  bg-slate-50  border border-slate-200/50  rounded-xl transition-all">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                </a>
                            <?php elseif ($res['url']): ?>
                                <a href="<?php echo sanitize($res['url']); ?>" target="_blank"
                                    class="p-2 text-slate-450 hover:text-slate-700  bg-slate-50  border border-slate-200/50  rounded-xl transition-all">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
    function updateFileLabel() {
        const input = document.getElementById('resource-input');
        const text = document.getElementById('file-label-text');
        if (input.files && input.files[0]) {
            text.textContent = "Selected: " + input.files[0].name;
            text.classList.add('text-blue-600', '');
        } else {
            text.textContent = "Choose resource file from disk";
            text.classList.remove('text-blue-600', '');
        }
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>