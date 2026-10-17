
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /eduhub/login.php");
    exit();
}

if (($_SESSION['role'] ?? '') === 'admin') {
    header("Location: /eduhub/admin_dashboard.php");
} else {
    header("Location: /eduhub/user_dashboard.php");
}
exit();
?>
