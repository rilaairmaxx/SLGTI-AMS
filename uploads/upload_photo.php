<?php
session_start();
header('Content-Type: application/json');

// ── Auth ──
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

require_once "../config/db.php";

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

// ── Config ──
define('UPLOAD_DIR',     __DIR__ . '/photos/');          // absolute path
define('UPLOAD_URL',     'uploads/photos/');             // relative URL for <img src>
define('MAX_SIZE_BYTES', 2 * 1024 * 1024);               // 2 MB
define('ALLOWED_TYPES',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_EXTS',   ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Create upload directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ── Validate file is present ──
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCodes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension.',
    ];
    $code = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'message' => $errCodes[$code] ?? 'Upload error.']);
    exit();
}

$file     = $_FILES['photo'];
$tmpPath  = $file['tmp_name'];
$origName = $file['name'];
$fileSize = $file['size'];

// ── Size check ──
if ($fileSize > MAX_SIZE_BYTES) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 2 MB.']);
    exit();
}

// ── Extension check ──
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXTS)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF.']);
    exit();
}

// ── MIME type check (real content, not just extension) ──
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

if (!in_array($mimeType, ALLOWED_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file content. Only images are allowed.']);
    exit();
}

// ── Generate unique filename ──
// Format: photo_{role}_{userId}_{timestamp}.{ext}
$filename = 'photo_' . $role . '_' . $userId . '_' . time() . '.' . $ext;
$destPath = UPLOAD_DIR . $filename;

// ── Delete old photo if exists ──
$oldPhoto = null;

if ($role === 'student') {
    $q = $conn->prepare("SELECT photo FROM students WHERE id = ?");
    $q->bind_param("i", $userId);
    $q->execute();
    $oldPhoto = $q->get_result()->fetch_assoc()['photo'] ?? null;
} else {
    $q = $conn->prepare("SELECT photo FROM users WHERE id = ?");
    $q->bind_param("i", $userId);
    $q->execute();
    $oldPhoto = $q->get_result()->fetch_assoc()['photo'] ?? null;
}

if ($oldPhoto) {
    $oldPath = __DIR__ . '/../' . $oldPhoto;
    if (file_exists($oldPath) && is_file($oldPath)) {
        @unlink($oldPath);
    }
}

// ── Move uploaded file ──
if (!move_uploaded_file($tmpPath, $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
    exit();
}

// ── Save path to DB ──
$photoUrl = UPLOAD_URL . $filename;

if ($role === 'student') {
    $upd = $conn->prepare("UPDATE students SET photo = ? WHERE id = ?");
} else {
    $upd = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
}

$upd->bind_param("si", $photoUrl, $userId);

if (!$upd->execute()) {
    // Rollback: delete the file we just saved
    @unlink($destPath);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit();
}

// ── Update session ──
$_SESSION['photo'] = $photoUrl;

echo json_encode([
    'success'   => true,
    'message'   => 'Profile photo updated successfully.',
    'photo_url' => $photoUrl . '?v=' . time(), // cache-bust
]);
exit();
