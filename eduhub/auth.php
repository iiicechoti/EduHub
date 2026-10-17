<?php
session_start();
require_once("config.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // นักเรียนล็อกอินด้วยรหัสนักเรียน / แอดมินล็อกอินด้วย username
    $sql = "SELECT u.*, s.student_id, s.first_name, s.last_name
            FROM users u
            LEFT JOIN students s ON s.user_id = u.user_id
            WHERE s.student_code = '$username' OR u.username = '$username'
            LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        // รหัสผ่านเก็บแบบ hash ต้องใช้ password_verify (ห้ามเทียบข้อความตรง ๆ)
        if (password_verify($password, $row['password_hash']) && $row['status'] === 'active') {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role'] = $row['role'];
            if ($row['role'] === 'admin') {
                $_SESSION['name'] = $row['username'];
                mysqli_query($conn, "UPDATE users SET last_login_at = NOW() WHERE user_id = " . (int)$row['user_id']);
                header("Location: admin_dashboard.php");
            } else {
                $_SESSION['student_id'] = $row['student_id'];
                $_SESSION['name'] = $row['first_name'] . " " . $row['last_name'];
                mysqli_query($conn, "UPDATE users SET last_login_at = NOW() WHERE user_id = " . (int)$row['user_id']);
                header("Location: user_dashboard.php");
            }
            exit();
        }
    }
    // ไม่บอกว่าผิดที่ชื่อหรือรหัสผ่าน
    $_SESSION['error'] = "รหัสนักเรียนหรือรหัสผ่านไม่ถูกต้อง กรุณาลองใหม่";
    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>
