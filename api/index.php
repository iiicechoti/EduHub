<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] === 'admin') { header("Location: admin_dashboard.php"); }
else { header("Location: user_dashboard.php"); }
exit();
?>
