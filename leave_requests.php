<?php
require_once "includes/auth.php";
require_once "config/db.php";

$role   = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$msg    = ['type' => '', 'text' => ''];

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS leave_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT         NOT NULL,
    course_id    INT,
    leave_date   DATE        NOT NULL,
    reason       TEXT        NOT NULL,
    status       ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by  INT,
    reviewed_at  DATETIME,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── STUDENT: submit request ──
if ($role === 'student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $courseId  = intval($_POST['course_id']  ?? 0);
    $leaveDate = trim($_POST['leave_date']   ?? '');
    $reason    = trim($_POST['reason']       ?? '');

    if (empty($leaveDate) || empty($reason)) {
        $msg = ['type' => 'warning', 'text' => 'Date and reason are required.'];
    } elseif (strlen($reason) < 10) {
        $msg = ['type' => 'warning', 'text' => 'Please provide a more detailed reason (min 10 characters).'];
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_requests (student_id, course_id, leave_date, reason) VALUES (?,?,?,?)");
        $cid  = $courseId ?: null;
        $stmt->bind_param("iiss", $userId, $cid, $leaveDate, $reason);
        if ($stmt->execute()) {
            $msg = ['type' => 'success', 'text' => 'Leave request submitted successfully.'];
        } else {
            $msg = ['type' => 'danger', 'text' => 'Failed to submit request.'];
        }
    }
}

// ── LECTURER / ADMIN: approve or reject ──
if (in_array($role, ['lecturer', 'admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'])) {
    $reqId  = intval($_POST['review_id']);
    $action = $_POST['review_action'] ?? '';
    if (in_array($action, ['approved', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE leave_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        $stmt->bind_param("sii", $action, $userId, $reqId);
        if ($stmt->execute()) {
            $msg = ['type' => 'success', 'text' => 'Request ' . $action . '.'];
            // Audit log
            $chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
            if ($chk && $chk->num_rows > 0) {
                $detail = "Leave request ID {$reqId} {$action} by user {$userId}";
                $ip     = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $al = $conn->prepare("INSERT INTO audit_log (user_id, role, action, detail, ip_address) VALUES (?,?,?,?,?)");
                $act = 'leave_' . $action;
                $al->bind_param("issss", $userId, $role, $act, $detail, $ip);
                $al->execute();
            }
        } else {
            $msg = ['type' => 'danger', 'text' => 'Failed to update request.'];
        }
    }
}

// ── Fetch data ──
if ($role === 'student') {
    // Student sees their own requests
    $requests = $conn->prepare("
        SELECT lr.*, c.course_name
        FROM leave_requests lr
        LEFT JOIN courses c ON lr.course_id = c.id
        WHERE lr.student_id = ?
        ORDER BY lr.created_at DESC
    ");
    $requests->bind_param("i", $userId);
    $requests->execute();
    $requests = $requests->get_result();

    // Enrolled courses for the form
    $courses = $conn->prepare("
        SELECT c.id, c.course_name FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
    ");
    $courses->bind_param("i", $userId);
    $courses->execute();
    $courses = $courses->get_result();

} elseif ($role === 'lecturer') {
    // Lecturer sees requests for their courses
    $requests = $conn->query("
        SELECT lr.*, s.student_name, s.student_number, c.course_name
        FROM leave_requests lr
        JOIN students s ON lr.student_id = s.id
        LEFT JOIN courses c ON lr.course_id = c.id
        WHERE c.lecturer_id = {$userId} OR lr.course_id IS NULL
        ORDER BY lr.status = 'pending' DESC, lr.created_at DESC
    ");
} else {
    // Admin sees all
    $requests = $conn->query("
        SELECT lr.*, s.student_name, s.student_number, c.course_name,
               COALESCE(u.full_name, 'System') AS reviewer_name
        FROM leave_requests lr
        JOIN students s ON lr.student_id = s.id
        LEFT JOIN courses c ON lr.course_id = c.id
        LEFT JOIN users u ON lr.reviewed_by = u.id
        ORDER BY lr.status = 'pending' DESC, lr.created_at DESC
    ");
}

$statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
?>
<?php include "includes/header.php"; ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-times me-2"></i>Leave / Excuse Requests</h1>
        <p class="page-subtitle">
            <?php echo $role === 'student' ? 'Submit and track your leave requests' : 'Review student leave requests'; ?>
        </p>
    </div>
</div>

<?php if ($msg['text']): ?>
    <div class="alert alert-<?php echo $msg['type']; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($msg['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($role === 'student'): ?>
<!-- ── Submit Form ── -->
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold"><i class="fas fa-plus-circle me-2"></i>New Leave Request</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="submit_request" value="1">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Course (optional)</label>
                    <select name="course_id" class="form-select">
                        <option value="">— All / General —</option>
                        <?php while ($c = $courses->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Leave Date <span class="text-danger">*</span></label>
                    <input type="date" name="leave_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="2" minlength="10" required
                              placeholder="Briefly explain your reason..."></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Requests Table ── -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <?php if ($role !== 'student'): ?>
                            <th>Student</th>
                        <?php endif; ?>
                        <th>Course</th>
                        <th>Leave Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <?php if (in_array($role, ['lecturer', 'admin'])): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($requests->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No leave requests found.</td></tr>
                <?php else: ?>
                    <?php while ($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <?php if ($role !== 'student'): ?>
                            <td>
                                <strong><?php echo htmlspecialchars($row['student_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($row['student_number']); ?></small>
                            </td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['leave_date']); ?></td>
                        <td class="small"><?php echo htmlspecialchars($row['reason']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $statusBadge[$row['status']]; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <?php if (in_array($role, ['lecturer', 'admin']) && $row['status'] === 'pending'): ?>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="review_id" value="<?php echo $row['id']; ?>">
                                    <button name="review_action" value="approved" class="btn btn-success btn-sm me-1"
                                            onclick="return confirm('Approve this request?')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button name="review_action" value="rejected" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Reject this request?')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </td>
                        <?php elseif (in_array($role, ['lecturer', 'admin'])): ?>
                            <td class="small text-muted">Reviewed</td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
