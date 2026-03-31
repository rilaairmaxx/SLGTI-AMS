<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include "config/db.php";

$role      = $_SESSION['role']      ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';
$userId    = $_SESSION['user_id'];

// Fetch current profile photo
if ($role === 'student') {
    $pq = $conn->prepare("SELECT photo FROM students WHERE id = ?");
} else {
    $pq = $conn->prepare("SELECT photo FROM users WHERE id = ?");
}
$pq->bind_param("i", $userId);
$pq->execute();
$profilePhoto = $pq->get_result()->fetch_assoc()['photo'] ?? null;
$_SESSION['photo'] = $profilePhoto; // keep session in sync
$userInitial = strtoupper(substr($full_name, 0, 1));
?>

<?php include "includes/header.php"; ?>

<link rel="stylesheet" href="includes/css/dashboard_style.css">

<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="db-top">
    <div class="db-top-left">
        <div class="db-role-icon <?php echo $role; ?>" style="<?php echo $profilePhoto ? 'padding:0;overflow:hidden;' : ''; ?>">
            <?php if ($profilePhoto): ?>
                <img src="<?php echo htmlspecialchars($profilePhoto); ?>?v=<?php echo time(); ?>"
                    alt="Profile"
                    style="width:52px;height:52px;border-radius:14px;object-fit:cover;display:block;">
            <?php elseif ($role === 'admin'): ?>
                <img src="Image/SLGTI LOGO.png" alt="SLGTI"
                    style="width:38px;height:38px;object-fit:contain;border-radius:6px;">
            <?php else: ?>
                <i class="fas fa-<?php echo $role === 'lecturer' ? 'chalkboard-teacher' : 'user-graduate'; ?>"></i>
            <?php endif; ?>
        </div>
        <div>
            <h1 class="db-title">
                Dashboard
                <span class="db-role-badge <?php echo $role; ?>">
                    <i class="fas fa-circle" style="font-size:.4rem;"></i>
                    <?php echo ucfirst($role); ?>
                </span>
            </h1>
            <p class="db-sub">
                Welcome back, <strong><?php echo htmlspecialchars($full_name); ?></strong>!
                Good <?php echo (date('H') < 12) ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>.
            </p>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <div class="db-date-pill">
            <i class="far fa-calendar-alt"></i>
            <?php echo date('l, F j, Y'); ?>
        </div>
        
        <button class="db-dark-btn" onclick="toggleDark()" title="Toggle Dark/Light Mode" aria-label="Toggle Dark Mode">
            <div class="stars">
                <div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
            </div>
            <div class="rays">
                <?php for($i=0; $i<8; $i++): ?><div class="ray"></div><?php endfor; ?>
            </div>
            <div class="knob">
                <i class="fas fa-sun knob-icon" id="dbDarkIcon"></i>
            </div>
            <span class="btn-label" id="dbDarkLabel">Light Mode</span>
        </button>
    </div>
</div>


<?php /* ══════════════════════════════════════
       ADMIN VIEW
══════════════════════════════════════ */ ?>
<?php if ($role === 'admin'): ?>
    <?php
    // Admin: system-wide counts
    $totalLecturers   = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='lecturer'")->fetch_assoc()['total'] ?? 0;
    $totalCourses     = $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'] ?? 0;
    $totalStudents    = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'] ?? 0;
    $totalEnrollments = $conn->query("SELECT COUNT(*) as total FROM enrollments")->fetch_assoc()['total'] ?? 0;
    ?>

    <!-- Welcome banner -->
    <div class="db-welcome">
        <div class="db-welcome-text">
            <h2>System Overview</h2>
            <p>Here's a real-time summary of the SLGTI Attendance System.</p>
        </div>
        <div class="db-welcome-icon"><i class="fas fa-university"></i></div>
    </div>

    <!-- Stats -->
    <div class="db-stats db-stats-4">
        <div class="db-stat blue">
            <div class="db-stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $totalLecturers; ?></div>
                <div class="db-stat-lbl">Lecturers</div>
            </div>
        </div>
        <div class="db-stat amber">
            <div class="db-stat-icon"><i class="fas fa-book-open"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $totalCourses; ?></div>
                <div class="db-stat-lbl">Courses</div>
            </div>
        </div>
        <div class="db-stat green">
            <div class="db-stat-icon"><i class="fas fa-user-graduate"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $totalStudents; ?></div>
                <div class="db-stat-lbl">Students</div>
            </div>
        </div>
        <div class="db-stat purple">
            <div class="db-stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $totalEnrollments; ?></div>
                <div class="db-stat-lbl">Enrollments</div>
            </div>
        </div>
    </div>

    <!-- Quick links -->
    <div class="db-quick">
        <a href="create_user.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#eff6ff;"><i class="fas fa-user-plus" style="color:var(--mid);"></i></div>
            <span class="db-quick-lbl">Create User</span>
        </a>
        <a href="students.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#f0fdf4;"><i class="fas fa-users" style="color:var(--green);"></i></div>
            <span class="db-quick-lbl">Manage Students</span>
        </a>
        <a href="courses.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#fffbeb;"><i class="fas fa-book" style="color:var(--amber);"></i></div>
            <span class="db-quick-lbl">Manage Courses</span>
        </a>
        <a href="enroll.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#f5f3ff;"><i class="fas fa-user-graduate" style="color:var(--purple);"></i></div>
            <span class="db-quick-lbl">Enroll Student</span>
        </a>
        <a href="reports.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#ecfeff;"><i class="fas fa-chart-bar" style="color:var(--info);"></i></div>
            <span class="db-quick-lbl">Reports</span>
        </a>
        <a href="calendar.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#fff1f1;"><i class="fas fa-calendar-alt" style="color:var(--red);"></i></div>
            <span class="db-quick-lbl">Calendar</span>
        </a>
    </div>


    <?php
     /*LECTURER VIEW*/ ?>

<?php elseif ($role === 'lecturer'): ?>
    <?php
    // Lecturer: counts scoped to their own courses
    $lecCourses = $conn->query("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = $userId")->fetch_assoc()['total'] ?? 0;

    // Unique students across all their courses
    $lecStudents = $conn->query("
        SELECT COUNT(DISTINCT e.student_id) as total
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE c.lecturer_id = $userId
    ")->fetch_assoc()['total'] ?? 0;

    $lecAttendance = $conn->query("
        SELECT COUNT(a.id) as total
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN courses c ON e.course_id = c.id
        WHERE c.lecturer_id = $userId
    ")->fetch_assoc()['total'] ?? 0;
    ?>

    <!-- Welcome banner -->
    <div class="db-welcome">
        <div class="db-welcome-text">
            <h2>Your Teaching Summary</h2>
            <p>Overview of your courses, students and attendance records.</p>
        </div>
        <div class="db-welcome-icon"><i class="fas fa-chalkboard-teacher"></i></div>
    </div>

    <!-- Stats -->
    <div class="db-stats db-stats-3">
        <div class="db-stat blue">
            <div class="db-stat-icon"><i class="fas fa-book-open"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $lecCourses; ?></div>
                <div class="db-stat-lbl">My Courses</div>
            </div>
        </div>
        <div class="db-stat green">
            <div class="db-stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $lecStudents; ?></div>
                <div class="db-stat-lbl">Total Students</div>
            </div>
        </div>
        <div class="db-stat amber">
            <div class="db-stat-icon"><i class="fas fa-clipboard-check"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $lecAttendance; ?></div>
                <div class="db-stat-lbl">Attendance Records</div>
            </div>
        </div>
    </div>

    <!-- Quick links -->
    <div class="db-quick">
        <a href="mark_attendance.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#f0fdf4;"><i class="fas fa-check-square" style="color:var(--green);"></i></div>
            <span class="db-quick-lbl">Mark Attendance</span>
        </a>
        <a href="reports.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#ecfeff;"><i class="fas fa-chart-bar" style="color:var(--info);"></i></div>
            <span class="db-quick-lbl">Reports</span>
        </a>
        <a href="calendar.php" class="db-quick-btn">
            <div class="db-quick-icon" style="background:#fff1f1;"><i class="fas fa-calendar-alt" style="color:var(--red);"></i></div>
            <span class="db-quick-lbl">Calendar</span>
        </a>
    </div>


    <?php 
    /*STUDENT VIEW*/ ?>
    
<?php elseif ($role === 'student'): ?>
    <?php
    // Student: personal attendance summary
    $myCourses    = $conn->query("SELECT COUNT(*) as total FROM enrollments WHERE student_id = $userId")->fetch_assoc()['total'] ?? 0;
    $myAttendance = $conn->query("
        SELECT COUNT(a.id) as total
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        WHERE e.student_id = $userId
    ")->fetch_assoc()['total'] ?? 0;

    $presentCount = $conn->query("
        SELECT COUNT(a.id) as total
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        WHERE e.student_id = $userId AND a.status = 'Present'
    ")->fetch_assoc()['total'] ?? 0;

    // Last 5 attendance records for the table below
    $recentAttendance = $conn->query("
        SELECT c.course_name, a.attendance_date, a.status
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $userId
        ORDER BY a.attendance_date DESC
        LIMIT 5
    ");

    $attendancePct = ($myAttendance > 0) ? round($presentCount / $myAttendance * 100) : 0;
    $absentCount   = $myAttendance - $presentCount;
    // SVG arc calculation
    $r = 21;
    $circ = round(2 * M_PI * $r, 2);
    $offset = round($circ - ($attendancePct / 100 * $circ), 2);
    $arcColour = $attendancePct >= 75 ? '#059669' : ($attendancePct >= 50 ? '#d97706' : '#dc2626');
    ?>

    <!-- Welcome banner -->
    <div class="db-welcome">
        <div class="db-welcome-text">
            <h2>My Attendance Overview</h2>
            <p>Track your presence and stay on top of your studies.</p>
        </div>
        <div class="db-welcome-icon"><i class="fas fa-user-graduate"></i></div>
    </div>

    <!-- Stats -->
    <div class="db-stats db-stats-3" style="margin-bottom:28px;">

        <!-- Enrolled courses -->
        <div class="db-stat blue">
            <div class="db-stat-icon"><i class="fas fa-book-open"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $myCourses; ?></div>
                <div class="db-stat-lbl">My Courses</div>
            </div>
        </div>

        <!-- Days present -->
        <div class="db-stat green">
            <div class="db-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $presentCount; ?></div>
                <div class="db-stat-lbl">Days Present</div>
            </div>
        </div>

        <!-- Attendance rate with arc -->
        <div class="db-stat info">
            <div class="db-stat-icon"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="db-stat-num"><?php echo $attendancePct; ?>%</div>
                <div class="db-stat-lbl">Attendance Rate</div>
            </div>
            <!-- SVG arc -->
            <div class="db-rate-wrap" style="margin-left:auto;">
                <div class="db-rate-arc">
                    <svg viewBox="0 0 48 48">
                        <circle class="track" cx="24" cy="24" r="<?php echo $r; ?>" />
                        <circle class="fill" cx="24" cy="24" r="<?php echo $r; ?>"
                            stroke="<?php echo $arcColour; ?>"
                            stroke-dasharray="<?php echo $circ; ?>"
                            stroke-dashoffset="<?php echo $offset; ?>" />
                    </svg>
                    <div class="db-rate-val" style="color:<?php echo $arcColour; ?>;">
                        <?php echo $attendancePct; ?>%
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Mini stats row: total / absent -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px;">
        <div class="db-stat amber" style="padding:16px 18px;">
            <div class="db-stat-icon" style="width:40px;height:40px;font-size:1rem;"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="db-stat-num" style="font-size:1.4rem;"><?php echo $myAttendance; ?></div>
                <div class="db-stat-lbl">Total Sessions</div>
            </div>
        </div>
        <div class="db-stat red" style="padding:16px 18px;">
            <div class="db-stat-icon" style="width:40px;height:40px;font-size:1rem;"><i class="fas fa-times-circle"></i></div>
            <div>
                <div class="db-stat-num" style="font-size:1.4rem;"><?php echo $absentCount; ?></div>
                <div class="db-stat-lbl">Days Absent</div>
            </div>
        </div>
    </div>

    <!-- Recent Attendance Table -->
    <div class="db-card">
        <div class="db-card-head">
            <h6>
                <i class="fas fa-history"></i>
                Recent Attendance
            </h6>
            <a href="reports.php" style="font-size:.78rem;font-weight:600;color:var(--mid);text-decoration:none;">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="db-tbl">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentAttendance && $recentAttendance->num_rows > 0): ?>
                        <?php while ($r = $recentAttendance->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="db-course-name"><?php echo htmlspecialchars($r['course_name']); ?></div>
                                </td>
                                <td>
                                    <div class="db-date-txt">
                                        <i class="far fa-calendar" style="margin-right:4px;color:#c8d0db;"></i>
                                        <?php echo date('d M Y', strtotime($r['attendance_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <!-- Green = Present, Grey = Absent -->
                                    <span class="db-badge <?php echo strtolower($r['status']); ?>">
                                        <span class="db-badge-dot"></span>
                                        <?php echo ucfirst($r['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">
                                <div class="db-empty">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>No attendance records found yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>


<?php include "includes/footer.php"; ?>
<script>
(function () {
    const icon  = document.getElementById('dbDarkIcon');
    const label = document.getElementById('dbDarkLabel');

    function sync() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (icon) icon.className    = dark ? 'fas fa-moon knob-icon' : 'fas fa-sun knob-icon';
        if (label) label.textContent = dark ? 'Dark Mode' : 'Light Mode';
    }

    sync();

    new MutationObserver(sync).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
})();
</script>