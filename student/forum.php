<?php
// student/forum.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "LMS Discussion Forum";

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

// 1. Thread Detail View
$post_id = sanitize($_GET['post_id'] ?? '');
$active_post = null;
$replies = [];

if (!empty($post_id)) {
    // Handle New Reply Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_reply') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("CSRF validation failed.");
        }

        $content = sanitize($_POST['content'] ?? '');

        if (empty($content)) {
            $errors[] = "Reply content cannot be empty.";
        } else {
            try {
                $reply_id = generate_uuid();
                $stmt = $conn->prepare("INSERT INTO forum_replies (id, post_id, student_id, content) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $reply_id, $post_id, $student_id, $content);
                $stmt->execute();
                $stmt->close();

                log_activity("Posted Forum Reply", ["post_id" => $post_id]);
                $success_msg = "Your reply has been successfully posted!";
            } catch (Exception $e) {
                $errors[] = "Failed to submit reply: " . $e->getMessage();
            }
        }
    }

    // Fetch Active Post details
    try {
        $stmt = $conn->prepare("SELECT fp.*, u.first_name, u.last_name, u.avatar, c.title as course_title,
                                (SELECT COUNT(*) FROM forum_post_likes fpl WHERE fpl.post_id = fp.id) as likes_count,
                                (SELECT COUNT(*) FROM forum_post_likes fpl WHERE fpl.post_id = fp.id AND fpl.student_id = ?) as is_liked
                                FROM forum_posts fp 
                                JOIN students s ON fp.student_id = s.id 
                                JOIN users_student u ON s.user_id = u.id 
                                LEFT JOIN courses c ON fp.course_id = c.id
                                WHERE fp.id = ?");
        $stmt->bind_param("ss", $student_id, $post_id);
        $stmt->execute();
        $active_post = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($active_post) {
            // Fetch replies
            $stmt = $conn->prepare("SELECT fr.*, u.first_name, u.last_name, u.avatar, u.role,
                                    (SELECT COUNT(*) FROM forum_reply_likes frl WHERE frl.reply_id = fr.id) as likes_count,
                                    (SELECT COUNT(*) FROM forum_reply_likes frl WHERE frl.reply_id = fr.id AND frl.student_id = ?) as is_liked
                                    FROM forum_replies fr 
                                    JOIN students s ON fr.student_id = s.id 
                                    JOIN users_student u ON s.user_id = u.id 
                                    WHERE fr.post_id = ? 
                                    ORDER BY fr.is_solution DESC, fr.created_at ASC");
            $stmt->bind_param("ss", $student_id, $post_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $replies[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// 2. Listing view: Handle Create New Thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $category = sanitize($_POST['category'] ?? 'General');
    $course_filter_id = sanitize($_POST['course_id'] ?? '');
    $tags_raw = sanitize($_POST['tags'] ?? '');

    if (empty($title) || empty($content)) {
        $errors[] = "Thread Title and Content description are required.";
    } else {
        try {
            $new_post_id = generate_uuid();
            $db_course_id = empty($course_filter_id) ? null : $course_filter_id;

            // Format tags as json
            $tags_arr = array_filter(array_map('trim', explode(',', $tags_raw)));
            $tags_json = json_encode(array_values($tags_arr));

            $stmt = $conn->prepare("INSERT INTO forum_posts (id, student_id, course_id, title, content, category, tags) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $new_post_id, $student_id, $db_course_id, $title, $content, $category, $tags_json);
            $stmt->execute();
            $stmt->close();

            log_activity("Created Forum Thread", ["post_title" => $title]);
            $success_msg = "Your discussion thread has been successfully created!";
        } catch (Exception $e) {
            $errors[] = "Failed to create thread: " . $e->getMessage();
        }
    }
}

// Listing parameters
$search_q = sanitize($_GET['q'] ?? '');
$filter_cat = sanitize($_GET['category'] ?? '');

// Fetch all posts
$posts = [];
try {
    $q_str = "SELECT fp.*, u.first_name, u.last_name, u.avatar, c.title as course_title,
              (SELECT COUNT(*) FROM forum_replies fr WHERE fr.post_id = fp.id) as replies_count,
              (SELECT COUNT(*) FROM forum_post_likes fpl WHERE fpl.post_id = fp.id) as likes_count
              FROM forum_posts fp 
              JOIN students s ON fp.student_id = s.id 
              JOIN users_student u ON s.user_id = u.id 
              LEFT JOIN courses c ON fp.course_id = c.id
              WHERE 1=1";

    $types = "";
    $params = [];

    if (!empty($search_q)) {
        $q_str .= " AND (fp.title LIKE ? OR fp.content LIKE ?)";
        $types .= "ss";
        $like_val = "%$search_q%";
        $params[] = $like_val;
        $params[] = $like_val;
    }

    if (!empty($filter_cat)) {
        $q_str .= " AND fp.category = ?";
        $types .= "s";
        $params[] = $filter_cat;
    }

    $q_str .= " ORDER BY fp.is_pinned DESC, fp.created_at DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($q_str);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $posts[] = $row;
        }
        $stmt->close();
    } else {
        $res = $conn->query($q_str);
        while ($row = $res->fetch_assoc()) {
            $posts[] = $row;
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch active student courses for thread creation select
$my_courses = [];
try {
    $stmt = $conn->prepare("SELECT c.id, c.title FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $my_courses[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
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

<?php if ($active_post): ?>
    <!-- THREAD DETAIL VIEW -->
    <div class="space-y-6 max-w-4xl mx-auto">
        <a href="forum.php" class="text-xs text-slate-400 hover:text-slate-700 flex items-center space-x-1.5 mb-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Forum Boards</span>
        </a>

        <!-- Main Post Card -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex justify-between items-start">
                <div class="flex items-center space-x-3">
                    <img src="<?php echo sanitize(avatar_url($active_post['avatar'], $active_post['first_name'] . ' ' . $active_post['last_name'])); ?>"
                        alt="Avatar" class="w-10 h-10 rounded-full object-cover">
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 ">
                            <?php echo sanitize($active_post['first_name'] . ' ' . $active_post['last_name']); ?></h4>
                        <span
                            class="text-[9px] text-slate-400"><?php echo date('d M Y - H:i', strtotime($active_post['created_at'])); ?></span>
                    </div>
                </div>

                <span
                    class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full bg-purple-500/10 text-purple-600 ">
                    <?php echo sanitize($active_post['category']); ?>
                </span>
            </div>

            <h2 class="text-lg font-bold text-slate-950  flex items-center flex-wrap gap-2">
                <?php if ($active_post['is_resolved']): ?>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-lg text-[9px] font-bold bg-emerald-500/10 text-emerald-600  border border-emerald-500/20">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5 mr-1"></i>
                        RESOLVED
                    </span>
                <?php endif; ?>
                <span><?php echo sanitize($active_post['title']); ?></span>
            </h2>
            <p class="text-xs text-slate-655  leading-relaxed whitespace-pre-line">
                <?php echo sanitize($active_post['content']); ?></p>

            <!-- Tags & Course metadata -->
            <div class="flex flex-wrap gap-2 pt-2">
                <?php if ($active_post['course_title']): ?>
                    <span
                        class="bg-slate-100  text-slate-500  px-2 py-0.5 rounded text-[10px] font-semibold flex items-center">
                        <i data-lucide="graduation-cap" class="w-3 h-3 mr-1 text-slate-400"></i>
                        <?php echo sanitize($active_post['course_title']); ?>
                    </span>
                <?php endif; ?>

                <?php
                $tags = json_decode($active_post['tags'], true);
                if ($tags):
                    foreach ($tags as $t):
                        ?>
                        <span
                            class="bg-blue-500/5 text-blue-600  px-2 py-0.5 rounded text-[10px] font-medium border border-blue-500/10">#<?php echo sanitize($t); ?></span>
                    <?php endforeach; endif; ?>
            </div>

            <!-- Like buttons -->
            <div class="border-t border-slate-100  pt-3 flex items-center space-x-4">
                <button
                    class="flex items-center space-x-1.5 text-xs font-semibold <?php echo $active_post['is_liked'] > 0 ? 'text-rose-500' : 'text-slate-400 hover:text-rose-500'; ?> transition-colors"
                    onclick="likePost('<?php echo $active_post['id']; ?>', this)">
                    <i data-lucide="heart"
                        class="w-4 h-4 <?php echo $active_post['is_liked'] > 0 ? 'fill-rose-500' : ''; ?>"></i>
                    <span class="likes-count-display"><?php echo $active_post['likes_count']; ?> Likes</span>
                </button>
            </div>
        </div>

        <!-- Replies Segment -->
        <div class="space-y-4">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest px-2"><?php echo count($replies); ?>
                Comments & Solutions</h4>

            <?php foreach ($replies as $rep):
                $is_sol = (int) $rep['is_solution'] === 1;
                ?>
                <div
                    class="border rounded-2xl p-5 space-y-3 transition-all <?php echo $is_sol ? 'bg-emerald-500/5  border-emerald-500/40 shadow-sm' : 'bg-slate-50  border-slate-150 '; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center space-x-2.5">
                            <img src="<?php echo sanitize(avatar_url($rep['avatar'], $rep['first_name'] . ' ' . $rep['last_name'])); ?>"
                                alt="Avatar" class="w-8 h-8 rounded-full object-cover">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h5 class="text-xs font-bold text-slate-900 ">
                                        <?php echo sanitize($rep['first_name'] . ' ' . $rep['last_name']); ?></h5>
                                    <span
                                        class="text-[8px] bg-slate-150  text-slate-450 uppercase px-1 rounded"><?php echo sanitize($rep['role']); ?></span>
                                    <?php if ($is_sol): ?>
                                        <span
                                            class="text-[8px] bg-emerald-500/20 text-emerald-600  font-bold px-1.5 py-0.5 rounded flex items-center">
                                            <i data-lucide="check" class="w-2.5 h-2.5 mr-0.5"></i>
                                            SOLUTION
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span
                                    class="text-[8px] text-slate-400"><?php echo date('d M Y - H:i', strtotime($rep['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <?php
                            // Show Mark/Unmark as Solution button if student is thread owner, or is staff/admin
                            $can_mark = false;
                            if (in_array($user['role'], ['STAFF', 'ADMIN', 'MANAGER'])) {
                                $can_mark = true;
                            } else if ($active_post['student_id'] === $student_id) {
                                $can_mark = true;
                            }
                            if ($can_mark):
                                ?>
                                <button
                                    class="flex items-center space-x-1.5 text-[9px] font-bold uppercase transition-all px-2 py-0.5 rounded-lg border <?php echo $is_sol ? 'text-amber-600 border-amber-500/20 bg-amber-500/5 hover:bg-amber-500/10' : 'text-emerald-600 border-emerald-500/20 bg-emerald-500/5 hover:bg-emerald-500/10'; ?>"
                                    onclick="toggleSolution('<?php echo $active_post['id']; ?>', '<?php echo $rep['id']; ?>', this)">
                                    <i data-lucide="<?php echo $is_sol ? 'x-circle' : 'check-circle'; ?>" class="w-3.5 h-3.5"></i>
                                    <span><?php echo $is_sol ? 'Unmark' : 'Mark Solution'; ?></span>
                                </button>
                            <?php endif; ?>

                            <button
                                class="flex items-center space-x-1 text-[10px] <?php echo $rep['is_liked'] > 0 ? 'text-rose-500' : 'text-slate-400 hover:text-rose-500'; ?> transition-colors"
                                onclick="likeReply('<?php echo $rep['id']; ?>', this)">
                                <i data-lucide="heart"
                                    class="w-3.5 h-3.5 <?php echo $rep['is_liked'] > 0 ? 'fill-rose-500' : ''; ?>"></i>
                                <span class="reply-likes-count"><?php echo $rep['likes_count']; ?></span>
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-slate-655  leading-relaxed whitespace-pre-line">
                        <?php echo sanitize($rep['content']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Post Reply Form -->
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2">
                <i data-lucide="reply" class="w-4 h-4 text-indigo-500"></i>
                <span>Write a Reply</span>
            </h4>

            <form action="forum.php?post_id=<?php echo $post_id; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create_reply">

                <div>
                    <textarea name="content" required placeholder="Provide guidance, suggestions or solutions..." rows="4"
                        class="w-full px-4 py-3 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="px-5 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-blue-500/10">
                        Post Reply
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- FORUM BOARDS LIST VIEW -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Panel: Filter search and Create Thread form -->
        <div class="space-y-6">

            <!-- Create Thread Form -->
            <div
                class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
                <h4
                    class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-blue-500"></i>
                    <span>Create a New Thread</span>
                </h4>

                <form action="forum.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="create_post">

                    <div>
                        <label for="post-title"
                            class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Thread
                            Title</label>
                        <input type="text" name="title" id="post-title" required placeholder="Question or topic..."
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                    </div>

                    <div>
                        <label for="post-category"
                            class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Category
                            Board</label>
                        <select name="category" id="post-category"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                            <option value="General">General Boards</option>
                            <option value="Coding Help">Coding Help</option>
                            <option value="Assignments">Assignments Dues</option>
                            <option value="Announcements">Announcements</option>
                        </select>
                    </div>

                    <div>
                        <label for="post-course"
                            class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Link to
                            Course (Optional)</label>
                        <select name="course_id" id="post-course"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                            <option value="">No Course Link</option>
                            <?php foreach ($my_courses as $mc): ?>
                                <option value="<?php echo $mc['id']; ?>"><?php echo sanitize($mc['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="post-tags"
                            class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Search
                            Tags (Comma separated)</label>
                        <input type="text" name="tags" id="post-tags" placeholder="PHP, CSRF, grading"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                    </div>

                    <div>
                        <label for="post-desc"
                            class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Thread
                            content details</label>
                        <textarea name="content" id="post-desc" required
                            placeholder="Describe your question or discussion..." rows="5"
                            class="w-full px-4 py-3 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all"></textarea>
                    </div>

                    <button type="submit"
                        class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-blue-500/10">
                        Create Thread
                    </button>
                </form>
            </div>

        </div>

        <!-- Right Panel: Thread Boards feeds lists -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Filters & Searches -->
            <div
                class="bg-white  border border-slate-200  p-4 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center gap-4">
                <form action="forum.php" method="GET" class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <input type="text" name="q" placeholder="Search keywords..." value="<?php echo sanitize($search_q); ?>"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">

                    <select name="category"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-xs transition-all">
                        <option value="">All Categories</option>
                        <option value="General" <?php echo $filter_cat === 'General' ? 'selected' : ''; ?>>General Boards
                        </option>
                        <option value="Coding Help" <?php echo $filter_cat === 'Coding Help' ? 'selected' : ''; ?>>Coding Help
                        </option>
                        <option value="Assignments" <?php echo $filter_cat === 'Assignments' ? 'selected' : ''; ?>>Assignments
                            Dues</option>
                        <option value="Announcements" <?php echo $filter_cat === 'Announcements' ? 'selected' : ''; ?>>
                            Announcements</option>
                    </select>

                    <button type="submit"
                        class="bg-slate-100  hover:bg-slate-200  text-slate-700  font-semibold rounded-xl text-xs transition-all py-2">
                        Filter Feed
                    </button>
                </form>
            </div>

            <!-- Thread posts list -->
            <div class="space-y-4">
                <?php if (empty($posts)): ?>
                    <div
                        class="bg-white  border border-slate-200  p-12 text-center rounded-2xl">
                        <i data-lucide="message-square" class="w-12 h-12 mx-auto opacity-30 text-slate-400 mb-3"></i>
                        <h4 class="text-sm font-bold text-slate-700 ">No forum discussions logged. Be the
                            first to start a thread!</h4>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $p): ?>
                        <div
                            class="bg-white  border border-slate-200  p-5 rounded-2xl shadow-sm hover:scale-[1.005] transition-all space-y-3 relative">
                            <?php if ($p['is_pinned']): ?>
                                <span class="absolute top-4 right-4 text-blue-500 flex items-center text-[10px] font-bold">
                                    <i data-lucide="pin" class="w-3.5 h-3.5 mr-0.5 fill-blue-500/20"></i>
                                    PINNED
                                </span>
                            <?php endif; ?>

                            <div class="flex items-center space-x-2.5">
                                <img src="<?php echo sanitize(avatar_url($p['avatar'], $p['first_name'] . ' ' . $p['last_name'])); ?>"
                                    alt="Avatar" class="w-7 h-7 rounded-full object-cover">
                                <div>
                                    <span
                                        class="text-[10px] font-bold text-slate-800  block"><?php echo sanitize($p['first_name'] . ' ' . $p['last_name']); ?></span>
                                    <span
                                        class="text-[8px] text-slate-400"><?php echo date('d M Y', strtotime($p['created_at'])); ?></span>
                                </div>
                            </div>

                            <div>
                                <a href="forum.php?post_id=<?php echo $p['id']; ?>"
                                    class="text-sm font-bold text-slate-905  hover:text-blue-500  transition-colors line-clamp-1 pr-14">
                                    <?php echo sanitize($p['title']); ?>
                                </a>
                                <p class="text-xs text-slate-500  line-clamp-2 mt-1 leading-relaxed">
                                    <?php echo sanitize($p['content']); ?></p>
                            </div>

                            <div
                                class="flex items-center justify-between pt-2 border-t border-slate-100  text-[10px]">
                                <div class="flex space-x-3">
                                    <span
                                        class="text-purple-600 bg-purple-500/10 px-2 py-0.5 rounded font-bold uppercase tracking-wider">
                                        <?php echo sanitize($p['category']); ?>
                                    </span>
                                    <?php if ($p['course_title']): ?>
                                        <span class="text-slate-500 bg-slate-100  px-2 py-0.5 rounded">
                                            <?php echo sanitize($p['course_title']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($p['is_resolved']): ?>
                                        <span
                                            class="text-emerald-600  bg-emerald-500/10 px-2 py-0.5 rounded font-bold flex items-center">
                                            <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                            RESOLVED
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center space-x-4 text-slate-400 font-semibold">
                                    <span class="flex items-center">
                                        <i data-lucide="message-square" class="w-3.5 h-3.5 mr-1 text-slate-400"></i>
                                        <?php echo $p['replies_count']; ?> Replies
                                    </span>
                                    <span class="flex items-center">
                                        <i data-lucide="heart" class="w-3.5 h-3.5 mr-1 text-slate-400"></i>
                                        <?php echo $p['likes_count']; ?> Likes
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
<?php endif; ?>

<!-- Like AJAX JavaScript handlers -->
<script>
    function likePost(postId, buttonElement) {
        const likeUrl = (window.BASE_URL || '') + '/api/like_post.php';
        fetch(likeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, student_id: '<?php echo $student_id; ?>' })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const countDisplay = buttonElement.querySelector('.likes-count-display');
                    countDisplay.textContent = data.likes_count + ' Likes';

                    const icon = buttonElement.querySelector('svg');
                    if (data.liked) {
                        buttonElement.classList.add('text-rose-500');
                        buttonElement.classList.remove('text-slate-400');
                        icon.classList.add('fill-rose-500');
                    } else {
                        buttonElement.classList.remove('text-rose-500');
                        buttonElement.classList.add('text-slate-400');
                        icon.classList.remove('fill-rose-500');
                    }
                }
            })
            .catch(err => console.error("Error liking post:", err));
    }

    function likeReply(replyId, buttonElement) {
        const likeRepUrl = (window.BASE_URL || '') + '/api/like_reply.php';
        fetch(likeRepUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reply_id: replyId, student_id: '<?php echo $student_id; ?>' })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const countDisplay = buttonElement.querySelector('.reply-likes-count');
                    countDisplay.textContent = data.likes_count;

                    const icon = buttonElement.querySelector('svg');
                    if (data.liked) {
                        buttonElement.classList.add('text-rose-500');
                        buttonElement.classList.remove('text-slate-400');
                        icon.classList.add('fill-rose-500');
                    } else {
                        buttonElement.classList.remove('text-rose-500');
                        buttonElement.classList.add('text-slate-400');
                        icon.classList.remove('fill-rose-500');
                    }
                }
            })
            .catch(err => console.error("Error liking reply:", err));
    }

    function toggleSolution(postId, replyId, buttonElement) {
        const resolveUrl = (window.BASE_URL || '') + '/api/resolve_thread.php';
        fetch(resolveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, reply_id: replyId })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || "Failed to update solution status.");
                }
            })
            .catch(err => console.error("Error toggling solution:", err));
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>