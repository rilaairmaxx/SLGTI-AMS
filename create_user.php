<?php
session_start();

require_once "config/db.php";
require_once "includes/header.php";

// Admin-only page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg      = ['type' => '', 'text' => ''];
$editMode = false;
$editUser = null;

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Prevent admin from deleting their own account
    if ($id == $_SESSION['user_id']) {
        $msg = ['type' => 'danger', 'text' => 'Action Denied: You cannot delete your own account.'];
    } else {
        // Only delete admin or lecturer accounts, never students
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'student'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = ['type' => 'success', 'text' => 'User deleted successfully.'];
        } else {
            $msg = ['type' => 'danger', 'text' => 'Deletion failed.'];
        }
    }
}

// Load existing user data into form for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editMode = true;
    $stmt     = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $uid        = $_POST['user_id']  ?? null;
    $username   = trim($_POST['username']  ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email']     ?? '');
    $role       = $_POST['role']           ?? '';
    $status     = $_POST['status']         ?? 'active';
    $password   = $_POST['password']       ?? '';
    $created_by = $_SESSION['user_id'];   // Track which admin created this user

    // Password required only when creating a new user
    if (empty($username) || empty($full_name) || empty($role) || (!$uid && empty($password))) {
        $msg = ['type' => 'warning', 'text' => 'Please fill in all required fields.'];
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = ['type' => 'warning', 'text' => 'Invalid email address.'];
    } elseif (!in_array($role, ['admin', 'lecturer'])) {
        $msg = ['type' => 'danger', 'text' => 'Invalid role selected.'];
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $msg = ['type' => 'danger', 'text' => 'Invalid status selected.'];
    } else {
        // Check for duplicate username (excluding current user on edit)
        $check      = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $existingId = $uid ?? 0;
        $check->bind_param("si", $username, $existingId);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $msg = ['type' => 'warning', 'text' => "Username '$username' already exists."];
        } else {
            if ($uid) {
                // Update: re-hash password only if a new one was provided
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql  = $conn->prepare("UPDATE users SET username=?, password=?, full_name=?, email=?, role=?, status=? WHERE id=?");
                    $sql->bind_param("ssssssi", $username, $hash, $full_name, $email, $role, $status, $uid);
                } else {
                    $sql = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE id=?");
                    $sql->bind_param("sssssi", $username, $full_name, $email, $role, $status, $uid);
                }
            } else {
                // Insert new user with hashed password and created_by tracking
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql  = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status, created_by) VALUES (?,?,?,?,?,?,?)");
                $sql->bind_param("ssssssi", $username, $hash, $full_name, $email, $role, $status, $created_by);
            }

            if ($sql->execute()) {
                echo "<script>window.location.href='create_user.php?success=1';</script>";
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

// Fetch all admin and lecturer accounts with creator name for the staff table
$staff = $conn->query("
    SELECT u.*, c.full_name AS created_by_name
    FROM users u
    LEFT JOIN users c ON u.created_by = c.id
    WHERE u.role IN ('admin', 'lecturer')
    ORDER BY u.created_at DESC
");
?>

<style>
/* ── Page variables ── */
:root {
    --royal: #0a2d6e;
    --mid:   #1456c8;
    --light: #f0f4fa;
    --border:#e4eaf3;
    --dark:  #0d1b2e;
    --muted: #5a6e87;
    --green: #059669;
    --red:   #dc2626;
    --amber: #d97706;
}

/* ── Page header ── */
.cu-top {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:28px; flex-wrap:wrap; gap:14px;
}
.cu-top-left  { display:flex; align-items:center; gap:14px; }
.cu-top-icon  {
    width:50px; height:50px; border-radius:14px; flex-shrink:0;
    background:linear-gradient(135deg,var(--royal),var(--mid));
    display:flex; align-items:center; justify-content:center;
    font-size:1.25rem; color:#fff;
    box-shadow:0 4px 14px rgba(10,45,110,.25);
}
.cu-top-title { font-size:1.25rem; font-weight:800; color:var(--dark); margin:0 0 3px; }
.cu-top-sub   { font-size:.8rem; color:var(--muted); margin:0; }
.cu-date-pill {
    display:inline-flex; align-items:center; gap:7px;
    background:#fff; border:1.5px solid var(--border);
    border-radius:10px; padding:7px 16px;
    font-size:.8rem; font-weight:600; color:var(--muted);
}

/* ── Alert ── */
.cu-msg {
    display:flex; align-items:center; gap:12px;
    padding:13px 16px; border-radius:12px; margin-bottom:22px;
    font-size:.87rem; font-weight:500;
    animation: msgSlide .35s ease;
}
@keyframes msgSlide { from{opacity:0;transform:translateY(-8px);} to{opacity:1;transform:translateY(0);} }
.cu-msg.success { background:#ecfdf5; color:#065f46; border-left:4px solid var(--green); }
.cu-msg.warning { background:#fffbeb; color:#92400e; border-left:4px solid var(--amber); }
.cu-msg.danger  { background:#fff1f1; color:#991b1b; border-left:4px solid var(--red);   }
.cu-msg i       { font-size:1rem; flex-shrink:0; }
.cu-msg-close   { margin-left:auto; background:none; border:none; cursor:pointer; opacity:.5; font-size:.9rem; padding:0 2px; }
.cu-msg-close:hover { opacity:1; }

/* ── Card ── */
.cu-card {
    background:#fff; border-radius:18px;
    box-shadow:0 4px 24px rgba(10,45,110,.09);
    border:1px solid var(--border); overflow:hidden; height:100%;
}
.cu-card-head {
    background:linear-gradient(135deg,var(--royal),var(--mid));
    padding:18px 22px; display:flex; align-items:center; gap:12px;
}
.cu-card-head-ico {
    width:38px; height:38px; border-radius:10px;
    background:rgba(255,255,255,.15);
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; color:#fff; flex-shrink:0;
}
.cu-card-head h5 { margin:0; font-size:.93rem; font-weight:700; color:#fff; }
.cu-card-head p  { margin:2px 0 0; font-size:.72rem; color:rgba(255,255,255,.65); }
.cu-card-body    { padding:24px 22px; }

/* ── Edit banner ── */
.cu-edit-banner {
    display:flex; align-items:center; gap:10px;
    background:linear-gradient(135deg,#fffbeb,#fef3c7);
    border:1.5px solid #fcd34d; border-radius:11px;
    padding:11px 15px; margin-bottom:20px;
    font-size:.82rem; color:#92400e; font-weight:600;
}
.cu-edit-banner i { color:var(--amber); }

/* ── Form fields ── */
.cu-label {
    display:flex; align-items:center; gap:6px;
    font-size:.8rem; font-weight:700; color:var(--dark);
    margin-bottom:6px; letter-spacing:.02em;
}
.cu-label i   { color:var(--mid); font-size:.8rem; }
.cu-label .req{ color:var(--red); margin-left:2px; }

.cu-input, .cu-select {
    width:100%; border:1.5px solid var(--border); border-radius:10px;
    padding:11px 14px; font-size:.9rem; font-family:inherit;
    color:var(--dark); background:#f8fafd;
    transition:border-color .2s, box-shadow .2s, background .2s;
    appearance:none; -webkit-appearance:none;
}
.cu-input:focus, .cu-select:focus {
    outline:none; border-color:var(--mid); background:#fff;
    box-shadow:0 0 0 3px rgba(20,86,200,.1);
}
.cu-input::placeholder { color:#aab4c4; }

/* Select arrow */
.cu-sel-wrap { position:relative; }
.cu-sel-wrap::after {
    content:'\f107'; font-family:'Font Awesome 6 Free'; font-weight:900;
    position:absolute; right:13px; top:50%; transform:translateY(-50%);
    color:var(--muted); pointer-events:none; font-size:.85rem;
}

/* Password wrap */
.cu-pw-wrap { position:relative; }
.cu-pw-wrap .cu-input { padding-right:44px; }
.cu-eye {
    position:absolute; right:13px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:#aab4c4;
    font-size:.95rem; transition:color .2s; padding:0;
}
.cu-eye:hover { color:var(--mid); }

/* Strength bar */
.cu-str-wrap  { margin-top:8px; }
.cu-str-track { height:4px; border-radius:2px; background:var(--border); overflow:hidden; }
.cu-str-fill  { height:100%; border-radius:2px; width:0%; transition:width .35s,background .35s; }
.cu-str-lbl   { font-size:.7rem; text-align:right; margin-top:3px; color:#94a3b8; }
.cu-pw-hint   { font-size:.72rem; color:var(--muted); margin-top:5px; display:flex; align-items:center; gap:5px; }

.cu-divider   { height:1px; background:var(--border); margin:20px 0; }

/* Buttons */
.cu-btn-primary {
    width:100%; background:linear-gradient(135deg,var(--mid),var(--royal));
    color:#fff; border:none; border-radius:11px;
    padding:13px; font-size:.92rem; font-weight:700; font-family:inherit;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
    box-shadow:0 4px 16px rgba(10,45,110,.25);
    transition:transform .2s,box-shadow .2s; letter-spacing:.02em;
}
.cu-btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(10,45,110,.32); }
.cu-btn-primary:disabled { opacity:.7; cursor:not-allowed; transform:none; }

.cu-btn-cancel {
    width:100%; background:#fff; color:var(--muted);
    border:1.5px solid var(--border); border-radius:11px;
    padding:11px; font-size:.88rem; font-weight:600; font-family:inherit;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:7px;
    text-decoration:none; margin-top:10px; transition:all .2s;
}
.cu-btn-cancel:hover { border-color:var(--red); color:var(--red); background:#fff1f1; }

/* ── Table panel ── */
.cu-tbl-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 22px; border-bottom:1px solid var(--border);
    flex-wrap:wrap; gap:10px; background:#fff;
}
.cu-tbl-head h5  { margin:0; font-size:.93rem; font-weight:800; color:var(--dark); }
.cu-tbl-count    {
    background:linear-gradient(135deg,var(--mid),var(--royal));
    color:#fff; border-radius:20px; padding:4px 14px;
    font-size:.74rem; font-weight:700;
}

/* Search */
.cu-search-bar { padding:12px 22px; border-bottom:1px solid var(--border); background:var(--light); }
.cu-search-wrap { position:relative; }
.cu-search-ico  { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.85rem; pointer-events:none; }
.cu-search-input{
    width:100%; border:1.5px solid var(--border); border-radius:9px;
    padding:9px 14px 9px 36px; font-size:.86rem; font-family:inherit;
    background:#fff; color:var(--dark); transition:border-color .2s,box-shadow .2s;
}
.cu-search-input:focus { outline:none; border-color:var(--mid); box-shadow:0 0 0 3px rgba(20,86,200,.1); }
.cu-search-input::placeholder { color:#aab4c4; }

/* Table */
.table-responsive { max-height: 500px; overflow-y: auto; overflow-x: auto; }
.table-responsive::-webkit-scrollbar { width: 6px; height: 6px; }
.table-responsive::-webkit-scrollbar-track { background: transparent; }
.table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.table-responsive::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.cu-tbl { width:100%; border-collapse:collapse; font-size:.85rem; }
.cu-tbl thead tr { background:var(--light); }
.cu-tbl thead th {
    padding:12px 16px; font-size:.7rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.07em;
    color:var(--muted); white-space:nowrap;
    position: sticky; top: 0; z-index: 10; background: var(--light);
    box-shadow: inset 0 -2px 0 var(--border);
}
.cu-tbl thead th:first-child { padding-left:22px; }
.cu-tbl thead th:last-child  { padding-right:22px; text-align:right; }
.cu-tbl tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
.cu-tbl tbody tr:last-child { border-bottom:none; }
.cu-tbl tbody tr:hover { background:#f7f9fc; }
.cu-tbl tbody tr.editing-row { background:#eff6ff; border-left:3px solid var(--mid); }
.cu-tbl td { padding:14px 16px; vertical-align:middle; }
.cu-tbl td:first-child { padding-left:22px; }
.cu-tbl td:last-child  { padding-right:22px; }

/* Avatar */
.cu-av {
    width:42px; height:42px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; font-weight:800; color:#fff;
    border:2px solid #fff; box-shadow:0 2px 8px rgba(10,45,110,.12);
}
.cu-av-lec   { background:linear-gradient(135deg,#1456c8,#0a2d6e); }
.cu-av-admin { background:linear-gradient(135deg,#dc2626,#991b1b); }

.cu-name     { font-weight:700; color:var(--dark); font-size:.88rem; margin-bottom:2px; }
.cu-username { font-size:.74rem; color:var(--muted); }
.cu-email    { font-size:.72rem; color:var(--muted); display:flex; align-items:center; gap:4px; }
.cu-email i  { font-size:.66rem; }

/* Role badge */
.cu-role {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 12px; border-radius:20px;
    font-size:.72rem; font-weight:700; letter-spacing:.04em;
}
.cu-role.admin    { background:#fff1f1; color:#be123c; }
.cu-role.lecturer { background:#eff6ff; color:#1d4ed8; }

/* Status */
.cu-status {
    display:inline-flex; align-items:center; gap:6px;
    font-size:.8rem; font-weight:600;
}
.cu-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.cu-dot.active   { background:var(--green); box-shadow:0 0 0 3px rgba(5,150,105,.15); }
.cu-dot.inactive { background:#94a3b8; }

/* Meta */
.cu-meta     { font-size:.78rem; color:var(--muted); }
.cu-meta i   { margin-right:4px; color:#c8d0db; }
.cu-never    { font-style:italic; color:#c8d0db; }

/* Action buttons */
.cu-actions  { display:flex; align-items:center; justify-content:flex-end; gap:6px; }
.cu-act-btn  {
    width:34px; height:34px; border-radius:9px;
    border:1.5px solid var(--border); background:#fff;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:.85rem; text-decoration:none; transition:all .2s; cursor:pointer;
}
.cu-act-btn.edit:hover   { background:#eff6ff; border-color:var(--mid);   color:var(--mid);  transform:translateY(-1px); }
.cu-act-btn.del:hover    { background:#fff1f1; border-color:var(--red);   color:var(--red);  transform:translateY(-1px); }
.cu-act-btn .fa-edit     { color:var(--mid); }
.cu-act-btn .fa-trash-alt{ color:var(--red); }

/* No results */
.cu-no-results { display:none; text-align:center; padding:28px; color:#94a3b8; font-size:.86rem; }
.cu-no-results i { font-size:1.6rem; display:block; margin-bottom:8px; opacity:.3; }

/* Responsive */
@media(max-width:992px){ .cu-tbl td,.cu-tbl th{ padding:11px 10px; } }
@media(max-width:768px){
    .cu-tbl thead th:nth-child(4), .cu-tbl td:nth-child(4),
    .cu-tbl thead th:nth-child(5), .cu-tbl td:nth-child(5) { display:none; }
    .cu-card-body { padding:18px 16px; }
}
@media(max-width:576px){ .cu-top-title{ font-size:1.05rem; } }
</style>

<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="cu-top">
    <div class="cu-top-left">
        <div class="cu-top-icon">
            <i class="fas <?php echo $editMode ? 'fa-user-edit' : 'fa-users-cog'; ?>"></i>
        </div>
        <div>
            <h1 class="cu-top-title"><?php echo $editMode ? 'Edit Staff Account' : 'Staff Management'; ?></h1>
            <p class="cu-top-sub">
                <?php echo $editMode
                    ? 'Updating: <strong>' . htmlspecialchars($editUser['full_name'] ?? '') . '</strong>'
                    : 'Register and manage administrator &amp; lecturer accounts'; ?>
            </p>
        </div>
    </div>
    <div class="cu-date-pill">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('l, d M Y'); ?>
    </div>
</div>

<!-- ══════════════════════════════════════
     ALERT MESSAGE
══════════════════════════════════════ -->
<?php if ($msg['text']): ?>
<div class="cu-msg <?php echo $msg['type']; ?>" id="cuAlert">
    <i class="fas fa-<?php
        echo $msg['type'] === 'success' ? 'check-circle'
           : ($msg['type'] === 'danger'  ? 'times-circle'
           : 'exclamation-triangle'); ?>"></i>
    <?php echo $msg['text']; ?>
    <button class="cu-msg-close" onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ══════════════════════════════════════
         LEFT — CREATE / EDIT FORM
    ══════════════════════════════════════ -->
    <div class="col-xl-4 col-lg-5">
        <div class="cu-card">

            <div class="cu-card-head">
                <div class="cu-card-head-ico">
                    <i class="fas <?php echo $editMode ? 'fa-user-edit' : 'fa-user-plus'; ?>"></i>
                </div>
                <div>
                    <h5><?php echo $editMode ? 'Modify Staff Record' : 'Register New Staff'; ?></h5>
                    <p><?php echo $editMode ? 'Update account details below' : 'Fill in all required fields'; ?></p>
                </div>
            </div>

            <div class="cu-card-body">

                <?php if ($editMode): ?>
                <div class="cu-edit-banner">
                    <i class="fas fa-pencil-alt"></i>
                    Editing: <strong><?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?></strong>
                </div>
                <?php endif; ?>

                <!-- ── FORM — PHP logic unchanged ── -->
                <form method="POST" autocomplete="off" id="staffForm">
                    <input type="hidden" name="user_id" value="<?php echo $editMode ? $editUser['id'] : ''; ?>">

                    <!-- Full Name -->
                    <div style="margin-bottom:16px;">
                        <label class="cu-label">
                            <i class="fas fa-id-badge"></i> Full Name <span class="req">*</span>
                        </label>
                        <input type="text" name="full_name" class="cu-input"
                               placeholder="e.g. Muhammad Rila" required
                               value="<?php echo $editMode ? htmlspecialchars($editUser['full_name'] ?? '') : ''; ?>">
                    </div>

                    <!-- Username -->
                    <div style="margin-bottom:16px;">
                        <label class="cu-label">
                            <i class="fas fa-at"></i> Username <span class="req">*</span>
                        </label>
                        <input type="text" name="username" class="cu-input"
                               placeholder="e.g. rila" required
                               value="<?php echo $editMode ? htmlspecialchars($editUser['username'] ?? '') : ''; ?>">
                    </div>

                    <!-- Email -->
                    <div style="margin-bottom:16px;">
                        <label class="cu-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" name="email" class="cu-input"
                               placeholder="e.g. staff@slgti.ac.lk"
                               value="<?php echo $editMode ? htmlspecialchars($editUser['email'] ?? '') : ''; ?>">
                    </div>

                    <div class="cu-divider"></div>

                    <!-- Role + Status row -->
                    <div class="row g-3" style="margin-bottom:16px;">
                        <div class="col-<?php echo $editMode ? '6' : '12'; ?>">
                            <label class="cu-label">
                                <i class="fas fa-user-tag"></i> System Role <span class="req">*</span>
                            </label>
                            <div class="cu-sel-wrap">
                                <select name="role" class="cu-select" required>
                                    <option value="lecturer" <?php echo ($editMode && ($editUser['role'] ?? '') == 'lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                                    <option value="admin"    <?php echo ($editMode && ($editUser['role'] ?? '') == 'admin')    ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($editMode): ?>
                        <div class="col-6">
                            <label class="cu-label">
                                <i class="fas fa-toggle-on"></i> Status
                            </label>
                            <div class="cu-sel-wrap">
                                <select name="status" class="cu-select">
                                    <option value="active"   <?php echo ($editUser['status'] ?? '') == 'active'   ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($editUser['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div style="margin-bottom:22px;">
                        <label class="cu-label">
                            <i class="fas fa-lock"></i> Password
                            <?php if (!$editMode): ?><span class="req">*</span><?php endif; ?>
                        </label>
                        <div class="cu-pw-wrap">
                            <input type="password" name="password" id="password" class="cu-input"
                                   placeholder="<?php echo $editMode ? 'Leave blank to keep current' : 'Min. 6 characters'; ?>"
                                   <?php echo !$editMode ? 'required' : ''; ?>>
                            <!-- ── ORIGINAL eye toggle (id unchanged) ── -->
                            <button type="button" class="cu-eye" id="togglePassword">
                                <i class="fas fa-eye" id="eyeIconCU"></i>
                            </button>
                        </div>
                        <?php if ($editMode): ?>
                        <p class="cu-pw-hint">
                            <i class="fas fa-info-circle"></i>
                            Leave blank to keep the existing password
                        </p>
                        <?php else: ?>
                        <!-- NEW: password strength bar (create mode only) -->
                        <div class="cu-str-wrap" id="strWrap" style="display:none;">
                            <div class="cu-str-track">
                                <div class="cu-str-fill" id="strFill"></div>
                            </div>
                            <div class="cu-str-lbl" id="strLbl">—</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="cu-btn-primary" id="submitBtn">
                        <i class="fas <?php echo $editMode ? 'fa-save' : 'fa-user-plus'; ?>"></i>
                        <?php echo $editMode ? 'Update Record' : 'Create Account'; ?>
                    </button>

                    <?php if ($editMode): ?>
                    <a href="create_user.php" class="cu-btn-cancel">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════
         RIGHT — STAFF TABLE
    ══════════════════════════════════════ -->
    <div class="col-xl-8 col-lg-7">
        <div class="cu-card">

            <!-- Table header -->
            <div class="cu-tbl-head">
                <h5>
                    <i class="fas fa-users me-2" style="color:var(--mid);"></i>
                    Administrative &amp; Teaching Staff
                </h5>
                <span class="cu-tbl-count">Total: <?php echo $staff->num_rows; ?></span>
            </div>

            <!-- NEW: live search bar -->
            <div class="cu-search-bar">
                <div class="cu-search-wrap">
                    <i class="fas fa-search cu-search-ico"></i>
                    <input type="text" class="cu-search-input" id="staffSearch"
                           placeholder="Search by name, username or email…">
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="cu-tbl" id="staffTable">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffTbody">
                    <?php
                    $count = 0;
                    while ($u = $staff->fetch_assoc()):
                        $count++;
                        $isAdmin  = ($u['role'] === 'admin');
                        $initial  = strtoupper(substr($u['full_name'], 0, 1));
                        $isEditing = ($editMode && isset($editUser['id']) && $editUser['id'] == $u['id']);
                    ?>
                        <tr class="<?php echo $isEditing ? 'editing-row' : ''; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars($u['full_name'])); ?>"
                            data-user="<?php echo strtolower(htmlspecialchars($u['username'])); ?>"
                            data-email="<?php echo strtolower(htmlspecialchars($u['email'] ?? '')); ?>">

                            <!-- Staff info -->
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="cu-av <?php echo $isAdmin ? 'cu-av-admin' : 'cu-av-lec'; ?>">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div>
                                        <div class="cu-name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                        <div class="cu-username">
                                            <i class="fas fa-at" style="font-size:.65rem;margin-right:3px;"></i>
                                            <?php echo htmlspecialchars($u['username']); ?>
                                        </div>
                                        <?php if (!empty($u['email'])): ?>
                                        <div class="cu-email">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($u['email']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td>
                                <span class="cu-role <?php echo $u['role']; ?>">
                                    <i class="fas fa-<?php echo $isAdmin ? 'shield-alt' : 'chalkboard-teacher'; ?>"></i>
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>

                            <!-- Status -->
                            <td>
                                <div class="cu-status">
                                    <span class="cu-dot <?php echo $u['status']; ?>"></span>
                                    <?php echo ucfirst($u['status']); ?>
                                </div>
                            </td>

                            <!-- Last login -->
                            <td>
                                <div class="cu-meta">
                                    <?php if (!empty($u['last_login'])): ?>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d M Y, h:i A', strtotime($u['last_login'])); ?>
                                    <?php else: ?>
                                        <span class="cu-never">Never logged in</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Created by -->
                            <td>
                                <div class="cu-meta">
                                    <?php if (!empty($u['created_by_name'])): ?>
                                        <i class="fas fa-user-shield"></i>
                                        <?php echo htmlspecialchars($u['created_by_name']); ?>
                                    <?php else: ?>
                                        <span class="cu-never">System</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="cu-actions">
                                    <a href="?edit=<?php echo $u['id']; ?>"
                                       class="cu-act-btn edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- Hide delete for logged-in admin (PHP unchanged) -->
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $u['id']; ?>"
                                       class="cu-act-btn del" title="Delete"
                                       onclick="return confirm('Delete this user?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                    <?php endwhile; ?>

                    <?php if ($count === 0): ?>
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:#94a3b8;">
                            <i class="fas fa-users-slash" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
                            No staff accounts found.
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <!-- NEW: no-results message -->
                <div class="cu-no-results" id="noResults">
                    <i class="fas fa-search"></i>
                    No staff found matching your search.
                </div>
            </div>

        </div>
    </div>

</div><!-- /row -->


<!-- ════════════════════════════════════════════════
     JAVASCRIPT
     ── ORIGINAL: togglePassword (id="togglePassword" unchanged)
     ── NEW 1: live table search
     ── NEW 2: password strength bar
     ── NEW 3: submit loading spinner
     ── NEW 4: auto-dismiss alert
════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ════════════════════════════
    //  ORIGINAL — Toggle password
    //  (id="togglePassword" unchanged)
    // ════════════════════════════
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput  = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // ════════════════════════════
    //  NEW 1 — Live table search
    // ════════════════════════════
    const searchInput = document.getElementById('staffSearch');
    const rows        = document.querySelectorAll('#staffTbody tr[data-name]');
    const noResults   = document.getElementById('noResults');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;

            rows.forEach(function (row) {
                const match = !q
                    || (row.dataset.name  || '').includes(q)
                    || (row.dataset.user  || '').includes(q)
                    || (row.dataset.email || '').includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (noResults) {
                noResults.style.display = (visible === 0 && q) ? 'block' : 'none';
            }
        });
    }

    // ════════════════════════════
    //  NEW 2 — Password strength bar
    //  (create mode only)
    // ════════════════════════════
    const pwInput  = document.getElementById('password');
    const strWrap  = document.getElementById('strWrap');
    const strFill  = document.getElementById('strFill');
    const strLbl   = document.getElementById('strLbl');

    if (pwInput && strWrap) {
        pwInput.addEventListener('input', function () {
            const v = this.value;
            if (!v) { strWrap.style.display = 'none'; return; }
            strWrap.style.display = 'block';

            const score = [
                v.length >= 6,
                /[A-Z]/.test(v),
                /[0-9]/.test(v),
                /[^A-Za-z0-9]/.test(v)
            ].filter(Boolean).length;

            const cols   = ['#dc2626', '#f97316', '#eab308', '#059669'];
            const labels = ['Weak', 'Fair', 'Good', 'Strong'];

            strFill.style.width      = (score / 4 * 100) + '%';
            strFill.style.background = score > 0 ? cols[score - 1] : '#e4eaf3';
            strLbl.textContent       = score > 0 ? labels[score - 1] : '—';
            strLbl.style.color       = score > 0 ? cols[score - 1] : '#94a3b8';
        });
    }

    // ════════════════════════════
    //  NEW 3 — Submit loading spinner
    // ════════════════════════════
    const staffForm = document.getElementById('staffForm');
    const submitBtn = document.getElementById('submitBtn');

    if (staffForm && submitBtn) {
        staffForm.addEventListener('submit', function () {
            submitBtn.disabled    = true;
            submitBtn.innerHTML   =
                '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
        });
    }

    // ════════════════════════════
    //  NEW 4 — Auto-dismiss alert after 4.5s
    // ════════════════════════════
    const alertEl = document.getElementById('cuAlert');
    if (alertEl) {
        setTimeout(function () {
            alertEl.style.transition  = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
            alertEl.style.opacity     = '0';
            alertEl.style.maxHeight   = '0';
            alertEl.style.overflow    = 'hidden';
            alertEl.style.padding     = '0';
            alertEl.style.margin      = '0';
            alertEl.style.borderWidth = '0';
        }, 4500);
    }

});
</script>

<?php require_once "includes/footer.php"; ?>