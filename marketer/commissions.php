<?php
// marketer/commissions.php - every commission this marketer has earned and its payout status.
require_once __DIR__ . '/../includes/marketer_context.php';

$page_title = "My Commissions";

$stmt = $conn->prepare("SELECT mc.*, pr.project_id AS project_ref, r.full_name AS student_name
                        FROM marketer_commissions mc
                        LEFT JOIN projects pr ON pr.id = mc.project_id
                        LEFT JOIN marketer_student_referrals r ON r.id = mc.referral_id
                        WHERE mc.marketer_id = ? ORDER BY mc.created_at DESC");
$stmt->bind_param("s", $marketer_id);
$stmt->execute();
$commissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totals = ['EARNED' => 0, 'PAID' => 0];
foreach ($commissions as $c) {
    if (isset($totals[$c['status']])) {
        $totals[$c['status']] += $c['amount'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Earned, not yet paid</span>
        <h3 class="text-3xl font-bold text-emerald-600 mt-1"><?php echo format_currency($totals['EARNED']); ?></h3>
    </div>
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Paid out</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo format_currency($totals['PAID']); ?></h3>
    </div>
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Your rates</span>
        <p class="text-sm font-bold text-slate-900 mt-2"><?php echo rtrim(rtrim($marketer['project_commission_rate'], '0'), '.'); ?>% of project value</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Student referrals: <?php echo rtrim(rtrim($marketer['student_commission_rate'], '0'), '.'); ?>% of course fee, set by the admin on confirmation</p>
    </div>
</div>

<div class="glass-card p-6 rounded-2xl">
    <h4 class="text-base font-bold text-slate-800 mb-4 flex items-center space-x-2">
        <i data-lucide="wallet" class="w-5 h-5 text-cyan-600"></i><span>Commission history</span>
    </h4>
    <?php if (empty($commissions)): ?>
        <div class="text-center py-8 text-sm text-slate-400">
            No commissions yet. You earn one when a client you referred kicks off a project, or when the admin confirms a student you referred.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="py-2 pr-4 font-semibold">Date</th>
                        <th class="py-2 pr-4 font-semibold">For</th>
                        <th class="py-2 pr-4 font-semibold text-right">Based on</th>
                        <th class="py-2 pr-4 font-semibold text-right">Amount</th>
                        <th class="py-2 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commissions as $c):
                        $badge = ['EARNED' => 'badge-warning', 'PAID' => 'badge-success', 'CANCELLED' => 'badge-info'][$c['status']];
                        $label = ['EARNED' => 'Awaiting payout', 'PAID' => 'Paid', 'CANCELLED' => 'Cancelled'][$c['status']]; ?>
                        <tr class="border-b border-slate-50 <?php echo $c['status'] === 'CANCELLED' ? 'opacity-60' : ''; ?>">
                            <td class="py-3 pr-4 text-slate-500 whitespace-nowrap"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex items-center gap-1 font-bold text-slate-800">
                                    <i data-lucide="<?php echo $c['source'] === 'PROJECT' ? 'briefcase' : 'graduation-cap'; ?>" class="w-3 h-3 text-slate-400"></i>
                                    <?php echo sanitize($c['description']); ?>
                                </span>
                                <?php if ($c['project_ref']): ?><span class="block text-[10px] font-mono text-slate-400"><?php echo sanitize($c['project_ref']); ?></span><?php endif; ?>
                            </td>
                            <td class="py-3 pr-4 text-right text-slate-500 whitespace-nowrap">
                                <?php echo $c['base_amount'] > 0 ? format_currency($c['base_amount']) : '-'; ?>
                                <?php if ($c['rate'] !== null): ?><span class="block text-[10px]">@ <?php echo rtrim(rtrim($c['rate'], '0'), '.'); ?>%</span><?php endif; ?>
                            </td>
                            <td class="py-3 pr-4 text-right font-bold text-slate-900 whitespace-nowrap"><?php echo format_currency($c['amount']); ?></td>
                            <td class="py-3">
                                <span class="text-[9px] font-mono font-bold px-2.5 py-1 rounded-full uppercase <?php echo $badge; ?>"><?php echo $label; ?></span>
                                <?php if ($c['status'] === 'PAID'): ?>
                                    <span class="block text-[10px] text-slate-400 mt-1"><?php echo date('M d, Y', strtotime($c['paid_at'])); ?><?php echo $c['payout_ref'] ? ' &bull; ' . sanitize($c['payout_ref']) : ''; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
