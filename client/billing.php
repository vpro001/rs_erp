<?php
// client/billing.php - invoices (deposit / milestone / final) and payment history.
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Billing & Payments";

// Anything past its due date is flagged overdue (still payable)
$stmt = $conn->prepare("UPDATE payments SET status = 'OVERDUE' WHERE client_id = ? AND status = 'PENDING' AND due_date IS NOT NULL AND due_date < NOW()");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$stmt->close();

$due = [];
$history = [];
$stmt = $conn->prepare("SELECT * FROM payments WHERE client_id = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (in_array($row['status'], ['PENDING', 'OVERDUE'], true)) {
        $due[] = $row;
    } else {
        $history[] = $row;
    }
}
$stmt->close();

$total_due = array_sum(array_column($due, 'amount'));
$total_paid = 0.0;
foreach ($history as $h) {
    if ($h['status'] === 'COMPLETED') {
        $total_paid += floatval($h['amount']);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Outstanding</span>
        <h3 class="text-3xl font-bold <?php echo $total_due > 0 ? 'text-[#B42318]' : 'text-slate-900'; ?> mt-1"><?php echo format_currency($total_due); ?></h3>
    </div>
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Total paid</span>
        <h3 class="text-3xl font-bold text-emerald-600 mt-1"><?php echo format_currency($total_paid); ?></h3>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8">
    <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center space-x-2">
        <i data-lucide="wallet" class="w-4 h-4 text-[#B42318]"></i><span>Invoices to pay</span>
    </h4>
    <?php if (empty($due)): ?>
        <p class="text-xs text-slate-400 text-center py-6">You're all paid up.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($due as $d): ?>
                <div class="p-4 border border-slate-100 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-[9px] font-mono bg-slate-100 px-2 py-0.5 rounded uppercase"><?php echo sanitize($d['type']); ?></span>
                        <h5 class="text-xs font-bold text-slate-800 mt-1"><?php echo sanitize($d['description']); ?></h5>
                        <p class="text-[10px] mt-1 <?php echo $d['status'] === 'OVERDUE' ? 'text-[#B42318] font-semibold' : 'text-slate-400'; ?>">
                            <?php echo sanitize($d['transaction_id']); ?>
                            <?php if ($d['due_date']): ?> &bull; <?php echo $d['status'] === 'OVERDUE' ? 'Overdue since' : 'Due'; ?> <?php echo date('M d, Y', strtotime($d['due_date'])); ?><?php endif; ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-base font-bold text-slate-900"><?php echo format_currency($d['amount']); ?></span>
                        <button type="button" onclick="openCheckout('<?php echo $d['id']; ?>', <?php echo json_encode(floatval($d['amount'])); ?>, <?php echo htmlspecialchars(json_encode($d['description']), ENT_QUOTES); ?>)"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-sm">Pay now</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
    <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center space-x-2">
        <i data-lucide="receipt" class="w-4 h-4 text-slate-500"></i><span>Payment history</span>
    </h4>
    <?php if (empty($history)): ?>
        <p class="text-xs text-slate-400 text-center py-6">No payments yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="text-[10px] uppercase text-slate-400">
                    <tr><th class="py-2">Date</th><th>Description</th><th>Reference</th><th>Status</th><th class="text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="py-2.5 text-slate-500"><?php echo date('M d, Y', strtotime($h['paid_at'] ?: $h['created_at'])); ?></td>
                            <td class="text-slate-800 font-medium"><?php echo sanitize($h['description']); ?></td>
                            <td class="font-mono text-slate-400"><?php echo sanitize($h['paystack_reference'] ?: $h['transaction_id']); ?></td>
                            <td class="<?php echo $h['status'] === 'COMPLETED' ? 'text-emerald-600' : 'text-slate-500'; ?> font-semibold"><?php echo sanitize($h['status']); ?></td>
                            <td class="text-right font-bold"><?php echo format_currency($h['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Checkout (simulated Paystack, same as the student checkout) -->
<div id="checkout-modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
            <span class="text-sm font-bold text-slate-900 tracking-wider">paystack</span>
            <button type="button" onclick="closeCheckout()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <p id="checkout-desc" class="text-xs text-slate-500"></p>
        <div>
            <span class="text-[9px] text-slate-400 uppercase font-bold block">Amount</span>
            <span id="checkout-amount" class="text-2xl font-bold text-slate-900"></span>
        </div>
        <button id="pay-submit-btn" type="button" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm">Authorize payment</button>
        <p class="text-[9px] text-slate-400 text-center">Secured by Paystack simulator framework integration.</p>
    </div>
</div>

<script>
    let activePaymentId = null;
    function openCheckout(id, amount, desc) {
        activePaymentId = id;
        document.getElementById('checkout-amount').textContent = '₦' + amount.toLocaleString('en-US', { minimumFractionDigits: 2 });
        document.getElementById('checkout-desc').textContent = desc;
        document.getElementById('checkout-modal').classList.remove('hidden');
    }
    function closeCheckout() { document.getElementById('checkout-modal').classList.add('hidden'); }

    document.getElementById('pay-submit-btn').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Authorizing transaction...';
        fetch((window.BASE_URL || '') + '/api/client_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: activePaymentId, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = 'Payment completed!';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert(data.error || 'Payment failed.');
                    btn.disabled = false;
                    btn.textContent = 'Authorize payment';
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Authorize payment';
            });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
