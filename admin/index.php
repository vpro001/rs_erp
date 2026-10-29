<?php
// admin/index.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Admin Dashboard";

// 1. Fetch user counts
$user_counts = [
    'STUDENT' => 0,
    'CLIENT' => 0,
    'STAFF' => 0,
    'ADMIN' => 0
];
try {
    $parts = [];
    foreach (USER_TABLES as $table) {
        $parts[] = "SELECT role FROM `$table`";
    }
    $res = $conn->query("SELECT role, COUNT(*) as count FROM (" . implode(" UNION ALL ", $parts) . ") x GROUP BY role");
    while ($row = $res->fetch_assoc()) {
        // Managers are counted with admins on the dashboard tiles.
        $key = $row['role'] === 'MANAGER' ? 'ADMIN' : $row['role'];
        $user_counts[$key] += intval($row['count']);
    }
} catch (Exception $e) {
    // Ignore
}
$total_users = array_sum($user_counts);

// 2. Total Enrollments
$total_enrollments = 0;
try {
    $res = $conn->query("SELECT COUNT(*) as count FROM enrollments");
    if ($row = $res->fetch_assoc()) {
        $total_enrollments = intval($row['count']);
    }
} catch (Exception $e) {
    // Ignore
}

// 3. Ongoing projects
$active_projects = 0;
try {
    $res = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('PLANNING', 'IN_PROGRESS')");
    if ($row = $res->fetch_assoc()) {
        $active_projects = intval($row['count']);
    }
} catch (Exception $e) {
    // Ignore
}

// 4. Financial Ledger Dues
$total_income = 0.00;
$total_expenses = 0.00;
try {
    $res = $conn->query("SELECT SUM(amount) as total FROM income_records");
    if ($row = $res->fetch_assoc()) {
        $total_income = floatval($row['total']);
    }
    $res = $conn->query("SELECT SUM(amount) as total FROM expense_records");
    if ($row = $res->fetch_assoc()) {
        $total_expenses = floatval($row['total']);
    }
} catch (Exception $e) {
    // Ignore
}
$net_profit = $total_income - $total_expenses;

// 5. Open support tickets
$open_tickets = [];
try {
    $res = $conn->query("SELECT t.*, u.first_name, u.last_name 
                         FROM support_tickets t 
                         LEFT JOIN students s ON t.student_id = s.id 
                         LEFT JOIN users_student u ON s.user_id = u.id 
                         WHERE t.status = 'OPEN' 
                         ORDER BY t.created_at DESC LIMIT 3");
    while ($row = $res->fetch_assoc()) {
        $open_tickets[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// 6. Recent Complaints
$complaints = [];
try {
    $res = $conn->query("SELECT * FROM complaints ORDER BY created_at DESC LIMIT 3");
    while ($row = $res->fetch_assoc()) {
        $complaints[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ── Bento-Box Grid Indicators ── -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <!-- User Administrations -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Global Users</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight"><?php echo $total_users; ?></h3>
            <p class="text-xs text-slate-500 ">
                <span class="font-semibold text-slate-700  font-mono"><?php echo $user_counts['STUDENT']; ?></span> Students &bull; <span class="font-semibold text-slate-700  font-mono"><?php echo $user_counts['CLIENT']; ?></span> Clients
            </p>
        </div>
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-cyan-50 text-cyan-600 border border-cyan-200">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
    </div>
    
    <!-- Active Course Enrollments -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">LMS Enrollments</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight"><?php echo $total_enrollments; ?></h3>
            <p class="text-xs text-slate-500 ">Active course seats allocated</p>
        </div>
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-[#17264A]/10 text-[#17264A] border border-[#17264A]/15">
            <i data-lucide="graduation-cap" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Active CRM Projects -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">CRM Projects</span>
            <h3 class="text-3xl font-display font-bold text-slate-900  tracking-tight"><?php echo $active_projects; ?></h3>
            <p class="text-xs text-slate-500 ">Ongoing client deliverables</p>
        </div>
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-indigo-500/10 text-cyan-600 border border-cyan-200">
            <i data-lucide="kanban-square" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Net Cash Balance -->
    <div class="glass-card card-hoverable p-6 rounded-2xl flex items-center justify-between">
        <div class="space-y-1.5">
            <span class="text-[10px] font-display font-semibold text-slate-400  uppercase tracking-[0.12em]">Net Cash Flow</span>
            <h3 class="text-3xl font-display font-bold tracking-tight <?php echo $net_profit >= 0 ? 'text-emerald-600' : 'text-[#B42318]'; ?>">
                <?php echo format_currency($net_profit); ?>
            </h3>
            <p class="text-xs text-slate-500 ">Revenue vs Expenditures</p>
        </div>
        <div class="w-12 h-12 rounded-xl flex items-center justify-center <?php echo $net_profit >= 0 ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-[#B42318]/8 text-[#B42318] border border-[#B42318]/15'; ?>">
            <i data-lucide="landmark" class="w-5 h-5"></i>
        </div>
    </div>

</div>

<!-- ── Admin Panel Workspace Bento Grid ── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Workspace (Financial ledger charts & lists) -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Financial summary details -->
        <div class="glass-card p-6 rounded-2xl">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-base font-display font-bold text-slate-800  flex items-center space-x-2">
                    <i data-lucide="wallet" class="w-5 h-5 text-emerald-600"></i>
                    <span>Administrative Finance Ledger Summary</span>
                </h4>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="p-4 rounded-xl border border-slate-100" style="background: rgba(0,255,157,0.03);">
                    <span class="text-[10px] font-display font-semibold uppercase tracking-wider text-slate-400 ">Total Income</span>
                    <h4 class="text-2xl font-display font-bold text-emerald-600 mt-1"><?php echo format_currency($total_income); ?></h4>
                </div>
                <div class="p-4 rounded-xl border border-slate-100" style="background: rgba(255,42,85,0.03);">
                    <span class="text-[10px] font-display font-semibold uppercase tracking-wider text-slate-400 ">Total Expenses</span>
                    <h4 class="text-2xl font-display font-bold text-[#B42318] mt-1"><?php echo format_currency($total_expenses); ?></h4>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="flex flex-wrap gap-3">
                <a href="<?php echo BASE_URL; ?>/admin/ledger.php" class="btn-neon px-4 py-2.5 rounded-xl text-xs flex items-center space-x-1.5">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Manage Cashflow Records</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn-glass px-4 py-2.5 rounded-xl text-xs flex items-center space-x-1.5">
                    <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
                    <span>Generate Analytics Reports</span>
                </a>
            </div>
        </div>

        <!-- Open Support Tickets -->
        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="help-circle" class="w-5 h-5 text-cyan-600"></i>
                <span>Open Support Tickets</span>
            </h4>
            
            <?php if (empty($open_tickets)): ?>
                <div class="text-center py-8 text-sm text-slate-400 ">
                    No active support requests pending.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th class="py-2.5 px-4">Subject</th>
                                <th class="py-2.5 px-4">Student</th>
                                <th class="py-2.5 px-4 text-right">Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($open_tickets as $tick): ?>
                                <tr class="hover:bg-purple-50">
                                    <td class="py-3 px-4 font-semibold text-xs text-slate-800 ">
                                        <?php echo sanitize($tick['title']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-slate-500 ">
                                        <?php echo sanitize($tick['first_name'] . ' ' . $tick['last_name']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded uppercase <?php
                                            echo $tick['priority'] === 'HIGH' ? 'badge-danger' : ($tick['priority'] === 'MEDIUM' ? 'badge-warning' : 'badge-info');
                                        ?>">
                                            <?php echo sanitize($tick['priority']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Workspace (Complaints & Quick Admin Tools) -->
    <div class="space-y-6">
        
        <!-- Customer Complaints Tracker Log -->
        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="shield-alert" class="w-5 h-5 text-[#B42318]"></i>
                <span>Customer Complaints Tracker Log</span>
            </h4>
            
            <?php if (empty($complaints)): ?>
                <div class="text-center py-8 text-sm text-slate-400 ">
                    No active complaints logged.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($complaints as $c): ?>
                        <div class="p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-all duration-200">
                            <div class="flex justify-between items-center mb-1.5">
                                <h5 class="text-xs font-semibold text-slate-800 "><?php echo sanitize($c['title']); ?></h5>
                                <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded uppercase <?php
                                    echo $c['status'] === 'PENDING' ? 'badge-warning' : 'badge-success';
                                ?>">
                                    <?php echo sanitize($c['status']); ?>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500  leading-relaxed"><?php echo sanitize($c['details']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Global Setup Tools -->
        <div class="glass-card p-6 rounded-2xl">
            <h4 class="text-base font-display font-bold text-slate-800  mb-4 flex items-center space-x-2">
                <i data-lucide="settings" class="w-5 h-5 text-slate-400 "></i>
                <span>System Administration Shortcuts</span>
            </h4>
            
            <div class="space-y-2.5">
                <a href="<?php echo BASE_URL; ?>/admin/user_control.php" class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-purple-400 transition-all duration-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-[#17264A]/10 text-[#17264A] border border-[#17264A]/10">
                            <i data-lucide="user-cog" class="w-4 h-4"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-700 ">Configure User Roles</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 "></i>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/admin/course_setup.php" class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-purple-400 transition-all duration-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-cyan-50 text-cyan-600 border border-cyan-200">
                            <i data-lucide="book-open" class="w-4 h-4"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-700 ">Launch Course Builder</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 "></i>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/admin/project_control.php" class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-purple-400 transition-all duration-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-indigo-500/10 text-cyan-600 border border-cyan-200">
                            <i data-lucide="briefcase" class="w-4 h-4"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-700 ">Project Allocator</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 "></i>
                </a>
            </div>
        </div>

    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
