<?php
// includes/header.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();

// Fetch unread notifications for the user
$notifications = [];
$unread_count = 0;
if ($user && isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 AND archived = 0 ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("s", $user['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND archived = 0");
        $stmt->bind_param("s", $user['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $unread_count = intval($row['count']);
        }
        $stmt->close();
    } catch (Exception $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - Royal Solutions ERP" : "Royal Solutions ERP"; ?></title>

    <?php include __DIR__ . '/theme.php'; ?>
</head>

<body class="font-sans min-h-screen flex">

    <!-- App container layout -->
    <div class="flex w-full min-h-screen overflow-hidden">

        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">

            <!-- Header Navbar / Sticky Topbar -->
            <header id="topbar" class="h-16 flex items-center justify-between px-6 sticky top-0 z-40">
                <!-- Left Header: Mobile Toggle and Title -->
                <div class="flex items-center space-x-4">
                    <button id="mobile-sidebar-toggle"
                        class="lg:hidden text-slate-500 hover:text-slate-800   transition-colors">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h1 class="text-lg font-display font-semibold text-slate-900">
                        <?php echo isset($page_title) ? $page_title : "Royal Solutions"; ?>
                    </h1>
                </div>

                <!-- Right Header: Actions -->
                <div class="flex items-center space-x-4">

                    <!-- Notifications Dropdown -->
                    <div class="relative">
                        <button id="notifications-toggle"
                            class="p-2 text-slate-500 hover:text-slate-700   bg-white/60  border border-slate-200  rounded-lg transition-all duration-200 hover:border-purple-400 relative">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span id="notifications-badge"
                                class="absolute top-0 right-0 w-2 h-2 bg-[#FF2A55] rounded-full ring-1 ring-white/10 <?php echo $unread_count > 0 ? '' : 'hidden'; ?>"></span>
                        </button>

                        <!-- Notifications Panel -->
                        <div id="notifications-panel"
                            class="dropdown-panel hidden absolute right-0 mt-2 w-80 rounded-xl z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center">
                                <span
                                    class="font-semibold text-sm text-slate-900">Notifications</span>
                                <?php if ($unread_count > 0): ?>
                                    <button id="mark-all-read"
                                        class="text-xs text-[#17264A] hover:underline font-semibold">Mark
                                        all read</button>
                                <?php endif; ?>
                            </div>
                            <div id="notifications-list" class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                <?php if (empty($notifications)): ?>
                                    <div class="px-4 py-8 text-center text-sm text-slate-400 ">
                                        <i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-2 opacity-55"></i>
                                        No new notifications
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <a href="<?php echo $notif['link'] ? sanitize($notif['link']) : '#'; ?>"
                                            class="block px-4 py-3 hover:bg-slate-100 transition-colors">
                                            <div class="flex justify-between items-start">
                                                <span
                                                    class="font-semibold text-xs text-slate-800 "><?php echo sanitize($notif['title']); ?></span>
                                                <span
                                                    class="text-[10px] text-slate-400"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></span>
                                            </div>
                                            <p class="text-xs text-slate-500  mt-0.5 line-clamp-2">
                                                <?php echo sanitize($notif['message']); ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2.5 border-t border-slate-100 text-center">
                                <a href="#"
                                    class="text-xs font-semibold text-[#17264A] hover:underline">View
                                    All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Dropdown -->
                    <?php if ($user): ?>
                        <div class="relative">
                            <button id="profile-toggle" class="flex items-center space-x-2 focus:outline-none">
                                <img src="<?php echo $user['avatar'] ? sanitize($user['avatar']) : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=200&auto=format&fit=crop'; ?>"
                                    alt="User Avatar"
                                    class="w-9 h-9 rounded-full object-cover border border-slate-200 hover:border-purple-400 hover:scale-105 transition-all duration-200 ">
                                <div class="hidden md:block text-left">
                                    <p class="text-xs font-semibold text-slate-900  leading-tight">
                                        <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    <span
                                        class="text-[11px] font-medium text-slate-500 uppercase tracking-wide"><?php echo sanitize($user['role']); ?></span>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                            </button>

                            <!-- Profile Menu Panel -->
                            <div id="profile-panel"
                                class="dropdown-panel hidden absolute right-0 mt-2 w-52 rounded-xl z-50 py-1">
                                <div class="px-4 py-2.5 border-b border-slate-100 md:hidden">
                                    <p class="text-sm font-semibold text-slate-955 ">
                                        <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    <span
                                        class="text-xs text-slate-400 uppercase tracking-wider font-semibold"><?php echo sanitize($user['role']); ?></span>
                                </div>

                                <a href="<?php echo BASE_URL; ?>/profile.php"
                                    class="flex items-center space-x-2 px-4 py-2.5 text-sm text-slate-600  hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>/settings.php"
                                    class="flex items-center space-x-2 px-4 py-2.5 text-sm text-slate-600  hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    <span>Account Settings</span>
                                </a>

                                <hr class="border-slate-100 my-1">

                                <a href="<?php echo BASE_URL; ?>/logout.php"
                                    class="flex items-center space-x-2 px-4 py-2.5 text-sm hover:bg-[#FF2A55]/8 transition-colors duration-150">
                                    <i data-lucide="log-out" class="w-4 h-4 text-red-500"></i>
                                    <span class="text-[#FF2A55] font-semibold">Sign Out</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </header>

            <!-- Flash Messages Display -->
            <?php $flash = get_flash_message(); ?>
            <?php if ($flash): ?>
                <div class="px-6 pt-4">
                    <div class="flex items-center justify-between p-3.5 rounded-xl border <?php
                    switch ($flash['type']) {
                        case 'success':
                            echo 'badge-success';
                            $icon = 'circle-check';
                            break;
                        case 'error':
                            echo 'badge-danger';
                            $icon = 'alert-circle';
                            break;
                        case 'warning':
                            echo 'badge-warning';
                            $icon = 'alert-triangle';
                            break;
                        default:
                            echo 'badge-info';
                            $icon = 'info';
                    }
                    ?>">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5 flex-shrink-0"></i>
                            <span class="text-sm font-medium"><?php echo $flash['message']; ?></span>
                        </div>
                        <button class="text-slate-400 hover:text-slate-600  transition-colors"
                            onclick="this.parentElement.remove()">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Main Workspace -->
            <main class="flex-1 p-6">