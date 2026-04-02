<?php
session_start();

include "../config/db.php";

if (!$conn) {
    echo "Database connection error.";
    exit;
}

$user_id  = $_SESSION['user_id'] ?? 0;
$role     = $_SESSION['role'] ?? 'student';
$message  = strtolower(trim($_POST['message'] ?? ''));
$response = "I didn't understand your question. Try asking about attendance, courses, or students.";

// ==================== STUDENT QUERIES ====================
if ($role === 'student') {

    // Keyword: "percentage" — return attendance % per course for this student
    if (strpos($message, 'percentage') !== false) {
        $sql = "
            SELECT c.course_name,
                   ROUND(
                       SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                       / COUNT(a.id) * 100, 2
                   ) AS percentage
            FROM attendance a
            JOIN enrollments e ON a.enrollment_id = e.id
            JOIN courses c     ON e.course_id = c.id
            WHERE e.student_id = '$user_id'
            GROUP BY c.course_name
        ";

        $result   = $conn->query($sql);
        $response = "📊 Your Attendance Percentage:<br>";

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response .= $row['course_name'] . " : " . $row['percentage'] . "%<br>";
            }
        } else {
            $response = "No attendance records found.";
        }
    }

    // Keyword: "miss" — return count of absent classes per course
    elseif (strpos($message, 'miss') !== false) {
        $sql = "
            SELECT c.course_name,
                   COUNT(a.id) AS missed
            FROM attendance a
            JOIN enrollments e ON a.enrollment_id = e.id
            JOIN courses c     ON e.course_id = c.id
            WHERE e.student_id = '$user_id'
              AND a.status = 'Absent'
            GROUP BY c.course_name
        ";

        $result   = $conn->query($sql);
        $response = "❌ Missed Classes:<br>";

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response .= $row['course_name'] . " : " . $row['missed'] . " classes<br>";
            }
        } else {
            $response = "No missed classes.";
        }
    }

    // Keyword: "low" — return courses where attendance is below 75%
    elseif (strpos($message, 'low') !== false) {
        $sql = "
            SELECT c.course_name,
                   ROUND(
                       SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                       / COUNT(a.id) * 100, 2
                   ) AS percentage
            FROM attendance a
            JOIN enrollments e ON a.enrollment_id = e.id
            JOIN courses c     ON e.course_id = c.id
            WHERE e.student_id = '$user_id'
            GROUP BY c.course_name
            HAVING percentage < 75
        ";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $response = "⚠ Low Attendance Subjects:<br>";
            while ($row = $result->fetch_assoc()) {
                $response .= $row['course_name'] . " : " . $row['percentage'] . "%<br>";
            }
        } else {
            $response = "✅ You have no low attendance subjects.";
        }
    }

    // Keyword: "courses" — show enrolled courses
    elseif (strpos($message, 'course') !== false) {
        $sql = "
            SELECT c.course_name, c.course_code
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = '$user_id'
        ";

        $result = $conn->query($sql);
        $response = "📚 Your Enrolled Courses:<br>";

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response .= $row['course_code'] . " - " . $row['course_name'] . "<br>";
            }
        } else {
            $response = "You are not enrolled in any courses.";
        }
    }
}

// ==================== LECTURER QUERIES ====================
elseif ($role === 'lecturer') {

    // Keyword: "students" — show total students in lecturer's courses
    if (strpos($message, 'student') !== false && strpos($message, 'total') !== false) {
        $sql = "
            SELECT COUNT(DISTINCT e.student_id) AS total_students
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE c.lecturer_id = '$user_id'
        ";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = "👥 Total Students in Your Courses: " . $row['total_students'];
        } else {
            $response = "No students found in your courses.";
        }
    }

    // Keyword: "low attendance" — show students with low attendance in lecturer's courses
    elseif (strpos($message, 'low') !== false) {
        $sql = "
            SELECT s.student_name, c.course_name,
                   ROUND(
                       SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                       / COUNT(a.id) * 100, 2
                   ) AS percentage
            FROM attendance a
            JOIN enrollments e ON a.enrollment_id = e.id
            JOIN students s ON e.student_id = s.id
            JOIN courses c ON e.course_id = c.id
            WHERE c.lecturer_id = '$user_id'
            GROUP BY s.id, c.id
            HAVING percentage < 75
            ORDER BY percentage ASC
        ";

        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $response = "⚠ Students with Low Attendance:<br>";
            while ($row = $result->fetch_assoc()) {
                $response .= $row['student_name'] . " - " . $row['course_name'] . " : " . $row['percentage'] . "%<br>";
            }
        } else {
            $response = "✅ No students with low attendance.";
        }
    }

    // Keyword: "courses" — show lecturer's courses
    elseif (strpos($message, 'course') !== false) {
        $sql = "
            SELECT course_name, course_code
            FROM courses
            WHERE lecturer_id = '$user_id'
        ";

        $result = $conn->query($sql);
        $response = "📚 Your Courses:<br>";

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response .= $row['course_code'] . " - " . $row['course_name'] . "<br>";
            }
        } else {
            $response = "You are not assigned to any courses.";
        }
    }

    // Keyword: "attendance rate" — overall attendance rate for lecturer's courses
    elseif (strpos($message, 'rate') !== false || strpos($message, 'percentage') !== false) {
        $sql = "
            SELECT c.course_name,
                   ROUND(
                       SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                       / COUNT(a.id) * 100, 2
                   ) AS percentage
            FROM attendance a
            JOIN enrollments e ON a.enrollment_id = e.id
            JOIN courses c ON e.course_id = c.id
            WHERE c.lecturer_id = '$user_id'
            GROUP BY c.id
        ";

        $result = $conn->query($sql);
        $response = "📊 Attendance Rate by Course:<br>";

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response .= $row['course_name'] . " : " . $row['percentage'] . "%<br>";
            }
        } else {
            $response = "No attendance data available.";
        }
    }
}

// ==================== ADMIN QUERIES ====================
elseif ($role === 'admin') {

    // Keyword: "total students" — count all students
    if (strpos($message, 'total') !== false && strpos($message, 'student') !== false) {
        $sql = "SELECT COUNT(*) AS total FROM students";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = "👥 Total Students: " . $row['total'];
        }
    }

    // Keyword: "total courses" — count all courses
    elseif (strpos($message, 'total') !== false && strpos($message, 'course') !== false) {
        $sql = "SELECT COUNT(*) AS total FROM courses";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = "📚 Total Courses: " . $row['total'];
        }
    }

    // Keyword: "total lecturers" — count all lecturers
    elseif (strpos($message, 'total') !== false && strpos($message, 'lecturer') !== false) {
        $sql = "SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = "👨‍🏫 Total Lecturers: " . $row['total'];
        }
    }

    // Keyword: "low attendance" — show all students with low attendance
    elseif (strpos($message, 'low') !== false) {
        $sql = "
            SELECT s.student_name, c.course_name,
                   ROUND(
                       SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                       / COUNT(a.id) * 100, 2
                   ) AS percentage
            FROM attendance a
            JOIN enrollments e ON a.enrollment_id = e.id
            JOIN students s ON e.student_id = s.id
            JOIN courses c ON e.course_id = c.id
            GROUP BY s.id, c.id
            HAVING percentage < 75
            ORDER BY percentage ASC
            LIMIT 10
        ";

        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $response = "⚠ Students with Low Attendance (Top 10):<br>";
            while ($row = $result->fetch_assoc()) {
                $response .= $row['student_name'] . " - " . $row['course_name'] . " : " . $row['percentage'] . "%<br>";
            }
        } else {
            $response = "✅ No students with low attendance.";
        }
    }

    // Keyword: "overall attendance" — system-wide attendance rate
    elseif (strpos($message, 'overall') !== false || strpos($message, 'system') !== false) {
        $sql = "
            SELECT ROUND(
                       SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END)
                       / COUNT(id) * 100, 2
                   ) AS overall_percentage
            FROM attendance
        ";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = "📊 Overall System Attendance: " . $row['overall_percentage'] . "%";
        } else {
            $response = "No attendance data available.";
        }
    }

    // Keyword: "active users" — count active users
    elseif (strpos($message, 'active') !== false && strpos($message, 'user') !== false) {
        $sql = "SELECT COUNT(*) AS total FROM users WHERE status = 'active'";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = "✅ Active Users: " . $row['total'];
        }
    }
}

echo $response;
