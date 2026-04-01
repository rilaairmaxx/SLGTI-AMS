<?php
// Public-facing AI chat — no login required, general SLGTI Q&A only
session_start();

$message  = strtolower(trim($_POST['message'] ?? ''));
$response = "I'm not sure about that. Try asking about SLGTI courses, admission, location, or the attendance system.";

// ── About SLGTI ──
if (strpos($message, 'about') !== false || strpos($message, 'what is slgti') !== false || strpos($message, 'who are you') !== false) {
    $response = "🏫 SLGTI (Sri Lanka German Training Institute) is a vocational training institute located in Kilinochchi, Sri Lanka. It offers NVQ-certified technical and vocational courses in partnership with German training standards.";
}

// ── Location ──
elseif (strpos($message, 'location') !== false || strpos($message, 'where') !== false || strpos($message, 'address') !== false) {
    $response = "📍 SLGTI is located in Kilinochchi, Northern Province, Sri Lanka.";
}

// ── Courses / Departments ──
elseif (strpos($message, 'course') !== false || strpos($message, 'department') !== false || strpos($message, 'program') !== false) {
    $response = "📚 SLGTI offers vocational courses including:<br>
    • Information Technology<br>
    • Electrical Engineering<br>
    • Mechanical Engineering<br>
    • Welding & Fabrication<br>
    • Carpentry & Joinery<br>
    • Plumbing & Pipe Fitting<br>
    All programs are NVQ certified.";
}

// ── Admission ──
elseif (strpos($message, 'admission') !== false || strpos($message, 'apply') !== false || strpos($message, 'enroll') !== false || strpos($message, 'join') !== false) {
    $response = "📝 To apply at SLGTI, visit the institute directly in Kilinochchi or contact the administration office. Admission requirements vary by course. Basic education qualifications are required for most programs.";
}

// ── Fees ──
elseif (strpos($message, 'fee') !== false || strpos($message, 'cost') !== false || strpos($message, 'price') !== false || strpos($message, 'tuition') !== false) {
    $response = "💰 Course fees at SLGTI are subsidized by the government. Please contact the administration office directly for the latest fee structure for each program.";
}

// ── Duration ──
elseif (strpos($message, 'duration') !== false || strpos($message, 'how long') !== false || strpos($message, 'years') !== false || strpos($message, 'months') !== false) {
    $response = "⏱ Course durations at SLGTI typically range from 6 months to 2 years depending on the NVQ level and program chosen.";
}

// ── NVQ ──
elseif (strpos($message, 'nvq') !== false || strpos($message, 'certificate') !== false || strpos($message, 'qualification') !== false) {
    $response = "🎓 SLGTI awards NVQ (National Vocational Qualification) certificates recognized by the Tertiary and Vocational Education Commission (TVEC) of Sri Lanka. Levels range from NVQ 2 to NVQ 5.";
}

// ── Contact ──
elseif (strpos($message, 'contact') !== false || strpos($message, 'phone') !== false || strpos($message, 'email') !== false || strpos($message, 'reach') !== false) {
    $response = "📞 Please visit SLGTI in Kilinochchi or reach out through the contact section on this page for the latest contact details.";
}

// ── Attendance System ──
elseif (strpos($message, 'attendance') !== false || strpos($message, 'system') !== false || strpos($message, 'portal') !== false || strpos($message, 'login') !== false) {
    $response = "💻 The SLGTI Attendance Management System allows students, lecturers, and admins to track attendance digitally. <a href='login.php' style='color:#4a90e2;'>Click here to login</a> if you have an account.";
}

// ── Student login help ──
elseif (strpos($message, 'student') !== false && (strpos($message, 'login') !== false || strpos($message, 'password') !== false)) {
    $response = "🔑 Students log in using their registered <strong>Email</strong> as the username and their <strong>Student Number</strong> as the default password. You can reset your password from the login page.";
}

// ── Greeting ──
elseif (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|greetings)/', $message)) {
    $response = "👋 Hello! Welcome to SLGTI. I can help you with information about our courses, admission, location, and the attendance system. What would you like to know?";
}

// ── Thanks ──
elseif (strpos($message, 'thank') !== false || strpos($message, 'thanks') !== false) {
    $response = "😊 You're welcome! Feel free to ask anything else about SLGTI.";
}

echo $response;
