<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "attendance_Management_system";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    error_log($e->getMessage());
    exit('Database connection failed. Please try again later.');
}
?>