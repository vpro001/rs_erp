<?php
// includes/enrollment_helpers.php
// Shared by admin/user_control.php and admin/enrollments.php.

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
        $stmt = $conn->prepare("SELECT b.start_date, b.end_date, b.capacity, b.status,
                                       (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id AND e.id <> ?) AS enrolled
                                FROM classes b WHERE b.id = ? AND b.course_id = ?");
        $stmt->bind_param("sss", $exclude_enrollment_id, $batch_id, $course_id);
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

// Keep the batch roster (class_students, used by staff attendance) in step with the enrollment's batch.
function sync_batch_roster($conn, $student_pk, $course_id, $batch_id)
{
    $stmt = $conn->prepare("DELETE cs FROM class_students cs JOIN classes cl ON cl.id = cs.class_id WHERE cs.student_id = ? AND cl.course_id = ? AND (? IS NULL OR cs.class_id <> ?)");
    $stmt->bind_param("ssss", $student_pk, $course_id, $batch_id, $batch_id);
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
