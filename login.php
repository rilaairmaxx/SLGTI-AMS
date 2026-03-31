<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include "config/db.php";
require_once "rate_limit.php";

// Load mailer safely — login still works even if PHPMailer is misconfigured
try {
    require_once "helpers/mailer.php";
} catch (Throwable $e) {
    error_log("Mailer load error: " . $e->getMessage());
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error   = '';
$success = '';

function logLoginActivity($conn, $user_id, $status)
{
    try {
        $ip_address = $_SERVER['REMOTE_ADDR']     ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $tableCheck = $conn->query("SHOW TABLES LIKE 'login_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $ip_address, $user_agent, $status);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Login activity logging error: " . $e->getMessage());
    }
}

function generateResetCode(): string
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generateToken(): string
{
    return bin2hex(random_bytes(32));
}

function sanitizeEmail(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
$max_attempts = 5;

// Show rate limit error if redirected here
if (isset($_SESSION['rate_limit_error'])) {
    $error = $_SESSION['rate_limit_error'];
    unset($_SESSION['rate_limit_error']);
}

if (isset($_GET['logout'])) {
    $success = "You have been successfully logged out. See you again!";
    $_SESSION['login_attempts'] = 0;
}
if (isset($_GET['expired'])) {
    $error = "Your session has expired. Please login again to continue.";
}


if (isset($_POST['ajax_forgot'])) {
    header('Content-Type: application/json');
    $step   = $_POST['step']   ?? '';
    $output = ['success' => false, 'message' => ''];

    if ($step === 'send_code') {
        $email = sanitizeEmail($_POST['email'] ?? '');

        if (!isValidEmail($email)) {
            $output['message'] = 'Please enter a valid email address.';
            echo json_encode($output);
            exit();
        }

        $userFound = false;
        $userName  = '';
        $userId    = 0;
        $userRole  = 'student';

        $stmt = $conn->prepare("SELECT id, student_name FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $userFound = true;
            $userName   = $row['student_name'];
            $userId     = $row['id'];
            $userRole   = 'student';
        } else {
            $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND role IN ('lecturer', 'admin')");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $userFound = true;
                $userName  = $row['full_name'];
                $userId    = $row['id'];
                $userRole  = 'lecturer';
            }
        }

        if (!$userFound) {
            $output['message'] = 'No account found with this email address.';
            echo json_encode($output);
            exit();
        }

        $code      = generateResetCode();
        $token     = generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $conn->query("DELETE FROM password_resets WHERE email = '$email' AND used_at IS NULL");

        $stmt = $conn->prepare("INSERT INTO password_resets (email, role, token, code, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $email, $userRole, $token, $code, $expiresAt);

        if (!$stmt->execute()) {
            $output['message'] = 'Failed to generate reset code. Please try again.';
            $stmt->close();
            echo json_encode($output);
            exit();
        }
        $stmt->close();

        $emailBody = "
            <h2 style='color:#0a2d6e;margin:0 0 8px;'>Password Reset Request</h2>
            <p style='color:#5a6e87;margin:0 0 20px;'>
                Hello <strong>{$userName}</strong>, we received a request to reset your SLGTI Attendance System password.
            </p>
            <div style='background:#f0f4fa;border-radius:12px;padding:22px 28px;margin-bottom:20px;text-align:center;'>
                <div style='font-size:.78rem;color:#5a6e87;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;'>Your Verification Code</div>
                <div style='font-size:2.2rem;font-weight:800;color:#0a2d6e;font-family:monospace;letter-spacing:.15em;'>{$code}</div>
            </div>
            <p style='color:#374151;font-size:.88rem;margin-bottom:8px;'>
                <strong>Important:</strong> This code expires in <strong>15 minutes</strong>. If you did not request a password reset, please ignore this email.
            </p>
            <p style='color:#94a3b8;font-size:.78rem;margin-top:16px;'>
                For security reasons, never share this code with anyone. Our staff will never ask for your verification code.
            </p>
        ";

        try {
            $result = Mailer::send([
                'to'      => $email,
                'toName'  => $userName,
                'subject' => 'SLGTI AMS - Password Reset Verification Code',
                'body'    => $emailBody,
            ]);
        } catch (Throwable $e) {
            error_log('Mailer Throwable: ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Email failed: ' . $e->getMessage()];
        }

        if ($result['success']) {
            $_SESSION['fp_token']     = $token;
            $_SESSION['fp_email']     = $email;
            $_SESSION['fp_role']      = $userRole;
            $_SESSION['fp_user_id']   = $userId;
            $_SESSION['fp_user_name'] = $userName;
            $_SESSION['fp_code_sent'] = time();

            $output['success']     = true;
            $output['masked_email'] = substr($email, 0, 3) . '***' . strstr($email, '@');
            $output['message']     = 'Verification code sent to your email.';
        } else {
            $output['message'] = $result['message'] ?? 'Failed to send email. Please try again.';
        }
        echo json_encode($output);
        exit();
    }

    if ($step === 'verify_code') {
        $code = trim($_POST['code'] ?? '');

        if (empty($_SESSION['fp_token']) || empty($_SESSION['fp_email'])) {
            $output['message'] = 'Session expired. Please request a new code.';
            echo json_encode($output);
            exit();
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            $output['message'] = 'Please enter a valid 6-digit code.';
            echo json_encode($output);
            exit();
        }

        $token  = $_SESSION['fp_token'];
        $email  = $_SESSION['fp_email'];

        $stmt = $conn->prepare("SELECT id, expires_at FROM password_resets WHERE email = ? AND token = ? AND code = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("sss", $email, $token, $code);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reset) {
            $output['message'] = 'Invalid or expired verification code.';
            echo json_encode($output);
            exit();
        }

        if (strtotime($reset['expires_at']) < time()) {
            $output['message'] = 'Verification code has expired. Please request a new one.';
            echo json_encode($output);
            exit();
        }

        $_SESSION['fp_verified'] = true;

        $output['success'] = true;
        $output['message'] = 'Code verified successfully.';
        echo json_encode($output);
        exit();
    }

    if ($step === 'resend_code') {
        if (empty($_SESSION['fp_email'])) {
            $output['message'] = 'Session expired. Please start again.';
            echo json_encode($output);
            exit();
        }

        $email = $_SESSION['fp_email'];

        if (isset($_SESSION['fp_code_sent']) && (time() - $_SESSION['fp_code_sent']) < 60) {
            $remaining = 60 - (time() - $_SESSION['fp_code_sent']);
            $output['message'] = "Please wait {$remaining} seconds before requesting a new code.";
            echo json_encode($output);
            exit();
        }

        $code      = generateResetCode();
        $token     = generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $conn->query("DELETE FROM password_resets WHERE email = '$email' AND used_at IS NULL");

        $stmt = $conn->prepare("INSERT INTO password_resets (email, role, token, code, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $email, $_SESSION['fp_role'], $token, $code, $expiresAt);
        $stmt->execute();
        $stmt->close();

        $emailBody = "
            <h2 style='color:#0a2d6e;margin:0 0 8px;'>New Password Reset Code</h2>
            <p style='color:#5a6e87;margin:0 0 20px;'>
                Hello <strong>{$_SESSION['fp_user_name']}</strong>, here is your new verification code.
            </p>
            <div style='background:#f0f4fa;border-radius:12px;padding:22px 28px;margin-bottom:20px;text-align:center;'>
                <div style='font-size:.78rem;color:#5a6e87;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;'>Your New Verification Code</div>
                <div style='font-size:2.2rem;font-weight:800;color:#0a2d6e;font-family:monospace;letter-spacing:.15em;'>{$code}</div>
            </div>
            <p style='color:#dc2626;font-size:.84rem;margin-bottom:0;'>
                <strong>Note:</strong> Your previous code is now invalid.
            </p>
        ";

        try {
            $result = Mailer::send([
                'to'      => $email,
                'toName'  => $_SESSION['fp_user_name'],
                'subject' => 'SLGTI AMS - New Password Reset Code',
                'body'    => $emailBody,
            ]);
        } catch (Throwable $e) {
            error_log('Mailer Throwable: ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Email failed: ' . $e->getMessage()];
        }

        if ($result['success']) {
            $_SESSION['fp_token']     = $token;
            $_SESSION['fp_code_sent'] = time();
            $output['success']        = true;
            $output['masked_email']   = substr($email, 0, 3) . '***' . strstr($email, '@');
            $output['message']        = 'New verification code sent to your email.';
        } else {
            $output['message'] = 'Failed to resend code. Please try again.';
        }
        echo json_encode($output);
        exit();
    }

    if ($step === 'reset') {
        $newPass = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_email']) || empty($_SESSION['fp_user_id'])) {
            $output['message'] = 'Session expired. Please verify your code first.';
            echo json_encode($output);
            exit();
        }

        if (strlen($newPass) < 8) {
            $output['message'] = 'Password must be at least 8 characters.';
            echo json_encode($output);
            exit();
        }

        if ($newPass !== $confPass) {
            $output['message'] = 'Passwords do not match.';
            echo json_encode($output);
            exit();
        }

        $userId   = (int) $_SESSION['fp_user_id'];
        $userRole = $_SESSION['fp_role'];

        $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

        if ($userRole === 'student') {
            $upd = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
        } else {
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        }
        $upd->bind_param("si", $hashedPass, $userId);

        if ($upd->execute()) {
            if (!empty($_SESSION['fp_email'])) {
                $conn->query("DELETE FROM password_resets WHERE email = '" . $_SESSION['fp_email'] . "'");
            }

            unset($_SESSION['fp_token'], $_SESSION['fp_email'], $_SESSION['fp_role'], $_SESSION['fp_user_id'], $_SESSION['fp_user_name'], $_SESSION['fp_code_sent'], $_SESSION['fp_verified']);

            $output['success'] = true;
            $output['message'] = 'Password updated successfully! You can now sign in.';
        } else {
            $output['message'] = 'Failed to update password. Please try again.';
        }
        $upd->close();
        echo json_encode($output);
        exit();
    }

    echo json_encode($output);
    exit();
}


if (isset($_POST['login'])) {
    rateLimit($conn, 'login', 10, 300);

    if ($_SESSION['login_attempts'] >= $max_attempts) {
        $error = "Too many failed login attempts. Please try again later.";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $error = "Please enter both username/email and password.";
        } else {
            $username_safe = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

            $stmt = $conn->prepare("SELECT id, username, password, role, full_name, status FROM users WHERE username = ?");
            $stmt->bind_param("s", $username_safe);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    $_SESSION['login_attempts'] = 0;
                    rateLimitClear($conn, 'login');
                    $uid = (int) $user['id'];
                    $conn->query("UPDATE users SET last_login = NOW() WHERE id = $uid");
                    logLoginActivity($conn, $user['id'], 'success');

                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Your account is currently inactive. Please contact the administrator.";
                    $_SESSION['login_attempts']++;
                    if ($user['id']) logLoginActivity($conn, $user['id'], 'failed');
                }
            } else {
                $studentStmt = $conn->prepare("SELECT id, student_number, student_name, email FROM students WHERE email = ?");
                $studentStmt->bind_param("s", $username_safe);
                $studentStmt->execute();
                $student = $studentStmt->get_result()->fetch_assoc();

                if ($student) {
                    if ($password === $student['student_number']) {
                        rateLimitClear($conn, 'login');
                        logLoginActivity($conn, $student['id'], 'success');
                        $_SESSION['user_id']   = $student['id'];
                        $_SESSION['username']  = $student['email'];
                        $_SESSION['role']      = 'student';
                        $_SESSION['full_name'] = $student['student_name'];
                        $_SESSION['login_attempts'] = 0;
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $pwRow = $conn->query("SELECT password FROM students WHERE id = {$student['id']}")->fetch_assoc();
                        if (!empty($pwRow['password']) && password_verify($password, $pwRow['password'])) {
                            rateLimitClear($conn, 'login');
                            logLoginActivity($conn, $student['id'], 'success');
                            $_SESSION['user_id']   = $student['id'];
                            $_SESSION['username']  = $student['email'];
                            $_SESSION['role']      = 'student';
                            $_SESSION['full_name'] = $student['student_name'];
                            $_SESSION['login_attempts'] = 0;
                            header("Location: dashboard.php");
                            exit();
                        }
                        $error = "Incorrect password. Students use their Student Number or their custom password.";
                        $_SESSION['login_attempts']++;
                        logLoginActivity($conn, $student['id'], 'failed');
                    }
                } else {
                    $error = "Login failed. Please check your credentials and try again.";
                    $_SESSION['login_attempts']++;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SLGTI Attendance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/css/login_style.css">
</head>

<body>

    <!-- Floating orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="login-page-wrap">
        <div class="login-card">

            <!-- Gradient stripe -->
            <div class="login-stripe"></div>

            <div class="login-inner">

                <!-- Logo -->
                <div class="login-logo-wrap">
                    <div class="login-logo-ring">
                        <img src="Image/SLGTI LOGO.png" alt="SLGTI Logo" class="login-logo-img">
                    </div>
                    <h2 class="login-logo-title">SLGTI Attendance Management System</h2>
                    <div class="login-logo-sub">Sri Lanka German Technical Institute</div>
                </div>

                <!-- Login form (PHP name/id attrs unchanged) -->
                <form method="POST">

                    <div style="margin-bottom:18px;">
                        <label class="lg-label">
                            <i class="fas fa-user"></i> Username / Email
                        </label>
                        <input type="text" name="username" class="lg-input"
                            placeholder="Enter username or email" required
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div style="margin-bottom:20px;">
                        <label class="lg-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="lg-pw-wrap">
                            <input type="password" name="password" id="password" class="lg-input"
                                placeholder="Enter your password" required>
                            <!-- id="togglePassword" UNCHANGED -->
                            <i class="fas fa-eye" id="togglePassword"></i>
                        </div>
                    </div>

                    <button type="submit" name="login" class="lg-btn-signin">
                        <i class="fas fa-sign-in-alt"></i> Sign In to SLGTI
                    </button>

                    <!-- Helper links -->
                    <div class="lg-links">
                        <button type="button" class="lg-link-btn" onclick="fpOpen()">
                            <i class="fas fa-key"></i> Forgot your password?
                        </button>
                        <a href="index.php" class="lg-link-btn">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>

                </form>

                <!-- Hint bar -->
                <div class="lg-hint-bar">
                    <i class="fas fa-info-circle"></i>
                    <p>
                        <strong>Admin / Lecturer:</strong> Sign in with your username &nbsp;&bull;&nbsp;
                        <strong>Student:</strong> Sign in with your email address
                    </p>
                </div>

            </div>

            <!-- Footer -->
            <div class="lg-footer">
                <span>SLGTI</span>
                <span class="lg-footer-dot"></span>
                <span>Kilinochchi</span>
                <span class="lg-footer-dot"></span>
                <span>Attendance System</span>
                <span class="lg-footer-dot"></span>
                <span>&copy; <?php echo date('Y'); ?></span>
            </div>

        </div>
    </div>


    <!--FORGOT PASSWORD-->
    <div class="fp-overlay" id="fpOverlay" onclick="fpOverlayClick(event)">
        <div class="fp-modal" id="fpModal">

            <button class="fp-close" onclick="fpClose()"><i class="fas fa-times"></i></button>

            <div class="fp-progress">
                <div class="fp-dot active" id="dot1"></div>
                <div class="fp-dot" id="dot2"></div>
                <div class="fp-dot" id="dot3"></div>
            </div>

            <!-- ── STEP 1: Enter Email ── -->
            <div class="fp-step active" id="fpStep1">
                <div class="fp-icon step1"><i class="fas fa-envelope-open-text"></i></div>
                <div class="fp-title">Reset Your Password</div>
                <p class="fp-sub">Enter your registered email address and we'll send you a verification code.</p>

                <div class="fp-error" id="fpErr1">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="fpErr1Msg"></span>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="fp-label"><i class="fas fa-at"></i> Email Address</label>
                    <input type="email" class="fp-input" id="fpEmail" placeholder="e.g. student@slgti.ac.lk" autocomplete="email">
                </div>

                <button class="fp-btn" id="fpSendBtn" onclick="fpSendCode()">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>

                <div class="hint-box show" id="fpHint">
                    <i class="fas fa-info-circle"></i>
                    <span id="fpHintText">Enter the email associated with your account.</span>
                </div>
            </div>

            <!-- ── STEP 2: Verify Code ── -->
            <div class="fp-step" id="fpStep2">
                <div class="fp-icon step2"><i class="fas fa-shield-alt"></i></div>
                <div class="fp-title">Enter Verification Code</div>
                <p class="fp-sub">We've sent a 6-digit code to <strong id="fpMaskedEmail"></strong></p>

                <div class="fp-error" id="fpErr2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="fpErr2Msg"></span>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="fp-label"><i class="fas fa-key"></i> Verification Code</label>
                    <div style="display:flex;gap:8px;justify-content:center;margin-bottom:14px;">
                        <input type="text" class="fp-input code-input" maxlength="1" id="fpCode1" oninput="fpCodeInput(this, 1)" onkeydown="fpCodeKeydown(event, 1)">
                        <input type="text" class="fp-input code-input" maxlength="1" id="fpCode2" oninput="fpCodeInput(this, 2)" onkeydown="fpCodeKeydown(event, 2)">
                        <input type="text" class="fp-input code-input" maxlength="1" id="fpCode3" oninput="fpCodeInput(this, 3)" onkeydown="fpCodeKeydown(event, 3)">
                        <input type="text" class="fp-input code-input" maxlength="1" id="fpCode4" oninput="fpCodeInput(this, 4)" onkeydown="fpCodeKeydown(event, 4)">
                        <input type="text" class="fp-input code-input" maxlength="1" id="fpCode5" oninput="fpCodeInput(this, 5)" onkeydown="fpCodeKeydown(event, 5)">
                        <input type="text" class="fp-input code-input" maxlength="1" id="fpCode6" oninput="fpCodeInput(this, 6)" onkeydown="fpCodeKeydown(event, 6)">
                    </div>
                </div>

                <button class="fp-btn" id="fpVerifyBtn" onclick="fpVerifyCode()">
                    <i class="fas fa-check-circle"></i> Verify Code
                </button>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                    <button class="fp-link-btn" id="fpResendBtn" onclick="fpResendCode()">
                        <i class="fas fa-redo"></i> Resend Code
                    </button>
                    <span class="fp-timer" id="fpTimer"></span>
                </div>

                <div class="hint-box" id="fpWrongEmail" style="display:none;margin-top:14px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Wrong email? <a href="#" onclick="fpGoStep(1); return false;" style="color:#1456c8;text-decoration:underline;">Start over</a></span>
                </div>
            </div>

            <!-- ── STEP 3: Set New Password ── -->
            <div class="fp-step" id="fpStep3">
                <div class="fp-icon step3"><i class="fas fa-lock-open"></i></div>
                <div class="fp-title">Set New Password</div>
                <p class="fp-sub">Create a strong new password for your account.</p>

                <div class="fp-error" id="fpErr3">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="fpErr3Msg"></span>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="fp-label"><i class="fas fa-lock"></i> New Password</label>
                    <div class="fp-eye-wrap">
                        <input type="password" class="fp-input" id="fpNewPass"
                            placeholder="Min. 8 characters" autocomplete="new-password"
                            oninput="fpStrength(); fpMatchCheck();">
                        <button type="button" class="fp-eye" onclick="fpToggleEye('fpNewPass','fpEye1')">
                            <i class="fas fa-eye" id="fpEye1"></i>
                        </button>
                    </div>
                    <div class="str-track">
                        <div class="str-fill" id="fpStrFill"></div>
                    </div>
                    <div class="str-lbl" id="fpStrLbl">—</div>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="fp-label"><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="fp-eye-wrap">
                        <input type="password" class="fp-input" id="fpConfPass"
                            placeholder="Re-enter your password" autocomplete="new-password"
                            oninput="fpMatchCheck();">
                        <button type="button" class="fp-eye" onclick="fpToggleEye('fpConfPass','fpEye2')">
                            <i class="fas fa-eye" id="fpEye2"></i>
                        </button>
                    </div>
                    <div class="match-msg" id="fpMatchMsg"></div>
                </div>

                <button class="fp-btn" id="fpSaveBtn" onclick="fpSave()">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </div>

            <!-- ── STEP 4: Success ── -->
            <div class="fp-step" id="fpStep4">
                <div class="fp-success-icon"><i class="fas fa-check"></i></div>
                <div class="fp-title">Password Reset Complete!</div>
                <p class="fp-sub" style="margin-bottom:22px;">
                    Your password has been successfully updated.<br>
                    Please sign in with your new password.
                </p>
                <button class="fp-btn" onclick="fpClose()">
                    <i class="fas fa-sign-in-alt"></i> Back to Sign In
                </button>
            </div>

        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => new bootstrap.Alert(a).close());
        }, 5000);

        let fpTimerInterval = null;

        function fpOpen() {
            document.getElementById('fpOverlay').classList.add('show');
            document.body.style.overflow = 'hidden';
            fpGoStep(1);
            fpResetForm();
        }

        function fpClose() {
            document.getElementById('fpOverlay').classList.remove('show');
            document.body.style.overflow = '';
            fpResetForm();
            if (fpTimerInterval) {
                clearInterval(fpTimerInterval);
                fpTimerInterval = null;
            }
        }

        function fpResetForm() {
            document.getElementById('fpEmail').value = '';
            document.getElementById('fpCode1').value = '';
            document.getElementById('fpCode2').value = '';
            document.getElementById('fpCode3').value = '';
            document.getElementById('fpCode4').value = '';
            document.getElementById('fpCode5').value = '';
            document.getElementById('fpCode6').value = '';
            document.getElementById('fpNewPass').value = '';
            document.getElementById('fpConfPass').value = '';
            document.getElementById('fpMaskedEmail').textContent = '';
            document.getElementById('fpStrFill').style.width = '0%';
            document.getElementById('fpStrLbl').textContent = '—';
            document.getElementById('fpMatchMsg').innerHTML = '';
            document.getElementById('fpTimer').textContent = '';
            document.getElementById('fpWrongEmail').style.display = 'none';
            fpHideError('fpErr1');
            fpHideError('fpErr2');
            fpHideError('fpErr3');
            if (fpTimerInterval) {
                clearInterval(fpTimerInterval);
                fpTimerInterval = null;
            }
        }

        function fpOverlayClick(e) {
            if (e.target === document.getElementById('fpOverlay')) fpClose();
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fpClose();
        });

        function fpGoStep(n) {
            document.querySelectorAll('.fp-step').forEach((s, i) => {
                s.classList.toggle('active', i === n - 1);
            });
            ['dot1', 'dot2', 'dot3'].forEach((id, i) => {
                const dot = document.getElementById(id);
                dot.classList.remove('active', 'done');
                if (i + 1 < n) dot.classList.add('done');
                if (i + 1 === n) dot.classList.add('active');
            });
            if (n === 2) {
                document.getElementById('fpCode1').focus();
                fpStartTimer();
            }
        }

        function fpStartTimer() {
            let seconds = 60;
            const timerEl = document.getElementById('fpTimer');
            const resendBtn = document.getElementById('fpResendBtn');

            if (fpTimerInterval) clearInterval(fpTimerInterval);

            function updateTimer() {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(fpTimerInterval);
                    fpTimerInterval = null;
                    timerEl.innerHTML = '';
                    resendBtn.style.opacity = '1';
                    resendBtn.style.pointerEvents = 'auto';
                } else {
                    timerEl.innerHTML = `<span style="color:#94a3b8;font-size:.82rem;"><i class="fas fa-clock me-1"></i>Resend in ${seconds}s</span>`;
                    resendBtn.style.opacity = '0.5';
                    resendBtn.style.pointerEvents = 'none';
                }
            }

            updateTimer();
            fpTimerInterval = setInterval(updateTimer, 1000);
        }

        async function fpSendCode() {
            fpHideError('fpErr1');
            const email = document.getElementById('fpEmail').value.trim();
            const btn = document.getElementById('fpSendBtn');

            if (!email) {
                fpShowError('fpErr1', 'Please enter your email address.');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                fpShowError('fpErr1', 'Please enter a valid email address.');
                return;
            }

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('ajax_forgot', '1');
                fd.append('step', 'send_code');
                fd.append('email', email);

                const res = await fetch('login.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('fpMaskedEmail').textContent = data.masked_email;
                    document.getElementById('fpHint').style.display = 'none';
                    document.getElementById('fpWrongEmail').style.display = 'flex';
                    fpGoStep(2);
                } else {
                    fpShowError('fpErr1', data.message);
                }
            } catch (e) {
                fpShowError('fpErr1', 'Connection error. Please try again.');
            }

            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
            btn.disabled = false;
        }

        document.getElementById('fpEmail').addEventListener('keydown', e => {
            if (e.key === 'Enter') fpSendCode();
        });

        function fpCodeInput(el, num) {
            const val = el.value.replace(/\D/g, '');
            el.value = val;
            if (val && num < 6) {
                document.getElementById('fpCode' + (num + 1)).focus();
            }
            if (num === 6 && val) {
                fpAutoVerify();
            }
        }

        function fpCodeKeydown(e, num) {
            if (e.key === 'Backspace' && !e.target.value && num > 1) {
                document.getElementById('fpCode' + (num - 1)).focus();
            }
        }

        function fpAutoVerify() {
            const code = fpGetCode();
            if (code.length === 6) {
                fpVerifyCode();
            }
        }

        function fpGetCode() {
            let code = '';
            for (let i = 1; i <= 6; i++) {
                code += document.getElementById('fpCode' + i).value;
            }
            return code;
        }

        async function fpVerifyCode() {
            fpHideError('fpErr2');
            const code = fpGetCode();
            const btn = document.getElementById('fpVerifyBtn');

            if (code.length !== 6) {
                fpShowError('fpErr2', 'Please enter the complete 6-digit code.');
                return;
            }

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('ajax_forgot', '1');
                fd.append('step', 'verify_code');
                fd.append('code', code);

                const res = await fetch('login.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    if (fpTimerInterval) {
                        clearInterval(fpTimerInterval);
                        fpTimerInterval = null;
                    }
                    fpGoStep(3);
                    document.getElementById('fpNewPass').focus();
                } else {
                    fpShowError('fpErr2', data.message);
                    document.querySelectorAll('.code-input').forEach(i => i.value = '');
                    document.getElementById('fpCode1').focus();
                }
            } catch (e) {
                fpShowError('fpErr2', 'Connection error. Please try again.');
            }

            btn.innerHTML = '<i class="fas fa-check-circle"></i> Verify Code';
            btn.disabled = false;
        }

        async function fpResendCode() {
            fpHideError('fpErr2');
            const btn = document.getElementById('fpResendBtn');

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Resending...';
            btn.style.pointerEvents = 'none';

            try {
                const fd = new FormData();
                fd.append('ajax_forgot', '1');
                fd.append('step', 'resend_code');

                const res = await fetch('login.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('fpMaskedEmail').textContent = data.masked_email;
                    fpShowSuccess('fpErr2', data.message);
                    fpStartTimer();
                    document.querySelectorAll('.code-input').forEach(i => i.value = '');
                    document.getElementById('fpCode1').focus();
                } else {
                    fpShowError('fpErr2', data.message);
                    fpStartTimer();
                }
            } catch (e) {
                fpShowError('fpErr2', 'Connection error. Please try again.');
                fpStartTimer();
            }

            btn.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
        }

        async function fpSave() {
            fpHideError('fpErr3');
            const np = document.getElementById('fpNewPass').value;
            const cp = document.getElementById('fpConfPass').value;
            const btn = document.getElementById('fpSaveBtn');

            if (np.length < 8) {
                fpShowError('fpErr3', 'Password must be at least 8 characters.');
                return;
            }
            if (np !== cp) {
                fpShowError('fpErr3', 'Passwords do not match.');
                return;
            }

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('ajax_forgot', '1');
                fd.append('step', 'reset');
                fd.append('new_password', np);
                fd.append('confirm_password', cp);

                const res = await fetch('login.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    fpGoStep(4);
                } else {
                    fpShowError('fpErr3', data.message);
                }
            } catch (e) {
                fpShowError('fpErr3', 'Connection error. Please try again.');
            }

            btn.innerHTML = '<i class="fas fa-save"></i> Update Password';
            btn.disabled = false;
        }

        function fpStrength() {
            const v = document.getElementById('fpNewPass').value;
            const score = [v.length >= 8, /[A-Z]/.test(v), /[0-9]/.test(v), /[^A-Za-z0-9]/.test(v)]
                .filter(Boolean).length;
            const cols = ['#ef4444', '#f97316', '#eab308', '#10b981'];
            const lbls = ['Weak', 'Fair', 'Good', 'Strong'];
            const fill = document.getElementById('fpStrFill');
            const lbl = document.getElementById('fpStrLbl');
            fill.style.width = (score / 4 * 100) + '%';
            fill.style.background = score > 0 ? cols[score - 1] : '#e2e8f0';
            lbl.textContent = score > 0 ? lbls[score - 1] : '—';
            lbl.style.color = score > 0 ? cols[score - 1] : '#94a3b8';
        }

        function fpMatchCheck() {
            const np = document.getElementById('fpNewPass').value;
            const cp = document.getElementById('fpConfPass').value;
            const msg = document.getElementById('fpMatchMsg');
            const ci = document.getElementById('fpConfPass');
            if (!cp) {
                msg.innerHTML = '';
                return;
            }
            if (np === cp) {
                msg.innerHTML = '<span style="color:#10b981"><i class="fas fa-check-circle me-1"></i>Passwords match</span>';
                ci.classList.add('ok');
                ci.classList.remove('error');
            } else {
                msg.innerHTML = '<span style="color:#ef4444"><i class="fas fa-times-circle me-1"></i>Passwords do not match</span>';
                ci.classList.add('error');
                ci.classList.remove('ok');
            }
        }

        function fpToggleEye(fieldId, iconId) {
            const f = document.getElementById(fieldId);
            const i = document.getElementById(iconId);
            const h = f.type === 'password';
            f.type = h ? 'text' : 'password';
            i.className = h ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        function fpShowError(id, msg) {
            document.getElementById(id + 'Msg').textContent = msg;
            document.getElementById(id).classList.add('show');
            document.getElementById(id).classList.remove('success');
        }

        function fpShowSuccess(id, msg) {
            document.getElementById(id + 'Msg').textContent = msg;
            document.getElementById(id).classList.add('show', 'success');
            document.getElementById(id).classList.remove('show');
            setTimeout(() => {
                const el = document.getElementById(id);
                el.classList.remove('show', 'success');
            }, 3000);
        }

        function fpHideError(id) {
            document.getElementById(id).classList.remove('show', 'success');
        }
    </script>
</body>

</html>