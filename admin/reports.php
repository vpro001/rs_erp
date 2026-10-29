<?php
// admin/reports.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Analytics Reports";

// 1. Enrollment stats per course
$course_titles = [];
$course_enrollments = [];
try {
    $res = $conn->query("SELECT c.title, COUNT(e.id) as count FROM courses c LEFT JOIN enrollments e ON c.id = e.course_id GROUP BY c.id");
    while ($row = $res->fetch_assoc()) {
        $course_titles[] = $row['title'];
        $course_enrollments[] = intval($row['count']);
    }
} catch (Exception $e) {
    // Ignore
}

// 2. Complaint resolution metrics
$complaint_statuses = [];
$complaint_counts = [];
try {
    $res = $conn->query("SELECT status, COUNT(*) as count FROM complaints GROUP BY status");
    while ($row = $res->fetch_assoc()) {
        $complaint_statuses[] = $row['status'];
        $complaint_counts[] = intval($row['count']);
    }
} catch (Exception $e) {
    // Ignore
}

// 3. Support Tickets stats
$ticket_statuses = [];
$ticket_counts = [];
try {
    $res = $conn->query("SELECT status, COUNT(*) as count FROM support_tickets GROUP BY status");
    while ($row = $res->fetch_assoc()) {
        $ticket_statuses[] = $row['status'];
        $ticket_counts[] = intval($row['count']);
    }
} catch (Exception $e) {
    // Ignore
}

// 4. Financial breakdown: Monthly Cash flow trend
$finance_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$income_trends = array_fill(0, 12, 0);
$expense_trends = array_fill(0, 12, 0);

try {
    $res = $conn->query("SELECT MONTH(date) as m, SUM(amount) as total FROM income_records GROUP BY MONTH(date)");
    while ($row = $res->fetch_assoc()) {
        $month_idx = intval($row['m']) - 1;
        if ($month_idx >= 0 && $month_idx < 12) {
            $income_trends[$month_idx] = floatval($row['total']);
        }
    }
    
    $res = $conn->query("SELECT MONTH(date) as m, SUM(amount) as total FROM expense_records GROUP BY MONTH(date)");
    while ($row = $res->fetch_assoc()) {
        $month_idx = intval($row['m']) - 1;
        if ($month_idx >= 0 && $month_idx < 12) {
            $expense_trends[$month_idx] = floatval($row['total']);
        }
    }
} catch (Exception $e) {
    // Ignore
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Load Chart.js play CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Analytics layout grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Cashflow trend Chart -->
    <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
        <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-100  pb-3">
            <i data-lucide="line-chart" class="w-4 h-4 text-emerald-500"></i>
            <span>Monthly Cashflow Trends ($)</span>
        </h4>
        <div class="h-64 relative">
            <canvas id="cashflowChart"></canvas>
        </div>
    </div>
    
    <!-- Enrollment counts Chart -->
    <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
        <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-100  pb-3">
            <i data-lucide="graduation-cap" class="w-4 h-4 text-blue-500"></i>
            <span>LMS Course Enrollment Volumes</span>
        </h4>
        <div class="h-64 relative">
            <canvas id="enrollmentChart"></canvas>
        </div>
    </div>

    <!-- Ticket resolutions Chart -->
    <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
        <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-100  pb-3">
            <i data-lucide="help-circle" class="w-4 h-4 text-indigo-500"></i>
            <span>Support Tickets statuses</span>
        </h4>
        <div class="h-64 relative">
            <canvas id="ticketChart"></canvas>
        </div>
    </div>

    <!-- Customer complaints Chart -->
    <div class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-4">
        <h4 class="text-sm font-bold text-slate-900  flex items-center space-x-2 border-b border-slate-100  pb-3">
            <i data-lucide="shield-alert" class="w-4 h-4 text-red-500"></i>
            <span>Customer Complaints statuses</span>
        </h4>
        <div class="h-64 relative">
            <canvas id="complaintChart"></canvas>
        </div>
    </div>

</div>

<!-- Initialize Charts Script -->
<script>
    const labelColor = '#64748b';
    const gridColor = '#f1f5f9';

    // Chart.js global defaults
    Chart.defaults.color = labelColor;
    Chart.defaults.scale.grid.color = gridColor;

    // 1. Cashflow Chart Setup
    new Chart(document.getElementById('cashflowChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($finance_months); ?>,
            datasets: [
                {
                    label: 'Income ($)',
                    data: <?php echo json_encode($income_trends); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Expense ($)',
                    data: <?php echo json_encode($expense_trends); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // 2. Enrollment Chart Setup
    new Chart(document.getElementById('enrollmentChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($course_titles); ?>,
            datasets: [{
                label: 'Student Enrolments',
                data: <?php echo json_encode($course_enrollments); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.75)',
                borderColor: '#3b82f6',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // 3. Tickets Chart Setup
    new Chart(document.getElementById('ticketChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($ticket_statuses); ?>,
            datasets: [{
                data: <?php echo json_encode($ticket_counts); ?>,
                backgroundColor: ['#f59e0b', '#10b981', '#3b82f6', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // 4. Complaints Chart Setup
    new Chart(document.getElementById('complaintChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($complaint_statuses); ?>,
            datasets: [{
                data: <?php echo json_encode($complaint_counts); ?>,
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#64748b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
