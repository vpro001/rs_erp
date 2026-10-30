<?php
// index.php — Royal Solutions gateway page
require_once __DIR__ . '/includes/helpers.php';

$user = current_user();

if ($user) {
    switch ($user['role']) {
        case 'ADMIN':
        case 'MANAGER':
            header("Location: " . BASE_URL . "/admin/index.php");
            break;
        case 'STAFF':
            header("Location: " . BASE_URL . "/staff/index.php");
            break;
        case 'STUDENT':
            header("Location: " . BASE_URL . "/student/index.php");
            break;
        case 'CLIENT':
            header("Location: " . BASE_URL . "/client/index.php");
            break;
        case 'MARKETER':
            header("Location: " . BASE_URL . "/marketer/index.php");
            break;
        default:
            header("Location: " . BASE_URL . "/login.php");
            break;
    }
    exit();
}

$loginUrl = BASE_URL . '/login.php';
$clientUrl = BASE_URL . '/client/login.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Royal Solutions — Sign in</title>
    <meta name="description" content="Access your Royal Solutions portal — students, clients, and staff.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --bg: #FBFAF7;
            --surface: #FFFFFF;
            --border: #E4DFD4;
            --border-strong: #D3CCBC;
            --ink: #17150F;
            --ink-soft: #4C473C;
            --muted: #8A8372;
            --navy: #17264A;
            --navy-soft: #26355C;
            --gold: #9A7A2E;
            --gold-tint: #F4ECD9;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1,
        h2,
        .font-display {
            font-family: 'Fraunces', Georgia, serif;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--ink);
            margin: 0;
        }

        p {
            margin: 0;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px;
            width: 100%;
        }

        .icon {
            width: 18px;
            height: 18px;
            stroke-width: 1.75;
        }

        /* header */
        header.site {
            padding: 26px 0;
        }

        nav.site {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 7px;
            border: 1px solid var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 15px;
            color: var(--navy);
            flex-shrink: 0;
        }

        .brand-name {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 15px;
            color: var(--ink);
        }

        .help-link {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ink-soft);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .help-link:hover {
            color: var(--navy);
        }

        /* main */
        main {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 40px 0 60px;
        }

        .intro {
            text-align: center;
            max-width: 560px;
            margin: 0 auto 44px;
        }

        .intro h1 {
            font-size: 30px;
            line-height: 1.3;
        }

        .intro p {
            margin-top: 12px;
            font-size: 15px;
            color: var(--ink-soft);
            line-height: 1.6;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media(max-width:680px) {
            .portal-grid {
                grid-template-columns: 1fr;
            }
        }

        .portal-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px 28px;
            display: flex;
            flex-direction: column;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .portal-card:hover {
            border-color: var(--border-strong);
            box-shadow: 0 6px 24px -14px rgba(23, 21, 15, .18);
        }

        .portal-icon {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            border: 1px solid var(--border-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }

        .portal-card h2 {
            font-size: 18px;
        }

        .portal-card .who {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--gold);
            margin-top: 3px;
            display: block;
        }

        .portal-card .desc {
            margin-top: 12px;
            font-size: 14px;
            color: var(--ink-soft);
            line-height: 1.6;
            flex: 1;
        }

        .btn {
            margin-top: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            padding: 11px 20px;
            border-radius: 7px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
        }

        .btn-primary {
            background: var(--navy);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--navy-soft);
        }

        .btn-secondary {
            background: #fff;
            color: var(--ink);
            border-color: var(--border-strong);
        }

        .btn-secondary:hover {
            border-color: var(--navy);
            color: var(--navy);
        }

        .new-client {
            margin-top: 14px;
            font-size: 13px;
            color: var(--muted);
            text-align: center;
        }

        .new-client a {
            color: var(--navy);
            font-weight: 600;
        }

        .new-client a:hover {
            text-decoration: underline;
        }

        /* help strip */
        .help-strip {
            margin-top: 40px;
            border: 1px dashed var(--border-strong);
            border-radius: 10px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 13.5px;
            color: var(--ink-soft);
        }

        .help-strip .icon {
            color: var(--gold);
            flex-shrink: 0;
        }

        .help-strip a {
            color: var(--navy);
            font-weight: 600;
        }

        .help-strip a:hover {
            text-decoration: underline;
        }

        footer {
            border-top: 1px solid var(--border);
            padding: 20px 0;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            color: var(--muted);
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>

<body>

    <header class="site">
        <div class="wrap">
            <nav class="site">
                <a href="<?php echo BASE_URL; ?>/index.php" class="brand">
                    <div class="brand-mark">R</div>
                    <span class="brand-name">Royal Solutions</span>
                </a>
                <a href="mailto:support@royalsolutions.com.ng" class="help-link">
                    <i data-lucide="life-buoy" class="icon" style="width:15px;height:15px;"></i>
                    <span>Need help?</span>
                </a>
            </nav>
        </div>
    </header>

    <main>
        <div class="wrap">
            <div class="intro">
                <h1>Sign in to continue</h1>
                <p>Choose the portal that applies to you. Students, staff, and administrators use one door; clients use the other.</p>
            </div>

            <div class="portal-grid">

                <!-- Student / Staff / Admin -->
                <div class="portal-card">
                    <div class="portal-icon"><i data-lucide="graduation-cap" class="icon" style="color:var(--navy);"></i></div>
                    <span class="who">Students</span>
                    <h2>Academic & internal access</h2>
                    <p class="desc">Courses, assignments, grades, certificates, and — for staff — scheduling, records and reports.</p>
                    <a href="<?php echo $loginUrl; ?>" class="btn btn-primary">
                        <i data-lucide="log-in" class="icon" style="width:16px;height:16px;color:#fff;"></i>
                        <span>Sign in</span>
                    </a>
                </div>

                <!-- Client -->
                <div class="portal-card">
                    <div class="portal-icon"><i data-lucide="briefcase" class="icon" style="color:var(--navy);"></i></div>
                    <span class="who">Clients</span>
                    <h2>Project & account access</h2>
                    <p class="desc">Proposals, project status, meeting notes, documents, and invoices for your engagement with us.</p>
                    <a href="<?php echo $clientUrl; ?>" class="btn btn-primary">
                        <i data-lucide="log-in" class="icon" style="width:16px;height:16px;color:#fff;"></i>
                        <span>Sign in</span>
                    </a>
                    <p class="new-client">New client? <a href="<?php echo $clientUrl; ?>#register">Create an account</a></p>
                </div>

            </div>

            <div class="help-strip">
                <i data-lucide="info" class="icon"></i>
                <span>
                    Not sure which portal you need, or having trouble signing in?
                    Contact us at <a href="mailto:support@royalsolutions.com.ng">support@royalsolutions.com.ng</a>
                    or your account administrator.
                </span>
            </div>
        </div>
    </main>

    <footer>
        <div class="wrap footer-row">
            <span>© <?php echo date('Y'); ?> Royal Solutions.</span>
            <span>Having trouble? <a href="mailto:support@royalsolutions.com.ng" style="color:var(--navy);font-weight:600;">support@royalsolutions.com.ng</a></span>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>