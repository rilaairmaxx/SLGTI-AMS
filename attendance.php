<?php
require_once "config/db.php";
require_once "includes/header.php";

// Lecturer-only page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

$msg         = ['type' => '', 'text' => ''];
$lecturer_id = $_SESSION['user_id'];

// Fetch only active courses assigned to this lecturer for the dropdown
$courses = $conn->prepare("
    SELECT id, course_name, course_code 
    FROM courses 
    WHERE lecturer_id=? AND status='active'
    ORDER BY course_name ASC
");
$courses->bind_param("i", $lecturer_id);
$courses->execute();
$courses_result = $courses->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
    $date      = $_POST['date'] ?? '';

    if (!$course_id) {
        $msg = ['type' => 'danger', 'text' => 'Invalid course selected.'];
    } elseif (!$date || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        $msg = ['type' => 'danger', 'text' => 'Invalid date format.'];
    } else {
        // Confirm this course belongs to the logged-in lecturer
        $verifyCourse = $conn->prepare("SELECT id FROM courses WHERE id=? AND lecturer_id=?");
        $verifyCourse->bind_param("ii", $course_id, $lecturer_id);
        $verifyCourse->execute();
        $verifyCourseResult = $verifyCourse->get_result();

        if ($verifyCourseResult->num_rows === 0) {
            $msg = ['type' => 'danger', 'text' => 'You are not authorized to mark attendance for this course.'];
        } elseif (!isset($_POST['attendance']) || !is_array($_POST['attendance'])) {
            $msg = ['type' => 'warning', 'text' => 'No attendance data submitted.'];
        } else {

            // Must match the ENUM in the attendance table exactly
            $valid_statuses = ['Present', 'Absent', 'Late', 'Excused'];

            // Loop each student's status and upsert attendance record
            foreach ($_POST['attendance'] as $enrollment_id => $status) {

                $enrollment_id = filter_var($enrollment_id, FILTER_VALIDATE_INT);
                if (!$enrollment_id || !in_array($status, $valid_statuses)) {
                    continue;
                }

                // Validate: enrollment must belong to this course, student must be active,
                // and enrollment_date must be <= attendance date
                $eligCheck = $conn->prepare("
                    SELECT e.id FROM enrollments e
                    JOIN students s ON e.student_id = s.id
                    WHERE e.id = ?
                      AND e.course_id = ?
                      AND s.status = 'active'
                      AND e.enrollment_date <= ?
                ");
                $eligCheck->bind_param("iis", $enrollment_id, $course_id, $date);
                $eligCheck->execute();
                if ($eligCheck->get_result()->num_rows === 0) {
                    continue; // skip ineligible students
                }

                // remarks is optional; default to empty string if not submitted
                $remarks   = trim($_POST['remarks'][$enrollment_id] ?? '');
                // marked_by records which lecturer saved this row
                $marked_by = $lecturer_id;

                // Check if record exists for this enrollment + date
                $check = $conn->prepare("
                    SELECT id FROM attendance 
                    WHERE enrollment_id=? AND attendance_date=?
                ");
                $check->bind_param("is", $enrollment_id, $date);
                $check->execute();

                if ($check->get_result()->num_rows === 0) {
                    // No record — insert new with remarks and marked_by
                    $insert = $conn->prepare("
                        INSERT INTO attendance (enrollment_id, attendance_date, status, remarks, marked_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insert->bind_param("isssi", $enrollment_id, $date, $status, $remarks, $marked_by);
                    $insert->execute();
                } else {
                    // Record exists — update status, remarks, and marked_by
                    $update = $conn->prepare("
                        UPDATE attendance 
                        SET status=?, remarks=?, marked_by=?
                        WHERE enrollment_id=? AND attendance_date=?
                    ");
                    $update->bind_param("sssis", $status, $remarks, $marked_by, $enrollment_id, $date);
                    $update->execute();
                }
            }

            $msg = ['type' => 'success', 'text' => 'Attendance saved successfully.'];
        }
    }
}

$students        = null;
$course_selected = null;
$selected_date   = $_GET['date'] ?? date('Y-m-d');

if (isset($_GET['course'])) {

    $course_id = filter_var($_GET['course'], FILTER_VALIDATE_INT);

    if ($course_id) {
        // Verify course ownership before loading students
        $verifyCourse = $conn->prepare("SELECT id, course_name FROM courses WHERE id=? AND lecturer_id=?");
        $verifyCourse->bind_param("ii", $course_id, $lecturer_id);
        $verifyCourse->execute();
        $result = $verifyCourse->get_result();

        if ($result->num_rows > 0) {
            $course_selected = $result->fetch_assoc();

            // Fetch enrolled students with any existing attendance for the selected date.
            // Rules:
            //   1. Only include students whose enrollment_date <= selected_date
            //      (student must have been registered on or before the attendance date)
            //   2. Only include active students (exclude inactive/graduated/suspended = left SLGTI)
            $students = $conn->prepare("
                SELECT 
                    e.id            AS enrollment_id,
                    s.student_name,
                    s.student_number,
                    e.enrollment_date,
                    a.status        AS saved_status,
                    a.remarks       AS saved_remarks,
                    a.marked_at,
                    u.full_name     AS marked_by_name
                FROM enrollments e
                JOIN students s       ON e.student_id     = s.id
                LEFT JOIN attendance a ON a.enrollment_id = e.id AND a.attendance_date = ?
                LEFT JOIN users u     ON a.marked_by      = u.id
                WHERE e.course_id = ?
                  AND s.status = 'active'
                  AND e.enrollment_date <= ?
                ORDER BY s.student_name ASC
            ");
            $students->bind_param("sis", $selected_date, $course_id, $selected_date);
            $students->execute();
            $students = $students->get_result();
        } else {
            $msg = ['type' => 'danger', 'text' => 'Unauthorized course selection.'];
        }
    } else {
        $msg = ['type' => 'danger', 'text' => 'Invalid course selected.'];
    }
}

// Pre-collect student rows for counters
$studentRows  = [];
$markedCount  = 0;
if ($students && $students->num_rows > 0) {
    while ($row = $students->fetch_assoc()) {
        $studentRows[] = $row;
        if (!empty($row['saved_status'])) $markedCount++;
    }
}
$totalStudents = count($studentRows);
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
        --purple: #7c3aed;
    }

    /* ── Page header ── */
    .at-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .at-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .at-top-icon {
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

    .at-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .at-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .at-date-pill {
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

    /* ── Alert ── */
    .at-msg {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        margin-bottom: 22px;
        font-size: .87rem;
        font-weight: 500;
        animation: msgIn .35s ease;
    }

    @keyframes msgIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .at-msg.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .at-msg.danger {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    .at-msg.warning {
        background: #fffbeb;
        color: #92400e;
        border-left: 4px solid var(--amber);
    }

    .at-msg i {
        font-size: 1rem;
        flex-shrink: 0;
    }

    .at-msg-close {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        opacity: .5;
        font-size: .9rem;
        padding: 0 2px;
    }

    .at-msg-close:hover {
        opacity: 1;
    }

    /* ── Card ── */
    .at-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .09);
        border: 1px solid var(--border);
        overflow: hidden;
        height: 100%;
    }

    .at-card-head {
        background: linear-gradient(135deg, var(--royal), var(--mid));
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .at-card-head-ico {
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

    .at-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 700;
        color: #fff;
    }

    .at-card-head p {
        margin: 2px 0 0;
        font-size: .72rem;
        color: rgba(255, 255, 255, .65);
    }

    .at-card-body {
        padding: 24px 22px;
    }

    /* ── Form labels ── */
    .at-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
        letter-spacing: .02em;
    }

    .at-label i {
        color: var(--mid);
        font-size: .8rem;
    }

    .at-select,
    .at-input {
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

    .at-input {
        padding-right: 14px;
        cursor: text;
    }

    .at-select:focus,
    .at-input:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .at-sel-wrap {
        position: relative;
    }

    .at-sel-wrap::after {
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

    .at-divider {
        height: 1px;
        background: var(--border);
        margin: 18px 0;
    }

    .at-hint {
        font-size: .7rem;
        color: var(--muted);
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Course preview */
    .at-course-preview {
        display: none;
        margin-top: 8px;
        padding: 10px 14px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        animation: fadeIn .2s ease;
    }

    .at-course-preview.show {
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

    .at-preview-code {
        font-family: monospace;
        font-size: .78rem;
        font-weight: 800;
        color: var(--mid);
        letter-spacing: .06em;
    }

    .at-preview-name {
        font-size: .84rem;
        font-weight: 700;
        color: var(--dark);
        margin-top: 2px;
    }

    /* Load button */
    .at-load-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--mid), var(--royal));
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
        box-shadow: 0 4px 16px rgba(10, 45, 110, .25);
        transition: transform .2s, box-shadow .2s;
    }

    .at-load-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .32);
    }

    /* Session stat mini grid */
    .at-stat-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 16px;
    }

    .at-stat-mini {
        border-radius: 10px;
        padding: 10px;
        text-align: center;
    }

    .at-stat-mini-num {
        font-size: 1.4rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 3px;
    }

    .at-stat-mini-lbl {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    /* ── Right panel header ── */
    .at-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
    }

    .at-panel-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 800;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .at-panel-head h5 i {
        color: var(--mid);
    }

    .at-course-tag {
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

    /* ── Progress bar ── */
    .at-progress-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 22px;
        background: var(--light);
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
    }

    .at-progress-text {
        font-size: .82rem;
        font-weight: 600;
        color: var(--dark);
    }

    .at-progress-text span {
        color: var(--mid);
        font-weight: 800;
    }

    .at-bar-wrap {
        flex: 1;
        min-width: 120px;
        max-width: 200px;
    }

    .at-bar-track {
        height: 6px;
        background: #d1d9e6;
        border-radius: 3px;
        overflow: hidden;
    }

    .at-bar-fill {
        height: 100%;
        border-radius: 3px;
        background: linear-gradient(90deg, var(--mid), var(--green));
        transition: width .5s ease;
    }

    /* ── Toolbar ── */
    .at-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
    }

    .at-bulk-btns {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .at-bulk-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: .76rem;
        font-weight: 700;
        border: 1.5px solid var(--border);
        background: #fff;
        cursor: pointer;
        transition: all .2s;
        font-family: inherit;
    }

    .at-bulk-btn.present {
        color: var(--green);
        border-color: #6ee7b7;
    }

    .at-bulk-btn.present:hover {
        background: #f0fdf4;
        border-color: var(--green);
    }

    .at-bulk-btn.absent {
        color: var(--red);
        border-color: #fca5a5;
    }

    .at-bulk-btn.absent:hover {
        background: #fff1f1;
        border-color: var(--red);
    }

    .at-bulk-btn.late {
        color: var(--amber);
        border-color: #fcd34d;
    }

    .at-bulk-btn.late:hover {
        background: #fffbeb;
        border-color: var(--amber);
    }

    /* Search */
    .at-search-wrap {
        position: relative;
    }

    .at-search-ico {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .8rem;
        pointer-events: none;
    }

    .at-search-input {
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 8px 13px 8px 30px;
        font-size: .82rem;
        font-family: inherit;
        background: var(--light);
        color: var(--dark);
        width: 190px;
        transition: border-color .2s, box-shadow .2s;
    }

    .at-search-input:focus {
        outline: none;
        border-color: var(--mid);
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
        background: #fff;
    }

    .at-search-input::placeholder {
        color: #aab4c4;
    }

    /* ── Student rows ── */
    .at-student-row {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .at-student-row:last-child {
        border-bottom: none;
    }

    .at-student-row:hover {
        background: #f7f9fc;
    }

    .at-student-row.is-marked {
        background: #f0fdf4;
    }

    .at-student-row.is-marked:hover {
        background: #dcfce7;
    }

    /* Avatar */
    .at-av {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        border: 2px solid #fff;
        box-shadow: 0 2px 8px rgba(10, 45, 110, .12);
        margin-top: 2px;
    }

    .at-student-info {
        flex: 1;
        min-width: 0;
    }

    .at-sname {
        font-weight: 700;
        color: var(--dark);
        font-size: .9rem;
        margin-bottom: 2px;
    }

    .at-snum {
        font-size: .72rem;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 4px;
    }

    .at-snum i {
        font-size: .6rem;
    }

    .at-marked-info {
        font-size: .7rem;
        color: var(--green);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Status dropdown (original .attendance-select retained + styled) */
    .at-status-select {
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 7px 32px 7px 12px;
        font-size: .82rem;
        font-family: inherit;
        color: var(--dark);
        background: #f8fafd;
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
        min-width: 120px;
    }

    .at-status-select:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .at-status-select.val-present {
        background: #f0fdf4;
        border-color: #6ee7b7;
        color: var(--green);
        font-weight: 700;
    }

    .at-status-select.val-absent {
        background: #fff1f1;
        border-color: #fca5a5;
        color: var(--red);
        font-weight: 700;
    }

    .at-status-select.val-late {
        background: #fffbeb;
        border-color: #fcd34d;
        color: var(--amber);
        font-weight: 700;
    }

    .at-status-select.val-excused {
        background: #f5f3ff;
        border-color: #c4b5fd;
        color: var(--purple);
        font-weight: 700;
    }

    .at-sel-icon-wrap {
        position: relative;
        display: inline-block;
    }

    .at-sel-icon-wrap::after {
        content: '\f107';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
        font-size: .8rem;
    }

    /* Remarks input */
    .at-remarks-input {
        border: 1.5px solid var(--border);
        border-radius: 8px;
        padding: 7px 11px;
        font-size: .78rem;
        font-family: inherit;
        color: var(--dark);
        background: #f8fafd;
        width: 100%;
        transition: border-color .2s, box-shadow .2s;
    }

    .at-remarks-input:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .at-remarks-input::placeholder {
        color: #aab4c4;
    }

    /* Row controls layout */
    .at-row-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .at-row-right {
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
        min-width: 0;
    }

    /* Save footer */
    .at-save-footer {
        padding: 16px 22px;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        background: #fafbfd;
    }

    .at-save-btn {
        background: linear-gradient(135deg, var(--green), #065f46);
        color: #fff;
        border: none;
        border-radius: 11px;
        padding: 12px 28px;
        font-size: .92rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 16px rgba(5, 150, 105, .25);
        transition: transform .2s, box-shadow .2s;
    }

    .at-save-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(5, 150, 105, .35);
    }

    .at-save-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    /* Empty state */
    .at-empty {
        text-align: center;
        padding: 52px 24px;
        color: #94a3b8;
    }

    .at-empty i {
        font-size: 2.5rem;
        display: block;
        margin-bottom: 12px;
        opacity: .28;
    }

    .at-empty h6 {
        font-size: .95rem;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .at-empty p {
        font-size: .82rem;
        margin: 0;
    }

    /* No results */
    .at-no-results {
        display: none;
        text-align: center;
        padding: 28px;
        color: #94a3b8;
        font-size: .86rem;
    }

    .at-no-results i {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 8px;
        opacity: .3;
    }

    @media(max-width:992px) {
        .at-student-row {
            flex-wrap: wrap;
        }

        .at-row-controls {
            margin-top: 8px;
        }
    }

    @media(max-width:576px) {
        .at-top-title {
            font-size: 1.05rem;
        }

        .at-search-input {
            width: 140px;
        }
    }
</style>


<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="at-top">
    <div class="at-top-left">
        <div class="at-top-icon">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <div>
            <h1 class="at-top-title">Mark Attendance</h1>
            <p class="at-top-sub">
                <i class="fas fa-chalkboard-teacher" style="margin-right:5px;color:var(--mid);"></i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Lecturer'); ?>
            </p>
        </div>
    </div>
    <div class="at-date-pill">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('l, d M Y'); ?>
    </div>
</div>


<!-- ══════════════════════════════════════
     ALERT
══════════════════════════════════════ -->
<?php if ($msg['text']): ?>
    <div class="at-msg <?php echo $msg['type']; ?>" id="atAlert">
        <i class="fas fa-<?php
                            echo $msg['type'] === 'success' ? 'check-circle'
                                : ($msg['type'] === 'danger'  ? 'times-circle'
                                    : 'exclamation-triangle'); ?>"></i>
        <?php echo htmlspecialchars($msg['text']); ?>
        <button class="at-msg-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>


<div class="row g-4">

    <!-- ══════════════════════════════════════
         LEFT — COURSE & DATE SELECTOR
    ══════════════════════════════════════ -->
    <div class="col-xl-3 col-lg-4">
        <div class="at-card">

            <div class="at-card-head">
                <div class="at-card-head-ico"><i class="fas fa-sliders-h"></i></div>
                <div>
                    <h5>Session Filter</h5>
                    <p>Choose course and date</p>
                </div>
            </div>

            <div class="at-card-body">

                <!-- ── GET form — id unchanged ── -->
                <form method="GET">

                    <!-- Course -->
                    <div style="margin-bottom:18px;">
                        <label class="at-label"><i class="fas fa-book-open"></i> Course</label>
                        <div class="at-sel-wrap">
                            <select name="course" class="at-select" id="atCourseSelect"
                                onchange="atCoursePreview(this)" required>
                                <option value="">— Select Course —</option>
                                <?php while ($c = $courses_result->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($c['course_code']); ?>"
                                        data-name="<?php echo htmlspecialchars($c['course_name']); ?>"
                                        <?php echo (isset($_GET['course']) && $_GET['course'] == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name'] . " (" . $c['course_code'] . ")"); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <!-- Course preview -->
                        <div class="at-course-preview <?php echo isset($_GET['course']) && $_GET['course'] ? 'show' : ''; ?>"
                            id="atCoursePreviewBox">
                            <?php
                            if (isset($_GET['course']) && $_GET['course'] && $course_selected) {
                                $cpStmt = $conn->prepare("SELECT course_code FROM courses WHERE id=?");
                                $cpId   = intval($_GET['course']);
                                $cpStmt->bind_param("i", $cpId);
                                $cpStmt->execute();
                                $cpRow = $cpStmt->get_result()->fetch_assoc();
                            }
                            ?>
                            <div class="at-preview-code" id="atPreviewCode">
                                <?php echo isset($cpRow) ? htmlspecialchars($cpRow['course_code'] ?? '') : ''; ?>
                            </div>
                            <div class="at-preview-name" id="atPreviewName">
                                <?php echo $course_selected ? htmlspecialchars($course_selected['course_name']) : ''; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Date -->
                    <div style="margin-bottom:22px;">
                        <label class="at-label"><i class="fas fa-calendar-day"></i> Attendance Date</label>
                        <!-- max attribute prevents selecting a future date -->
                        <input type="date" name="date" class="at-input"
                            max="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($selected_date); ?>" required>
                        <p class="at-hint"><i class="fas fa-info-circle"></i> Future dates are not allowed</p>
                    </div>

                    <button type="submit" class="at-load-btn">
                        <i class="fas fa-users"></i> Load Students
                    </button>

                </form>

                <!-- Session stat summary (shown after course loaded) -->
                <?php if ($course_selected && $totalStudents > 0):
                    $presentC = $absentC = $lateC = $excusedC = 0;
                    foreach ($studentRows as $sr) {
                        switch ($sr['saved_status']) {
                            case 'Present':
                                $presentC++;
                                break;
                            case 'Absent':
                                $absentC++;
                                break;
                            case 'Late':
                                $lateC++;
                                break;
                            case 'Excused':
                                $excusedC++;
                                break;
                        }
                    }
                ?>
                    <div class="at-divider"></div>
                    <div style="font-size:.78rem;font-weight:700;color:var(--dark);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-chart-pie" style="color:var(--mid);"></i> Session Status
                    </div>
                    <div class="at-stat-grid">
                        <?php foreach (
                            [
                                ['Present', $presentC, '#f0fdf4', '#059669'],
                                ['Absent',  $absentC,  '#fff1f1', '#dc2626'],
                                ['Late',    $lateC,    '#fffbeb', '#d97706'],
                                ['Excused', $excusedC, '#f5f3ff', '#7c3aed'],
                            ] as [$lbl, $cnt, $bg, $col]
                        ): ?>
                            <div class="at-stat-mini" style="background:<?php echo $bg; ?>;">
                                <div class="at-stat-mini-num" style="color:<?php echo $col; ?>;"><?php echo $cnt; ?></div>
                                <div class="at-stat-mini-lbl" style="color:<?php echo $col; ?>;"><?php echo $lbl; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:10px;text-align:center;font-size:.74rem;color:var(--muted);">
                        <?php echo $markedCount; ?> of <?php echo $totalStudents; ?> students marked
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════
         RIGHT — ATTENDANCE TABLE
    ══════════════════════════════════════ -->
    <div class="col-xl-9 col-lg-8">
        <div class="at-card">

            <!-- Panel header -->
            <div class="at-panel-head">
                <h5>
                    <i class="fas fa-clipboard-list"></i>
                    Mark Attendance
                    <?php if ($course_selected): ?>
                        <span class="at-course-tag">
                            <i class="fas fa-book" style="font-size:.7rem;"></i>
                            <?php echo htmlspecialchars($course_selected['course_name']); ?>
                            &mdash; <?php echo date('d M Y', strtotime($selected_date)); ?>
                        </span>
                    <?php endif; ?>
                </h5>
            </div>

            <?php if ($course_selected && $totalStudents > 0): ?>

                <!-- Progress banner -->
                <div class="at-progress-banner">
                    <div class="at-progress-text">
                        <span id="atMarkedCounter"><?php echo $markedCount; ?></span>
                        of <?php echo $totalStudents; ?> marked
                    </div>
                    <div class="at-bar-wrap">
                        <div class="at-bar-track">
                            <div class="at-bar-fill" id="atBarFill"
                                style="width:<?php echo $totalStudents > 0 ? round($markedCount / $totalStudents * 100) : 0; ?>%;">
                            </div>
                        </div>
                    </div>
                    <div style="font-size:.74rem;font-weight:600;color:var(--muted);">
                        <?php echo date('d M Y', strtotime($selected_date)); ?>
                    </div>
                </div>

                <!-- Toolbar: bulk + search -->
                <div class="at-toolbar">
                    <div>
                        <div style="font-size:.74rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Mark All As:</div>
                        <!-- ORIGINAL: id="markAllPresent" preserved + extended -->
                        <div class="at-bulk-btns">
                            <button type="button" class="at-bulk-btn present"
                                id="markAllPresent" onclick="atMarkAll('Present')">
                                <i class="fas fa-check-circle"></i> Present
                            </button>
                            <button type="button" class="at-bulk-btn absent" onclick="atMarkAll('Absent')">
                                <i class="fas fa-times-circle"></i> Absent
                            </button>
                            <button type="button" class="at-bulk-btn late" onclick="atMarkAll('Late')">
                                <i class="fas fa-clock"></i> Late
                            </button>
                        </div>
                    </div>
                    <div class="at-search-wrap">
                        <i class="fas fa-search at-search-ico"></i>
                        <input type="text" class="at-search-input" id="atSearch"
                            placeholder="Search student…">
                    </div>
                </div>

                <!-- ── POST FORM — PHP unchanged ── -->
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_selected['id']); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">

                    <div id="atStudentList">
                        <?php foreach ($studentRows as $row):
                            $eid      = $row['enrollment_id'];
                            $saved    = $row['saved_status']  ?? '';
                            $savedRem = $row['saved_remarks'] ?? '';
                            $isMarked = !empty($saved);
                            $initial  = strtoupper(substr($row['student_name'], 0, 1));
                            $valClass = $saved ? 'val-' . strtolower($saved) : '';
                        ?>
                            <div class="at-student-row <?php echo $isMarked ? 'is-marked' : ''; ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($row['student_name'])); ?>"
                                id="atrow-<?php echo $eid; ?>">

                                <!-- Avatar -->
                                <div class="at-av"><?php echo $initial; ?></div>

                                <!-- Student info + controls -->
                                <div class="at-student-info">
                                    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;">

                                        <div>
                                            <div class="at-sname"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                            <div class="at-snum">
                                                <i class="fas fa-hashtag"></i>
                                                <?php echo htmlspecialchars($row['student_number']); ?>
                                            </div>
                                            <?php if ($isMarked && !empty($row['marked_by_name'])): ?>
                                                <div class="at-marked-info">
                                                    <i class="fas fa-check-circle"></i>
                                                    Marked by <?php echo htmlspecialchars($row['marked_by_name']); ?>
                                                    <?php if ($row['marked_at']): ?>
                                                        &middot; <?php echo date('d M Y, h:i A', strtotime($row['marked_at'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Status dropdown (class="attendance-select" ORIGINAL preserved) -->
                                        <div class="at-sel-icon-wrap">
                                            <select name="attendance[<?php echo $eid; ?>]"
                                                class="at-status-select attendance-select <?php echo $valClass; ?>"
                                                id="sel-<?php echo $eid; ?>"
                                                onchange="atOnChange(<?php echo $eid; ?>, this)">
                                                <?php
                                                $statuses = ['Present', 'Absent', 'Late', 'Excused'];
                                                foreach ($statuses as $st):
                                                    $sel = ($saved === $st) ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo $st; ?>" <?php echo $sel; ?>><?php echo $st; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                    </div>

                                    <!-- Remarks -->
                                    <div style="margin-top:8px;">
                                        <input type="text"
                                            name="remarks[<?php echo $eid; ?>]"
                                            class="at-remarks-input"
                                            placeholder="Remarks (optional)"
                                            maxlength="255"
                                            value="<?php echo htmlspecialchars($savedRem); ?>">
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- No search results -->
                    <div class="at-no-results" id="atNoResults">
                        <i class="fas fa-search"></i>
                        No students match your search.
                    </div>

                    <!-- Save footer -->
                    <div class="at-save-footer">
                        <div style="font-size:.78rem;color:var(--muted);">
                            <i class="fas fa-info-circle me-1"></i>
                            <?php echo $totalStudents; ?> students in this session
                        </div>
                        <button type="submit" class="at-save-btn" id="atSaveBtn">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>

                </form>

            <?php elseif ($course_selected && $totalStudents === 0): ?>
                <div class="at-empty">
                    <i class="fas fa-users-slash"></i>
                    <h6>No Students Enrolled</h6>
                    <p>This course has no enrolled students. <a href="enroll.php" style="color:var(--mid);font-weight:700;">Enroll students →</a></p>
                </div>
            <?php else: ?>
                <div class="at-empty">
                    <i class="fas fa-clipboard-list"></i>
                    <h6>Select a Course</h6>
                    <p>Choose a course and date from the left panel, then click <strong>Load Students</strong>.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div><!-- /row -->


<!-- ════════════════════════════════════════════════
     JAVASCRIPT
     ── ORIGINAL: markAllPresent (id="markAllPresent" + class="attendance-select" unchanged)
     ── NEW 1: Course preview
     ── NEW 2: Mark all extended (Absent / Late too)
     ── NEW 3: Status dropdown colour update
     ── NEW 4: Progress bar live update
     ── NEW 5: Live search
     ── NEW 6: Submit spinner
     ── NEW 7: Auto-dismiss alert
════════════════════════════════════════════════ -->
<script>
    // ════════════════════════════════════
    //  ORIGINAL — Mark All Present
    //  (id="markAllPresent" + .attendance-select unchanged)
    // ════════════════════════════════════
    document.getElementById('markAllPresent')?.addEventListener('click', function() {
        document.querySelectorAll('.attendance-select').forEach(function(sel) {
            sel.value = 'Present';
            atStyleSelect(sel);
        });
        atUpdateProgress();
    });


    // ════════════════════════════════════
    //  NEW 1 — Course preview
    // ════════════════════════════════════
    function atCoursePreview(select) {
        const opt = select.options[select.selectedIndex];
        const box = document.getElementById('atCoursePreviewBox');
        const code = document.getElementById('atPreviewCode');
        const name = document.getElementById('atPreviewName');
        if (opt.value) {
            code.textContent = opt.getAttribute('data-code') || '';
            name.textContent = opt.getAttribute('data-name') || '';
            box.classList.add('show');
        } else {
            box.classList.remove('show');
        }
    }

    // Init preview on load
    const atInitSelect = document.getElementById('atCourseSelect');
    if (atInitSelect && atInitSelect.value) atCoursePreview(atInitSelect);


    // ════════════════════════════════════
    //  NEW 2 — Mark all extended
    // ════════════════════════════════════
    function atMarkAll(status) {
        document.querySelectorAll('.at-student-row').forEach(function(row) {
            if (row.style.display === 'none') return;
            const eid = row.id.replace('atrow-', '');
            const sel = document.getElementById('sel-' + eid);
            if (sel) {
                sel.value = status;
                atStyleSelect(sel);
                row.classList.add('is-marked');
            }
        });
        atUpdateProgress();
    }


    // ════════════════════════════════════
    //  NEW 3 — Status dropdown colour
    // ════════════════════════════════════
    function atStyleSelect(sel) {
        sel.className = sel.className.replace(/val-\S+/g, '').trim();
        if (sel.value) {
            sel.classList.add('val-' + sel.value.toLowerCase());
        }
    }

    function atOnChange(eid, sel) {
        atStyleSelect(sel);
        const row = document.getElementById('atrow-' + eid);
        if (row) row.classList.add('is-marked');
        atUpdateProgress();
    }

    // Style all selects on load
    document.querySelectorAll('.attendance-select').forEach(atStyleSelect);


    // ════════════════════════════════════
    //  NEW 4 — Progress bar live update
    // ════════════════════════════════════
    const atTotal = <?php echo $totalStudents; ?>;

    function atUpdateProgress() {
        const rows = document.querySelectorAll('.at-student-row[data-name]');
        let counted = 0;
        rows.forEach(function(row) {
            if (row.classList.contains('is-marked')) counted++;
        });
        const counter = document.getElementById('atMarkedCounter');
        const fill = document.getElementById('atBarFill');
        if (counter) counter.textContent = counted;
        if (fill && atTotal > 0) fill.style.width = Math.round(counted / atTotal * 100) + '%';
    }


    // ════════════════════════════════════
    //  NEW 5 — Live search
    // ════════════════════════════════════
    const atSearch = document.getElementById('atSearch');
    if (atSearch) {
        atSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll('.at-student-row[data-name]');
            let visible = 0;
            rows.forEach(function(row) {
                const match = !q || (row.dataset.name || '').includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            const noRes = document.getElementById('atNoResults');
            if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
        });
    }


    // ════════════════════════════════════
    //  NEW 6 — Submit spinner
    // ════════════════════════════════════
    const atForm = document.getElementById('attendanceForm');
    if (atForm) {
        atForm.addEventListener('submit', function() {
            const btn = document.getElementById('atSaveBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
            }
        });
    }


    // ════════════════════════════════════
    //  NEW 7 — Auto-dismiss alert
    // ════════════════════════════════════
    const atAlert = document.getElementById('atAlert');
    if (atAlert) {
        setTimeout(function() {
            atAlert.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
            atAlert.style.opacity = '0';
            atAlert.style.maxHeight = '0';
            atAlert.style.overflow = 'hidden';
            atAlert.style.padding = '0';
            atAlert.style.margin = '0';
            atAlert.style.borderWidth = '0';
        }, 4500);
    }
</script>

<?php include "includes/footer.php"; ?>