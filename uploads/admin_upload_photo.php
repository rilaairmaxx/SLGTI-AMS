<?php
session_start();
header('Content-Type: application/json');

// ── Admin-only ──
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit();
}

require_once "../config/db.php";

$action      = $_POST['action']      ?? 'upload';
$target_id   = intval($_POST['target_id']   ?? 0);
$target_type = $_POST['target_type'] ?? 'user'; // 'user' or 'student'

if ($target_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid target ID.']);
    exit();
}

if (!in_array($target_type, ['user', 'student'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid target type.']);
    exit();
}

// ── Config ──
define('UPLOAD_DIR',    __DIR__ . '/photos/');
define('UPLOAD_URL',    'uploads/photos/');
define('MAX_SIZE',      2 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_EXTS',  ['jpg', 'jpeg', 'png', 'webp', 'gif']);

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// ── Helper: fetch current photo ──
function getCurrentPhoto(mysqli $conn, int $id, string $type): ?string
{
    $table = ($type === 'student') ? 'students' : 'users';
    $q = $conn->prepare("SELECT photo FROM {$table} WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    return $q->get_result()->fetch_assoc()['photo'] ?? null;
}

// ── Helper: delete file ──
function deleteFile(?string $path): void
{
    if (!$path) return;
    $abs = __DIR__ . '/../' . $path;
    if (file_exists($abs) && is_file($abs)) @unlink($abs);
}

// ── Helper: save photo path to DB ──
function savePhotoToDB(mysqli $conn, int $id, string $type, ?string $photoUrl): bool
{
    $table = ($type === 'student') ? 'students' : 'users';
    $upd   = $conn->prepare("UPDATE {$table} SET photo = ? WHERE id = ?");
    $upd->bind_param("si", $photoUrl, $id);
    return $upd->execute();
}

// ════════════════════
//  ACTION: REMOVE
// ════════════════════
if ($action === 'remove') {
    $old = getCurrentPhoto($conn, $target_id, $target_type);
    deleteFile($old);

    if (savePhotoToDB($conn, $target_id, $target_type, null)) {
        echo json_encode(['success' => true, 'message' => 'Photo removed successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error removing photo.']);
    }
    exit();
}

// ════════════════════
//  ACTION: UPLOAD
// ════════════════════
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCodes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension.',
    ];
    $code = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'message' => $errCodes[$code] ?? 'Upload error.']);
    exit();
}

$file    = $_FILES['photo'];
$tmpPath = $file['tmp_name'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Size check
if ($file['size'] > MAX_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 2 MB.']);
    exit();
}

// Extension check
if (!in_array($ext, ALLOWED_EXTS)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, WEBP or GIF.']);
    exit();
}

// Real MIME check
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'File content is not a valid image.']);
    exit();
}

// Delete old photo
deleteFile(getCurrentPhoto($conn, $target_id, $target_type));

// Save new file
$filename = 'photo_' . $target_type . '_' . $target_id . '_' . time() . '.' . $ext;
$destPath = UPLOAD_DIR . $filename;

if (!move_uploaded_file($tmpPath, $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
    exit();
}

$photoUrl = UPLOAD_URL . $filename;

if (savePhotoToDB($conn, $target_id, $target_type, $photoUrl)) {
    echo json_encode([
        'success'   => true,
        'message'   => 'Photo updated successfully.',
        'photo_url' => $photoUrl . '?v=' . time(),
    ]);
} else {
    @unlink($destPath);
    echo json_encode(['success' => false, 'message' => 'Database error. Could not save photo.']);
}
exit();
