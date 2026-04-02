<?php
// ── AJAX: save/delete timetable slot (before header output) ──
require_once "config/db.php";

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit();
    }

    // Ensure timetable table exists
    $conn->query("CREATE TABLE IF NOT EXISTS timetable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        room VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'save') {
        $course_id   = intval($_POST['course_id']   ?? 0);
        $lecturer_id = intval($_POST['lecturer_id'] ?? 0) ?: null;
        $day         = trim($_POST['day']        ?? '');
        $start_time  = trim($_POST['start_time'] ?? '');
        $end_time    = trim($_POST['end_time']   ?? '');
        $room        = trim($_POST['room']       ?? '');
        $edit_id     = intval($_POST['edit_id']  ?? 0);

        $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        if (!$course_id || !in_array($day, $validDays) || !$start_time || !$end_time) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }
        if ($start_time >= $end_time) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'End time must be after start time.']);
            exit();
        }

        if ($edit_id) {
            $s = $conn->prepare("UPDATE timetable SET course_id=?,lecturer_id=?,day=?,start_time=?,end_time=?,room=? WHERE id=?");
            $s->bind_param("iissssi", $course_id, $lecturer_id, $day, $start_time, $end_time, $room, $edit_id);
        } else {
            $s = $conn->prepare("INSERT INTO timetable(course_id,lecturer_id,day,start_time,end_time,room) VALUES(?,?,?,?,?,?)");
            $s->bind_param("iissss", $course_id, $lecturer_id, $day, $start_time, $end_time, $room);
        }
        header('Content-Type: application/json');
        echo json_encode($s->execute()
            ? ['success' => true,  'message' => $edit_id ? 'Slot updated.' : 'Slot added.']
            : ['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $s  = $conn->prepare("DELETE FROM timetable WHERE id=?");
        $s->bind_param("i", $id);
        header('Content-Type: application/json');
        echo json_encode($s->execute()
            ? ['success' => true,  'message' => 'Slot removed.']
            : ['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

require_once "includes/header.php";

// Allow all roles — students and lecturers can view their own timetable
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}
$role    = $_SESSION['role'];
$userId  = $_SESSION['user_id'];

// Ensure timetable table exists with lecturer_id column
$conn->query("CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lecturer_id INT DEFAULT NULL,
    day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add lecturer_id if table existed without it
$colCheck = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timetable' AND COLUMN_NAME='lecturer_id'");
if ($colCheck->fetch_assoc()['cnt'] == 0) {
    $conn->query("ALTER TABLE timetable ADD COLUMN lecturer_id INT DEFAULT NULL");
}

// Fetch data
$departments = ['ICT','Mechanical','Automotive','Electrical','Food Technology','Construction'];
$courses_res = $conn->query("SELECT id, course_code, course_name, department, lecturer_id FROM courses WHERE status='active' ORDER BY department, course_name ASC");
$courses_all = [];
while ($r = $courses_res->fetch_assoc()) $courses_all[] = $r;

// Fetch lecturers for modal
$lec_res  = $conn->query("SELECT id, full_name FROM users WHERE role='lecturer' AND status='active' ORDER BY full_name ASC");
$lecturers_all = [];
while ($r = $lec_res->fetch_assoc()) $lecturers_all[] = $r;

$days      = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$timeSlots = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];

// Fetch timetable entries — scoped by role
if ($role === 'admin') {
    $tt_res = $conn->query("
        SELECT t.*, c.course_name, c.course_code, c.department,
               u.full_name AS lecturer_name
        FROM timetable t
        JOIN courses c ON c.id = t.course_id
        LEFT JOIN users u ON u.id = t.lecturer_id
        ORDER BY FIELD(t.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
    ");
} elseif ($role === 'lecturer') {
    // Lecturer sees only timetable slots for courses assigned to them by admin
    // Matches either timetable.lecturer_id OR courses.lecturer_id
    $stmt = $conn->prepare("
        SELECT t.*, c.course_name, c.course_code, c.department,
               u.full_name AS lecturer_name
        FROM timetable t
        JOIN courses c ON c.id = t.course_id
        LEFT JOIN users u ON u.id = t.lecturer_id
        WHERE c.lecturer_id = ? OR t.lecturer_id = ?
        ORDER BY FIELD(t.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
    ");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $tt_res = $stmt->get_result();
} else {
    // Student sees timetable for courses they are enrolled in
    $stmt = $conn->prepare("
        SELECT t.*, c.course_name, c.course_code, c.department,
               u.full_name AS lecturer_name
        FROM timetable t
        JOIN courses c ON c.id = t.course_id
        LEFT JOIN users u ON u.id = t.lecturer_id
        JOIN enrollments e ON e.course_id = t.course_id
        WHERE e.student_id = ? AND e.status = 'active'
        ORDER BY FIELD(t.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $tt_res = $stmt->get_result();
}
$timetable = [];
while ($r = $tt_res->fetch_assoc()) $timetable[] = $r;

// Filter by department / day
$filterDept = $_GET['dept'] ?? 'all';
$filterDay  = $_GET['day']  ?? 'all';
?>
<style>
:root {
    --royal: #0a2d6e; --mid: #1456c8; --accent: #1e90ff;
    --light: #f0f4fa; --border: #e4eaf3; --dark: #0d1b2e;
    --muted: #5a6e87; --green: #059669; --red: #dc2626; --amber: #d97706;
}
.tt-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:14px; }
.tt-top-left { display:flex; align-items:center; gap:14px; }
.tt-top-icon { width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg,var(--royal),var(--mid)); display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#fff; box-shadow:0 4px 14px rgba(10,45,110,.25); flex-shrink:0; }
.tt-top-title { font-size:1.25rem; font-weight:800; color:var(--dark); margin:0 0 3px; }
.tt-top-sub { font-size:.8rem; color:var(--muted); margin:0; }
.tt-date-pill { display:inline-flex; align-items:center; gap:7px; background:#fff; border:1.5px solid var(--border); border-radius:10px; padding:7px 16px; font-size:.8rem; font-weight:600; color:var(--muted); }

/* Filter bar */
.tt-filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:24px; background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 18px; box-shadow:0 2px 12px rgba(10,45,110,.06); }
.tt-filter-label { font-size:.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; white-space:nowrap; }
.tt-filter-select { border:1.5px solid var(--border); border-radius:9px; padding:7px 32px 7px 12px; font-size:.84rem; font-family:inherit; color:var(--dark); background:#f8fafd; appearance:none; -webkit-appearance:none; cursor:pointer; transition:border-color .2s; }
.tt-filter-select:focus { outline:none; border-color:var(--mid); box-shadow:0 0 0 3px rgba(20,86,200,.1); }
.tt-sel-wrap { position:relative; }
.tt-sel-wrap::after { content:'\f107'; font-family:'Font Awesome 6 Free'; font-weight:900; position:absolute; right:10px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; font-size:.8rem; }
.tt-add-btn { margin-left:auto; background:linear-gradient(135deg,var(--mid),var(--royal)); color:#fff; border:none; border-radius:10px; padding:9px 18px; font-size:.84rem; font-weight:700; font-family:inherit; cursor:pointer; display:flex; align-items:center; gap:7px; box-shadow:0 4px 14px rgba(10,45,110,.22); transition:transform .2s,box-shadow .2s; }
.tt-add-btn:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(10,45,110,.3); }

/* Grid card */
.tt-card { background:#fff; border-radius:18px; box-shadow:0 4px 24px rgba(10,45,110,.09); border:1px solid var(--border); overflow:hidden; margin-bottom:28px; }
.tt-card-head { background:linear-gradient(135deg,var(--royal),var(--mid)); padding:16px 22px; display:flex; align-items:center; gap:12px; }
.tt-card-head-ico { width:36px; height:36px; border-radius:9px; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-size:.95rem; color:#fff; flex-shrink:0; }
.tt-card-head h5 { margin:0; font-size:.92rem; font-weight:700; color:#fff; }
.tt-card-head p { margin:2px 0 0; font-size:.7rem; color:rgba(255,255,255,.6); }
.tt-card-head .tt-count { margin-left:auto; background:rgba(255,255,255,.18); color:#fff; border-radius:20px; padding:3px 12px; font-size:.72rem; font-weight:700; }

/* Timetable grid */
.tt-grid-wrap { overflow-x:auto; }
.tt-grid { width:100%; border-collapse:collapse; min-width:700px; }
.tt-grid thead tr { background:var(--light); border-bottom:2px solid var(--border); }
.tt-grid thead th { padding:11px 14px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); white-space:nowrap; }
.tt-grid thead th:first-child { padding-left:20px; width:90px; }
.tt-grid tbody tr { border-bottom:1px solid var(--border); }
.tt-grid tbody tr:last-child { border-bottom:none; }
.tt-grid tbody tr:hover { background:#f7f9fc; }
.tt-grid td { padding:10px 14px; vertical-align:top; min-width:120px; }
.tt-grid td:first-child { padding-left:20px; font-size:.75rem; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; vertical-align:middle; }

/* Slot pill */
.tt-slot { display:inline-flex; flex-direction:column; background:linear-gradient(135deg,#eff6ff,#dbeafe); border:1px solid #bfdbfe; border-radius:10px; padding:7px 11px; margin:3px 2px; min-width:110px; position:relative; cursor:default; transition:box-shadow .2s; }
.tt-slot:hover { box-shadow:0 4px 14px rgba(20,86,200,.15); }
.tt-slot-code { font-size:.7rem; font-weight:800; color:var(--mid); font-family:monospace; letter-spacing:.04em; }
.tt-slot-name { font-size:.72rem; font-weight:600; color:var(--dark); margin-top:2px; line-height:1.3; }
.tt-slot-time { font-size:.66rem; color:var(--muted); margin-top:3px; display:flex; align-items:center; gap:4px; }
.tt-slot-room { font-size:.66rem; color:var(--green); font-weight:600; margin-top:1px; }
.tt-slot-lec  { font-size:.66rem; color:var(--amber); font-weight:600; margin-top:1px; display:flex; align-items:center; gap:3px; }
.tt-slot-actions { display:flex; gap:4px; margin-top:5px; }
.tt-slot-btn { width:22px; height:22px; border-radius:6px; border:1px solid var(--border); background:#fff; display:flex; align-items:center; justify-content:center; font-size:.62rem; cursor:pointer; transition:all .15s; }
.tt-slot-btn.edit:hover { background:#eff6ff; border-color:var(--mid); }
.tt-slot-btn.del:hover  { background:#fff1f1; border-color:var(--red); }
.tt-slot-btn.edit i { color:var(--mid); }
.tt-slot-btn.del  i { color:var(--red); }
.tt-empty-cell { font-size:.72rem; color:#d1d5db; font-style:italic; }

/* Dept color variants */
.tt-slot.ict    { background:linear-gradient(135deg,#eff6ff,#dbeafe); border-color:#bfdbfe; }
.tt-slot.mech   { background:linear-gradient(135deg,#fef3c7,#fde68a22); border-color:#fcd34d; }
.tt-slot.auto   { background:linear-gradient(135deg,#ecfdf5,#d1fae5); border-color:#6ee7b7; }
.tt-slot.elec   { background:linear-gradient(135deg,#fdf4ff,#f3e8ff); border-color:#d8b4fe; }
.tt-slot.ft     { background:linear-gradient(135deg,#fff7ed,#fed7aa); border-color:#fdba74; }
.tt-slot.con    { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-color:#86efac; }

/* Empty state */
.tt-empty { text-align:center; padding:44px 24px; color:#94a3b8; }
.tt-empty i { font-size:2.2rem; display:block; margin-bottom:12px; opacity:.3; }
.tt-empty p { font-size:.88rem; margin:0; }

/* Toast */
#ttToast { position:fixed; bottom:28px; right:28px; z-index:9999; min-width:270px; max-width:340px; background:#fff; border-radius:14px; box-shadow:0 8px 32px rgba(10,45,110,.18); border:1px solid var(--border); padding:14px 16px; display:flex; align-items:center; gap:12px; transform:translateY(120%); opacity:0; transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s ease; pointer-events:none; }
#ttToastIco { width:34px; height:34px; border-radius:9px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.95rem; }
#ttToastTitle { font-size:.8rem; font-weight:800; color:var(--dark); }
#ttToastMsg   { font-size:.74rem; color:var(--muted); }

/* Modal */
.tt-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1050; align-items:center; justify-content:center; }
.tt-modal-backdrop.open { display:flex; }
.tt-modal { background:#fff; border-radius:18px; box-shadow:0 16px 48px rgba(10,45,110,.22); width:100%; max-width:460px; overflow:hidden; animation:modalIn .3s ease; }
@keyframes modalIn { from{opacity:0;transform:scale(.94)} to{opacity:1;transform:scale(1)} }
.tt-modal-head { background:linear-gradient(135deg,var(--royal),var(--mid)); padding:18px 22px; display:flex; align-items:center; gap:12px; }
.tt-modal-head h5 { margin:0; font-size:.95rem; font-weight:700; color:#fff; }
.tt-modal-head button { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:30px; height:30px; border-radius:8px; cursor:pointer; font-size:.9rem; display:flex; align-items:center; justify-content:center; }
.tt-modal-body { padding:22px; }
.tt-m-label { font-size:.78rem; font-weight:700; color:var(--dark); margin-bottom:5px; display:flex; align-items:center; gap:5px; }
.tt-m-label i { color:var(--mid); }
.tt-m-label .req { color:var(--red); }
.tt-m-input, .tt-m-select { width:100%; border:1.5px solid var(--border); border-radius:10px; padding:10px 13px; font-size:.88rem; font-family:inherit; color:var(--dark); background:#f8fafd; transition:border-color .2s; margin-bottom:14px; }
.tt-m-input:focus, .tt-m-select:focus { outline:none; border-color:var(--mid); background:#fff; box-shadow:0 0 0 3px rgba(20,86,200,.1); }
.tt-m-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.tt-m-save { width:100%; background:linear-gradient(135deg,var(--mid),var(--royal)); color:#fff; border:none; border-radius:11px; padding:12px; font-size:.9rem; font-weight:700; font-family:inherit; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 16px rgba(10,45,110,.25); transition:transform .2s; margin-top:4px; }
.tt-m-save:hover { transform:translateY(-2px); }

/* ══════════════════════════════════════
   DARK MODE - Same as Dashboard
══════════════════════════════════════ */
[data-theme="dark"] .tt-top-title { color: #e2e8f0; }
[data-theme="dark"] .tt-top-sub { color: #94a3b8; }
[data-theme="dark"] .tt-date-pill { background: #1e293b; border-color: #334155; color: #94a3b8; }
[data-theme="dark"] .tt-add-btn { background: linear-gradient(135deg, #1e293b, #0f172a); border-color: #334155; }

[data-theme="dark"] .tt-filter-bar { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .tt-filter-label { color: #94a3b8; }
[data-theme="dark"] .tt-filter-select { background: #0f172a; border-color: #334155; color: #e2e8f0; }

[data-theme="dark"] .tt-card { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .tt-card-head { background: linear-gradient(135deg, #1e293b, #0f172a); }
[data-theme="dark"] .tt-card-head h5 { color: #fff; }

[data-theme="dark"] .tt-grid thead tr { background: #0f172a; }
[data-theme="dark"] .tt-grid thead th { background: #0f172a; color: #64748b; border-color: #334155; }
[data-theme="dark"] .tt-grid tbody tr { border-color: #334155; }
[data-theme="dark"] .tt-grid tbody tr:hover { background: #273549; }
[data-theme="dark"] .tt-grid td { color: #cbd5e1; }
[data-theme="dark"] .tt-grid td:first-child { color: #94a3b8; }

[data-theme="dark"] .tt-slot { background: #0f172a; border-color: #334155; }
[data-theme="dark"] .tt-slot-code { color: #93c5fd; }
[data-theme="dark"] .tt-slot-name { color: #e2e8f0; }
[data-theme="dark"] .tt-slot-time { color: #64748b; }
[data-theme="dark"] .tt-slot-room { color: #6ee7b7; }
[data-theme="dark"] .tt-slot-lec { color: #fcd34d; }
[data-theme="dark"] .tt-slot-btn { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .tt-slot-btn.edit:hover { background: #0f172a; border-color: #1456c8; }
[data-theme="dark"] .tt-slot-btn.del:hover { background: #3f0a0a; border-color: #dc2626; }
[data-theme="dark"] .tt-slot-btn.edit i { color: #93c5fd; }
[data-theme="dark"] .tt-slot-btn.del i { color: #fca5a5; }
[data-theme="dark"] .tt-empty-cell { color: #475569; }
[data-theme="dark"] .tt-empty { color: #64748b; }

/* Dept variants dark */
[data-theme="dark"] .tt-slot.ict { background: #0f172a; border-color: #334155; }
[data-theme="dark"] .tt-slot.mech { background: #1c1003; border-color: #d97706; }
[data-theme="dark"] .tt-slot.mech .tt-slot-code { color: #fcd34d; }
[data-theme="dark"] .tt-slot.mech .tt-slot-name { color: #e2e8f0; }
[data-theme="dark"] .tt-slot.auto { background: #052e16; border-color: #059669; }
[data-theme="dark"] .tt-slot.auto .tt-slot-code { color: #6ee7b7; }
[data-theme="dark"] .tt-slot.auto .tt-slot-name { color: #e2e8f0; }
[data-theme="dark"] .tt-slot.elec { background: #1e0a3f; border-color: #7c3aed; }
[data-theme="dark"] .tt-slot.elec .tt-slot-code { color: #c4b5fd; }
[data-theme="dark"] .tt-slot.elec .tt-slot-name { color: #e2e8f0; }
[data-theme="dark"] .tt-slot.ft { background: #1c1003; border-color: #d97706; }
[data-theme="dark"] .tt-slot.con { background: #052e16; border-color: #059669; }

[data-theme="dark"] #ttToast { background: #1e293b; border-color: #334155; }
[data-theme="dark"] #ttToastTitle { color: #e2e8f0; }

[data-theme="dark"] #ttToastMsg { color: #94a3b8; }

[data-theme="dark"] .tt-modal-backdrop { background: rgba(0,0,0,.7); }
[data-theme="dark"] .tt-modal { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .tt-modal-head { background: linear-gradient(135deg, #1e293b, #0f172a); }
[data-theme="dark"] .tt-m-label { color: #e2e8f0; }
[data-theme="dark"] .tt-m-input, [data-theme="dark"] .tt-m-select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
[data-theme="dark"] .tt-m-input:focus, [data-theme="dark"] .tt-m-select:focus { border-color: #1456c8; }
</style>

<!-- ══ PAGE HEADER ══ -->
<div class="tt-top">
    <div class="tt-top-left">
        <div class="tt-top-icon"><i class="fas fa-calendar-week"></i></div>
        <div>
            <h1 class="tt-top-title"><?php echo $role === 'admin' ? 'Timetable Management' : 'My Timetable'; ?></h1>
            <p class="tt-top-sub"><?php echo $role === 'admin' ? 'Schedule classes for all departments' : ($role === 'lecturer' ? 'Your assigned class schedule' : 'Your enrolled class schedule'); ?></p>
        </div>
    </div>
    <div class="tt-date-pill"><i class="fas fa-calendar-alt"></i><?php echo date('l, d M Y'); ?></div>
</div>

<!-- ══ FILTER BAR ══ -->
<div class="tt-filter-bar">
    <span class="tt-filter-label"><i class="fas fa-filter"></i> Filter:</span>

    <div class="tt-sel-wrap">
        <select class="tt-filter-select" id="filterDept" onchange="applyFilter()">
            <option value="all">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?php echo $d; ?>" <?php echo $filterDept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tt-sel-wrap">
        <select class="tt-filter-select" id="filterDay" onchange="applyFilter()">
            <option value="all">All Days</option>
            <?php foreach ($days as $d): ?>
                <option value="<?php echo $d; ?>" <?php echo $filterDay === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($role === 'admin'): ?>
    <button class="tt-add-btn" onclick="openModal()">
        <i class="fas fa-plus-circle"></i> Add Slot
    </button>
    <?php endif; ?>
</div>

<?php
// Build timetable indexed by [dept][day]
$ttByDept = [];
foreach ($timetable as $slot) {
    $dept = $slot['department'] ?? 'Other';
    $day  = $slot['day'];
    if (!isset($ttByDept[$dept][$day])) $ttByDept[$dept][$day] = [];
    $ttByDept[$dept][$day][] = $slot;
}

$deptClass = ['ICT'=>'ict','Mechanical'=>'mech','Automotive'=>'auto','Electrical'=>'elec','Food Technology'=>'ft','Construction'=>'con'];

// Determine which depts to show
$showDepts = $filterDept === 'all' ? $departments : [$filterDept];
$showDays  = $filterDay  === 'all' ? $days        : [$filterDay];
?>

<?php foreach ($showDepts as $dept):
    $deptSlots = $ttByDept[$dept] ?? [];
    $totalSlots = 0;
    foreach ($deptSlots as $ds) $totalSlots += count($ds);
    $dc = $deptClass[$dept] ?? 'ict';

    // Lecturers and students only see departments that have slots assigned to them
    if ($role !== 'admin' && $totalSlots === 0) continue;
?>
<div class="tt-card" data-dept="<?php echo $dept; ?>">
    <div class="tt-card-head">
        <div class="tt-card-head-ico"><i class="fas fa-building"></i></div>
        <div>
            <h5><?php echo $dept; ?> Department</h5>
            <p>Weekly class schedule</p>
        </div>
        <span class="tt-count"><?php echo $totalSlots; ?> slot<?php echo $totalSlots !== 1 ? 's' : ''; ?></span>
    </div>

    <div class="tt-grid-wrap">
        <?php if ($totalSlots === 0): ?>
            <div class="tt-empty">
                <i class="fas fa-calendar-times"></i>
                <p>No classes scheduled for <?php echo $dept; ?> yet.
                   <?php if ($role === 'admin'): ?>
                   <a href="#" onclick="openModal('<?php echo $dept; ?>')" style="color:var(--mid);font-weight:700;">Add a slot →</a>
                   <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
        <table class="tt-grid">
            <thead>
                <tr>
                    <th>Day</th>
                    <?php foreach ($showDays as $d): ?>
                        <th><?php echo $d; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeSlots as $ts):
                    $tsEnd = date('H:i', strtotime($ts) + 3600);
                    $hasAny = false;
                    foreach ($showDays as $d) {
                        foreach (($deptSlots[$d] ?? []) as $sl) {
                            if ($sl['start_time'] <= $ts.':00' && $sl['end_time'] > $ts.':00') { $hasAny = true; break 2; }
                        }
                    }
                    if (!$hasAny) continue;
                ?>
                <tr>
                    <td><?php echo date('H:i', strtotime($ts)); ?></td>
                    <?php foreach ($showDays as $d):
                        $cellSlots = [];
                        foreach (($deptSlots[$d] ?? []) as $sl) {
                            if ($sl['start_time'] <= $ts.':00' && $sl['end_time'] > $ts.':00')
                                $cellSlots[] = $sl;
                        }
                    ?>
                    <td>
                        <?php if (empty($cellSlots)): ?>
                            <span class="tt-empty-cell">—</span>
                        <?php else: ?>
                            <?php foreach ($cellSlots as $sl): ?>
                            <div class="tt-slot <?php echo $dc; ?>">
                                <span class="tt-slot-code"><?php echo htmlspecialchars($sl['course_code']); ?></span>
                                <span class="tt-slot-name"><?php echo htmlspecialchars(mb_strimwidth($sl['course_name'],0,28,'…')); ?></span>
                                <span class="tt-slot-time">
                                    <i class="fas fa-clock" style="font-size:.6rem;"></i>
                                    <?php echo date('H:i',strtotime($sl['start_time'])); ?>–<?php echo date('H:i',strtotime($sl['end_time'])); ?>
                                </span>
                                <?php if ($sl['room']): ?>
                                <span class="tt-slot-room"><i class="fas fa-door-open" style="font-size:.6rem;"></i> <?php echo htmlspecialchars($sl['room']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($sl['lecturer_name'])): ?>
                                <span class="tt-slot-lec"><i class="fas fa-chalkboard-teacher" style="font-size:.6rem;"></i> <?php echo htmlspecialchars($sl['lecturer_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($role === 'admin'): ?>
                                <div class="tt-slot-actions">
                                    <button class="tt-slot-btn edit" title="Edit"
                                        onclick="openModal(null,<?php echo htmlspecialchars(json_encode($sl)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="tt-slot-btn del" title="Delete"
                                        onclick="deleteSlot(<?php echo $sl['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($role !== 'admin' && empty($timetable)): ?>
<div style="text-align:center;padding:52px 24px;color:#94a3b8;">
    <i class="fas fa-calendar-times" style="font-size:2.4rem;display:block;margin-bottom:12px;opacity:.3;"></i>
    <p style="font-size:.9rem;margin:0;">No timetable has been assigned to you yet. Contact your administrator.</p>
</div>
<?php endif; ?>

<!-- ══ ADD/EDIT MODAL ══ -->
<div class="tt-modal-backdrop" id="ttModal">
    <div class="tt-modal">
        <div class="tt-modal-head">
            <div style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <h5 id="modalTitle">Add Timetable Slot</h5>
            <button onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="tt-modal-body">
            <input type="hidden" id="editId" value="">

            <div style="margin-bottom:14px;">
                <label class="tt-m-label"><i class="fas fa-book-open"></i> Course <span class="req">*</span></label>
                <select class="tt-m-select" id="mCourse" onchange="autoFillLecturer(this)">
                    <option value="">— Select Course —</option>
                    <?php foreach ($courses_all as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                            data-dept="<?php echo htmlspecialchars($c['department'] ?? ''); ?>"
                            data-lec="<?php echo intval($c['lecturer_id'] ?? 0); ?>">
                            <?php echo htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']); ?>
                            <?php if ($c['department']): ?>(<?php echo $c['department']; ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:14px;">
                <label class="tt-m-label"><i class="fas fa-chalkboard-teacher"></i> Lecturer</label>
                <select class="tt-m-select" id="mLecturer">
                    <option value="">— Select Lecturer —</option>
                    <?php foreach ($lecturers_all as $l): ?>
                        <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:14px;">
                <label class="tt-m-label"><i class="fas fa-calendar-day"></i> Day <span class="req">*</span></label>
                <select class="tt-m-select" id="mDay">
                    <option value="">— Select Day —</option>
                    <?php foreach ($days as $d): ?>
                        <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tt-m-row">
                <div>
                    <label class="tt-m-label"><i class="fas fa-clock"></i> Start Time <span class="req">*</span></label>
                    <input type="time" class="tt-m-input" id="mStart">
                </div>
                <div>
                    <label class="tt-m-label"><i class="fas fa-clock"></i> End Time <span class="req">*</span></label>
                    <input type="time" class="tt-m-input" id="mEnd">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="tt-m-label"><i class="fas fa-door-open"></i> Room / Hall <span style="font-weight:400;color:var(--muted);font-size:.72rem;">(optional)</span></label>
                <input type="text" class="tt-m-input" id="mRoom" placeholder="e.g. Lab 01, Hall A">
            </div>

            <button class="tt-m-save" id="mSaveBtn" onclick="saveSlot()">
                <i class="fas fa-save" id="mSaveIco"></i>
                <span id="mSaveTxt">Save Slot</span>
            </button>
        </div>
    </div>
</div>

<!-- ══ TOAST ══ -->
<div id="ttToast">
    <div id="ttToastIco"></div>
    <div><div id="ttToastTitle"></div><div id="ttToastMsg"></div></div>
    <button onclick="toastHide()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--muted);font-size:.85rem;"><i class="fas fa-times"></i></button>
</div>

<script>
// ── Filter ──
function applyFilter() {
    const dept = document.getElementById('filterDept').value;
    const day  = document.getElementById('filterDay').value;
    window.location.href = 'timetable.php?dept=' + encodeURIComponent(dept) + '&day=' + encodeURIComponent(day);
}

// ── Modal ──
function openModal(prefillDept, editData) {
    document.getElementById('editId').value      = '';
    document.getElementById('mCourse').value     = '';
    document.getElementById('mLecturer').value   = '';
    document.getElementById('mDay').value        = '';
    document.getElementById('mStart').value      = '';
    document.getElementById('mEnd').value        = '';
    document.getElementById('mRoom').value       = '';
    document.getElementById('modalTitle').textContent = 'Add Timetable Slot';
    document.getElementById('mSaveTxt').textContent   = 'Save Slot';

    if (editData) {
        document.getElementById('editId').value    = editData.id;
        document.getElementById('mCourse').value   = editData.course_id;
        document.getElementById('mLecturer').value = editData.lecturer_id || '';
        document.getElementById('mDay').value      = editData.day;
        document.getElementById('mStart').value    = editData.start_time.substring(0,5);
        document.getElementById('mEnd').value      = editData.end_time.substring(0,5);
        document.getElementById('mRoom').value     = editData.room || '';
        document.getElementById('modalTitle').textContent = 'Edit Timetable Slot';
        document.getElementById('mSaveTxt').textContent   = 'Update Slot';
    } else if (prefillDept) {
        Array.from(document.getElementById('mCourse').options).forEach(o => {
            if (o.dataset.dept === prefillDept) { document.getElementById('mCourse').value = o.value; }
        });
    }
    document.getElementById('ttModal').classList.add('open');
}
function closeModal() { document.getElementById('ttModal').classList.remove('open'); }
document.getElementById('ttModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

// ── Auto-fill lecturer from course ──
function autoFillLecturer(sel) {
    const opt = sel.options[sel.selectedIndex];
    const lecId = opt ? opt.dataset.lec : '';
    if (lecId && lecId !== '0') {
        document.getElementById('mLecturer').value = lecId;
    }
}

// ── Save ──
function saveSlot() {
    const course    = document.getElementById('mCourse').value;
    const lecturer  = document.getElementById('mLecturer').value;
    const day       = document.getElementById('mDay').value;
    const start     = document.getElementById('mStart').value;
    const end       = document.getElementById('mEnd').value;
    const room      = document.getElementById('mRoom').value.trim();
    const editId    = document.getElementById('editId').value;

    if (!course || !day || !start || !end) { toastShow('error','Please fill all required fields.'); return; }
    if (start >= end) { toastShow('error','End time must be after start time.'); return; }

    const btn = document.getElementById('mSaveBtn');
    const ico = document.getElementById('mSaveIco');
    const txt = document.getElementById('mSaveTxt');
    btn.disabled = true;
    ico.className = 'fas fa-spinner fa-spin';
    txt.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('action',      'save');
    fd.append('course_id',   course);
    fd.append('lecturer_id', lecturer);
    fd.append('day',         day);
    fd.append('start_time',  start);
    fd.append('end_time',    end);
    fd.append('room',        room);
    if (editId) fd.append('edit_id', editId);

    fetch('timetable.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            ico.className = 'fas fa-save';
            txt.textContent = editId ? 'Update Slot' : 'Save Slot';
            if (data.success) {
                toastShow('success', data.message);
                closeModal();
                setTimeout(() => location.reload(), 1200);
            } else {
                toastShow('error', data.message);
            }
        })
        .catch(() => { btn.disabled=false; ico.className='fas fa-save'; txt.textContent='Save Slot'; toastShow('error','Network error.'); });
}

// ── Delete ──
function deleteSlot(id) {
    if (!confirm('Remove this timetable slot?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('timetable.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { toastShow('success', data.message); setTimeout(() => location.reload(), 1000); }
            else toastShow('error', data.message);
        })
        .catch(() => toastShow('error','Network error.'));
}

// ── Toast ──
function toastShow(type, message) {
    const t   = document.getElementById('ttToast');
    const ico = document.getElementById('ttToastIco');
    const ttl = document.getElementById('ttToastTitle');
    const msg = document.getElementById('ttToastMsg');
    if (type === 'success') {
        ico.style.background='#ecfdf5'; ico.style.color='#059669';
        ico.innerHTML='<i class="fas fa-check-circle"></i>'; ttl.textContent='Success';
    } else {
        ico.style.background='#fff1f1'; ico.style.color='#dc2626';
        ico.innerHTML='<i class="fas fa-times-circle"></i>'; ttl.textContent='Error';
    }
    msg.textContent = message;
    t.style.pointerEvents='auto'; t.style.transform='translateY(0)'; t.style.opacity='1';
    clearTimeout(t._t);
    t._t = setTimeout(toastHide, 4000);
}
function toastHide() {
    const t = document.getElementById('ttToast');
    t.style.transform='translateY(120%)'; t.style.opacity='0'; t.style.pointerEvents='none';
}
</script>

<?php include "includes/footer.php"; ?>
