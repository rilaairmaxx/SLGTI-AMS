<?php
require_once "includes/auth.php";
require_once "config/db.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$msg = ['type' => '', 'text' => ''];
$preview = [];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = ['type' => 'danger', 'text' => 'File upload failed.'];
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $msg = ['type' => 'danger', 'text' => 'Only CSV files are accepted.'];
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // skip header row
        $row    = 1;
        $imported = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < 3) {
                $errors[] = "Row {$row}: Not enough columns (need at least student_number, student_name, email).";
                continue;
            }

            $student_number = trim($data[0] ?? '');
            $student_name   = trim($data[1] ?? '');
            $email          = trim($data[2] ?? '');
            $phone          = trim($data[3] ?? '');
            $gender         = strtolower(trim($data[4] ?? ''));
            $status         = strtolower(trim($data[5] ?? 'active'));

            if (empty($student_number) || empty($student_name)) {
                $errors[] = "Row {$row}: student_number and student_name are required.";
                continue;
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$row}: Invalid email '{$email}'.";
                continue;
            }
            if (!in_array($status, ['active','inactive','graduated','suspended'])) {
                $status = 'active';
            }
            if (!in_array($gender, ['male','female','other',''])) {
                $gender = null;
            }

            // Skip duplicates
            $chk = $conn->prepare("SELECT id FROM students WHERE student_number = ?");
            $chk->bind_param("s", $student_number);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = "Row {$row}: Student number '{$student_number}' already exists — skipped.";
                continue;
            }

            $stmt = $conn->prepare("INSERT INTO students (student_number, student_name, email, phone, gender, status) VALUES (?,?,?,?,?,?)");
            $gv = $gender ?: null;
            $stmt->bind_param("ssssss", $student_number, $student_name, $email, $phone, $gv, $status);
            if ($stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Row {$row}: Database error for '{$student_number}'.";
            }
        }
        fclose($handle);

        $msg = ['type' => $imported > 0 ? 'success' : 'warning',
                'text' => "{$imported} student(s) imported successfully." . (count($errors) ? ' Some rows had issues.' : '')];

        // Audit log
        $chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
        if ($chk && $chk->num_rows > 0 && $imported > 0) {
            $detail = "{$imported} student(s) imported via CSV by user {$_SESSION['user_id']}";
            $ip     = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $alRole = $_SESSION['role'];
            $alUid  = $_SESSION['user_id'];
            $al = $conn->prepare("INSERT INTO audit_log (user_id, role, action, detail, ip_address) VALUES (?,?,?,?,?)");
            $act = 'import_students';
            $al->bind_param("issss", $alUid, $alRole, $act, $detail, $ip);
            $al->execute();
        }
    }
}
?>
<?php include "includes/header.php"; ?>

<style>
    :root { --royal:#0a2d6e; --mid:#1456c8; --light:#f0f4fa; --dark:#0d1b2e; --muted:#5a6e87; }
    .page-top { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
    .page-icon { width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,var(--royal),var(--mid));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.25rem;box-shadow:0 4px 14px rgba(10,45,110,.25); }
    .page-title { font-size:1.25rem;font-weight:800;color:var(--dark);margin:0 0 2px; }
    .page-sub   { font-size:.8rem;color:var(--muted);margin:0; }
    .upload-zone { border:2px dashed #c7d4e8;border-radius:14px;padding:40px;text-align:center;background:var(--light);transition:border-color .2s; }
    .upload-zone:hover { border-color:var(--mid); }
    .csv-hint { background:#f8fafc;border:1px solid #e4eaf3;border-radius:10px;padding:16px 20px;font-size:.82rem;color:var(--muted); }
    .csv-hint code { background:#e4eaf3;padding:2px 6px;border-radius:4px;font-size:.8rem; }
</style>

<div class="page-top">
    <div class="page-icon"><i class="fas fa-file-import"></i></div>
    <div>
        <p class="page-title">Bulk Student Import</p>
        <p class="page-sub">Upload a CSV file to add multiple students at once</p>
    </div>
</div>

<?php if ($msg['text']): ?>
    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
        <i class="fas fa-<?= $msg['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($msg['text']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <strong><i class="fas fa-exclamation-triangle me-2"></i>Import Issues:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload CSV</h5></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-zone mb-3">
                        <i class="fas fa-file-csv fa-3x mb-3" style="color:var(--mid);"></i>
                        <p class="mb-2 fw-600">Choose a CSV file</p>
                        <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-file-import me-2"></i>Import Students
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>CSV Format</h5></div>
            <div class="card-body">
                <p class="text-muted small mb-3">The first row is treated as a header and skipped. Columns must be in this order:</p>
                <div class="csv-hint">
                    <div class="mb-1"><code>student_number</code> <span class="text-danger">*required</span></div>
                    <div class="mb-1"><code>student_name</code> <span class="text-danger">*required</span></div>
                    <div class="mb-1"><code>email</code></div>
                    <div class="mb-1"><code>phone</code></div>
                    <div class="mb-1"><code>gender</code> <span class="text-muted">(male/female/other)</span></div>
                    <div><code>status</code> <span class="text-muted">(active/inactive/graduated/suspended)</span></div>
                </div>
                <p class="text-muted small mt-3 mb-0">Duplicate student numbers are skipped automatically.</p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-body text-center">
                <a href="students.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-users me-2"></i>View All Students
                </a>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
