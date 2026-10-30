<?php
// client/onboarding.php - "Request a Project": the client describes what they need built,
// and a DRAFT proposal (no price) is opened for staff to assess and quote.
require_once __DIR__ . '/../includes/client_context.php';

$page_title = "Request a Project";

// What we offer (SERVICE_CATALOG). No prices here: every quote is set by our team after reviewing the brief.
$services = array_map(fn($svc) => $svc['label'], SERVICE_CATALOG);
// Services that are software builds get the features / reference-sites questions.
$software_services = ['website', 'web_app', 'mobile_app'];
$website_kinds = ['new' => 'A new website', 'revamp' => 'Revamp my existing website', 'ecommerce' => 'An online store'];
$mobile_platforms = ['android' => 'Android', 'ios' => 'iOS (iPhone)', 'both' => 'Android & iOS'];
$training_formats = ['onsite' => 'At our office', 'centre' => 'At your training centre', 'online' => 'Online', 'flexible' => 'Open to suggestions'];
$premises_types = ['home' => 'Home / residence', 'office' => 'Office', 'shop' => 'Shop / retail', 'warehouse' => 'Warehouse / factory', 'estate' => 'Estate / compound', 'school' => 'School / church', 'other' => 'Other'];
$timelines = [
    'asap' => ['label' => 'As soon as possible', 'text' => 'ASAP'],
    'under_1' => ['label' => 'Within 1 month', 'text' => 'Within 1 month'],
    '1-3' => ['label' => '1 - 3 months', 'text' => '1-3 Months'],
    '3-6' => ['label' => '3 - 6 months', 'text' => '3-6 Months'],
    '6_plus' => ['label' => '6+ months', 'text' => '6+ Months'],
    'flexible' => ['label' => 'Flexible', 'text' => 'Flexible'],
];
$suggestions = ['User accounts & login', 'Online payments', 'Admin dashboard', 'Reports & analytics', 'Contact / booking forms', 'Blog or news section', 'Multi-language', 'SEO setup', 'Hosting & domain', 'Ongoing maintenance'];

$errors = [];
$form = ['project_title' => '', 'service' => $_GET['service'] ?? 'web_app', 'description' => '', 'features' => '', 'current_site' => '', 'references' => '', 'timeline' => 'flexible', 'budget_note' => '',
    'website_kind' => 'new', 'platforms' => 'both',
    'participants' => '', 'training_topics' => '', 'training_format' => 'flexible',
    'site_address' => '', 'premises' => 'office', 'cameras' => '', 'remote_view' => '1'];
if (!isset($services[$form['service']])) {
    $form['service'] = 'web_app';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_request') {
    if (!csrf_ok()) {
        die("CSRF validation failed.");
    }

    foreach ($form as $k => $_) {
        $form[$k] = trim($_POST[$k] ?? '');
    }

    if ($form['project_title'] === '' || mb_strlen($form['project_title']) > 190) {
        $errors[] = "Please give your project a short title (up to 190 characters).";
    }
    if (!isset($services[$form['service']]) || !isset($timelines[$form['timeline']])) {
        $errors[] = "Please choose the kind of project and when you need it.";
    }
    if (mb_strlen($form['description']) < 20) {
        $errors[] = "Please describe what you need in at least a couple of sentences so our team can assess it properly.";
    }
    $svc = $form['service'];
    if ($svc === 'website') {
        if (!isset($website_kinds[$form['website_kind']])) {
            $errors[] = "Please tell us what kind of website you need.";
        } elseif ($form['website_kind'] === 'revamp' && $form['current_site'] === '') {
            $errors[] = "For a website revamp, please share the address of your current website.";
        }
    }
    if ($svc === 'mobile_app' && !isset($mobile_platforms[$form['platforms']])) {
        $errors[] = "Please choose which phones the app should run on.";
    }
    if ($svc === 'corporate_training') {
        if (!ctype_digit($form['participants']) || (int) $form['participants'] < 1) {
            $errors[] = "Please tell us roughly how many staff will attend the training.";
        }
        if ($form['training_topics'] === '') {
            $errors[] = "Please list the topics or skills the training should cover.";
        }
        if (!isset($training_formats[$form['training_format']])) {
            $errors[] = "Please choose where the training should hold.";
        }
    }
    if ($svc === 'cctv') {
        if ($form['site_address'] === '') {
            $errors[] = "Please give the address where the cameras will be installed, so we can plan a site survey.";
        }
        if (!isset($premises_types[$form['premises']])) {
            $errors[] = "Please choose the type of premises.";
        }
        if ($form['cameras'] !== '' && (!ctype_digit($form['cameras']) || (int) $form['cameras'] < 1)) {
            $errors[] = "Number of cameras should be a whole number (or leave it blank if you're not sure).";
        }
    }
    if (mb_strlen($form['description']) > 5000 || mb_strlen($form['features']) > 5000 || mb_strlen($form['references']) > 2000
        || mb_strlen($form['training_topics']) > 2000 || mb_strlen($form['site_address']) > 300) {
        $errors[] = "One of your answers is too long.";
    }

    if (empty($errors)) {
        // Only keep the answers that belong to the chosen service, so the brief staff read is clean
        $keep = ['project_title', 'service', 'description', 'timeline', 'budget_note'];
        $keep = array_merge($keep, match ($svc) {
            'website' => ['website_kind', 'current_site', 'features', 'references'],
            'web_app' => ['features', 'references'],
            'mobile_app' => ['platforms', 'features', 'references'],
            'corporate_training' => ['participants', 'training_topics', 'training_format'],
            'cctv' => ['site_address', 'premises', 'cameras', 'remote_view'],
            default => [],
        });
        if ($svc !== 'website' || $form['website_kind'] !== 'revamp') {
            $keep = array_diff($keep, ['current_site']);
        }
        $brief = array_intersect_key($form, array_flip($keep));

        // What the request covers, as a starting point for the quote's "included services"
        $service_list = [$services[$svc]];
        if ($svc === 'website') {
            $service_list[] = $website_kinds[$form['website_kind']];
        } elseif ($svc === 'mobile_app') {
            $service_list[] = 'Platforms: ' . $mobile_platforms[$form['platforms']];
        } elseif ($svc === 'corporate_training') {
            $service_list[] = "Training for {$form['participants']} participant(s) - " . $training_formats[$form['training_format']];
            $service_list = array_merge($service_list, array_filter(array_map('trim', preg_split('/\r?\n/', $form['training_topics']))));
        } elseif ($svc === 'cctv') {
            $service_list[] = ($form['cameras'] !== '' ? "{$form['cameras']} camera(s)" : 'Camera count to be advised after site survey') . ' - ' . $premises_types[$form['premises']];
            if ($form['remote_view'] === '1') {
                $service_list[] = 'Remote viewing on phone';
            }
        }
        // One feature per line, in the client's own words
        if (in_array($svc, $software_services, true)) {
            $service_list = array_merge($service_list, array_filter(array_map('trim', preg_split('/\r?\n/', $form['features']))));
        }
        $service_list = array_values($service_list);

        $uuid = generate_uuid();
        $proposal_ref = 'PROP-' . strtoupper(substr(str_replace('-', '', $uuid), 0, 8));
        $services_json = json_encode($service_list);
        $timeline_text = $timelines[$form['timeline']]['text'];
        $valid_until = date('Y-m-d H:i:s', strtotime('+14 days'));
        $category_label = $services[$svc];
        $unpriced = 0.00; // DRAFT = awaiting a price from our team

        $responses_json = json_encode(['inputs' => $brief, 'service_label' => $category_label]);
        $questions_json = json_encode(['title' => 'Project request', 'version' => '4.0']);
        $q_id = generate_uuid();

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO proposals (id, proposal_id, title, client_id, status, amount, category, description, services, timeline, valid_until)
                                    VALUES (?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdsssss", $uuid, $proposal_ref, $form['project_title'], $client_id, $unpriced, $category_label, $form['description'], $services_json, $timeline_text, $valid_until);
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
                ($client['company_name'] ?: 'A client') . " requested '{$form['project_title']}' ($category_label). Review the brief and set a price.",
                "/admin/proposals.php"
            );
            log_activity("Submitted project request", ["proposal" => $proposal_ref]);
            set_flash_message('success', "Request received. Our team will review it and send you a quote - you'll find it under Proposals.");
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
                    <span>Tell us what you need</span>
                </h4>
                <p class="text-xs text-slate-400 mt-1">Share what you have in mind. Our team will assess it and send you a priced proposal to accept or discuss.</p>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">What do you need?</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach (SERVICE_CATALOG as $k => $svc): ?>
                        <label class="svc-card flex items-start gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-indigo-400 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/50">
                            <input type="radio" name="service" value="<?php echo $k; ?>" class="mt-1 accent-indigo-600" <?php echo $form['service'] === $k ? 'checked' : ''; ?>>
                            <span>
                                <span class="flex items-center gap-1.5 text-xs font-bold text-slate-800">
                                    <i data-lucide="<?php echo $svc['icon']; ?>" class="w-3.5 h-3.5 text-indigo-500"></i><?php echo $svc['label']; ?>
                                </span>
                                <span class="block text-[11px] text-slate-500 mt-0.5"><?php echo $svc['blurb']; ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Title</label>
                <input type="text" name="project_title" required maxlength="190" value="<?php echo sanitize($form['project_title']); ?>"
                    placeholder="e.g. New website for my law firm"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
            </div>

            <!-- Website Design / Revamp -->
            <div class="svc-box space-y-4" data-for="website">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">What kind of website?</label>
                    <select name="website_kind" id="f-website-kind" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                        <?php foreach ($website_kinds as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $form['website_kind'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="redesign-box">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Your current website</label>
                    <input type="text" name="current_site" value="<?php echo sanitize($form['current_site']); ?>" placeholder="https://www.example.com"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-1">Tell us below what you dislike about it and what you'd like changed.</p>
                </div>
            </div>

            <!-- Mobile App -->
            <div class="svc-box" data-for="mobile_app">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Which phones should it run on?</label>
                <select name="platforms" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                    <?php foreach ($mobile_platforms as $k => $label): ?>
                        <option value="<?php echo $k; ?>" <?php echo $form['platforms'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Corporate Training -->
            <div class="svc-box space-y-4" data-for="corporate_training">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Number of participants</label>
                        <input type="number" name="participants" min="1" value="<?php echo sanitize($form['participants']); ?>" placeholder="e.g. 15"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Where should it hold?</label>
                        <select name="training_format" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                            <?php foreach ($training_formats as $k => $label): ?>
                                <option value="<?php echo $k; ?>" <?php echo $form['training_format'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Topics or skills to cover (one per line)</label>
                    <textarea name="training_topics" rows="3" placeholder="e.g.&#10;Microsoft Excel for reporting&#10;Cybersecurity awareness"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500"><?php echo sanitize($form['training_topics']); ?></textarea>
                </div>
            </div>

            <!-- CCTV Installation -->
            <div class="svc-box space-y-4" data-for="cctv">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Installation address</label>
                    <input type="text" name="site_address" maxlength="300" value="<?php echo sanitize($form['site_address']); ?>" placeholder="Street, area, city"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Type of premises</label>
                        <select name="premises" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                            <?php foreach ($premises_types as $k => $label): ?>
                                <option value="<?php echo $k; ?>" <?php echo $form['premises'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Cameras (if known)</label>
                        <input type="number" name="cameras" min="1" value="<?php echo sanitize($form['cameras']); ?>" placeholder="Not sure? Leave blank"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">View on your phone?</label>
                        <select name="remote_view" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                            <option value="1" <?php echo $form['remote_view'] === '1' ? 'selected' : ''; ?>>Yes, remote viewing</option>
                            <option value="0" <?php echo $form['remote_view'] === '0' ? 'selected' : ''; ?>>No, on-site only</option>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Describe what you need</label>
                <textarea name="description" rows="5" required id="f-description"
                    placeholder="Tell us about your business and what you want to achieve. The more detail, the more accurate your quote."
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500"><?php echo sanitize($form['description']); ?></textarea>
            </div>

            <!-- Software builds: website, web app, mobile app -->
            <div class="svc-box space-y-6" data-for="<?php echo implode(' ', $software_services); ?>">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Features &amp; pages you want (one per line)</label>
                    <textarea name="features" id="f-features" rows="5"
                        placeholder="List anything you need, in your own words. For example:&#10;Customers can book appointments online&#10;Staff dashboard with monthly sales report"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500"><?php echo sanitize($form['features']); ?></textarea>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <span class="text-[10px] text-slate-400 mr-1 self-center">Ideas, tap to add:</span>
                        <?php foreach ($suggestions as $sg): ?>
                            <button type="button" class="suggest px-2.5 py-1 border border-slate-200 rounded-full text-[10px] text-slate-600 hover:border-indigo-400" data-text="<?php echo sanitize($sg); ?>"><?php echo sanitize($sg); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Websites or apps you like (optional)</label>
                    <textarea name="references" rows="2" placeholder="Links or names of examples that show the style or features you have in mind"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500"><?php echo sanitize($form['references']); ?></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">When do you need it?</label>
                    <select name="timeline" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                        <?php foreach ($timelines as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $form['timeline'] === $k ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Budget in mind (optional)</label>
                    <input type="text" name="budget_note" maxlength="100" value="<?php echo sanitize($form['budget_note']); ?>" placeholder="e.g. around ₦500,000"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                <p class="text-[11px] text-slate-400 max-w-xs">Our team reviews every request and sends you a price after assessing what's needed. No payment is due until you accept the quote.</p>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-sm shadow-sm">Submit request</button>
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
                            <span class="text-[9px] font-mono uppercase text-slate-500"><?php echo $h['status'] === 'DRAFT' ? 'Being assessed' : sanitize($h['status']); ?></span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1"><?php echo date('M d, Y', strtotime($h['created_at'])); ?> &bull; <?php echo ($h['status'] === 'DRAFT' ? 'Quote pending' : format_currency($h['amount'])); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Show only the questions that apply to the chosen service
    const titleHints = <?php echo json_encode([
        'corporate_training' => 'e.g. Excel & reporting training for our finance team',
        'website' => 'e.g. New website for my law firm',
        'web_app' => 'e.g. Online booking system for my clinic',
        'mobile_app' => 'e.g. Delivery tracking app for our riders',
        'cctv' => 'e.g. 8-camera CCTV for our warehouse',
        'consultation' => 'e.g. Advice on moving our records to the cloud',
        'other' => 'A short name for what you need',
    ]); ?>;
    function currentService() {
        return document.querySelector('input[name="service"]:checked')?.value || '';
    }
    function toggleServiceBoxes() {
        const svc = currentService();
        document.querySelectorAll('.svc-box').forEach(box => {
            box.classList.toggle('hidden', !box.dataset.for.split(' ').includes(svc));
        });
        document.getElementById('redesign-box').classList.toggle('hidden', document.getElementById('f-website-kind').value !== 'revamp');
        document.querySelector('input[name="project_title"]').placeholder = titleHints[svc] || '';
    }
    document.querySelectorAll('input[name="service"]').forEach(r => r.addEventListener('change', toggleServiceBoxes));
    document.getElementById('f-website-kind').addEventListener('change', toggleServiceBoxes);
    toggleServiceBoxes();

    document.querySelectorAll('.suggest').forEach(btn => btn.addEventListener('click', () => {
        const box = document.getElementById('f-features');
        const lines = box.value.split('\n').map(l => l.trim());
        if (!lines.includes(btn.dataset.text)) {
            box.value = (box.value.trim() ? box.value.trim() + '\n' : '') + btn.dataset.text;
        }
    }));
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
