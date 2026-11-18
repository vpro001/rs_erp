<?php
// includes/enrollment_helpers.php
// Shared by admin/user_control.php and admin/enrollments.php (also used by staff via wrappers).

// enrollments/payments reference students.id, not the login id; create the record on first use.
function student_record_id($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return $row['id'];

    $id = generate_uuid();
    $reg = next_registration_number($conn, (int) date('Y'), (int) date('n'));
    $stmt = $conn->prepare("INSERT INTO students (id, user_id, student_id, lecture_mode, profile_complete) VALUES (?, ?, ?, 'ONLINE', 1)");
    $stmt->bind_param("sss", $id, $user_id, $reg);
    $stmt->execute();
    $stmt->close();
    return $id;
}

/**
 * Resolve the batch and start/end dates for an enrollment from the posted form.
 * Dates default to the batch's dates; either can be overridden per student.
 *
 * @return array [batch_id|null, start Y-m-d, end Y-m-d]
 */
function resolve_enrollment_schedule($conn, $course_id, $exclude_enrollment_id = '')
{
    $batch_id = sanitize($_POST['batch_id'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');

    if ($batch_id !== '') {
        // A batch takes students of its main course and of any course listed in class_courses;
        // the latter leave on their own end date (e.g. Frontend students in a Fullstack batch).
        $stmt = $conn->prepare("SELECT b.start_date, COALESCE(cc.end_date, b.end_date) AS end_date, b.capacity, b.status,
                                       (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.id <> ?) AS enrolled
                                FROM classes b
                                LEFT JOIN class_courses cc ON cc.class_id = b.id AND cc.course_id = ?
                                WHERE b.id = ? AND (b.course_id = ? OR cc.course_id IS NOT NULL)");
        $stmt->bind_param("ssss", $exclude_enrollment_id, $course_id, $batch_id, $course_id);
        $stmt->execute();
        $batch = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$batch) throw new Exception("Selected batch does not belong to this course.");
        if ($batch['status'] !== 'ACTIVE') throw new Exception("Selected batch is not open for enrollment.");
        if ($batch['capacity'] && $batch['enrolled'] >= $batch['capacity']) throw new Exception("Selected batch is full ({$batch['capacity']} students).");
        if ($start === '' && $batch['start_date']) $start = $batch['start_date'];
        if ($end === '' && $batch['end_date']) $end = $batch['end_date'];
    }

    if (!strtotime($start) || !strtotime($end)) throw new Exception("Start and end dates are required (choose a batch or enter them).");
    if (strtotime($end) < strtotime($start)) throw new Exception("End date cannot be before the start date.");
    return [$batch_id !== '' ? $batch_id : null, date('Y-m-d', strtotime($start)), date('Y-m-d', strtotime($end))];
}

/**
 * Which courses each batch takes and when each course's students leave it:
 * [class_id => [course_id => end Y-m-d or '']]. The main course ends with the batch.
 */
function batch_course_ends($conn)
{
    $fmt = fn($d) => $d ? date('Y-m-d', strtotime($d)) : '';
    $map = [];
    $res = $conn->query("SELECT id, course_id, end_date FROM classes");
    while ($r = $res->fetch_assoc()) $map[$r['id']][$r['course_id']] = $fmt($r['end_date']);
    $res = $conn->query("SELECT cc.class_id, cc.course_id, COALESCE(cc.end_date, cl.end_date) AS end_date
                         FROM class_courses cc JOIN classes cl ON cl.id = cc.class_id");
    while ($r = $res->fetch_assoc()) $map[$r['class_id']][$r['course_id']] = $fmt($r['end_date']);
    return $map;
}

// Keep the batch roster (class_students, used by staff attendance) in step with the enrollment's batch.
function sync_batch_roster($conn, $student_pk, $course_id, $batch_id)
{
    // Drop the student from other batches that take this course, unless another of their
    // enrollments (a different course) still places them there.
    $stmt = $conn->prepare("DELETE cs FROM class_students cs JOIN classes cl ON cl.id = cs.class_id
                            WHERE cs.student_id = ?
                              AND (cl.course_id = ? OR EXISTS (SELECT 1 FROM class_courses cc WHERE cc.class_id = cl.id AND cc.course_id = ?))
                              AND (? IS NULL OR cs.class_id <> ?)
                              AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = cs.student_id AND e.batch_id = cs.class_id AND e.course_id <> ?)");
    $stmt->bind_param("ssssss", $student_pk, $course_id, $course_id, $batch_id, $batch_id, $course_id);
    $stmt->execute();
    $stmt->close();
    if ($batch_id) {
        $id = generate_uuid();
        $by = current_user()['email'] ?? 'admin';
        $stmt = $conn->prepare("INSERT IGNORE INTO class_students (id, class_id, student_id, assigned_by, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
        $stmt->bind_param("ssss", $id, $batch_id, $student_pk, $by);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Mirror the enrollment's status onto its batch roster row, so a student who has finished
 * (or dropped) their course stops getting that batch's assignments, messages and attendance.
 */
function sync_roster_status($conn, $enrollment_id)
{
    $stmt = $conn->prepare("UPDATE class_students cs JOIN enrollments e ON e.student_id = cs.student_id AND e.batch_id = cs.class_id
                            SET cs.status = e.status WHERE e.id = ?");
    $stmt->bind_param("s", $enrollment_id);
    $stmt->execute();
    $stmt->close();
}

/** Posted negotiated fee, defaulting to the course list price when left blank. */
function posted_agreed_fee($list_price)
{
    $raw = trim($_POST['agreed_fee'] ?? '');
    if ($raw === '') return round(floatval($list_price), 2);
    if (!is_numeric($raw) || floatval($raw) < 0) throw new Exception("Enter a valid agreed fee (0 or more).");
    return round(floatval($raw), 2);
}
