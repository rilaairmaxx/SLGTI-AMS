<?php
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role      = $_SESSION['role'];
$full_name = $_SESSION['full_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLGTI Attendance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/css/style.css">
    <script src="includes/js/script.js"></script>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <h4>SLGTI</h4>
            <div class="sidebar-subtitle">Attendance Management System</div>
        </div>

        <div class="nav flex-column">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>

            <?php if ($role == 'admin'): ?>
                <a href="create_user.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create_user.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> Create User
                </a>
                <a href="students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Manage Students
                </a>
                <a href="courses.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i> Manage Courses
                </a>
                <a href="add_course.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_course.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i> Add Course
                </a>
                <a href="enroll.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'enroll.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Enroll Student
                </a>
            <?php endif; ?>

            <?php if ($role == 'lecturer'): ?>
                <a href="mark_attendance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'mark_attendance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-square"></i> Mark Attendance
                </a>
            <?php endif; ?>

            <!-- SLGTI Calendar — all roles -->
            <a href="calendar.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> SLGTI Calendar
            </a>

            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

            <hr style="border-color:rgba(255,255,255,0.2);margin:1rem;">

            <a href="logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <div class="user-info">
            <small>Logged in as:</small>
            <strong><?php echo htmlspecialchars($full_name); ?></strong><br>
            <small style="color:rgba(255,255,255,0.6);"><?php echo ucfirst($role); ?></small>
            <div class="mt-2">
                <small style="color:rgba(255,255,255,0.5);font-size:0.75rem;">
                    <i class="fas fa-clock me-1"></i>
                    Session expires in <span id="sessionTimer">30:00</span>
                </small>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="content-wrapper fade-in">

            <script>
                let sessionTimeout = 1800;
                const timerElement = document.getElementById('sessionTimer');

                function updateTimer() {
                    const minutes = Math.floor(sessionTimeout / 60);
                    const seconds = sessionTimeout % 60;
                    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    if (sessionTimeout <= 300) timerElement.style.color = '#ffc107';
                    if (sessionTimeout <= 60) timerElement.style.color = '#ef4444';
                    if (sessionTimeout <= 0) {
                        alert('Your session has expired. You will be redirected to the login page.');
                        window.location.href = 'logout.php';
                    }
                    sessionTimeout--;
                }
                setInterval(updateTimer, 1000);
                ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function(ev) {
                    document.addEventListener(ev, function() {
                        sessionTimeout = 1800;
                        timerElement.style.color = 'rgba(255,255,255,0.5)';
                    }, true);
                });
            </script>