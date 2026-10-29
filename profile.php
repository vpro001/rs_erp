<?php
// profile.php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db_connection.php';

$user = current_user();
$page_title = "My Profile";
$user_table = user_table_for_role($user['role']);

$errors = [];
$success_msg = "";

// Fetch extended profile
$profile = [];
try {
    $stmt = $conn->prepare("SELECT p.bio, p.full_name, u.first_name, u.last_name, u.phone, u.avatar 
                            FROM `$user_table` u 
                            LEFT JOIN profiles p ON p.user_id = u.id 
                            WHERE u.id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "First name and Last name are required fields.";
    } else {
        $conn->begin_transaction();
        try {
            // Update the role's credential table
            $stmt = $conn->prepare("UPDATE `$user_table` SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssss", $first_name, $last_name, $phone, $user['id']);
            $stmt->execute();
            $stmt->close();
            
            // Upsert profiles row (accounts created by admin have none yet)
            $full_name = $first_name . ' ' . $last_name;
            $profile_id = generate_uuid();
            $user_type = $user['role'] === 'MANAGER' ? 'ADMIN' : $user['role'];
            $stmt = $conn->prepare("INSERT INTO profiles (id, user_id, user_type, full_name, bio) VALUES (?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), bio = VALUES(bio)");
            $stmt->bind_param("sssss", $profile_id, $user['id'], $user_type, $full_name, $bio);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            // Refresh session details
            $_SESSION['user']['first_name'] = $first_name;
            $_SESSION['user']['last_name'] = $last_name;
            
            // Log activity
            log_activity("Updated profile details", ["full_name" => $full_name]);
            
            // Reload updated values
            $profile['first_name'] = $first_name;
            $profile['last_name'] = $last_name;
            $profile['phone'] = $phone;
            $profile['bio'] = $bio;
            $profile['full_name'] = $full_name;
            
            $success_msg = "Your profile has been updated successfully.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
    
    <div class="flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-6 pb-6 border-b border-slate-200  mb-6">
        <div class="relative group">
            <img src="<?php echo $user['avatar'] ? sanitize($user['avatar']) : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=200&auto=format&fit=crop'; ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border-2 border-blue-500/20 shadow-md">
            <div class="absolute inset-0 bg-slate-900/60 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                <i data-lucide="camera" class="w-6 h-6 text-white"></i>
            </div>
        </div>
        <div>
            <h3 class="text-xl font-bold text-slate-900 "><?php echo sanitize($profile['full_name'] ?? ''); ?></h3>
            <span class="text-xs px-2.5 py-0.5 rounded bg-blue-500/10 text-blue-600  font-semibold uppercase tracking-wider inline-block mt-1">
                <?php echo sanitize($user['role']); ?>
            </span>
            <p class="text-xs text-slate-400 mt-1">Register Email: <?php echo sanitize($user['email']); ?></p>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="mb-4 p-4 bg-red-500/10 border border-red-500/30 text-red-600  rounded-xl text-sm font-medium">
            <?php foreach($errors as $err): ?>
                <p><?php echo $err; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_msg)): ?>
        <div class="mb-4 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600  rounded-xl text-sm font-medium">
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    
    <form action="profile.php" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="first_name" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">First Name</label>
                <input type="text" name="first_name" id="first_name" required value="<?php echo sanitize($profile['first_name'] ?? ''); ?>" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
            </div>
            
            <div>
                <label for="last_name" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Last Name</label>
                <input type="text" name="last_name" id="last_name" required value="<?php echo sanitize($profile['last_name'] ?? ''); ?>" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
            </div>
        </div>
        
        <div>
            <label for="phone" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Phone Number</label>
            <input type="text" name="phone" id="phone" value="<?php echo sanitize($profile['phone'] ?? ''); ?>" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
        </div>
        
        <div>
            <label for="bio" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Bio / Personal statement</label>
            <textarea name="bio" id="bio" rows="4" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm"><?php echo sanitize($profile['bio'] ?? ''); ?></textarea>
        </div>
        
        <div class="pt-4 border-t border-slate-200  flex justify-end">
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl transition-all shadow-md shadow-blue-500/20 text-sm">
                Save Profile Changes
            </button>
        </div>
    </form>
    
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
