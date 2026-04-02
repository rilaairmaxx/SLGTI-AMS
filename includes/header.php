<?php

// Start session if not already active
if (!isset($_SESSION)) session_start();

// Redirect unauthenticated users to login
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/css/style.css">
    <script src="includes/js/script.js"></script>

    <style>
        :root {
            --royal: #0a2d6e;
            --mid: #1456c8;
            --accent: #1e90ff;
            --sidebar: 260px;
            --topbar: 0px;
            /* no separate topbar — sidebar only */
            --dark: #0d1b2e;
            --muted: #5a6e87;
            --border: #e4eaf3;
            --light: #f0f4fa;
            --green: #059669;
            --red: #dc2626;
            --amber: #d97706;
        }

        /* ── Body & layout ── */
        body {
            font-family: 'Plus Jakarta Sans', 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--dark);
            line-height: 1.6;
        }

        /* ── Base Sidebar (Light Mode Default) ── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar);
            height: 100vh;
            background: #ffffff;
            color: #1e293b;
            border-right: 1px solid #e2e8f0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0, 0, 0, .04);
            overflow-y: auto;
            overflow-x: hidden;
            transition: left .3s cubic-bezier(0.4, 0, 0.2, 1), background .4s ease, border-color .4s;
        }

        [data-theme="dark"] .sidebar {
            background: #0f172a;
            border-right: 1px solid #1e293b;
            box-shadow: 4px 0 24px rgba(0, 0, 0, .3);
        }

        /* ── Sidebar header ── */
        .sidebar-header {
            padding: 26px 20px 20px;
            flex-shrink: 0;
            text-align: center;
            background: transparent;
            position: relative;
        }

        .sidebar-header::after {
            content: '';
            position: absolute;
            bottom: 0px;
            left: 20px;
            right: 20px;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            transition: background .4s;
        }

        [data-theme="dark"] .sidebar-header::after {
            background: linear-gradient(90deg, transparent, #1e293b, transparent);
        }

        .sidebar-header h4 {
            margin: 0 0 2px;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--royal);
            letter-spacing: .02em;
            justify-content: center;
            transition: color .4s;
        }

        [data-theme="dark"] .sidebar-header h4 {
            color: #f8fafc;
        }

        .sidebar-subtitle {
            font-size: .67rem;
            color: #64748b;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 8px;
            transition: color .4s;
        }

        [data-theme="dark"] .sidebar-subtitle {
            color: #94a3b8;
        }

        /* Live date/time */
        #liveDatetime {
            margin-top: 10px;
            font-size: .74rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.5;
            transition: color .4s;
        }

        [data-theme="dark"] #liveDatetime {
            color: #64748b;
        }

        /* ── Section labels ── */
        .nav-section-label {
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #94a3b8;
            padding: 16px 20px 6px;
            transition: color .4s;
        }

        [data-theme="dark"] .nav-section-label {
            color: #475569;
        }

        /* ── Nav links (Floating Pills) ── */
        .nav {
            margin-top: 10px;
            padding-bottom: 20px;
            flex: 1;
        }

        .nav-link {
            color: #475569;
            padding: 11px 18px;
            margin: 3px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            border-radius: 12px;
            transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: .88rem;
            font-weight: 600;
            position: relative;
        }

        [data-theme="dark"] .nav-link {
            color: #94a3b8;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.05rem;
            flex-shrink: 0;
            transition: transform .3s cubic-bezier(0.34, 1.56, 0.64, 1), color .3s;
            color: #94a3b8;
        }

        [data-theme="dark"] .nav-link i {
            color: #64748b;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: var(--mid);
            transform: translateX(4px);
        }

        [data-theme="dark"] .nav-link:hover {
            background: rgba(255, 255, 255, .05);
            color: #f8fafc;
        }

        .nav-link:hover i {
            transform: scale(1.15) rotate(-3deg);
            color: var(--mid);
        }

        [data-theme="dark"] .nav-link:hover i {
            color: #60a5fa;
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--mid) 0%, var(--royal) 100%);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(20, 86, 200, .25);
        }

        [data-theme="dark"] .nav-link.active {
            background: linear-gradient(135deg, var(--mid) 0%, var(--royal) 100%);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .5), inset 0 1px 1px rgba(255, 255, 255, .1);
        }

        .nav-link.active i {
            color: #ffffff;
        }

        [data-theme="dark"] .nav-link.active i {
            color: #ffffff;
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: -14px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: var(--mid);
            border-radius: 0 4px 4px 0;
        }

        [data-theme="dark"] .nav-link.active::before {
            background: #3b82f6;
        }

        /* Logout special styling */
        .nav-link.logout-link:hover {
            background: #fef2f2;
            color: #ef4444;
        }

        .nav-link.logout-link:hover i {
            color: #ef4444;
        }

        [data-theme="dark"] .nav-link.logout-link:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
        }

        [data-theme="dark"] .nav-link.logout-link:hover i {
            color: #fca5a5;
        }

        /* Sidebar HR */
        .sidebar hr {
            border-color: #e2e8f0;
            margin: 14px 20px;
            transition: border-color .4s;
        }

        [data-theme="dark"] .sidebar hr {
            border-color: #1e293b;
        }

        /* ── User info block (Sleek Bottom Section) ── */
        .user-info {
            padding: 20px 20px 26px;
            background: transparent;
            flex-shrink: 0;
            text-align: center;
            position: relative;
        }

        .user-info::before {
            content: '';
            position: absolute;
            top: 0px;
            left: 20px;
            right: 20px;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            transition: background .4s;
        }

        [data-theme="dark"] .user-info::before {
            background: linear-gradient(90deg, transparent, #1e293b, transparent);
        }

        .user-info small {
            color: #64748b;
            font-size: .68rem;
            transition: color .4s;
            display: block;
        }

        [data-theme="dark"] .user-info small {
            color: #94a3b8;
        }

        .user-info strong {
            color: var(--royal);
            font-size: 1.05rem;
            font-weight: 800;
            display: block;
            margin: 4px 0;
            transition: color .4s;
        }

        [data-theme="dark"] .user-info strong {
            color: #f8fafc;
        }

        #sessionTimer {
            font-weight: 800;
            color: #94a3b8;
            font-variant-numeric: tabular-nums;
            transition: color .4s;
        }

        [data-theme="dark"] #sessionTimer {
            color: #64748b;
        }

        /* ── Main content ── */
        .main-content {
            margin-left: var(--sidebar);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-wrapper {
            padding: 28px 28px 40px;
            flex: 1;
            animation: fadeIn .4s ease both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Mobile: hamburger button ── */
        .mobile-menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            border-radius: 50%;
            width: 46px;
            height: 46px;
            display: none;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--mid), var(--royal));
            border: none;
            box-shadow: 0 4px 14px rgba(10, 45, 110, .35);
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform .2s;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.08);
        }

        @media (max-width: 992px) {
            .sidebar {
                left: -260px;
            }

            .sidebar.show {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .content-wrapper {
                padding: 20px 16px 32px;
            }

            .mobile-menu-toggle {
                display: flex;
            }
        }

        /* Sidebar overlay on mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>

<body>

    <div class="sidebar" id="sidebar">

        <!-- Brand header — id="liveDatetime" UNCHANGED -->
        <div class="sidebar-header">
            <h4>SLGTI</h4>
            <div class="sidebar-subtitle">Attendance Management System</div>
            <div id="liveDatetime"></div>
        </div>

        <!-- Navigation links — active class logic UNCHANGED -->
        <div class="nav flex-column">

            <div class="nav-section-label">Main</div>

            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>

            <!-- Admin-only navigation links — PHP UNCHANGED -->
            <?php if ($role == 'admin'): ?>
                <div class="nav-section-label">Administration</div>
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
                <a href="timetable.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> Timetable
                </a>
                <a href="import_students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'import_students.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-import"></i> Import Students
                </a>
                <a href="audit_log.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'audit_log.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Audit Log
                </a>
            <?php endif; ?>

            <!-- Lecturer-only navigation links — PHP UNCHANGED -->
            <?php if ($role == 'lecturer'): ?>
                <div class="nav-section-label">Teaching</div>

                <a href="attendance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> Attendance Sheet
                </a>
                <a href="my_courses.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i> My Courses
                </a>
                <a href="timetable.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> My Timetable
                </a>
            <?php endif; ?>

            <div class="nav-section-label">General</div>

            <?php if ($role == 'student'): ?>
                <a href="timetable.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> My Timetable
                </a>
            <?php endif; ?>

            <a href="profile_photo.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile_photo.php' ? 'active' : ''; ?>">
                <i class="fas fa-camera"></i> Profile Photo
            </a>

            <a href="change_password.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'change_password.php' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> Change Password
            </a>

            <a href="leave_requests.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leave_requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-times"></i> Leave Requests
            </a>

            <!-- Calendar — visible to ALL roles — PHP UNCHANGED -->
            <a href="calendar.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Calendar
            </a>

            <!-- Reports link visible to all roles — PHP UNCHANGED -->
            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

            <!-- Notifications — visible to all roles -->
            <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" id="notifNavLink">
                <i class="fas fa-bell"></i> Notifications
                <span id="notifNavBadge" style="display:none;margin-left:auto;background:#dc2626;color:#fff;border-radius:20px;padding:1px 8px;font-size:.65rem;font-weight:800;"></span>
            </a>

            <hr style="border-color: rgba(255,255,255,0.2); margin: 1rem;">

            <!-- Logout — onclick UNCHANGED -->
            <a href="logout.php" class="nav-link logout-link" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>

        </div>



        <!-- Sidebar footer — id="sessionTimer" UNCHANGED -->
        <div class="user-info">
            <small>Logged in as:</small>
            <strong><?php echo htmlspecialchars($full_name); ?></strong>
            <small style="text-transform:uppercase; letter-spacing: .08em; font-weight: 600;"><?php echo ucfirst($role); ?></small>
            <div style="margin-top: 14px;">
                <small style="font-size: 0.7rem;">
                    <i class="fas fa-clock me-1"></i>
                    Session expires in <span id="sessionTimer">30:00</span>
                </small>
            </div>
        </div>

    </div>

    <!-- Mobile overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="document.getElementById('sidebar').classList.remove('show'); this.classList.remove('show');"></div>

    <!-- ══════════════════════════════════════
     MAIN CONTENT WRAPPER
══════════════════════════════════════ -->
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

                let activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
                activityEvents.forEach(function(eventName) {
                    document.addEventListener(eventName, function() {
                        sessionTimeout = 1800;
                        timerElement.style.color = '';
                    }, true);
                });

                // Live date & time display — id="liveDatetime" UNCHANGED
                function updateDatetime() {
                    const now = new Date();
                    const dateStr = now.toLocaleDateString('en-US', {
                        weekday: 'short',
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    const timeStr = now.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    document.getElementById('liveDatetime').innerHTML = `<i class="fas fa-calendar-day me-1"></i>${dateStr}<br><i class="fas fa-clock me-1"></i>${timeStr}`;
                }
                updateDatetime();
                setInterval(updateDatetime, 1000);

                // ── Notification badge polling ──
                function fetchNotifCount() {
                    fetch('notifications.php?action=count', {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(r => r.json())
                        .then(data => {
                            const count = data.count || 0;
                            const badge = document.getElementById('notifBadge');
                            const navBadge = document.getElementById('notifNavBadge');
                            if (count > 0) {
                                const label = count > 99 ? '99+' : count;
                                if (badge) {
                                    badge.textContent = label;
                                    badge.style.display = 'flex';
                                }
                                if (navBadge) {
                                    navBadge.textContent = label;
                                    navBadge.style.display = 'inline-block';
                                }
                            } else {
                                if (badge) badge.style.display = 'none';
                                if (navBadge) navBadge.style.display = 'none';
                            }
                        })
                        .catch(() => {});
                }
                fetchNotifCount();
                setInterval(fetchNotifCount, 60000); // poll every 60s

                // ── Dark mode init ──
                (function() {
                    const saved = localStorage.getItem('slgti_theme') || 'light';
                    document.documentElement.setAttribute('data-theme', saved);
                    const icon = document.getElementById('darkIcon');
                    const label = document.getElementById('darkLabel');
                    if (saved === 'dark') {
                        if (icon) {
                            icon.classList.replace('fa-moon', 'fa-sun');
                        }
                        if (label) label.textContent = 'Light Mode';
                    }
                })();

                function toggleDark() {
                    const current = document.documentElement.getAttribute('data-theme') || 'light';
                    const next = current === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('slgti_theme', next);
                    const icon = document.getElementById('darkIcon');
                    const label = document.getElementById('darkLabel');
                    if (next === 'dark') {
                        if (icon) icon.classList.replace('fa-moon', 'fa-sun');
                        if (label) label.textContent = 'Light Mode';
                    } else {
                        if (icon) icon.classList.replace('fa-sun', 'fa-moon');
                        if (label) label.textContent = 'Dark Mode';
                    }
                }
            </script>
