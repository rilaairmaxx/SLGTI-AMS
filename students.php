<?php
session_start();
require_once "config/db.php";
require_once "includes/header.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg         = ['type' => '', 'text' => ''];
$editMode    = false;
$editStudent = null;

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM students WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $msg = ['type' => 'success', 'text' => 'Student removed successfully'];
    } else {
        $msg = ['type' => 'danger',  'text' => 'Failed to delete student'];
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editMode = true;
    $stmt     = $conn->prepare("SELECT * FROM students WHERE id=?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editStudent = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = $_POST['student_id'] ?? null;
    $student_number = trim($_POST['student_number'] ?? '');
    $student_name   = trim($_POST['student_name']   ?? '');
    $email          = trim($_POST['email']          ?? '');
    $phone          = trim($_POST['phone']          ?? '');
    $address        = trim($_POST['address']        ?? '');
    $date_of_birth  = trim($_POST['date_of_birth']  ?? '');
    $gender         = $_POST['gender']              ?? '';
    $status         = $_POST['status']              ?? 'active';

    if (empty($student_number) || empty($student_name)) {
        $msg = ['type' => 'warning', 'text' => 'Student Number and Name are required.'];
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = ['type' => 'warning', 'text' => 'Invalid email address.'];
    } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $msg = ['type' => 'warning', 'text' => 'Invalid phone number.'];
    } elseif (!empty($date_of_birth) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
        $msg = ['type' => 'warning', 'text' => 'Invalid date of birth format.'];
    } elseif (!empty($date_of_birth) && strtotime($date_of_birth) > time()) {
        $msg = ['type' => 'warning', 'text' => 'Date of birth cannot be a future date.'];
    } elseif (!empty($gender) && !in_array($gender, ['male', 'female', 'other'])) {
        $msg = ['type' => 'danger',  'text' => 'Invalid gender selected.'];
    } elseif (!in_array($status, ['active', 'inactive', 'graduated', 'suspended'])) {
        $msg = ['type' => 'danger',  'text' => 'Invalid status.'];
    } else {
        $check      = $conn->prepare("SELECT id FROM students WHERE student_number=? AND id != ?");
        $existingId = $sid ?? 0;
        $check->bind_param("si", $student_number, $existingId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg = ['type' => 'warning', 'text' => 'Student number already exists.'];
        } else {
            $dobValue    = !empty($date_of_birth) ? $date_of_birth : null;
            $genderValue = !empty($gender)         ? $gender        : null;
            if ($sid) {
                $sql = $conn->prepare("UPDATE students SET student_number=?, student_name=?, email=?, phone=?, address=?, date_of_birth=?, gender=?, status=? WHERE id=?");
                $sql->bind_param("ssssssssi", $student_number, $student_name, $email, $phone, $address, $dobValue, $genderValue, $status, $sid);
            } else {
                $sql = $conn->prepare("INSERT INTO students(student_number, student_name, email, phone, address, date_of_birth, gender, status) VALUES(?,?,?,?,?,?,?,?)");
                $sql->bind_param("ssssssss", $student_number, $student_name, $email, $phone, $address, $dobValue, $genderValue, $status);
            }
            if ($sql->execute()) {
                echo "<script>window.location.href='students.php?success=1';</script>";
                exit();
            } else {
                $msg = ['type' => 'danger', 'text' => 'Database error: Could not save record.'];
            }
        }
    }
}

if (isset($_GET['success'])) {
    $msg = ['type' => 'success', 'text' => 'Database updated successfully.'];
}

$students = $conn->query("SELECT * FROM students ORDER BY created_at DESC");
?>

<style>
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

    .st-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .st-top-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .st-top-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--royal), var(--mid));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #fff;
        box-shadow: 0 4px 14px rgba(10, 45, 110, .25);
    }

    .st-top-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 3px;
    }

    .st-top-sub {
        font-size: .8rem;
        color: var(--muted);
        margin: 0;
    }

    .st-date-pill {
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

    .st-msg {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        margin-bottom: 22px;
        font-size: .87rem;
        font-weight: 500;
        animation: msgSlide .35s ease;
    }

    @keyframes msgSlide {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .st-msg.success {
        background: #ecfdf5;
        color: #065f46;
        border-left: 4px solid var(--green);
    }

    .st-msg.warning {
        background: #fffbeb;
        color: #92400e;
        border-left: 4px solid var(--amber);
    }

    .st-msg.danger {
        background: #fff1f1;
        color: #991b1b;
        border-left: 4px solid var(--red);
    }

    .st-msg i {
        font-size: 1rem;
        flex-shrink: 0;
    }

    .st-msg-close {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        opacity: .5;
        font-size: .9rem;
        padding: 0 2px;
    }

    .st-msg-close:hover {
        opacity: 1;
    }

    .st-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 24px rgba(10, 45, 110, .09);
        border: 1px solid var(--border);
        overflow: hidden;
        height: 100%;
    }

    .st-card-head {
        background: linear-gradient(135deg, var(--royal), var(--mid));
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .st-card-head-ico {
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

    .st-card-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 700;
        color: #fff;
    }

    .st-card-head p {
        margin: 2px 0 0;
        font-size: .72rem;
        color: rgba(255, 255, 255, .65);
    }

    .st-card-body {
        padding: 22px 20px;
    }

    .st-edit-banner {
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

    .st-edit-banner i {
        color: var(--amber);
    }

    .st-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
        letter-spacing: .02em;
    }

    .st-label i {
        color: var(--mid);
        font-size: .8rem;
    }

    .st-label .req {
        color: var(--red);
        margin-left: 2px;
    }

    .st-input,
    .st-select,
    .st-textarea {
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

    .st-input:focus,
    .st-select:focus,
    .st-textarea:focus {
        outline: none;
        border-color: var(--mid);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .st-input::placeholder,
    .st-textarea::placeholder {
        color: #aab4c4;
    }

    .st-textarea {
        resize: vertical;
        min-height: 72px;
    }

    .st-sel-wrap {
        position: relative;
    }

    .st-sel-wrap::after {
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

    .st-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 16px;
    }

    @media(max-width:480px) {
        .st-row {
            grid-template-columns: 1fr;
        }
    }

    .st-divider {
        height: 1px;
        background: var(--border);
        margin: 18px 0;
    }

    .st-btn-primary {
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

    .st-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(10, 45, 110, .32);
    }

    .st-btn-primary:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    .st-btn-cancel {
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

    .st-btn-cancel:hover {
        border-color: var(--red);
        color: var(--red);
        background: #fff1f1;
    }

    .st-tbl-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
    }

    .st-tbl-head h5 {
        margin: 0;
        font-size: .93rem;
        font-weight: 800;
        color: var(--dark);
    }

    .st-tbl-count {
        background: linear-gradient(135deg, var(--mid), var(--royal));
        color: #fff;
        border-radius: 20px;
        padding: 4px 14px;
        font-size: .74rem;
        font-weight: 700;
    }

    .st-filter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding: 12px 22px;
        border-bottom: 1px solid var(--border);
        background: var(--light);
    }

    .st-search-wrap {
        position: relative;
        flex: 1;
        min-width: 200px;
    }

    .st-search-ico {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: .85rem;
        pointer-events: none;
    }

    .st-search-input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 9px 14px 9px 36px;
        font-size: .86rem;
        font-family: inherit;
        background: #fff;
        color: var(--dark);
        transition: border-color .2s, box-shadow .2s;
    }

    .st-search-input:focus {
        outline: none;
        border-color: var(--mid);
        box-shadow: 0 0 0 3px rgba(20, 86, 200, .1);
    }

    .st-search-input::placeholder {
        color: #aab4c4;
    }

    .st-filter-sel {
        border: 1.5px solid var(--border);
        border-radius: 9px;
        padding: 9px 32px 9px 12px;
        font-size: .84rem;
        font-family: inherit;
        background: #fff;
        appearance: none;
        color: var(--dark);
        cursor: pointer;
    }

    .st-filter-sel:focus {
        outline: none;
        border-color: var(--mid);
    }

    .table-responsive { max-height: 500px; overflow-y: auto; overflow-x: auto; }
    .table-responsive::-webkit-scrollbar { width: 6px; height: 6px; }
    .table-responsive::-webkit-scrollbar-track { background: transparent; }
    .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .st-tbl {
        width: 100%;
        border-collapse: collapse;
        font-size: .84rem;
    }

    .st-tbl thead tr {
        background: var(--light);
    }

    .st-tbl thead th {
        padding: 11px 14px;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--muted);
        white-space: nowrap;
        position: sticky; top: 0; z-index: 10; background: var(--light);
        box-shadow: inset 0 -2px 0 var(--border);
    }

    .st-tbl thead th:first-child {
        padding-left: 22px;
    }

    .st-tbl thead th:last-child {
        padding-right: 22px;
        text-align: right;
    }

    .st-tbl tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .st-tbl tbody tr:last-child {
        border-bottom: none;
    }

    .st-tbl tbody tr:hover {
        background: #f7f9fc;
    }

    .st-tbl tbody tr.editing-row {
        background: #eff6ff;
        border-left: 3px solid var(--mid);
    }

    .st-tbl td {
        padding: 13px 14px;
        vertical-align: middle;
    }

    .st-tbl td:first-child {
        padding-left: 22px;
    }

    .st-tbl td:last-child {
        padding-right: 22px;
    }

    .st-av {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
        font-weight: 800;
        color: #fff;
        border: 2px solid #fff;
        box-shadow: 0 2px 8px rgba(10, 45, 110, .12);
    }

    .av-c0 {
        background: linear-gradient(135deg, #1456c8, #0a2d6e);
    }

    .av-c1 {
        background: linear-gradient(135deg, #059669, #065f46);
    }

    .av-c2 {
        background: linear-gradient(135deg, #7c3aed, #4c1d95);
    }

    .av-c3 {
        background: linear-gradient(135deg, #d97706, #92400e);
    }

    .av-c4 {
        background: linear-gradient(135deg, #0891b2, #164e63);
    }

    .av-c5 {
        background: linear-gradient(135deg, #dc2626, #7f1d1d);
    }

    .st-name {
        font-weight: 700;
        color: var(--dark);
        font-size: .87rem;
        margin-bottom: 1px;
    }

    .st-num {
        font-size: .72rem;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .st-cell {
        font-size: .79rem;
        color: var(--muted);
    }

    .st-cell i {
        margin-right: 4px;
        color: #c8d0db;
        font-size: .72rem;
    }

    .st-dash {
        color: #d1d5db;
    }

    .st-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
    }

    .st-badge.active {
        background: #f0fdf4;
        color: #166534;
    }

    .st-badge.inactive {
        background: #f8fafc;
        color: #64748b;
    }

    .st-badge.graduated {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .st-badge.suspended {
        background: #fff1f1;
        color: #be123c;
    }

    .st-badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    .st-badge.active .st-badge-dot {
        background: var(--green);
    }

    .st-badge.inactive .st-badge-dot {
        background: #94a3b8;
    }

    .st-badge.graduated .st-badge-dot {
        background: var(--mid);
    }

    .st-badge.suspended .st-badge-dot {
        background: var(--red);
    }

    .st-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .st-act-btn {
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

    .st-act-btn.edit:hover {
        background: #eff6ff;
        border-color: var(--mid);
        transform: translateY(-1px);
    }

    .st-act-btn.del:hover {
        background: #fff1f1;
        border-color: var(--red);
        transform: translateY(-1px);
    }

    .st-act-btn .fa-edit {
        color: var(--mid);
    }

    .st-act-btn .fa-trash {
        color: var(--red);
    }

    .st-no-results {
        display: none;
        text-align: center;
        padding: 30px;
        color: #94a3b8;
        font-size: .86rem;
    }

    .st-no-results i {
        font-size: 1.8rem;
        display: block;
        margin-bottom: 8px;
        opacity: .3;
    }

    @media(max-width:992px) {

        .st-tbl td,
        .st-tbl th {
            padding: 10px;
        }
    }

    @media(max-width:768px) {

        .st-tbl thead th:nth-child(3),
        .st-tbl td:nth-child(3),
        .st-tbl thead th:nth-child(4),
        .st-tbl td:nth-child(4),
        .st-tbl thead th:nth-child(5),
        .st-tbl td:nth-child(5) {
            display: none;
        }

        .st-card-body {
            padding: 16px 14px;
        }

        .st-filter-bar {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<!-- PAGE HEADER -->
<div class="st-top">
    <div class="st-top-left">
        <div class="st-top-icon">
            <i class="fas <?php echo $editMode ? 'fa-user-edit' : 'fa-user-graduate'; ?>"></i>
        </div>
        <div>
            <h1 class="st-top-title"><?php echo $editMode ? 'Edit Student Record' : 'Student Management'; ?></h1>
            <p class="st-top-sub">
                <?php echo $editMode
                    ? 'Updating: <strong>' . htmlspecialchars($editStudent['student_name'] ?? '') . '</strong>'
                    : 'Register and manage student accounts'; ?>
            </p>
        </div>
    </div>
    <div class="st-date-pill">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('l, d M Y'); ?>
    </div>
</div>

<?php if ($msg['text']): ?>
    <div class="st-msg <?php echo $msg['type']; ?>" id="stAlert">
        <i class="fas fa-<?php echo $msg['type'] === 'success' ? 'check-circle' : ($msg['type'] === 'danger' ? 'times-circle' : 'exclamation-triangle'); ?>"></i>
        <?php echo $msg['text']; ?>
        <button class="st-msg-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- LEFT FORM -->
    <div class="col-xl-4 col-lg-5">
        <div class="st-card">
            <div class="st-card-head">
                <div class="st-card-head-ico"><i class="fas <?php echo $editMode ? 'fa-user-edit' : 'fa-user-graduate'; ?>"></i></div>
                <div>
                    <h5><?php echo $editMode ? 'Modify Student Record' : 'Register New Student'; ?></h5>
                    <p><?php echo $editMode ? 'Update student details below' : 'Fill in all required fields'; ?></p>
                </div>
            </div>
            <div class="st-card-body">
                <?php if ($editMode): ?>
                    <div class="st-edit-banner">
                        <i class="fas fa-pencil-alt"></i>
                        Editing: <strong><?php echo htmlspecialchars($editStudent['student_name'] ?? ''); ?></strong>
                        <span style="font-size:.75rem;font-weight:500;margin-left:4px;">(<?php echo htmlspecialchars($editStudent['student_number'] ?? ''); ?>)</span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="studentForm">
                    <input type="hidden" name="student_id" value="<?php echo $editMode ? $editStudent['id'] : ''; ?>">

                    <div class="st-row">
                        <div>
                            <label class="st-label"><i class="fas fa-id-card"></i> Student No. <span class="req">*</span></label>
                            <input type="text" name="student_number" class="st-input" placeholder="e.g. SL2024001" required
                                value="<?php echo $editMode ? htmlspecialchars($editStudent['student_number'] ?? '') : ''; ?>">
                        </div>
                        <div>
                            <label class="st-label"><i class="fas fa-user"></i> Full Name <span class="req">*</span></label>
                            <input type="text" name="student_name" class="st-input" placeholder="Full name" required
                                value="<?php echo $editMode ? htmlspecialchars($editStudent['student_name'] ?? '') : ''; ?>">
                        </div>
                    </div>

                    <div class="st-row">
                        <div>
                            <label class="st-label"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" class="st-input" placeholder="email@example.com"
                                value="<?php echo $editMode ? htmlspecialchars($editStudent['email'] ?? '') : ''; ?>">
                        </div>
                        <div>
                            <label class="st-label"><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" name="phone" class="st-input" placeholder="+94 77 000 0000"
                                value="<?php echo $editMode ? htmlspecialchars($editStudent['phone'] ?? '') : ''; ?>">
                        </div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label class="st-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" class="st-textarea" placeholder="Enter address"><?php echo $editMode ? htmlspecialchars($editStudent['address'] ?? '') : ''; ?></textarea>
                    </div>

                    <div class="st-divider"></div>

                    <div class="st-row">
                        <div>
                            <label class="st-label"><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                            <input type="date" name="date_of_birth" class="st-input"
                                max="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo $editMode ? htmlspecialchars($editStudent['date_of_birth'] ?? '') : ''; ?>">
                        </div>
                        <div>
                            <label class="st-label"><i class="fas fa-venus-mars"></i> Gender</label>
                            <div class="st-sel-wrap">
                                <select name="gender" class="st-select">
                                    <option value="">-- Select --</option>
                                    <?php
                                    $genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
                                    foreach ($genders as $val => $label):
                                        $sel = ($editMode && ($editStudent['gender'] ?? '') == $val) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $val; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php if ($editMode): ?>
                        <div style="margin-bottom:16px;">
                            <label class="st-label"><i class="fas fa-toggle-on"></i> Status</label>
                            <div class="st-sel-wrap">
                                <select name="status" class="st-select">
                                    <?php
                                    $statuses = ['active', 'inactive', 'graduated', 'suspended'];
                                    foreach ($statuses as $st) {
                                        $sel = (($editStudent['status'] ?? '') == $st) ? 'selected' : '';
                                        echo "<option value='$st' $sel>" . ucfirst($st) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="st-btn-primary" id="submitBtn">
                        <i class="fas <?php echo $editMode ? 'fa-save' : 'fa-user-plus'; ?>"></i>
                        <?php echo $editMode ? 'Update Student' : 'Register Student'; ?>
                    </button>

                    <?php if ($editMode): ?>
                        <a href="students.php" class="st-btn-cancel"><i class="fas fa-times"></i> Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT TABLE -->
    <div class="col-xl-8 col-lg-7">
        <div class="st-card">
            <div class="st-tbl-head">
                <h5><i class="fas fa-users me-2" style="color:var(--mid);"></i>Registered Students</h5>
                <span class="st-tbl-count">Total: <?php echo $students->num_rows; ?></span>
            </div>
            <div class="st-filter-bar">
                <div class="st-search-wrap">
                    <i class="fas fa-search st-search-ico"></i>
                    <input type="text" class="st-search-input" id="studentSearch" placeholder="Search by name, number or email...">
                </div>
                <select class="st-filter-sel" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="graduated">Graduated</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="st-tbl">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentTbody">
                        <?php
                        $colours = ['av-c0', 'av-c1', 'av-c2', 'av-c3', 'av-c4', 'av-c5'];
                        $idx = 0;
                        while ($s = $students->fetch_assoc()):
                            $initial   = strtoupper(substr($s['student_name'], 0, 1));
                            $colClass  = $colours[$idx % count($colours)];
                            $idx++;
                            $isEditing = ($editMode && isset($editStudent['id']) && $editStudent['id'] == $s['id']);
                            $status    = $s['status'] ?? 'active';
                        ?>
                            <tr class="<?php echo $isEditing ? 'editing-row' : ''; ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($s['student_name'])); ?>"
                                data-num="<?php echo strtolower(htmlspecialchars($s['student_number'])); ?>"
                                data-email="<?php echo strtolower(htmlspecialchars($s['email'] ?? '')); ?>"
                                data-status="<?php echo htmlspecialchars($status); ?>">
                                <td>
                                    <div style="display:flex;align-items:center;gap:11px;">
                                        <div class="st-av <?php echo $colClass; ?>"><?php echo $initial; ?></div>
                                        <div>
                                            <div class="st-name"><?php echo htmlspecialchars($s['student_name']); ?></div>
                                            <div class="st-num"><i class="fas fa-hashtag" style="font-size:.6rem;"></i><?php echo htmlspecialchars($s['student_number']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="st-cell">
                                        <?php if (!empty($s['email'])): ?><div><i class="fas fa-envelope"></i><?php echo htmlspecialchars($s['email']); ?></div><?php endif; ?>
                                        <?php if (!empty($s['phone'])): ?><div><i class="fas fa-phone"></i><?php echo htmlspecialchars($s['phone']); ?></div><?php endif; ?>
                                        <?php if (empty($s['email']) && empty($s['phone'])): ?><span class="st-dash">-</span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="st-cell" style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($s['address'] ?? ''); ?>"><?php echo !empty($s['address']) ? htmlspecialchars($s['address']) : '<span class="st-dash">-</span>'; ?></div>
                                </td>
                                <td>
                                    <div class="st-cell"><?php if (!empty($s['gender'])): ?><i class="fas fa-<?php echo $s['gender'] === 'male' ? 'mars' : ($s['gender'] === 'female' ? 'venus' : 'genderless'); ?>"></i><?php echo ucfirst(htmlspecialchars($s['gender'])); ?><?php else: ?><span class="st-dash">-</span><?php endif; ?></div>
                                </td>
                                <td>
                                    <div class="st-cell"><?php if (!empty($s['date_of_birth'])): ?><i class="fas fa-birthday-cake"></i><?php echo date('d M Y', strtotime($s['date_of_birth'])); ?><?php else: ?><span class="st-dash">-</span><?php endif; ?></div>
                                </td>
                                <td><span class="st-badge <?php echo $status; ?>"><span class="st-badge-dot"></span><?php echo ucfirst($status); ?></span></td>
                                <td>
                                    <div class="st-actions">
                                        <a href="?edit=<?php echo $s['id']; ?>" class="st-act-btn edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $s['id']; ?>" class="st-act-btn del" title="Delete" onclick="return confirm('Delete this student?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="st-no-results" id="noResults"><i class="fas fa-search"></i>No students match your search or filter.</div>
            </div>
        </div>
    </div>
</div>

<script>
    // ORIGINAL — Client-side form validation (unchanged)
    document.getElementById('studentForm').addEventListener('submit', function(e) {
        let num = this.student_number.value.trim();
        let name = this.student_name.value.trim();
        let email = this.email.value.trim();
        let phone = this.phone.value.trim();
        let dob = this.date_of_birth.value.trim();
        let gender = this.gender.value;
        if (num === '' || name === '') {
            alert("Student Number and Name are required.");
            e.preventDefault();
            return;
        }
        if (email !== '' && !/^\S+@\S+\.\S+$/.test(email)) {
            alert("Enter a valid email address.");
            e.preventDefault();
            return;
        }
        if (phone !== '' && !/^[0-9+\-\s]{7,15}$/.test(phone)) {
            alert("Enter a valid phone number.");
            e.preventDefault();
            return;
        }
        if (dob !== '') {
            const today = new Date().toISOString().split('T')[0];
            if (dob > today) {
                alert("Date of birth cannot be a future date.");
                e.preventDefault();
                return;
            }
        }
        if (gender !== '' && !['male', 'female', 'other'].includes(gender)) {
            alert("Please select a valid gender.");
            e.preventDefault();
            return;
        }
        // NEW: loading state on valid submit
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }
    });

    // NEW 1 — Live search + status filter
    const searchInput = document.getElementById('studentSearch');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('#studentTbody tr[data-name]');
    const noResults = document.getElementById('noResults');

    function filterTable() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const status = (statusFilter.value || '').toLowerCase();
        let visible = 0;
        rows.forEach(function(row) {
            const nameMatch = !q || (row.dataset.name || '').includes(q) || (row.dataset.num || '').includes(q) || (row.dataset.email || '').includes(q);
            const statusMatch = !status || (row.dataset.status || '') === status;
            const show = nameMatch && statusMatch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noResults) noResults.style.display = (visible === 0 && (q || status)) ? 'block' : 'none';
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);

    // NEW 2 — Auto-dismiss alert after 4.5s
    const alertEl = document.getElementById('stAlert');
    if (alertEl) {
        setTimeout(function() {
            alertEl.style.transition = 'opacity .4s ease,max-height .4s ease,margin .4s ease,padding .4s ease';
            alertEl.style.opacity = '0';
            alertEl.style.maxHeight = '0';
            alertEl.style.overflow = 'hidden';
            alertEl.style.padding = '0';
            alertEl.style.margin = '0';
            alertEl.style.borderWidth = '0';
        }, 4500);
    }
</script>

<?php require_once "includes/footer.php"; ?>