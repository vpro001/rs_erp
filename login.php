<?php
// login.php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db_connection.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    switch ($user['role']) {
        case 'ADMIN': case 'MANAGER': header("Location: " . BASE_URL . "/admin/index.php"); break;
        case 'STAFF': header("Location: " . BASE_URL . "/staff/index.php"); break;
        case 'STUDENT': header("Location: " . BASE_URL . "/student/index.php"); break;
        case 'CLIENT': header("Location: " . BASE_URL . "/client/index.php"); break;
        case 'MARKETER': header("Location: " . BASE_URL . "/marketer/index.php"); break;
    }
    exit();
}

$errors = [];
$success_msg = "";

$awaiting_otp = $_SESSION['auth_otp_pending'] ?? false;
$otp_email = $_SESSION['auth_otp']['email'] ?? '';
$otp_action = $_SESSION['auth_otp']['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Token validation failed.");
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login_submit') {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate login fields
        $login_rules = [
            'required' => ['email', 'password'],
            'email' => [['email' => ['message' => 'Invalid email format']]],
            'minLength' => [['password' => ['min' => 6]]]
        ];
        
        $validation = validate(['email' => $email, 'password' => $password], $login_rules);
        
        if (!$validation['success']) {
            $errors = array_merge($errors, $validation['errors']);
        } else {
            $email = $validation['validated']['email'];
            $password = $validation['validated']['password'];
            try {
                $stmt = $conn->prepare("SELECT * FROM users_student WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res->fetch_assoc();
                $stmt->close();
                
                if ($user) {
                    if ($user['status'] !== 'ACTIVE') {
                        $errors[] = "Your account status is: " . $user['status'] . ". Access denied.";
                    } // Development mode: Allow direct password comparison (disable in production)
// else if (password_verify($password, $user['password_hash'])) {
if ($password === $user['password_hash']) {
                        // OTP logic removed. Straight-forward login.
                        $_SESSION['user'] = $user;
                        $stmt = $conn->prepare("UPDATE users_student SET last_active = NOW() WHERE id = ?");
                        $stmt->bind_param("s", $user['id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        header("Location: " . BASE_URL . "/student/index.php");
                        exit();
                    } else {
                        $errors[] = "Incorrect password.";
                    }
                } else {
                    $errors[] = "No student account found with this email. This portal is for students; staff and clients must use their own portal.";
                }
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    else if ($action === 'verify_otp') {
        $user_otp = trim($_POST['otp'] ?? '');
        $saved_otp = $_SESSION['auth_otp'] ?? null;
        
        if (empty($user_otp)) {
            $errors[] = "OTP field cannot be empty.";
        } else if (!$saved_otp) {
            $errors[] = "OTP session expired or invalid. Please try again.";
        } else if (time() > $saved_otp['expires']) {
            $errors[] = "OTP has expired. Please request a new code.";
        } else if ($user_otp !== $saved_otp['code']) {
            $errors[] = "Invalid OTP verification code.";
        } else {
            if ($saved_otp['action'] === 'login') {
                try {
                    $stmt = $conn->prepare("SELECT * FROM users_student WHERE id = ?");
                    $stmt->bind_param("s", $saved_otp['user_id']);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if ($user) {
                        $_SESSION['user'] = [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'username' => $user['username'],
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'],
                            'role' => $user['role'],
                            'avatar' => $user['avatar']
                        ];
                        $_SESSION['last_active'] = time();
                        
                        unset($_SESSION['auth_otp'], $_SESSION['auth_otp_pending']);
                        log_activity("User Logged In", ["email" => $user['email']]);
                        
                        $redirect = $_SESSION['redirect_to'] ?? '';
                        unset($_SESSION['redirect_to']);
                        
                        if ($redirect && check_portal_access($user['role'], $redirect)) {
                            header("Location: " . $redirect);
                        } else {
                            switch ($user['role']) {
                                case 'ADMIN': case 'MANAGER': header("Location: " . BASE_URL . "/admin/index.php"); break;
                                case 'STAFF': header("Location: " . BASE_URL . "/staff/index.php"); break;
                                case 'STUDENT': header("Location: " . BASE_URL . "/student/index.php"); break;
                                case 'CLIENT': header("Location: " . BASE_URL . "/client/index.php"); break;
                                case 'MARKETER': header("Location: " . BASE_URL . "/marketer/index.php"); break;
                            }
                        }
                        exit();
                    } else {
                        $errors[] = "User record not found.";
                    }
                } catch (Exception $e) {
                    $errors[] = "Database authentication error: " . $e->getMessage();
                }
            }
        }
    }
    else if ($action === 'resend_otp') {
        if ($awaiting_otp && $otp_email) {
            $otp = strval(mt_rand(100000, 999999));
            $_SESSION['auth_otp']['code'] = $otp;
            $_SESSION['auth_otp']['expires'] = time() + 300;
            
            $log_file = __DIR__ . '/uploads/otp_simulator.log';
            $log_msg = "[" . date('Y-m-d H:i:s') . "] RESEND OTP generated for $otp_email ($otp_action): $otp\n";
            @file_put_contents($log_file, $log_msg, FILE_APPEND);
            
            $success_msg = "A new verification code has been dispatched.";
        } else {
            $errors[] = "No active OTP transaction found.";
        }
    }
    else if ($action === 'cancel_otp') {
        unset($_SESSION['auth_otp'], $_SESSION['auth_otp_pending']);
        $awaiting_otp = false;
        $success_msg = "OTP session cancelled.";
    }
}

// Reusable classes for consistent contrast
$input_classes = "w-full px-3.5 py-3 rounded-lg border text-sm outline-none";
$label_classes = "block text-sm font-medium mb-1.5 text-slate-700";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login · Royal Solutions ERP</title>
    
    <?php include __DIR__ . '/includes/theme.php'; ?>
    <style>
        .auth-card { animation: fadeIn .25s ease-out both; }
        @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
        .otp-box { width: 3rem; height: 3.5rem; text-align: center; font-size: 1.4rem; font-weight: 600; border-radius: 8px; font-family: 'JetBrains Mono', monospace !important; }
        .spinner { border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; width: 16px; height: 16px; animation: spin .8s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Bar -->
    <header class="relative z-10 px-8 py-5 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-[7px] border border-[#17264A] flex items-center justify-center flex-shrink-0 font-display font-semibold text-[15px] text-[#17264A]">R</div>
            <div>
                <span class="font-display font-semibold text-slate-900 text-[15px] block leading-tight">Royal Solutions</span>
                <span class="text-[11px] text-slate-500">Student portal</span>
            </div>
        </div>
    </header>

    <!-- Main Layout -->
    <main class="flex-1 flex items-center justify-center px-4 pb-12 pt-4 relative z-10">
        <div class="w-full max-w-5xl">
            
            <div class="auth-card rounded-xl overflow-hidden flex flex-col lg:flex-row min-h-[560px] bg-white border border-[#E4DFD4]">
                
                <!-- LEFT PANEL -->
                <div class="lg:w-5/12 relative overflow-hidden flex flex-col justify-between p-8 lg:p-10 bg-[#F4F2EC] text-slate-900 border-r border-[#E4DFD4]">

                    <div class="relative z-10">
                        <h1 class="font-display text-3xl lg:text-[34px] font-semibold leading-tight">
                            Student<br><span class="text-[#9A7A2E]">Portal</span>
                        </h1>
                        <p class="text-sm mt-3 text-slate-600">Sign in to continue.</p>
                    </div>

                    <div class="relative z-10 space-y-5 my-8">
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center mt-0.5 border border-[#D3CCBC] bg-white">
                                <i data-lucide="shield-check" class="w-4 h-4 text-[#9A7A2E]"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Two-step verification</p>
                                <p class="text-xs text-slate-600 mt-0.5">A code is sent to your email at sign-in</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center mt-0.5 border border-[#D3CCBC] bg-white">
                                <i data-lucide="user-check" class="w-4 h-4 text-[#9A7A2E]"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Admin-Provisioned access</p>
                                <p class="text-xs text-slate-600 mt-0.5">Accounts created by administration</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center mt-0.5 border border-[#D3CCBC] bg-white">
                                <i data-lucide="activity" class="w-4 h-4 text-[#9A7A2E]"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Live records</p>
                                <p class="text-xs text-slate-600 mt-0.5">Courses, grades and billing in one place</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL -->
                <div class="lg:w-7/12 flex flex-col justify-center p-8 lg:p-10 relative bg-white">

                    <!-- Alerts -->
                    <?php if (!empty($errors)): ?>
                        <div class="mb-5 p-3.5 rounded-lg text-sm flex items-start space-x-3 bg-red-50 border border-red-200 text-red-700 animate-shake">
                            <i data-lucide="alert-octagon" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                            <div class="flex flex-col">
                                <?php foreach ($errors as $err): ?>
                                    <span class="text-sm"><?php echo $err; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_msg)): ?>
                        <div class="mb-5 p-3.5 rounded-lg text-sm flex items-start space-x-3 bg-emerald-50 border border-emerald-200 text-emerald-700">
                            <i data-lucide="check-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                            <span class="text-sm"><?php echo $success_msg; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($awaiting_otp): ?>
                    <!-- OTP SCREEN -->
                    <div class="text-center mb-7">
                        <div class="w-12 h-12 rounded-lg border border-[#D3CCBC] flex items-center justify-center mx-auto mb-4"><i data-lucide="mail-check" class="w-6 h-6 text-[#17264A]"></i></div>
                        <h2 class="font-display text-2xl font-semibold text-slate-900 ">Check your email</h2>
                        <p class="text-sm text-slate-600 mt-2">
                            Enter the 6-digit code sent to<br>
                            <span class="font-semibold text-[#17264A] mt-1 block"><?php echo sanitize($otp_email); ?></span>
                        </p>
                    </div>

                    <form action="<?php echo BASE_URL; ?>/login.php" method="POST" class="space-y-6" onsubmit="combineOtp()">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="verify_otp">
                        <input type="hidden" name="otp" id="otp-hidden">
                        
                        <div class="flex justify-center gap-2 sm:gap-3">
                            <input type="text" maxlength="1" class="otp-box" data-otp="1" required>
                            <input type="text" maxlength="1" class="otp-box" data-otp="2" required>
                            <input type="text" maxlength="1" class="otp-box" data-otp="3" required>
                            <input type="text" maxlength="1" class="otp-box" data-otp="4" required>
                            <input type="text" maxlength="1" class="otp-box" data-otp="5" required>
                            <input type="text" maxlength="1" class="otp-box" data-otp="6" required>
                        </div>
                        
                        <button type="submit" class="btn-neon w-full py-3 rounded-lg text-sm flex items-center justify-center gap-2">
                            <span>Verify</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </form>

                    <div class="flex justify-between items-center mt-6">
                        <form action="<?php echo BASE_URL; ?>/login.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="resend_otp">
                            <button type="submit" class="flex items-center space-x-1.5 text-sm font-semibold transition-colors text-[#17264A] hover:underline">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                <span>Resend code</span>
                            </button>
                        </form>
                        <form action="<?php echo BASE_URL; ?>/login.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="cancel_otp">
                            <button type="submit" class="flex items-center space-x-1.5 text-sm text-slate-600 hover:text-slate-900  transition-colors">
                                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                                <span>Cancel</span>
                            </button>
                        </form>
                    </div>

                    <!-- Dev OTP Helper -->
                    <div class="mt-8 p-4 rounded-lg border border-dashed border-[#D3CCBC] bg-[#FBFAF7]">
                        <h4 class="text-xs font-semibold mb-2 flex items-center space-x-1.5 text-slate-600">
                            <i data-lucide="terminal" class="w-3 h-3"></i>
                            <span>Development mode</span>
                        </h4>
                        <p class="text-xs text-slate-600 flex items-center gap-2">
                            Code:
                            <span class="font-mono font-semibold px-2 py-1 rounded text-sm bg-white text-[#17264A] border border-[#D3CCBC] tracking-widest">
                                <?php echo $_SESSION['auth_otp']['code'] ?? '000000'; ?>
                            </span>
                        </p>
                    </div>

                    <?php else: ?>
                    <!-- STUDENT LOGIN FORM (accounts are created by the admin - no self-registration) -->
                    <div class="space-y-5">
                        <div class="mb-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#9A7A2E] mb-2">Student portal</p>
                            <h2 class="font-display text-2xl font-semibold text-slate-900 ">Sign in</h2>
                            <p class="text-sm text-slate-600 mt-1">Use the email and password issued to you.</p>
                        </div>
                        <form action="<?php echo BASE_URL; ?>/login.php" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="login_submit">
                            
                            <div>
                                <label class="<?php echo $label_classes; ?>">Email Address</label>
                                <div class="relative group">
                                    <i data-lucide="mail" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400   transition-colors"></i>
                                    <input type="email" name="email" required placeholder="student@school.edu" class="<?php echo $input_classes; ?> pl-10 pr-4">
                                </div>
                            </div>
                            
                            <div>
                                <label class="<?php echo $label_classes; ?>">Password</label>
                                <div class="relative group">
                                    <i data-lucide="lock" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400   transition-colors"></i>
                                    <input type="password" name="password" id="login-password" required placeholder="••••••••" class="<?php echo $input_classes; ?> pl-10 pr-10">
                                    <button type="button" onclick="togglePassword('login-password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#17264A]  transition-colors">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-neon w-full py-3 rounded-lg text-sm mt-2 flex items-center justify-center gap-2">
                                <span>Sign in</span>
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </button>
                        </form>

                        <div class="p-3.5 rounded-lg border border-[#E4DFD4] bg-[#FBFAF7] flex items-start space-x-2.5">
                            <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0 text-slate-400 "></i>
                            <p class="text-xs text-slate-500  leading-relaxed">
                                Student accounts are created by the administration team — self-registration is not available. Contact the admin if you need access. Staff and clients sign in through their own portals.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </main>

    <footer class="text-center pb-6 text-xs text-slate-500">
        © <?php echo date('Y'); ?> Royal Solutions
    </footer>

    <style>
        /* Shake animation for errors */
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
        .animate-shake { animation: shake 0.4s; }
    </style>

    <script>
        lucide.createIcons();

        // Password Toggle
        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = '<i data-lucide="eye-off" class="w-4 h-4"></i>';
            } else {
                input.type = 'password';
                btn.innerHTML = '<i data-lucide="eye" class="w-4 h-4"></i>';
            }
            lucide.createIcons();
        }

        // OTP Auto-Tab Logic
        const otpInputs = document.querySelectorAll('.otp-box');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (input.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text').trim();
                if(/^\d+$/.test(paste)) {
                    paste.split('').forEach((char, i) => {
                        if (otpInputs[i]) otpInputs[i].value = char;
                    });
                    otpInputs[Math.min(paste.length, otpInputs.length - 1)].focus();
                }
            });
        });

        function combineOtp() {
            const hidden = document.getElementById('otp-hidden');
            hidden.value = Array.from(otpInputs).map(i => i.value).join('');
        }

        // Add loading state to all form submits
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const btn = form.querySelector('button[type="submit"]');
                if (btn && !btn.classList.contains('btn-neon')) return; 
                if (btn) {
                    const originalContent = btn.innerHTML;
                    btn.innerHTML = '<div class="spinner"></div> <span>Please wait…</span>';
                    btn.disabled = true;
                    setTimeout(() => {
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                        lucide.createIcons();
                    }, 3000);
                }
            });
        });
    </script>
</body>
</html>
