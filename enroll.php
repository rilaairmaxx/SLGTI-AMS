<?php
require_once "config/db.php";
require_once "includes/header.php";

// Admin-only page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = ['type' => '', 'text' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {

    $student_ids = $_POST['student_id'] ?? [];
    if (!is_array($student_ids)) $student_ids = [$student_ids];
    $student_ids = array_filter(array_map('intval', $student_ids));
    $course_id   = intval($_POST['course_id'] ?? 0);

    if (empty($student_ids) || $course_id <= 0) {
        $msg = ['type' => 'warning', 'text' => 'Please select at least one student and a course.'];
    } else {
        $enrolled = 0;
        $skipped  = 0;

        $check = $conn->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=?");
        $stmt  = $conn->prepare("INSERT INTO enrollments(student_id, course_id, enrollment_date) VALUES(?, ?, ?)");
        $enrollDate = date('Y-m-d');

        foreach ($student_ids as $student_id) {
            $check->bind_param("ii", $student_id, $course_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $skipped++;
            } else {
                $stmt->bind_param("iis", $student_id, $course_id, $enrollDate);
                $stmt->execute();
                $enrolled++;
            }
            $check->free_result();
        }

        if ($enrolled > 0 && $skipped === 0) {
            $msg = ['type' => 'success', 'text' => "Successfully enrolled {$enrolled} student(s)."];
        } elseif ($enrolled > 0) {
            $msg = ['type' => 'success', 'text' => "Enrolled {$enrolled} student(s). {$skipped} already enrolled (skipped)."];
        } else {
            $msg = ['type' => 'warning', 'text' => "All selected student(s) are already enrolled in this course."];
        }
    }
}

if (isset($_GET['unenroll']) && is_numeric($_GET['unenroll'])) {
    $id = intval($_GET['unenroll']);

    // Block unenrollment if attendance records exist for this enrollment
    $checkAtt = $conn->prepare("SELECT id FROM attendance WHERE enrollment_id=? LIMIT 1");
    $checkAtt->bind_param("i", $id);
    $checkAtt->execute();
    $checkAtt->store_result();

    if ($checkAtt->num_rows > 0) {
        $msg = ['type' => 'danger', 'text' => 'Cannot remove enrollment. Attendance exists.'];
    } else {
        $delete = $conn->prepare("DELETE FROM enrollments WHERE id=?");
        $delete->bind_param("i", $id);

        if ($delete->execute()) {
            $msg = ['type' => 'success', 'text' => 'Enrollment removed successfully.'];
        } else {
            $msg = ['type' => 'danger', 'text' => 'Failed to remove enrollment.'];
        }
    }
}

// AJAX: return student details for the info panel
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && isset($_GET['action']) && $_GET['action'] === 'get_student') {
    $sid  = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, student_number, student_name, email, phone, gender, status FROM students WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    header('Content-Type: application/json');
    echo json_encode($stmt->get_result()->fetch_assoc() ?: null);
    exit();
}

// Fetch students and courses for the enrollment form dropdowns
$students = $conn->query("SELECT id, student_number, student_name, email, phone, gender FROM students ORDER BY student_name ASC");
$courses  = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_name ASC");

// Fetch all enrollments joined with student and course details for the table
$enrollment_list = $conn->query("
    SELECT e.id, e.enrollment_date, e.status AS enroll_status,
           s.student_number, s.student_name, c.course_code, c.course_name
    FROM enrollments e
    JOIN students s ON s.id = e.student_id
    JOIN courses  c ON c.id = e.course_id
    ORDER BY e.id DESC
");

// Build students array for JS searchable dropdown
$studentsArr = [];
$students->data_seek(0);
while ($sr = $students->fetch_assoc()) $studentsArr[] = $sr;
$students->data_seek(0);
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
    }

    /* ── Page header ── */
    .en-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .en-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .en-top-icon {
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

    .en-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .en-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .en-date-pill {
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
    .en-msg {
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

    .en-msg.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .en-msg.warning {
        background: #fffbeb;
        color: #92400e;
        border-left: 4px solid var(--amber);
    }

    .en-msg.danger {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    .en-msg i {
        font-size: 1rem;
        flex-shrink: 0;
    }

    .en-msg-close {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        opacity: .5;
        font-size: .9rem;
        padding: 0 2px;
    }

    .en-msg-close:hover {
        opacity: 1;
    }

    /* ── Card ── */
    .en-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .09);
        border: 1px solid var(--border);
        overflow: hidden;
        height: 100%;
    }

    .en-card-head {
        background: linear-gradient(135deg, var(--royal), var(--mid));
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .en-card-head-ico {
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

    .en-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 700;
        color: #fff;
    }

    .en-card-head p {
        margin: 2px 0 0;
        font-size: .72rem;
        color: rgba(255, 255, 255, .65);
    }

    .en-card-body {
        padding: 24px 22px;
    }

    /* ── Form fields ── */
    .en-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
        letter-spacing: .02em;
    }

    .en-label i {
        color: var(--mid);
        font-size: .8rem;
    }

    .en-label .req {
        color: var(--red);
        margin-left: 2px;
    }

    .en-select {
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

    .en-select:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    /* Select wrapper with arrow */
    .en-sel-wrap {
        position: relative;
    }

    .en-sel-wrap::after {
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

    /* Selected preview */
    .en-preview {
        display: none;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
        padding: 9px 13px;
        border-radius: 9px;
        background: var(--light);
        border: 1px solid var(--border);
        font-size: .78rem;
        font-weight: 600;
        color: var(--dark);
        animation: fadeIn .2s ease;
    }

    .en-preview.show {
        display: flex;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .en-preview-icon {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .8rem;
    }

    .en-preview-icon.stu {
        background: linear-gradient(135deg, #d1fae5, #ecfdf5);
        color: var(--green);
    }

    .en-preview-icon.crs {
        background: linear-gradient(135deg, #dbeafe, #eff6ff);
        color: var(--mid);
    }

    .en-divider {
        height: 1px;
        background: var(--border);
        margin: 20px 0;
    }

    /* Submit button */
    .en-btn {
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

    .en-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .32);
    }

    .en-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    /* ── Table panel ── */
    .en-tbl-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
    }

    .en-tbl-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 800;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .en-tbl-head h5 i {
        color: var(--mid);
    }

    .en-tbl-count {
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border-radius: 20px;
        padding: 4px 14px;
        font-size: .74rem;
        font-weight: 700;
    }

    /* Search bar */
    .en-search-bar {
        padding: 12px 22px;
        border-bottom: 1px solid var(--border);
        background: var(--light);
    }

    .en-search-wrap {
        position: relative;
    }

    .en-search-ico {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .85rem;
        pointer-events: none;
    }

    .en-search-input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 9px 14px 9px 34px;
        font-size: .86rem;
        font-family: inherit;
        background: #fff;
        color: var(--dark);
        transition: border-color .2s, box-shadow .2s;
    }

    .en-search-input:focus {
        outline: none;
        border-color: var(--mid);
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .en-search-input::placeholder {
        color: #aab4c4;
    }

    /* ── Table ── */
    .table-responsive { max-height: 500px; overflow-y: auto; overflow-x: auto; }
    .table-responsive::-webkit-scrollbar { width: 6px; height: 6px; }
    .table-responsive::-webkit-scrollbar-track { background: transparent; }
    .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .en-tbl {
        width: 100%;
        border-collapse: collapse;
    }

    .en-tbl thead tr {
        background: var(--light);
    }

    .en-tbl thead th {
        padding: 12px 16px;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--muted);
        white-space: nowrap;
        position: sticky; top: 0; z-index: 10; background: var(--light);
        box-shadow: inset 0 -2px 0 var(--border);
    }

    .en-tbl thead th:first-child {
        padding-left: 22px;
    }

    .en-tbl thead th:last-child {
        padding-right: 22px;
        text-align: center;
    }

    .en-tbl tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .en-tbl tbody tr:last-child {
        border-bottom: none;
    }

    .en-tbl tbody tr:hover {
        background: #f7f9fc;
    }

    .en-tbl td {
        padding: 13px 16px;
        vertical-align: middle;
    }

    .en-tbl td:first-child {
        padding-left: 22px;
    }

    .en-tbl td:last-child {
        padding-right: 22px;
        text-align: center;
    }

    /* Student avatar */
    .en-av {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        border: 2px solid #fff;
        box-shadow: 0 2px 8px rgba(10, 45, 110, .12);
    }

    .en-sname {
        font-weight: 700;
        color: var(--dark);
        font-size: .88rem;
    }

    .en-snum {
        font-size: .72rem;
        color: var(--muted);
        margin-top: 1px;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .en-snum i {
        font-size: .6rem;
    }

    /* Course pill */
    .en-course-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 14px;
        border-radius: 10px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
    }

    .en-course-code {
        font-size: .78rem;
        font-weight: 800;
        color: var(--mid);
        font-family: monospace;
        letter-spacing: .06em;
    }

    .en-course-name {
        font-size: .78rem;
        color: var(--dark);
        font-weight: 500;
    }

    /* Unenroll button */
    .en-act-btn {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        border: 1.5px solid var(--border);
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        text-decoration: none;
        transition: all .2s;
        cursor: pointer;
    }

    .en-act-btn:hover {
        background: #fff1f1;
        border-color: var(--red);
        transform: translateY(-1px);
    }

    .en-act-btn .fa-trash {
        color: var(--red);
    }

    /* Empty / no-results */
    .en-empty {
        text-align: center;
        padding: 44px 24px;
        color: #94a3b8;
    }

    .en-empty i {
        font-size: 2.2rem;
        display: block;
        margin-bottom: 12px;
        opacity: .3;
    }

    .en-empty p {
        font-size: .88rem;
        margin: 0;
    }

    .en-no-results {
        display: none;
        text-align: center;
        padding: 28px;
        color: #94a3b8;
        font-size: .86rem;
    }

    .en-no-results i {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 8px;
        opacity: .3;
    }

    /* Responsive */
    @media(max-width:768px) {

        .en-tbl thead th:nth-child(2),
        .en-tbl td:nth-child(2) {
            display: none;
        }

        .en-card-body {
            padding: 18px 16px;
        }
    }

    @media(max-width:576px) {
        .en-top-title {
            font-size: 1.05rem;
        }
    }

    /* ── Searchable student dropdown ── */
    .en-search-student {
        position: relative;
    }

    .en-search-student-input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 11px 14px 11px 38px;
        font-size: .9rem;
        font-family: inherit;
        color: var(--dark);
        background: #f8fafd;
        transition: border-color .2s, box-shadow .2s;
    }

    .en-search-student-input:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .en-search-student-ico {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .85rem;
        pointer-events: none;
    }

    .en-student-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(10, 45, 110, .12);
        max-height: 220px;
        overflow-y: auto;
        z-index: 200;
    }

    .en-student-dropdown.open {
        display: block;
    }

    .en-student-opt {
        padding: 10px 14px;
        cursor: pointer;
        font-size: .85rem;
        border-bottom: 1px solid var(--border);
        transition: background .15s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .en-student-opt:last-child {
        border-bottom: none;
    }

    .en-student-opt:hover,
    .en-student-opt.active {
        background: var(--light);
    }

    .en-student-opt-av {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .78rem;
        font-weight: 800;
        flex-shrink: 0;
    }

    .en-student-opt-name {
        font-weight: 700;
        color: var(--dark);
    }

    .en-student-opt-num {
        font-size: .7rem;
        color: var(--muted);
    }

    .en-no-student-opt {
        padding: 14px;
        text-align: center;
        color: var(--muted);
        font-size: .82rem;
    }

    /* ── Student info panel (read-only) ── */
    .en-stu-info {
        display: none;
        background: linear-gradient(135deg, #f0f4fa, #e8f0fe);
        border: 1.5px solid #bfdbfe;
        border-radius: 12px;
        padding: 14px 16px;
        margin-top: 10px;
        animation: fadeIn .25s ease;
    }

    .en-stu-info.show {
        display: block;
    }

    .en-stu-info-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .78rem;
        color: var(--dark);
        margin-bottom: 6px;
    }

    .en-stu-info-row:last-child {
        margin-bottom: 0;
    }

    .en-stu-info-row i {
        color: var(--mid);
        width: 14px;
        flex-shrink: 0;
    }

    .en-stu-info-label {
        color: var(--muted);
        min-width: 60px;
    }

    .en-stu-info-val {
        font-weight: 700;
    }

    /* ── Stepper ── */
    .en-stepper {
        display: flex;
        align-items: center;
        margin-bottom: 22px;
        padding: 0 4px;
    }

    .en-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
        cursor: default;
    }

    .en-step-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .78rem;
        font-weight: 800;
        border: 2px solid var(--border);
        background: #fff;
        color: var(--muted);
        transition: all .3s;
        position: relative;
        z-index: 1;
    }

    .en-step.active .en-step-circle {
        background: var(--mid);
        border-color: var(--mid);
        color: #fff;
        box-shadow: 0 0 0 4px rgba(20, 86, 200, .15);
    }

    .en-step.done .en-step-circle {
        background: var(--green);
        border-color: var(--green);
        color: #fff;
    }

    .en-step-label {
        font-size: .65rem;
        font-weight: 700;
        color: var(--muted);
        margin-top: 5px;
        text-align: center;
        white-space: nowrap;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .en-step.active .en-step-label {
        color: var(--mid);
    }

    .en-step.done .en-step-label {
        color: var(--green);
    }

    .en-step-line {
        flex: 1;
        height: 2px;
        background: var(--border);
        margin: 0 4px;
        margin-bottom: 18px;
        transition: background .3s;
    }

    .en-step-line.done {
        background: var(--green);
    }

    /* ── Empty state ── */
    .en-empty-state {
        text-align: center;
        padding: 32px 20px;
        background: linear-gradient(135deg, #f8fafd, #f0f4fa);
        border: 1.5px dashed var(--border);
        border-radius: 14px;
        margin-bottom: 16px;
    }

    .en-empty-state i {
        font-size: 2rem;
        color: #c8d0db;
        display: block;
        margin-bottom: 10px;
    }

    .en-empty-state p {
        font-size: .84rem;
        color: var(--muted);
        margin: 0 0 12px;
    }

    .en-empty-state a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border-radius: 9px;
        padding: 8px 18px;
        font-size: .8rem;
        font-weight: 700;
        text-decoration: none;
        transition: transform .2s, box-shadow .2s;
    }

    .en-empty-state a:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(10, 45, 110, .25);
    }


    .en-input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 11px 14px;
        font-size: .88rem;
        font-family: inherit;
        color: var(--dark);
        background: #f8fafd;
        transition: border-color .2s;
    }

    .en-input:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .en-input[readonly] {
        background: var(--light);
        color: var(--muted);
        cursor: default;
    }
</style>


<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="en-top">
    <div class="en-top-left">
        <div class="en-top-icon">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div>
            <h1 class="en-top-title">Student Enrollment</h1>
            <p class="en-top-sub">Assign students to courses and manage enrollments</p>
        </div>
    </div>
    <div class="en-date-pill">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('l, d M Y'); ?>
    </div>
</div>


<!-- ══════════════════════════════════════
     ALERT
══════════════════════════════════════ -->
<?php if ($msg['text']): ?>
    <div class="en-msg <?php echo $msg['type']; ?>" id="enAlert">
        <i class="fas fa-<?php
                            echo $msg['type'] === 'success' ? 'check-circle'
                                : ($msg['type'] === 'danger'  ? 'times-circle'
                                    : 'exclamation-triangle'); ?>"></i>
        <?php echo $msg['text']; ?>
        <button class="en-msg-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>


<div class="row g-4">

    <!-- ══════════════════════════════════════
         LEFT — ENROLL FORM
    ══════════════════════════════════════ -->
    <div class="col-xl-4 col-lg-5">
        <div class="en-card">

            <div class="en-card-head">
                <div class="en-card-head-ico">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h5>Enroll Student</h5>
                    <p>Select a student and assign a course</p>
                </div>
            </div>

            <div class="en-card-body">

                <form method="POST" id="enrollForm">

                    <!-- ── Stepper ── -->
                    <div class="en-stepper" id="enrollStepper">
                        <div class="en-step active" id="step1">
                            <div class="en-step-circle" id="step1Circle">1</div>
                            <div class="en-step-label">Student</div>
                        </div>
                        <div class="en-step-line" id="line1"></div>
                        <div class="en-step" id="step2">
                            <div class="en-step-circle" id="step2Circle">2</div>
                            <div class="en-step-label">Course</div>
                        </div>
                        <div class="en-step-line" id="line2"></div>
                        <div class="en-step" id="step3">
                            <div class="en-step-circle" id="step3Circle">3</div>
                            <div class="en-step-label">Confirm</div>
                        </div>
                    </div>

                    <!-- ── Step 1: Searchable Student ── -->
                    <div style="margin-bottom:6px;">
                        <label class="en-label">
                            <i class="fas fa-user-graduate"></i> Search Student <span class="req">*</span>
                        </label>
                        <div class="en-search-student" id="stuSearchWrap">
                            <i class="fas fa-search en-search-student-ico"></i>
                            <input type="text" id="stuSearchInput" class="en-search-student-input"
                                placeholder="Type student name or ID…" autocomplete="off">
                            <div class="en-student-dropdown" id="stuDropdown"></div>
                        </div>
                        <div id="stuHiddenInputs"></div>
                        <div id="stuCountBadge" style="display:none;margin-top:8px;padding:6px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:.75rem;font-weight:700;color:var(--mid);">
                            <i class="fas fa-users"></i> <span id="stuCountText">0</span> student(s) selected
                            <button type="button" onclick="clearStudents()" style="margin-left:8px;background:none;border:none;color:var(--red);cursor:pointer;font-size:.75rem;font-weight:700;">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                        <div id="stuTags" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
                    </div>

                    <!-- Student info panel (read-only) -->
                    <div class="en-stu-info" id="stuInfoPanel">
                        <div style="font-size:.72rem;font-weight:800;color:var(--mid);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em;">
                            <i class="fas fa-id-card"></i> Student Info (last selected)
                        </div>
                        <div class="en-stu-info-row"><i class="fas fa-hashtag"></i><span class="en-stu-info-label">ID:</span><span class="en-stu-info-val" id="infoNum">—</span></div>
                        <div class="en-stu-info-row"><i class="fas fa-user"></i><span class="en-stu-info-label">Name:</span><span class="en-stu-info-val" id="infoName">—</span></div>
                        <div class="en-stu-info-row"><i class="fas fa-envelope"></i><span class="en-stu-info-label">Email:</span><span class="en-stu-info-val" id="infoEmail">—</span></div>
                        <div class="en-stu-info-row"><i class="fas fa-phone"></i><span class="en-stu-info-label">Phone:</span><span class="en-stu-info-val" id="infoPhone">—</span></div>
                        <div class="en-stu-info-row"><i class="fas fa-venus-mars"></i><span class="en-stu-info-label">Gender:</span><span class="en-stu-info-val" id="infoGender">—</span></div>
                    </div>

                    <div class="en-divider"></div>

                    <!-- ── Step 2: Course ── -->
                    <?php $courses->data_seek(0);
                    $courseCount = $courses->num_rows; ?>
                    <?php if ($courseCount === 0): ?>
                        <div class="en-empty-state">
                            <i class="fas fa-book-open"></i>
                            <p>No courses available yet.</p>
                            <a href="add_course.php"><i class="fas fa-plus-circle"></i> Add Course to Begin</a>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom:16px;">
                            <label class="en-label">
                                <i class="fas fa-book-open"></i> Course <span class="req">*</span>
                            </label>
                            <div class="en-sel-wrap">
                                <select name="course_id" class="en-select" id="courseSelect" required
                                    onchange="showCoursePreview(this); updateStepper();">
                                    <option value="">— Choose Course —</option>
                                    <?php while ($c = $courses->fetch_assoc()): ?>
                                        <option value="<?php echo $c['id']; ?>"
                                            data-label="<?php echo htmlspecialchars($c['course_name']); ?>"
                                            data-sub="<?php echo htmlspecialchars($c['course_code']); ?>">
                                            <?php echo htmlspecialchars($c['course_code'] . " — " . $c['course_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="en-preview" id="coursePreview">
                                <div class="en-preview-icon crs"><i class="fas fa-book-open"></i></div>
                                <div>
                                    <div style="font-weight:700;color:var(--dark);" id="coursePreviewName"></div>
                                    <div style="font-size:.7rem;color:var(--muted);font-family:monospace;" id="coursePreviewCode"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ── Step 3: Confirm — Enrollment Date ── -->
                    <div style="margin-bottom:16px;">
                        <label class="en-label">
                            <i class="fas fa-calendar-check"></i> Enrollment Date
                        </label>
                        <input type="text" class="en-input" value="<?php echo date('d M Y'); ?>" readonly>
                    </div>

                    <!-- Submit -->
                    <input type="hidden" name="enroll" value="1">
                    <button type="submit" class="en-btn" id="enrollBtn" <?php echo $courseCount === 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-user-check" id="enrollBtnIco"></i>
                        <span id="enrollBtnTxt">Enroll Students</span>
                    </button>

                </form>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════
         RIGHT — ENROLLMENTS TABLE
    ══════════════════════════════════════ -->
    <div class="col-xl-8 col-lg-7">
        <div class="en-card">

            <div class="en-tbl-head">
                <h5>
                    <i class="fas fa-list-ul"></i>
                    Enrollment List
                </h5>
                <span class="en-tbl-count">Total: <?php echo $enrollment_list->num_rows; ?></span>
            </div>

            <!-- NEW: search bar -->
            <div class="en-search-bar">
                <div class="en-search-wrap">
                    <i class="fas fa-search en-search-ico"></i>
                    <input type="text" class="en-search-input" id="enrollSearch"
                        placeholder="Search by student name, number or course…">
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="en-tbl">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Unenroll</th>
                        </tr>
                    </thead>
                    <tbody id="enrollTbody">
                        <?php if ($enrollment_list->num_rows > 0): ?>
                            <?php while ($row = $enrollment_list->fetch_assoc()):
                                $initial = strtoupper(substr($row['student_name'], 0, 1));
                            ?>
                                <tr data-student="<?php echo strtolower(htmlspecialchars($row['student_name'])); ?>"
                                    data-num="<?php echo strtolower(htmlspecialchars($row['student_number'])); ?>"
                                    data-course="<?php echo strtolower(htmlspecialchars($row['course_name'] . ' ' . $row['course_code'])); ?>">

                                    <td>
                                        <div style="display:flex;align-items:center;gap:12px;">
                                            <div class="en-av"><?php echo $initial; ?></div>
                                            <div>
                                                <div class="en-sname"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                <div class="en-snum"><i class="fas fa-hashtag"></i><?php echo htmlspecialchars($row['student_number']); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="en-course-pill">
                                            <span class="en-course-code"><?php echo htmlspecialchars($row['course_code']); ?></span>
                                            <span style="color:#c8d0db;font-size:.7rem;">|</span>
                                            <span class="en-course-name"><?php echo htmlspecialchars($row['course_name']); ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <?php
                                        $es = $row['enroll_status'] ?? 'active';
                                        $esColor = $es === 'active' ? '#059669' : ($es === 'completed' ? '#1456c8' : '#dc2626');
                                        $esBg    = $es === 'active' ? '#ecfdf5' : ($es === 'completed' ? '#eff6ff' : '#fff1f1');
                                        ?>
                                        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;background:<?php echo $esBg; ?>;color:<?php echo $esColor; ?>;">
                                            <span style="width:6px;height:6px;border-radius:50%;background:<?php echo $esColor; ?>;"></span>
                                            <?php echo ucfirst($es); ?>
                                        </span>
                                    </td>

                                    <td style="font-size:.76rem;color:var(--muted);white-space:nowrap;">
                                        <?php echo !empty($row['enrollment_date']) ? date('d M Y', strtotime($row['enrollment_date'])) : '<span style="color:#c8d0db;">—</span>'; ?>
                                    </td>

                                    <td>
                                        <a href="?unenroll=<?php echo $row['id']; ?>"
                                            class="en-act-btn"
                                            onclick="return confirm('Remove this enrollment?')"
                                            title="Remove enrollment">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>

                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="en-empty">
                                        <i class="fas fa-user-graduate"></i>
                                        <p>No enrollments found. Use the form to enroll students.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- No search results -->
                <div class="en-no-results" id="enNoResults">
                    <i class="fas fa-search"></i>
                    No enrollments match your search.
                </div>
            </div>

        </div>
    </div>

</div><!-- /row -->


<!-- ════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════ -->
<script>
    // ── Students data from PHP ──
    const allStudents = <?php
                        $out = [];
                        $students->data_seek(0);
                        while ($sr = $students->fetch_assoc()) {
                            $out[] = [
                                'id'     => (int)$sr['id'],
                                'number' => $sr['student_number'],
                                'name'   => $sr['student_name'],
                                'email'  => $sr['email']  ?? '',
                                'phone'  => $sr['phone']  ?? '',
                                'gender' => $sr['gender'] ?? '',
                            ];
                        }
                        echo json_encode($out);
                        ?>;

    let selectedStudents = {};

    // ── Searchable student dropdown ──
    const stuInput = document.getElementById('stuSearchInput');
    const stuDropdown = document.getElementById('stuDropdown');

    stuInput.addEventListener('input', function() {
        renderDropdown(this.value.trim().toLowerCase());
    });
    stuInput.addEventListener('focus', function() {
        renderDropdown(this.value.trim().toLowerCase());
    });
    document.addEventListener('click', function(e) {
        if (!document.getElementById('stuSearchWrap').contains(e.target))
            stuDropdown.classList.remove('open');
    });

    function renderDropdown(q) {
        const filtered = q ?
            allStudents.filter(s => s.name.toLowerCase().includes(q) || s.number.toLowerCase().includes(q)) :
            allStudents;
        stuDropdown.innerHTML = '';
        if (filtered.length === 0) {
            stuDropdown.innerHTML = '<div class="en-no-student-opt">No students found.</div>';
        } else {
            filtered.slice(0, 40).forEach(s => {
                const div = document.createElement('div');
                div.className = 'en-student-opt' + (selectedStudents[s.id] ? ' active' : '');
                div.innerHTML = `<div class="en-student-opt-av">${s.name.charAt(0).toUpperCase()}</div>
                    <div><div class="en-student-opt-name">${escHtml(s.name)}</div>
                    <div class="en-student-opt-num">${escHtml(s.number)}</div></div>
                    ${selectedStudents[s.id] ? '<i class="fas fa-check" style="margin-left:auto;color:var(--green);"></i>' : ''}`;
                div.addEventListener('click', function() {
                    toggleStudent(s);
                });
                stuDropdown.appendChild(div);
            });
        }
        stuDropdown.classList.add('open');
    }

    function toggleStudent(s) {
        if (selectedStudents[s.id]) delete selectedStudents[s.id];
        else {
            selectedStudents[s.id] = s;
            showStudentInfo(s);
        }
        updateStudentUI();
        updateStepper();
        stuInput.value = '';
        stuDropdown.classList.remove('open');
    }

    function showStudentInfo(s) {
        document.getElementById('infoNum').textContent = s.number || '—';
        document.getElementById('infoName').textContent = s.name || '—';
        document.getElementById('infoEmail').textContent = s.email || '—';
        document.getElementById('infoPhone').textContent = s.phone || '—';
        document.getElementById('infoGender').textContent = s.gender ? s.gender.charAt(0).toUpperCase() + s.gender.slice(1) : '—';
        document.getElementById('stuInfoPanel').classList.add('show');
    }

    function updateStudentUI() {
        const ids = Object.keys(selectedStudents);
        // Hidden inputs
        const hw = document.getElementById('stuHiddenInputs');
        hw.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'student_id[]';
            inp.value = id;
            hw.appendChild(inp);
        });
        // Tags
        const tw = document.getElementById('stuTags');
        tw.innerHTML = '';
        ids.forEach(id => {
            const s = selectedStudents[id];
            const tag = document.createElement('div');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;padding:4px 10px;font-size:.74rem;font-weight:700;color:var(--mid);';
            tag.innerHTML = `${escHtml(s.name)} <button type="button" onclick="removeStudent(${id})" style="background:none;border:none;cursor:pointer;color:var(--red);font-size:.7rem;padding:0;"><i class="fas fa-times"></i></button>`;
            tw.appendChild(tag);
        });
        // Badge
        const badge = document.getElementById('stuCountBadge');
        document.getElementById('stuCountText').textContent = ids.length;
        badge.style.display = ids.length > 0 ? 'block' : 'none';
        if (ids.length === 0) document.getElementById('stuInfoPanel').classList.remove('show');
    }

    function removeStudent(id) {
        delete selectedStudents[id];
        updateStudentUI();
    }

    function clearStudents() {
        selectedStudents = {};
        updateStudentUI();
    }

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Course preview ──
    function showCoursePreview(select) {
        const opt = select.options[select.selectedIndex];
        const preview = document.getElementById('coursePreview');
        if (opt.value) {
            document.getElementById('coursePreviewName').textContent = opt.getAttribute('data-label') || '';
            document.getElementById('coursePreviewCode').textContent = opt.getAttribute('data-sub') || '';
            preview.classList.add('show');
        } else {
            preview.classList.remove('show');
        }
    }

    // ── Stepper logic ──
    function updateStepper() {
        const ids = Object.keys(selectedStudents);
        const hasStu = ids.length > 0;
        const hasCourse = document.getElementById('courseSelect') &&
            document.getElementById('courseSelect').value !== '';

        // Step 1
        document.getElementById('step1').className = 'en-step ' + (hasStu ? 'done' : 'active');
        document.getElementById('step1Circle').innerHTML = hasStu ? '<i class="fas fa-check"></i>' : '1';

        // Line 1
        document.getElementById('line1').className = 'en-step-line' + (hasStu ? ' done' : '');

        // Step 2
        document.getElementById('step2').className = 'en-step ' +
            (hasCourse ? 'done' : (hasStu ? 'active' : ''));
        document.getElementById('step2Circle').innerHTML = hasCourse ? '<i class="fas fa-check"></i>' : '2';

        // Line 2
        document.getElementById('line2').className = 'en-step-line' + (hasCourse ? ' done' : '');

        // Step 3
        document.getElementById('step3').className = 'en-step ' +
            (hasStu && hasCourse ? 'active' : '');
    }

    // ── Form validation ──
    document.getElementById('enrollForm').addEventListener('submit', function(e) {
        const course = this.course_id ? this.course_id.value.trim() : '';
        const ids = Object.keys(selectedStudents);
        if (!course || isNaN(course) || parseInt(course) <= 0) {
            alert("Please select a valid course.");
            e.preventDefault();
            return;
        }
        if (ids.length === 0) {
            alert("Please select at least one student.");
            e.preventDefault();
            return;
        }
        // Loading spinner
        const btn = document.getElementById('enrollBtn');
        const ico = document.getElementById('enrollBtnIco');
        const txt = document.getElementById('enrollBtnTxt');
        if (btn) {
            btn.disabled = true;
            if (ico) ico.className = 'fas fa-spinner fa-spin';
            if (txt) txt.textContent = 'Enrolling…';
        }
    });

    // ── Live search in table ──
    const enrollSearch = document.getElementById('enrollSearch');
    if (enrollSearch) {
        enrollSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#enrollTbody tr[data-student]');
            let visible = 0;
            rows.forEach(function(row) {
                const match = !q || (row.dataset.student || '').includes(q) || (row.dataset.num || '').includes(q) || (row.dataset.course || '').includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            const noRes = document.getElementById('enNoResults');
            if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
        });
    }

    // ── Auto-dismiss alert ──
    const enAlert = document.getElementById('enAlert');
    if (enAlert) {
        setTimeout(function() {
            enAlert.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
            enAlert.style.opacity = '0';
            enAlert.style.maxHeight = '0';
            enAlert.style.overflow = 'hidden';
            enAlert.style.padding = '0';
            enAlert.style.margin = '0';
            enAlert.style.borderWidth = '0';
        }, 4500);
    }
</script>

<?php include "includes/footer.php"; ?>