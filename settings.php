<?php
// settings.php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db_connection.php';

$user = current_user();
$page_title = "Account Settings";

$errors = [];
$success_msg = "";

// Fetch current user settings
$settings = [];
try {
    $stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Set defaults if empty
if (!$settings) {
    $settings = [
        'language' => 'english',
        'timezone' => 'Africa/Lagos',
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'push_notifications' => 1,
        'session_timeout' => '30'
    ];
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $language = sanitize($_POST['language'] ?? 'english');
    $timezone = sanitize($_POST['timezone'] ?? 'Africa/Lagos');
    $session_timeout = sanitize($_POST['session_timeout'] ?? '30');
    
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
    
    try {
        // Upsert setting
        $stmt = $conn->prepare("INSERT INTO user_settings (id, user_id, language, timezone, email_notifications, sms_notifications, push_notifications, session_timeout) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                language = VALUES(language), 
                                timezone = VALUES(timezone), 
                                email_notifications = VALUES(email_notifications), 
                                sms_notifications = VALUES(sms_notifications), 
                                push_notifications = VALUES(push_notifications),
                                session_timeout = VALUES(session_timeout)");
        
        $settings_id = generate_uuid();
        $stmt->bind_param("ssssiiis", $settings_id, $user['id'], $language, $timezone, $email_notifications, $sms_notifications, $push_notifications, $session_timeout);
        $stmt->execute();
        $stmt->close();
        
        // Refresh local settings display
        $settings['language'] = $language;
        $settings['timezone'] = $timezone;
        $settings['email_notifications'] = $email_notifications;
        $settings['sms_notifications'] = $sms_notifications;
        $settings['push_notifications'] = $push_notifications;
        $settings['session_timeout'] = $session_timeout;
        
        log_activity("Updated account settings preferences");
        $success_msg = "Your configuration preferences have been saved.";
        
    } catch (Exception $e) {
        $errors[] = "Error saving configuration: " . $e->getMessage();
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    
    <?php if (!empty($errors)): ?>
        <div class="p-4 bg-red-500/10 border border-red-500/30 text-red-600  rounded-xl text-sm font-medium">
            <?php foreach($errors as $err): ?>
                <p><?php echo $err; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_msg)): ?>
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600  rounded-xl text-sm font-medium">
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    
    <form action="settings.php" method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- Appearance & Localization Card -->
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
            <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-200  pb-3">
                <i data-lucide="palette" class="w-4 h-4 text-blue-500"></i>
                <span>Appearance & Location</span>
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="session_timeout" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Session Expiry Timeout (Minutes)</label>
                    <select name="session_timeout" id="session_timeout" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
                        <option value="15" <?php echo $settings['session_timeout'] === '15' ? 'selected' : ''; ?>>15 Minutes</option>
                        <option value="30" <?php echo $settings['session_timeout'] === '30' ? 'selected' : ''; ?>>30 Minutes (Recommended)</option>
                        <option value="60" <?php echo $settings['session_timeout'] === '60' ? 'selected' : ''; ?>>1 Hour</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="language" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">System Language</label>
                    <input type="text" name="language" id="language" value="<?php echo sanitize($settings['language']); ?>" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
                </div>
                
                <div>
                    <label for="timezone" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Timezone</label>
                    <input type="text" name="timezone" id="timezone" value="<?php echo sanitize($settings['timezone']); ?>" class="w-full px-4 py-2.5 bg-slate-50  border border-slate-200  rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
                </div>
            </div>
        </div>
        
        <!-- Notifications Card -->
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
            <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-200  pb-3">
                <i data-lucide="bell" class="w-4 h-4 text-indigo-500"></i>
                <span>Channel Dispatch Alerts</span>
            </h4>
            
            <div class="space-y-3">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?> class="rounded  border-slate-350  text-blue-600 focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-semibold text-slate-800 ">Email Notifications</span>
                        <p class="text-[11px] text-slate-400">Receive system OTPs, payments, and assignment alerts by email.</p>
                    </div>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="sms_notifications" <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?> class="rounded  border-slate-350  text-blue-600 focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-semibold text-slate-800 ">SMS Alerts</span>
                        <p class="text-[11px] text-slate-400">Dispatch direct text reminders on billing installments deadlines.</p>
                    </div>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="push_notifications" <?php echo $settings['push_notifications'] ? 'checked' : ''; ?> class="rounded  border-slate-350  text-blue-600 focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-semibold text-slate-800 ">Browser Push Alerts</span>
                        <p class="text-[11px] text-slate-400">Real-time notification bells when updates occur while active.</p>
                    </div>
                </label>
            </div>
        </div>
        
        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold rounded-xl transition-all shadow-md shadow-blue-500/20 text-sm">
                Save System Settings
            </button>
        </div>
    </form>
    
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
