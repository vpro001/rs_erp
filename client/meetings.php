<?php
// client/meetings.php - the client asks for a date and time; an admin confirms or reschedules it
// and decides who hosts. Clients never choose the host.
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Meetings";

$errors = [];
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_ok()) {
        die("CSRF validation failed.");
    }

    if ($_POST['action'] === 'request_meeting') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = intval($_POST['duration'] ?? 30);
        $when = strtotime($_POST['meeting_date'] ?? '');

        if ($title === '' || mb_strlen($title) > 190) {
            $errors[] = "Please give the meeting a short title.";
        } elseif ($when === false || $when < time() + 3600) {
            $errors[] = "Please choose a date and time at least an hour from now.";
        } elseif (!in_array($duration, [15, 30, 45, 60, 90], true)) {
            $errors[] = "Please choose a valid duration.";
        } else {
            $meet_id = generate_uuid();
            $when_sql = date('Y-m-d H:i:s', $when);
            $stmt = $conn->prepare("INSERT INTO meetings (id, title, description, client_id, meeting_date, duration, status)
                                    VALUES (?, ?, ?, ?, ?, ?, 'REQUESTED')");
            $stmt->bind_param("sssssi", $meet_id, $title, $description, $client_id, $when_sql, $duration);
            $stmt->execute();
            $stmt->close();

            notify_admins("Meeting requested", ($client['company_name'] ?: 'A client') . " requested '$title' for " . date('M d, H:i', $when) . ". Confirm or reschedule it.", "/admin/meetings.php");
            log_activity("Requested meeting", ["meeting_id" => $meet_id, "title" => $title]);
            $success_msg = "Request sent. We'll confirm the time or propose another one, and you'll be notified.";
        }
    } elseif ($_POST['action'] === 'cancel_meeting') {
        $stmt = $conn->prepare("UPDATE meetings SET status = 'CANCELLED' WHERE id = ? AND client_id = ? AND status IN ('REQUESTED','SCHEDULED','RESCHEDULED')");
        $stmt->bind_param("ss", $_POST['meeting_id'], $client_id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed) {
            notify_admins("Meeting cancelled by client", ($client['company_name'] ?: 'A client') . " cancelled a meeting.", "/admin/meetings.php");
            $success_msg = "Meeting cancelled.";
        }
    }
}

$meetings = [];
$stmt = $conn->prepare("SELECT m.*, u.first_name AS staff_first, u.last_name AS staff_last
                        FROM meetings m
                        LEFT JOIN staff s ON m.staff_id = s.id
                        LEFT JOIN users_staff u ON s.user_id = u.id
                        WHERE m.client_id = ?
                        ORDER BY FIELD(m.status,'REQUESTED','RESCHEDULED','SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED'), m.meeting_date ASC");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Joinable room: only confirmed meetings
$active_meeting = null;
$active_id = $_GET['meeting_id'] ?? '';
foreach ($meetings as $meet) {
    if ($meet['id'] === $active_id && in_array($meet['status'], ['SCHEDULED', 'RESCHEDULED', 'IN_PROGRESS'], true)) {
        $active_meeting = $meet;
    }
}

$status_ui = [
    'REQUESTED' => ['Awaiting confirmation', 'bg-amber-500/10 text-amber-600'],
    'SCHEDULED' => ['Confirmed', 'bg-blue-500/10 text-blue-600'],
    'RESCHEDULED' => ['Rescheduled by our team', 'bg-indigo-500/10 text-indigo-600'],
    'IN_PROGRESS' => ['In progress', 'bg-blue-500/10 text-blue-600'],
    'COMPLETED' => ['Completed', 'bg-emerald-500/10 text-emerald-600'],
    'CANCELLED' => ['Cancelled', 'bg-red-500/10 text-red-600'],
];

include __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-semibold"><?php echo sanitize($err); ?></div>
<?php endforeach; ?>
<?php if ($success_msg): ?>
    <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 text-xs font-semibold"><?php echo sanitize($success_msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <div class="lg:col-span-2 space-y-6">
        <?php if ($active_meeting): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900"><?php echo sanitize($active_meeting['title']); ?></h3>
                        <p class="text-xs text-slate-400 mt-0.5">Host: <?php echo sanitize(trim(($active_meeting['staff_first'] ?? '') . ' ' . ($active_meeting['staff_last'] ?? '')) ?: 'Royal Solutions team'); ?></p>
                    </div>
                    <a href="meetings.php" class="text-xs text-slate-400 hover:text-slate-700 flex items-center space-x-1"><i data-lucide="x" class="w-4 h-4"></i><span>Exit call</span></a>
                </div>
                <div class="bg-[#0b0f19] rounded-xl overflow-hidden aspect-video border border-slate-200">
                    <iframe src="https://meet.jit.si/RoyalSolutionsERP_<?php echo md5($active_meeting['id']); ?>#userInfo.displayName=%22<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>%22" class="w-full h-full border-0" allow="camera; microphone; fullscreen; display-capture; autoplay"></iframe>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2 border-b border-slate-100 pb-3 mb-4">
                    <i data-lucide="calendar-plus" class="w-4 h-4 text-indigo-500"></i><span>Request a meeting</span>
                </h4>
                <p class="text-xs text-slate-400 mb-4">Tell us when suits you. Our team will confirm that time or suggest another, and assign who will meet with you.</p>
                <form action="meetings.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="request_meeting">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">What would you like to discuss?</label>
                        <input type="text" name="title" required maxlength="190" placeholder="e.g. Discuss my website project" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Preferred date &amp; time</label>
                            <input type="datetime-local" name="meeting_date" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Duration</label>
                            <select name="duration" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none">
                                <?php foreach ([15, 30, 45, 60, 90] as $d): ?>
                                    <option value="<?php echo $d; ?>" <?php echo $d === 30 ? 'selected' : ''; ?>><?php echo $d; ?> minutes</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Notes (optional)</label>
                        <textarea name="description" rows="3" placeholder="Anything we should prepare?" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-indigo-500"></textarea>
                    </div>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-sm">Request this time</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm h-fit">
        <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2 border-b border-slate-100 pb-3 mb-4">
            <i data-lucide="calendar" class="w-4 h-4 text-blue-500"></i><span>Your meetings</span>
        </h4>
        <?php if (empty($meetings)): ?>
            <p class="text-center py-6 text-xs text-slate-400">No meetings yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($meetings as $meet):
                    [$label, $cls] = $status_ui[$meet['status']] ?? [$meet['status'], 'bg-slate-100 text-slate-500'];
                    $open = in_array($meet['status'], ['REQUESTED', 'SCHEDULED', 'RESCHEDULED'], true);
                    ?>
                    <div class="p-4 bg-slate-50 border border-slate-100 rounded-xl space-y-2.5">
                        <div class="flex justify-between items-start gap-2">
                            <h5 class="text-xs font-bold text-slate-900"><?php echo sanitize($meet['title']); ?></h5>
                            <span class="px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider whitespace-nowrap <?php echo $cls; ?>"><?php echo $label; ?></span>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            <b><?php echo date('D, M d, Y - H:i', strtotime($meet['meeting_date'])); ?></b> &bull; <?php echo intval($meet['duration']); ?> min
                        </p>
                        <?php if ($meet['status'] === 'REQUESTED'): ?>
                            <p class="text-[10px] text-slate-400">This is the time you asked for. We'll confirm or suggest another.</p>
                        <?php elseif ($meet['status'] === 'RESCHEDULED'): ?>
                            <p class="text-[10px] text-indigo-600">Our team moved this meeting to the time shown above.</p>
                        <?php endif; ?>
                        <?php if ($meet['staff_first']): ?>
                            <p class="text-[10px] text-slate-400">Host: <?php echo sanitize($meet['staff_first'] . ' ' . $meet['staff_last']); ?></p>
                        <?php endif; ?>
                        <div class="flex gap-2">
                            <?php if (in_array($meet['status'], ['SCHEDULED', 'RESCHEDULED'], true)): ?>
                                <a href="meetings.php?meeting_id=<?php echo $meet['id']; ?>" class="flex-1 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold text-center">Join video room</a>
                            <?php endif; ?>
                            <?php if ($open): ?>
                                <form action="meetings.php" method="POST" onsubmit="return confirm('Cancel this meeting?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="cancel_meeting">
                                    <input type="hidden" name="meeting_id" value="<?php echo $meet['id']; ?>">
                                    <button class="px-3 py-1.5 border border-red-500/30 text-red-600 rounded-lg text-[10px] font-bold">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
