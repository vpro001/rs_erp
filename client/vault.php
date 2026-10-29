<?php
// client/vault.php
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Client Document Vault";

$errors = [];
$success_msg = "";

// Documents always belong to one of the client's projects
$stmt = $conn->prepare("SELECT id, project_id, title FROM projects WHERE client_id = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$my_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$project_names = array_column($my_projects, 'title', 'id');

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $upload_project = $_POST['project_id'] ?? '';

    if (!isset($project_names[$upload_project])) {
        $errors[] = "Please choose which project this document belongs to.";
    } elseif (isset($_FILES['vault_file']) && $_FILES['vault_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['vault_file']['name'];
        $file_tmp = $_FILES['vault_file']['tmp_name'];
        $file_size = $_FILES['vault_file']['size'];
        $mime_type = $_FILES['vault_file']['type'];

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'zip', 'png', 'jpg', 'jpeg', 'psd', 'txt', 'docx', 'xlsx'];

        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = "Unsupported file format.";
        } else if ($file_size > 20 * 1024 * 1024) { // 20MB limit
            $errors[] = "Files cannot exceed the 20MB size limit.";
        } else {
            // Save file
            $vault_dir = __DIR__ . '/../uploads/documents';
            if (!is_dir($vault_dir)) {
                @mkdir($vault_dir, 0755, true);
            }

            $clean_file_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $file_name);
            $new_file_name = uniqid() . '_' . $clean_file_name;
            $dest_path = $vault_dir . '/' . $new_file_name;
            $db_file_path = '/uploads/documents/' . $new_file_name;

            // Format size
            $formatted_size = round($file_size / 1024, 1) . ' KB';
            if ($file_size > 1024 * 1024) {
                $formatted_size = round($file_size / (1024 * 1024), 1) . ' MB';
            }

            if (move_uploaded_file($file_tmp, $dest_path)) {
                try {
                    $uploaded_by = $user['first_name'] . ' ' . $user['last_name'];
                    $change_summary = sanitize($_POST['change_summary'] ?? 'Uploaded new document version');

                    // Check if file with same name and client_id already exists
                    $stmt = $conn->prepare("SELECT * FROM documents WHERE file_name = ? AND client_id = ? AND project_id = ?");
                    $stmt->bind_param("sss", $file_name, $client_id, $upload_project);
                    $stmt->execute();
                    $existing_doc = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $conn->begin_transaction();

                    if ($existing_doc) {
                        // 1. Back up existing doc to document_versions
                        $version_id = generate_uuid();
                        $stmt = $conn->prepare("INSERT INTO document_versions (id, document_id, version_number, file_path, file_size, uploaded_by, uploaded_at, change_summary) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssisssss", $version_id, $existing_doc['id'], $existing_doc['current_version'], $existing_doc['file_path'], $existing_doc['file_size'], $existing_doc['uploaded_by'], $existing_doc['uploaded_at'], $change_summary);
                        $stmt->execute();
                        $stmt->close();

                        // 2. Update existing doc with new file info, increment version
                        $new_version = $existing_doc['current_version'] + 1;
                        $stmt = $conn->prepare("UPDATE documents 
                                                SET file_path = ?, file_size = ?, mime_type = ?, uploaded_by = ?, uploaded_at = CURRENT_TIMESTAMP, current_version = ? 
                                                WHERE id = ?");
                        $stmt->bind_param("ssssis", $db_file_path, $formatted_size, $mime_type, $uploaded_by, $new_version, $existing_doc['id']);
                        $stmt->execute();
                        $stmt->close();

                        $success_msg = "A new version of '" . sanitize($file_name) . "' (v" . $new_version . ") has been uploaded successfully!";
                    } else {
                        // Standard insert
                        $doc_id = generate_uuid();
                        $stmt = $conn->prepare("INSERT INTO documents (id, file_name, file_path, file_size, mime_type, client_id, project_id, uploaded_by, current_version) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                        $stmt->bind_param("ssssssss", $doc_id, $file_name, $db_file_path, $formatted_size, $mime_type, $client_id, $upload_project, $uploaded_by);
                        $stmt->execute();
                        $stmt->close();

                        $success_msg = "Document successfully uploaded to the project vault!";
                    }

                    $conn->commit();
                    log_activity("Uploaded document to vault", ["file" => $file_name, "project_id" => $upload_project]);
                    notify_admins("Client uploaded a document", ($client['company_name'] ?: 'A client') . " uploaded '" . $file_name . "' to project " . $project_names[$upload_project] . ".", "/admin/project_control.php?project_id=" . $upload_project);
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "Database operation failed: " . $e->getMessage();
                }
            } else {
                $errors[] = "Error saving file on local storage.";
            }
        }
    } else {
        $errors[] = "Please select a valid file to upload.";
    }
}

// Fetch documents on vault
$documents = [];
try {
    $filter = $_GET['project'] ?? '';
    if ($filter !== '' && !isset($project_names[$filter])) {
        $filter = '';
    }
    $stmt = $conn->prepare("SELECT d.*, p.title AS project_title FROM documents d LEFT JOIN projects p ON p.id = d.project_id
                            WHERE d.client_id = ? AND (? = '' OR d.project_id = ?) ORDER BY d.uploaded_at DESC");
    $stmt->bind_param("sss", $client_id, $filter, $filter);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace: Upload area with drag-drop -->
    <div class="space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="upload" class="w-4 h-4 text-blue-500"></i>
                <span>Upload New Documents</span>
            </h4>

            <?php if (empty($my_projects)): ?>
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 leading-relaxed">
                    Documents are attached to a project, and you don't have one yet. Once your proposal is accepted, the deposit is paid and we kick off your project, you can share files here.
                </div>
            <?php else: ?>
            <form id="upload-form" action="vault.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="upload_doc">

                <div>
                    <label for="upload-project" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Project</label>
                    <select name="project_id" id="upload-project" required
                        class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl outline-none text-xs">
                        <?php foreach ($my_projects as $mp): ?>
                            <option value="<?php echo $mp['id']; ?>" <?php echo (($filter ?? '') === $mp['id']) ? 'selected' : ''; ?>><?php echo sanitize($mp['title'] . ' (' . $mp['project_id'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Drag-drop layout area -->
                <div id="drop-zone"
                    class="border-2 border-dashed border-slate-200  hover:border-blue-500 rounded-xl p-8 text-center cursor-pointer transition-colors relative">
                    <input type="file" name="vault_file" id="file-input" required
                        class="absolute inset-0 opacity-0 cursor-pointer">
                    <div class="space-y-2 pointer-events-none">
                        <i data-lucide="file-up" class="w-8 h-8 mx-auto text-slate-400 "></i>
                        <h5 class="text-xs font-semibold text-slate-700 ">Drag files here or click to
                            browse</h5>
                        <p class="text-[10px] text-slate-400">PDF, ZIP, PNG, JPG, PSD, TXT, DOCX (Max 20MB)</p>
                    </div>
                    <div id="file-selected-info"
                        class="hidden text-xs text-blue-600  font-semibold mt-4"></div>
                </div>

                <div>
                    <label for="change-summary"
                        class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Change
                        Summary (For revised versions)</label>
                    <input type="text" name="change_summary" id="change-summary"
                        placeholder="e.g. Updated milestone targets..."
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                </div>

                <!-- Simulated Progress Bar UI -->
                <div id="progress-bar-container" class="hidden space-y-1.5">
                    <div class="flex justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Uploading...</span>
                        <span id="progress-percentage">0%</span>
                    </div>
                    <div class="w-full bg-slate-100  h-1.5 rounded-full overflow-hidden">
                        <div id="progress-bar-fill"
                            class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all duration-300"
                            style="width: 0%"></div>
                    </div>
                </div>

                <button type="submit" id="upload-submit-btn"
                    class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-blue-500/10">
                    Upload to Vault
                </button>
            </form>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Panel: Files List -->
    <div class="lg:col-span-2 space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="folder-open" class="w-4 h-4 text-indigo-500"></i>
                <span>Document Vault Files</span>
            </h4>

            <?php if (count($my_projects) > 1): ?>
                <form method="GET" action="vault.php" class="mb-4 flex items-center gap-2">
                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Project</label>
                    <select name="project" onchange="this.form.submit()" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                        <option value="">All projects</option>
                        <?php foreach ($my_projects as $mp): ?>
                            <option value="<?php echo $mp['id']; ?>" <?php echo ($filter ?? '') === $mp['id'] ? 'selected' : ''; ?>><?php echo sanitize($mp['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <?php if (empty($documents)): ?>
                <div class="text-center py-12 text-slate-500 ">
                    <i data-lucide="archive" class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-400"></i>
                    No documents found in vault.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 ">
                    <?php foreach ($documents as $doc): ?>
                        <div class="py-4 first:pt-0 last:pb-0 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3 truncate pr-4">
                                    <div class="bg-indigo-500/10 p-2.5 rounded-xl text-indigo-600 ">
                                        <i data-lucide="file" class="w-4 h-4"></i>
                                    </div>
                                    <div class="truncate">
                                        <div class="flex items-center space-x-2">
                                            <h5 class="text-xs font-bold text-slate-900  truncate">
                                                <?php echo sanitize($doc['file_name']); ?></h5>
                                            <span
                                                class="bg-indigo-500/10 text-indigo-650  text-[8px] font-bold px-1.5 py-0.5 rounded">v<?php echo $doc['current_version']; ?></span>
                                            <span class="bg-slate-100 text-slate-600 text-[8px] font-bold px-1.5 py-0.5 rounded truncate max-w-[10rem]"><?php echo sanitize($doc['project_title'] ?: 'No project'); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2 text-[9px] text-slate-400 mt-0.5">
                                            <span>Size: <?php echo sanitize($doc['file_size']); ?></span>
                                            <span>&bull;</span>
                                            <span>By: <?php echo sanitize($doc['uploaded_by']); ?></span>
                                            <span>&bull;</span>
                                            <span>Updated:
                                                <?php echo date('M d, H:i', strtotime($doc['uploaded_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <!-- History toggle -->
                                    <button
                                        class="p-2 text-slate-400 hover:text-slate-700  bg-slate-50  hover:scale-105 border border-slate-200/50  rounded-xl transition-all flex items-center space-x-1"
                                        onclick="toggleHistory('<?php echo $doc['id']; ?>', this)">
                                        <i data-lucide="history" class="w-4 h-4"></i>
                                        <span class="text-[9px] font-bold uppercase hidden sm:inline">History</span>
                                    </button>

                                    <a href="<?php echo upload_url($doc['file_path']); ?>" download
                                        class="p-2 text-slate-400 hover:text-slate-700  bg-slate-50  hover:scale-105 border border-slate-200/50  rounded-xl transition-all">
                                        <i data-lucide="download" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- History container -->
                            <div id="history-<?php echo $doc['id']; ?>"
                                class="hidden pl-12 pr-4 py-3 bg-slate-50/50  border border-slate-100  rounded-xl space-y-3">
                                <h6
                                    class="text-[9px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                    <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                    <span>Version History log</span>
                                </h6>

                                <div class="space-y-2.5 divide-y divide-slate-100/50 ">
                                    <?php
                                    $hist_stmt = $conn->prepare("SELECT * FROM document_versions WHERE document_id = ? ORDER BY version_number DESC");
                                    $hist_stmt->bind_param("s", $doc['id']);
                                    $hist_stmt->execute();
                                    $hist_res = $hist_stmt->get_result();
                                    $has_history = false;

                                    while ($hist = $hist_res->fetch_assoc()):
                                        $has_history = true;
                                        ?>
                                        <div class="pt-2.5 first:pt-0 flex items-center justify-between">
                                            <div class="space-y-1">
                                                <div class="flex items-center space-x-2">
                                                    <span
                                                        class="text-[9px] font-bold text-slate-700 ">v<?php echo $hist['version_number']; ?></span>
                                                    <span
                                                        class="text-[8px] text-slate-400"><?php echo date('M d, Y - H:i', strtotime($hist['uploaded_at'])); ?></span>
                                                    <span class="text-[8px] text-slate-400">&bull;
                                                        <?php echo sanitize($hist['file_size']); ?></span>
                                                </div>
                                                <p class="text-[10px] text-slate-500  italic">
                                                    "<?php echo sanitize($hist['change_summary']); ?>"</p>
                                                <span class="text-[8px] text-slate-400 block">Uploaded by:
                                                    <?php echo sanitize($hist['uploaded_by']); ?></span>
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <a href="<?php echo upload_url($hist['file_path']); ?>" download
                                                    class="p-1 text-slate-400 hover:text-slate-655" title="Download Version">
                                                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                                </a>
                                                <button class="p-1 text-blue-500 hover:text-blue-700"
                                                    title="Rollback to this version"
                                                    onclick="rollbackVersion('<?php echo $doc['id']; ?>', '<?php echo $hist['id']; ?>')">
                                                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php
                                    endwhile;
                                    $hist_stmt->close();

                                    if (!$has_history):
                                        ?>
                                        <p class="text-[10px] text-slate-400 py-1">No previous versions available for this file.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Upload Progress Animation scripts -->
<script>
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');
    const dropZone = document.getElementById('drop-zone');
    const fileInfo = document.getElementById('file-selected-info');
    const uploadBtn = document.getElementById('upload-submit-btn');
    const progressContainer = document.getElementById('progress-bar-container');
    const progressBarFill = document.getElementById('progress-bar-fill');
    const progressPercentage = document.getElementById('progress-percentage');

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                fileInfo.textContent = "Selected: " + fileInput.files[0].name;
                fileInfo.classList.remove('hidden');
            }
        });
    }

    // Simulate drag effects
    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-blue-500');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-blue-500');
        });

        dropZone.addEventListener('drop', () => {
            dropZone.classList.remove('border-blue-500');
        });
    }

    // Trigger simulated progress bar on form submit
    const form = document.getElementById('upload-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (fileInput.files && fileInput.files[0]) {
                e.preventDefault();
                uploadBtn.disabled = true;
                progressContainer.classList.remove('hidden');

                let percent = 0;
                const interval = setInterval(() => {
                    percent += 10;
                    progressBarFill.style.width = percent + '%';
                    progressPercentage.textContent = percent + '%';

                    if (percent >= 100) {
                        clearInterval(interval);
                        // Submit form actual
                        form.submit();
                    }
                }, 100);
            }
        });
    }

    function toggleHistory(docId, btn) {
        const historyContainer = document.getElementById('history-' + docId);
        if (historyContainer) {
            historyContainer.classList.toggle('hidden');
        }
    }

    function rollbackVersion(docId, versionId) {
        if (confirm("Are you sure you want to rollback to this version? This will move the current version into the history log.")) {
            const rollbackUrl = (window.BASE_URL || '') + '/api/rollback_document.php';
            fetch(rollbackUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document_id: docId, version_id: versionId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || "Failed to rollback version.");
                    }
                })
                .catch(err => console.error("Error rolling back document:", err));
        }
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>