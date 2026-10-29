<?php
// client/meetings.php
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "My Meeting Schedules";

$errors = [];
$success_msg = "";

// Handle Meeting Scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_meeting') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    
    $title = sanitize($_POST['title'] ?? '');
    $staff_id = sanitize($_POST['staff_id'] ?? '');
    $meeting_date = sanitize($_POST['meeting_date'] ?? '');
    $duration = intval($_POST['duration'] ?? 30);
    $description = sanitize($_POST['description'] ?? '');
    
    $when = strtotime($meeting_date);
    $staff_ok = false;
    if ($staff_id !== '') {
        $chk = $conn->prepare("SELECT id FROM staff WHERE id = ? AND status = 'ACTIVE'");
        $chk->bind_param("s", $staff_id);
        $chk->execute();
        $staff_ok = (bool) $chk->get_result()->fetch_assoc();
        $chk->close();
    }

    if (empty($title) || empty($meeting_date)) {
        $errors[] = "Meeting title and date/time are required.";
    } elseif ($when === false || $when < time()) {
        $errors[] = "Please choose a date and time in the future.";
    } elseif (!$staff_ok) {
        $errors[] = "Please choose a team member to meet with.";
    } elseif (!in_array($duration, [15, 30, 45, 60, 90], true)) {
        $errors[] = "Please choose a valid meeting duration.";
    } else {
        $meeting_date = date('Y-m-d H:i:s', $when);
        try {
            $meet_id = generate_uuid();
            $location = "Jitsi Virtual Conference Room";
            $room_name = 'RoyalSolutionsERP_' . md5($meet_id);
            $meeting_url = "https://meet.jit.si/" . $room_name;
            
            $stmt = $conn->prepare("INSERT INTO meetings (id, title, description, client_id, staff_id, meeting_date, duration, location, meeting_url, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'SCHEDULED')");
            $stmt->bind_param("ssssssiss", $meet_id, $title, $description, $client_id, $staff_id, $meeting_date, $duration, $location, $meeting_url);
            $stmt->execute();
            $stmt->close();
            
            notify_admins("Client booked a meeting", ($client['company_name'] ?: 'A client') . " booked '$title' for " . date('M d, H:i', $when) . ".", "/staff/meetings.php");
            log_activity("Scheduled Video Meeting", ["meeting_id" => $meet_id, "title" => $title]);
            $success_msg = "Meeting successfully scheduled!";
        } catch (Exception $e) {
            $errors[] = "Failed to schedule meeting: " . $e->getMessage();
        }
    }
}

// Fetch available staff instructors for dropdown
$available_staff = [];
try {
    $res = $conn->query("SELECT s.id, u.first_name, u.last_name, s.position FROM staff s JOIN users_staff u ON s.user_id = u.id WHERE s.status = 'ACTIVE'");
    while ($row = $res->fetch_assoc()) {
        $available_staff[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// 1. Fetch meetings list
$meetings = [];
try {
    $stmt = $conn->prepare("SELECT m.*, u.first_name as staff_first, u.last_name as staff_last, u.email as staff_email 
                            FROM meetings m 
                            LEFT JOIN staff s ON m.staff_id = s.id 
                            LEFT JOIN users_staff u ON s.user_id = u.id 
                            WHERE m.client_id = ? 
                            ORDER BY m.meeting_date ASC");
    $stmt->bind_param("s", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $meetings[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// 2. Active video meeting
$active_meeting_id = sanitize($_GET['meeting_id'] ?? '');
$active_meeting = null;

if (!empty($active_meeting_id)) {
    foreach ($meetings as $meet) {
        if ($meet['id'] === $active_meeting_id) {
            $active_meeting = $meet;
            break;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-semibold">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $err): ?>
                <li><?php echo $err; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Workspace: Active Jitsi Meet Player frame -->
    <div class="lg:col-span-2 space-y-6">
        
        <?php if ($active_meeting): ?>
            <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex justify-between items-start border-b border-slate-100  pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 "><?php echo sanitize($active_meeting['title']); ?></h3>
                        <p class="text-xs text-slate-400 mt-0.5">Host: Instructor <?php echo sanitize($active_meeting['staff_first'] . ' ' . $active_meeting['staff_last']); ?></p>
                    </div>
                    <a href="meetings.php" class="text-xs text-slate-400 hover:text-slate-700 flex items-center space-x-1">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        <span>Exit Call</span>
                    </a>
                </div>
                
                <!-- Jitsi Embedded Player IFrame -->
                <div class="bg-[#0b0f19] rounded-xl overflow-hidden aspect-video border border-slate-200 relative">
                    <iframe src="https://meet.jit.si/RoyalSolutionsERP_<?php echo md5($active_meeting['id']); ?>#userInfo.displayName=%22<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>%22" class="w-full h-full border-0" allow="camera; microphone; fullscreen; display-capture; autoplay"></iframe>
                </div>
                
                <div class="p-3 bg-blue-500/5 rounded-xl border border-blue-500/10 text-xs text-slate-500 leading-relaxed">
                    <h5 class="font-bold text-blue-600  mb-1">Embedded Jitsi Video Conference</h5>
                    This conference is secured and hosted inside the ERP dashboard using WebRTC. Ensure browser camera/microphone permissions are granted.
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white  border border-slate-200  p-12 text-center rounded-2xl flex flex-col justify-center items-center min-h-[400px]">
                <i data-lucide="video" class="w-16 h-16 opacity-30 text-slate-400 mb-3 animate-pulse"></i>
                <h4 class="text-base font-bold text-slate-700 ">No active conference room selected.</h4>
                <p class="text-xs text-slate-500  mt-1 max-w-sm">Select an upcoming meeting from the schedule sidebar or book a new session with an advisor.</p>
                <button onclick="document.getElementById('schedule-modal').classList.remove('hidden')" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all flex items-center space-x-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Book New Meeting</span>
                </button>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Right Workspace: Meeting schedules list -->
    <div class="space-y-6">
        
        <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <div class="flex justify-between items-center mb-6 border-b border-slate-100  pb-3">
                <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-blue-500"></i>
                    <span>Meetings & Calendars</span>
                </h4>
                <button onclick="document.getElementById('schedule-modal').classList.remove('hidden')" class="px-2.5 py-1 bg-indigo-600/10 text-indigo-600  hover:bg-indigo-600/20 rounded-lg text-[10px] font-bold transition-all flex items-center space-x-1">
                    <i data-lucide="plus" class="w-3 h-3"></i>
                    <span>Schedule</span>
                </button>
            </div>
            
            <?php if (empty($meetings)): ?>
                <div class="text-center py-6 text-xs text-slate-400">
                    No scheduled meetings found.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($meetings as $meet): ?>
                        <div class="p-4 bg-slate-50  border border-slate-100  rounded-xl space-y-3 <?php echo $meet['id'] === $active_meeting_id ? 'ring-2 ring-indigo-500 ring-offset-2 ' : ''; ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-900 "><?php echo sanitize($meet['title']); ?></h5>
                                    <span class="text-[9px] text-slate-400 block mt-0.5"><?php echo sanitize($meet['location']); ?></span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider <?php
                                    switch($meet['status']) {
                                        case 'SCHEDULED': echo 'bg-blue-500/10 text-blue-600 '; break;
                                        case 'COMPLETED': echo 'bg-emerald-500/10 text-emerald-600 '; break;
                                        case 'CANCELLED': echo 'bg-red-500/10 text-red-600 '; break;
                                        default: echo 'bg-slate-100 text-slate-500';
                                    }
                                ?>">
                                    <?php echo sanitize($meet['status']); ?>
                                </span>
                            </div>
                            
                            <p class="text-[11px] text-slate-500  leading-relaxed"><?php echo sanitize($meet['description']); ?></p>
                            
                            <div class="flex justify-between items-center text-[10px] text-slate-400 border-t border-slate-100  pt-2.5">
                                <div>
                                    <span class="font-semibold text-slate-700  block">Host: <?php echo sanitize($meet['staff_first'] ?? 'Advisor'); ?></span>
                                    <span class="text-[8px]"><?php echo sanitize($meet['staff_email'] ?? 'support@royalsolutions.com'); ?></span>
                                </div>
                                <span class="font-bold text-slate-900 "><?php echo date('M d, H:i', strtotime($meet['meeting_date'])); ?></span>
                            </div>
                            
                            <?php if ($meet['status'] === 'SCHEDULED'): ?>
                                <a href="meetings.php?meeting_id=<?php echo $meet['id']; ?>" class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition-all flex items-center justify-center space-x-1.5 shadow-sm">
                                    <i data-lucide="video" class="w-3.5 h-3.5"></i>
                                    <span>Join Video Room</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Schedule Meeting Modal -->
<div id="schedule-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white  border border-slate-200  w-full max-w-lg rounded-2xl p-6 shadow-2xl space-y-4 m-4">
        <div class="flex justify-between items-center border-b border-slate-100  pb-3">
            <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2">
                <i data-lucide="calendar-plus" class="w-4 h-4 text-indigo-500"></i>
                <span>Schedule New Conference</span>
            </h4>
            <button class="text-slate-400 hover:text-slate-700" onclick="document.getElementById('schedule-modal').classList.add('hidden')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        
        <form action="meetings.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="schedule_meeting">
            
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Meeting Title</label>
                <input type="text" name="title" required placeholder="e.g. Project Milestone Review" class="w-full px-3 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none focus:border-indigo-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Host / Staff Advisor</label>
                    <select name="staff_id" class="w-full px-3 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none focus:border-indigo-500">
                        <?php foreach ($available_staff as $stf): ?>
                            <option value="<?php echo $stf['id']; ?>"><?php echo sanitize($stf['first_name'] . ' ' . $stf['last_name'] . ' (' . $stf['position'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Duration (Minutes)</label>
                    <select name="duration" class="w-full px-3 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none focus:border-indigo-500">
                        <option value="15">15 Mins</option>
                        <option value="30" selected>30 Mins</option>
                        <option value="45">45 Mins</option>
                        <option value="60">60 Mins</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Date & Time</label>
                <input type="datetime-local" name="meeting_date" required class="w-full px-3 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none focus:border-indigo-500">
            </div>
            
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Agenda / Description</label>
                <textarea name="description" rows="3" placeholder="Brief summary of discussion topics..." class="w-full px-3 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none focus:border-indigo-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100 ">
                <button type="button" class="px-4 py-2 bg-slate-100  text-slate-600  rounded-xl text-xs font-semibold" onclick="document.getElementById('schedule-modal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-sm">Confirm Booking</button>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
