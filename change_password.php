<?php
require_once "includes/auth.php";
require_once "config/db.php";

$role   = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$msg    = ['type' => '', 'text' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $msg = ['type' => 'warning', 'text' => 'All fields are required.'];
    } elseif (strlen($new) < 8) {
        $msg = ['type' => 'warning', 'text' => 'New password must be at least 8 characters.'];
    } elseif ($new !== $confirm) {
        $msg = ['type' => 'warning', 'text' => 'New passwords do not match.'];
    } else {
        // Fetch current hash — students vs users table
        if ($role === 'student') {
            $stmt = $conn->prepare("SELECT password FROM students WHERE id = ?");
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row['password'])) {
            $msg = ['type' => 'danger', 'text' => 'Current password is incorrect.'];
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            if ($role === 'student') {
                $upd = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
            } else {
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            }
            $upd->bind_param("si", $hash, $userId);
            if ($upd->execute()) {
                // Log to audit_log if table exists
                $chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
                if ($chk && $chk->num_rows > 0) {
                    $tbl    = ($role === 'student') ? 'students' : 'users';
                    $action = 'password_change';
                    $detail = "User ID {$userId} ({$role}) changed their password";
                    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $al = $conn->prepare("INSERT INTO audit_log (user_id, role, action, detail, ip_address) VALUES (?,?,?,?,?)");
                    $al->bind_param("issss", $userId, $role, $action, $detail, $ip);
                    $al->execute();
                }
                $msg = ['type' => 'success', 'text' => 'Password changed successfully.'];
            } else {
                $msg = ['type' => 'danger', 'text' => 'Failed to update password. Please try again.'];
            }
        }
    }
}
?>
<?php include "includes/header.php"; ?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-key me-2"></i>Change Password</h1>
    <p class="page-subtitle">Update your account password</p>
</div>

<?php if ($msg['text']): ?>
    <div class="alert alert-<?php echo $msg['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <style>
                .lg-pw-wrap { position: relative; }
                .lg-pw-wrap .lg-input {
                    width: 100%;
                    padding: 10px 42px 10px 14px;
                    border: 1.5px solid #e4eaf3;
                    border-radius: 10px;
                    font-size: .9rem;
                    font-family: inherit;
                    background: #f8fafd;
                    color: #0d1b2e;
                    transition: border-color .2s, box-shadow .2s;
                    outline: none;
                }
                .lg-pw-wrap .lg-input:focus {
                    border-color: #1456c8;
                    background: #fff;
                    box-shadow: 0 0 0 3px rgba(20,86,200,.1);
                }
                .lg-pw-wrap i {
                    position: absolute;
                    right: 13px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #5a6e87;
                    cursor: pointer;
                    font-size: .9rem;
                    transition: color .2s;
                }
                .lg-pw-wrap i:hover { color: #1456c8; }
                .cp-label {
                    font-size: .82rem;
                    font-weight: 700;
                    color: #0d1b2e;
                    margin-bottom: 6px;
                    display: block;
                }
                .cp-hint { font-size: .74rem; color: #5a6e87; margin-top: 4px; }
                .cp-submit {
                    width: 100%;
                    background: linear-gradient(135deg, #1456c8, #0a2d6e);
                    color: #fff;
                    border: none;
                    border-radius: 11px;
                    padding: 12px;
                    font-size: .92rem;
                    font-weight: 700;
                    font-family: inherit;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    box-shadow: 0 4px 16px rgba(10,45,110,.22);
                    transition: transform .2s, box-shadow .2s;
                    margin-top: 4px;
                }
                .cp-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(10,45,110,.3); }

                [data-theme="dark"] .cp-label { color:#cbd5e1; }
                [data-theme="dark"] .cp-hint { color:#94a3b8; }
                [data-theme="dark"] .lg-pw-wrap .lg-input { background:#1e293b;border-color:#334155;color:#e2e8f0; }
                [data-theme="dark"] .lg-pw-wrap .lg-input:focus { background:#1e293b;border-color:#1456c8;color:#e2e8f0; }
                [data-theme="dark"] .lg-pw-wrap .lg-input::placeholder { color:#64748b; }
                [data-theme="dark"] .lg-pw-wrap i { color:#64748b; }
                [data-theme="dark"] .lg-pw-wrap i:hover { color:#1456c8; }
                </style>

                <form method="POST" id="cpForm">
                    <div class="mb-3">
                        <label class="cp-label">Current Password</label>
                        <div class="lg-pw-wrap">
                            <input type="password" name="current_password" id="cur" class="lg-input" placeholder="Enter current password" required>
                            <i class="fas fa-eye" id="toggleCur"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="cp-label">New Password</label>
                        <div class="lg-pw-wrap">
                            <input type="password" name="new_password" id="npw" class="lg-input" placeholder="Enter new password" minlength="8" required>
                            <i class="fas fa-eye" id="toggleNpw"></i>
                        </div>
                        <div class="cp-hint">Minimum 8 characters.</div>
                    </div>
                    <div class="mb-4">
                        <label class="cp-label">Confirm New Password</label>
                        <div class="lg-pw-wrap">
                            <input type="password" name="confirm_password" id="cpw" class="lg-input" placeholder="Confirm new password" required>
                            <i class="fas fa-eye" id="toggleCpw"></i>
                        </div>
                    </div>
                    <button type="submit" class="cp-submit">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Eye toggle — same pattern as login.php
[['toggleCur','cur'],['toggleNpw','npw'],['toggleCpw','cpw']].forEach(function([btnId, inputId]) {
    const btn   = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    if (btn && input) {
        btn.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
});

document.getElementById('cpForm').addEventListener('submit', function(e) {
    const np = document.getElementById('npw').value;
    const cp = document.getElementById('cpw').value;
    if (np.length < 8) { e.preventDefault(); alert('New password must be at least 8 characters.'); return; }
    if (np !== cp)     { e.preventDefault(); alert('New passwords do not match.'); }
});
</script>

<?php include "includes/footer.php"; ?>
