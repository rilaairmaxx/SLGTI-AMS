<?php
// Start session if not already active
if (!isset($_SESSION)) session_start();

// Redirect unauthenticated users to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html>

<head>
    <title>Attendance System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar">
                <h4 class="text-center">AMS SLGTI</h4>
                <hr>
                <a href="dashboard.php">Dashboard</a>

                <!-- Admin-only navigation links -->
                <?php if ($role == 'admin'): ?>
                    <a href="create_user.php">Create User</a>
                    <a href="students.php">Manage Students</a>
                    <a href="add_course.php">Add Course</a>
                    <a href="enroll.php">Enroll Student</a>
                <?php endif; ?>

                <!-- Lecturer-only navigation links -->
                <?php if ($role == 'lecturer'): ?>
                    <a href="mark_attendance.php">Mark Attendance</a>
                <?php endif; ?>

                <!-- Visible to all roles -->
                <a href="report.php">Attendance Report</a>
                <a href="logout.php">Logout</a>

            </div>
            <div class="col-md-10 content">