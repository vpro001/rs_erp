<?php
// includes/theme.php — single source of truth for fonts, Tailwind palette and base component styles.
// Included in <head> by header.php, login.php, portal_login_template.php and verify-certificate.php.
// Palette mirrors the gateway page (index.php): warm paper, ink, navy, one gold accent.
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
    window.BASE_URL = "<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>";
    // Legacy utility names (purple/cyan/blue/…) are remapped so existing markup picks up the new palette.
    (function () {
        var navy = { 50: '#F1F3F8', 100: '#E1E5EF', 200: '#C3CADD', 300: '#97A3C4', 400: '#5C6C9C', 500: '#26355C', 600: '#17264A', 700: '#121E3B', 800: '#0D1630', 900: '#091024' };
        var gold = { 50: '#FAF5E8', 100: '#F4ECD9', 200: '#E8D8AE', 300: '#D6BC78', 400: '#BF9A45', 500: '#9A7A2E', 600: '#7F6424', 700: '#664F1D', 800: '#4D3C16', 900: '#352A10' };
        var stone = { 50: '#FBFAF7', 100: '#F4F2EC', 200: '#E4DFD4', 300: '#D3CCBC', 400: '#A39C8A', 500: '#8A8372', 600: '#6B655A', 700: '#4C473C', 800: '#2E2B23', 900: '#17150F', 950: '#0E0D09' };
        var green = { 50: '#EEF6F0', 100: '#D8EBDD', 200: '#B3D6BD', 300: '#86BC96', 400: '#4E9A66', 500: '#2F7D4B', 600: '#256B3F', 700: '#1D5532', 800: '#164126', 900: '#0F2D1B' };
        var red = { 50: '#FBF0EE', 100: '#F6DDD9', 200: '#EDBBB3', 300: '#E0917F', 400: '#CC6450', 500: '#B42318', 600: '#9C1C13', 700: '#7F170F', 800: '#5F110B', 900: '#400B07' };
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Fraunces', 'Georgia', 'serif'],
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        body: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                    },
                    colors: {
                        navy: navy, gold: gold, slate: stone, gray: stone, zinc: stone,
                        purple: navy, violet: navy, blue: navy, indigo: navy, cyan: navy, sky: navy, teal: navy,
                        amber: gold, yellow: gold, emerald: green, green: green, rose: red, red: red,
                    },
                }
            }
        };
    })();
</script>
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
        --danger: #B42318;
        --ok: #256B3F;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        background: var(--bg) !important;
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3, h4, .font-display {
        font-family: 'Fraunces', Georgia, serif !important;
        font-weight: 600;
        letter-spacing: -0.01em;
    }
    h5, h6, p.font-display, span.font-display, label.font-display,
    .font-display.uppercase, .font-display[class*="tracking-"] { font-family: 'Inter', sans-serif !important; font-weight: 600; letter-spacing: normal; }
    .font-display.uppercase { letter-spacing: .06em; }
    code, pre, .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace !important; }

    :focus-visible { outline: 2px solid var(--navy); outline-offset: 2px; }

    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 8px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted); }

    /* Flatten leftover decoration from earlier markup */
    [class*="bg-gradient-to"] { background-image: none !important; }
    [class*="bg-gradient-to"][class*="from-navy"], [class*="bg-gradient-to"][class*="from-purple"],
    [class*="bg-gradient-to"][class*="from-amber"] { background-color: var(--navy); }
    .bg-clip-text { background: none !important; -webkit-text-fill-color: currentColor !important; color: var(--navy) !important; }
    .backdrop-blur, .backdrop-blur-sm, .backdrop-blur-md, .backdrop-blur-xl { backdrop-filter: none !important; }
    .rounded-2xl { border-radius: 10px !important; }
    .rounded-xl { border-radius: 8px !important; }
    .rounded-3xl { border-radius: 12px !important; }
    .shadow-lg, .shadow-xl, .shadow-2xl { box-shadow: 0 1px 2px rgba(23,21,15,.06) !important; }

    /* Surfaces */
    .glass-card, .glass-card-elevated {
        background: var(--surface) !important;
        border: 1px solid var(--border) !important;
        box-shadow: none !important;
        color: var(--ink) !important;
    }
    .card-hoverable { transition: border-color .15s ease; }
    .card-hoverable:hover { border-color: var(--border-strong) !important; transform: none; }

    #sidebar { background: var(--surface) !important; border-right: 1px solid var(--border) !important; color: var(--ink) !important; }
    .sidebar-active {
        background: var(--gold-tint) !important;
        border-left: 0 !important;
        box-shadow: inset 2px 0 0 var(--gold);
        color: var(--navy) !important;
        font-weight: 600 !important;
    }
    #topbar { background: var(--bg) !important; border-bottom: 1px solid var(--border) !important; color: var(--ink) !important; }

    /* Forms */
    input[type="text"], input[type="email"], input[type="password"], input[type="number"],
    input[type="date"], input[type="datetime-local"], input[type="time"], input[type="search"], input[type="tel"], input[type="url"],
    select, textarea {
        background: #fff !important;
        border: 1px solid var(--border-strong) !important;
        color: var(--ink) !important;
        border-radius: 7px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    input::placeholder, textarea::placeholder { color: var(--muted) !important; }
    input:focus, select:focus, textarea:focus {
        border-color: var(--navy) !important;
        box-shadow: 0 0 0 3px rgba(23,38,74,.12) !important;
        outline: none !important;
    }

    /* Tables */
    table th {
        font-family: 'Inter', sans-serif !important;
        font-weight: 600 !important;
        font-size: 11.5px;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--muted) !important;
        border-bottom: 1px solid var(--border) !important;
    }
    table tbody tr { border-bottom: 1px solid #EFEBE1 !important; color: var(--ink) !important; }
    table tbody tr:nth-child(even) { background: transparent !important; }
    table tbody tr:hover { background: #F7F5EF !important; }

    /* Buttons (class names kept so existing markup keeps working) */
    .btn-neon, .btn-glass {
        font-family: 'Inter', sans-serif;
        font-weight: 600;
        border-radius: 7px;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }
    .btn-neon { background: var(--navy); color: #fff !important; border: 1px solid var(--navy); }
    .btn-neon:hover { background: var(--navy-soft); box-shadow: none; transform: none; }
    .btn-neon:active { background: var(--navy); }
    .btn-glass { background: #fff; border: 1px solid var(--border-strong); color: var(--ink) !important; }
    .btn-glass:hover { border-color: var(--navy); color: var(--navy) !important; background: #fff; }

    /* Status badges: tinted fill + dark text, no heavy borders */
    .badge-success { background: #E4F1E8 !important; color: #1D5532 !important; border: 1px solid #B3D6BD !important; font-weight: 600 !important; }
    .badge-warning { background: var(--gold-tint) !important; color: #664F1D !important; border: 1px solid #E8D8AE !important; font-weight: 600 !important; }
    .badge-danger  { background: #FBECE9 !important; color: #7F170F !important; border: 1px solid #EDBBB3 !important; font-weight: 600 !important; }
    .badge-info    { background: #E9ECF4 !important; color: var(--navy) !important; border: 1px solid #C3CADD !important; font-weight: 600 !important; }

    .dropdown-panel {
        background: #fff !important;
        border: 1px solid var(--border-strong) !important;
        box-shadow: 0 10px 30px -12px rgba(23,21,15,.25) !important;
        color: var(--ink) !important;
    }

    /* Respect reduced-motion; no decorative page animation */
    @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
