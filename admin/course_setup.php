<?php
// admin/course_setup.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "LMS Course Setup Builder";

$errors = [];
$success_msg = "";

// 1. Handle New Course Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0.00);
    $duration = sanitize($_POST['duration'] ?? '');
    $level = sanitize($_POST['level'] ?? 'BEGINNER');
    $mode = sanitize($_POST['mode'] ?? 'ONLINE');
    $instructor_id = sanitize($_POST['instructor_id'] ?? '');
    $category_id = sanitize($_POST['category_id'] ?? '');
    
    $allow_installment = isset($_POST['allow_installment']) ? 1 : 0;
    $installment_count = empty($_POST['installment_count']) ? null : intval($_POST['installment_count']);
    $installment_amount = empty($_POST['installment_amount']) ? null : floatval($_POST['installment_amount']);
    
    if (empty($title) || empty($duration) || empty($instructor_id) || empty($category_id)) {
        $errors[] = "Please fill in all required fields.";
    } else {
        try {
            $course_id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO courses (id, title, description, price, duration, level, mode, status, allow_installment, installment_count, installment_amount, instructor_id, category_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdsssiidss", $course_id, $title, $description, $price, $duration, $level, $mode, $allow_installment, $installment_count, $installment_amount, $instructor_id, $category_id);
            $stmt->execute();
            $stmt->close();
            
            log_activity("Created New LMS Course Setup", ["title" => $title]);
            $success_msg = "New course track created successfully! Select it below to build modules and lessons.";
        } catch (Exception $e) {
            $errors[] = "Insertion failed: " . $e->getMessage();
        }
    }
}

// 2. Handle New Module Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_module') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $target_course_id = sanitize($_POST['course_id'] ?? '');
    $mod_title = sanitize($_POST['title'] ?? '');
    $mod_desc = sanitize($_POST['description'] ?? '');
    $order_num = intval($_POST['order_num'] ?? 1);
    
    if (empty($target_course_id) || empty($mod_title)) {
        $errors[] = "Module title is required.";
    } else {
        try {
            $mod_id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO modules (id, course_id, title, description, order_num) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $mod_id, $target_course_id, $mod_title, $mod_desc, $order_num);
            $stmt->execute();
            $stmt->close();
            
            log_activity("Created Course Syllabus Module", ["module" => $mod_title]);
            $success_msg = "Syllabus module added successfully!";
        } catch (Exception $e) {
            $errors[] = "Module insertion failed: " . $e->getMessage();
        }
    }
}

// 3. Handle New Lesson Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_lesson') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $target_module_id = sanitize($_POST['module_id'] ?? '');
    $les_title = sanitize($_POST['title'] ?? '');
    $les_content = sanitize($_POST['content'] ?? '');
    $video_url = sanitize($_POST['video_url'] ?? '');
    $les_duration = sanitize($_POST['duration'] ?? '');
    $order_num = intval($_POST['order_num'] ?? 1);
    
    if (empty($target_module_id) || empty($les_title)) {
        $errors[] = "Lesson title is required.";
    } else {
        try {
            $les_id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO lessons (id, module_id, title, content, video_url, duration, order_num) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $les_id, $target_module_id, $les_title, $les_content, $video_url, $les_duration, $order_num);
            $stmt->execute();
            $stmt->close();
            
            log_activity("Created Syllabus Module Lesson", ["lesson" => $les_title]);
            $success_msg = "Lesson added successfully!";
        } catch (Exception $e) {
            $errors[] = "Lesson insertion failed: " . $e->getMessage();
        }
    }
}

// Fetch all staff for instructors dropdown
$instructors = [];
try {
    $res = $conn->query("SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users_staff u ON s.user_id = u.id WHERE s.status = 'ACTIVE'");
    while ($row = $res->fetch_assoc()) {
        $instructors[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch categories dropdown
$categories = [];
try {
    $res = $conn->query("SELECT id, name FROM course_categories ORDER BY name ASC");
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch existing courses catalog list
$courses = [];
try {
    $res = $conn->query("SELECT c.*, cat.name as category_name, u.first_name as staff_first, u.last_name as staff_last 
                         FROM courses c 
                         LEFT JOIN course_categories cat ON c.category_id = cat.id 
                         LEFT JOIN staff s ON c.instructor_id = s.id 
                         LEFT JOIN users_staff u ON s.user_id = u.id 
                         ORDER BY c.created_at DESC");
    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Active designer course selection
$active_course_id = sanitize($_GET['course_id'] ?? '');
$active_course = null;
$modules = [];

if (!empty($active_course_id)) {
    foreach ($courses as $c) {
        if ($c['id'] === $active_course_id) {
            $active_course = $c;
            break;
        }
    }
    
    if ($active_course) {
        // Fetch modules and nested lessons
        try {
            $stmt = $conn->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY order_num ASC");
            $stmt->bind_param("s", $active_course_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $modules[$row['id']] = $row;
                $modules[$row['id']]['lessons'] = [];
            }
            $stmt->close();
            
            if (!empty($modules)) {
                $mod_ids = implode("','", array_keys($modules));
                $res = $conn->query("SELECT * FROM lessons WHERE module_id IN ('$mod_ids') ORDER BY order_num ASC");
                while ($row = $res->fetch_assoc()) {
                    $modules[$row['module_id']]['lessons'][] = $row;
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Workspace: Course builder panels -->
    <div class="space-y-6">
        
        <!-- Add New Course Form -->
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="plus-circle" class="w-4 h-4 text-blue-500"></i>
                <span>Add New Course Catalog</span>
            </h4>
            
            <form action="course_setup.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create_course">
                
                <div>
                    <label for="title" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Course Title</label>
                    <input type="text" name="title" id="title" required placeholder="e.g. Intro to PHP Modules" class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="price" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Course Price ($)</label>
                        <input type="number" step="0.01" name="price" id="price" required placeholder="250.00" class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                    </div>
                    <div>
                        <label for="duration" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Duration</label>
                        <input type="text" name="duration" id="duration" required placeholder="e.g. 12 Weeks" class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="level" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Level</label>
                        <select name="level" id="level" class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                            <option value="BEGINNER">Beginner</option>
                            <option value="INTERMEDIATE">Intermediate</option>
                            <option value="ADVANCED">Advanced</option>
                        </select>
                    </div>
                    <div>
                        <label for="mode" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Mode</label>
                        <select name="mode" id="mode" class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                            <option value="ONLINE">Online</option>
                            <option value="OFFLINE">Offline</option>
                            <option value="HYBRID">Hybrid</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="instructor" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Instructor Host</label>
                        <select name="instructor_id" id="instructor" required class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                            <option value="">Select Instructor...</option>
                            <?php foreach($instructors as $ins): ?>
                                <option value="<?php echo $ins['id']; ?>"><?php echo sanitize($ins['first_name'] . ' ' . $ins['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="category" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Course Category</label>
                        <select name="category_id" id="category" required class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="space-y-2 border-t border-slate-100  pt-3">
                    <label class="flex items-center space-x-2 text-xs text-slate-500  cursor-pointer">
                        <input type="checkbox" name="allow_installment" id="allow_installment" class="rounded text-blue-600 focus:ring-blue-500" onclick="toggleInstallmentInputs(this)">
                        <span>Allow Payment Installments</span>
                    </label>
                    
                    <div id="installment-fields" class="hidden grid grid-cols-2 gap-4 pt-1.5">
                        <div>
                            <label for="installment_count" class="block text-[9px] font-semibold text-slate-400 uppercase mb-1">Installment Count</label>
                            <input type="number" name="installment_count" id="installment_count" placeholder="2" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-200  rounded-lg text-xs outline-none">
                        </div>
                        <div>
                            <label for="installment_amount" class="block text-[9px] font-semibold text-slate-400 uppercase mb-1">Installment Amt ($)</label>
                            <input type="number" step="0.01" name="installment_amount" id="installment_amount" placeholder="125.00" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-200  rounded-lg text-xs outline-none">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="desc" class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Description</label>
                    <textarea name="description" id="desc" placeholder="Write course metadata overview details..." rows="3" class="w-full px-4 py-3 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all"></textarea>
                </div>
                
                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-blue-500/10">
                    Create Course Track
                </button>
            </form>
        </div>
        
    </div>
    
    <!-- Right Panel: Course selection list & modules config (Col span 2) -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Courses selection lists -->
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="book-open" class="w-4 h-4 text-indigo-500"></i>
                <span>Select Course to Configure Syllabus</span>
            </h4>
            
            <?php if (empty($courses)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No course catalogs registered.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($courses as $c): ?>
                        <div class="p-4 bg-slate-50  border border-slate-200/50  rounded-xl flex items-center justify-between <?php echo $c['id'] === $active_course_id ? 'ring-2 ring-indigo-500 ring-offset-2 ' : ''; ?>">
                            <div>
                                <h5 class="text-xs font-bold text-slate-905 "><?php echo sanitize($c['title']); ?></h5>
                                <p class="text-[9px] text-slate-400 mt-1">Instructor: <?php echo sanitize($c['staff_first'] . ' ' . $c['staff_last']); ?> &bull; Category: <?php echo sanitize($c['category_name']); ?></p>
                            </div>
                            
                            <a href="course_setup.php?course_id=<?php echo $c['id']; ?>" class="px-3.5 py-1.5 bg-indigo-650 hover:bg-indigo-500 text-white rounded font-bold text-[10px] transition-colors">
                                Syllabus Builder
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Detailed Syllabus Builder Workspace -->
        <?php if ($active_course): ?>
            <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-6">
                <div class="flex justify-between items-start border-b border-slate-100  pb-3">
                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">Syllabus Designer</span>
                        <h4 class="text-sm font-bold text-slate-950  mt-1"><?php echo sanitize($active_course['title']); ?></h4>
                    </div>
                </div>
                
                <!-- Modules Accordions list -->
                <div class="space-y-4">
                    <?php if (empty($modules)): ?>
                        <p class="text-xs text-slate-400 text-center py-6">No syllabus modules structured yet. Add one below.</p>
                    <?php else: ?>
                        <?php foreach ($modules as $m_id => $m): ?>
                            <div class="border border-slate-200/50  rounded-xl p-4 space-y-3 bg-slate-50/50 ">
                                <div class="flex justify-between items-center bg-slate-100  px-3 py-2 rounded-lg">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 "><?php echo sanitize($m['title']); ?></span>
                                        <p class="text-[10px] text-slate-400 mt-0.5"><?php echo sanitize($m['description']); ?></p>
                                    </div>
                                    <span class="text-[9px] bg-indigo-500/10 text-indigo-600  px-1.5 py-0.5 rounded font-mono">Order: <?php echo $m['order_num']; ?></span>
                                </div>
                                
                                <!-- Lessons list inside module -->
                                <div class="pl-2 space-y-2">
                                    <?php foreach ($m['lessons'] as $les): ?>
                                        <div class="p-2.5 bg-white  rounded-lg flex justify-between items-center text-xs border border-slate-150 ">
                                            <div>
                                                <span class="font-bold text-slate-800 "><?php echo sanitize($les['title']); ?></span>
                                                <p class="text-[9px] text-slate-400 mt-0.5">Duration: <?php echo sanitize($les['duration']); ?> &bull; Video URL: <code><?php echo sanitize($les['video_url']); ?></code></p>
                                            </div>
                                            <span class="text-[8px] bg-slate-100  px-1.5 py-0.5 rounded font-mono">Order: <?php echo $les['order_num']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Add Lesson Form inside Module -->
                                    <div class="pt-2">
                                        <button class="text-[10px] text-blue-600  hover:underline font-bold flex items-center space-x-1" onclick="toggleForm('lesson-form-<?php echo $m_id; ?>')">
                                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                            <span>Add Lesson to Module</span>
                                        </button>
                                        
                                        <form id="lesson-form-<?php echo $m_id; ?>" action="course_setup.php?course_id=<?php echo $active_course_id; ?>" method="POST" class="hidden mt-3 p-4 bg-white  border border-slate-200  rounded-xl space-y-3">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="create_lesson">
                                            <input type="hidden" name="module_id" value="<?php echo $m_id; ?>">
                                            
                                            <div class="grid grid-cols-3 gap-3">
                                                <div class="col-span-2">
                                                    <input type="text" name="title" required placeholder="Lesson Title" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none">
                                                </div>
                                                <div>
                                                    <input type="text" name="duration" placeholder="Duration (e.g. 15 mins)" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-3 gap-3">
                                                <div class="col-span-2">
                                                    <input type="text" name="video_url" placeholder="Video URL (e.g. https://...mp4)" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none">
                                                </div>
                                                <div>
                                                    <input type="number" name="order_num" placeholder="Order Num" value="1" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none">
                                                </div>
                                            </div>
                                            <div>
                                                <textarea name="content" placeholder="Write lesson markdown contents..." rows="3" class="w-full px-3 py-2 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none"></textarea>
                                            </div>
                                            
                                            <div class="flex justify-end space-x-2">
                                                <button type="button" class="px-3 py-1 bg-slate-100  text-slate-700  rounded text-[10px] font-bold" onclick="toggleForm('lesson-form-<?php echo $m_id; ?>')">Cancel</button>
                                                <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded text-[10px] font-bold shadow-sm shadow-blue-500/10">Add Lesson</button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Add Module Form -->
                <div class="border-t border-slate-150  pt-4">
                    <button class="text-xs text-indigo-650  hover:underline font-bold flex items-center space-x-1" onclick="toggleForm('module-creation-form')">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        <span>Add Syllabus Module</span>
                    </button>
                    
                    <form id="module-creation-form" action="course_setup.php?course_id=<?php echo $active_course_id; ?>" method="POST" class="hidden mt-4 p-4 bg-slate-50  border border-slate-200  rounded-xl space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="create_module">
                        <input type="hidden" name="course_id" value="<?php echo $active_course_id; ?>">
                        
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Module Title</label>
                                <input type="text" name="title" required placeholder="e.g. Chapter 1: Basic concepts" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Sorting Order</label>
                                <input type="number" name="order_num" placeholder="1" value="1" class="w-full px-3 py-1.5 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 uppercase font-semibold mb-1">Module summary details</label>
                            <textarea name="description" placeholder="Brief outline of concepts covered..." rows="2" class="w-full px-3 py-2 bg-slate-50  border border-slate-250  rounded-lg text-xs outline-none"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-2">
                            <button type="button" class="px-4 py-1.5 bg-slate-100  text-slate-700  rounded-lg text-xs font-bold" onclick="toggleForm('module-creation-form')">Cancel</button>
                            <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow-sm">Save Module</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
    </div>

</div>

<script>
    function toggleInstallmentInputs(checkbox) {
        const fields = document.getElementById('installment-fields');
        if (checkbox.checked) {
            fields.classList.remove('hidden');
        } else {
            fields.classList.add('hidden');
        }
    }
    
    function toggleForm(formId) {
        const form = document.getElementById(formId);
        form.classList.toggle('hidden');
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
