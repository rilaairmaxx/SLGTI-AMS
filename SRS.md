# Software Requirements Specification (SRS)
## SLGTI Attendance Management System

**Document Version:** 2.0  
**Date:** March 30, 2026  
**Organization:** Sri Lanka German Technical Institute (SLGTI), Kilinochchi  
**System:** Web-Based Attendance Management System  

---

## 1. Introduction

### 1.1 Purpose
This document defines the functional and non-functional requirements for the SLGTI Attendance Management System — a web-based application designed to digitize and streamline student attendance tracking across all departments at Sri Lanka German Technical Institute, Kilinochchi.

### 1.2 Scope
The system covers:
- Student, lecturer, and administrator account management
- Course and enrollment management
- Attendance marking and tracking
- Timetable management
- Attendance reporting, analytics, and export (Excel/PDF)
- Bulk student import via CSV
- Profile photo management
- Leave/excuse request workflow
- In-system notification center
- Audit logging
- AI-powered chatbot assistant
- Academic calendar with institute events
- Forgot-password and change-password self-service flows
- IP-based rate limiting
- Email notifications via SMTP

### 1.3 Definitions and Abbreviations

| Term | Definition |
|------|-----------|
| AMS | Attendance Management System |
| NVQ | National Vocational Qualification |
| SLGTI | Sri Lanka German Technical Institute |
| Admin | System Administrator |
| Lecturer | Teaching staff member |
| Student | Enrolled learner |
| Enrollment | Association between a student and a course |
| Poya Day | Full Moon holiday observed in Sri Lanka |
| SMTP | Simple Mail Transfer Protocol |
| AJAX | Asynchronous JavaScript and XML |
| CSV | Comma-Separated Values |

### 1.4 Overview
The system is built with PHP (server-side), MySQL (database), Bootstrap 5 (UI framework), and Font Awesome (icons). It is hosted on a local/web server and accessed via a browser. Email delivery uses PHPMailer via Brevo SMTP relay.

---

## 2. Overall Description

### 2.1 Product Perspective
The AMS replaces manual paper-based attendance registers. It provides a centralized portal accessible to three user roles: Admin, Lecturer, and Student. Each role has a tailored dashboard and restricted access to relevant features.

### 2.2 User Classes

| Role | Description | Access Level |
|------|-------------|--------------|
| Admin | Manages all system data, users, courses, enrollments, timetable, audit logs, and notifications | Full |
| Lecturer | Marks attendance for assigned courses; reviews leave requests; views reports and timetable | Moderate |
| Student | Views own attendance, submits leave requests, views timetable and notifications | Read/Submit |

### 2.3 Operating Environment
- Server: Apache/Nginx with PHP 7.4+
- Database: MySQL 5.7+ / MariaDB
- Client: Any modern web browser (Chrome, Firefox, Edge, Safari)
- Network: LAN or internet connection

### 2.4 Assumptions and Dependencies
- The server has PHP `mysqli` extension enabled.
- The database `attendance_Management_system` is pre-created and seeded.
- Bootstrap 5 and Font Awesome 6 CDN resources are accessible.
- An AI backend endpoint is available for the chatbot feature (`includes/ai_chat.php`).
- PHPMailer is installed via Composer; Brevo SMTP credentials are configured in `helpers/mailer.php`.
- Several database tables (`audit_log`, `leave_requests`, `notifications`, `rate_limits`, `timetable`) are auto-created on first use.

---

## 3. Functional Requirements

### 3.1 Authentication Module (`login.php`, `logout.php`, `includes/auth.php`)

**FR-AUTH-01:** The system shall allow Admin and Lecturer users to log in using a username and password.

**FR-AUTH-02:** The system shall allow Student users to log in using their registered email address and student number (or a custom password set via forgot-password).

**FR-AUTH-03:** The system shall enforce IP-based rate limiting: a maximum of 5 login attempts per 300-second window per IP address.

**FR-AUTH-04:** The system shall redirect authenticated users to the dashboard; inactive accounts shall be denied access with an appropriate message.

**FR-AUTH-05:** The system shall log each login attempt (success/failure) with IP address, user agent, and timestamp into a `login_logs` table.

**FR-AUTH-06:** The system shall provide a two-step forgot-password flow:
- Step 1: Verify identity by role (Student via email, Lecturer via username).
- Step 2: Set a new password (minimum 6 characters, confirmed twice).

**FR-AUTH-07:** The system shall destroy the session and redirect to the login page on logout.

**FR-AUTH-08:** The system shall enforce a 30-minute session inactivity timeout, automatically logging out idle users.

**FR-AUTH-09:** The system shall regenerate the session ID every 30 minutes to mitigate session fixation attacks.

---

### 3.2 Dashboard Module (`dashboard.php`)

**FR-DASH-01 (Admin):** The admin dashboard shall display total counts of lecturers, courses, students, and enrollments, along with quick-action links to all management pages.

**FR-DASH-02 (Lecturer):** The lecturer dashboard shall display the number of their assigned courses, total unique students across those courses, and total attendance records they have marked.

**FR-DASH-03 (Student):** The student dashboard shall display enrolled courses count, days present, attendance rate (%), total sessions, days absent, and the 5 most recent attendance records in a table.

**FR-DASH-04:** The dashboard shall display a role-specific welcome banner and the current date.

---

### 3.3 Student Management Module (`students.php`)

**FR-STU-01:** Admin shall be able to register a new student with: student number, full name, email, phone, address, date of birth, gender, and status.

**FR-STU-02:** Admin shall be able to edit any existing student record.

**FR-STU-03:** Admin shall be able to delete a student record.

**FR-STU-04:** The system shall prevent duplicate student numbers.

**FR-STU-05:** The system shall validate email format, phone format (7–15 digits), and that date of birth is not a future date.

**FR-STU-06:** The student list shall support live client-side search (by name, number, or email) and filtering by status (active, inactive, graduated, suspended).

**FR-STU-07:** Student status options shall be: active, inactive, graduated, suspended.

---

### 3.4 Bulk Student Import Module (`import_students.php`)

**FR-IMP-01:** Admin shall be able to upload a CSV file to bulk-import students.

**FR-IMP-02:** The CSV format shall include columns: student_number, student_name, email, phone, gender, status.

**FR-IMP-03:** The system shall skip rows with duplicate student numbers and report them as skipped.

**FR-IMP-04:** The system shall validate each row for required fields, email format, phone format, and valid status values; invalid rows shall be reported with a row number and reason.

**FR-IMP-05:** Successful imports shall be logged to the audit log.

---

### 3.5 User (Staff) Management Module (`create_user.php`, `users.php`)

**FR-USR-01:** Admin shall be able to create Admin or Lecturer accounts with: full name, username, email, role, status, and password.

**FR-USR-02:** Admin shall be able to edit existing staff accounts; password update shall be optional during edit.

**FR-USR-03:** Admin shall be able to delete a staff account, except their own account and accounts with assigned courses.

**FR-USR-04:** The system shall prevent duplicate usernames.

**FR-USR-05:** Passwords shall be stored as bcrypt hashes.

**FR-USR-06:** The system shall record which admin created each user (`created_by` field).

**FR-USR-07:** The staff list shall support live search by name, username, or email.

---

### 3.6 Course Management Module (`courses.php`, `add_course.php`)

**FR-CRS-01:** Admin shall be able to add a course with: course name (from predefined department list), course code, description, assigned lecturer, and status.

**FR-CRS-02:** Course codes shall follow the format `[PREFIX][4-digit number]` (e.g., `AUTO2601`). The prefix is auto-suggested based on the selected department.

**FR-CRS-03:** Admin shall be able to edit any course.

**FR-CRS-04:** Admin shall be able to delete a course only if it has no active enrollments.

**FR-CRS-05:** The course list shall display course code, name, description, assigned lecturer, enrolled student count, and status.

**FR-CRS-06:** The system shall display a locked icon instead of a delete button for courses with active enrollments.

**FR-CRS-07:** Predefined departments: Automobile, ICT, Electrical & Electronic, Food Technology, Construction Technology.

---

### 3.7 Enrollment Module (`enroll.php`)

**FR-ENR-01:** Admin shall be able to enroll a student into a course by selecting from dropdown lists.

**FR-ENR-02:** The system shall prevent duplicate enrollments (same student + same course).

**FR-ENR-03:** Admin shall be able to remove an enrollment only if no attendance records exist for it.

**FR-ENR-04:** The enrollment list shall support live search by student name, number, or course name/code.

---

### 3.8 Attendance Marking Module (`attendance.php`)

**FR-ATT-01:** Lecturers shall be able to mark attendance for their own courses only.

**FR-ATT-02:** The lecturer shall select a course and a date (not a future date) to load the student list.

**FR-ATT-03:** The system shall only show students whose account was created on or before the selected date.

**FR-ATT-04:** For each student, the lecturer shall select one of four statuses: Present, Absent, Late, Excused.

**FR-ATT-05:** The lecturer may optionally add a remarks note per student.

**FR-ATT-06:** The system shall upsert attendance records (insert if new, update if existing for the same enrollment + date).

**FR-ATT-07:** The system shall record which lecturer marked each attendance row (`marked_by` field).

**FR-ATT-08:** Bulk-mark buttons shall allow marking all visible students as Present, Absent, or Late at once.

**FR-ATT-09:** A live search bar shall filter the student list by name or student number.

**FR-ATT-10:** A progress bar and counter shall show how many students have been marked out of the total.

**FR-ATT-11:** A session summary panel shall display counts of Present, Absent, Late, and Excused for the loaded session.

**FR-ATT-12:** The date picker minimum shall be set to the earliest student enrollment date for the selected course.

---

### 3.9 Timetable Module (`timetable.php`)

**FR-TT-01:** Admin shall be able to add, edit, and delete timetable slots for any course.

**FR-TT-02:** Each timetable slot shall include: course, lecturer, day of week (Monday–Saturday), start time, end time, and room.

**FR-TT-03:** The system shall reject slots where end time is not after start time.

**FR-TT-04:** Lecturers shall be able to view the timetable for their assigned courses.

**FR-TT-05:** Students shall be able to view the timetable for their enrolled courses.

**FR-TT-06:** The timetable view shall support filtering by department and day of week.

**FR-TT-07:** Timetable slots shall be displayed in a color-coded time grid (08:00–17:00).

**FR-TT-08:** Add/edit operations shall be performed via AJAX modal without full page reload.

---

### 3.10 My Courses Module (`my_courses.php`)

**FR-MC-01:** Lecturers shall have a dedicated page listing all courses assigned to them.

**FR-MC-02:** The page shall display summary statistics: total assigned courses, total enrolled students, and active course count.

**FR-MC-03:** Each course card shall show department, course code, course name, description, student count, and status.

**FR-MC-04:** The course list shall support live search by course name or code.

---

### 3.11 Reports Module (`reports.php`)

**FR-RPT-01:** All roles shall be able to view attendance reports filtered by course and date range.

**FR-RPT-02:** Lecturers shall only see their own courses; students shall only see their enrolled courses; admins shall see all courses.

**FR-RPT-03:** The report shall display aggregate counts: Present, Absent, Late, Excused, and overall attendance rate (%).

**FR-RPT-04:** The report shall display a detailed table of individual attendance records (student name, status, date).

**FR-RPT-05:** The report shall support live search within the detail table by student name.

**FR-RPT-06:** The system shall provide a print-friendly view of the report.

**FR-RPT-07:** A visual SVG arc shall display the attendance rate percentage.

---

### 3.12 Export Module (`exports/export_excel.php`, `exports/export_pdf.php`, `helpers/export.php`)

**FR-EXP-01:** All roles (with appropriate course access) shall be able to export attendance reports to Excel (CSV) format.

**FR-EXP-02:** The Excel export shall include a metadata block (course info, date range, generated-by), a summary sheet with per-student attendance counts and percentages, and a daily detail sheet.

**FR-EXP-03:** The Excel export shall include a totals row and use a UTF-8 BOM for Excel compatibility.

**FR-EXP-04:** All roles (with appropriate course access) shall be able to export attendance reports to PDF format.

**FR-EXP-05:** The PDF report shall include the SLGTI branded header, course information, summary statistics, and a color-coded attendance table.

**FR-EXP-06:** Role-based access control shall be enforced on all export endpoints.

---

### 3.13 Leave Request Module (`leave_requests.php`)

**FR-LVE-01:** Students shall be able to submit a leave request specifying a date, an optional course, and a reason (minimum 10 characters).

**FR-LVE-02:** Leave request status shall be: pending, approved, or rejected.

**FR-LVE-03:** Lecturers shall be able to approve or reject leave requests for students in their courses.

**FR-LVE-04:** Admins shall be able to approve or reject any leave request.

**FR-LVE-05:** Students shall only see their own leave requests; lecturers shall see requests for their courses; admins shall see all.

**FR-LVE-06:** All review actions (approve/reject) shall be logged to the audit log.

---

### 3.14 Notifications Module (`notifications.php`)

**FR-NOT-01:** The system shall maintain a per-user notification center accessible from the navigation bar.

**FR-NOT-02:** Notification types shall be: low_attendance, new_enrollment, general.

**FR-NOT-03:** The system shall auto-generate low-attendance alerts for students whose attendance falls below 75%.

**FR-NOT-04:** Users shall be able to mark individual notifications or all notifications as read.

**FR-NOT-05:** An unread count badge shall be displayed on the notification bell icon in the navigation bar, updated via AJAX.

**FR-NOT-06:** Notifications shall include a title, message, type, timestamp, and an optional link to a relevant page.

---

### 3.15 Profile Photo Module (`profile_photo.php`, `uploads/`)

**FR-PHO-01:** All authenticated users shall be able to upload a profile photo.

**FR-PHO-02:** Accepted file types shall be: JPG, JPEG, PNG, WEBP, GIF. Maximum file size shall be 2 MB.

**FR-PHO-03:** The system shall verify MIME type server-side in addition to file extension.

**FR-PHO-04:** Uploading a new photo shall automatically delete the previous photo from disk.

**FR-PHO-05:** Users without a photo shall display an avatar with their initial as a fallback.

**FR-PHO-06:** Admin shall be able to manage profile photos for all staff and students from a tabbed management panel with search.

**FR-PHO-07:** Photo upload shall use AJAX with a progress indicator and preview before confirmation.

---

### 3.16 Change Password Module (`change_password.php`)

**FR-PWD-01:** All authenticated users shall be able to change their password from within the system.

**FR-PWD-02:** The user shall provide their current password, a new password, and a confirmation of the new password.

**FR-PWD-03:** The new password shall be a minimum of 8 characters.

**FR-PWD-04:** The system shall verify the current password before applying the change.

**FR-PWD-05:** Password changes shall be logged to the audit log.

**FR-PWD-06:** The password input fields shall include a visibility toggle.

---

### 3.17 Audit Log Module (`audit_log.php`)

**FR-AUD-01:** The system shall maintain an audit log recording all significant actions: login, logout, password_change, leave_request, leave_approved, leave_rejected, import_students, delete, update, create.

**FR-AUD-02:** Each audit record shall store: user_id, role, action, detail, IP address, and timestamp.

**FR-AUD-03:** The audit log shall be accessible to Admin only.

**FR-AUD-04:** The audit log shall support filtering by action type, user, and date.

**FR-AUD-05:** The audit log shall be paginated at 25 records per page.

**FR-AUD-06:** Action entries shall be displayed with color-coded badges.

---

### 3.18 AI Chatbot Module (`ai_chatbox.php`, `includes/ai_chat.php`)

**FR-AI-01:** A floating chatbot button shall be available on all authenticated pages.

**FR-AI-02:** The chatbot shall display role-specific quick-suggestion chips on open.

**FR-AI-03:** Student suggestions: attendance percentage, missed classes, low-attendance subjects, enrolled courses.

**FR-AI-04:** Lecturer suggestions: course list, total students, low-attendance students, attendance rate.

**FR-AI-05:** Admin suggestions: total students, total courses, total lecturers, overall attendance, low-attendance students, active users.

**FR-AI-06:** The chatbot shall show a typing indicator while awaiting a response.

---

### 3.19 Calendar Module (`calendar.php`)

**FR-CAL-01:** The calendar shall display the SLGTI 2026 official academic calendar.

**FR-CAL-02:** The calendar shall visually distinguish three event types: Full Moon Holidays (red circle), Public Holidays (yellow box), and Institute Programs (pentagon/square border).

**FR-CAL-03:** Clicking a marked date shall open a modal with full event details.

**FR-CAL-04:** The calendar shall support month navigation (previous/next) and a quick-jump row for all 12 months.

**FR-CAL-05:** A sidebar shall list all events for the currently viewed month.

**FR-CAL-06:** A live clock shall display the current date and time in the navigation banner.

---

### 3.20 Email Notification Module (`helpers/mailer.php`)

**FR-MAIL-01:** The system shall send transactional emails via PHPMailer using Brevo SMTP relay.

**FR-MAIL-02:** All outgoing emails shall use an SLGTI-branded HTML template.

**FR-MAIL-03:** The system shall support the following email types: password reset, attendance warning, monthly summary, and welcome email.

**FR-MAIL-04:** The mailer shall fall back to PHP `mail()` if SMTP is unavailable.

---

### 3.21 Public Landing Page (`index.php`)

**FR-PUB-01:** The public landing page shall display a hero section, department cards (5 departments with NVQ levels), an about section, and a contact section.

**FR-PUB-02:** The page shall be accessible without authentication.

---

## 4. Non-Functional Requirements

### 4.1 Security

**NFR-SEC-01:** All passwords shall be hashed using PHP `password_hash()` with `PASSWORD_DEFAULT` (bcrypt).

**NFR-SEC-02:** All user-supplied input rendered in HTML shall be escaped with `htmlspecialchars()`.

**NFR-SEC-03:** All database queries shall use prepared statements with bound parameters to prevent SQL injection.

**NFR-SEC-04:** Session-based authentication shall be enforced on every protected page; unauthenticated requests shall redirect to `login.php`.

**NFR-SEC-05:** Role-based access control shall be enforced server-side; unauthorized role access shall redirect to the appropriate page.

**NFR-SEC-06:** Future attendance dates shall be rejected server-side.

**NFR-SEC-07:** Login attempts shall be IP-rate-limited to 5 per 300-second window via the `rate_limits` table.

**NFR-SEC-08:** File uploads shall be validated by MIME type and file extension server-side; maximum upload size is 2 MB.

**NFR-SEC-09:** Session IDs shall be regenerated every 30 minutes; sessions shall expire after 30 minutes of inactivity.

**NFR-SEC-10:** Sensitive actions (password changes, imports, deletions, leave reviews) shall be recorded in the audit log.

### 4.2 Performance

**NFR-PERF-01:** Page load time shall not exceed 3 seconds on a standard LAN connection.

**NFR-PERF-02:** Database queries shall use indexed foreign keys (`enrollment_id`, `course_id`, `student_id`, `lecturer_id`) to ensure efficient joins.

**NFR-PERF-03:** AJAX endpoints (timetable, notifications, photo upload) shall respond within 1 second under normal load.

### 4.3 Usability

**NFR-USE-01:** The UI shall be responsive and usable on desktop, tablet, and mobile screen sizes using Bootstrap 5 grid.

**NFR-USE-02:** All forms shall provide inline validation feedback (client-side and server-side).

**NFR-USE-03:** Alert messages shall auto-dismiss after 4.5 seconds.

**NFR-USE-04:** Loading spinners shall be shown on form submission buttons to prevent double-submission.

**NFR-USE-05:** Password fields shall include a visibility toggle.

### 4.4 Reliability

**NFR-REL-01:** The system shall handle database connection failures gracefully and log errors without exposing sensitive details to the user.

**NFR-REL-02:** Attendance records shall use an upsert strategy to prevent duplicate entries for the same enrollment + date combination.

**NFR-REL-03:** Auto-created tables (`audit_log`, `leave_requests`, `notifications`, `rate_limits`, `timetable`) shall use `CREATE TABLE IF NOT EXISTS` to be idempotent.

### 4.5 Maintainability

**NFR-MNT-01:** The codebase shall follow a consistent file structure: `config/`, `includes/`, `includes/css/`, `includes/js/`, `helpers/`, `exports/`, `uploads/`.

**NFR-MNT-02:** Shared layout components (header, footer, navigation) shall be included via PHP `include`/`require_once`.

**NFR-MNT-03:** Reusable logic shall be encapsulated in helper classes: `Validator`, `Mailer`, `Exporter`.

---

## 5. Database Schema (Summary)

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `users` | id, username, password, full_name, email, role, status, photo, created_by, last_login | Admin and Lecturer accounts |
| `students` | id, student_number, student_name, email, phone, address, date_of_birth, gender, status, password, photo | Student accounts |
| `courses` | id, course_code, course_name, description, department, lecturer_id, status | Course catalog |
| `enrollments` | id, student_id, course_id, status, enrollment_date | Student–course associations |
| `attendance` | id, enrollment_id, attendance_date, status (Present/Absent/Late/Excused), remarks, marked_by, marked_at | Attendance records |
| `timetable` | id, course_id, lecturer_id, day, start_time, end_time, room, created_at | Class schedule slots |
| `leave_requests` | id, student_id, course_id, leave_date, reason, status, reviewed_by, reviewed_at, created_at | Student leave/excuse requests |
| `notifications` | id, user_id, type, title, message, link, is_read, created_at | In-system user notifications |
| `audit_log` | id, user_id, role, action, detail, ip_address, created_at | System activity audit trail |
| `rate_limits` | id, ip_address, action, attempts, first_hit, last_hit | IP-based rate limiting |
| `login_logs` | id, user_id, ip_address, user_agent, status, created_at | Login attempt audit trail |
| `password_resets` | id, email, token, expires_at, created_at | Password reset tokens |

---

## 6. System Constraints

- The system is designed for single-institution use (SLGTI).
- The calendar data is hardcoded for the year 2026 based on the official SLGTI academic calendar.
- Student login uses email + student number (or custom password); no separate username field for students.
- Course names are restricted to a predefined list of five SLGTI departments.
- Several tables are auto-created at runtime on first access; no separate migration script is required for these tables.
- Email delivery depends on Brevo SMTP relay availability; a PHP `mail()` fallback is provided.

---

## 7. External Interface Requirements

### 7.1 User Interface
- Bootstrap 5.3 for layout and components
- Font Awesome 6.4 for iconography
- Custom CSS per module using scoped class prefixes (e.g., `.ma-`, `.rp-`, `.db-`, `.mc-`, `.pp-`)

### 7.2 Hardware Interface
- Standard web server hardware; no special hardware requirements
- No biometric or RFID integration in the current version

### 7.3 Software Interface
- PHP 7.4+ with `mysqli` extension
- MySQL 5.7+ / MariaDB 10.3+
- PHPMailer (via Composer) with Brevo SMTP relay
- AI chat backend (internal PHP endpoint)

### 7.4 Communication Interface
- SMTP: Brevo relay (`smtp-relay.brevo.com`, port 587, TLS) for transactional email
- AJAX: JSON responses for timetable CRUD, notification badge, photo upload, and chatbot interactions

---

## 8. Helper Components

### 8.1 Validator (`helpers/validator.php`)
A fluent validation class providing 20+ chainable rules: `required`, `minLength`, `maxLength`, `email`, `numeric`, `integer`, `in`, `regex`, `matches`, `date`, `notFutureDate`, `notPastDate`, `phone`, `nic`, `studentNumber`, `strongPassword`, `studentStatus`, `userRole`, `attendanceStatus`, `unique`. Also provides sanitization methods: `sanitiseString`, `sanitiseInt`, `sanitiseEmail`, `sanitiseDate`, `stripTags`.

### 8.2 Mailer (`helpers/mailer.php`)
A static mailer class wrapping PHPMailer. Provides branded HTML email templates and methods: `send`, `sendPasswordReset`, `sendAttendanceWarning`, `sendMonthlySummary`, `sendWelcome`.

### 8.3 Exporter (`helpers/export.php`)
A static exporter class providing: `csv`, `excel`, `attendanceCsv`, `attendancePdf`. Handles HTTP headers, UTF-8 BOM, metadata blocks, and styled HTML-to-PDF output.

---

*End of SRS Document — Version 2.0*
