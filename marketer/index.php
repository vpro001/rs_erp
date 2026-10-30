<?php
// marketer/index.php - referral link, earnings at a glance, and the clients this marketer brought in.
require_once __DIR__ . '/../includes/marketer_context.php';

$page_title = "Marketer Dashboard";

$referral_link = marketer_referral_link($marketer['referral_code']);

// Where each referred client is in the request -> quote -> kickoff funnel
$stmt = $conn->prepare("SELECT cl.id, cl.company_name, cl.contact_person, u.created_at AS joined_at,
                               (SELECT p.status FROM proposals p WHERE p.client_id = cl.id ORDER BY p.created_at DESC LIMIT 1) AS latest_proposal,
                               (SELECT COUNT(*) FROM projects pr WHERE pr.client_id = cl.id) AS projects,
                               (SELECT COALESCE(SUM(mc.amount), 0) FROM marketer_commissions mc JOIN projects pr ON pr.id = mc.project_id
                                 WHERE pr.client_id = cl.id AND mc.marketer_id = ? AND mc.status <> 'CANCELLED') AS earned
                        FROM clients cl JOIN users_client u ON u.id = cl.user_id
                        WHERE cl.referred_by = ? ORDER BY u.created_at DESC");
$stmt->bind_param("ss", $marketer_id, $marketer_id);
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT
                            COALESCE(SUM(CASE WHEN status = 'EARNED' THEN amount END), 0) AS unpaid,
                            COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount END), 0) AS paid
                        FROM marketer_commissions WHERE marketer_id = ?");
$stmt->bind_param("s", $marketer_id);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT status, COUNT(*) AS n FROM marketer_student_referrals WHERE marketer_id = ? GROUP BY status");
$stmt->bind_param("s", $marketer_id);
$stmt->execute();
$student_counts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'n', 'status');
$stmt->close();

$projects_started = array_sum(array_column($clients, 'projects'));

function client_stage($c)
{
    if ($c['projects'] > 0) {
        return ['Project started', 'badge-success'];
    }
    return match ($c['latest_proposal']) {
        'DRAFT' => ['Request being quoted', 'badge-info'],
        'PENDING' => ['Quote sent', 'badge-warning'],
        'APPROVED' => ['Accepted - awaiting kickoff', 'badge-warning'],
        'REJECTED', 'EXPIRED' => ['Quote not accepted', 'badge-info'],
        default => ['Signed up', 'badge-info'],
    };
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Referral link -->
<div class="glass-card rounded-2xl p-6 mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-bold text-slate-900">Welcome, <?php echo sanitize($user['first_name']); ?>. Share your link to bring in clients.</h3>
            <p class="text-xs text-slate-500 mt-0.5">
                Clients who register through this link are credited to you. You earn <b><?php echo rtrim(rtrim($marketer['project_commission_rate'], '0'), '.'); ?>%</b> of each project's value when it kicks off,
                and a commission on every student referral the admin confirms.
            </p>
        </div>
        <span class="text-[10px] font-mono font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 whitespace-nowrap">CODE: <?php echo sanitize($marketer['referral_code']); ?></span>
    </div>
    <div class="mt-4 flex flex-col sm:flex-row gap-2">
        <input type="text" id="ref-link" readonly value="<?php echo sanitize($referral_link); ?>"
            class="flex-1 min-w-0 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono outline-none">
        <button type="button" id="copy-link" class="px-5 py-2.5 bg-[#17264A] hover:opacity-90 text-white font-bold rounded-xl text-xs whitespace-nowrap flex items-center justify-center gap-1.5">
            <i data-lucide="copy" class="w-3.5 h-3.5"></i><span>Copy link</span>
        </button>
    </div>
    <p class="text-[11px] text-slate-400 mt-2">
        We offer: <?php echo implode(' &bull; ', array_column(array_slice(SERVICE_CATALOG, 0, -1), 'label')); ?>.
    </p>
</div>

<!-- Metrics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="glass-card p-6 rounded-2xl">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Clients referred</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo count($clients); ?></h3>
        <p class="text-[11px] text-slate-400 mt-1"><?php echo $projects_started; ?> project(s) kicked off</p>
    </div>
    <a href="students.php" class="glass-card p-6 rounded-2xl block">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Students confirmed</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo intval($student_counts['CONFIRMED'] ?? 0); ?></h3>
        <p class="text-[11px] text-slate-400 mt-1"><?php echo intval($student_counts['PENDING'] ?? 0); ?> awaiting confirmation</p>
    </a>
    <a href="commissions.php" class="glass-card p-6 rounded-2xl block">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Earned, not yet paid</span>
        <h3 class="text-3xl font-bold text-emerald-600 mt-1"><?php echo format_currency($earnings['unpaid']); ?></h3>
    </a>
    <a href="commissions.php" class="glass-card p-6 rounded-2xl block">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.12em]">Paid out</span>
        <h3 class="text-3xl font-bold text-slate-900 mt-1"><?php echo format_currency($earnings['paid']); ?></h3>
    </a>
</div>

<!-- Referred clients -->
<div class="glass-card p-6 rounded-2xl">
    <h4 class="text-base font-bold text-slate-800 mb-4 flex items-center space-x-2">
        <i data-lucide="briefcase" class="w-5 h-5 text-cyan-600"></i><span>Clients you referred</span>
    </h4>
    <?php if (empty($clients)): ?>
        <div class="text-center py-8 text-sm text-slate-400">No clients yet. Share your link above - anyone who registers through it shows up here.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="py-2 pr-4 font-semibold">Client</th>
                        <th class="py-2 pr-4 font-semibold">Joined</th>
                        <th class="py-2 pr-4 font-semibold">Stage</th>
                        <th class="py-2 font-semibold text-right">Your commission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c):
                        [$stage, $badge] = client_stage($c); ?>
                        <tr class="border-b border-slate-50">
                            <td class="py-3 pr-4">
                                <span class="font-bold text-slate-800 block"><?php echo sanitize($c['company_name'] ?: $c['contact_person']); ?></span>
                                <span class="text-[11px] text-slate-400"><?php echo sanitize($c['contact_person']); ?></span>
                            </td>
                            <td class="py-3 pr-4 text-slate-500"><?php echo date('M d, Y', strtotime($c['joined_at'])); ?></td>
                            <td class="py-3 pr-4"><span class="text-[9px] font-mono font-bold px-2.5 py-1 rounded-full uppercase <?php echo $badge; ?>"><?php echo $stage; ?></span></td>
                            <td class="py-3 text-right font-bold <?php echo $c['earned'] > 0 ? 'text-emerald-600' : 'text-slate-400'; ?>"><?php echo format_currency($c['earned']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('copy-link').addEventListener('click', async (e) => {
        const input = document.getElementById('ref-link');
        try {
            await navigator.clipboard.writeText(input.value);
        } catch (err) {
            input.select();
            document.execCommand('copy');
        }
        e.currentTarget.querySelector('span').textContent = 'Copied';
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
