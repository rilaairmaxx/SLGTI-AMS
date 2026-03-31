<?php
require_once "includes/auth.php";
require_once "config/db.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Ensure audit_log table exists
$conn->query("CREATE TABLE IF NOT EXISTS audit_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    role        VARCHAR(20)  NOT NULL,
    action      VARCHAR(100) NOT NULL,
    detail      TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user  (user_id),
    INDEX idx_time  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Filters
$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user_id'] ?? '');
$filterDate   = trim($_GET['date']    ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if ($filterAction !== '') { $where[] = "al.action = ?";              $params[] = $filterAction; $types .= 's'; }
if ($filterUser   !== '') { $where[] = "al.user_id = ?";             $params[] = intval($filterUser); $types .= 'i'; }
if ($filterDate   !== '') { $where[] = "DATE(al.created_at) = ?";    $params[] = $filterDate;   $types .= 's'; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countSQL  = "SELECT COUNT(*) as total FROM audit_log al $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total     = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($total / $perPage));

// Fetch rows
$sql  = "SELECT al.*, 
              COALESCE(u.full_name, s.student_name, CONCAT('ID:', al.user_id)) AS display_name
         FROM audit_log al
         LEFT JOIN users    u ON al.role != 'student' AND u.id = al.user_id
         LEFT JOIN students s ON al.role  = 'student' AND s.id = al.user_id
         $whereSQL
         ORDER BY al.created_at DESC
         LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$logs = $stmt->get_result();

// Distinct actions for filter dropdown
$actions = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action");

// Action badge colours
$badgeMap = [
    'login'           => 'success',
    'logout'          => 'secondary',
    'password_change' => 'warning',
    'leave_request'   => 'info',
    'leave_approved'  => 'success',
    'leave_rejected'  => 'danger',
    'import_students' => 'primary',
    'delete'          => 'danger',
    'update'          => 'warning',
    'create'          => 'success',
];
?>
<?php include "includes/header.php"; ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1 class="page-title"><i class="fas fa-history me-2"></i>Audit Log</h1>
        <p class="page-subtitle">Track all system activity and changes</p>
    </div>
    <span class="badge bg-secondary fs-6"><?php echo number_format($total); ?> records</span>
</div>

<!-- Filters -->
<form method="GET" class="row g-2 mb-4">
    <div class="col-sm-4 col-md-3">
        <select name="action" class="form-select form-select-sm">
            <option value="">All Actions</option>
            <?php while ($a = $actions->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>"
                    <?php echo $filterAction === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $a['action']))); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-sm-4 col-md-3">
        <input type="number" name="user_id" class="form-control form-control-sm"
               placeholder="User ID" value="<?php echo htmlspecialchars($filterUser); ?>">
    </div>
    <div class="col-sm-4 col-md-3">
        <input type="date" name="date" class="form-control form-control-sm"
               value="<?php echo htmlspecialchars($filterDate); ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
        <a href="audit_log.php" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Date / Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Detail</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($logs->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No audit records found.</td></tr>
                <?php else: ?>
                    <?php $i = $offset + 1; while ($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted small"><?php echo $i++; ?></td>
                        <td class="small text-nowrap"><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($row['display_name']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['role']); ?></span></td>
                        <td>
                            <?php
                            $badge = $badgeMap[$row['action']] ?? 'light';
                            $label = ucwords(str_replace('_', ' ', $row['action']));
                            ?>
                            <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($label); ?></span>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($row['detail'] ?? '—'); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($row['ip_address'] ?? '—'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $p;
                    echo $filterAction ? '&action='.urlencode($filterAction) : '';
                    echo $filterUser   ? '&user_id='.urlencode($filterUser)  : '';
                    echo $filterDate   ? '&date='.urlencode($filterDate)     : '';
                ?>"><?php echo $p; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
