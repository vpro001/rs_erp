<?php
// student/messages.php - messages from instructors/admins to the student's batches or to them directly.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Messages";

$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("s", $user['id']);
$stmt->execute();
$student_pk = $stmt->get_result()->fetch_assoc()['id'] ?? '';
$stmt->close();

$stmt = $conn->prepare("SELECT m.*, b.name AS batch_name FROM class_messages m LEFT JOIN classes b ON b.id = m.class_id
                        WHERE m.student_id = ?
                           OR (m.student_id IS NULL AND m.class_id IN (SELECT class_id FROM class_students WHERE student_id = ?))
                        ORDER BY m.created_at DESC LIMIT 100");
$stmt->bind_param("ss", $student_pk, $student_pk);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl space-y-4">
    <?php if (!$messages): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-sm text-slate-400">
            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
            No messages from your instructors yet.
        </div>
    <?php endif; ?>
    <?php foreach ($messages as $m): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex flex-wrap justify-between gap-2 text-[11px] text-slate-400">
                <span><b class="text-slate-600"><?php echo sanitize($m['sender_name']); ?></b> &bull; <?php echo $m['student_id'] ? 'To you' : sanitize($m['batch_name'] ?? 'Your class'); ?></span>
                <span><?php echo date('D d M Y, g:ia', strtotime($m['created_at'])); ?></span>
            </div>
            <h4 class="text-sm font-bold text-slate-900 mt-1"><?php echo sanitize($m['subject']); ?></h4>
            <p class="text-sm text-slate-600 whitespace-pre-line mt-1"><?php echo sanitize($m['message']); ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
