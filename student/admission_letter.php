<?php
// student/admission_letter.php - kept for old links; the letter now lives at /admission_letter.php
// so staff portals can open it too.
require_once __DIR__ . '/../includes/helpers.php';
$query = http_build_query(array_intersect_key($_GET, ['enrollment_id' => 1, 'download' => 1]));
header("Location: " . BASE_URL . "/admission_letter.php?" . $query);
exit;
