<?php
// student/results.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "My Academic Results & Certificates";

// Get student ID
$student_id = null;
try {
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $student_id = $row['id'];
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch Completed Exams/Results
$results = [];
try {
    $stmt = $conn->prepare("SELECT r.*, c.title as course_title FROM results r 
                            JOIN courses c ON r.course_id = c.id 
                            WHERE r.student_id = ? 
                            ORDER BY r.exam_date DESC");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch Awarded Certificates
$certificates = [];
try {
    $stmt = $conn->prepare("SELECT cert.*, c.title as course_title 
                            FROM certificates cert 
                            JOIN courses c ON cert.course_id = c.id 
                            WHERE cert.student_id = ? AND cert.is_valid = 1");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $certificates[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace: Exam Results List -->
    <div class="lg:col-span-2 space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="award" class="w-4 h-4 text-blue-500"></i>
                <span>Completed Exam Results</span>
            </h4>

            <?php if (empty($results)): ?>
                <div class="text-center py-12 text-slate-550 ">
                    <i data-lucide="award" class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-400"></i>
                    No exam results logged yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200  text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="pb-3">Course / Exam Type</th>
                                <th class="pb-3">Date Taken</th>
                                <th class="pb-3">Score Achieved</th>
                                <th class="pb-3 text-right">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100  text-xs">
                            <?php foreach ($results as $res): ?>
                                <tr>
                                    <td class="py-4">
                                        <span
                                            class="font-bold text-slate-905  block text-sm"><?php echo sanitize($res['exam_type']); ?></span>
                                        <span
                                            class="text-[10px] text-slate-400 mt-0.5 block"><?php echo sanitize($res['course_title']); ?></span>
                                    </td>
                                    <td class="py-4 text-slate-500 ">
                                        <?php echo date('M d, Y', strtotime($res['exam_date'])); ?>
                                    </td>
                                    <td class="py-4">
                                        <span
                                            class="font-semibold text-slate-800 "><?php echo $res['score']; ?></span>
                                        <span class="text-slate-400">/ <?php echo $res['max_score']; ?></span>
                                        <span
                                            class="text-[10px] text-slate-400 ml-1">(<?php echo round(($res['score'] / $res['max_score']) * 100); ?>%)</span>
                                    </td>
                                    <td class="py-4 text-right">
                                        <span
                                            class="font-bold text-sm text-blue-600 "><?php echo sanitize($res['grade']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Workspace: Verifiable Certificates -->
    <div class="space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
            <h4
                class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="award" class="w-4 h-4 text-indigo-500"></i>
                <span>Verifiable Certificates</span>
            </h4>

            <p class="text-xs text-slate-500 ">
                Certificates are dynamically awarded upon 100% course completions.
            </p>

            <?php if (empty($certificates)): ?>
                <div
                    class="text-center py-6 border border-dashed border-slate-200  rounded-xl text-xs text-slate-400">
                    No certificates earned yet. Complete all lessons of your course to unlock.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($certificates as $cert): ?>
                        <div
                            class="p-3 bg-gradient-to-tr from-amber-500/5 to-yellow-500/10 border border-amber-500/20 rounded-xl flex items-center justify-between">
                            <div>
                                <h5 class="text-xs font-bold text-slate-900 ">
                                    <?php echo sanitize($cert['course_title']); ?></h5>
                                <span class="text-[9px] text-slate-450 uppercase font-mono tracking-wider block mt-1">ID:
                                    <?php echo sanitize($cert['certificate_id']); ?></span>
                            </div>

                            <button
                                class="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-500 hover:to-yellow-500 text-white rounded font-bold text-[10px] transition-all"
                                onclick='showCertificate(<?php echo json_encode($cert); ?>, "<?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?>")'>
                                View
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Certificate Visual Modal (Outfit + Fira Code) -->
<div id="certificate-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div
        class="bg-white  border-4 border-amber-500/30 w-full max-w-2xl rounded-2xl p-8 shadow-2xl space-y-6 m-4 relative overflow-hidden text-center">

        <!-- Gold Seal Design background watermark -->
        <div class="absolute -right-16 -top-16 w-48 h-48 rounded-full bg-yellow-500/5 border border-yellow-500/10">
        </div>
        <div class="absolute -left-16 -bottom-16 w-48 h-48 rounded-full bg-amber-500/5 border border-amber-500/10">
        </div>

        <div class="flex justify-end relative z-10 -mt-4 -mr-4">
            <button class="text-slate-400 hover:text-slate-600 " onclick="closeCertificate()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Certificate Layout -->
        <div class="space-y-4 relative z-10">
            <span class="text-[10px] font-bold text-amber-655 uppercase tracking-[0.2em] block">Certificate of
                Excellence</span>

            <h2
                class="text-2xl font-bold bg-gradient-to-r from-amber-600 to-yellow-500 bg-clip-text text-transparent italic">
                Royal Solutions Academy</h2>

            <div class="w-24 h-0.5 bg-gradient-to-r from-amber-500 to-yellow-500 mx-auto"></div>

            <p class="text-xs text-slate-400">This is proudly presented to</p>

            <h3 id="cert-student-name" class="text-xl font-bold text-slate-900  tracking-wide">Jane Smith
            </h3>

            <p class="text-xs text-slate-400 w-96 mx-auto leading-relaxed">
                For successfully fulfilling the academic requirements and completing the required lecture lessons for
                the curriculum course track of:
            </p>

            <h4 id="cert-course-title"
                class="text-base font-bold text-slate-950  bg-slate-50  py-2.5 px-6 rounded-xl inline-block">
                Fullstack Web Development with PHP & MySQL</h4>

            <div class="grid grid-cols-2 gap-6 pt-6 items-end text-left max-w-lg mx-auto">

                <!-- Signature -->
                <div class="space-y-2">
                    <span class="font-mono text-[9px] text-slate-400 font-semibold block truncate">HASH: <code
                            id="cert-signature"
                            class="bg-slate-100  px-1 py-0.5 rounded text-[8px]">DIGITAL_SIGNATURE</code></span>
                    <div class="border-t border-slate-200  pt-1 text-center">
                        <span class="text-[10px] text-slate-500  font-bold block">Royal
                            Registrar</span>
                        <span class="text-[8px] text-slate-400">Authorized Digital Issuance</span>
                    </div>
                </div>

                <!-- QR Code Verification link -->
                <div class="flex items-center space-x-3 justify-end">
                    <a id="cert-verify-link" href="#" target="_blank"
                        class="flex items-center space-x-3 group hover:opacity-80 transition-opacity"
                        title="Click to verify online">
                        <div
                            class="bg-white p-2 border border-slate-200 rounded-lg group-hover:border-amber-500 transition-colors">
                            <!-- Standard SVG placeholder for QR Code -->
                            <svg class="w-14 h-14 text-slate-900" viewBox="0 0 100 100">
                                <rect x="0" y="0" width="20" height="20" fill="currentColor" />
                                <rect x="0" y="80" width="20" height="20" fill="currentColor" />
                                <rect x="80" y="0" width="20" height="20" fill="currentColor" />
                                <rect x="30" y="30" width="10" height="10" fill="currentColor" />
                                <rect x="60" y="30" width="10" height="40" fill="currentColor" />
                                <rect x="30" y="60" width="20" height="10" fill="currentColor" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[9px] text-slate-500  font-bold block">Verifiable
                                ID</span>
                            <span id="cert-serial-id"
                                class="text-[8px] text-amber-500  font-mono font-bold group-hover:underline">CERT-SERIAL</span>
                            <span class="text-[7px] text-slate-400 block mt-0.5">Click to Verify</span>
                        </div>
                    </a>
                </div>

            </div>

            <p class="text-[9px] text-slate-400 pt-6">Issued on <span id="cert-date">Aug 11, 2026</span> &bull; Valid &
                Verifiable</p>
        </div>

    </div>
</div>

<script>
    function showCertificate(cert, studentName) {
        document.getElementById('cert-student-name').textContent = studentName;
        document.getElementById('cert-course-title').textContent = cert.course_title;
        document.getElementById('cert-signature').textContent = cert.digital_signature.substr(0, 20) + '...';
        document.getElementById('cert-serial-id').textContent = cert.certificate_id;

        const verifyLink = document.getElementById('cert-verify-link');
        if (verifyLink) {
            verifyLink.href = (window.BASE_URL || '') + '/verify-certificate.php?id=' + encodeURIComponent(cert.certificate_id);
        }

        const issueDate = new Date(cert.issue_date);
        document.getElementById('cert-date').textContent = issueDate.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });

        document.getElementById('certificate-modal').classList.remove('hidden');
    }

    function closeCertificate() {
        document.getElementById('certificate-modal').classList.add('hidden');
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>