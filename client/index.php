<?php
// client/index.php - dashboard: where the client is in the journey, and what to do next.
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Client Dashboard";

function fetch_all($conn, $sql, $types, ...$params)
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$projects = fetch_all($conn, "SELECT p.*, u.first_name AS supervisor_first, u.last_name AS supervisor_last
                              FROM projects p LEFT JOIN staff s ON p.supervisor_id = s.id LEFT JOIN users_staff u ON s.user_id = u.id
                              WHERE p.client_id = ? ORDER BY p.created_at DESC", "s", $client_id);
$proposal_counts = array_column(fetch_all($conn, "SELECT status, COUNT(*) AS n FROM proposals WHERE client_id = ? GROUP BY status", "s", $client_id), 'n', 'status');
$approved_val = floatval(fetch_all($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM proposals WHERE client_id = ? AND status = 'APPROVED'", "s", $client_id)[0]['t']);
$unpaid = fetch_all($conn, "SELECT COALESCE(SUM(amount),0) AS t, COUNT(*) AS n FROM payments WHERE client_id = ? AND status IN ('PENDING','OVERDUE')", "s", $client_id)[0];
$meetings = fetch_all($conn, "SELECT m.*, u.first_name AS staff_first FROM meetings m
                              LEFT JOIN staff s ON m.staff_id = s.id LEFT JOIN users_staff u ON s.user_id = u.id
                              WHERE m.client_id = ? AND m.status IN ('SCHEDULED','RESCHEDULED') AND m.meeting_date >= NOW()
                              ORDER BY m.meeting_date ASC LIMIT 3", "s", $client_id);
$milestones = fetch_all($conn, "SELECT m.*, p.title AS project_title FROM project_milestones m JOIN projects p ON m.project_id = p.id
                                WHERE p.client_id = ? ORDER BY m.completed ASC, m.due_date ASC LIMIT 4", "s", $client_id);

$active_projects = count(array_filter($projects, fn($p) => in_array($p['status'], ['PLANNING', 'IN_PROGRESS'], true)));
$unpaid_total = floatval($unpaid['t']);

// Next best action for this client
if ($unpaid['n'] > 0) {
    $next = ['wallet', 'bg-[#B42318]', "You have " . format_currency($unpaid_total) . " to pay", "Settle your invoice to keep your project moving.", 'Go to billing', 'billing.php'];
} elseif (!empty($proposal_counts['PENDING'])) {
    $next = ['file-text', 'bg-amber-500', "Your quote is ready", "Review the proposal our team prepared and accept it to get started.", 'Review proposal', 'proposals.php'];
} elseif (!empty($proposal_counts['DRAFT'])) {
    $next = ['hourglass', 'bg-slate-500', "We're assessing your request", "Our team is assessing your request and will set a price. You'll be notified when your proposal is ready.", 'View request', 'proposals.php'];
} elseif ($active_projects > 0) {
    $next = ['kanban-square', 'bg-indigo-600', "Your project is underway", "Follow milestones and updates from our team.", 'Track project', 'projects.php'];
} elseif (!empty($proposal_counts['APPROVED'])) {
    $next = ['calendar-clock', 'bg-emerald-600', "Deposit received - we're scheduling your project", "A supervisor will be assigned and your project workspace will appear here.", 'View proposals', 'proposals.php'];
} else {
    $next = ['rocket', 'bg-indigo-600', "What can we help you with?", implode(', ', array_column(array_slice(SERVICE_CATALOG, 0, -1), 'label')) . " - tell us what you need and get a quote from our team.", 'Request a project', 'onboarding.php'];
}

$steps = [
    ['Request', !empty($proposal_counts) || !empty($projects)],
    ['Quote', !empty($proposal_counts['PENDING']) || !empty($proposal_counts['APPROVED']) || !empty($projects)],
    ['Approve & pay', !empty($proposal_counts['APPROVED']) || !empty($projects)],
    ['Work underway', !empty($projects)],
    ['Deliver', !empty($projects) && count(array_filter($projects, fn($p) => $p['status'] === 'COMPLETED')) > 0],
];

include __DIR__ . '/../includes/header.php';
?>

<!-- Next step -->
<div class="glass-card rounded-2xl p-6 mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-center space-x-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white <?php echo $next[1]; ?>">
            <i data-lucide="<?php echo $next[0]; ?>" class="w-5 h-5"></i>
        </div>
        <div>
            <h3 class="text-base font-bold text-slate-900">Welcome, <?php echo sanitize($user['first_name']); ?>. <?php echo $next[2]; ?></h3>
            <p class="text-xs text-slate-500 mt-0.5"><?php echo $next[3]; ?></p>
        </div>
    </div>
    <a href="<?php echo $next[5]; ?>" class="px-5 py-2.5 bg-[#17264A] hover:opacity-90 text-white font-bold rounded-xl text-xs text-center whitespace-nowrap"><?php echo $next[4]; ?></a>
</div>

<!-- Journey -->
<div class="glass-card rounded-2xl p-5 mb-8">
    <div class="grid grid-cols-5 gap-2 text-center">
        <?php foreach ($steps as $i => [$label, $done]): ?>
            <div>
                <div class="mx-auto w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold <?php echo $done ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400'; ?>">
                    <?php echo $done ? '&#10003;' : $i + 1; ?>
                </div>
                <span class="text-[10px] font-semibold uppercase tracking-wider mt-1.5 block <?php echo $done ? 'text-slate-700' : 'text-slate-400'; ?>"><?php echo $label; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Metrics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Active projects</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo $active_projects; ?></h3>
    </div>
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Accepted value</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo format_currency($approved_val); ?></h3>
    </div>
    <a href="billing.php" class="glass-card p-6 rounded-2xl block">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Outstanding invoices</span>
        <h3 class="text-3xl font-bold mt-1 <?php echo $unpaid_total > 0 ? 'text-[#B42318]' : 'text-slate-900'; ?>"><?php echo format_currency($unpaid_total); ?></h3>
    </a>
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Upcoming meetings</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo count($meetings); ?></h3>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-bold text-slate-800 mb-4 flex items-center space-x-2">
                <i data-lucide="briefcase" class="w-5 h-5 text-cyan-600"></i><span>Your projects</span>
            </h4>
            <?php if (empty($projects)): ?>
                <div class="text-center py-8 text-sm text-slate-400">
                    No projects yet. <a href="onboarding.php" class="text-indigo-600 font-semibold">Request one</a> and we'll send you a quote.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($projects as $p): ?>
                        <a href="projects.php?project_id=<?php echo $p['id']; ?>" class="block p-4 rounded-xl border border-slate-100 hover:border-slate-300">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h5 class="text-sm font-bold text-slate-800"><?php echo sanitize($p['title']); ?></h5>
                                    <p class="text-[11px] text-slate-400 font-mono mt-1"><?php echo sanitize($p['project_id']); ?>
                                        <?php if ($p['supervisor_first']): ?> &bull; Supervisor: <?php echo sanitize($p['supervisor_first'] . ' ' . $p['supervisor_last']); ?><?php endif; ?></p>
                                </div>
                                <span class="text-[9px] font-mono font-bold px-2.5 py-1 rounded-full uppercase <?php echo $p['status'] === 'COMPLETED' ? 'badge-success' : ($p['status'] === 'IN_PROGRESS' ? 'badge-warning' : 'badge-info'); ?>"><?php echo sanitize(str_replace('_', ' ', $p['status'])); ?></span>
                            </div>
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-400">Progress</span>
                                <span class="text-cyan-600 font-mono"><?php echo intval($p['progress']); ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width: <?php echo intval($p['progress']); ?>%; background: #17264A;"></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-bold text-slate-800 mb-6 flex items-center space-x-2">
                <i data-lucide="git-commit" class="w-5 h-5 text-[#17264A]"></i><span>Milestones</span>
            </h4>
            <?php if (empty($milestones)): ?>
                <div class="text-center py-6 text-sm text-slate-400">Milestones appear here once your project kicks off.</div>
            <?php else: ?>
                <div class="relative border-l border-slate-200 ml-3.5 space-y-6">
                    <?php foreach ($milestones as $m): ?>
                        <div class="relative pl-6">
                            <span class="absolute -left-2.5 top-1.5 w-5 h-5 rounded-full border-2 border-[#FBFAF7] <?php echo $m['completed'] ? 'bg-emerald-500' : 'bg-slate-200'; ?>"></span>
                            <div class="flex justify-between items-center">
                                <h5 class="text-xs font-bold text-slate-800"><?php echo sanitize($m['title']); ?></h5>
                                <span class="text-[10px] font-mono text-cyan-600/70"><?php echo date('M d, Y', strtotime($m['due_date'])); ?></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1"><?php echo sanitize($m['description']); ?></p>
                            <span class="text-[9px] font-mono text-slate-400 uppercase tracking-widest mt-1 block"><?php echo sanitize($m['project_title']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-bold text-slate-800 mb-4 flex items-center space-x-2">
                <i data-lucide="calendar" class="w-5 h-5 text-[#17264A]"></i><span>Meetings</span>
            </h4>
            <?php if (empty($meetings)): ?>
                <p class="text-center py-4 text-sm text-slate-400">Nothing scheduled.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($meetings as $meet): ?>
                        <div class="p-3.5 rounded-xl border border-slate-100">
                            <h5 class="text-xs font-bold text-slate-800"><?php echo sanitize($meet['title']); ?></h5>
                            <div class="flex justify-between text-[10px] text-slate-400 mt-2">
                                <span>With <?php echo sanitize($meet['staff_first'] ?: 'our team'); ?></span>
                                <span class="font-mono text-cyan-600/80"><?php echo date('M d, H:i', strtotime($meet['meeting_date'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="meetings.php" class="block text-center mt-4 text-xs font-bold text-indigo-600">Request a meeting</a>
        </div>

        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-bold text-slate-800 mb-3 flex items-center space-x-2">
                <i data-lucide="folder-lock" class="w-5 h-5 text-emerald-600"></i><span>Document vault</span>
            </h4>
            <p class="text-xs text-slate-400 mb-3">Share requirement sheets, brand assets and contracts with our team.</p>
            <a href="vault.php" class="block text-center py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:border-slate-400">Open vault</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
