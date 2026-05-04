<?php
require_once __DIR__ . '/../config/functions.php';
startSession();
requireRole(['admin', 'teacher']);

$db = getDB();

// ── Same filters as students.php ──────────────────────────
$search    = sanitize($_GET['search']  ?? '');
$gradeId   = (int)($_GET['grade']      ?? 0);
$sectionId = (int)($_GET['section']    ?? 0);
$status    = sanitize($_GET['status']  ?? '');

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ? OR s.middle_name LIKE ?)";
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($gradeId) {
    $where[] = "sec.grade_level_id = ?";
    $params[] = $gradeId;
}
if ($sectionId) {
    $where[] = "s.section_id = ?";
    $params[] = $sectionId;
}
if ($status) {
    $where[] = "s.enrollment_status = ?";
    $params[] = $status;
}

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT
        s.lrn,
        s.last_name,
        s.first_name,
        s.middle_name,
        s.suffix,
        s.gender,
        s.date_of_birth,
        s.place_of_birth,
        s.nationality,
        s.religion,
        s.mother_tongue,
        s.address,
        s.barangay,
        s.municipality,
        s.province,
        s.zip_code,
        gl.grade_name,
        sec.section_name,
        s.enrollment_status,
        s.school_year,
        s.date_enrolled,
        s.father_name,
        s.father_occupation,
        s.father_contact,
        s.mother_name,
        s.mother_occupation,
        s.mother_contact,
        s.guardian_name,
        s.guardian_relationship,
        s.guardian_contact,
        s.height_cm,
        s.weight_kg,
        s.blood_type
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
    WHERE $whereStr
    ORDER BY gl.id, s.last_name, s.first_name
");
$stmt->execute($params);
$students = $stmt->fetchAll();

auditLog('EXPORT_STUDENTS_CSV', 'students', null, "Exported " . count($students) . " student(s)");

// ── Stream CSV ────────────────────────────────────────────
$filename = 'students_export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header row
fputcsv($out, [
    'LRN',
    'Last Name',
    'First Name',
    'Middle Name',
    'Suffix',
    'Gender',
    'Date of Birth',
    'Place of Birth',
    'Nationality',
    'Religion',
    'Mother Tongue',
    'Address',
    'Barangay',
    'Municipality',
    'Province',
    'ZIP Code',
    'Grade Level',
    'Section',
    'Enrollment Status',
    'School Year',
    'Date Enrolled',
    'Father Name',
    'Father Occupation',
    'Father Contact',
    'Mother Name',
    'Mother Occupation',
    'Mother Contact',
    'Guardian Name',
    'Guardian Relationship',
    'Guardian Contact',
    'Height (cm)',
    'Weight (kg)',
    'Blood Type',
]);

// Data rows
foreach ($students as $s) {
    fputcsv($out, [
        $s['lrn']                  ?? '',
        $s['last_name']            ?? '',
        $s['first_name']           ?? '',
        $s['middle_name']          ?? '',
        $s['suffix']               ?? '',
        $s['gender']               ?? '',
        $s['date_of_birth']        ?? '',
        $s['place_of_birth']       ?? '',
        $s['nationality']          ?? '',
        $s['religion']             ?? '',
        $s['mother_tongue']        ?? '',
        $s['address']              ?? '',
        $s['barangay']             ?? '',
        $s['municipality']         ?? '',
        $s['province']             ?? '',
        $s['zip_code']             ?? '',
        $s['grade_name']           ?? '',
        $s['section_name']         ?? '',
        $s['enrollment_status']    ?? '',
        $s['school_year']          ?? '',
        $s['date_enrolled']        ?? '',
        $s['father_name']          ?? '',
        $s['father_occupation']    ?? '',
        $s['father_contact']       ?? '',
        $s['mother_name']          ?? '',
        $s['mother_occupation']    ?? '',
        $s['mother_contact']       ?? '',
        $s['guardian_name']        ?? '',
        $s['guardian_relationship']?? '',
        $s['guardian_contact']     ?? '',
        $s['height_cm']            ?? '',
        $s['weight_kg']            ?? '',
        $s['blood_type']           ?? '',
    ]);
}

fclose($out);
exit();
