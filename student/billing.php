<?php
// student/billing.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Billing & Installments";

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

// Fetch Completed Payments
$payments = [];
try {
    $stmt = $conn->prepare("SELECT p.*, c.title as course_title FROM payments p 
                            LEFT JOIN courses c ON p.course_id = c.id 
                            WHERE p.student_id = ? AND p.status = 'COMPLETED' 
                            ORDER BY p.paid_at DESC");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch Flexible Balances
$installments = [];
try {
    $stmt = $conn->prepare("SELECT e.id as enrollment_id, e.amount_due, e.total_paid, e.enrollment_date, c.title as course_title, c.id as course_id 
                            FROM enrollments e 
                            JOIN courses c ON e.course_id = c.id 
                            WHERE e.student_id = ? AND e.amount_due > 0");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $installments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Billing Layout Workspace -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Panel: Installments Schedule -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Installment plans tracker -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="wallet" class="w-4 h-4 text-blue-500"></i>
                <span>Active Flexible Balances</span>
            </h4>

            <?php if (empty($installments)): ?>
                <div class="text-center py-8 text-sm text-slate-500 ">
                    No active balances. All your enrolled courses are fully paid.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($installments as $inst): ?>
                        <div class="border border-slate-150 p-4 rounded-xl flex items-center justify-between">
                            <div>
                                <span class="text-xs bg-slate-100 px-2 py-0.5 rounded font-semibold text-slate-500 uppercase tracking-wider">
                                    Remaining Balance
                                </span>
                                <h5 class="text-sm font-bold text-slate-900 mt-2">
                                    <?php echo sanitize($inst['course_title']); ?></h5>
                                <div class="flex items-center space-x-3 mt-1.5 text-[10px] text-slate-400">
                                    <span>Enrolled: <?php echo date('M d, Y', strtotime($inst['enrollment_date'])); ?></span>
                                    <span>&bull;</span>
                                    <span>Total Paid: <?php echo format_currency($inst['total_paid']); ?></span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <span class="text-sm font-bold text-amber-600"><?php echo format_currency($inst['amount_due']); ?></span>
                                <button
                                    onclick="openCheckout('<?php echo $inst['enrollment_id']; ?>', '<?php echo $inst['amount_due']; ?>', '<?php echo sanitize(addslashes($inst['course_title'])); ?>', '<?php echo $inst['course_id']; ?>')"
                                    class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-semibold rounded-xl transition-all shadow-md shadow-blue-500/10">
                                    Make Payment
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Panel: Transaction Receipts History -->
    <div class="space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-6 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="history" class="w-4 h-4 text-indigo-500"></i>
                <span>Completed Receipts Logs</span>
            </h4>

            <?php if (empty($payments)): ?>
                <div class="text-center py-6 text-xs text-slate-500 ">
                    No transactions captured.
                </div>
            <?php else: ?>
                <div class="space-y-3.5">
                    <?php foreach ($payments as $pay): ?>
                        <div
                            class="text-xs space-y-1.5 pb-3 border-b border-slate-100  last:border-0 last:pb-0">
                            <div class="flex justify-between items-center">
                                <span
                                    class="font-semibold text-slate-700 "><?php echo sanitize($pay['course_title']); ?></span>
                                <span
                                    class="font-bold text-slate-900 "><?php echo format_currency($pay['amount']); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-[10px] text-slate-400">
                                <span>Ref: <code><?php echo sanitize($pay['transaction_id']); ?></code></span>
                                <span><?php echo date('d M, Y', strtotime($pay['paid_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Paystack Custom Simulation Modal -->
<div id="checkout-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div
        class="bg-white  border border-slate-200  w-full max-w-sm rounded-2xl p-6 shadow-xl space-y-4 m-4 relative overflow-hidden">

        <!-- Paystack Brand Banner Header -->
        <div
            class="flex items-center justify-between border-b border-slate-100  pb-3 bg-slate-50  -mx-6 -mt-6 p-6">
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                <span class="text-sm font-bold text-slate-900  tracking-wider">paystack</span>
                <span
                    class="text-[9px] bg-slate-100  px-1.5 py-0.5 rounded font-mono text-slate-400 uppercase">Simulator</span>
            </div>
            <button class="text-slate-400 hover:text-slate-655" onclick="closeCheckout()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="space-y-4">
            <div class="text-center bg-blue-500/5 p-4 rounded-xl">
                <span class="text-xs text-slate-400 block uppercase tracking-wider font-semibold">Payment Amount</span>
                <div class="mt-2 relative w-1/2 mx-auto">
                    <span class="absolute left-3 top-2 text-slate-500 font-bold">$</span>
                    <input type="number" id="checkout-amount-input" step="0.01" class="w-full pl-7 pr-3 py-1.5 text-center bg-white border border-slate-200 rounded-lg font-bold text-lg text-slate-950 outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <p id="checkout-course-display" class="text-xs text-slate-400 mt-2 truncate"></p>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Card Number</label>
                    <input type="text" placeholder="4012 8821 9002 1102" value="4012 8821 9002 1102"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-1 focus:ring-emerald-500 outline-none text-xs">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Expiry</label>
                        <input type="text" placeholder="12/29" value="12/29"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-1 focus:ring-emerald-500 outline-none text-xs text-center">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">CVV</label>
                        <input type="text" placeholder="123" value="123"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl focus:ring-1 focus:ring-emerald-500 outline-none text-xs text-center">
                    </div>
                </div>
            </div>

            <button id="pay-submit-btn"
                class="w-full py-3 bg-[#3bb75e] hover:bg-[#32a050] text-white font-bold rounded-xl text-xs transition-all shadow-md flex items-center justify-center space-x-2">
                <span>Authorize payment</span>
            </button>
            <p class="text-[9px] text-slate-400 text-center">Secured by Paystack simulator framework integration.</p>
        </div>
    </div>
</div>

<!-- Script to handle custom paystack simulator callbacks -->
<script>
    let activeInstallmentId = null;
    let activeAmount = 0.00;
    let activeCourseId = null;

    function openCheckout(instId, amount, courseTitle, courseId) {
        activeInstallmentId = instId;
        activeAmount = parseFloat(amount);
        activeCourseId = courseId;

        document.getElementById('checkout-amount-input').value = activeAmount.toFixed(2);
        document.getElementById('checkout-course-display').textContent = courseTitle;
        document.getElementById('checkout-modal').classList.remove('hidden');
    }

    function closeCheckout() {
        document.getElementById('checkout-modal').classList.add('hidden');
    }

    const paySubmitBtn = document.getElementById('pay-submit-btn');
    if (paySubmitBtn) {
        paySubmitBtn.addEventListener('click', () => {
            // Disable button during simulated callback processing
            paySubmitBtn.disabled = true;
            paySubmitBtn.innerHTML = `
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                <span>Authorizing transaction...</span>
            `;
            lucide.createIcons();

            // Mock transaction attributes
            const ref = 'PAYSTACK_REF_' + Math.random().toString(36).substr(2, 9).toUpperCase();

            // Send payload to our api/paystack_callback.php endpoint
            const callbackUrl = (window.BASE_URL || '') + '/api/paystack_callback.php';
            fetch(callbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    installment_id: activeInstallmentId,
                    amount: parseFloat(document.getElementById('checkout-amount-input').value),
                    reference: ref,
                    student_id: '<?php echo $student_id; ?>',
                    course_id: activeCourseId
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        paySubmitBtn.innerHTML = `
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Payment Completed!</span>
                    `;
                        lucide.createIcons();
                        setTimeout(() => {
                            closeCheckout();
                            location.reload();
                        }, 1200);
                    } else {
                        alert(data.error || "Payment validation failed.");
                        paySubmitBtn.disabled = false;
                        paySubmitBtn.textContent = "Authorize payment";
                        lucide.createIcons();
                    }
                })
                .catch(err => {
                    console.error("AJAX Callback Error:", err);
                    paySubmitBtn.disabled = false;
                    paySubmitBtn.textContent = "Authorize payment";
                    lucide.createIcons();
                });
        });
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>