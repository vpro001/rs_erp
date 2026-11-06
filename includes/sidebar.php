<?php
// includes/sidebar.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/staff_roles.php';
$user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$current_role = $user ? $user['role'] : '';

// Helper: active state uses neon cyan pill; inactive uses subtle hover
function is_menu_active($page_name, $current_page) {
    if ($page_name === $current_page) {
        return 'sidebar-active font-semibold';
    }
    return 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-medium';
}

function sidebar_link($url, $icon, $text, $current_page) {
    $cls = is_menu_active(basename($url), $current_page);
    return '<a href="' . BASE_URL . $url . '" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 ' . $cls . '">'
        . '<i data-lucide="' . $icon . '" class="w-4 h-4 flex-shrink-0"></i><span class="text-sm">' . $text . '</span></a>';
}

function sidebar_heading($text) {
    return '<p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pt-4 pb-2">' . $text . '</p>';
}

// Staff menu entries per staff role (see includes/staff_roles.php).
$staff_menus = [
    'INSTRUCTOR' => [
        ['/staff/classes.php', 'calendar-check', 'Classes & Attendance'],
        ['/staff/assignments.php', 'file-edit', 'Assignments'],
        ['/staff/grading.php', 'award', 'Grading Center'],
        ['/staff/messages.php', 'message-circle', 'Message Students'],
        ['/staff/upload_resource.php', 'folder-up', 'Resources Upload'],
    ],
    'MARKETING' => [
        ['/staff/sales.php', 'target', 'Sales & Prospects'],
        ['/staff/marketers.php', 'megaphone', 'Marketers & Referrals'],
        ['/staff/meetings.php', 'video', 'Client Meetings'],
    ],
    'CUSTOMER_SERVICE' => [
        ['/staff/students.php', 'user-plus', 'Student Registration'],
        ['/staff/enrollments.php', 'clipboard-list', 'Enrollments & Payments'],
        ['/staff/batches.php', 'calendar-range', 'Class Batches'],
    ],
    'HEAD_ADMIN' => [
        ['/staff/certification.php', 'badge-check', 'Certification'],
    ],
];
$my_staff_role = $current_role === 'STAFF' ? staff_role() : null;
?>

<!-- ============================================================
     SIDEBAR — Deep Space Neon Floating Glass Panel
============================================================ -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col justify-between transform -translate-x-full lg:translate-x-0 lg:static lg:flex transition-transform duration-300 ease-in-out overflow-hidden">
    
    <!-- Inner wrapper keeps content above pseudo-elements -->
    <div class="flex flex-col flex-1 relative z-10 overflow-y-auto">
        
        <!-- ── Brand Logomark ── -->
        <div class="h-16 flex items-center justify-between px-5 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-[7px] border border-[#17264A] flex items-center justify-center flex-shrink-0 font-display font-semibold text-[15px] text-[#17264A]">R</div>
                <div>
                    <span class="font-display font-semibold text-[15px] text-slate-900 block leading-tight">Royal Solutions</span>
                    <span class="text-[11px] text-slate-500 block">Operations</span>
                </div>
            </div>
            
            <button id="mobile-sidebar-close" class="lg:hidden text-slate-400 hover:text-slate-900">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <!-- ── Navigation Menu ── -->
        <nav class="flex-1 px-3 py-4 space-y-0.5">
            
            <?php if (in_array($current_role, ['ADMIN', 'MANAGER'])): ?>
                <!-- Admin & Manager Menu -->
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pt-2 pb-2">Admin Control</p>
                
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('index.php', $current_page); ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/user_control.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('user_control.php', $current_page); ?>">
                    <i data-lucide="users-round" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">User Management</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/students.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('students.php', $current_page); ?>">
                    <i data-lucide="user-plus" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Student Registration</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/enrollments.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('enrollments.php', $current_page); ?>">
                    <i data-lucide="clipboard-list" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Course Enrollments</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/course_setup.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('course_setup.php', $current_page); ?>">
                    <i data-lucide="book-open" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Course Builder</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/batches.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('batches.php', $current_page); ?>">
                    <i data-lucide="calendar-range" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Class Batches</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/proposals.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('proposals.php', $current_page); ?>">
                    <i data-lucide="file-text" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Client Proposals</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/meetings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('meetings.php', $current_page); ?>">
                    <i data-lucide="video" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Client Meetings</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/project_control.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('project_control.php', $current_page); ?>">
                    <i data-lucide="kanban-square" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Project Workspace</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/marketers.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('marketers.php', $current_page); ?>">
                    <i data-lucide="megaphone" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Marketers & Referrals</span>
                </a>
                <?php echo sidebar_link('/admin/sales.php', 'target', 'Sales & Prospects', $current_page); ?>
                <a href="<?php echo BASE_URL; ?>/admin/ledger.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('ledger.php', $current_page); ?>">
                    <i data-lucide="wallet" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Finance Ledger</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('reports.php', $current_page); ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Analytics Reports</span>
                </a>

                <?php echo sidebar_heading('Staff & Academics'); ?>
                <?php echo sidebar_link('/admin/staff.php', 'id-card', 'Staff Management', $current_page); ?>
                <?php echo sidebar_link('/admin/certification.php', 'badge-check', 'Certification', $current_page); ?>
                <?php echo sidebar_link('/admin/assignments.php', 'file-edit', 'Assignments', $current_page); ?>
                <?php echo sidebar_link('/admin/grading.php', 'award', 'Grading Center', $current_page); ?>
                <?php echo sidebar_link('/admin/messages.php', 'message-circle', 'Message Students', $current_page); ?>
                
            <?php elseif ($current_role === 'STAFF'): ?>
                <!-- Staff Menu (depends on the staff member's role) -->
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pt-2 pb-2"><?php echo STAFF_ROLES[$my_staff_role] ?? 'Staff Portal'; ?></p>

                <?php echo sidebar_link('/staff/index.php', 'layout-dashboard', 'Dashboard', $current_page); ?>
                <?php foreach ($staff_menus[$my_staff_role] ?? [] as [$url, $icon, $text]) echo sidebar_link($url, $icon, $text, $current_page); ?>
                
            <?php elseif ($current_role === 'STUDENT'): ?>
                <!-- Student Menu -->
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pt-2 pb-2">Student Portal</p>
                
                <a href="<?php echo BASE_URL; ?>/student/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('index.php', $current_page); ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/student/my_courses.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('my_courses.php', $current_page); ?>">
                    <i data-lucide="graduation-cap" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">My Courses</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/student/assignments.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('assignments.php', $current_page); ?>">
                    <i data-lucide="file-edit" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Assignments</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/student/billing.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('billing.php', $current_page); ?>">
                    <i data-lucide="credit-card" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Billing & Installments</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/student/results.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('results.php', $current_page); ?>">
                    <i data-lucide="award" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Exam Results</span>
                </a>
                <?php echo sidebar_link('/student/messages.php', 'mail', 'Messages', $current_page); ?>
                <a href="<?php echo BASE_URL; ?>/student/forum.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('forum.php', $current_page); ?>">
                    <i data-lucide="message-square" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Community Forum</span>
                </a>
                
            <?php elseif ($current_role === 'CLIENT'): ?>
                <!-- Client Menu -->
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pt-2 pb-2">Client Portal</p>
                
                <a href="<?php echo BASE_URL; ?>/client/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('index.php', $current_page); ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/client/projects.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('projects.php', $current_page); ?>">
                    <i data-lucide="kanban-square" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Projects & Timelines</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/client/proposals.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('proposals.php', $current_page); ?>">
                    <i data-lucide="file-text" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Proposals & Quotes</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/client/billing.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('billing.php', $current_page); ?>">
                    <i data-lucide="wallet" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Billing & Payments</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/client/vault.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('vault.php', $current_page); ?>">
                    <i data-lucide="folder-lock" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Document Vault</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/client/onboarding.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('onboarding.php', $current_page); ?>">
                    <i data-lucide="sparkles" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Request a Project</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/client/meetings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('meetings.php', $current_page); ?>">
                    <i data-lucide="video" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Meeting Schedule</span>
                </a>
            <?php elseif ($current_role === 'MARKETER'): ?>
                <!-- Marketer Menu -->
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pt-2 pb-2">Marketer Portal</p>

                <a href="<?php echo BASE_URL; ?>/marketer/index.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('index.php', $current_page); ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Dashboard & Clients</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/marketer/students.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('students.php', $current_page); ?>">
                    <i data-lucide="graduation-cap" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Student Referrals</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/marketer/commissions.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('commissions.php', $current_page); ?>">
                    <i data-lucide="wallet" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">My Commissions</span>
                </a>
            <?php endif; ?>

            <!-- ── Shared bottom links ── -->
            <div class="pt-4 border-t border-slate-100 mt-4 space-y-0.5">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-[0.08em] px-3 pb-1">Account</p>
                <a href="<?php echo BASE_URL; ?>/profile.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('profile.php', $current_page); ?>">
                    <i data-lucide="user-round" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">My Profile</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/settings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-md transition-colors duration-150 <?php echo is_menu_active('settings.php', $current_page); ?>">
                    <i data-lucide="settings-2" class="w-4 h-4 flex-shrink-0"></i>
                    <span class="text-sm">Settings</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- ── Footer: User Card + Logout ── -->
    <div class="relative z-10 p-3 border-t border-slate-100">
        <?php if ($user): ?>
        <div class="flex items-center space-x-3 mb-3 px-1">
            <img src="<?php echo sanitize(avatar_url($user['avatar'], $user['first_name'] . ' ' . $user['last_name'])); ?>"
                 alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-slate-200 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-800  truncate leading-none"><?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></p>
                <span class="text-[11px] text-slate-500 uppercase tracking-wide"><?php echo sanitize($my_staff_role ? STAFF_ROLES[$my_staff_role] : $user['role']); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo BASE_URL; ?>/logout.php"
           class="flex items-center justify-center space-x-2 w-full px-4 py-2 rounded-md font-semibold text-sm text-slate-700 border border-[#D3CCBC] hover:border-[#B42318] hover:text-[#B42318] transition-colors">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            <span>Sign out</span>
        </a>
    </div>
</aside>

<!-- Mobile Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden lg:hidden"></div>
