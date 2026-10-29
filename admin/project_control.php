<?php
// admin/project_control.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "CRM Project Setup Allocator";

$errors = [];
$success_msg = "";

// 1. Handle Project Initiation from Proposal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initiate_project') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $proposal_id = $_POST['proposal_id'] ?? '';
    $supervisor_id = $_POST['supervisor_id'] ?? '';
    $project_serial = trim($_POST['project_id'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);

    if (empty($proposal_id) || empty($supervisor_id) || $project_serial === '' || !$start_ts || !$end_ts) {
        $errors[] = "Please fill in all required fields.";
    } elseif ($end_ts <= $start_ts) {
        $errors[] = "The end date must be after the start date.";
    } else {
        $conn->begin_transaction();
        try {
            // Title, price and category come from the accepted proposal, not from the form
            $stmt = $conn->prepare("SELECT p.*, cl.user_id AS client_user_id FROM proposals p JOIN clients cl ON cl.id = p.client_id
                                    WHERE p.id = ? AND p.status = 'APPROVED' FOR UPDATE");
            $stmt->bind_param("s", $proposal_id);
            $stmt->execute();
            $prop = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$prop) {
                throw new Exception("Accepted proposal not found.");
            }

            $stmt = $conn->prepare("SELECT 1 FROM projects WHERE proposal_id = ?");
            $stmt->bind_param("s", $proposal_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new Exception("A project has already been created for this proposal.");
            }
            $stmt->close();

            // The deposit invoice must be settled before work starts (proposals without one predate invoicing)
            $tag = '%[' . $prop['proposal_id'] . ']%';
            $stmt = $conn->prepare("SELECT id, status FROM payments WHERE client_id = ? AND type = 'DEPOSIT' AND description LIKE ? ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("ss", $prop['client_id'], $tag);
            $stmt->execute();
            $deposit = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($deposit && $deposit['status'] !== 'COMPLETED') {
                throw new Exception("The client has not paid the deposit yet.");
            }

            $project_id = generate_uuid();
            $start_sql = date('Y-m-d H:i:s', $start_ts);
            $end_sql = date('Y-m-d H:i:s', $end_ts);
            $title = $prop['title'];
            $budget = floatval($prop['amount']);
            $category = $prop['category'];
            $client_id = $prop['client_id'];
            $scope = $description !== '' ? $description : $prop['description'];

            $stmt = $conn->prepare("INSERT INTO projects (id, project_id, title, description, client_id, supervisor_id, status, budget, start_date, end_date, progress, category, proposal_id)
                                    VALUES (?, ?, ?, ?, ?, ?, 'PLANNING', ?, ?, ?, 0, ?, ?)");
            $stmt->bind_param("ssssssdssss", $project_id, $project_serial, $title, $scope, $client_id, $supervisor_id, $budget, $start_sql, $end_sql, $category, $proposal_id);
            $stmt->execute();
            $stmt->close();

            if ($deposit) {
                $stmt = $conn->prepare("UPDATE payments SET project_id = ? WHERE id = ?");
                $stmt->bind_param("ss", $project_id, $deposit['id']);
                $stmt->execute();
                $stmt->close();
            }

            // Standard delivery milestones spread across the project window
            $phases = [
                ['Design & architecture', 0.25, 'Wireframes, prototype flows and system design signed off.'],
                ['Core build', 0.55, 'Backend, database and main features working in staging.'],
                ['Integrations & testing', 0.80, 'Third-party integrations, QA and fixes.'],
                ['Launch & handover', 1.00, 'Deployment, training and project handover.'],
            ];
            $span = $end_ts - $start_ts;
            foreach ($phases as [$m_title, $at, $m_desc]) {
                $m_id = generate_uuid();
                $m_due = date('Y-m-d H:i:s', $start_ts + (int) round($span * $at));
                $stmt = $conn->prepare("INSERT INTO project_milestones (id, project_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $m_id, $project_id, $m_title, $m_desc, $m_due);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            send_notification($prop['client_user_id'], "Project kicked off", "Your project '$title' has been scheduled. Follow progress in your project workspace.", "CRM", "HIGH", "/client/projects.php?project_id=" . $project_id);
            log_activity("Initiated project from proposal", ["project_title" => $title]);
            $success_msg = "Project workspace created with 4 standard milestones.";
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Initialization failed: " . $e->getMessage();
        }
    }
}

// 2. Handle Team Member Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_team') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $project_id = sanitize($_POST['project_id'] ?? '');
    $staff_id = sanitize($_POST['staff_id'] ?? '');
    $role = sanitize($_POST['role'] ?? 'Developer');

    if (empty($project_id) || empty($staff_id) || empty($role)) {
        $errors[] = "Member role is required.";
    } else {
        try {
            $member_id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO project_team_members (id, project_id, staff_id, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)");
            $stmt->bind_param("ssss", $member_id, $project_id, $staff_id, $role);
            $stmt->execute();
            $stmt->close();

            log_activity("Assigned staff to project team", ["project_id" => $project_id, "staff_id" => $staff_id]);
            $success_msg = "Team member role assigned successfully!";
        } catch (Exception $e) {
            $errors[] = "Assignment failed: " . $e->getMessage();
        }
    }
}

// 3. Delivery tools: status/progress, milestones, client-visible updates, invoices
function recalc_progress($conn, $project_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(completed),0) AS done FROM project_milestones WHERE project_id = ?");
    $stmt->bind_param("s", $project_id);
    $stmt->execute();
    $m = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$m['total']) {
        return;
    }
    $progress = (int) round($m['done'] / $m['total'] * 100);
    $status = $progress >= 100 ? 'COMPLETED' : ($progress > 0 ? 'IN_PROGRESS' : 'PLANNING');
    $stmt = $conn->prepare("UPDATE projects SET progress = ?, status = ? WHERE id = ? AND status NOT IN ('ON_HOLD','CANCELLED')");
    $stmt->bind_param("iss", $progress, $status, $project_id);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_milestone', 'toggle_milestone', 'post_update', 'set_status', 'issue_invoice'], true)) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    $act = $_POST['action'];
    $pid = $_POST['project_id'] ?? '';

    try {
        $stmt = $conn->prepare("SELECT p.*, cl.user_id AS client_user_id FROM projects p JOIN clients cl ON cl.id = p.client_id WHERE p.id = ?");
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $proj = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$proj) {
            throw new Exception("Project not found.");
        }

        if ($act === 'add_milestone') {
            $m_title = trim($_POST['title'] ?? '');
            $m_due = strtotime($_POST['due_date'] ?? '');
            if ($m_title === '' || !$m_due) {
                throw new Exception("Milestone title and due date are required.");
            }
            $m_id = generate_uuid();
            $m_desc = trim($_POST['description'] ?? '');
            $due_sql = date('Y-m-d H:i:s', $m_due);
            $stmt = $conn->prepare("INSERT INTO project_milestones (id, project_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $m_id, $pid, $m_title, $m_desc, $due_sql);
            $stmt->execute();
            $stmt->close();
            recalc_progress($conn, $pid);
            $success_msg = "Milestone added.";
        } elseif ($act === 'toggle_milestone') {
            $m_id = $_POST['milestone_id'] ?? '';
            $stmt = $conn->prepare("UPDATE project_milestones SET completed = 1 - completed, completed_at = IF(completed = 1, NOW(), NULL) WHERE id = ? AND project_id = ?");
            $stmt->bind_param("ss", $m_id, $pid);
            $stmt->execute();
            $stmt->close();
            recalc_progress($conn, $pid);
            $success_msg = "Milestone updated; project progress recalculated.";
        } elseif ($act === 'post_update') {
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') {
                throw new Exception("Write an update message first.");
            }
            $u_id = generate_uuid();
            $author = $user['first_name'] . ' ' . $user['last_name'];
            $stmt = $conn->prepare("INSERT INTO project_updates (id, project_id, author, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $u_id, $pid, $author, $msg);
            $stmt->execute();
            $stmt->close();
            send_notification($proj['client_user_id'], "Project update", mb_substr($msg, 0, 200), "CRM", "MEDIUM", "/client/projects.php?project_id=" . $pid);
            $success_msg = "Update posted to the client.";
        } elseif ($act === 'set_status') {
            $new = $_POST['status'] ?? '';
            if (!in_array($new, ['PLANNING', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED'], true)) {
                throw new Exception("Invalid status.");
            }
            $stmt = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
            $stmt->bind_param("ss", $new, $pid);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Project status set to $new.";
        } elseif ($act === 'issue_invoice') {
            $type = $_POST['type'] ?? '';
            $amount = round(floatval($_POST['amount'] ?? 0), 2);
            $label = trim($_POST['label'] ?? '');
            if (!in_array($type, ['MILESTONE', 'FINAL'], true) || $amount <= 0 || $label === '') {
                throw new Exception("Choose an invoice type, a label and an amount above zero.");
            }
            $pay_id = generate_uuid();
            $trans = 'INV-' . strtoupper(substr(str_replace('-', '', $pay_id), 0, 10));
            $desc = $label . " - " . $proj['title'];
            $stmt = $conn->prepare("INSERT INTO payments (id, transaction_id, type, amount, method, status, client_id, project_id, description, due_date)
                                    VALUES (?, ?, ?, ?, 'PAYSTACK', 'PENDING', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY))");
            $stmt->bind_param("sssdsss", $pay_id, $trans, $type, $amount, $proj['client_id'], $pid, $desc);
            $stmt->execute();
            $stmt->close();
            send_notification($proj['client_user_id'], "New invoice", "An invoice of " . format_currency($amount) . " was issued: $desc", "FINANCE", "HIGH", "/client/billing.php");
            $success_msg = "Invoice $trans issued to the client.";
        }
        log_activity("Project delivery action: $act", ["project_id" => $pid]);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Fetch staff dropdown
$staff_members = [];
try {
    $res = $conn->query("SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users_staff u ON s.user_id = u.id WHERE s.status = 'ACTIVE'");
    while ($row = $res->fetch_assoc()) {
        $staff_members[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch approved proposals
$approved_proposals = [];
try {
    $res = $conn->query("SELECT p.*, cl.company_name,
                            (SELECT pay.status FROM payments pay WHERE pay.client_id = p.client_id AND pay.type = 'DEPOSIT'
                               AND pay.description LIKE CONCAT('%[', p.proposal_id, ']%') ORDER BY pay.created_at DESC LIMIT 1) AS deposit_status
                     FROM proposals p JOIN clients cl ON p.client_id = cl.id
                     WHERE p.status = 'APPROVED' AND NOT EXISTS (SELECT 1 FROM projects pr WHERE pr.proposal_id = p.id)
                     ORDER BY p.updated_at DESC");
    while ($row = $res->fetch_assoc()) {
        $approved_proposals[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch active projects lists
$projects = [];
try {
    $res = $conn->query("SELECT p.*, cl.company_name, u.first_name as supervisor_first, u.last_name as supervisor_last 
                         FROM projects p 
                         JOIN clients cl ON p.client_id = cl.id 
                         LEFT JOIN staff s ON p.supervisor_id = s.id 
                         LEFT JOIN users_staff u ON s.user_id = u.id 
                         ORDER BY p.created_at DESC");
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
} catch (Exception $e) {
    // Ignore
}

// Active project configuration select
$active_proj_id = sanitize($_GET['project_id'] ?? '');
$active_proj = null;
$team = [];

if (!empty($active_proj_id)) {
    foreach ($projects as $pr) {
        if ($pr['id'] === $active_proj_id) {
            $active_proj = $pr;
            break;
        }
    }

    $ms_list = [];
    $upd_list = [];
    $inv_list = [];
    if ($active_proj) {
        $stmt = $conn->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date ASC");
        $stmt->bind_param("s", $active_proj_id);
        $stmt->execute();
        $ms_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $stmt = $conn->prepare("SELECT * FROM project_updates WHERE project_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("s", $active_proj_id);
        $stmt->execute();
        $upd_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $stmt = $conn->prepare("SELECT * FROM payments WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $active_proj_id);
        $stmt->execute();
        $inv_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if ($active_proj) {
        try {
            $stmt = $conn->prepare("SELECT ptm.*, u.first_name, u.last_name, s.department 
                                    FROM project_team_members ptm 
                                    JOIN staff s ON ptm.staff_id = s.id 
                                    JOIN users_staff u ON s.user_id = u.id 
                                    WHERE ptm.project_id = ?");
            $stmt->bind_param("s", $active_proj_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $team[] = $row;
            }
            $stmt->close();
        } catch (Exception $e) {
            // Ignore
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Alerts -->
<?php if (!empty($errors)): ?>
    <div
        class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600  rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?>
            <p><?php echo $err; ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div
        class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600  rounded-xl text-sm font-medium">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace: Proposal list & Project initiator -->
    <div class="space-y-6">

        <!-- Approved proposals awaiting kickoff -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-150  pb-3">
                <i data-lucide="inbox" class="w-4 h-4 text-emerald-500"></i>
                <span>Approved Contracts Kickoff Queue</span>
            </h4>

            <?php if (empty($approved_proposals)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No approved contracts awaiting execution.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($approved_proposals as $ap): ?>
                        <div
                            class="p-3.5 bg-slate-50  border border-slate-200/50  rounded-xl space-y-3">
                            <div>
                                <h5 class="text-xs font-bold text-slate-900 ">
                                    <?php echo sanitize($ap['title']); ?></h5>
                                <div class="flex justify-between items-center text-[9px] text-slate-400 mt-1">
                                    <span>Client: <?php echo sanitize($ap['company_name']); ?></span>
                                    <span>Budget: <?php echo format_currency($ap['amount']); ?></span>
                                </div>
                                <?php
                                $dep = $ap['deposit_status'];
                                $dep_ok = ($dep === null || $dep === 'COMPLETED');
                                ?>
                                <p class="text-[10px] mt-1.5 font-semibold <?php echo $dep_ok ? 'text-emerald-600' : 'text-amber-600'; ?>">
                                    <?php echo $dep === null ? 'No deposit invoice on file' : ($dep === 'COMPLETED' ? 'Deposit paid - ready to kick off' : 'Waiting for client deposit'); ?>
                                </p>
                            </div>

                            <!-- Trigger form toggle -->
                            <button <?php echo $dep_ok ? '' : 'disabled'; ?>
                                class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold rounded-lg text-[10px] transition-all flex items-center justify-center space-x-1"
                                onclick="toggleForm('kickoff-form-<?php echo $ap['id']; ?>')">
                                <i data-lucide="play" class="w-3 h-3"></i>
                                <span>Kicked Off Project</span>
                            </button>

                            <!-- Project Initiation Form -->
                            <form id="kickoff-form-<?php echo $ap['id']; ?>" action="project_control.php" method="POST"
                                class="hidden mt-3 pt-3 border-t border-slate-150  space-y-3">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="initiate_project">
                                <input type="hidden" name="proposal_id" value="<?php echo $ap['id']; ?>">
                                <input type="hidden" name="title" value="<?php echo sanitize($ap['title']); ?>">
                                <input type="hidden" name="budget" value="<?php echo $ap['amount']; ?>">
                                <input type="hidden" name="category" value="<?php echo sanitize($ap['category']); ?>">

                                <div>
                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Custom Project
                                        Serial ID</label>
                                    <input type="text" name="project_id" required placeholder="PRJ-8821"
                                        value="PRJ-<?php echo mt_rand(1000, 9999); ?>"
                                        class="w-full px-3 py-1.5 bg-white  border border-slate-250  rounded-lg text-xs outline-none">
                                </div>

                                <div>
                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Assigned Supervisor
                                        Host</label>
                                    <select name="supervisor_id" required
                                        class="w-full px-3 py-1.5 bg-white  border border-slate-250  rounded-lg text-xs outline-none">
                                        <option value="">Choose Supervisor...</option>
                                        <?php foreach ($staff_members as $sm): ?>
                                            <option value="<?php echo $sm['id']; ?>">
                                                <?php echo sanitize($sm['first_name'] . ' ' . $sm['last_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Start
                                            Date</label>
                                        <input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>"
                                            class="w-full px-3 py-1.5 bg-white  border border-slate-250  rounded-lg text-xs outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                                        <input type="date" name="end_date" required
                                            value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>"
                                            class="w-full px-3 py-1.5 bg-white  border border-slate-250  rounded-lg text-xs outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Scope
                                        details</label>
                                    <textarea name="description" placeholder="Write final engineering task scopes..." rows="2"
                                        class="w-full px-3 py-2 bg-white  border border-slate-250  rounded-lg text-xs outline-none"><?php echo sanitize($ap['description']); ?></textarea>
                                </div>

                                <div class="flex justify-end space-x-2 pt-1">
                                    <button type="button" class="px-3 py-1 bg-slate-105 rounded text-[9px] font-bold"
                                        onclick="toggleForm('kickoff-form-<?php echo $ap['id']; ?>')">Cancel</button>
                                    <button type="submit"
                                        class="px-3 py-1 bg-emerald-600 text-white rounded text-[9px] font-bold shadow-sm">Save
                                        Kickoff</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Panel: Active projects roster list & Allocator details (Col span 2) -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Active Projects List -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="briefcase" class="w-4 h-4 text-blue-500"></i>
                <span>Active CRM Project Workspaces</span>
            </h4>

            <?php if (empty($projects)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No projects registered.</p>
            <?php else: ?>
                <div class="space-y-3.5">
                    <?php foreach ($projects as $pr): ?>
                        <div
                            class="p-4 bg-slate-50  border border-slate-150  rounded-xl flex items-center justify-between <?php echo $pr['id'] === $active_proj_id ? 'ring-2 ring-indigo-500' : ''; ?>">
                            <div>
                                <span
                                    class="text-[9px] bg-slate-100  px-2 py-0.5 rounded font-mono text-slate-450 uppercase font-semibold"><?php echo sanitize($pr['project_id']); ?></span>
                                <h5 class="text-xs font-bold text-slate-905  mt-1.5">
                                    <?php echo sanitize($pr['title']); ?></h5>
                                <p class="text-[9px] text-slate-400 mt-1">Client: <?php echo sanitize($pr['company_name']); ?>
                                    &bull; Supervisor:
                                    <?php echo sanitize($pr['supervisor_first'] . ' ' . $pr['supervisor_last']); ?> &bull;
                                    Status: <span class="uppercase"><?php echo sanitize($pr['status']); ?></span></p>
                            </div>

                            <a href="project_control.php?project_id=<?php echo $pr['id']; ?>"
                                class="px-3.5 py-1.5 bg-indigo-650 hover:bg-indigo-555 text-white rounded font-bold text-[10px] transition-all">
                                Manage
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Team allocator workflow panel -->
        <?php if ($active_proj): ?>
            <div
                class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-6">

                <div class="flex justify-between items-center border-b border-slate-100  pb-3">
                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">Roster
                            Allocator</span>
                        <h4 class="text-sm font-bold text-slate-950  mt-1">
                            <?php echo sanitize($active_proj['title']); ?> Team</h4>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Team members list -->
                    <div class="space-y-3">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Assigned Staff
                            members</span>

                        <?php if (empty($team)): ?>
                            <p
                                class="text-xs text-slate-400 text-center py-4 border border-dashed border-slate-200  rounded-xl">
                                No engineering team allocated yet.</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($team as $tm): ?>
                                    <div class="p-3 bg-slate-50  rounded-xl flex items-center justify-between">
                                        <div>
                                            <h5 class="text-xs font-bold text-slate-900 ">
                                                <?php echo sanitize($tm['first_name'] . ' ' . $tm['last_name']); ?></h5>
                                            <p class="text-[9px] text-slate-400 mt-0.5"><?php echo sanitize($tm['role']); ?> &bull;
                                                <?php echo sanitize($tm['department']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Member allocation form -->
                    <div
                        class="bg-slate-50  border border-slate-150  p-4 rounded-xl space-y-4">
                        <span
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block border-b border-slate-100  pb-2">Allocate
                            Team Member</span>

                        <form action="project_control.php?project_id=<?php echo $active_proj_id; ?>" method="POST"
                            class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="assign_team">
                            <input type="hidden" name="project_id" value="<?php echo $active_proj_id; ?>">

                            <div>
                                <label for="team-staff"
                                    class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Choose Engineer</label>
                                <select name="staff_id" id="team-staff" required
                                    class="w-full px-3 py-1.5 bg-white  border border-slate-250  rounded-lg text-xs outline-none">
                                    <option value="">Choose Engineer...</option>
                                    <?php foreach ($staff_members as $sm): ?>
                                        <option value="<?php echo $sm['id']; ?>">
                                            <?php echo sanitize($sm['first_name'] . ' ' . $sm['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="team-role"
                                    class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Project Role
                                    Title</label>
                                <input type="text" name="role" id="team-role" required
                                    placeholder="Lead Architect, Developer, Quality check"
                                    class="w-full px-3 py-1.5 bg-white  border border-slate-250  rounded-lg text-xs outline-none">
                            </div>

                            <button type="submit"
                                class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg text-xs transition-all shadow-sm">
                                Allocate Member
                            </button>
                        </form>
                    </div>

                </div>

            </div>
        <?php endif; ?>

        <?php if ($active_proj): ?>
            <!-- Delivery panel: what the client sees on their project page -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
                <div class="flex flex-wrap justify-between items-center gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">Delivery &amp; client updates</span>
                        <h4 class="text-sm font-bold text-slate-900 mt-1"><?php echo sanitize($active_proj['title']); ?> &bull; <?php echo intval($active_proj['progress']); ?>% complete</h4>
                    </div>
                    <form method="POST" action="project_control.php?project_id=<?php echo $active_proj_id; ?>" class="flex items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="project_id" value="<?php echo $active_proj_id; ?>">
                        <select name="status" class="px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                            <?php foreach (['PLANNING', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $active_proj['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-[10px] font-bold">Set status</button>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Milestones (tick to complete)</span>
                        <?php foreach ($ms_list as $m): ?>
                            <form method="POST" action="project_control.php?project_id=<?php echo $active_proj_id; ?>" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="toggle_milestone">
                                <input type="hidden" name="project_id" value="<?php echo $active_proj_id; ?>">
                                <input type="hidden" name="milestone_id" value="<?php echo $m['id']; ?>">
                                <div>
                                    <h5 class="text-xs font-bold <?php echo $m['completed'] ? 'text-emerald-600 line-through' : 'text-slate-900'; ?>"><?php echo sanitize($m['title']); ?></h5>
                                    <p class="text-[9px] text-slate-400">Due <?php echo date('M d, Y', strtotime($m['due_date'])); ?></p>
                                </div>
                                <button class="px-2.5 py-1 rounded text-[10px] font-bold <?php echo $m['completed'] ? 'bg-slate-200 text-slate-600' : 'bg-emerald-600 text-white'; ?>"><?php echo $m['completed'] ? 'Reopen' : 'Complete'; ?></button>
                            </form>
                        <?php endforeach; ?>
                        <form method="POST" action="project_control.php?project_id=<?php echo $active_proj_id; ?>" class="grid grid-cols-2 gap-2 pt-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="add_milestone">
                            <input type="hidden" name="project_id" value="<?php echo $active_proj_id; ?>">
                            <input type="text" name="title" required placeholder="New milestone" class="px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs">
                            <input type="date" name="due_date" required class="px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs">
                            <input type="text" name="description" placeholder="Description (optional)" class="col-span-2 px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs">
                            <button class="col-span-2 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold">Add milestone</button>
                        </form>
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Post an update to the client</span>
                            <form method="POST" action="project_control.php?project_id=<?php echo $active_proj_id; ?>" class="space-y-2">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="post_update">
                                <input type="hidden" name="project_id" value="<?php echo $active_proj_id; ?>">
                                <textarea name="message" rows="3" required placeholder="What was done, what's next..." class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs"></textarea>
                                <button class="w-full py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold">Post update</button>
                            </form>
                            <?php foreach ($upd_list as $u): ?>
                                <p class="text-[10px] text-slate-500 border-l-2 border-slate-200 pl-2"><b><?php echo sanitize($u['author']); ?></b> &bull; <?php echo date('M d', strtotime($u['created_at'])); ?>: <?php echo sanitize(mb_substr($u['message'], 0, 120)); ?></p>
                            <?php endforeach; ?>
                        </div>

                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Invoices for this project</span>
                            <?php foreach ($inv_list as $iv): ?>
                                <p class="text-[10px] text-slate-600 flex justify-between"><span><?php echo sanitize($iv['type']); ?> &bull; <?php echo sanitize($iv['transaction_id']); ?></span><span class="font-bold"><?php echo format_currency($iv['amount']); ?> &bull; <?php echo sanitize($iv['status']); ?></span></p>
                            <?php endforeach; ?>
                            <form method="POST" action="project_control.php?project_id=<?php echo $active_proj_id; ?>" class="grid grid-cols-2 gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="issue_invoice">
                                <input type="hidden" name="project_id" value="<?php echo $active_proj_id; ?>">
                                <select name="type" class="px-2 py-1.5 bg-white border border-slate-250 rounded-lg text-xs"><option value="MILESTONE">Milestone</option><option value="FINAL">Final</option></select>
                                <input type="number" name="amount" step="0.01" min="1" required placeholder="Amount (₦)" class="px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs">
                                <input type="text" name="label" required placeholder="e.g. Milestone 2 - Core build" class="col-span-2 px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs">
                                <button class="col-span-2 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold">Issue invoice</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
    function toggleForm(formId) {
        const form = document.getElementById(formId);
        form.classList.toggle('hidden');
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>