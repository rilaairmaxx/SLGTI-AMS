<?php
$page_title = "SLGTI Attendance Management System";
$current_year = date("Y");

// Detect HTTPS for secure cookie/settings
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$site_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SLGTI Attendance Management System - Track student attendance, manage courses, and monitor academic performance at Sri Lanka German Training Institute">
    <meta name="keywords" content="SLGTI, attendance, management, system, Kilinochchi, technical, education">
    <meta name="author" content="SLGTI">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="SLGTI Attendance Management System">
    <meta property="og:description" content="Empowering students and staff at SLGTI with smarter attendance management">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $site_url; ?>">
    
    <title><?php echo $page_title; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/css/front.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="Image/SLGTI.jpg">
    <link rel="canonical" href="<?php echo $site_url; ?>">
</head>

<body>

    <!--NAVBAR-->
    <nav class="slgti-nav">
        <div class="container d-flex align-items-center justify-content-between">

            <a href="#home" class="nav-logo">
                <img src="Image/SLGTI.jpg" alt="SLGTI Logo" class="nav-logo-img">
                <div class="nav-logo-text">
                    <span>SLGTI</span>
                    <span>Sri Lanka German Training Institute</span>
                </div>
            </a>

            <button class="navbar-toggler d-lg-none" type="button" id="navToggle" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="d-none d-lg-flex align-items-center gap-2" id="navMenu">
                <a href="#home" class="nav-link-custom active">Home</a>
                <a href="#courses" class="nav-link-custom">Courses</a>
                <a href="#about" class="nav-link-custom">About Us</a>
                <a href="#contact" class="nav-link-custom">Contact</a>
                <a href="login.php" class="nav-link-custom btn-login-nav ms-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
            </div>

            <!-- Mobile overlay menu -->
            <div id="mobileMenu" aria-modal="true" role="dialog" aria-label="Navigation menu"
                style="display:none; position:fixed; inset:0; background:rgba(8,20,52,.97);
                    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
                    z-index:999; flex-direction:column; align-items:center; justify-content:center; gap:20px;
                    animation:mobileMenuIn .28s cubic-bezier(.16,1,.3,1);">
                <button id="navClose" aria-label="Close menu"
                    style="position:absolute;top:18px;right:18px;background:rgba(255,255,255,.1);
                        border:1.5px solid rgba(255,255,255,.2);color:#fff;font-size:1.4rem;
                        width:42px;height:42px;border-radius:50%;cursor:pointer;
                        display:flex;align-items:center;justify-content:center;
                        transition:background .2s;">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div style="margin-bottom:8px;opacity:.4;">
                    <img src="Image/SLGTI.jpg" alt="SLGTI" style="height:52px;border-radius:8px;background:#fff;padding:4px 8px;">
                </div>
                <a href="#home"    class="nav-link-custom" style="font-size:1.05rem;width:220px;text-align:center;" onclick="closeMobileNav()">Home</a>
                <a href="#courses" class="nav-link-custom" style="font-size:1.05rem;width:220px;text-align:center;" onclick="closeMobileNav()">Courses</a>
                <a href="#about"   class="nav-link-custom" style="font-size:1.05rem;width:220px;text-align:center;" onclick="closeMobileNav()">About Us</a>
                <a href="#contact" class="nav-link-custom" style="font-size:1.05rem;width:220px;text-align:center;" onclick="closeMobileNav()">Contact</a>
                <a href="login.php" class="btn-login-nav nav-link-custom" style="margin-top:8px;padding:12px 40px !important;" onclick="closeMobileNav()">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
            </div>
            <style>
                @keyframes mobileMenuIn {
                    from { opacity:0; transform:translateY(-12px); }
                    to   { opacity:1; transform:translateY(0); }
                }
            </style>
        </div>
    </nav>