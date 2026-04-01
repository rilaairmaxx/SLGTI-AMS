<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

require_once "../config/db.php";

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

// ── Fetch current photo path ──
if ($role === 'student') {
    $q = $conn->prepare("SELECT photo FROM students WHERE id = ?");
} else {
    $q = $conn->prepare("SELECT photo FROM users WHERE id = ?");
}

$q->bind_param("i", $userId);
$q->execute();
$row = $q->get_result()->fetch_assoc();

if (empty($row['photo'])) {
    echo json_encode(['success' => true, 'message' => 'No photo to remove.']);
    exit();
}

// ── Delete file from disk ──
$filePath = __DIR__ . '/../' . $row['photo'];
if (file_exists($filePath) && is_file($filePath)) {
    @unlink($filePath);
}

// ── Clear from DB ──
if ($role === 'student') {
    $upd = $conn->prepare("UPDATE students SET photo = NULL WHERE id = ?");
} else {
    $upd = $conn->prepare("UPDATE users SET photo = NULL WHERE id = ?");
}

$upd->bind_param("i", $userId);

if ($upd->execute()) {
    unset($_SESSION['photo']);
    echo json_encode(['success' => true, 'message' => 'Profile photo removed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Could not remove photo.']);
}
exit();
