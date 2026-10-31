<?php
// includes/helpers.php

if (session_status() === PHP_SESSION_NONE) {
    $save_path = session_save_path();
    if (empty($save_path) || !is_writable($save_path)) {
        $temp_path = sys_get_temp_dir();
        if (is_writable($temp_path)) {
            session_save_path($temp_path);
        }
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    @session_start();
}

// Global project BASE_URL configuration
if (!defined('BASE_URL')) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $base_path = '';
        if (isset($_SERVER['SCRIPT_NAME'])) {
            $pos = strpos($_SERVER['SCRIPT_NAME'], '/rs_erp');
            if ($pos !== false) {
                $base_path = '/rs_erp';
            }
        }
        define('BASE_URL', rtrim($protocol . $host . $base_path, '/'));
    } else {
        // Fallback for CLI/Cron environments
        define('BASE_URL', 'http://localhost/rs_erp');
    }
}

/**
 * Resolve upload file URL safely for views and downloads.
 *
 * @param string|null $path
 * @return string
 */
function upload_url($path)
{
    if (empty($path))
        return '';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Avatar image URL for a user: their uploaded photo, or an initials placeholder.
 *
 * @param string|null $path Stored avatar path (users_*.avatar)
 * @param string $name Display name used for the initials fallback
 * @return string
 */
function avatar_url($path, $name = '')
{
    if (!empty($path)) {
        return upload_url($path);
    }
    $initials = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part !== '' && strlen($initials) < 2) {
            $initials .= strtoupper(mb_substr($part, 0, 1));
        }
    }
    $initials = htmlspecialchars($initials ?: '?', ENT_QUOTES | ENT_XML1, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
        . '<rect width="100" height="100" fill="#6366f1"/>'
        . '<text x="50" y="50" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="40" font-weight="600" fill="#fff">' . $initials . '</text>'
        . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Resolve asset URL safely.
 *
 * @param string|null $path
 * @return string
 */
function asset_url($path)
{
    return upload_url($path);
}

/**
 * Generate a unique UUID v4.
 *
 * @return string
 */
function generate_uuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Sanitize user input for XSS prevention.
 *
 * @param mixed $data Input string or array
 * @return mixed Sanitized string or array
 */
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate user input with comprehensive rules.
 * PHP equivalent of Zod validation schemas.
 *
 * @param array $data Input data array
 * @param array $rules Validation rules
 * @return array ['success' => bool, 'errors' => array, 'validated' => array]
 */
function validate($data, $rules)
{
    $errors = [];
    $validated = [];

    // Check required fields
    if (isset($rules['required']) && is_array($rules['required'])) {
        foreach ($rules['required'] as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "{$field} is required";
            }
        }
    }

    // Validate email format
    if (isset($rules['email']) && is_array($rules['email'])) {
        foreach ($rules['email'] as $field => $options) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = $options['message'] ?? 'Invalid email format';
                }
            }
        }
    }

    // Validate minimum length
    if (isset($rules['minLength']) && is_array($rules['minLength'])) {
        foreach ($rules['minLength'] as $field => $options) {
            if (isset($data[$field]) && strlen($data[$field]) < $options['min']) {
                $errors[$field] = "{$field} must be at least {$options['min']} characters";
            }
        }
    }

    // Validate maximum length
    if (isset($rules['maxLength']) && is_array($rules['maxLength'])) {
        foreach ($rules['maxLength'] as $field => $options) {
            if (isset($data[$field]) && strlen($data[$field]) > $options['max']) {
                $errors[$field] = "{$field} must not exceed {$options['max']} characters";
            }
        }
    }

    // Validate enum/allowed values
    if (isset($rules['enum']) && is_array($rules['enum'])) {
        foreach ($rules['enum'] as $field => $options) {
            if (isset($data[$field]) && !in_array($data[$field], $options['values'])) {
                $errors[$field] = "{$field} must be one of: " . implode(', ', $options['values']);
            }
        }
    }

    // Validate positive number
    if (isset($rules['positiveNumber']) && is_array($rules['positiveNumber'])) {
        foreach ($rules['positiveNumber'] as $field => $options) {
            if (isset($data[$field])) {
                $val = $data[$field];
                if (!is_numeric($val) || $val <= 0) {
                    $errors[$field] = $options['message'] ?? "{$field} must be a positive number";
                }
            }
        }
    }

    // Validated data includes only fields that passed validation
    foreach ($data as $key => $value) {
        if (!isset($errors[$key])) {
            $validated[$key] = $value;
        }
    }

    return [
        'success' => empty($errors),
        'errors' => $errors,
        'validated' => $validated
    ];
}

/**
 * Get current logged in user details.
 *
 * @return array|null
 */
function current_user()
{
    return $_SESSION['user'] ?? null;
}

/**
 * Check if the user has a specific role or roles.
 *
 * @param string|array $roles A single role string or array of role strings
 * @return bool
 */
function has_role($roles)
{
    $user = current_user();
    if (!$user)
        return false;

    if (is_array($roles)) {
        return in_array($user['role'], $roles);
    }
    return $user['role'] === $roles;
}

/**
 * Check whether a role is allowed to access a portal URI.
 *
 * @param string $role User role
 * @param string $uri Request URI
 * @return bool
 */
function check_portal_access($role, $uri)
{
    if (strpos($uri, '/admin/') !== false && !in_array($role, ['ADMIN', 'MANAGER'])) {
        return false;
    }
    if (strpos($uri, '/staff/') !== false && $role !== 'STAFF' && !in_array($role, ['ADMIN', 'MANAGER'])) {
        return false;
    }
    if (strpos($uri, '/student/') !== false && $role !== 'STUDENT' && !in_array($role, ['ADMIN', 'MANAGER'])) {
        return false;
    }
    if (strpos($uri, '/client/') !== false && $role !== 'CLIENT' && !in_array($role, ['ADMIN', 'MANAGER'])) {
        return false;
    }
    if (strpos($uri, '/marketer/') !== false && $role !== 'MARKETER') {
        return false;
    }
    return true;
}

// Credentials live in one table per role family; ids are UUIDs so a lookup across all of them is unambiguous.
const USER_TABLES = ['users_student', 'users_client', 'users_staff', 'users_admin', 'users_marketer'];

/**
 * Credential table for a session role (MANAGER accounts live in users_admin).
 *
 * @param string $role
 * @return string|null
 */
function user_table_for_role($role)
{
    return match ($role) {
        'STUDENT' => 'users_student',
        'CLIENT' => 'users_client',
        'STAFF' => 'users_staff',
        'ADMIN', 'MANAGER' => 'users_admin',
        'MARKETER' => 'users_marketer',
        default => null,
    };
}

/**
 * Find which credential table holds a user id.
 *
 * @return string|null
 */
function find_user_table($conn, $user_id)
{
    foreach (USER_TABLES as $table) {
        $stmt = $conn->prepare("SELECT 1 FROM `$table` WHERE id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($found) return $table;
    }
    return null;
}

/**
 * Format currency.
 *
 * @param float $amount
 * @param string $currency
 * @return string
 */
function format_currency($amount, $currency = 'NGN')
{
    $symbol = '$';
    if ($currency === 'NGN') {
        $symbol = '₦';
    } else if ($currency === 'EUR') {
        $symbol = '€';
    } else if ($currency === 'GBP') {
        $symbol = '£';
    }
    return $symbol . number_format($amount, 2);
}

/**
 * Log activity in the database.
 *
 * @param string $action Action description
 * @param array|null $details Optional detailed metadata
 * @return bool
 */
function log_activity($action, $details = null)
{
    global $conn;
    if (!isset($conn)) {
        return false;
    }

    $user = current_user();
    $user_id = $user ? $user['id'] : null;
    if (!$user_id)
        return false;

    $id = generate_uuid();
    $details_json = $details ? json_encode($details) : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    try {
        $stmt = $conn->prepare("INSERT INTO activities (id, user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $id, $user_id, $action, $details_json, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send a notification to a specific user.
 *
 * @param string $user_id User unique ID
 * @param string $title Notification title
 * @param string $message Detailed notification message
 * @param string $type Notification type (GENERAL, LMS, CRM, FINANCE, COMPLAINT)
 * @param string $priority LOW, MEDIUM, HIGH
 * @param string|null $link Optional redirect link
 * @param array|null $metadata Optional JSON metadata
 * @return bool
 */
function send_notification($user_id, $title, $message, $type = 'GENERAL', $priority = 'MEDIUM', $link = null, $metadata = null)
{
    global $conn;
    if (!isset($conn)) {
        return false;
    }

    $id = generate_uuid();
    $metadata_json = $metadata ? json_encode($metadata) : null;

    try {
        // notifications.user_type is required: users_student -> STUDENT, users_admin -> ADMIN, ...
        $table = find_user_table($conn, $user_id);
        if (!$table) return false;
        $user_type = strtoupper(substr($table, strlen('users_')));

        $stmt = $conn->prepare("INSERT INTO notifications (id, user_id, user_type, title, message, type, priority, link, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $id, $user_id, $user_type, $title, $message, $type, $priority, $link, $metadata_json);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/** Notify every active admin/manager (used when a client or marketer needs staff attention). */
function notify_admins($title, $message, $link = null)
{
    global $conn;
    $res = $conn->query("SELECT id FROM users_admin WHERE role IN ('ADMIN','MANAGER') AND status = 'ACTIVE' LIMIT 5");
    while ($row = $res->fetch_assoc()) {
        send_notification($row['id'], $title, $message, 'CRM', 'HIGH', $link);
    }
}

/**
 * Set session message (flash messaging).
 *
 * @param string $type success, error, warning, info
 * @param string $message
 */
function set_flash_message($type, $message)
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear session message.
 *
 * @return array|null
 */
function get_flash_message()
{
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Check if running in development environment.
 *
 * @return bool
 */
function is_development()
{
    return (getenv('APP_ENV') ?? 'production') === 'development';
}

/**
 * Conditional logging for development only.
 * Equivalent to: if (process.env.NODE_ENV === 'development') console.log(...)
 *
 * @param string $message Log message
 * @param string $level error, warning, info, debug
 */
function dev_log($message, $level = 'info')
{
    if (!is_development()) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $prefix = strtoupper($level);
    error_log("[$timestamp] [$prefix] $message");
}

const REG_PREFIX = 'RIV/PH/WJ';

/**
 * Next reg number for the given year/month: RIV/PH/WJ/YYYY/MM/NNN.
 * The sequence is the highest used number for that year+month plus one,
 * so manually-entered numbers in the same format are respected too.
 */
function next_registration_number($conn, $year, $month)
{
    $prefix = sprintf('%s/%04d/%02d/', REG_PREFIX, $year, $month);
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id LIKE ? FOR UPDATE");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $max = 0;
    while ($row = $res->fetch_assoc()) {
        $n = substr($row['student_id'], strlen($prefix));
        if (ctype_digit($n)) {
            $max = max($max, (int) $n);
        }
    }
    $stmt->close();
    return $prefix . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}


/**
 * What clients can engage us for. proposals.category / projects.category store the label.
 * `milestones` are the delivery phases created at kickoff: [title, share of the project window, description].
 */
const SERVICE_CATALOG = [
    'corporate_training' => [
        'label' => 'Corporate Training',
        'icon' => 'presentation',
        'blurb' => 'Hands-on training for your staff or team, at your office, our centre or online.',
        'milestones' => [
            ['Training needs assessment', 0.15, 'Skills gap review with your team leads and agreed learning outcomes.'],
            ['Curriculum & materials', 0.40, 'Course outline, slides, exercises and schedule signed off.'],
            ['Training delivery', 0.85, 'Sessions delivered to all participants as scheduled.'],
            ['Evaluation & certificates', 1.00, 'Participant assessment, feedback report and certificates issued.'],
        ],
    ],
    'website' => [
        'label' => 'Website Design / Revamp',
        'icon' => 'globe',
        'blurb' => 'A new business website, online store, or a redesign of the site you already have.',
        'milestones' => [
            ['Design & sitemap', 0.25, 'Page structure, look and feel approved.'],
            ['Build & content', 0.65, 'Pages built and your content loaded on a preview link.'],
            ['Review & revisions', 0.85, 'Your feedback applied, forms and SEO basics checked.'],
            ['Launch & handover', 1.00, 'Site live on your domain, admin access and walkthrough handed over.'],
        ],
    ],
    'web_app' => [
        'label' => 'Web Application',
        'icon' => 'app-window',
        'blurb' => 'Custom software that runs in the browser: portals, dashboards, booking or management systems.',
        'milestones' => [
            ['Design & architecture', 0.25, 'Wireframes, prototype flows and system design signed off.'],
            ['Core build', 0.55, 'Backend, database and main features working in staging.'],
            ['Integrations & testing', 0.80, 'Third-party integrations, QA and fixes.'],
            ['Launch & handover', 1.00, 'Deployment, training and project handover.'],
        ],
    ],
    'mobile_app' => [
        'label' => 'Mobile App',
        'icon' => 'smartphone',
        'blurb' => 'Android and/or iOS apps for your customers or staff.',
        'milestones' => [
            ['Design & prototype', 0.25, 'Screens and user flows approved on a clickable prototype.'],
            ['Core build', 0.55, 'App, backend and main features working on test devices.'],
            ['Testing & beta', 0.80, 'Device testing, fixes and a beta build for your team.'],
            ['Store release & handover', 1.00, 'Published to Play Store / App Store and handed over.'],
        ],
    ],
    'cctv' => [
        'label' => 'CCTV Installation',
        'icon' => 'cctv',
        'blurb' => 'Security camera supply, installation and remote viewing for homes, offices and sites.',
        'milestones' => [
            ['Site survey & design', 0.20, 'Camera positions, cabling routes and equipment list agreed.'],
            ['Equipment procurement', 0.45, 'Cameras, recorder, storage and accessories sourced.'],
            ['Installation & cabling', 0.80, 'Cameras mounted, cabled and powered.'],
            ['Configuration & handover', 1.00, 'Recording, remote viewing set up, tested, and your team shown how to use it.'],
        ],
    ],
    'consultation' => [
        'label' => 'IT Consultation',
        'icon' => 'messages-square',
        'blurb' => 'Expert advice on technology decisions, digital strategy, systems or infrastructure.',
        'milestones' => [
            ['Discovery session', 0.25, 'Kick-off meeting to understand your goals and current setup.'],
            ['Assessment & analysis', 0.60, 'Review of systems, processes and options.'],
            ['Recommendations report', 0.90, 'Written findings with a prioritised action plan.'],
            ['Review meeting', 1.00, 'Walkthrough of recommendations and next steps.'],
        ],
    ],
    'other' => [
        'label' => 'Other',
        'icon' => 'sparkles',
        'blurb' => "Something not listed? Describe it and we'll tell you how we can help.",
        'milestones' => [
            ['Planning', 0.25, 'Scope, approach and schedule agreed.'],
            ['Execution', 0.65, 'Main work carried out.'],
            ['Review', 0.85, 'Your feedback applied.'],
            ['Delivery & handover', 1.00, 'Final delivery and handover.'],
        ],
    ],
];

/**
 * Catalog entry for a stored category label, or null for legacy/custom categories.
 */
function service_by_label($label)
{
    foreach (SERVICE_CATALOG as $key => $svc) {
        if (strcasecmp($svc['label'], (string) $label) === 0) {
            return $svc + ['key' => $key];
        }
    }
    return null;
}

const REFERRAL_COOKIE = 'rs_ref';

/**
 * Active marketer owning a referral code.
 *
 * @return array|null
 */
function find_marketer_by_code($conn, $code)
{
    try {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users_marketer WHERE referral_code = ? AND status = 'ACTIVE'");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Remember a marketer's ?ref= code for 30 days so the visitor's client signup is credited to them.
 */
function capture_referral_code($conn)
{
    $code = strtoupper(trim($_GET['ref'] ?? ''));
    if ($code === '' || !preg_match('/^[A-Z0-9]{4,20}$/', $code) || !find_marketer_by_code($conn, $code)) {
        return;
    }
    $_SESSION['referral_code'] = $code;
    setcookie(REFERRAL_COOKIE, $code, ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

/**
 * Marketer whose referral link brought the current visitor, if any.
 *
 * @return array|null
 */
function pending_referral_marketer($conn)
{
    $code = $_SESSION['referral_code'] ?? ($_COOKIE[REFERRAL_COOKIE] ?? '');
    return $code !== '' ? find_marketer_by_code($conn, strtoupper($code)) : null;
}

/**
 * Credit a newly registered client to the marketer whose link they came through.
 *
 * @return array|null The crediting marketer
 */
function credit_client_referral($conn, $client_id, $client_email)
{
    $marketer = pending_referral_marketer($conn);
    unset($_SESSION['referral_code']);
    if (isset($_COOKIE[REFERRAL_COOKIE])) {
        setcookie(REFERRAL_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    }
    if (!$marketer || strcasecmp($marketer['email'], $client_email) === 0) {
        return null;
    }
    $stmt = $conn->prepare("UPDATE clients SET referred_by = ? WHERE id = ? AND referred_by IS NULL");
    $stmt->bind_param("ss", $marketer['id'], $client_id);
    $stmt->execute();
    $stmt->close();
    return $marketer;
}

/**
 * Record the referring marketer's commission for a project that has just kicked off.
 * Run inside the kickoff transaction; the rate is snapshotted from the marketer's current rate.
 *
 * @return array|null ['marketer_id' => ..., 'amount' => ...] when a commission was recorded
 */
function record_project_commission($conn, $project_id, $client_id, $budget, $title)
{
    $stmt = $conn->prepare("SELECT m.id, m.project_commission_rate FROM clients cl JOIN users_marketer m ON m.id = cl.referred_by WHERE cl.id = ?");
    $stmt->bind_param("s", $client_id);
    $stmt->execute();
    $marketer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$marketer) {
        return null;
    }

    $rate = floatval($marketer['project_commission_rate']);
    $amount = round($budget * $rate / 100, 2);
    $id = generate_uuid();
    $desc = "Project kickoff: $title";
    $stmt = $conn->prepare("INSERT INTO marketer_commissions (id, marketer_id, source, project_id, description, base_amount, rate, amount) VALUES (?, ?, 'PROJECT', ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssddd", $id, $marketer['id'], $project_id, $desc, $budget, $rate, $amount);
    $stmt->execute();
    $stmt->close();
    return ['marketer_id' => $marketer['id'], 'amount' => $amount];
}

/**
 * Unused referral code like RS7F3A9C.
 */
function generate_referral_code($conn)
{
    do {
        $code = 'RS' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $conn->prepare("SELECT 1 FROM users_marketer WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $taken = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($taken);
    return $code;
}

function marketer_referral_link($code)
{
    return BASE_URL . '/client/login.php?ref=' . urlencode($code) . '#register';
}
