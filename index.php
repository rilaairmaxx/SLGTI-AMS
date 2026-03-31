<?php
include 'includes/front_header.php';
?>

<!--HERO-->
<section class="hero" id="home">
    <div class="hero-bg" style="background-image: linear-gradient(155deg, rgba(10,45,110,.92) 0%, rgba(10,45,110,.78) 50%, rgba(20,86,200,.65) 100%), url('Image/SLGTI_01.jpg'); background-size: cover; background-position: center;"></div>
    <div class="hero-grid-overlay"></div>

    <div class="container py-5">
        <div class="row align-items-center justify-content-center">

            <!-- Hero Content -->
            <div class="col-lg-8 text-center">
                <div class="hero-badge anim-1">
                    <i class="bi bi-shield-check-fill"></i>
                    Attendance Management System
                </div>
                <h1 class="anim-2">
                    Welcome to
                    <span class="highlight">Your Gateway to SLGTI</span>
                </h1>
                <p class="lead anim-3 mx-auto">
                    Empowering students and staff at SLGTI, Kilinochchi with smarter attendance management.
                </p>
                <div class="hero-cta-group anim-4 justify-content-center">
                    <a href="#courses" class="btn-hero-outline">
                        <i class="bi bi-book"></i> Our Departments
                    </a>
                </div>
                <div class="hero-stats anim-5 justify-content-center">
                    <div class="stat-item">
                        <div class="stat-number">5+</div>
                        <div class="stat-label">Departments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">NVQ</div>
                        <div class="stat-label">Certified</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Access</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>


<!--DEPARTMENTS-->
<section id="courses">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-label">What We Offer</span>
            <h2 class="section-title">Our Specialized Departments</h2>
            <p class="section-subtitle mx-auto">
                SLGTI provides nationally recognized NVQ-certified technical education
                across five specialized disciplines.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-sm-6 col-lg-4">
                <div class="dept-card dept-auto">
                    <div class="dept-icon"><i class="bi bi-car-front-fill"></i></div>
                    <h5>Automotive Technology</h5>
                    <p>Specializing in NVQ levels for engine repair, diagnostics, and advanced electronic vehicle systems.</p>
                    <span class="dept-tag">NVQ Level 3–5</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="dept-card dept-civil">
                    <div class="dept-icon"><i class="bi bi-building"></i></div>
                    <h5>Construction Technology</h5>
                    <p>Civil engineering foundations, structural design, and modern sustainable building practices.</p>
                    <span class="dept-tag">NVQ Level 3–5</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="dept-card dept-mech">
                    <div class="dept-icon"><i class="bi bi-gear-wide-connected"></i></div>
                    <h5>Mechanical Technology</h5>
                    <p>Precision machining, CNC programming, lathe operation, and industrial maintenance engineering.</p>
                    <span class="dept-tag">NVQ Level 3–5</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="dept-card dept-elec">
                    <div class="dept-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                    <h5>Electrical &amp; Electronic</h5>
                    <p>Industrial automation, smart wiring systems, PLC programming, and power electronics.</p>
                    <span class="dept-tag">NVQ Level 3–5</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="dept-card dept-ict">
                    <div class="dept-icon"><i class="bi bi-laptop-fill"></i></div>
                    <h5>Information &amp; Communication Technology</h5>
                    <p>Software development, network infrastructure, cybersecurity, and systems administration.</p>
                    <span class="dept-tag">NVQ Level 3–5</span>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="dept-card d-flex flex-column justify-content-center align-items-center text-center"
                     style="background:linear-gradient(135deg,#0a2d6e,#1456c8); border:none;">
                    <i class="bi bi-arrow-right-circle-fill text-white mb-3" style="font-size:2.4rem;"></i>
                    <h5 style="color:#fff;">Ready to Join?</h5>
                    <p style="color:rgba(255,255,255,.7);">Explore NVQ pathways and begin your technical career with SLGTI.</p>
                    <a href="#contact" class="btn-hero-outline mt-3" style="font-size:.85rem;padding:9px 22px;">
                        Get In Touch
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>


<!--ABOUT US-->
<section id="about">
    <div class="container">
        <div class="row align-items-center gy-5">

            <div class="col-lg-5">
                <div class="about-image-wrap">
                    <img src="https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=800&q=80"
                         alt="SLGTI Workshop">
                    <div class="about-image-badge">
                        <div class="num">Est.</div>
                        <div class="lbl">Sri Lanka German Training Institute</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 offset-lg-1">
                <span class="section-label">About SLGTI</span>
                <h2 class="section-title">Building Sri Lanka's<br>Technical Workforce</h2>
                <p class="mb-4" style="color:var(--text-muted);line-height:1.8;font-size:.95rem;">
                    Located in Kilinochchi, SLGTI provides world-class technical and vocational education
                    with German-standard training methodology, equipping graduates for industry demands.
                </p>

                <div class="about-feature">
                    <div class="about-feature-icon"><i class="bi bi-award-fill"></i></div>
                    <div class="about-feature-text">
                        <h6>NVQ Certified Programs</h6>
                        <p>All courses align with the National Vocational Qualifications framework, ensuring industry recognition.</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="about-feature-icon"><i class="bi bi-tools"></i></div>
                    <div class="about-feature-text">
                        <h6>Hands-On Training</h6>
                        <p>State-of-the-art workshops and laboratories with German-standard equipment and tooling.</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="about-feature-icon"><i class="bi bi-people-fill"></i></div>
                    <div class="about-feature-text">
                        <h6>Expert Lecturers</h6>
                        <p>Industry-experienced instructors committed to delivering high-quality technical education.</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="about-feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="about-feature-text">
                        <h6>Digital Attendance System</h6>
                        <p>Our modern portal tracks attendance in real-time, ensuring transparency for students and lecturers alike.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>


<!--CONTACT-->
<section id="contact">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-label">Get In Touch</span>
            <h2 class="section-title">Contact Us</h2>
            <p class="section-subtitle mx-auto" style="color:rgba(255,255,255,.6);">
                Whether it's a portal issue or a program enquiry, our team at SLGTI is ready to assist you.
            </p>
        </div>

        <div class="row g-4 justify-content-center">

            <div class="col-sm-6 col-lg-3">
                <div class="contact-card">
                    <i class="bi bi-geo-alt-fill ci"></i>
                    <h6>Address</h6>
                    <p>SLGTI, Kilinochchi,<br>Sri Lanka</p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="contact-card">
                    <i class="bi bi-envelope-fill ci"></i>
                    <h6>Email</h6>
                    <a href="mailto:support@slgti.ac.lk">support@slgti.ac.lk</a>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="contact-card">
                    <i class="bi bi-telephone-fill ci"></i>
                    <h6>Phone</h6>
                    <a href="tel:+94212060500">+94 21 206 0500</a>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="contact-card">
                    <i class="bi bi-clock-fill ci"></i>
                    <h6>Office Hours</h6>
                    <div class="hours-table">
                        <span>Mon – Fri</span>
                        <span>8:30 AM – 4:30 PM</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php include 'includes/front_footer.php'; ?>
