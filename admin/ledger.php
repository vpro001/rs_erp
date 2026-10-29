<?php
// admin/ledger.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Finance Ledger Controls";

// 0. Handle Clear Mail Logs request
if (isset($_GET['action']) && $_GET['action'] === 'clear_mail_logs') {
    if (!has_role(['ADMIN', 'MANAGER'])) {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "error" => "Unauthorized"]);
        exit();
    }
    $log_path = __DIR__ . '/../uploads/simulated_emails.log';
    if (file_exists($log_path)) {
        @unlink($log_path);
    }
    header("Content-Type: application/json");
    echo json_encode(["success" => true]);
    exit();
}

$errors = [];
$success_msg = "";

// 1. Handle New Income entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_income') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $source = sanitize($_POST['source'] ?? 'OTHER');
    $amount = floatval($_POST['amount'] ?? 0.00);
    $description = sanitize($_POST['description'] ?? '');
    $category = sanitize($_POST['category'] ?? 'Miscellaneous');

    if ($amount <= 0) {
        $errors[] = "Income amount must be greater than zero.";
    } else {
        try {
            $income_id = generate_uuid();
            $stmt = $conn->prepare("INSERT INTO income_records (id, source, amount, description, category) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdss", $income_id, $source, $amount, $description, $category);
            $stmt->execute();
            $stmt->close();

            log_activity("Logged manual income record", ["amount" => $amount]);
            $success_msg = "Income entry logged successfully!";
        } catch (Exception $e) {
            $errors[] = "Operation failed: " . $e->getMessage();
        }
    }
}

// 2. Handle New Expense entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $category = sanitize($_POST['category'] ?? 'OTHER');
    $amount = floatval($_POST['amount'] ?? 0.00);
    $description = sanitize($_POST['description'] ?? '');

    if ($amount <= 0) {
        $errors[] = "Expense amount must be greater than zero.";
    } else {
        $db_receipt_path = null;

        // Handle receipt upload
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['receipt']['name'];
            $file_tmp = $_FILES['receipt']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg'];
            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = "Receipt must be PDF, PNG, or JPG.";
            } else {
                $upload_dir = __DIR__ . '/../uploads/receipts';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }

                $new_file_name = uniqid() . '_receipt.' . $file_ext;
                $dest_path = $upload_dir . '/' . $new_file_name;
                $db_receipt_path = '/uploads/receipts/' . $new_file_name;

                if (!move_uploaded_file($file_tmp, $dest_path)) {
                    $errors[] = "Could not save receipt image.";
                }
            }
        }

        if (empty($errors)) {
            try {
                $expense_id = generate_uuid();
                $stmt = $conn->prepare("INSERT INTO expense_records (id, category, amount, description, receipt) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdss", $expense_id, $category, $amount, $description, $db_receipt_path);
                $stmt->execute();
                $stmt->close();

                log_activity("Logged manual expense record", ["amount" => $amount]);
                $success_msg = "Expense entry logged successfully!";
            } catch (Exception $e) {
                $errors[] = "Operation failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch Income list
$income_records = [];
$total_income = 0.00;
try {
    $res = $conn->query("SELECT * FROM income_records ORDER BY date DESC LIMIT 20");
    while ($row = $res->fetch_assoc()) {
        $income_records[] = $row;
        $total_income += floatval($row['amount']);
    }
} catch (Exception $e) {
    // Ignore
}

// Fetch Expense list
$expense_records = [];
$total_expenses = 0.00;
try {
    $res = $conn->query("SELECT * FROM expense_records ORDER BY date DESC LIMIT 20");
    while ($row = $res->fetch_assoc()) {
        $expense_records[] = $row;
        $total_expenses += floatval($row['amount']);
    }
} catch (Exception $e) {
    // Ignore
}

$net_profit = $total_income - $total_expenses;

include __DIR__ . '/../includes/header.php';
?>

<!-- Financial balances cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white  border border-slate-200  p-5 rounded-2xl shadow-sm">
        <span class="text-xs text-slate-400  uppercase tracking-wider font-semibold">Total
            Revenue</span>
        <h3 class="text-2xl font-bold text-slate-905  mt-1"><?php echo format_currency($total_income); ?>
        </h3>
    </div>
    <div class="bg-white  border border-slate-200  p-5 rounded-2xl shadow-sm">
        <span class="text-xs text-slate-400  uppercase tracking-wider font-semibold">Total
            Expenditures</span>
        <h3 class="text-2xl font-bold text-slate-905  mt-1">
            <?php echo format_currency($total_expenses); ?></h3>
    </div>
    <div class="bg-white  border border-slate-200  p-5 rounded-2xl shadow-sm">
        <span class="text-xs text-slate-400  uppercase tracking-wider font-semibold">Net
            Balance</span>
        <h3
            class="text-2xl font-bold mt-1 <?php echo $net_profit >= 0 ? 'text-emerald-600 ' : 'text-red-500'; ?>">
            <?php echo format_currency($net_profit); ?>
        </h3>
    </div>
</div>

<!-- Ledger Grid Workspace -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Workspace: Entry Creators (Forms) -->
    <div class="space-y-6">

        <!-- Add Income Form -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-500"></i>
                <span>Log Income Receipt</span>
            </h4>

            <form action="ledger.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_income">

                <div>
                    <label for="income-source"
                        class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Source Channel</label>
                    <select name="source" id="income-source"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none">
                        <option value="TRAINING">Training / Course Fees</option>
                        <option value="SERVICES">Services Delivery</option>
                        <option value="SALES">Product Sales</option>
                        <option value="CONSULTING">Consulting</option>
                        <option value="OTHER">Other Income</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="income-amount"
                            class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Amount ($)</label>
                        <input type="number" step="0.01" name="amount" id="income-amount" required placeholder="250.00"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none font-mono">
                    </div>
                    <div>
                        <label for="income-cat"
                            class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Category</label>
                        <input type="text" name="category" id="income-cat" placeholder="e.g. LMS Fee"
                            class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none">
                    </div>
                </div>

                <div>
                    <label for="income-desc"
                        class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Details
                        description</label>
                    <input type="text" name="description" id="income-desc" placeholder="Details of receipt..."
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none">
                </div>

                <button type="submit"
                    class="w-full py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-emerald-500/10">
                    Log Income
                </button>
            </form>
        </div>

        <!-- Add Expense Form -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="minus-circle" class="w-4 h-4 text-red-500"></i>
                <span>Log Expenditure</span>
            </h4>

            <form action="ledger.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_expense">

                <div>
                    <label for="expense-cat"
                        class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Expense Category</label>
                    <select name="category" id="expense-cat"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none">
                        <option value="SALARIES">Salaries & Wages</option>
                        <option value="INFRASTRUCTURE">Server Infrastructure / SaaS</option>
                        <option value="UTILITIES">Utilities / Office</option>
                        <option value="MARKETING">Marketing & Advertisements</option>
                        <option value="OTHER">Other Expense</option>
                    </select>
                </div>

                <div>
                    <label for="expense-amount"
                        class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Amount ($)</label>
                    <input type="number" step="0.01" name="amount" id="expense-amount" required placeholder="45.00"
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none font-mono">
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Expense Receipt File
                        (PDF/Image)</label>
                    <input type="file" name="receipt"
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-semibold file:bg-slate-100  file:text-slate-700  hover:file:bg-slate-200 cursor-pointer">
                </div>

                <div>
                    <label for="expense-desc"
                        class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Details
                        description</label>
                    <input type="text" name="description" id="expense-desc"
                        placeholder="Hosting renewals, hardware purchased..."
                        class="w-full px-4 py-2 bg-slate-50  border border-slate-200  rounded-xl text-xs outline-none">
                </div>

                <button type="submit"
                    class="w-full py-2.5 bg-gradient-to-r from-red-655 to-orange-600 hover:from-red-500 hover:to-orange-555 text-white font-semibold rounded-xl text-xs transition-all shadow-md shadow-red-500/10">
                    Log Expense
                </button>
            </form>
        </div>

        <!-- Installment Cron Simulator Controls -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
            <h4
                class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="mail" class="w-4 h-4 text-amber-500"></i>
                <span>Installment Reminders Simulator</span>
            </h4>

            <p class="text-xs text-slate-500  leading-relaxed">
                Scan all enrollments and trigger automated email deadline reminders for installments due in the next 3
                days.
            </p>

            <button id="btn-trigger-cron"
                class="w-full py-2.5 bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-500 hover:to-yellow-500 text-white font-bold rounded-xl text-xs transition-all flex items-center justify-center space-x-2 shadow-md shadow-amber-500/10">
                <i data-lucide="play" class="w-4 h-4"></i>
                <span>Execute Reminders Cron</span>
            </button>

            <!-- Email logs preview window -->
            <div class="space-y-2 mt-4">
                <div
                    class="flex justify-between items-center text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <span>Simulated Mailbox Logs</span>
                    <button onclick="clearMailLogs()" class="text-red-500 hover:underline">Clear Logs</button>
                </div>
                <textarea id="mail-logs-preview" readonly placeholder="Mail dispatcher log is currently empty..."
                    rows="8"
                    class="w-full px-3 py-2 bg-slate-100 text-slate-700 font-mono text-[9px] rounded-xl border border-slate-200 focus:outline-none resize-none"><?php
                    $log_path = __DIR__ . '/../uploads/simulated_emails.log';
                    if (file_exists($log_path)) {
                        echo htmlspecialchars(file_get_contents($log_path));
                    }
                    ?></textarea>
            </div>
        </div>

    </div>

    <!-- Right Panel: Ledger List (Col span 2) -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Income Log Table -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-150  pb-3">
                <i data-lucide="arrow-up-right" class="w-4 h-4 text-emerald-500"></i>
                <span>Recent Income Audit Trail</span>
            </h4>

            <?php if (empty($income_records)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No income records registered.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200  text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="pb-2">Details</th>
                                <th class="pb-2">Channel</th>
                                <th class="pb-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100  text-xs">
                            <?php foreach ($income_records as $inc): ?>
                                <tr>
                                    <td class="py-3">
                                        <span
                                            class="font-bold text-slate-905  block"><?php echo sanitize($inc['category'] ?? 'General'); ?></span>
                                        <span
                                            class="text-[10px] text-slate-400 block"><?php echo sanitize($inc['description']); ?></span>
                                    </td>
                                    <td class="py-3 text-slate-500  font-semibold uppercase text-[10px]">
                                        <?php echo sanitize($inc['source']); ?>
                                    </td>
                                    <td class="py-3 text-right font-bold text-emerald-600  font-mono">
                                        +<?php echo format_currency($inc['amount']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Expense Log Table -->
        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-150  pb-3">
                <i data-lucide="arrow-down-left" class="w-4 h-4 text-red-500"></i>
                <span>Recent Expense Audit Trail</span>
            </h4>

            <?php if (empty($expense_records)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No expense records registered.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200  text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="pb-2">Details</th>
                                <th class="pb-2">Receipt</th>
                                <th class="pb-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100  text-xs">
                            <?php foreach ($expense_records as $exp): ?>
                                <tr>
                                    <td class="py-3">
                                        <span
                                            class="font-bold text-slate-905  block uppercase text-[10px]"><?php echo sanitize($exp['category']); ?></span>
                                        <span
                                            class="text-[10px] text-slate-400 block"><?php echo sanitize($exp['description']); ?></span>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($exp['receipt']): ?>
                                            <a href="<?php echo upload_url($exp['receipt']); ?>" target="_blank"
                                                class="text-xs text-blue-600  hover:underline flex items-center">
                                                <i data-lucide="paperclip" class="w-3.5 h-3.5 mr-1"></i>
                                                <span>View</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-right font-bold text-red-600  font-mono">
                                        -<?php echo format_currency($exp['amount']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
    const btnTriggerCron = document.getElementById('btn-trigger-cron');
    const logsPreview = document.getElementById('mail-logs-preview');

    if (btnTriggerCron) {
        btnTriggerCron.addEventListener('click', () => {
            btnTriggerCron.disabled = true;
            const originalText = btnTriggerCron.innerHTML;
            btnTriggerCron.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> <span>Processing...</span>';
            lucide.createIcons({ node: btnTriggerCron });

            const cronUrl = (window.BASE_URL || '') + '/config/installment_cron.php';
            fetch(cronUrl)
                .then(res => res.json())
                .then(data => {
                    btnTriggerCron.disabled = false;
                    btnTriggerCron.innerHTML = originalText;
                    lucide.createIcons({ node: btnTriggerCron });

                    if (data.success) {
                        alert("Cron simulation run successfully! Sent reminders count: " + data.reminders_sent);
                        location.reload();
                    } else {
                        alert(data.error || "Simulation process failed.");
                    }
                })
                .catch(err => {
                    btnTriggerCron.disabled = false;
                    btnTriggerCron.innerHTML = originalText;
                    lucide.createIcons({ node: btnTriggerCron });
                    console.error("Cron Error:", err);
                });
        });
    }

    function clearMailLogs() {
        if (confirm("Are you sure you want to clear simulated email logs?")) {
            const clearUrl = (window.BASE_URL || '') + '/admin/ledger.php?action=clear_mail_logs';
            fetch(clearUrl, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (logsPreview) logsPreview.value = '';
                    }
                });
        }
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>