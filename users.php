<?php
require_once "includes/auth.php";
require_once "config/db.php";

// Admin-only page
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error   = '';

if (isset($_GET['delete'])) {
    $userId = intval($_GET['delete']);

    // Prevent admin from deleting their own account
    if ($userId === $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        // Block deletion if the user has courses assigned to them
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE lecturer_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $courseCount = $stmt->get_result()->fetch_assoc()['count'];

        if ($courseCount > 0) {
            $error = "Cannot delete user. They have assigned courses.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute() ? $message = "User deleted successfully!" : $error = "Error deleting user.";
        }
    }
}

// Fetch all users with their assigned course count
$users = $conn->query("
    SELECT u.id, u.username, u.full_name, u.role, u.created_at,
           COUNT(c.id) as course_count
    FROM users u
    LEFT JOIN courses c ON u.id = c.lecturer_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
?>

<?php include "includes/header.php"; ?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Manage Users</h1>
        <p class="page-subtitle">View and manage all system users</p>
    </div>
    <a href="create_user.php" class="btn btn-primary">
        <i class="fas fa-user-plus me-2"></i>Add New User
    </a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>All Users</h5>
        <input type="text" class="form-control" placeholder="Search users..." id="searchInput" style="width:250px;">
    </div>
    <div class="card-body p-0">
        <table class="table table-hover" id="usersTable">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Courses</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><i class="fas fa-user text-muted me-2"></i><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                        <td>
                            <?php
                            // Badge colour per role: red=admin, blue=lecturer, cyan=student
                            $badgeClass = 'secondary';
                            if ($user['role'] === 'admin')    $badgeClass = 'danger';
                            elseif ($user['role'] === 'lecturer') $badgeClass = 'primary';
                            elseif ($user['role'] === 'student')  $badgeClass = 'info';
                            ?>
                            <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($user['role']); ?></span>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'lecturer'): ?>
                                <span class="badge bg-success"><i class="fas fa-book"></i> <?php echo $user['course_count']; ?> courses</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small></td>
                        <td>
                            <div class="btn-group">
                                <?php if ($user['course_count'] === 0 && $user['id'] !== $_SESSION['user_id']): ?>
                                    <!-- Delete allowed only when user has no courses and is not the logged-in admin -->
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <!-- Lock shown when user has assigned courses or is the current admin -->
                                    <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-lock"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Fetch role breakdown counts for the statistics panel
$stats = $conn->query("
    SELECT
        COUNT(*) as total_users,
        COUNT(CASE WHEN role='admin'    THEN 1 END) as admins,
        COUNT(CASE WHEN role='lecturer' THEN 1 END) as lecturers,
        COUNT(CASE WHEN role='student'  THEN 1 END) as students
    FROM users
")->fetch_assoc();
?>

<div class="card mt-4 text-center">
    <div class="card-header">
        <h6>User Statistics</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col"><h3><?php echo $stats['total_users']; ?></h3><small>Total Users</small></div>
            <div class="col"><h3><?php echo $stats['admins'];      ?></h3><small>Admins</small></div>
            <div class="col"><h3><?php echo $stats['lecturers'];   ?></h3><small>Lecturers</small></div>
            <div class="col"><h3><?php echo $stats['students'];    ?></h3><small>Students</small></div>
        </div>
    </div>
</div>

<script>
    // Live search: filters table rows as the user types in the search box
    document.getElementById("searchInput").addEventListener("keyup", function() {
        let value = this.value.toLowerCase();
        document.querySelectorAll("#usersTable tbody tr").forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
        });
    });
</script>

<?php include "includes/footer.php"; ?>