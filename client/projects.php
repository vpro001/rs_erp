<?php
// client/projects.php
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "CRM Projects Tracker";

// 1. Single Project Detail Workspace
$project_id = sanitize($_GET['project_id'] ?? '');
$active_project = null;
$milestones = [];
$team_members = [];
$updates = [];

if (!empty($project_id)) {
    try {
        // Fetch project metadata
        $stmt = $conn->prepare("SELECT p.*, u.first_name as supervisor_first, u.last_name as supervisor_last, u.email as supervisor_email, u.phone as supervisor_phone, st.position as supervisor_position 
                                FROM projects p 
                                LEFT JOIN staff st ON p.supervisor_id = st.id 
                                LEFT JOIN users_staff u ON st.user_id = u.id 
                                WHERE p.id = ? AND p.client_id = ?");
        $stmt->bind_param("ss", $project_id, $client_id);
        $stmt->execute();
        $active_project = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($active_project) {
            // Fetch Milestones
            $stmt = $conn->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date ASC");
            $stmt->bind_param("s", $project_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $milestones[] = $row;
            }
            $stmt->close();
            
            // Fetch Team Members
            $stmt = $conn->prepare("SELECT ptm.*, u.first_name, u.last_name, u.avatar, s.position, s.department 
                                    FROM project_team_members ptm 
                                    JOIN staff s ON ptm.staff_id = s.id 
                                    JOIN users_staff u ON s.user_id = u.id 
                                    WHERE ptm.project_id = ?");
            $stmt->bind_param("s", $project_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $team_members[] = $row;
            }
            $stmt->close();
            
            // Fetch status updates / logs
            $stmt = $conn->prepare("SELECT * FROM project_updates WHERE project_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $project_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $updates[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// 2. Listing projects
$projects = [];
try {
    $stmt = $conn->prepare("SELECT p.*, u.first_name as supervisor_first, u.last_name as supervisor_last 
                            FROM projects p 
                            LEFT JOIN staff s ON p.supervisor_id = s.id 
                            LEFT JOIN users_staff u ON s.user_id = u.id 
                            WHERE p.client_id = ? 
                            ORDER BY p.start_date DESC");
    $stmt->bind_param("s", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($active_project): ?>
    <!-- DETAILED PROJECT WORKSPACE -->
    <div class="space-y-6">
        <a href="projects.php" class="text-xs text-slate-400 hover:text-slate-700 flex items-center space-x-1.5 mb-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Projects list</span>
        </a>
        <a href="vault.php?project=<?php echo $active_project['id']; ?>" class="text-xs text-indigo-600 font-semibold flex items-center space-x-1.5">
            <i data-lucide="folder-lock" class="w-4 h-4"></i>
            <span>Project documents &amp; uploads</span>
        </a>
        
        <!-- Project Title Banner Header -->
        <div class="bg-white  border border-slate-200  p-6 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-[9px] bg-slate-100  px-2.5 py-0.5 rounded font-mono text-slate-450 uppercase tracking-wider font-semibold">
                    ID: <?php echo sanitize($active_project['project_id']); ?>
                </span>
                <h2 class="text-xl font-bold text-slate-905  mt-1"><?php echo sanitize($active_project['title']); ?></h2>
                <p class="text-xs text-slate-400 mt-1"><?php echo sanitize($active_project['description']); ?></p>
            </div>
            
            <div class="text-right">
                <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Contract Budget</span>
                <h3 class="text-xl font-bold text-emerald-600  mt-0.5"><?php echo format_currency($active_project['budget']); ?></h3>
            </div>
        </div>
        
        <!-- Project Columns Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Milestones Progress Timeline -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Progress Indicator -->
                <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-3">
                    <div class="flex justify-between text-xs text-slate-400 font-semibold">
                        <span>Overall Project Milestone Completion</span>
                        <span class="text-slate-700 "><?php echo $active_project['progress']; ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100  h-3 rounded-full overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all" style="width: <?php echo $active_project['progress']; ?>%"></div>
                    </div>
                </div>
                
                <!-- Milestones Timeline list -->
                <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
                    <h4 class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                        <i data-lucide="git-commit" class="w-4 h-4 text-blue-500"></i>
                        <span>Project Milestones Timelines</span>
                    </h4>
                    
                    <?php if (empty($milestones)): ?>
                        <p class="text-xs text-slate-400 text-center py-4">No milestones scheduled.</p>
                    <?php else: ?>
                        <div class="relative border-l border-slate-200  ml-3 space-y-6">
                            <?php foreach ($milestones as $m): ?>
                                <div class="relative pl-6">
                                    <span class="absolute -left-2 top-1.5 w-4 h-4 rounded-full border-2 border-white  <?php echo $m['completed'] ? 'bg-emerald-500' : 'bg-slate-350 '; ?> flex items-center justify-center text-white">
                                        <?php if ($m['completed']): ?>
                                            <i data-lucide="check" class="w-2.5 h-2.5"></i>
                                        <?php endif; ?>
                                    </span>
                                    
                                    <div>
                                        <div class="flex justify-between items-center">
                                            <h5 class="text-xs font-bold text-slate-900 "><?php echo sanitize($m['title']); ?></h5>
                                            <span class="text-[9px] text-slate-400"><?php echo date('M d, Y', strtotime($m['due_date'])); ?></span>
                                        </div>
                                        <p class="text-xs text-slate-500  mt-1"><?php echo sanitize($m['description']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Status Logs updates -->
                <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
                    <h4 class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                        <i data-lucide="history" class="w-4 h-4 text-indigo-500"></i>
                        <span>Recent Project Logs & Updates</span>
                    </h4>
                    
                    <?php if (empty($updates)): ?>
                        <p class="text-xs text-slate-400 text-center py-4">No recent comments or updates logged.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($updates as $up): ?>
                                <div class="p-3.5 bg-slate-50  border border-slate-150  rounded-xl space-y-1.5">
                                    <div class="flex justify-between items-center text-[10px] text-slate-400">
                                        <span class="font-bold text-slate-700 ">By: <?php echo sanitize($up['author']); ?></span>
                                        <span><?php echo date('M d, Y - H:i', strtotime($up['created_at'])); ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600 "><?php echo sanitize($up['message']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            
            <!-- Right Panel: Supervisor & Engineering Members list -->
            <div class="space-y-6">
                
                <!-- Supervisor details -->
                <div class="bg-white  border border-slate-200  rounded-2xl p-5 shadow-sm space-y-4">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100  pb-2">Supervisor in Charge</h4>
                    
                    <?php if (empty($active_project['supervisor_first'])): ?>
                        <p class="text-xs text-slate-400">No supervisor assigned.</p>
                    <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <div class="bg-indigo-500/10 p-2.5 rounded-full text-indigo-500">
                                <i data-lucide="user" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h5 class="text-xs font-bold text-slate-900 "><?php echo sanitize($active_project['supervisor_first'] . ' ' . $active_project['supervisor_last']); ?></h5>
                                <span class="text-[9px] text-slate-400 uppercase tracking-wider font-semibold"><?php echo sanitize($active_project['supervisor_position']); ?></span>
                            </div>
                        </div>
                        <div class="text-[10px] text-slate-450 space-y-1 border-t border-slate-100  pt-2.5">
                            <p class="flex items-center">
                                <i data-lucide="mail" class="w-3.5 h-3.5 mr-1.5 opacity-60"></i>
                                <span><?php echo sanitize($active_project['supervisor_email']); ?></span>
                            </p>
                            <?php if ($active_project['supervisor_phone']): ?>
                                <p class="flex items-center">
                                    <i data-lucide="phone" class="w-3.5 h-3.5 mr-1.5 opacity-60"></i>
                                    <span><?php echo sanitize($active_project['supervisor_phone']); ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Engineering Team Members list -->
                <div class="bg-white  border border-slate-200  rounded-2xl p-5 shadow-sm space-y-4">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100  pb-2">Engineering Roster</h4>
                    
                    <?php if (empty($team_members)): ?>
                        <p class="text-xs text-slate-400 text-center py-4">No team members assigned.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($team_members as $tm): ?>
                                <div class="flex items-center space-x-2.5">
                                    <img src="<?php echo sanitize(avatar_url($tm['avatar'], $tm['first_name'] . ' ' . $tm['last_name'])); ?>" alt="Avatar" class="w-7 h-7 rounded-full object-cover">
                                    <div>
                                        <h5 class="text-xs font-bold text-slate-800 "><?php echo sanitize($tm['first_name'] . ' ' . $tm['last_name']); ?></h5>
                                        <p class="text-[9px] text-slate-405 leading-none mt-0.5"><?php echo sanitize($tm['role']); ?> &bull; <?php echo sanitize($tm['department']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

<?php else: ?>
    <!-- PROJECTS GRID LIST VIEW -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($projects)): ?>
            <div class="col-span-full bg-white  border border-slate-200  p-12 text-center rounded-2xl">
                <i data-lucide="kanban-square" class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-400"></i>
                <h4 class="text-sm font-bold text-slate-700 ">No client projects registered under your account profile.</h4>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <div class="bg-white  border border-slate-200  rounded-2xl p-5 shadow-sm flex flex-col justify-between hover:scale-[1.01] transition-transform">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <span class="text-[9px] bg-slate-100  px-2 py-0.5 rounded font-mono text-slate-450 uppercase font-semibold">
                                <?php echo sanitize($p['project_id']); ?>
                            </span>
                            
                            <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider <?php
                                switch($p['status']) {
                                    case 'PLANNING': echo 'bg-blue-500/10 text-blue-600 '; break;
                                    case 'IN_PROGRESS': echo 'bg-indigo-500/10 text-indigo-600 '; break;
                                    case 'COMPLETED': echo 'bg-emerald-500/10 text-emerald-600 '; break;
                                    default: echo 'bg-slate-500/10 text-slate-655 ';
                                }
                            ?>">
                                <?php echo sanitize($p['status']); ?>
                            </span>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-bold text-slate-905  line-clamp-1"><?php echo sanitize($p['title']); ?></h4>
                            <p class="text-xs text-slate-500  line-clamp-2 mt-1 leading-relaxed"><?php echo sanitize($p['description']); ?></p>
                        </div>
                        
                        <!-- Progress bar -->
                        <div class="pt-2">
                            <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                                <span>Milestones Progress</span>
                                <span class="font-bold"><?php echo $p['progress']; ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100  h-1.5 rounded-full overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width: <?php echo $p['progress']; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-slate-100  mt-4 pt-3 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] text-slate-400 uppercase font-bold block">Budget Limit</span>
                            <span class="text-xs font-bold text-slate-900 "><?php echo format_currency($p['budget']); ?></span>
                        </div>
                        
                        <a href="projects.php?project_id=<?php echo $p['id']; ?>" class="px-3 py-1.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white rounded font-bold text-[10px] transition-all">
                            Workspace
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
include __DIR__ . '/../includes/footer.php';
?>
