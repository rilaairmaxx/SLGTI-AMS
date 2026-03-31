<?php
require_once "config/db.php";

// ── Handle AJAX POST before any HTML output ──
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit();
    }

    // inline validation (mirrors validateCourseForm below)
    $cid         = $_POST['course_id']   ?? null;
    $department  = trim($_POST['department']  ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
    $nvq_level   = trim($_POST['nvq_level']   ?? '');
    $duration    = trim($_POST['duration']    ?? '');
    $description = trim($_POST['description'] ?? '');
    $lecturerIds = $_POST['lecturer_id'] ?? [];
    if (!is_array($lecturerIds)) $lecturerIds = array_filter([$lecturerIds]);
    $lecturer_id = !empty($lecturerIds) ? implode(',', array_map('intval', $lecturerIds)) : null;
    $status      = $_POST['status'] ?? 'active';
    $excludeId   = $cid ? intval($cid) : null;

    // reuse the same validation function defined below — but we need it here,
    // so define a quick inline version
    $ajaxErrors = [];
    $validDepts = ['ICT','Mechanical','Automotive','Electrical','Food Technology','Construction'];
    if (!$department || !in_array($department, $validDepts, true))
        $ajaxErrors['department'] = $department ? 'Invalid department.' : 'Department is required.';
    if (!$course_name)
        $ajaxErrors['course_name'] = 'Course name is required.';
    if (!$nvq_level || !in_array($nvq_level, ['Level 4','Level 5','Level 6'], true))
        $ajaxErrors['nvq_level'] = $nvq_level ? 'Invalid NVQ level.' : 'NVQ Level is required.';
    if (!$duration || !in_array($duration, ['6 Months','1 Year','2 Years','3 Years'], true))
        $ajaxErrors['duration'] = $duration ? 'Invalid duration.' : 'Duration is required.';
    if (!$course_code)
        $ajaxErrors['course_code'] = 'Course ID is required.';
    elseif (!preg_match('/^SLGTI\/[A-Z0-9]+\/[0-9]{4}\/[0-9]{2}$/', $course_code))
        $ajaxErrors['course_code'] = 'Format must be SLGTI/DEPT/YEAR/NN';
    else {
        $excId = $excludeId ?? 0;
        $dup = $conn->prepare("SELECT id FROM courses WHERE course_code=? AND id!=? LIMIT 1");
        $dup->bind_param("si", $course_code, $excId);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0)
            $ajaxErrors['course_code'] = "Course ID '{$course_code}' already exists.";
        $dup->close();
    }
    $lids = array_filter(array_map('intval', is_array($_POST['lecturer_id'] ?? []) ? ($_POST['lecturer_id'] ?? []) : [$_POST['lecturer_id'] ?? '']));
    if (empty($lids)) {
        $ajaxErrors['lecturer_id'] = 'Please assign at least one lecturer.';
    } else {
        foreach ($lids as $lid) {
            $lchk = $conn->prepare("SELECT id FROM users WHERE id=? AND role='lecturer' AND status='active' LIMIT 1");
            $lchk->bind_param("i", $lid);
            $lchk->execute();
            if ($lchk->get_result()->num_rows === 0) {
                $ajaxErrors['lecturer_id'] = 'One or more lecturers not found or inactive.';
                $lchk->close(); break;
            }
            $lchk->close();
        }
    }

    header('Content-Type: application/json');

    if (!empty($ajaxErrors)) {
        $firstErr   = array_values($ajaxErrors)[0];
        $errCount   = count($ajaxErrors);
        echo json_encode([
            'success' => false,
            'errors'  => $ajaxErrors,
            'message' => $errCount > 1 ? "Please fix {$errCount} errors below before saving." : $firstErr,
        ]);
        exit();
    }

    $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

    if ($cid) {
        $sql = $conn->prepare("UPDATE courses SET department=?, course_name=?, course_code=?, nvq_level=?, duration=?, description=?, lecturer_id=?, status=? WHERE id=?");
        $sql->bind_param("ssssssssi", $department, $course_name, $course_code, $nvq_level, $duration, $description, $lecturer_id, $status, $cid);
    } else {
        $sql = $conn->prepare("INSERT INTO courses(department, course_name, course_code, nvq_level, duration, description, lecturer_id, status) VALUES(?,?,?,?,?,?,?,?)");
        $sql->bind_param("ssssssss", $department, $course_name, $course_code, $nvq_level, $duration, $description, $lecturer_id, $status);
    }

    if ($sql->execute()) {
        echo json_encode(['success' => true, 'message' => $cid ? 'Course updated successfully.' : 'Course added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit();
}

require_once "includes/header.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg        = ['type' => '', 'text' => ''];
$editMode   = false;
$editCourse = null;

// ══════════════════════════════════════════════════════════════
//  VALIDATION HELPER — professional server-side validation
//  All PHP logic below this block is 100% original unchanged
// ══════════════════════════════════════════════════════════════

/**
 * Validate a course form submission.
 * Returns an array of [ field => error_message ] or empty array on pass.
 */
function validateCourseForm(array $post, mysqli $conn, ?int $excludeId = null): array
{
    $errors = [];

    // ── 1. Department ──
    $validDepts = ['ICT','Mechanical','Automotive','Electrical','Food Technology','Construction'];
    $dept = trim($post['department'] ?? '');
    if ($dept === '') {
        $errors['department'] = 'Department is required.';
    } elseif (!in_array($dept, $validDepts, true)) {
        $errors['department'] = 'Invalid department selected.';
    }

    // ── 2. Course Name ──
    $courseName = trim($post['course_name'] ?? '');
    if ($courseName === '') {
        $errors['course_name'] = 'Course name is required.';
    } elseif (mb_strlen($courseName) > 120) {
        $errors['course_name'] = 'Course name must not exceed 120 characters.';
    }

    // ── 3. NVQ Level ──
    $validLevels = ['Level 4', 'Level 5', 'Level 6'];
    $nvqLevel = trim($post['nvq_level'] ?? '');
    if ($nvqLevel === '') {
        $errors['nvq_level'] = 'NVQ Level is required.';
    } elseif (!in_array($nvqLevel, $validLevels, true)) {
        $errors['nvq_level'] = 'Invalid NVQ level selected.';
    }

    // ── 4. Duration ──
    $validDurations = ['6 Months', '1 Year', '2 Years', '3 Years'];
    $duration = trim($post['duration'] ?? '');
    if ($duration === '') {
        $errors['duration'] = 'Duration is required.';
    } elseif (!in_array($duration, $validDurations, true)) {
        $errors['duration'] = 'Invalid duration selected.';
    }

    // ── 5. Course ID (auto-generated, still validated) ──
    $courseCode = strtoupper(trim($post['course_code'] ?? ''));
    if ($courseCode === '') {
        $errors['course_code'] = 'Course ID is required.';
    } elseif (!preg_match('/^SLGTI\/[A-Z0-9]+\/[0-9]{4}\/[0-9]{2}$/', $courseCode)) {
        $errors['course_code'] = 'Course ID must follow the format: SLGTI/DEPT/YEAR/NN (e.g. SLGTI/ICT/2026/01).';
    } else {
        $excId = $excludeId ?? 0;
        $dup   = $conn->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ? LIMIT 1");
        $dup->bind_param("si", $courseCode, $excId);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $errors['course_code'] = "Course ID '{$courseCode}' already exists. A new sequence number was generated — please re-select the department.";
        }
        $dup->close();
    }

    // ── 6. Description (optional) ──
    $description = trim($post['description'] ?? '');
    if ($description !== '' && mb_strlen($description) > 500) {
        $errors['description'] = 'Description must not exceed 500 characters.';
    }
    if ($description !== '' && strip_tags($description) !== $description) {
        $errors['description'] = 'Description must not contain HTML.';
    }

    // ── 7. Lecturer (multiple) ──
    $lecturerIds = $post['lecturer_id'] ?? [];
    if (!is_array($lecturerIds)) $lecturerIds = array_filter([$lecturerIds]);
    $lecturerIds = array_filter(array_map('intval', $lecturerIds));
    if (empty($lecturerIds)) {
        $errors['lecturer_id'] = 'Please assign at least one lecturer to this course.';
    } else {
        foreach ($lecturerIds as $lid) {
            $lchk = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'lecturer' AND status = 'active' LIMIT 1");
            $lchk->bind_param("i", $lid);
            $lchk->execute();
            if ($lchk->get_result()->num_rows === 0) {
                $errors['lecturer_id'] = 'One or more selected lecturers do not exist or are inactive.';
                $lchk->close();
                break;
            }
            $lchk->close();
        }
    }

    // ── 8. Status (edit mode) ──
    if (isset($post['status'])) {
        if (!in_array($post['status'], ['active', 'inactive'], true)) {
            $errors['status'] = 'Invalid status value.';
        }
    }

    return $errors;
}

// ══════════════════════════════════════════════════════════════
//  END VALIDATION HELPER
// ══════════════════════════════════════════════════════════════


// Delete course by ID from URL parameter
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id   = intval($_GET['delete']);

    // ── Extra guard: block delete if course has enrollments ──
    $enrollCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM enrollments WHERE course_id = ?");
    $enrollCheck->bind_param("i", $id);
    $enrollCheck->execute();
    $enrollCount = $enrollCheck->get_result()->fetch_assoc()['cnt'] ?? 0;

    if ($enrollCount > 0) {
        $msg = ['type' => 'warning', 'text' => "Cannot delete: this course has {$enrollCount} enrolled student(s). Remove enrollments first."];
    } else {
        $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = ['type' => 'success', 'text' => 'Course removed successfully'];
        } else {
            $msg = ['type' => 'danger', 'text' => 'Failed to remove course'];
        }
    }
}

// Load existing course data into form for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editMode = true;
    $stmt     = $conn->prepare("SELECT * FROM courses WHERE id=?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editCourse = $stmt->get_result()->fetch_assoc();
}

// ── Field-level error store (populated by validateCourseForm()) ──
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid         = $_POST['course_id']   ?? null;
    $department  = trim($_POST['department']  ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
    $nvq_level   = trim($_POST['nvq_level']   ?? '');
    $duration    = trim($_POST['duration']    ?? '');
    $description = trim($_POST['description'] ?? '');
    $lecturerIds = $_POST['lecturer_id'] ?? [];
    if (!is_array($lecturerIds)) $lecturerIds = array_filter([$lecturerIds]);
    $lecturer_id = !empty($lecturerIds) ? implode(',', array_map('intval', $lecturerIds)) : null;
    $status      = $_POST['status'] ?? 'active';

    $excludeId   = $cid ? intval($cid) : null;
    $fieldErrors = validateCourseForm($_POST, $conn, $excludeId);

    if (!empty($fieldErrors)) {
        $firstError = array_values($fieldErrors)[0];
        $errorCount = count($fieldErrors);
        $msg = [
            'type' => 'warning',
            'text' => $errorCount > 1
                ? "Please fix {$errorCount} errors below before saving."
                : $firstError,
        ];
    } else {
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        // Ensure nvq_level, duration, department columns exist (MySQL 5.x compatible)
        foreach (['nvq_level VARCHAR(20)','duration VARCHAR(20)','department VARCHAR(60)'] as $colDef) {
            $colName = explode(' ', $colDef)[0];
            $chk = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND COLUMN_NAME='{$colName}'");
            if ($chk->fetch_assoc()['cnt'] == 0) {
                $conn->query("ALTER TABLE courses ADD COLUMN {$colDef} DEFAULT NULL");
            }
        }

        if ($cid) {
            $sql = $conn->prepare("UPDATE courses SET department=?, course_name=?, course_code=?, nvq_level=?, duration=?, description=?, lecturer_id=?, status=? WHERE id=?");
            $sql->bind_param("ssssssssi", $department, $course_name, $course_code, $nvq_level, $duration, $description, $lecturer_id, $status, $cid);
        } else {
            $sql = $conn->prepare("INSERT INTO courses(department, course_name, course_code, nvq_level, duration, description, lecturer_id, status) VALUES(?,?,?,?,?,?,?,?)");
            $sql->bind_param("ssssssss", $department, $course_name, $course_code, $nvq_level, $duration, $description, $lecturer_id, $status);
        }

        if ($sql->execute()) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $cid ? 'Course updated successfully.' : 'Course added successfully.']);
                exit();
            }
            echo "<script>window.location.href='add_course.php?success=1';</script>";
            exit();
        } else {
            $msg = ['type' => 'danger', 'text' => 'Database error: ' . $conn->error];
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg['text']]);
                exit();
            }
        }
    }
}

if (isset($_GET['success'])) {
    $msg = ['type' => 'success', 'text' => 'Database updated successfully.'];
}

// Fetch all lecturers for the dropdown
$lecturers = $conn->query("SELECT id, full_name FROM users WHERE role='lecturer' ORDER BY full_name ASC");

// Fetch all courses joined with lecturer name for the table
$courses = $conn->query("SELECT c.*, u.full_name AS lecturer_name FROM courses c LEFT JOIN users u ON c.lecturer_id = u.id ORDER BY c.created_at DESC");

// Predefined departments → short code for Course ID
$course_options = [
    'ICT'             => 'ICT',
    'Mechanical'      => 'MECH',
    'Automotive'      => 'AUTO',
    'Electrical'      => 'ELEC',
    'Food Technology' => 'FT',
    'Construction'    => 'CON',
];

// Build next available sequence per department for the current year
// Course ID format: SLGTI/DEPT/YEAR/NN
$currentYear = date('Y');
$nextCodeMap = [];
foreach ($course_options as $deptCode) {
    $pattern = 'SLGTI/' . $deptCode . '/' . $currentYear . '/%';
    $res = $conn->prepare("SELECT course_code FROM courses WHERE course_code LIKE ? ORDER BY course_code DESC LIMIT 1");
    $res->bind_param("s", $pattern);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();
    $nextCodeMap[$deptCode] = $row ? str_pad(intval(substr($row['course_code'], -2)) + 1, 2, '0', STR_PAD_LEFT) : '01';
    $res->close();
}
?>
<style>
    /* ── Variables ── */
    :root {
        --royal: #0a2d6e;
        --mid: #1456c8;
        --accent: #1e90ff;
        --light: #f0f4fa;
        --border: #e4eaf3;
        --dark: #0d1b2e;
        --muted: #5a6e87;
        --green: #059669;
        --red: #dc2626;
        --amber: #d97706;
        --info: #0891b2;
    }

    /* ── Page header ── */
    .ac-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .ac-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .ac-top-icon {
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

    .ac-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .ac-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .ac-date-pill {
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
    .ac-msg {
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

    .ac-msg.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .ac-msg.warning {
        background: #fffbeb;
        color: #92400e;
        border-left: 4px solid var(--amber);
    }

    .ac-msg.danger {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    .ac-msg i {
        font-size: 1rem;
        flex-shrink: 0;
    }

    .ac-msg-close {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        opacity: .5;
        font-size: .9rem;
        padding: 0 2px;
    }

    .ac-msg-close:hover {
        opacity: 1;
    }

    /* ── Field-level error hint ── */
    .ac-field-error {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        padding: 7px 11px;
        background: #fff1f1;
        border: 1px solid #fecaca;
        border-radius: 8px;
        font-size: .74rem;
        font-weight: 600;
        color: #991b1b;
        animation: msgIn .25s ease;
    }

    .ac-field-error i { font-size: .76rem; flex-shrink: 0; }

    /* Input error state */
    .ac-input.is-invalid,
    .ac-select.is-invalid,
    .ac-textarea.is-invalid {
        border-color: var(--red) !important;
        background: #fff8f8;
    }

    /* Input valid state */
    .ac-input.is-valid,
    .ac-select.is-valid,
    .ac-textarea.is-valid {
        border-color: var(--green) !important;
        background: #f0fdf4;
    }

    /* Character counter */
    .ac-char-count {
        font-size: .7rem;
        color: var(--muted);
        text-align: right;
        margin-top: 3px;
        transition: color .2s;
    }
    .ac-char-count.warn  { color: var(--amber); }
    .ac-char-count.over  { color: var(--red); font-weight: 700; }

    /* ── Card ── */
    .ac-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .09);
        border: 1px solid var(--border);
        overflow: hidden;
        height: 100%;
    }

    .ac-card-head {
        background: linear-gradient(135deg, var(--royal), var(--mid));
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ac-card-head-ico {
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

    .ac-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 700;
        color: #fff;
    }

    .ac-card-head p {
        margin: 2px 0 0;
        font-size: .72rem;
        color: rgba(255, 255, 255, .65);
    }

    .ac-card-body {
        padding: 24px 22px;
    }

    /* ── Edit banner ── */
    .ac-edit-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border: 1.5px solid #fcd34d;
        border-radius: 11px;
        padding: 11px 15px;
        margin-bottom: 20px;
        font-size: .82rem;
        color: #92400e;
        font-weight: 600;
    }

    .ac-edit-banner i {
        color: var(--amber);
    }

    /* ── Code preview badge ── */
    .ac-code-preview {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
        padding: 6px 12px;
        border-radius: 8px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
        font-size: .78rem;
        font-weight: 800;
        color: var(--mid);
        font-family: monospace;
        letter-spacing: .08em;
        transition: all .25s ease;
    }

    .ac-code-preview.hidden {
        display: none;
    }

    /* ── No lecturer warning ── */
    .ac-no-lec {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff1f1;
        border: 1px solid #fecaca;
        border-radius: 9px;
        padding: 10px 13px;
        margin-top: 8px;
        font-size: .78rem;
        color: #991b1b;
        font-weight: 500;
    }

    /* ── Form fields ── */
    .ac-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
        letter-spacing: .02em;
    }

    .ac-label i {
        color: var(--mid);
        font-size: .8rem;
    }

    .ac-label .req {
        color: var(--red);
        margin-left: 2px;
    }

    .ac-label .opt {
        font-size: .72rem;
        font-weight: 400;
        color: var(--muted);
        margin-left: 4px;
    }

    .ac-input,
    .ac-select,
    .ac-textarea {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 11px 14px;
        font-size: .9rem;
        font-family: inherit;
        color: var(--dark);
        background: #f8fafd;
        transition: border-color .2s, box-shadow .2s, background .2s;
        appearance: none;
        -webkit-appearance: none;
    }

    .ac-input:focus,
    .ac-select:focus,
    .ac-textarea:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .ac-input::placeholder,
    .ac-textarea::placeholder {
        color: #aab4c4;
    }

    .ac-textarea {
        resize: vertical;
        min-height: 80px;
    }

    /* Select arrow */
    .ac-sel-wrap {
        position: relative;
    }

    .ac-sel-wrap::after {
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

    .ac-hint {
        font-size: .72rem;
        color: var(--muted);
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .ac-divider {
        height: 1px;
        background: var(--border);
        margin: 18px 0;
    }

    /* Buttons */
    .ac-btn-primary {
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
        letter-spacing: .02em;
    }

    .ac-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .32);
    }

    .ac-btn-primary:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    .ac-btn-cancel {
        width: 100%;
        background: #fff;
        color: var(--muted);
        border: 1.5px solid var(--border);
        border-radius: 11px;
        padding: 11px;
        font-size: .88rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        text-decoration: none;
        margin-top: 10px;
        transition: all .2s;
    }

    .ac-btn-cancel:hover {
        border-color: var(--red);
        color: var(--red);
        background: #fff1f1;
    }

    /* ── Table panel ── */
    .ac-tbl-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
    }

    .ac-tbl-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 800;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ac-tbl-head h5 i {
        color: var(--mid);
    }

    .ac-tbl-count {
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border-radius: 20px;
        padding: 4px 14px;
        font-size: .74rem;
        font-weight: 700;
    }

    /* ── Table ── */
    .ac-tbl {
        width: 100%;
        border-collapse: collapse;
    }

    .ac-tbl thead tr {
        background: var(--light);
        border-bottom: 2px solid var(--border);
    }

    .ac-tbl thead th {
        padding: 12px 16px;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--muted);
        white-space: nowrap;
    }

    .ac-tbl thead th:first-child {
        padding-left: 22px;
    }

    .ac-tbl thead th:last-child {
        padding-right: 22px;
        text-align: right;
    }

    .ac-tbl tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .ac-tbl tbody tr:last-child {
        border-bottom: none;
    }

    .ac-tbl tbody tr:hover {
        background: #f7f9fc;
    }

    .ac-tbl tbody tr.editing-row {
        background: #eff6ff;
        border-left: 3px solid var(--mid);
    }

    .ac-tbl td {
        padding: 13px 16px;
        vertical-align: middle;
    }

    .ac-tbl td:first-child {
        padding-left: 22px;
    }

    .ac-tbl td:last-child {
        padding-right: 22px;
    }

    /* Course code pill */
    .ac-code {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: var(--mid);
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 4px 11px;
        font-size: .78rem;
        font-weight: 800;
        font-family: monospace;
        letter-spacing: .06em;
    }

    /* Course name */
    .ac-cname {
        font-weight: 700;
        color: var(--dark);
        font-size: .9rem;
    }

    /* Description */
    .ac-desc {
        font-size: .76rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .ac-no-desc {
        font-style: italic;
        color: #c8d0db;
        font-size: .76rem;
    }

    /* Lecturer */
    .ac-lec {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: .82rem;
        color: var(--dark);
        font-weight: 500;
    }

    .ac-lec-av {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 800;
        color: var(--mid);
        flex-shrink: 0;
    }

    .ac-no-lec-cell {
        font-size: .78rem;
        color: var(--red);
        font-style: italic;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Status */
    .ac-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
    }

    .ac-badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    .ac-badge.active {
        background: #f0fdf4;
        color: #166534;
    }

    .ac-badge.active .ac-badge-dot {
        background: var(--green);
    }

    .ac-badge.inactive {
        background: #f8fafc;
        color: #475569;
    }

    .ac-badge.inactive .ac-badge-dot {
        background: #94a3b8;
    }

    /* Actions */
    .ac-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .ac-act-btn {
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

    .ac-act-btn.edit:hover {
        background: #eff6ff;
        border-color: var(--mid);
        transform: translateY(-1px);
    }

    .ac-act-btn.del:hover {
        background: #fff1f1;
        border-color: var(--red);
        transform: translateY(-1px);
    }

    .ac-act-btn .fa-edit {
        color: var(--mid);
    }

    .ac-act-btn .fa-trash {
        color: var(--red);
    }

    /* Empty state */
    .ac-empty {
        text-align: center;
        padding: 44px 24px;
        color: #94a3b8;
    }

    .ac-empty i {
        font-size: 2.2rem;
        display: block;
        margin-bottom: 12px;
        opacity: .3;
    }

    .ac-empty p {
        font-size: .88rem;
        margin: 0;
    }

    /* Responsive */
    @media(max-width:992px) {

        .ac-tbl thead th:nth-child(3),
        .ac-tbl td:nth-child(3) {
            display: none;
        }

        .ac-card-body {
            padding: 18px 16px;
        }
    }

    @media(max-width:576px) {
        .ac-top-title {
            font-size: 1.05rem;
        }
    }
</style>


<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="ac-top">
    <div class="ac-top-left">
        <div class="ac-top-icon">
            <i class="fas <?php echo $editMode ? 'fa-edit' : 'fa-book-medical'; ?>"></i>
        </div>
        <div>
            <h1 class="ac-top-title"><?php echo $editMode ? 'Edit Course' : 'Course Management'; ?></h1>
            <p class="ac-top-sub">
                <?php echo $editMode
                    ? 'Updating: <strong>' . htmlspecialchars($editCourse['course_name'] ?? '') . '</strong>'
                    : 'Add and manage SLGTI courses and assignments'; ?>
            </p>
        </div>
    </div>
    <div class="ac-date-pill">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('l, d M Y'); ?>
    </div>
</div>


<!-- ══════════════════════════════════════
     ALERT
══════════════════════════════════════ -->
<?php if ($msg['text']): ?>
    <div class="ac-msg <?php echo $msg['type']; ?>" id="acAlert">
        <i class="fas fa-<?php
            echo $msg['type'] === 'success' ? 'check-circle'
                : ($msg['type'] === 'danger'  ? 'times-circle'
                : 'exclamation-triangle'); ?>"></i>
        <?php echo $msg['text']; ?>
        <button class="ac-msg-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>


<div class="row g-4">

    <!-- ══════════════════════════════════════
         LEFT — ADD / EDIT FORM
    ══════════════════════════════════════ -->
    <div class="col-xl-4 col-lg-5">
        <div class="ac-card">

            <div class="ac-card-head">
                <div class="ac-card-head-ico">
                    <i class="fas <?php echo $editMode ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                </div>
                <div>
                    <h5><?php echo $editMode ? 'Edit Course' : 'Add New Course'; ?></h5>
                    <p><?php echo $editMode ? 'Update course details below' : 'Fill in the course information'; ?></p>
                </div>
            </div>

            <div class="ac-card-body">

                <?php if ($editMode): ?>
                    <div class="ac-edit-banner">
                        <i class="fas fa-pencil-alt"></i>
                        Editing: <strong><?php echo htmlspecialchars($editCourse['course_name'] ?? ''); ?></strong>
                        <span style="opacity:.7;margin-left:4px;"><?php echo htmlspecialchars($editCourse['course_code'] ?? ''); ?></span>
                    </div>
                <?php endif; ?>

                <!-- ── FORM ── -->
                <form method="POST" id="courseForm" novalidate>
                    <input type="hidden" name="course_id" value="<?php echo $editMode ? $editCourse['id'] : ''; ?>">

                    <!-- Department -->
                    <div style="margin-bottom:16px;">
                        <label class="ac-label">
                            <i class="fas fa-building"></i> Department <span class="req">*</span>
                        </label>
                        <div class="ac-sel-wrap">
                            <select name="department" id="department"
                                class="ac-select <?php echo isset($fieldErrors['department']) ? 'is-invalid' : ''; ?>"
                                required>
                                <option value="">— Select Department —</option>
                                <?php foreach ($course_options as $deptName => $deptCode): ?>
                                    <option value="<?php echo $deptName; ?>" data-code="<?php echo $deptCode; ?>"
                                        <?php echo ($editMode && ($editCourse['department'] ?? '') == $deptName) ? 'selected' : ''; ?>
                                        <?php echo (isset($_POST['department']) && $_POST['department'] == $deptName) ? 'selected' : ''; ?>>
                                        <?php echo $deptName; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (isset($fieldErrors['department'])): ?>
                            <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['department']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Course Name -->
                    <div style="margin-bottom:16px;">
                        <label class="ac-label">
                            <i class="fas fa-book"></i> Course Name <span class="req">*</span>
                        </label>
                        <input type="text" name="course_name" id="course_name"
                            class="ac-input <?php echo isset($fieldErrors['course_name']) ? 'is-invalid' : ''; ?>"
                            placeholder="e.g. Diploma in ICT" required maxlength="120"
                            value="<?php echo $editMode ? htmlspecialchars($editCourse['course_name'] ?? '') : htmlspecialchars($_POST['course_name'] ?? ''); ?>">
                        <?php if (isset($fieldErrors['course_name'])): ?>
                            <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['course_name']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- NVQ Level + Duration (side by side) -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div>
                            <label class="ac-label">
                                <i class="fas fa-layer-group"></i> NVQ Level <span class="req">*</span>
                            </label>
                            <div class="ac-sel-wrap">
                                <select name="nvq_level" id="nvq_level"
                                    class="ac-select <?php echo isset($fieldErrors['nvq_level']) ? 'is-invalid' : ''; ?>"
                                    required>
                                    <option value="">— Level —</option>
                                    <?php foreach (['Level 4','Level 5','Level 6'] as $lvl): ?>
                                        <option value="<?php echo $lvl; ?>"
                                            <?php echo ($editMode && ($editCourse['nvq_level'] ?? '') == $lvl) ? 'selected' : ''; ?>
                                            <?php echo (isset($_POST['nvq_level']) && $_POST['nvq_level'] == $lvl) ? 'selected' : ''; ?>>
                                            <?php echo $lvl; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (isset($fieldErrors['nvq_level'])): ?>
                                <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['nvq_level']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="ac-label">
                                <i class="fas fa-clock"></i> Duration <span class="req">*</span>
                            </label>
                            <div class="ac-sel-wrap">
                                <select name="duration" id="duration"
                                    class="ac-select <?php echo isset($fieldErrors['duration']) ? 'is-invalid' : ''; ?>"
                                    required>
                                    <option value="">— Duration —</option>
                                    <?php foreach (['6 Months','1 Year','2 Years','3 Years'] as $dur): ?>
                                        <option value="<?php echo $dur; ?>"
                                            <?php echo ($editMode && ($editCourse['duration'] ?? '') == $dur) ? 'selected' : ''; ?>
                                            <?php echo (isset($_POST['duration']) && $_POST['duration'] == $dur) ? 'selected' : ''; ?>>
                                            <?php echo $dur; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (isset($fieldErrors['duration'])): ?>
                                <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['duration']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Course ID (auto-generated, read-only preview + hidden input) -->
                    <div style="margin-bottom:16px;">
                        <label class="ac-label">
                            <i class="fas fa-id-badge"></i> Course ID <span class="req">*</span>
                        </label>
                        <input type="text" name="course_code" id="course_code"
                            class="ac-input <?php echo isset($fieldErrors['course_code']) ? 'is-invalid' : ''; ?>"
                            placeholder="Auto-generates on department selection"
                            maxlength="30"
                            value="<?php echo $editMode ? htmlspecialchars($editCourse['course_code'] ?? '') : htmlspecialchars($_POST['course_code'] ?? ''); ?>"
                            style="font-family:monospace;font-weight:700;letter-spacing:.04em;">
                        <div class="ac-code-preview <?php echo ($editMode && !empty($editCourse['course_code'])) ? '' : 'hidden'; ?>" id="codePreview">
                            <i class="fas fa-tag" style="font-size:.75rem;"></i>
                            <span id="codePreviewText"><?php echo $editMode ? htmlspecialchars($editCourse['course_code'] ?? '') : ''; ?></span>
                        </div>
                        <?php if (isset($fieldErrors['course_code'])): ?>
                            <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['course_code']); ?></div>
                        <?php else: ?>
                            <p class="ac-hint"><i class="fas fa-info-circle"></i> Format: SLGTI/ICT/2026/01 — auto-generated, editable if needed.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div style="margin-bottom:16px;">
                        <label class="ac-label">
                            <i class="fas fa-align-left"></i> Description <span class="opt">(optional)</span>
                        </label>
                        <textarea name="description" id="description"
                            class="ac-textarea <?php echo isset($fieldErrors['description']) ? 'is-invalid' : ''; ?>"
                            placeholder="Short course description…" maxlength="500"
                            oninput="acCharCount(this, 500, 'descCount')"><?php
                                echo $editMode ? htmlspecialchars($editCourse['description'] ?? '') : htmlspecialchars($_POST['description'] ?? '');
                            ?></textarea>
                        <div class="ac-char-count" id="descCount"><?php
                            $currentLen = $editMode ? mb_strlen($editCourse['description'] ?? '') : mb_strlen($_POST['description'] ?? '');
                            echo $currentLen . ' / 500';
                        ?></div>
                        <?php if (isset($fieldErrors['description'])): ?>
                            <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['description']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="ac-divider"></div>

                    <!-- Assign Lecturers (multiple) -->
                    <?php
                        $selectedLecIds = [];
                        if (isset($_POST['lecturer_id'])) {
                            $selectedLecIds = is_array($_POST['lecturer_id'])
                                ? array_map('intval', $_POST['lecturer_id'])
                                : array_map('intval', explode(',', $_POST['lecturer_id']));
                        } elseif ($editMode && !empty($editCourse['lecturer_id'])) {
                            $selectedLecIds = array_map('intval', explode(',', $editCourse['lecturer_id']));
                        }
                    ?>
                    <div style="margin-bottom:16px;">
                        <label class="ac-label">
                            <i class="fas fa-chalkboard-teacher"></i> Assign Lecturers <span class="req">*</span>
                        </label>
                        <select name="lecturer_id[]" id="lecturer_id" multiple
                            class="ac-select <?php echo isset($fieldErrors['lecturer_id']) ? 'is-invalid' : ''; ?>"
                            style="min-height:110px;"
                            <?php echo ($lecturers->num_rows === 0) ? 'disabled' : ''; ?>>
                            <?php while ($lecturer = $lecturers->fetch_assoc()): ?>
                                <option value="<?php echo $lecturer['id']; ?>"
                                    <?php echo in_array((int)$lecturer['id'], $selectedLecIds) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lecturer['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <p class="ac-hint"><i class="fas fa-info-circle"></i> Hold Ctrl / Cmd to select multiple lecturers.</p>
                        <?php if ($lecturers->num_rows === 0): ?>
                            <div class="ac-no-lec">
                                <i class="fas fa-exclamation-triangle"></i>
                                No lecturers found. <a href="create_user.php" style="color:var(--red);font-weight:700;">Add one first →</a>
                            </div>
                        <?php elseif (isset($fieldErrors['lecturer_id'])): ?>
                            <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['lecturer_id']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Status (edit mode only) -->
                    <?php if ($editMode): ?>
                        <div style="margin-bottom:16px;">
                            <label class="ac-label"><i class="fas fa-toggle-on"></i> Status</label>
                            <div class="ac-sel-wrap">
                                <select name="status" class="ac-select <?php echo isset($fieldErrors['status']) ? 'is-invalid' : ''; ?>">
                                    <option value="active"   <?php echo (($editCourse['status'] ?? '') == 'active')   ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($editCourse['status'] ?? '') == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <?php if (isset($fieldErrors['status'])): ?>
                                <div class="ac-field-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($fieldErrors['status']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Submit -->
                    <button type="submit" class="ac-btn-primary" id="submitBtn">
                        <i class="fas <?php echo $editMode ? 'fa-save' : 'fa-plus-circle'; ?>"></i>
                        <?php echo $editMode ? 'Update Course' : 'Add Course'; ?>
                    </button>

                    <?php if ($editMode): ?>
                        <a href="add_course.php" class="ac-btn-cancel">
                            <i class="fas fa-times"></i> Cancel Edit
                        </a>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════
         RIGHT — COURSES TABLE
    ══════════════════════════════════════ -->
    <div class="col-xl-8 col-lg-7">
        <div class="ac-card">

            <div class="ac-tbl-head">
                <h5>
                    <i class="fas fa-list-ul"></i>
                    All Courses
                </h5>
                <span class="ac-tbl-count">Total: <?php echo $courses->num_rows; ?></span>
            </div>

            <div class="table-responsive">
                <table class="ac-tbl">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Course ID</th>
                            <th>NVQ / Duration</th>
                            <th>Lecturer</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $cCount = 0;
                        while ($c = $courses->fetch_assoc()):
                            $cCount++;
                            $isActive  = ($c['status'] ?? 'active') === 'active';
                            $lecName   = $c['lecturer_name'] ?? null;
                            $lecInit   = $lecName ? strtoupper(substr($lecName, 0, 1)) : '';
                            $isEditing = ($editMode && isset($editCourse['id']) && $editCourse['id'] == $c['id']);
                        ?>
                            <tr class="<?php echo $isEditing ? 'editing-row' : ''; ?>">

                                <!-- Course Name + Dept -->
                                <td>
                                    <div class="ac-cname"><?php echo htmlspecialchars($c['course_name'] ?? ''); ?></div>
                                    <?php if (!empty($c['department'])): ?>
                                        <div class="ac-desc"><?php echo htmlspecialchars($c['department']); ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Course ID -->
                                <td>
                                    <span class="ac-code" style="font-size:.72rem;"><?php echo htmlspecialchars($c['course_code'] ?? ''); ?></span>
                                </td>

                                <!-- NVQ Level + Duration -->
                                <td>
                                    <?php if (!empty($c['nvq_level'])): ?>
                                        <div style="font-size:.78rem;font-weight:700;color:var(--mid);"><?php echo htmlspecialchars($c['nvq_level']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['duration'])): ?>
                                        <div class="ac-desc"><?php echo htmlspecialchars($c['duration']); ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Lecturer -->
                                <td>
                                    <?php if ($lecName): ?>
                                        <div class="ac-lec">
                                            <div class="ac-lec-av"><?php echo $lecInit; ?></div>
                                            <?php echo htmlspecialchars($lecName); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="ac-no-lec-cell">
                                            <i class="fas fa-user-slash"></i> Not Assigned
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="ac-badge <?php echo $c['status'] ?? 'active'; ?>">
                                        <span class="ac-badge-dot"></span>
                                        <?php echo ucfirst($c['status'] ?? 'active'); ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td>
                                    <div class="ac-actions">
                                        <a href="?edit=<?php echo $c['id']; ?>"
                                            class="ac-act-btn edit" title="Edit course">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $c['id']; ?>"
                                            class="ac-act-btn del" title="Delete course"
                                            onclick="return confirm('Delete this course?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        <?php endwhile; ?>

                        <?php if ($cCount === 0): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="ac-empty">
                                        <i class="fas fa-book-open"></i>
                                        <p>No courses added yet. Use the form on the left to add your first course.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div><!-- /row -->


<!-- ════════════════════════════════════════════════
     TOAST NOTIFICATION
════════════════════════════════════════════════ -->
<div id="acToast" style="
    position:fixed;bottom:28px;right:28px;z-index:9999;
    min-width:280px;max-width:360px;
    background:#fff;border-radius:14px;
    box-shadow:0 8px 32px rgba(10,45,110,.18);
    border:1px solid var(--border);
    padding:16px 18px;
    display:flex;align-items:center;gap:12px;
    transform:translateY(120%);opacity:0;
    transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s ease;
    pointer-events:none;">
    <div id="acToastIco" style="width:36px;height:36px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1rem;"></div>
    <div style="flex:1;">
        <div id="acToastTitle" style="font-size:.82rem;font-weight:800;color:var(--dark);margin-bottom:2px;"></div>
        <div id="acToastMsg"   style="font-size:.76rem;color:var(--muted);"></div>
    </div>
    <button onclick="acToastHide()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:.85rem;padding:0 2px;flex-shrink:0;">
        <i class="fas fa-times"></i>
    </button>
</div>

<!-- ════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════ -->
<script>
    const nextCodeMap = <?php echo json_encode($nextCodeMap); ?>;
    const currentYear = <?php echo date('Y'); ?>;

    // ── Auto-generate Course ID on department change ──
    document.getElementById('department').addEventListener('change', function() {
        const opt      = this.options[this.selectedIndex];
        const deptCode = opt.getAttribute('data-code');
        const codeInput = document.getElementById('course_code');
        if (deptCode) {
            const nextNum = nextCodeMap[deptCode] || '01';
            codeInput.value = 'SLGTI/' + deptCode + '/' + currentYear + '/' + nextNum;
            updateCodePreview(codeInput.value);
        }
        clearFieldError('department');
    });

    // ── Live code preview badge ──
    function updateCodePreview(val) {
        const preview = document.getElementById('codePreview');
        const text    = document.getElementById('codePreviewText');
        if (!preview || !text) return;
        const pattern = /^SLGTI\/[A-Z0-9]+\/[0-9]{4}\/[0-9]{2}$/;
        if (val) {
            text.textContent = val;
            preview.classList.remove('hidden');
            preview.style.background  = pattern.test(val) ? '' : 'linear-gradient(135deg,#fff1f1,#fee2e2)';
            preview.style.borderColor = pattern.test(val) ? '' : '#fca5a5';
            preview.style.color       = pattern.test(val) ? '' : '#dc2626';
        } else {
            preview.classList.add('hidden');
        }
    }

    const codeInput = document.getElementById('course_code');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            updateCodePreview(this.value);
            clearFieldError('course_code');
        });
        if (codeInput.value) updateCodePreview(codeInput.value);
    }

    // ── AJAX form submit with toast ──
    document.getElementById('courseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        clearAllFieldErrors();

        const dept     = this.department.value.trim();
        const name     = this.course_name.value.trim();
        const nvq      = this.nvq_level.value.trim();
        const dur      = this.duration.value.trim();
        const code     = this.course_code.value.trim().toUpperCase();
        const desc     = this.description ? this.description.value.trim() : '';
        const lecSel   = this.querySelector('[name="lecturer_id[]"]');
        const lecCount = lecSel ? Array.from(lecSel.selectedOptions).length : 0;
        const codePattern = /^SLGTI\/[A-Z0-9]+\/[0-9]{4}\/[0-9]{2}$/;
        let hasError = false;

        if (!dept)  { showFieldError('department',  'Department is required.');  hasError = true; }
        if (!name)  { showFieldError('course_name', 'Course name is required.'); hasError = true; }
        if (!nvq)   { showFieldError('nvq_level',   'NVQ Level is required.');   hasError = true; }
        if (!dur)   { showFieldError('duration',    'Duration is required.');    hasError = true; }
        if (!code)  { showFieldError('course_code', 'Course ID is required.');   hasError = true; }
        else if (!codePattern.test(code)) { showFieldError('course_code', 'Format: SLGTI/DEPT/YEAR/NN'); hasError = true; }
        if (lecCount === 0) { showFieldError('lecturer_id[]', 'Assign at least one lecturer.'); hasError = true; }
        if (desc.length > 500) { showFieldError('description', 'Max 500 characters.'); hasError = true; }

        if (hasError) {
            const firstErr = document.querySelector('.ac-field-error');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        this.course_code.value = code;
        const btn = document.getElementById('submitBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…'; }

        fetch('add_course.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas <?php echo $editMode ? "fa-save" : "fa-plus-circle"; ?>"></i> <?php echo $editMode ? "Update Course" : "Add Course"; ?>'; }
            if (data.success) {
                acToastShow('success', data.message || 'Course saved successfully.');
                <?php if (!$editMode): ?>
                document.getElementById('courseForm').reset();
                document.getElementById('codePreview').classList.add('hidden');
                document.getElementById('descCount').textContent = '0 / 500';
                <?php endif; ?>
                setTimeout(() => location.reload(), 1800);
            } else {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([f, m]) => showFieldError(f, m));
                    const firstErr = document.querySelector('.ac-field-error');
                    if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                acToastShow('error', data.message || 'Please fix the errors and try again.');
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus-circle"></i> Add Course'; }
            acToastShow('error', 'Network error. Please try again.');
        });
    });

    // ── Toast ──
    function acToastShow(type, message) {
        const toast = document.getElementById('acToast');
        const ico   = document.getElementById('acToastIco');
        const title = document.getElementById('acToastTitle');
        const msg   = document.getElementById('acToastMsg');
        if (!toast) return;
        if (type === 'success') {
            ico.style.background = '#ecfdf5'; ico.style.color = '#059669';
            ico.innerHTML = '<i class="fas fa-check-circle"></i>';
            title.textContent = 'Success';
        } else {
            ico.style.background = '#fff1f1'; ico.style.color = '#dc2626';
            ico.innerHTML = '<i class="fas fa-times-circle"></i>';
            title.textContent = 'Error';
        }
        msg.textContent = message;
        toast.style.pointerEvents = 'auto';
        toast.style.transform = 'translateY(0)';
        toast.style.opacity   = '1';
        clearTimeout(toast._timer);
        toast._timer = setTimeout(acToastHide, 4000);
    }
    function acToastHide() {
        const toast = document.getElementById('acToast');
        if (!toast) return;
        toast.style.transform = 'translateY(120%)';
        toast.style.opacity   = '0';
        toast.style.pointerEvents = 'none';
    }

    // ── Field error helpers ──
    function showFieldError(fieldName, message) {
        const field = document.querySelector('[name="' + fieldName + '"]') ||
                      document.getElementById('lecturer_id');
        if (!field) return;
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        const existingErr = field.closest('div')?.querySelector('.ac-field-error');
        if (existingErr) existingErr.remove();
        const errDiv = document.createElement('div');
        errDiv.className = 'ac-field-error';
        errDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
        const wrapper = field.closest('.ac-sel-wrap') || field;
        wrapper.insertAdjacentElement('afterend', errDiv);
    }
    function clearFieldError(fieldName) {
        const field = document.querySelector('[name="' + fieldName + '"]');
        if (!field) return;
        field.classList.remove('is-invalid');
        const wrapper = field.closest('.ac-sel-wrap') || field;
        const errDiv  = wrapper.nextElementSibling;
        if (errDiv && errDiv.classList.contains('ac-field-error')) errDiv.remove();
    }
    function clearAllFieldErrors() {
        document.querySelectorAll('.ac-field-error').forEach(e => e.remove());
        document.querySelectorAll('.is-invalid').forEach(f => f.classList.remove('is-invalid'));
    }
    document.querySelectorAll('.ac-input, .ac-select, .ac-textarea').forEach(function(el) {
        el.addEventListener('input',  function() { clearFieldError(this.name); });
        el.addEventListener('change', function() { clearFieldError(this.name); });
    });

    // ── Character counter ──
    function acCharCount(el, max, countId) {
        const counter = document.getElementById(countId);
        if (!counter) return;
        const len = el.value.length;
        counter.textContent = len + ' / ' + max;
        counter.className = 'ac-char-count';
        if (len >= max)            counter.classList.add('over');
        else if (len > max * 0.85) counter.classList.add('warn');
    }
    const descEl = document.getElementById('description');
    if (descEl) acCharCount(descEl, 500, 'descCount');

    // ── Auto-dismiss page-level alert ──
    const acAlert = document.getElementById('acAlert');
    if (acAlert) {
        setTimeout(function() {
            acAlert.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
            acAlert.style.opacity = '0'; acAlert.style.maxHeight = '0';
            acAlert.style.overflow = 'hidden'; acAlert.style.padding = '0';
            acAlert.style.margin = '0'; acAlert.style.borderWidth = '0';
        }, 4500);
    }

    <?php if (isset($_GET['success'])): ?>
    window.addEventListener('DOMContentLoaded', function() { acToastShow('success', 'Course saved successfully.'); });
    <?php endif; ?>
</script>

<?php include "includes/footer.php"; ?>