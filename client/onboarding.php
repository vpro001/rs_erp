<?php
// client/onboarding.php - "Request a Project": the client describes what they need built,
// gets an instant estimate, and a DRAFT proposal is opened for staff to review and quote.
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Request a Project";

$platforms = [
    'web' => ['label' => 'Web application', 'fee' => 1500],
    'mobile' => ['label' => 'Mobile app', 'fee' => 2500],
    'hybrid' => ['label' => 'Web + mobile', 'fee' => 3500],
    'desktop' => ['label' => 'Desktop software', 'fee' => 4000],
];
$categories = [
    'ecommerce' => ['label' => 'E-commerce / online store', 'fee' => 1000],
    'saas' => ['label' => 'SaaS product', 'fee' => 2000],
    'erp' => ['label' => 'ERP / business operations system', 'fee' => 3500],
    'lms' => ['label' => 'Learning platform (LMS)', 'fee' => 1500],
    'custom' => ['label' => 'Other custom software', 'fee' => 2500],
];
$features = [
    'user_mgmt' => ['label' => 'User accounts & roles', 'fee' => 400],
    'payments' => ['label' => 'Online payments', 'fee' => 600],
    'realtime' => ['label' => 'Real-time features / chat', 'fee' => 700],
    'analytics' => ['label' => 'Dashboards & reporting', 'fee' => 800],
    'documents' => ['label' => 'Document management', 'fee' => 500],
    'scheduling' => ['label' => 'Booking & scheduling', 'fee' => 450],
];
$timelines = [
    'under_1' => ['label' => 'Under 1 month (rush)', 'mult' => 1.3, 'text' => 'Under 4 weeks'],
    '1-3' => ['label' => '1 - 3 months', 'mult' => 1.0, 'text' => '1-3 Months'],
    '3-6' => ['label' => '3 - 6 months', 'mult' => 0.95, 'text' => '3-6 Months'],
    '6_plus' => ['label' => '6+ months', 'mult' => 0.9, 'text' => '6+ Months'],
];

function estimate_project($platforms, $categories, $features, $timelines, $platform, $category, $picked, $timeline)
{
    $total = ($platforms[$platform]['fee'] ?? 0) + ($categories[$category]['fee'] ?? 0);
    foreach ($picked as $f) {
        $total += $features[$f]['fee'] ?? 0;
    }
    return round($total * ($timelines[$timeline]['mult'] ?? 1.0), 2);
}

$errors = [];
$form = ['project_title' => '', 'description' => '', 'platform' => 'web', 'category' => 'custom', 'timeline' => '1-3', 'budget_limit' => '', 'features' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_request') {
    if (!csrf_ok()) {
        die("CSRF validation failed.");
    }

    $form['project_title'] = trim($_POST['project_title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['platform'] = $_POST['platform'] ?? '';
    $form['category'] = $_POST['category'] ?? '';
    $form['timeline'] = $_POST['timeline'] ?? '';
    $form['budget_limit'] = trim($_POST['budget_limit'] ?? '');
    $form['features'] = array_values(array_intersect((array) ($_POST['features'] ?? []), array_keys($features)));

    if ($form['project_title'] === '' || mb_strlen($form['project_title']) > 190) {
        $errors[] = "Please give your project a short title (up to 190 characters).";
    }
    if (mb_strlen($form['description']) < 20) {
        $errors[] = "Please describe what you want built in at least a couple of sentences so our team can quote accurately.";
    }
    if (!isset($platforms[$form['platform']]) || !isset($categories[$form['category']]) || !isset($timelines[$form['timeline']])) {
        $errors[] = "Please choose a platform, project type and timeline.";
    }
    $budget_limit = $form['budget_limit'] === '' ? 0.0 : floatval($form['budget_limit']);
    if ($budget_limit < 0) {
        $errors[] = "Budget cannot be negative.";
    }

    if (empty($errors)) {
        $est = estimate_project($platforms, $categories, $features, $timelines, $form['platform'], $form['category'], $form['features'], $form['timeline']);

        $service_list = [$platforms[$form['platform']]['label'], $categories[$form['category']]['label']];
        foreach ($form['features'] as $f) {
            $service_list[] = $features[$f]['label'];
        }
        $service_list[] = 'Testing, QA & UAT sign-off';
        $service_list[] = 'Deployment & handover';

        $uuid = generate_uuid();
        $proposal_ref = 'PROP-' . strtoupper(substr(str_replace('-', '', $uuid), 0, 8));
        $services_json = json_encode($service_list);
        $timeline_text = $timelines[$form['timeline']]['text'];
        $valid_until = date('Y-m-d H:i:s', strtotime('+14 days'));
        $category_label = $categories[$form['category']]['label'];

        $questionnaire = [
            'inputs' => $form + ['target_budget' => $budget_limit],
            'estimate' => $est,
        ];
        $questions_json = json_encode(['title' => 'Project request', 'version' => '2.0']);
        $responses_json = json_encode($questionnaire);
        $q_id = generate_uuid();

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO proposals (id, proposal_id, title, client_id, status, amount, category, description, services, timeline, valid_until)
                                    VALUES (?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdsssss", $uuid, $proposal_ref, $form['project_title'], $client_id, $est, $category_label, $form['description'], $services_json, $timeline_text, $valid_until);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO questionnaires (id, client_id, title, questions, responses, completed_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $q_id, $client_id, $form['project_title'], $questions_json, $responses_json);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "We could not save your request: " . $e->getMessage();
        }

        if (empty($errors)) {
            notify_admins(
                "New project request",
                ($client['company_name'] ?: 'A client') . " requested '{$form['project_title']}' (instant estimate " . format_currency($est) . "). Review and send a quote.",
                "/admin/proposals.php"
            );
            log_activity("Submitted project request", ["proposal" => $proposal_ref, "estimate" => $est]);
            set_flash_message('success', "Request received. Our team will review it and send you a formal quote - you'll find it under Proposals.");
            header("Location: " . BASE_URL . "/client/proposals.php");
            exit();
        }
    }
}

// Past requests, with the status of the proposal each one opened
$history = [];
$stmt = $conn->prepare("SELECT proposal_id, title, amount, status, created_at FROM proposals WHERE client_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $history[] = $row;
}
$stmt->close();

$pricing = [
    'platforms' => array_map(fn($v) => $v['fee'], $platforms),
    'categories' => array_map(fn($v) => $v['fee'], $categories),
    'features' => array_map(fn($v) => $v['fee'], $features),
    'timelines' => array_map(fn($v) => $v['mult'], $timelines),
];

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-600 rounded-xl text-sm font-medium">
        <?php foreach ($errors as $err): ?>
            <p><?php echo sanitize($err); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <div class="lg:col-span-2">
        <form method="POST" action="onboarding.php" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="submit_request">

            <div>
                <h4 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <i data-lucide="rocket" class="w-4 h-4 text-blue-500"></i>
                    <span>Tell us what you want built</span>
                </h4>
                <p class="text-xs text-slate-400 mt-1">You'll get an instant ballpark figure. Our team then reviews your request and sends you a formal proposal to accept.</p>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Project title</label>
                <input type="text" name="project_title" required maxlength="190" value="<?php echo sanitize($form['project_title']); ?>"
                    placeholder="e.g. Inventory & sales web app for my stores"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">What should it do?</label>
                <textarea name="description" rows="4" required
                    placeholder="Describe your business, who will use the system, and the problems it should solve."
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500"><?php echo sanitize($form['description']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Platform</label>
                    <select name="platform" id="f-platform" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                        <?php foreach ($platforms as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $form['platform'] === $k ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Project type</label>
                    <select name="category" id="f-category" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                        <?php foreach ($categories as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $form['category'] === $k ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">When do you need it?</label>
                    <select name="timeline" id="f-timeline" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                        <?php foreach ($timelines as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $form['timeline'] === $k ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Your budget (optional, USD)</label>
                    <input type="number" name="budget_limit" min="0" step="50" value="<?php echo sanitize($form['budget_limit']); ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Features you need</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($features as $k => $v): ?>
                        <label class="flex items-center space-x-2.5 p-3 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 cursor-pointer hover:border-indigo-400">
                            <input type="checkbox" name="features[]" value="<?php echo $k; ?>" class="f-feature" <?php echo in_array($k, $form['features'], true) ? 'checked' : ''; ?>>
                            <span><?php echo $v['label']; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                <div>
                    <span class="text-[9px] text-slate-400 uppercase font-bold block">Instant ballpark estimate</span>
                    <span id="est-total" class="text-xl font-bold text-slate-900">$0.00</span>
                    <span class="text-[10px] text-slate-400 block">Final price is confirmed in your proposal.</span>
                </div>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-sm shadow-sm">
                    Request a quote
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm h-fit">
        <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center space-x-2">
            <i data-lucide="history" class="w-4 h-4 text-slate-500"></i>
            <span>Your requests</span>
        </h4>
        <?php if (empty($history)): ?>
            <p class="text-xs text-slate-400 text-center py-4">Nothing requested yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($history as $h): ?>
                    <a href="proposals.php" class="block p-3 border border-slate-100 rounded-xl hover:border-slate-300">
                        <div class="flex justify-between items-start">
                            <h5 class="text-xs font-bold text-slate-800"><?php echo sanitize($h['title']); ?></h5>
                            <span class="text-[9px] font-mono uppercase text-slate-500"><?php echo $h['status'] === 'DRAFT' ? 'In review' : sanitize($h['status']); ?></span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1"><?php echo date('M d, Y', strtotime($h['created_at'])); ?> &bull; <?php echo format_currency($h['amount']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const PRICING = <?php echo json_encode($pricing); ?>;
    function updateEstimate() {
        let total = (PRICING.platforms[document.getElementById('f-platform').value] || 0)
            + (PRICING.categories[document.getElementById('f-category').value] || 0);
        document.querySelectorAll('.f-feature:checked').forEach(c => total += PRICING.features[c.value] || 0);
        total *= PRICING.timelines[document.getElementById('f-timeline').value] || 1;
        document.getElementById('est-total').textContent = '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.querySelectorAll('#f-platform, #f-category, #f-timeline, .f-feature').forEach(el => el.addEventListener('change', updateEstimate));
    updateEstimate();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
