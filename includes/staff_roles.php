<?php
// includes/staff_roles.php
// Staff accounts (users_staff, created by admins only) carry a staff_role on their `staff` row.
// Pages check capabilities with can()/require_permission() instead of raw roles, so the admin
// panel and each staff role share the same screens with the right scope.

const STAFF_ROLES = [
    'INSTRUCTOR' => 'Instructor',
    'MARKETING' => 'Marketing & Sales Manager',
    'CUSTOMER_SERVICE' => 'Customer Service',
    'HEAD_ADMIN' => 'Head Admin',
];

// Default department / position written to the staff row for each role.
const STAFF_ROLE_DEFAULTS = [
    'INSTRUCTOR' => ['Academics & Instruction', 'Instructor'],
    'MARKETING' => ['Marketing & Sales', 'Marketing & Sales Manager'],
    'CUSTOMER_SERVICE' => ['Customer Service', 'Customer Service Officer'],
    'HEAD_ADMIN' => ['Administration', 'Head Admin'],
];

/**
 * Capability => staff roles that hold it. ADMIN and MANAGER accounts hold every capability.
 *  students.manage  register students, enrollments, batches, payments, negotiated fees
 *  students.view    see student records and download admission letters
 *  certify          certify full payment and authorize certificates
 *  teach            send assignments, grade, message students (instructors: own batches only)
 *  sales            prospects, appointments/demos, sales records
 *  marketers        manage marketer accounts, referrals and commissions
 *  meetings         client meeting schedule
 */
const STAFF_PERMISSIONS = [
    'students.manage' => ['CUSTOMER_SERVICE'],
    'students.view' => ['CUSTOMER_SERVICE', 'HEAD_ADMIN'],
    'certify' => ['HEAD_ADMIN'],
    'teach' => ['INSTRUCTOR'],
    'sales' => ['MARKETING'],
    'marketers' => ['MARKETING'],
    'meetings' => ['MARKETING'],
];

/**
 * The logged-in staff member's `staff` row (with staff_role), or null for non-staff.
 * Read fresh each request so a role change by the admin applies immediately.
 */
function current_staff()
{
    global $conn;
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'STAFF' || !isset($conn)) {
        return null;
    }
    try {
        $stmt = $conn->prepare("SELECT * FROM staff WHERE user_id = ?");
        $stmt->bind_param("s", $user['id']);
        $stmt->execute();
        $cache = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    } catch (Exception $e) {
        $cache = null;
    }
    return $cache;
}

function staff_role()
{
    $staff = current_staff();
    return $staff['staff_role'] ?? null;
}

function is_admin_user()
{
    return has_role(['ADMIN', 'MANAGER']);
}

function can($permission)
{
    if (is_admin_user()) {
        return true;
    }
    $role = staff_role();
    return $role !== null && in_array($role, STAFF_PERMISSIONS[$permission] ?? [], true);
}

/** Dashboard URL (absolute) for the logged-in user's portal. */
function portal_home_url()
{
    $role = current_user()['role'] ?? '';
    $path = match ($role) {
        'ADMIN', 'MANAGER' => '/admin/index.php',
        'STAFF' => '/staff/index.php',
        'STUDENT' => '/student/index.php',
        'CLIENT' => '/client/index.php',
        'MARKETER' => '/marketer/index.php',
        default => '/login.php',
    };
    return BASE_URL . $path;
}

/** Stop the request unless the current user holds $permission. */
function require_permission($permission)
{
    if (can($permission)) {
        return;
    }
    set_flash_message('error', 'Access Denied: your role does not have access to that page.');
    header("Location: " . portal_home_url());
    exit();
}

/**
 * Batches (classes.id) the current user teaches, or null meaning "all batches" for admins.
 *
 * @return array|null
 */
function teaching_batch_ids()
{
    global $conn;
    if (is_admin_user()) {
        return null;
    }
    $staff = current_staff();
    if (!$staff) {
        return [];
    }
    $stmt = $conn->prepare("SELECT class_id FROM class_staff WHERE staff_id = ?");
    $stmt->bind_param("s", $staff['id']);
    $stmt->execute();
    $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'class_id');
    $stmt->close();
    return $ids;
}

/** SQL-safe IN (...) list for ids that came from the database. */
function sql_in_list($ids)
{
    global $conn;
    if (!$ids) {
        return "('')";
    }
    return "('" . implode("','", array_map([$conn, 'real_escape_string'], $ids)) . "')";
}

/** Display name for the person acting (used in logs and messages). */
function actor_name()
{
    $u = current_user();
    return $u ? trim($u['first_name'] . ' ' . $u['last_name']) : 'System';
}

/**
 * SQL condition limiting assignments (alias $a) on courses (alias $c) to what the current user teaches:
 * everything for admins; for instructors, assignments in their batches or on courses they lead.
 */
function teaching_assignment_scope($a = 'a', $c = 'c')
{
    global $conn;
    $batches = teaching_batch_ids();
    if ($batches === null) {
        return '1=1';
    }
    $staff_id = $conn->real_escape_string(current_staff()['id'] ?? '');
    return "($a.class_id IN " . sql_in_list($batches) . " OR $c.instructor_id = '$staff_id')";
}
