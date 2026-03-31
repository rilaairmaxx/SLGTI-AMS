<?php
session_start();

// Log the logout event before destroying the session
if (isset($_SESSION['user_id'])) {
    try {
        include "config/db.php";

        $user_id    = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR']     ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $tableCheck = $conn->query("SHOW TABLES LIKE 'login_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'logout')");
            $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear session data, delete session cookie, then destroy the session
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

header("Location: login.php?logout=1");
exit();
