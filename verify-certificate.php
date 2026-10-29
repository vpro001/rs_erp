<?php
// verify-certificate.php / verify_certificate.php
// Public Certificate Verification Endpoint for Royal Solutions ERP

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db_connection.php';

$cert_query = sanitize($_GET['id'] ?? $_GET['cert_id'] ?? $_GET['code'] ?? '');
$certificate = null;
$error = null;

if (!empty($cert_query)) {
    try {
        $stmt = $conn->prepare("SELECT c.*, s.student_id as student_reg_no, u.first_name, u.last_name, co.title as course_title, co.duration, co.level 
                                FROM certificates c 
                                JOIN students s ON c.student_id = s.id 
                                JOIN users_student u ON s.user_id = u.id 
                                JOIN courses co ON c.course_id = co.id 
                                WHERE c.certificate_id = ? OR c.qr_code = ? OR c.id = ?");
        $stmt->bind_param("sss", $cert_query, $cert_query, $cert_query);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $certificate = $row;
        } else {
            $error = "Certificate with identifier '" . htmlspecialchars($cert_query) . "' was not found in our registry.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "An error occurred while verifying the certificate.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - Royal Solutions Academy</title>

    <?php include __DIR__ . '/includes/theme.php'; ?>
</head>

<body class="text-slate-800 min-h-screen flex flex-col justify-between antialiased">

    <!-- Header Navbar -->
    <header class="border-b border-slate-200 bg-white sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="<?php echo BASE_URL; ?>" class="flex items-center space-x-3 group">
                <div class="w-9 h-9 rounded-[7px] border border-[#17264A] flex items-center justify-center font-display font-semibold text-[15px] text-[#17264A]">R</div>
                <div>
                    <span
                        class="font-display font-semibold text-[15px] text-slate-900 block leading-tight">Royal Solutions</span>
                    <span class="text-[11px] text-slate-500">Academic registry</span>
                </div>
            </a>

            <div class="flex items-center space-x-4">
                <a href="<?php echo BASE_URL; ?>/login.php"
                    class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-800 text-xs font-semibold rounded-xl transition-all border border-slate-300 hover:border-slate-400">
                    Sign In to Portal
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-4xl mx-auto w-full px-6 py-12 flex flex-col items-center justify-center">

        <!-- Search Bar Header -->
        <div class="text-center max-w-xl mb-8">
            <h1 class="text-2xl md:text-3xl font-display font-semibold text-slate-900 mb-2">
                Official Credential Verification
            </h1>
            <p class="text-xs text-slate-500 leading-relaxed">
                Confirm the authenticity and completion record of graduates from Royal Solutions Academy.
            </p>

            <!-- Lookup Form -->
            <form action="verify-certificate.php" method="GET" class="mt-6 flex items-center max-w-md mx-auto relative">
                <input type="text" name="id" value="<?php echo htmlspecialchars($cert_query); ?>"
                    placeholder="Enter Certificate ID or QR Serial..." required
                    class="w-full pl-10 pr-24 py-3 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                <button type="submit"
                    class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-[#17264A] text-white font-semibold text-sm rounded-md hover:bg-[#26355C] transition-colors">
                    Verify
                </button>
            </form>
        </div>

        <?php if ($certificate): ?>
            <!-- Verified Card Badge -->
            <div
                class="w-full bg-white border border-[#D3CCBC] rounded-xl p-8 md:p-12 relative overflow-hidden">
                <!-- Background Decorative Rings -->

                <!-- Status Banner -->
                <div class="flex items-center justify-between pb-6 border-b border-slate-200">
                    <div class="flex items-center space-x-2 text-emerald-600">
                        <div
                            class="w-7 h-7 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider block">Official Credential
                                Verified</span>
                            <span class="text-[10px] text-slate-500">Issued by Royal Solutions Registry</span>
                        </div>
                    </div>

                    <span
                        class="font-mono text-xs text-amber-700 font-bold bg-amber-50 px-3 py-1 rounded-full border border-amber-200">
                        <?php echo htmlspecialchars($certificate['certificate_id']); ?>
                    </span>
                </div>

                <!-- Credential Body -->
                <div class="text-center py-8 space-y-4">
                    <span class="text-[11px] font-bold text-amber-700 uppercase tracking-[0.12em] block">Certificate of
                        Completion</span>
                    <h2
                        class="text-2xl md:text-3xl font-semibold text-[#17264A] font-display">
                        Royal Solutions Academy
                    </h2>

                    <div class="w-16 h-px bg-[#9A7A2E] mx-auto"></div>

                    <p class="text-xs text-slate-500">This certifies that</p>

                    <h3 class="text-xl md:text-2xl font-bold text-slate-900 tracking-wide">
                        <?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?>
                    </h3>

                    <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                        has successfully completed all lectures, assignments, and curriculum criteria for:
                    </p>

                    <div class="inline-block bg-slate-100 border border-slate-200 px-6 py-3 rounded-2xl shadow-sm">
                        <h4 class="text-base font-bold text-slate-900 font-display">
                            <?php echo htmlspecialchars($certificate['course_title']); ?>
                        </h4>
                        <span class="text-[10px] text-slate-500 mt-0.5 block">
                            Duration: <?php echo htmlspecialchars($certificate['duration']); ?> &bull; Level:
                            <?php echo htmlspecialchars($certificate['level']); ?>
                        </span>
                    </div>
                </div>

                <!-- Credential Verification Metadata Footer -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-6 border-t border-slate-200 text-xs">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <span class="text-[10px] text-slate-500 uppercase tracking-wider block mb-1">Student Reg ID</span>
                        <span
                            class="font-mono text-slate-800 font-bold"><?php echo htmlspecialchars($certificate['student_reg_no']); ?></span>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <span class="text-[10px] text-slate-500 uppercase tracking-wider block mb-1">Issue Date</span>
                        <span
                            class="text-slate-800 font-bold"><?php echo date('F d, Y', strtotime($certificate['issue_date'])); ?></span>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <span class="text-[10px] text-slate-500 uppercase tracking-wider block mb-1">Digital
                            Signature</span>
                        <span class="font-mono text-[9px] text-slate-500 truncate block"
                            title="<?php echo htmlspecialchars($certificate['digital_signature']); ?>">
                            <?php echo htmlspecialchars(substr($certificate['digital_signature'], 0, 24)); ?>...
                        </span>
                    </div>
                </div>

            </div>
        <?php elseif ($error): ?>
            <!-- Error / Not Found Banner -->
            <div class="w-full max-w-lg bg-red-500/10 border border-red-500/30 rounded-2xl p-6 text-center space-y-3">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-red-600">Certificate Not Found</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    <?php echo $error; ?>
                </p>
                <div class="pt-2">
                    <a href="verify-certificate.php" class="text-xs text-amber-700 hover:underline">Clear search and try
                        again</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Initial Search Prompt -->
            <div class="text-center py-12 text-slate-500 space-y-2">
                <div
                    class="w-14 h-14 rounded-2xl bg-white border border-slate-200 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="qr-code" class="w-7 h-7 opacity-60"></i>
                </div>
                <h4 class="text-sm font-semibold text-slate-700">Enter a Certificate ID to Verify</h4>
                <p class="text-xs max-w-sm mx-auto">Sample valid demo certificate ID: <code
                        class="bg-slate-100 px-2 py-1 rounded text-amber-700 font-mono">CERT-DEMO-001</code></p>
            </div>
        <?php endif; ?>

    </main>

    <!-- Public Footer -->
    <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
        <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-4">
            <span>&copy; <?php echo date('Y'); ?> Royal Solutions ERP &bull; Academic Registrar</span>
            <div class="flex items-center space-x-4">
                <a href="<?php echo BASE_URL; ?>" class="hover:text-slate-900">Home</a>
                <a href="<?php echo BASE_URL; ?>/login.php" class="hover:text-slate-900">Login</a>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>