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

            // Standard delivery milestones for this kind of service, spread across the project window
            $phases = (service_by_label($category) ?? SERVICE_CATALOG['other'])['milestones'];
            $span = $end_ts - $start_ts;
            foreach ($phases as [$m_title, $at, $m_desc]) {
                $m_id = generate_uuid();
                $m_due = date('Y-m-d H:i:s', $start_ts + (int) round($span * $at));
                $stmt = $conn->prepare("INSERT INTO project_milestones (id, project_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $m_id, $project_id, $m_title, $m_desc, $m_due);
                $stmt->execute();
                $stmt->close();
            }

            // A client brought in by a marketer earns them a commission on every project that kicks off
            $commission = record_project_commission($conn, $project_id, $client_id, $budget, $title);

            $conn->commit();
            if ($commission) {
                send_notification($commission['marketer_id'], "Commission earned", "A client you referred kicked off '$title'. " . format_currency($commission['amount']) . " has been added to your earnings.", "FINANCE", "HIGH", "/marketer/commissions.php");
            }
            send_notification($prop['client_user_id'], "Project kicked off", "Your project '$title' has been scheduled. Follow progress in your project workspace.", "CRM", "HIGH", "/client/projects.php?project_id=" . $project_id);
            log_activity("Initiated project from proposal", ["project_title" => $title]);
            $success_msg = "Project workspace created with " . count($phases) . " standard milestones." . ($commission ? " Marketer commission of " . format_currency($commission['amount']) . " recorded." : "");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_milestone', 'toggle_milestone', 'post_update', 'set_status', 'issue_invoice', 'remove_team_member', 'delete_milestone', 'edit_project', 'delete_project'], true)) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    $act = $_POST['action'];
    $pid = $_POST['project_id'] ?? '';

    try {
        // delete_project does not need to fetch the project first in the same way
        if ($act !== 'delete_project') {
            $stmt = $conn->prepare("SELECT p.*, cl.user_id AS client_user_id FROM projects p JOIN clients cl ON cl.id = p.client_id WHERE p.id = ?");
            $stmt->bind_param("s", $pid);
            $stmt->execute();
            $proj = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$proj) {
                throw new Exception("Project not found.");
            }
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
        } elseif ($act === 'remove_team_member') {
            $member_id = $_POST['member_id'] ?? '';
            if (empty($member_id)) {
                throw new Exception("Member ID is required.");
            }
            $stmt = $conn->prepare("DELETE FROM project_team_members WHERE id = ? AND project_id = ?");
            $stmt->bind_param("ss", $member_id, $pid);
            $stmt->execute();
            $stmt->close();
            log_activity("Removed team member from project", ["project_id" => $pid, "member_id" => $member_id]);
            $success_msg = "Team member removed from project.";
        } elseif ($act === 'delete_milestone') {
            $milestone_id = $_POST['milestone_id'] ?? '';
            if (empty($milestone_id)) {
                throw new Exception("Milestone ID is required.");
            }
            $stmt = $conn->prepare("DELETE FROM project_milestones WHERE id = ? AND project_id = ?");
            $stmt->bind_param("ss", $milestone_id, $pid);
            $stmt->execute();
            $stmt->close();
            recalc_progress($conn, $pid);
            log_activity("Deleted project milestone", ["project_id" => $pid, "milestone_id" => $milestone_id]);
            $success_msg = "Milestone deleted and progress recalculated.";
        } elseif ($act === 'edit_project') {
            $title_ep = trim($_POST['title'] ?? '');
            $description_ep = trim($_POST['description'] ?? '');
            $budget_ep = round(floatval($_POST['budget'] ?? 0), 2);
            $start_date_ep = $_POST['start_date'] ?? '';
            $end_date_ep = $_POST['end_date'] ?? '';
            $supervisor_id_ep = $_POST['supervisor_id'] ?? '';
            $category_ep = trim($_POST['category'] ?? '');

            if ($title_ep === '') {
                throw new Exception("Project title is required.");
            }
            if ($budget_ep <= 0) {
                throw new Exception("Budget must be greater than zero.");
            }
            $start_ts_ep = strtotime($start_date_ep);
            $end_ts_ep = strtotime($end_date_ep);
            if (!$start_ts_ep || !$end_ts_ep) {
                throw new Exception("Valid start and end dates are required.");
            }
            if ($end_ts_ep <= $start_ts_ep) {
                throw new Exception("End date must be after the start date.");
            }

            $start_sql_ep = date('Y-m-d H:i:s', $start_ts_ep);
            $end_sql_ep = date('Y-m-d H:i:s', $end_ts_ep);

            $stmt = $conn->prepare("UPDATE projects SET title = ?, description = ?, budget = ?, start_date = ?, end_date = ?, supervisor_id = ?, category = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssdsssss", $title_ep, $description_ep, $budget_ep, $start_sql_ep, $end_sql_ep, $supervisor_id_ep, $category_ep, $pid);
            $stmt->execute();
            $stmt->close();
            log_activity("Admin edited project", ["project_id" => $pid]);
            $success_msg = "Project updated successfully.";
        } elseif ($act === 'delete_project') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM project_team_members WHERE project_id = ?");
                $stmt->bind_param("s", $pid);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM project_milestones WHERE project_id = ?");
                $stmt->bind_param("s", $pid);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM project_updates WHERE project_id = ?");
                $stmt->bind_param("s", $pid);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->bind_param("s", $pid);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                log_activity("Admin deleted project", ["project_id" => $pid]);
                header('Location: project_control.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        }

        if ($act !== 'delete_project') {
            log_activity("Project delivery action: $act", ["project_id" => $pid]);
        }
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
        $stmt = $conn->prepare("SELECT * FROM project_updates WHERE project_id = ? ORDER BY created_at DESC LIMIT 20");
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

// Status badge helper
function status_badge(string $status): string
{
    $map = [
        'PLANNING'    => 'bg-slate-100 text-slate-600',
        'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
        'COMPLETED'   => 'bg-emerald-100 text-emerald-700',
        'ON_HOLD'     => 'bg-amber-100 text-amber-700',
        'CANCELLED'   => 'bg-red-100 text-red-600',
    ];
    $cls = $map[$status] ?? 'bg-slate-100 text-slate-500';
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Edit Project Modal -->
<div id="edit-project-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="pencil" class="w-4 h-4 text-indigo-500"></i>
                Edit Project
            </h3>
            <button onclick="closeEditProjectModal()" class="text-slate-400 hover:text-slate-700 transition-colors text-lg leading-none">&times;</button>
        </div>
        <form method="POST" action="project_control.php" class="space-y-3.5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="edit_project">
            <input type="hidden" name="project_id" id="ep_project_id">

            <div>
                <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Project Title</label>
                <input type="text" name="title" id="ep_title" required
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Category</label>
                <input type="text" name="category" id="ep_category" list="service-categories"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300">
                <datalist id="service-categories">
                    <?php foreach (SERVICE_CATALOG as $svc_opt): ?>
                        <option value="<?= $svc_opt['label'] ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Budget (₦)</label>
                <input type="number" name="budget" id="ep_budget" step="0.01" min="1" required
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" id="ep_start_date" required
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                    <input type="date" name="end_date" id="ep_end_date" required
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>
            <div>
                <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Supervisor</label>
                <select name="supervisor_id" id="ep_supervisor_id"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300">
                    <option value="">— No Supervisor —</option>
                    <?php foreach ($staff_members as $sm): ?>
                        <option value="<?= $sm['id'] ?>"><?= sanitize($sm['first_name'] . ' ' . $sm['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Description / Scope</label>
                <textarea name="description" id="ep_description" rows="3"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-300"
                    placeholder="Project scope and delivery details..."></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="closeEditProjectModal()"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-all">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow-sm transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Project Confirmation Modal -->
<div id="delete-project-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-red-700 flex items-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
                Delete Project
            </h3>
            <button onclick="closeDeleteProjectModal()" class="text-slate-400 hover:text-slate-700 transition-colors text-lg leading-none">&times;</button>
        </div>
        <p class="text-xs text-slate-600 mb-1">You are about to permanently delete:</p>
        <p class="text-xs font-bold text-slate-900 mb-4 bg-red-50 border border-red-100 rounded-lg px-3 py-2" id="dp_project_title"></p>
        <p class="text-xs text-slate-500 mb-5">This will also delete all milestones, team assignments and updates for this project. <span class="font-bold text-red-600">This cannot be undone.</span></p>
        <form method="POST" action="project_control.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete_project">
            <input type="hidden" name="project_id" id="dp_project_id">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDeleteProjectModal()"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-all">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                    Delete Project
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?>
            <p><?= $err ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-sm font-medium">
        <?= $success_msg ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace: Proposal list & Project initiator -->
    <div class="space-y-6">

        <!-- Approved proposals awaiting kickoff -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center space-x-2 border-b border-slate-150 pb-3">
                <i data-lucide="inbox" class="w-4 h-4 text-emerald-500"></i>
                <span>Approved Contracts Kickoff Queue</span>
            </h4>

            <?php if (empty($approved_proposals)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No approved contracts awaiting execution.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($approved_proposals as $ap): ?>
                        <div class="p-3.5 bg-slate-50 border border-slate-200/50 rounded-xl space-y-3">
                            <div>
                                <h5 class="text-xs font-bold text-slate-900">
                                    <?= sanitize($ap['title']) ?></h5>
                                <div class="flex justify-between items-center text-[9px] text-slate-400 mt-1">
                                    <span>Client: <?= sanitize($ap['company_name']) ?></span>
                                    <span>Budget: <?= format_currency($ap['amount']) ?></span>
                                </div>
                                <?php
                                $dep = $ap['deposit_status'];
                                $dep_ok = ($dep === null || $dep === 'COMPLETED');
                                ?>
                                <p class="text-[10px] mt-1.5 font-semibold <?= $dep_ok ? 'text-emerald-600' : 'text-amber-600' ?>">
                                    <?= $dep === null ? 'No deposit invoice on file' : ($dep === 'COMPLETED' ? 'Deposit paid - ready to kick off' : 'Waiting for client deposit') ?>
                                </p>
                            </div>

                            <!-- Trigger form toggle -->
                            <button <?= $dep_ok ? '' : 'disabled' ?>
                                class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold rounded-lg text-[10px] transition-all flex items-center justify-center space-x-1"
                                onclick="toggleForm('kickoff-form-<?= $ap['id'] ?>')">
                                <i data-lucide="play" class="w-3 h-3"></i>
                                <span>Kicked Off Project</span>
                            </button>

                            <!-- Project Initiation Form -->
                            <form id="kickoff-form-<?= $ap['id'] ?>" action="project_control.php" method="POST"
                                class="hidden mt-3 pt-3 border-t border-slate-150 space-y-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="initiate_project">
                                <input type="hidden" name="proposal_id" value="<?= $ap['id'] ?>">
                                <input type="hidden" name="title" value="<?= sanitize($ap['title']) ?>">
                                <input type="hidden" name="budget" value="<?= $ap['amount'] ?>">
                                <input type="hidden" name="category" value="<?= sanitize($ap['category']) ?>">

                                <div>
                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Custom Project
                                        Serial ID</label>
                                    <input type="text" name="project_id" required placeholder="PRJ-8821"
                                        value="PRJ-<?= mt_rand(1000, 9999) ?>"
                                        class="w-full px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                </div>

                                <div>
                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Assigned Supervisor
                                        Host</label>
                                    <select name="supervisor_id" required
                                        class="w-full px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                        <option value="">Choose Supervisor...</option>
                                        <?php foreach ($staff_members as $sm): ?>
                                            <option value="<?= $sm['id'] ?>">
                                                <?= sanitize($sm['first_name'] . ' ' . $sm['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Start
                                            Date</label>
                                        <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"
                                            class="w-full px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                                        <input type="date" name="end_date" required
                                            value="<?= date('Y-m-d', strtotime('+3 months')) ?>"
                                            class="w-full px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Scope
                                        details</label>
                                    <textarea name="description" placeholder="Write final engineering task scopes..." rows="2"
                                        class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs outline-none"><?= sanitize($ap['description']) ?></textarea>
                                </div>

                                <div class="flex justify-end space-x-2 pt-1">
                                    <button type="button" class="px-3 py-1 bg-slate-105 rounded text-[9px] font-bold"
                                        onclick="toggleForm('kickoff-form-<?= $ap['id'] ?>')">Cancel</button>
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
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-5">
                <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <i data-lucide="briefcase" class="w-4 h-4 text-blue-500"></i>
                    <span>Active CRM Project Workspaces</span>
                </h4>
                <?php if (!empty($projects)): ?>
                    <span class="text-[10px] text-slate-400 font-semibold"><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <!-- Search -->
            <?php if (!empty($projects)): ?>
                <div class="mb-4">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input type="text" id="project-search" placeholder="Search projects by name, client or status..."
                            class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300 transition-all">
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($projects)): ?>
                <p class="text-xs text-slate-400 text-center py-8">No projects registered.</p>
            <?php else: ?>
                <div class="space-y-3.5" id="projects-list">
                    <?php foreach ($projects as $pr):
                        $days_left = ceil((strtotime($pr['end_date']) - time()) / 86400);
                        $days_cls = $days_left < 0 ? 'text-red-600 font-bold' : ($days_left < 7 ? 'text-amber-600 font-semibold' : 'text-slate-400');
                        $days_label = $days_left < 0 ? abs($days_left) . 'd overdue' : $days_left . ' days left';
                        $prog = intval($pr['progress']);

                        $js_proj = json_encode([
                            'id'            => $pr['id'],
                            'title'         => $pr['title'],
                            'category'      => $pr['category'] ?? '',
                            'budget'        => $pr['budget'],
                            'start_date'    => date('Y-m-d', strtotime($pr['start_date'])),
                            'end_date'      => date('Y-m-d', strtotime($pr['end_date'])),
                            'supervisor_id' => $pr['supervisor_id'] ?? '',
                            'description'   => $pr['description'] ?? '',
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    ?>
                        <div data-project-card
                            class="p-4 bg-slate-50 border border-slate-150 rounded-xl space-y-2.5 <?= $pr['id'] === $active_proj_id ? 'ring-2 ring-indigo-400 bg-indigo-50/30' : '' ?>">
                            <!-- Row 1: serial + badges + actions -->
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] bg-slate-200 px-2 py-0.5 rounded font-mono text-slate-500 uppercase font-semibold"><?= sanitize($pr['project_id']) ?></span>
                                    <?= status_badge($pr['status']) ?>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="openEditProjectModal(<?= htmlspecialchars($js_proj, ENT_QUOTES) ?>)"
                                        title="Edit project"
                                        class="p-1.5 rounded-lg bg-white border border-slate-200 hover:bg-indigo-50 hover:border-indigo-200 text-slate-400 hover:text-indigo-600 transition-all">
                                        <i data-lucide="pencil" class="w-3 h-3"></i>
                                    </button>
                                    <button onclick="openDeleteProjectModal('<?= addslashes($pr['id']) ?>', '<?= addslashes($pr['title']) ?>')"
                                        title="Delete project"
                                        class="p-1.5 rounded-lg bg-white border border-slate-200 hover:bg-red-50 hover:border-red-200 text-slate-400 hover:text-red-600 transition-all">
                                        <i data-lucide="trash-2" class="w-3 h-3"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Row 2: title -->
                            <h5 class="text-xs font-bold text-slate-900 leading-snug"><?= sanitize($pr['title']) ?></h5>

                            <!-- Row 3: meta -->
                            <p class="text-[9px] text-slate-400">
                                <span class="font-medium text-slate-600"><?= sanitize($pr['company_name']) ?></span>
                                <?php if (!empty($pr['supervisor_first'])): ?>
                                    &bull; <span>Supervisor: <?= sanitize($pr['supervisor_first'] . ' ' . $pr['supervisor_last']) ?></span>
                                <?php endif; ?>
                            </p>

                            <!-- Row 4: progress bar -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[9px] text-slate-400 font-medium">Progress</span>
                                    <span class="text-[9px] font-bold text-slate-700"><?= $prog ?>%</span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5">
                                    <div class="<?= $prog >= 100 ? 'bg-emerald-500' : 'bg-blue-500' ?> h-1.5 rounded-full transition-all" style="width: <?= $prog ?>%"></div>
                                </div>
                            </div>

                            <!-- Row 5: dates + days left + manage -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-[9px] text-slate-400">
                                    <i data-lucide="calendar" class="w-3 h-3"></i>
                                    <span><?= date('M j', strtotime($pr['start_date'])) ?> &rarr; <?= date('M j, Y', strtotime($pr['end_date'])) ?></span>
                                    <span class="<?= $days_cls ?>">&bull; <?= $days_label ?></span>
                                </div>
                                <a href="project_control.php?project_id=<?= $pr['id'] ?>"
                                    class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold text-[10px] transition-all flex items-center gap-1">
                                    Manage <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Team allocator workflow panel -->
        <?php if ($active_proj): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">

                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">Roster
                            Allocator</span>
                        <h4 class="text-sm font-bold text-slate-950 mt-1">
                            <?= sanitize($active_proj['title']) ?> Team</h4>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Team members list -->
                    <div class="space-y-3">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Assigned Staff
                            members</span>

                        <?php if (empty($team)): ?>
                            <p class="text-xs text-slate-400 text-center py-4 border border-dashed border-slate-200 rounded-xl">
                                No engineering team allocated yet.</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($team as $tm): ?>
                                    <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between gap-2">
                                        <div>
                                            <h5 class="text-xs font-bold text-slate-900">
                                                <?= sanitize($tm['first_name'] . ' ' . $tm['last_name']) ?></h5>
                                            <p class="text-[9px] text-slate-400 mt-0.5"><?= sanitize($tm['role']) ?> &bull;
                                                <?= sanitize($tm['department']) ?>
                                                <?php if (!empty($tm['join_date'])): ?>
                                                    &bull; Joined <?= date('M j, Y', strtotime($tm['join_date'])) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>"
                                            onsubmit="return confirm('Remove this team member?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="remove_team_member">
                                            <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                                            <input type="hidden" name="member_id" value="<?= $tm['id'] ?>">
                                            <button type="submit" title="Remove member"
                                                class="p-1.5 rounded-lg bg-white border border-red-100 hover:bg-red-50 text-red-400 hover:text-red-600 transition-all flex-shrink-0">
                                                <i data-lucide="user-minus" class="w-3 h-3"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Member allocation form -->
                    <div class="bg-slate-50 border border-slate-150 p-4 rounded-xl space-y-4">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block border-b border-slate-100 pb-2">Allocate
                            Team Member</span>

                        <form action="project_control.php?project_id=<?= $active_proj_id ?>" method="POST"
                            class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="assign_team">
                            <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">

                            <div>
                                <label for="team-staff"
                                    class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Choose Engineer</label>
                                <select name="staff_id" id="team-staff" required
                                    class="w-full px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                    <option value="">Choose Engineer...</option>
                                    <?php foreach ($staff_members as $sm): ?>
                                        <option value="<?= $sm['id'] ?>">
                                            <?= sanitize($sm['first_name'] . ' ' . $sm['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="team-role"
                                    class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Project Role
                                    Title</label>
                                <input type="text" name="role" id="team-role" required
                                    placeholder="Lead Architect, Developer, Quality check"
                                    class="w-full px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
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
                <div class="flex flex-wrap justify-between items-start gap-3 border-b border-slate-100 pb-4">
                    <div class="flex-1 min-w-0">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block">Delivery &amp; client updates</span>
                        <h4 class="text-sm font-bold text-slate-900 mt-1 flex items-center gap-2 flex-wrap">
                            <?= sanitize($active_proj['title']) ?>
                            <?= status_badge($active_proj['status']) ?>
                        </h4>
                        <!-- Overall progress bar -->
                        <div class="mt-3 max-w-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] text-slate-400 font-medium">Overall completion</span>
                                <span class="text-[9px] font-bold text-slate-700"><?= intval($active_proj['progress']) ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2">
                                <div class="<?= intval($active_proj['progress']) >= 100 ? 'bg-emerald-500' : 'bg-blue-500' ?> h-2 rounded-full transition-all"
                                    style="width: <?= intval($active_proj['progress']) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>" class="flex items-center gap-2 flex-shrink-0">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                        <select name="status" class="px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-200">
                            <?php foreach (['PLANNING', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED'] as $st): ?>
                                <option value="<?= $st ?>" <?= $active_proj['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-[10px] font-bold transition-all">Set status</button>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Milestones -->
                    <div class="space-y-3">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Milestones</span>
                        <?php foreach ($ms_list as $m): ?>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl gap-2">
                                <div class="flex-1 min-w-0">
                                    <h5 class="text-xs font-bold <?= $m['completed'] ? 'text-emerald-600 line-through' : 'text-slate-900' ?>">
                                        <?= sanitize($m['title']) ?></h5>
                                    <p class="text-[9px] text-slate-400">
                                        Due <?= date('M d, Y', strtotime($m['due_date'])) ?>
                                        <?php if ($m['completed'] && !empty($m['completed_at'])): ?>
                                            &bull; <span class="text-emerald-600">Done <?= date('M d, Y', strtotime($m['completed_at'])) ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <!-- Toggle complete -->
                                    <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="toggle_milestone">
                                        <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                                        <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">
                                        <button class="px-2.5 py-1 rounded-lg text-[10px] font-bold <?= $m['completed'] ? 'bg-slate-200 text-slate-600 hover:bg-slate-300' : 'bg-emerald-600 text-white hover:bg-emerald-500' ?> transition-all">
                                            <?= $m['completed'] ? 'Reopen' : 'Complete' ?>
                                        </button>
                                    </form>
                                    <!-- Delete milestone -->
                                    <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>"
                                        onsubmit="return confirm('Delete this milestone?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="delete_milestone">
                                        <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                                        <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">
                                        <button type="submit" title="Delete milestone"
                                            class="p-1.5 rounded-lg bg-white border border-red-100 hover:bg-red-50 text-red-400 hover:text-red-600 transition-all">
                                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Add milestone form -->
                        <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>" class="grid grid-cols-2 gap-2 pt-1">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="add_milestone">
                            <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                            <input type="text" name="title" required placeholder="New milestone" class="px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-200">
                            <input type="date" name="due_date" required class="px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-200">
                            <input type="text" name="description" placeholder="Description (optional)" class="col-span-2 px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-200">
                            <button class="col-span-2 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition-all">Add milestone</button>
                        </form>
                    </div>

                    <div class="space-y-6">
                        <!-- Updates feed -->
                        <div class="space-y-3">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Post an update to the client</span>
                            <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>" class="space-y-2">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="post_update">
                                <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                                <textarea name="message" rows="3" required placeholder="What was done, what's next..."
                                    class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-200"></textarea>
                                <button class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition-all">Post update</button>
                            </form>

                            <?php if (!empty($upd_list)): ?>
                                <div class="space-y-3 mt-1">
                                    <?php foreach ($upd_list as $u): ?>
                                        <div class="flex gap-3">
                                            <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                                <i data-lucide="user" class="w-3 h-3 text-slate-400"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-xs font-bold text-slate-900"><?= sanitize($u['author']) ?></span>
                                                    <span class="text-[10px] text-slate-400"><?= date('M j, Y', strtotime($u['created_at'])) ?></span>
                                                </div>
                                                <p class="text-xs text-slate-600 mt-0.5 break-words"><?= sanitize(mb_substr($u['message'], 0, 200)) ?><?= mb_strlen($u['message']) > 200 ? '…' : '' ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Invoices -->
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Invoices for this project</span>

                            <?php if (!empty($inv_list)): ?>
                                <div class="space-y-1.5">
                                    <?php foreach ($inv_list as $iv):
                                        $inv_status_cls = match($iv['status']) {
                                            'COMPLETED' => 'bg-emerald-100 text-emerald-700',
                                            'CANCELLED' => 'bg-red-100 text-red-600',
                                            default     => 'bg-amber-100 text-amber-700',
                                        };
                                    ?>
                                        <div class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-100 rounded-xl gap-2">
                                            <div class="min-w-0">
                                                <p class="text-[10px] font-bold text-slate-700 font-mono"><?= sanitize($iv['transaction_id']) ?></p>
                                                <p class="text-[9px] text-slate-400"><?= sanitize($iv['type']) ?></p>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <span class="text-[10px] font-bold text-slate-900"><?= format_currency($iv['amount']) ?></span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase <?= $inv_status_cls ?>"><?= sanitize($iv['status']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-[10px] text-slate-400 text-center py-2">No invoices issued yet.</p>
                            <?php endif; ?>

                            <form method="POST" action="project_control.php?project_id=<?= $active_proj_id ?>" class="grid grid-cols-2 gap-2 pt-1">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="issue_invoice">
                                <input type="hidden" name="project_id" value="<?= $active_proj_id ?>">
                                <select name="type" class="px-2 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                    <option value="MILESTONE">Milestone</option>
                                    <option value="FINAL">Final</option>
                                </select>
                                <input type="number" name="amount" step="0.01" min="1" required placeholder="Amount (₦)" class="px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                <input type="text" name="label" required placeholder="e.g. Milestone 2 - Core build" class="col-span-2 px-3 py-1.5 bg-white border border-slate-250 rounded-lg text-xs outline-none">
                                <button class="col-span-2 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-all">Issue invoice</button>
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

    function openEditProjectModal(data) {
        document.getElementById('ep_project_id').value = data.id;
        document.getElementById('ep_title').value = data.title;
        document.getElementById('ep_category').value = data.category;
        document.getElementById('ep_budget').value = data.budget;
        document.getElementById('ep_start_date').value = data.start_date;
        document.getElementById('ep_end_date').value = data.end_date;
        document.getElementById('ep_supervisor_id').value = data.supervisor_id;
        document.getElementById('ep_description').value = data.description;
        document.getElementById('edit-project-modal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeEditProjectModal() {
        document.getElementById('edit-project-modal').classList.add('hidden');
    }

    function openDeleteProjectModal(id, title) {
        document.getElementById('dp_project_id').value = id;
        document.getElementById('dp_project_title').textContent = title;
        document.getElementById('delete-project-modal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeDeleteProjectModal() {
        document.getElementById('delete-project-modal').classList.add('hidden');
    }

    // Escape key closes modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditProjectModal();
            closeDeleteProjectModal();
        }
    });

    // Backdrop click closes modals
    document.addEventListener('click', function(e) {
        if (e.target === document.getElementById('edit-project-modal')) closeEditProjectModal();
        if (e.target === document.getElementById('delete-project-modal')) closeDeleteProjectModal();
    });

    // Project search filter
    const projectSearch = document.getElementById('project-search');
    if (projectSearch) {
        projectSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('[data-project-card]').forEach(function(card) {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
