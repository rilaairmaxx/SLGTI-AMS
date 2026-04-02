<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include "config/db.php";
include "includes/header.php";

$role   = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'];
$courses = null;

if ($role === 'lecturer') {
    $stmt = $conn->prepare("
        SELECT id, course_name, course_code 
        FROM courses 
        WHERE lecturer_id=? AND status='active'
        ORDER BY course_name ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $courses = $stmt->get_result();
} elseif ($role === 'admin') {
    $courses = $conn->query("
        SELECT id, course_name, course_code
        FROM courses
        WHERE status='active'
        ORDER BY course_name ASC
    ");
} elseif ($role === 'student') {
    $stmt = $conn->prepare("
        SELECT c.id, c.course_name, c.course_code
        FROM courses c
        JOIN enrollments e ON c.id=e.course_id
        WHERE e.student_id=? AND c.status='active'
        ORDER BY c.course_name ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $courses = $stmt->get_result();
} else {
    die("Access denied.");
}

$analytics  = null;
$courseName = '';
$course_id  = $_GET['course_id'] ?? '';
$filterDate = $_GET['filter_date'] ?? '';
$date_from  = $_GET['date_from'] ?? date('Y-01-01');
$date_to    = $_GET['date_to']   ?? date('Y-12-31');

if ($course_id && is_numeric($course_id)) {
    $course_id = intval($course_id);

    // Verify course access per role before loading analytics
    if ($role === 'lecturer') {
        $stmt = $conn->prepare("SELECT course_name FROM courses WHERE id=? AND lecturer_id=?");
        $stmt->bind_param("ii", $course_id, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) die("Unauthorized access.");
        $courseName = $res->fetch_assoc()['course_name'] ?? '';
    } elseif ($role === 'student') {
        $stmt = $conn->prepare("
            SELECT c.course_name
            FROM courses c
            JOIN enrollments e ON c.id=e.course_id
            WHERE c.id=? AND e.student_id=?
        ");
        $stmt->bind_param("ii", $course_id, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) die("Unauthorized access.");
        $courseName = $res->fetch_assoc()['course_name'] ?? '';
    } else {
        $stmt = $conn->prepare("SELECT course_name FROM courses WHERE id=?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $res        = $stmt->get_result();
        $courseName = $res->fetch_assoc()['course_name'] ?? '';
    }

    // Aggregate attendance counts (Present / Absent / Late) for the selected course
    $dateCondition = "";
    $params = [$course_id];
    $types = "i";

    if ($filterDate) {
        $dateCondition = " AND a.attendance_date = ?";
        $params[] = $filterDate;
        $types .= "s";
    }

    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status='Absent'  THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.status='Late'    THEN 1 ELSE 0 END) AS late_count,
            COUNT(a.id) AS total_classes
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id=e.id
        WHERE e.course_id=?" . $dateCondition . "
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $analytics = $stmt->get_result()->fetch_assoc();
}
?>

<style>
    /* ── Variables ── */
    :root {
        --royal: #0a2d6e;
        --mid: #1456c8;
        --light: #f0f4fa;
        --border: #e4eaf3;
        --dark: #0d1b2e;
        --muted: #5a6e87;
        --green: #059669;
        --red: #dc2626;
        --amber: #d97706;
        --info: #0891b2;
        --purple: #7c3aed;
    }

    /* ── Page header ── */
    .rp-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .rp-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .rp-top-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--royal), var(--mid));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #fff;
        box-shadow: 0 4px 14px rgba(10, 45, 110, .25);
    }

    .rp-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .rp-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .rp-date-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 7px 16px;
        font-size: .8rem;
        font-weight: 600;
        color: var(--muted);
    }

    /* ── Card ── */
    .rp-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .09);
        border: 1px solid var(--border);
        overflow: hidden;
        height: 100%;
    }

    .rp-card-head {
        background: linear-gradient(135deg, var(--royal), var(--mid));
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .rp-card-head-ico {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: #fff;
        flex-shrink: 0;
    }

    .rp-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 700;
        color: #fff;
    }

    .rp-card-head p {
        margin: 2px 0 0;
        font-size: .72rem;
        color: rgba(255, 255, 255, .65);
    }

    .rp-card-body {
        padding: 24px 22px;
    }

    /* ── Form fields ── */
    .rp-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
        letter-spacing: .02em;
    }

    .rp-label i {
        color: var(--mid);
        font-size: .8rem;
    }

    .rp-select,
    .rp-input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 11px 38px 11px 14px;
        font-size: .9rem;
        font-family: inherit;
        color: var(--dark);
        background: #f8fafd;
        transition: border-color .2s, box-shadow .2s, background .2s;
        appearance: none;
        -webkit-appearance: none;
        cursor: pointer;
    }

    .rp-input {
        padding-right: 14px;
    }

    .rp-select:focus,
    .rp-input:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .rp-sel-wrap {
        position: relative;
    }

    .rp-sel-wrap::after {
        content: '\f107';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
        font-size: .85rem;
    }

    .rp-divider {
        height: 1px;
        background: var(--border);
        margin: 18px 0;
    }

    .rp-hint {
        font-size: .72rem;
        color: var(--muted);
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Selected course preview */
    .rp-course-preview {
        display: none;
        margin-top: 10px;
        padding: 10px 14px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        animation: fadeIn .2s ease;
    }

    .rp-course-preview.show {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .rp-course-preview-code {
        font-family: monospace;
        font-size: .78rem;
        font-weight: 800;
        color: var(--mid);
        letter-spacing: .06em;
        margin-bottom: 2px;
    }

    .rp-course-preview-name {
        font-size: .84rem;
        font-weight: 700;
        color: var(--dark);
    }

    /* Submit button */
    .rp-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--green), #065f46);
        color: #fff;
        border: none;
        border-radius: 11px;
        padding: 13px;
        font-size: .92rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 16px rgba(5, 150, 105, .25);
        transition: transform .2s, box-shadow .2s;
    }

    .rp-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(5, 150, 105, .35);
    }

    .rp-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    /* Print button */
    .rp-print-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border: none;
        border-radius: 9px;
        padding: 8px 18px;
        font-size: .82rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(10, 45, 110, .2);
        transition: transform .2s, box-shadow .2s;
        font-family: inherit;
    }

    .rp-print-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(10, 45, 110, .3);
        color: #fff;
    }

    /* ── Analytics right panel header ── */
    .rp-analytics-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
    }

    .rp-analytics-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 800;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rp-analytics-head h5 i {
        color: var(--mid);
    }

    .rp-course-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
        border-radius: 20px;
        padding: 4px 14px;
        font-size: .76rem;
        font-weight: 700;
        color: var(--mid);
    }

    /* ── Stat cards ── */
    .rp-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    @media(max-width:992px) {
        .rp-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width:480px) {
        .rp-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .rp-stat {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 2px 14px rgba(10, 45, 110, .08);
        border: 1px solid var(--border);
        padding: 18px 16px;
        text-align: center;
        transition: transform .2s, box-shadow .2s;
        position: relative;
        overflow: hidden;
    }

    .rp-stat:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .12);
    }

    .rp-stat::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 4px 4px 0 0;
    }

    .rp-stat.green::before {
        background: linear-gradient(90deg, #10b981, #059669);
    }

    .rp-stat.red::before {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .rp-stat.amber::before {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    .rp-stat.blue::before {
        background: linear-gradient(90deg, var(--mid), var(--royal));
    }

    .rp-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        margin: 0 auto 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .rp-stat.green .rp-stat-icon {
        background: #d1fae5;
        color: var(--green);
    }

    .rp-stat.red .rp-stat-icon {
        background: #fee2e2;
        color: var(--red);
    }

    .rp-stat.amber .rp-stat-icon {
        background: #fef3c7;
        color: var(--amber);
    }

    .rp-stat.blue .rp-stat-icon {
        background: #dbeafe;
        color: var(--mid);
    }

    .rp-stat-num {
        font-size: 1.9rem;
        font-weight: 800;
        color: var(--dark);
        line-height: 1;
        margin-bottom: 4px;
    }

    .rp-stat-lbl {
        font-size: .74rem;
        color: var(--muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    /* Attendance rate arc */
    .rp-rate-arc {
        width: 80px;
        height: 80px;
        margin: 0 auto 8px;
        position: relative;
    }

    .rp-rate-arc svg {
        width: 80px;
        height: 80px;
        transform: rotate(-90deg);
    }

    .rp-rate-arc circle.track {
        fill: none;
        stroke: #e4eaf3;
        stroke-width: 7;
    }

    .rp-rate-arc circle.fill {
        fill: none;
        stroke-width: 7;
        stroke-linecap: round;
        transition: stroke-dashoffset .6s ease;
    }

    .rp-rate-val {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .82rem;
        font-weight: 800;
        color: var(--dark);
    }

    /* Progress bars */
    .rp-bar-wrap {
        margin-bottom: 16px;
    }

    .rp-bar-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .rp-bar-lbl {
        font-size: .78rem;
        font-weight: 600;
        color: var(--dark);
    }

    .rp-bar-val {
        font-size: .78rem;
        font-weight: 700;
        color: var(--muted);
    }

    .rp-bar-track {
        height: 8px;
        background: #e4eaf3;
        border-radius: 4px;
        overflow: hidden;
    }

    .rp-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width .6s ease;
    }

    /* ── Detail table ── */
    .rp-tbl-wrap {
        border-top: 1px solid var(--border);
    }

    .rp-tbl-head-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 22px;
        background: #fafbfd;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
    }

    .rp-tbl-head-row h6 {
        margin: 0;
        font-size: .85rem;
        font-weight: 800;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 7px;
    }

    /* Search */
    .rp-search-wrap {
        position: relative;
    }

    .rp-search-ico {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .8rem;
        pointer-events: none;
    }

    .rp-search-input {
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 8px 13px 8px 30px;
        font-size: .82rem;
        font-family: inherit;
        background: #fff;
        color: var(--dark);
        width: 200px;
        transition: border-color .2s, box-shadow .2s;
    }

    .rp-search-input:focus {
        outline: none;
        border-color: var(--mid);
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .rp-search-input::placeholder {
        color: #aab4c4;
    }

    .rp-tbl {
        width: 100%;
        border-collapse: collapse;
    }

    .rp-tbl thead tr {
        background: var(--light);
        border-bottom: 2px solid var(--border);
    }

    .rp-tbl thead th {
        padding: 11px 16px;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--muted);
    }

    .rp-tbl thead th:first-child {
        padding-left: 22px;
    }

    .rp-tbl thead th:last-child {
        padding-right: 22px;
    }

    .rp-tbl tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .rp-tbl tbody tr:last-child {
        border-bottom: none;
    }

    .rp-tbl tbody tr:hover {
        background: #f7f9fc;
    }

    .rp-tbl td {
        padding: 12px 16px;
        vertical-align: middle;
    }

    .rp-tbl td:first-child {
        padding-left: 22px;
    }

    .rp-tbl td:last-child {
        padding-right: 22px;
    }

    /* Student cell */
    .rp-av {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, var(--mid), var(--royal));
    }

    .rp-sname {
        font-weight: 600;
        color: var(--dark);
        font-size: .86rem;
    }

    /* Status badge */
    .rp-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
    }

    .rp-badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    .rp-badge.present {
        background: #f0fdf4;
        color: #166534;
    }

    .rp-badge.present .rp-badge-dot {
        background: var(--green);
    }

    .rp-badge.absent {
        background: #fff1f1;
        color: #991b1b;
    }

    .rp-badge.absent .rp-badge-dot {
        background: var(--red);
    }

    .rp-badge.late {
        background: #fffbeb;
        color: #92400e;
    }

    .rp-badge.late .rp-badge-dot {
        background: var(--amber);
    }

    .rp-date-txt {
        font-size: .78rem;
        color: var(--muted);
    }

    .rp-date-txt i {
        margin-right: 4px;
        color: #c8d0db;
        font-size: .7rem;
    }

    /* Empty / placeholder state */
    .rp-placeholder {
        text-align: center;
        padding: 52px 24px;
        color: #94a3b8;
    }

    .rp-placeholder i {
        font-size: 2.8rem;
        display: block;
        margin-bottom: 14px;
        opacity: .25;
    }

    .rp-placeholder h6 {
        font-size: .95rem;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .rp-placeholder p {
        font-size: .82rem;
        margin: 0;
    }

    .rp-no-results {
        display: none;
        text-align: center;
        padding: 24px;
        color: #94a3b8;
        font-size: .84rem;
    }

    .rp-no-results i {
        font-size: 1.4rem;
        display: block;
        margin-bottom: 6px;
        opacity: .3;
    }

    /* ── Print styles ── */
    @media print {
        body * {
            visibility: hidden;
        }

        #reportArea,
        #reportArea * {
            visibility: visible;
        }

        #reportArea {
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
        }

        .rp-print-btn,
        .rp-search-wrap,
        .rp-tbl-head-row button {
            display: none !important;
        }
    }

/* ══════════════════════════════════════
   DARK MODE - Same as Dashboard
══════════════════════════════════════ */
[data-theme="dark"] .rp-top-title { color: #e2e8f0; }
[data-theme="dark"] .rp-top-sub { color: #94a3b8; }
[data-theme="dark"] .rp-date-pill { background: #1e293b; border-color: #334155; color: #94a3b8; }

[data-theme="dark"] .rp-card { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .rp-card-head { background: linear-gradient(135deg, #1e293b, #0f172a); }

[data-theme="dark"] .rp-label { color: #e2e8f0; }
[data-theme="dark"] .rp-select, [data-theme="dark"] .rp-input { background: #0f172a; border-color: #334155; color: #e2e8f0; }
[data-theme="dark"] .rp-select:focus, [data-theme="dark"] .rp-input:focus { border-color: #1456c8; }
[data-theme="dark"] .rp-divider { background: #334155; }
[data-theme="dark"] .rp-hint { color: #64748b; }

[data-theme="dark"] .rp-course-preview { background: #0f172a; border-color: #334155; }
[data-theme="dark"] .rp-course-preview-code { color: #93c5fd; }
[data-theme="dark"] .rp-course-preview-name { color: #e2e8f0; }

[data-theme="dark"] .rp-analytics-head { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .rp-analytics-head h5 { color: #e2e8f0; }
[data-theme="dark"] .rp-course-badge { background: #0f172a; border-color: #334155; color: #93c5fd; }

[data-theme="dark"] .rp-stat { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .rp-stat-num { color: #e2e8f0; }
[data-theme="dark"] .rp-stat-lbl { color: #64748b; }
[data-theme="dark"] .rp-stat.green .rp-stat-icon { background: #052e16; color: #6ee7b7; }
[data-theme="dark"] .rp-stat.red .rp-stat-icon { background: #3f0a0a; color: #fca5a5; }
[data-theme="dark"] .rp-stat.amber .rp-stat-icon { background: #1c1003; color: #fcd34d; }
[data-theme="dark"] .rp-stat.blue .rp-stat-icon { background: #0f172a; color: #93c5fd; }

[data-theme="dark"] .rp-rate-arc circle.track { stroke: #334155; }
[data-theme="dark"] .rp-rate-val { color: #e2e8f0; }

[data-theme="dark"] .rp-bar-lbl { color: #e2e8f0; }
[data-theme="dark"] .rp-bar-val { color: #64748b; }
[data-theme="dark"] .rp-bar-track { background: #334155; }

[data-theme="dark"] .rp-tbl-wrap { border-color: #334155; }
[data-theme="dark"] .rp-tbl-head-row { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .rp-tbl-head-row h6 { color: #e2e8f0; }

[data-theme="dark"] .rp-search-input { background: #0f172a; border-color: #334155; color: #e2e8f0; }
[data-theme="dark"] .rp-search-input:focus { border-color: #1456c8; }
[data-theme="dark"] .rp-search-input::placeholder { color: #475569; }

[data-theme="dark"] .rp-tbl thead tr { background: #0f172a; }
[data-theme="dark"] .rp-tbl thead th { background: #0f172a; color: #64748b; border-color: #334155; }
[data-theme="dark"] .rp-tbl tbody tr { border-color: #334155; }
[data-theme="dark"] .rp-tbl tbody tr:hover { background: #273549; }
[data-theme="dark"] .rp-tbl td { color: #cbd5e1; }

[data-theme="dark"] .rp-av { border-color: #334155; }
[data-theme="dark"] .rp-sname { color: #e2e8f0; }

[data-theme="dark"] .rp-badge.present { background: #052e16; color: #6ee7b7; }
[data-theme="dark"] .rp-badge.present .rp-badge-dot { background: #059669; }
[data-theme="dark"] .rp-badge.absent { background: #3f0a0a; color: #fca5a5; }
[data-theme="dark"] .rp-badge.absent .rp-badge-dot { background: #dc2626; }
[data-theme="dark"] .rp-badge.late { background: #1c1003; color: #fcd34d; }
[data-theme="dark"] .rp-badge.late .rp-badge-dot { background: #d97706; }
[data-theme="dark"] .rp-date-txt { color: #64748b; }

[data-theme="dark"] .rp-placeholder { color: #64748b; }
[data-theme="dark"] .rp-placeholder h6 { color: #64748b; }
[data-theme="dark"] .rp-no-results { color: #64748b; }

[data-theme="dark"] .table-responsive::-webkit-scrollbar-thumb { background: #334155; }
</style>


<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="rp-top">
    <div class="rp-top-left">
        <div class="rp-top-icon">
            <i class="fas fa-chart-bar"></i>
        </div>
        <div>
            <h1 class="rp-top-title">Attendance Reports</h1>
            <p class="rp-top-sub">
                <?php
                echo $role === 'admin'    ? 'System-wide attendance analytics across all courses'
                    : ($role === 'lecturer' ? 'Analytics for your assigned courses'
                        : 'Your personal attendance summary');
                ?>
            </p>
        </div>
    </div>
    <div class="rp-date-pill">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('l, d M Y'); ?>
    </div>
</div>


<div class="row g-4">

    <!-- ══════════════════════════════════════
         LEFT — COURSE SELECTOR
    ══════════════════════════════════════ -->
    <div class="col-lg-4">
        <div class="rp-card">

            <div class="rp-card-head">
                <div class="rp-card-head-ico">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <div>
                    <h5>Filter Options</h5>
                    <p>Select a course to view its report</p>
                </div>
            </div>

            <div class="rp-card-body">

                <!-- ── FORM — id="analyticsForm" unchanged ── -->
                <form method="GET" id="analyticsForm">

                    <!-- Course -->
                    <div style="margin-bottom:18px;">
                        <label class="rp-label">
                            <i class="fas fa-book-open"></i> Course <span style="color:var(--red);margin-left:2px;">*</span>
                        </label>
                        <div class="rp-sel-wrap">
                            <select name="course_id" class="rp-select" id="courseSelect" required
                                onchange="rpCoursePreview(this)">
                                <option value="">— Select Course —</option>
                                <?php while ($c = $courses->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($c['course_code']); ?>"
                                        data-name="<?php echo htmlspecialchars($c['course_name']); ?>"
                                        <?php echo ($course_id == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name'] . " (" . $c['course_code'] . ")"); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <!-- NEW: selected course preview -->
                        <div class="rp-course-preview <?php echo $course_id ? 'show' : ''; ?>" id="rpCoursePreview">
                            <div class="rp-course-preview-code" id="rpPreviewCode">
                                <?php
                                if ($course_id) {
                                    $stmt2 = $conn->prepare("SELECT course_code FROM courses WHERE id=?");
                                    $stmt2->bind_param("i", $course_id);
                                    $stmt2->execute();
                                    $cRow = $stmt2->get_result()->fetch_assoc();
                                    echo htmlspecialchars($cRow['course_code'] ?? '');
                                }
                                ?>
                            </div>
                            <div class="rp-course-preview-name" id="rpPreviewName">
                                <?php echo htmlspecialchars($courseName); ?>
                            </div>
                        </div>
                    </div>

                    <div class="rp-divider"></div>

                    <!-- Submit -->
                    <button type="submit" class="rp-btn" id="rpSubmitBtn">
                        <i class="fas fa-chart-line"></i> Show Analytics
                    </button>

                </form>

                <?php if ($analytics && $analytics['total_classes'] > 0): ?>
                    <!-- Report info box -->
                    <div style="margin-top:20px;padding:14px 16px;background:var(--light);border:1px solid var(--border);border-radius:12px;">
                        <div style="font-size:.78rem;font-weight:700;color:var(--dark);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-info-circle" style="color:var(--mid);"></i>
                            Report Summary
                        </div>
                        <?php
                        $total   = intval($analytics['total_classes']);
                        $present = intval($analytics['present_count']);
                        $absent  = intval($analytics['absent_count']);
                        $late    = intval($analytics['late_count']);
                        $percent = $total > 0 ? round(($present / $total) * 100, 2) : 0;
                        $r = 35;
                        $circ = round(2 * M_PI * $r, 2);
                        $offset = round($circ - ($percent / 100 * $circ), 2);
                        $arcCol = $percent >= 75 ? '#059669' : ($percent >= 50 ? '#d97706' : '#dc2626');
                        ?>
                        <!-- SVG arc -->
                        <div style="text-align:center;margin-bottom:10px;">
                            <div class="rp-rate-arc">
                                <svg viewBox="0 0 80 80">
                                    <circle class="track" cx="40" cy="40" r="<?php echo $r; ?>" />
                                    <circle class="fill" cx="40" cy="40" r="<?php echo $r; ?>"
                                        stroke="<?php echo $arcCol; ?>"
                                        stroke-dasharray="<?php echo $circ; ?>"
                                        stroke-dashoffset="<?php echo $offset; ?>" />
                                </svg>
                                <div class="rp-rate-val" style="color:<?php echo $arcCol; ?>;">
                                    <?php echo $percent; ?>%
                                </div>
                            </div>
                            <div style="font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">
                                Attendance Rate
                            </div>
                        </div>
                        <!-- Quick breakdown -->
                        <?php foreach (
                            [
                                ['Present', $present, $total, '#059669'],
                                ['Absent',  $absent,  $total, '#dc2626'],
                                ['Late',    $late,    $total, '#d97706'],
                            ] as [$lbl, $val, $tot, $col]
                        ): $pct = $tot > 0 ? round($val / $tot * 100) : 0; ?>
                            <div class="rp-bar-wrap" style="margin-bottom:10px;">
                                <div class="rp-bar-row">
                                    <span class="rp-bar-lbl"><?php echo $lbl; ?></span>
                                    <span class="rp-bar-val"><?php echo $val; ?> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div class="rp-bar-track">
                                    <div class="rp-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════
         RIGHT — ANALYTICS + TABLE
    ══════════════════════════════════════ -->
    <div class="col-lg-8">
        <div class="rp-card">

            <div class="rp-analytics-head">
                <h5>
                    <i class="fas fa-chart-bar"></i>
                    Attendance Analytics
                    <?php if ($courseName): ?>
                        <span class="rp-course-badge">
                            <i class="fas fa-book" style="font-size:.7rem;"></i>
                            <?php echo htmlspecialchars($courseName); ?>
                        </span>
                    <?php endif; ?>
                </h5>
                <?php if ($analytics && $analytics['total_classes'] > 0): ?>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <!-- Print -->
                        <button onclick="printReport()" class="rp-print-btn">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <!-- PDF export -->
                        <a href="exports/export_pdf.php?course_id=<?= $course_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
                           target="_blank" class="rp-print-btn" style="background:linear-gradient(135deg,#dc2626,#991b1b);text-decoration:none;">
                            <i class="fas fa-file-pdf"></i> Print / PDF
                        </a>
                        <!-- Excel export -->
                        <a href="exports/export_excel.php?course_id=<?= $course_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
                           class="rp-print-btn" style="background:linear-gradient(135deg,#059669,#065f46);text-decoration:none;">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
     <?php endif; ?>
            </div>

            <!-- ── REPORT AREA (id="reportArea" unchanged) ── -->
            <div id="reportArea">

                <?php if ($analytics && $analytics['total_classes'] > 0):
                    $total   = intval($analytics['total_classes']);
                    $present = intval($analytics['present_count']);
                    $absent  = intval($analytics['absent_count']);
                    $late    = intval($analytics['late_count']);
                    $percent = $total > 0 ? round(($present / $total) * 100, 2) : 0;
                ?>

                    <!-- Stat cards -->
                    <div style="padding:22px 22px 0;">
                        <div class="rp-stats">
                            <div class="rp-stat green">
                                <div class="rp-stat-icon"><i class="fas fa-check-circle"></i></div>
                                <div class="rp-stat-num"><?php echo $present; ?></div>
                                <div class="rp-stat-lbl">Present</div>
                            </div>
                            <div class="rp-stat red">
                                <div class="rp-stat-icon"><i class="fas fa-times-circle"></i></div>
                                <div class="rp-stat-num"><?php echo $absent; ?></div>
                                <div class="rp-stat-lbl">Absent</div>
                            </div>
                            <div class="rp-stat amber">
                                <div class="rp-stat-icon"><i class="fas fa-clock"></i></div>
                                <div class="rp-stat-num"><?php echo $late; ?></div>
                                <div class="rp-stat-lbl">Late</div>
                            </div>
                            <div class="rp-stat blue">
                                <div class="rp-stat-icon"><i class="fas fa-percentage"></i></div>
                                <div class="rp-stat-num"><?php echo $percent; ?>%</div>
                                <div class="rp-stat-lbl">Rate</div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail table section -->
                    <div class="rp-tbl-wrap">
                        <div class="rp-tbl-head-row">
                            <h6>
                                <i class="fas fa-list-ul" style="color:var(--mid);"></i>
                                Attendance Records
                            </h6>
                            <!-- NEW: live search for detail table -->
                            <div class="rp-search-wrap">
                                <i class="fas fa-search rp-search-ico"></i>
                                <input type="text" class="rp-search-input" id="rpSearch"
                                    placeholder="Search student…">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="rp-tbl" id="rpTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="rpTbody">
                                    <?php
                                    $stmt = $conn->prepare("
                                    SELECT s.student_name, a.status, a.attendance_date
                                    FROM attendance a
                                    JOIN enrollments e ON a.enrollment_id=e.id
                                    JOIN students s ON e.student_id=s.id
                                    WHERE e.course_id=?
                                      AND s.status = 'active'
                                    ORDER BY a.attendance_date DESC
                                ");
                                    $stmt->bind_param("i", $course_id);
                                    $stmt->execute();
                                    $details = $stmt->get_result();

                                    if ($details->num_rows > 0):
                                        while ($row = $details->fetch_assoc()):
                                            $initial  = strtoupper(substr($row['student_name'], 0, 1));
                                            $statusCl = strtolower($row['status']);
                                    ?>
                                            <tr data-name="<?php echo strtolower(htmlspecialchars($row['student_name'])); ?>">
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:10px;">
                                                        <div class="rp-av"><?php echo $initial; ?></div>
                                                        <div class="rp-sname"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="rp-badge <?php echo $statusCl; ?>">
                                                        <span class="rp-badge-dot"></span>
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="rp-date-txt">
                                                        <i class="far fa-calendar"></i>
                                                        <?php echo date('d M Y', strtotime($row['attendance_date'])); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="3">
                                                <div class="rp-placeholder">
                                                    <i class="fas fa-clipboard-list"></i>
                                                    <h6>No Records Found</h6>
                                                    <p>No attendance records for this course yet.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <div class="rp-no-results" id="rpNoResults">
                                <i class="fas fa-search"></i>
                                No students found matching your search.
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Placeholder state -->
                    <div class="rp-placeholder">
                        <i class="fas fa-chart-pie"></i>
                        <h6>No Data to Display</h6>
                        <p>Select a course from the left panel and click <strong>Show Analytics</strong> to view the attendance report.</p>
                    </div>
                <?php endif; ?>

            </div><!-- /reportArea -->

        </div>
    </div>

</div><!-- /row -->


<!-- ════════════════════════════════════════════════
     JAVASCRIPT
     ── ORIGINAL 1: printReport() (id="reportArea" unchanged)
     ── ORIGINAL 2: analyticsForm validation (id unchanged)
     ── NEW 1: course selection preview
     ── NEW 2: live search on detail table
     ── NEW 3: submit loading spinner
════════════════════════════════════════════════ -->
<script>
    // ════════════════════════════════════
    //  ORIGINAL 1 — Print report
    //  (id="reportArea" unchanged)
    // ════════════════════════════════════
    function printReport() {
        const report = document.getElementById("reportArea").innerHTML;
        const original = document.body.innerHTML;
        document.body.innerHTML = report;
        window.print();
        document.body.innerHTML = original;
        location.reload();
    }

    // ════════════════════════════════════
    //  ORIGINAL 2 — Form validation
    //  (id="analyticsForm" unchanged)
    // ════════════════════════════════════
    document.getElementById('analyticsForm').addEventListener('submit', function(e) {
        if (!this.course_id.value || isNaN(this.course_id.value) || parseInt(this.course_id.value) <= 0) {
            alert("Please select a valid course.");
            e.preventDefault();
            return;
        }

        // NEW 3: loading spinner on valid submit
        const btn = document.getElementById('rpSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading…';
        }
    });

    // ════════════════════════════════════
    //  NEW 1 — Course selection preview
    // ════════════════════════════════════
    function rpCoursePreview(select) {
        const opt = select.options[select.selectedIndex];
        const preview = document.getElementById('rpCoursePreview');
        const code = document.getElementById('rpPreviewCode');
        const name = document.getElementById('rpPreviewName');

        if (opt.value) {
            code.textContent = opt.getAttribute('data-code') || '';
            name.textContent = opt.getAttribute('data-name') || '';
            preview.classList.add('show');
        } else {
            preview.classList.remove('show');
        }
    }

    // Init preview if course already selected (page reload after submit)
    const rpSelectInit = document.getElementById('courseSelect');
    if (rpSelectInit && rpSelectInit.value) {
        rpCoursePreview(rpSelectInit);
    }

    // ════════════════════════════════════
    //  NEW 2 — Live search on detail table
    // ════════════════════════════════════
    const rpSearch = document.getElementById('rpSearch');
    if (rpSearch) {
        rpSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#rpTbody tr[data-name]');
            let visible = 0;

            rows.forEach(function(row) {
                const match = !q || (row.dataset.name || '').includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            const noRes = document.getElementById('rpNoResults');
            if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
        });
    }
</script>

<?php include "includes/footer.php"; ?>