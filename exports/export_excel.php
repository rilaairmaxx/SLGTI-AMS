<?php

session_start();
require_once "../config/db.php";

// ── Auth ──
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role   = $_SESSION['role']      ?? '';
$userId = $_SESSION['user_id'];
$name   = $_SESSION['full_name'] ?? 'User';

// ── Input parameters ──
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?: 0;
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to   = $_GET['date_to']   ?? date('Y-12-31');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-12-31');

// ── Fetch course info ──
$courseRow = null;
if ($course_id) {
    $cs = $conn->prepare("
        SELECT c.course_name, c.course_code, u.full_name AS lecturer_name
        FROM courses c
        LEFT JOIN users u ON c.lecturer_id = u.id
        WHERE c.id = ?
    ");
    $cs->bind_param("i", $course_id);
    $cs->execute();
    $courseRow = $cs->get_result()->fetch_assoc();
}

// ── Role-based access guard ──
if ($role === 'lecturer') {
    $guard = $conn->prepare("SELECT id FROM courses WHERE id = ? AND lecturer_id = ?");
    $guard->bind_param("ii", $course_id, $userId);
    $guard->execute();
    if ($guard->get_result()->num_rows === 0) die("Unauthorized access.");
}

// ── Fetch attendance summary per student ──
$params = [$course_id, $date_from, $date_to];
$types  = "iss";

if ($role === 'student') {
    $sql = "
        SELECT
            s.student_name,
            s.student_number,
            SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status='Absent'  THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN a.status='Late'    THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status='Excused' THEN 1 ELSE 0 END) AS excused,
            COUNT(a.id) AS total
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN students s    ON e.student_id    = s.id
        WHERE e.course_id = ?
          AND a.attendance_date BETWEEN ? AND ?
          AND e.student_id = ?
          AND s.status = 'active'
        GROUP BY s.id
        ORDER BY s.student_name
    ";
    $params[] = $userId;
    $types   .= "i";
} else {
    $sql = "
        SELECT
            s.student_name,
            s.student_number,
            SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status='Absent'  THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN a.status='Late'    THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status='Excused' THEN 1 ELSE 0 END) AS excused,
            COUNT(a.id) AS total
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN students s    ON e.student_id    = s.id
        WHERE e.course_id = ?
          AND a.attendance_date BETWEEN ? AND ?
          AND s.status = 'active'
        GROUP BY s.id
        ORDER BY s.student_name
    ";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Also fetch the daily detail sheet ──
$detailParams = [$course_id, $date_from, $date_to];
$detailTypes  = "iss";

if ($role === 'student') {
    $detailSql = "
        SELECT s.student_name, s.student_number, a.attendance_date, a.status, a.remarks
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN students s    ON e.student_id    = s.id
        WHERE e.course_id = ?
          AND a.attendance_date BETWEEN ? AND ?
          AND e.student_id = ?
          AND s.status = 'active'
        ORDER BY a.attendance_date DESC, s.student_name
    ";
    $detailParams[] = $userId;
    $detailTypes   .= "i";
} else {
    $detailSql = "
        SELECT s.student_name, s.student_number, a.attendance_date, a.status, a.remarks
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN students s    ON e.student_id    = s.id
        WHERE e.course_id = ?
          AND a.attendance_date BETWEEN ? AND ?
          AND s.status = 'active'
        ORDER BY a.attendance_date DESC, s.student_name
    ";
}

$dStmt = $conn->prepare($detailSql);
$dStmt->bind_param($detailTypes, ...$detailParams);
$dStmt->execute();
$detailRows = $dStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Build filename ──
$courseCode = $courseRow['course_code'] ?? 'ALL';
$filename   = 'SLGTI_Attendance_' . $courseCode . '_' . $date_from . '_to_' . $date_to . '.csv';

// ── Output CSV headers ──
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM so Excel opens it correctly
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ════════════════════════════════════════
//  SHEET 1 — REPORT METADATA
// ════════════════════════════════════════
fputcsv($out, ['SLGTI Attendance Management System']);
fputcsv($out, ['Sri Lanka German Technical Institute, Kilinochchi 44000']);
fputcsv($out, []);
fputcsv($out, ['ATTENDANCE REPORT']);
fputcsv($out, []);
fputcsv($out, ['Course',     $courseRow['course_name']     ?? 'All Courses']);
fputcsv($out, ['Code',       $courseRow['course_code']     ?? '—']);
fputcsv($out, ['Lecturer',   $courseRow['lecturer_name']   ?? '—']);
fputcsv($out, ['Period',     $date_from . ' to ' . $date_to]);
fputcsv($out, ['Generated',  date('d M Y, h:i A')]);
fputcsv($out, ['Generated By', $name]);
fputcsv($out, []);

// ════════════════════════════════════════
//  SHEET 2 — SUMMARY TABLE
// ════════════════════════════════════════
fputcsv($out, ['--- SUMMARY ---']);
fputcsv($out, ['#', 'Student Name', 'Student Number', 'Present', 'Absent', 'Late', 'Excused', 'Total Sessions', 'Attendance %']);

$grandPresent = $grandAbsent = $grandLate = $grandExcused = $grandTotal = 0;

foreach ($rows as $i => $row) {
    $pct = $row['total'] > 0 ? round($row['present'] / $row['total'] * 100, 1) : 0;
    fputcsv($out, [
        $i + 1,
        $row['student_name'],
        $row['student_number'],
        $row['present'],
        $row['absent'],
        $row['late'],
        $row['excused'],
        $row['total'],
        $pct . '%',
    ]);
    $grandPresent += $row['present'];
    $grandAbsent  += $row['absent'];
    $grandLate    += $row['late'];
    $grandExcused += $row['excused'];
    $grandTotal   += $row['total'];
}

// Totals row
$grandPct = $grandTotal > 0 ? round($grandPresent / $grandTotal * 100, 1) : 0;
fputcsv($out, []);
fputcsv($out, [
    '',
    'TOTALS',
    '',
    $grandPresent,
    $grandAbsent,
    $grandLate,
    $grandExcused,
    $grandTotal,
    $grandPct . '%',
]);
fputcsv($out, []);

// ════════════════════════════════════════
//  SHEET 3 — DAILY DETAIL
// ════════════════════════════════════════
fputcsv($out, ['--- DAILY DETAIL ---']);
fputcsv($out, ['Student Name', 'Student Number', 'Date', 'Day', 'Status', 'Remarks']);

foreach ($detailRows as $dr) {
    $dayName = date('l', strtotime($dr['attendance_date']));
    $formattedDate = date('d M Y', strtotime($dr['attendance_date']));
    fputcsv($out, [
        $dr['student_name'],
        $dr['student_number'],
        $formattedDate,
        $dayName,
        $dr['status'],
        $dr['remarks'] ?? '',
    ]);
}

fclose($out);
exit();
