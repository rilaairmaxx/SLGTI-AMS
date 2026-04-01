<?php
// Session timeout duration in seconds (30 minutes)
$timeout_duration = 1800;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated users to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Expire session after 30 minutes of inactivity
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

// Update last activity timestamp on every page load
$_SESSION['LAST_ACTIVITY'] = time();

// Regenerate session ID every 30 minutes to prevent session fixation
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}
