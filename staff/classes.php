<?php
// staff/classes.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db_connection.php';

$user = current_user();
$page_title = "Staff - Classes & Attendance";

// Get staff ID
$staff_id = null;
try {
    $stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->bind_param("s", $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $staff_id = $row['id'];
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Fetch Staff's assigned classes
$classes = [];
try {
    $stmt = $conn->prepare("SELECT cl.id, cl.name, c.title as course_title 
                            FROM class_staff cs 
                            JOIN classes cl ON cs.class_id = cl.id 
                            JOIN courses c ON cl.course_id = c.id 
                            WHERE cs.staff_id = ? AND cl.status = 'ACTIVE'");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore
}

// Active class select
$class_id = sanitize($_GET['class_id'] ?? '');
$active_class = null;
$students = [];
$attendance_date = sanitize($_GET['date'] ?? date('Y-m-d'));

if (!empty($class_id)) {
    // Verify class assignment
    foreach ($classes as $cl) {
        if ($cl['id'] === $class_id) {
            $active_class = $cl;
            break;
        }
    }

    if ($active_class) {
        // Fetch class roster with existing attendance for the selected date
        try {
            $stmt = $conn->prepare("SELECT s.id as student_db_id, s.student_id as student_serial, u.first_name, u.last_name, 
                                    att.status as attendance_status, att.notes as attendance_notes 
                                    FROM class_students cs 
                                    JOIN students s ON cs.student_id = s.id 
                                    JOIN users_student u ON s.user_id = u.id 
                                    LEFT JOIN attendance att ON s.id = att.student_id AND att.date = ?
                                    WHERE cs.class_id = ? AND cs.status = 'ACTIVE' 
                                    ORDER BY u.first_name ASC");
            $stmt->bind_param("ss", $attendance_date, $class_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();
        } catch (Exception $e) {
            // Ignore
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Panel: Classes select sidebar -->
    <div class="space-y-6">

        <div
            class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm">
            <h4
                class="text-sm font-bold text-slate-900  mb-4 flex items-center space-x-2 border-b border-slate-100  pb-3">
                <i data-lucide="layers" class="w-4 h-4 text-blue-500"></i>
                <span>My Assigned Lecture Cohorts</span>
            </h4>

            <?php if (empty($classes)): ?>
                <p class="text-xs text-slate-400 text-center py-4">No active classes assigned.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($classes as $cl): ?>
                        <a href="classes.php?class_id=<?php echo $cl['id']; ?>&date=<?php echo $attendance_date; ?>"
                            class="w-full flex items-center justify-between p-3 rounded-xl border text-xs font-semibold transition-all <?php echo $cl['id'] === $class_id ? 'border-blue-500 bg-blue-500/5 text-blue-600 ' : 'border-slate-200/50  text-slate-700  hover:bg-slate-50 '; ?>">
                            <div>
                                <span class="block"><?php echo sanitize($cl['name']); ?></span>
                                <span
                                    class="text-[9px] text-slate-400 font-medium block mt-0.5"><?php echo sanitize($cl['course_title']); ?></span>
                            </div>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Panel: Student Attendance Sheet checklist -->
    <div class="lg:col-span-2 space-y-6">

        <?php if ($active_class): ?>
            <div
                class="bg-white  border border-slate-200  rounded-2xl p-6 shadow-sm space-y-6">

                <div
                    class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100  pb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-905 ">
                            <?php echo sanitize($active_class['name']); ?> - Student Attendance</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Assigned Syllabus:
                            <?php echo sanitize($active_class['course_title']); ?></p>
                    </div>

                    <!-- Date Picker Selector -->
                    <div>
                        <form action="classes.php" method="GET" class="flex items-center space-x-2">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            <input type="date" name="date" value="<?php echo $attendance_date; ?>"
                                onchange="this.form.submit()"
                                class="px-3 py-1.5 bg-slate-50  border border-slate-200  rounded-lg text-xs outline-none text-slate-655 ">
                        </form>
                    </div>
                </div>

                <?php if (empty($students)): ?>
                    <p class="text-xs text-slate-400 text-center py-6">No students assigned to this class cohort roster.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-100  text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <th class="pb-2">Student Name</th>
                                    <th class="pb-2">Roster Serial</th>
                                    <th class="pb-2 text-center">Status Action</th>
                                    <th class="pb-2 text-right">Instant Save</th>
                                </tr>
                                </tbody>
                            <tbody class="divide-y divide-slate-100  text-xs">
                                <?php foreach ($students as $stud): ?>
                                    <tr class="attendance-row" data-student-id="<?php echo $stud['student_db_id']; ?>">
                                        <td class="py-3.5 font-semibold text-slate-900 ">
                                            <?php echo sanitize($stud['first_name'] . ' ' . $stud['last_name']); ?>
                                        </td>
                                        <td class="py-3.5 font-mono text-[10px] text-slate-400">
                                            <?php echo sanitize($stud['student_serial']); ?>
                                        </td>
                                        <td class="py-3.5 text-center">
                                            <!-- Status selector options -->
                                            <div class="flex items-center justify-center space-x-1.5">
                                                <button
                                                    class="status-btn px-2.5 py-1 rounded text-[10px] font-bold tracking-wide transition-colors border <?php echo $stud['attendance_status'] === 'PRESENT' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30' : 'bg-slate-50  text-slate-400 border-transparent'; ?>"
                                                    onclick="setStatus(this, 'PRESENT')">P</button>
                                                <button
                                                    class="status-btn px-2.5 py-1 rounded text-[10px] font-bold tracking-wide transition-colors border <?php echo $stud['attendance_status'] === 'ABSENT' ? 'bg-red-500/10 text-red-650 border-red-500/30' : 'bg-slate-50  text-slate-400 border-transparent'; ?>"
                                                    onclick="setStatus(this, 'ABSENT')">A</button>
                                                <button
                                                    class="status-btn px-2.5 py-1 rounded text-[10px] font-bold tracking-wide transition-colors border <?php echo $stud['attendance_status'] === 'LATE' ? 'bg-amber-500/10 text-amber-600 border-amber-500/30' : 'bg-slate-50  text-slate-400 border-transparent'; ?>"
                                                    onclick="setStatus(this, 'LATE')">L</button>
                                                <button
                                                    class="status-btn px-2.5 py-1 rounded text-[10px] font-bold tracking-wide transition-colors border <?php echo $stud['attendance_status'] === 'EXCUSED' ? 'bg-blue-500/10 text-blue-600 border-blue-500/30' : 'bg-slate-50  text-slate-400 border-transparent'; ?>"
                                                    onclick="setStatus(this, 'EXCUSED')">E</button>
                                            </div>
                                        </td>
                                        <td class="py-3.5 text-right">
                                            <button
                                                class="save-row-btn px-3 py-1 bg-slate-100  text-slate-655  hover:bg-slate-200 rounded text-[10px] font-bold transition-all"
                                                onclick="saveRow(this)">
                                                Save row
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        <?php else: ?>
            <div
                class="bg-white  border border-slate-200  p-12 text-center rounded-2xl flex flex-col justify-center items-center min-h-[300px]">
                <i data-lucide="calendar" class="w-14 h-14 opacity-30 text-slate-400 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-705 ">Select a Class Cohort</h4>
                <p class="text-xs text-slate-500  mt-0.5">Please choose one of your assigned cohort
                    modules from the sidebar to take attendance checks.</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Ajax script to update attendance status rows -->
<script>
    let selectedStatus = {};

    function setStatus(button, statusVal) {
        const row = button.closest('.attendance-row');
        const studentId = row.getAttribute('data-student-id');

        // Remove active styling from siblings buttons
        const buttons = row.querySelectorAll('.status-btn');
        buttons.forEach(btn => {
            btn.className = "status-btn px-2.5 py-1 rounded text-[10px] font-bold tracking-wide transition-colors bg-slate-50  text-slate-400 border-transparent border";
        });

        // Add active style based on type
        let activeClass = '';
        if (statusVal === 'PRESENT') activeClass = 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30 border';
        else if (statusVal === 'ABSENT') activeClass = 'bg-red-500/10 text-red-650 border-red-500/30 border';
        else if (statusVal === 'LATE') activeClass = 'bg-amber-500/10 text-amber-600 border-amber-500/30 border';
        else if (statusVal === 'EXCUSED') activeClass = 'bg-blue-500/10 text-blue-600 border-blue-500/30 border';

        button.className = "status-btn px-2.5 py-1 rounded text-[10px] font-bold tracking-wide transition-colors " + activeClass;

        // Record status locally
        selectedStatus[studentId] = statusVal;
    }

    function saveRow(button) {
        const row = button.closest('.attendance-row');
        const studentId = row.getAttribute('data-student-id');
        const status = selectedStatus[studentId];

        if (!status) {
            alert("Please choose a status (P/A/L/E) before saving.");
            return;
        }

        button.disabled = true;
        button.textContent = "Saving...";

        const saveUrl = (window.BASE_URL || '') + '/api/save_attendance.php';
        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: studentId,
                date: '<?php echo $attendance_date; ?>',
                status: status
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    button.className = "save-row-btn px-3 py-1 bg-emerald-500/10 text-emerald-600  border border-emerald-500/30 rounded text-[10px] font-bold transition-all";
                    button.textContent = "Saved";
                    setTimeout(() => {
                        button.className = "save-row-btn px-3 py-1 bg-slate-100  text-slate-655  hover:bg-slate-200 rounded text-[10px] font-bold transition-all";
                        button.textContent = "Save row";
                        button.disabled = false;
                    }, 1500);
                } else {
                    alert(data.error || "Save error.");
                    button.disabled = false;
                    button.textContent = "Save row";
                }
            })
            .catch(err => {
                console.error("AJAX Error:", err);
                button.disabled = false;
                button.textContent = "Save row";
            });
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>