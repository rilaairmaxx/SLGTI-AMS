<?php
/**
 * IP-based rate limiter using a DB table.
 * Usage: require_once "rate_limit.php"; rateLimit($conn, 'login', 5, 300);
 */

/**
 * Check and record an attempt for a given action from the current IP.
 *
 * @param mysqli $conn
 * @param string $action    Identifier, e.g. 'login', 'otp'
 * @param int    $maxHits   Max allowed attempts in the window
 * @param int    $window    Time window in seconds
 * @param bool   $jsonError If true, respond with JSON on block; otherwise redirect to login.php
 */
function rateLimit(mysqli $conn, string $action, int $maxHits = 5, int $window = 300, bool $jsonError = false): void
{
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS rate_limits (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45)  NOT NULL,
        action     VARCHAR(50)  NOT NULL,
        attempts   INT          NOT NULL DEFAULT 1,
        first_hit  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_hit   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ip_action (ip_address, action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Purge expired windows
    $conn->query("DELETE FROM rate_limits WHERE TIMESTAMPDIFF(SECOND, first_hit, NOW()) > {$window}");

    // Fetch current record
    $stmt = $conn->prepare("SELECT id, attempts FROM rate_limits WHERE ip_address = ? AND action = ?");
    $stmt->bind_param("ss", $ip, $action);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        if ($row['attempts'] >= $maxHits) {
            // Blocked
            if ($jsonError) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait and try again.']);
                exit();
            }
            session_start();
            $_SESSION['rate_limit_error'] = "Too many {$action} attempts from your IP. Please wait a few minutes.";
            header("Location: login.php");
            exit();
        }
        // Increment
        $conn->query("UPDATE rate_limits SET attempts = attempts + 1 WHERE id = {$row['id']}");
    } else {
        // First attempt
        $stmt2 = $conn->prepare("INSERT INTO rate_limits (ip_address, action) VALUES (?, ?)");
        $stmt2->bind_param("ss", $ip, $action);
        $stmt2->execute();
    }
}

/**
 * Clear rate limit record for an IP + action (call on successful login).
 */
function rateLimitClear(mysqli $conn, string $action): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ?");
    $stmt->bind_param("ss", $ip, $action);
    $stmt->execute();
}
