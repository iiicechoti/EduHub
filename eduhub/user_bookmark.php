<?php
// บันทึก / ยกเลิกบันทึกโอกาส (ผู้รับผิดชอบ: คนที่ 2)
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}
$student_id = (int)$_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $opp_id = (int)$_POST['opportunity_id'];

    $opp = fetch_one($conn, "SELECT opportunity_id FROM opportunities WHERE opportunity_id = $opp_id");
    if ($opp) {
        $bm = fetch_one($conn, "SELECT bookmark_id FROM bookmarks WHERE student_id = $student_id AND opportunity_id = $opp_id");
        if ($bm) {
            mysqli_query($conn, "DELETE FROM bookmarks WHERE bookmark_id = " . (int)$bm['bookmark_id']);
            echo "<script>alert('ยกเลิกการบันทึกแล้ว'); window.location.href='user_detail.php?id=$opp_id';</script>";
        } else {
            mysqli_query($conn, "INSERT INTO bookmarks (student_id, opportunity_id) VALUES ($student_id, $opp_id)");
            echo "<script>alert('บันทึกโอกาสนี้แล้ว'); window.location.href='user_detail.php?id=$opp_id';</script>";
        }
        exit();
    }
}
header("Location: user_dashboard.php");
exit();
?>
