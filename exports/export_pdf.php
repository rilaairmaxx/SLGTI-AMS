<?php

session_start();
require_once "../config/db.php";

// ── Auth ──
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role    = $_SESSION['role']      ?? '';
$userId  = $_SESSION['user_id'];
$name    = $_SESSION['full_name'] ?? 'User';

// ── Input parameters ──
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?: 0;
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to   = $_GET['date_to']   ?? date('Y-12-31');

// Validate dates
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

// ── Role-based course access guard ──
if ($role === 'lecturer') {
    $guard = $conn->prepare("SELECT id FROM courses WHERE id = ? AND lecturer_id = ?");
    $guard->bind_param("ii", $course_id, $userId);
    $guard->execute();
    if ($guard->get_result()->num_rows === 0) {
        die("Unauthorized access.");
    }
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

// ── Totals ──
$grandTotal   = array_sum(array_column($rows, 'total'));
$grandPresent = array_sum(array_column($rows, 'present'));
$grandAbsent  = array_sum(array_column($rows, 'absent'));
$grandLate    = array_sum(array_column($rows, 'late'));
$grandExcused = array_sum(array_column($rows, 'excused'));
$overallPct   = $grandTotal > 0 ? round($grandPresent / $grandTotal * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance Report — <?php echo htmlspecialchars($courseRow['course_code'] ?? 'ALL'); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #fff;
            padding: 0;
        }

        /* ── Print header ── */
        .report-header {
            background: linear-gradient(135deg, #0a2d6e, #1456c8);
            color: #fff;
            padding: 24px 32px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .report-logo-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .report-logo {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            font-weight: 800;
            border: 2px solid rgba(255, 255, 255, .25);
        }

        .report-org h1 {
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .report-org p {
            font-size: .72rem;
            color: rgba(255, 255, 255, .7);
        }

        .report-meta {
            text-align: right;
            font-size: .72rem;
            color: rgba(255, 255, 255, .7);
            line-height: 1.8;
        }

        .report-meta strong {
            color: #fff;
        }

        /* ── Report title strip ── */
        .report-title-strip {
            background: #f0f4fa;
            border-bottom: 2px solid #e4eaf3;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-title-strip h2 {
            font-size: 1rem;
            font-weight: 800;
            color: #0a2d6e;
        }

        .report-title-strip .badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-amber {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ── Course info bar ── */
        .course-info {
            display: flex;
            gap: 20px;
            padding: 14px 32px;
            border-bottom: 1px solid #e4eaf3;
            flex-wrap: wrap;
            background: #fff;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-label {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
        }

        .info-value {
            font-size: .85rem;
            font-weight: 700;
            color: #0d1b2e;
        }

        /* ── Summary stats row ── */
        .summary-row {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #e4eaf3;
        }

        .summary-cell {
            flex: 1;
            padding: 14px 20px;
            text-align: center;
            border-right: 1px solid #e4eaf3;
        }

        .summary-cell:last-child {
            border-right: none;
        }

        .summary-num {
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
        }

        .summary-lbl {
            font-size: .65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
        }

        .c-green {
            color: #059669;
        }

        .c-red {
            color: #dc2626;
        }

        .c-amber {
            color: #d97706;
        }

        .c-blue {
            color: #1456c8;
        }

        .c-purple {
            color: #7c3aed;
        }

        /* ── Main table ── */
        .report-body {
            padding: 20px 32px 32px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        thead tr {
            background: #0a2d6e;
            color: #fff;
        }

        thead th {
            padding: 10px 14px;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            text-align: left;
        }

        thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        thead th:last-child {
            border-radius: 0 8px 0 0;
            text-align: center;
        }

        tbody tr:nth-child(even) {
            background: #f8fafd;
        }

        tbody tr:hover {
            background: #eff6ff;
        }

        tbody td {
            padding: 10px 14px;
            font-size: .82rem;
            color: #374151;
            border-bottom: 1px solid #e4eaf3;
        }

        tbody td:last-child {
            text-align: center;
        }

        /* Student name cell */
        .st-name {
            font-weight: 700;
            color: #0d1b2e;
        }

        .st-num {
            font-size: .7rem;
            color: #94a3b8;
        }

        /* Status value cells */
        td.present {
            color: #059669;
            font-weight: 700;
        }

        td.absent {
            color: #dc2626;
            font-weight: 700;
        }

        td.late {
            color: #d97706;
            font-weight: 700;
        }

        td.excused {
            color: #7c3aed;
            font-weight: 700;
        }

        /* Percentage pill */
        .pct-pill {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: .74rem;
            font-weight: 700;
        }

        .pct-high {
            background: #dcfce7;
            color: #166534;
        }

        .pct-mid {
            background: #fef3c7;
            color: #92400e;
        }

        .pct-low {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Totals row */
        tfoot tr {
            background: #1456c8;
            color: #fff;
        }

        tfoot td {
            padding: 10px 14px;
            font-size: .8rem;
            font-weight: 700;
            border: none;
        }

        tfoot td:last-child {
            text-align: center;
        }

        /* ── No data ── */
        .no-data {
            text-align: center;
            padding: 48px 24px;
            color: #94a3b8;
        }

        .no-data i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
            opacity: .3;
        }

        /* ── Footer ── */
        .report-footer {
            border-top: 2px solid #e4eaf3;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .7rem;
            color: #94a3b8;
        }

        /* ── Action buttons (hidden when printing) ── */
        .action-bar {
            padding: 14px 32px;
            background: #f0f4fa;
            border-bottom: 1px solid #e4eaf3;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, #0a2d6e, #1456c8);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 20px;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: transform .2s;
        }

        .btn-print:hover {
            transform: translateY(-2px);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            color: #5a6e87;
            border: 1.5px solid #e4eaf3;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
        }

        .btn-back:hover {
            border-color: #1456c8;
            color: #1456c8;
        }

        /* ── Print media ── */
        @media print {
            .action-bar {
                display: none !important;
            }

            body {
                padding: 0;
            }

            @page {
                margin: 1cm;
            }
        }
    </style>
</head>

<body>

    <!-- Action bar (hidden on print) -->
    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">
            &#128438; Print / Save as PDF
        </button>
        <a href="../reports.php" class="btn-back">
            &#8592; Back to Reports
        </a>
        <a href="export_excel.php?course_id=<?php echo $course_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"
            class="btn-back" style="color:#059669;border-color:#6ee7b7;">
            &#128196; Export Excel
        </a>
    </div>

    <!-- Report header -->
    <div class="report-header">
        <div class="report-logo-wrap">
            <div class="report-logo">SL</div>
            <div class="report-org">
                <h1>Sri Lanka German Technical Institute</h1>
                <p>SLGTI — Ariviyal Nagar, Kilinochchi 44000</p>
            </div>
        </div>
        <div class="report-meta">
            <div><strong>Report Generated</strong></div>
            <div><?php echo date('d M Y, h:i A'); ?></div>
            <div>Generated by: <strong><?php echo htmlspecialchars($name); ?></strong></div>
        </div>
    </div>

    <!-- Title strip -->
    <div class="report-title-strip">
        <h2>&#128202; Attendance Report</h2>
        <?php
        $pctClass = $overallPct >= 75 ? 'badge-green' : ($overallPct >= 50 ? 'badge-amber' : 'badge-red');
        ?>
        <span class="badge <?php echo $pctClass; ?>">
            Overall Attendance: <?php echo $overallPct; ?>%
        </span>
    </div>

    <!-- Course info -->
    <div class="course-info">
        <div class="info-item">
            <span class="info-label">Course</span>
            <span class="info-value">
                <?php echo $courseRow ? htmlspecialchars($courseRow['course_name']) : 'All Courses'; ?>
            </span>
        </div>
        <?php if ($courseRow): ?>
            <div class="info-item">
                <span class="info-label">Code</span>
                <span class="info-value" style="font-family:monospace;">
                    <?php echo htmlspecialchars($courseRow['course_code']); ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Lecturer</span>
                <span class="info-value"><?php echo htmlspecialchars($courseRow['lecturer_name'] ?? 'N/A'); ?></span>
            </div>
        <?php endif; ?>
        <div class="info-item">
            <span class="info-label">Period</span>
            <span class="info-value">
                <?php echo date('d M Y', strtotime($date_from)); ?> &mdash; <?php echo date('d M Y', strtotime($date_to)); ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Students</span>
            <span class="info-value"><?php echo count($rows); ?></span>
        </div>
    </div>

    <!-- Summary row -->
    <div class="summary-row">
        <div class="summary-cell">
            <div class="summary-num c-blue"><?php echo $grandTotal; ?></div>
            <div class="summary-lbl">Total Sessions</div>
        </div>
        <div class="summary-cell">
            <div class="summary-num c-green"><?php echo $grandPresent; ?></div>
            <div class="summary-lbl">Present</div>
        </div>
        <div class="summary-cell">
            <div class="summary-num c-red"><?php echo $grandAbsent; ?></div>
            <div class="summary-lbl">Absent</div>
        </div>
        <div class="summary-cell">
            <div class="summary-num c-amber"><?php echo $grandLate; ?></div>
            <div class="summary-lbl">Late</div>
        </div>
        <div class="summary-cell">
            <div class="summary-num c-purple"><?php echo $grandExcused; ?></div>
            <div class="summary-lbl">Excused</div>
        </div>
        <div class="summary-cell">
            <div class="summary-num <?php echo $overallPct >= 75 ? 'c-green' : ($overallPct >= 50 ? 'c-amber' : 'c-red'); ?>">
                <?php echo $overallPct; ?>%
            </div>
            <div class="summary-lbl">Avg Attendance</div>
        </div>
    </div>

    <!-- Main table -->
    <div class="report-body">
        <?php if (count($rows) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Student No.</th>
                        <th style="text-align:center;">Present</th>
                        <th style="text-align:center;">Absent</th>
                        <th style="text-align:center;">Late</th>
                        <th style="text-align:center;">Excused</th>
                        <th style="text-align:center;">Total</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row):
                        $pct     = $row['total'] > 0 ? round($row['present'] / $row['total'] * 100, 1) : 0;
                        $pctCls  = $pct >= 75 ? 'pct-high' : ($pct >= 50 ? 'pct-mid' : 'pct-low');
                    ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <div class="st-name"><?php echo htmlspecialchars($row['student_name']); ?></div>
                            </td>
                            <td class="st-num"><?php echo htmlspecialchars($row['student_number']); ?></td>
                            <td class="present" style="text-align:center;"><?php echo $row['present']; ?></td>
                            <td class="absent" style="text-align:center;"><?php echo $row['absent'];  ?></td>
                            <td class="late" style="text-align:center;"><?php echo $row['late'];    ?></td>
                            <td class="excused" style="text-align:center;"><?php echo $row['excused']; ?></td>
                            <td style="text-align:center;font-weight:700;"><?php echo $row['total'];   ?></td>
                            <td>
                                <span class="pct-pill <?php echo $pctCls; ?>"><?php echo $pct; ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">TOTALS</td>
                        <td style="text-align:center;"><?php echo $grandPresent; ?></td>
                        <td style="text-align:center;"><?php echo $grandAbsent;  ?></td>
                        <td style="text-align:center;"><?php echo $grandLate;    ?></td>
                        <td style="text-align:center;"><?php echo $grandExcused; ?></td>
                        <td style="text-align:center;"><?php echo $grandTotal;   ?></td>
                        <td style="text-align:center;"><?php echo $overallPct; ?>%</td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div class="no-data">
                <div style="font-size:2rem;opacity:.25;">&#128202;</div>
                <p style="margin-top:10px;font-size:.9rem;">No attendance records found for the selected period.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="report-footer">
        <span>SLGTI Attendance Management System &mdash; Confidential</span>
        <span>Printed: <?php echo date('d M Y, h:i A'); ?></span>
    </div>

</body>

</html>