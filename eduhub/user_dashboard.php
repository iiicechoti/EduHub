<?php
// Dashboard นักเรียน (ผู้รับผิดชอบ: คนที่ 3)
session_start();
require_once("config.php");
require_once("eligibility.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}
$student_id = (int)$_SESSION['student_id'];
$user_id = (int)$_SESSION['user_id'];

// ข้อมูลพื้นฐาน
$stu = fetch_one($conn, "SELECT s.*, sc.school_name, sp.plan_name FROM students s
                          LEFT JOIN schools sc ON sc.school_id = s.school_id
                          LEFT JOIN study_plans sp ON sp.study_plan_id = s.study_plan_id
                          WHERE s.student_id = $student_id");
$gpax = null;
$rec = fetch_one($conn, "SELECT gpax FROM education_records WHERE student_id = $student_id AND gpax IS NOT NULL ORDER BY academic_year DESC, semester DESC LIMIT 1");
if ($rec) { $gpax = $rec['gpax']; }

// โอกาสที่เปิดรับ -> ตรวจคุณสมบัติทีละโครงการ
$opps = fetch_all($conn, "SELECT o.*, t.type_name FROM opportunities o
                          JOIN opportunity_types t ON t.opportunity_type_id = o.opportunity_type_id
                          WHERE o.status NOT IN ('draft','cancelled','expired') ORDER BY o.close_date");
$eligible = array();
$closing = array();
foreach ($opps as $o) {
    if (effective_status($o) != 'open') { continue; }
    $res = check_eligibility($conn, $student_id, $o['opportunity_id']);
    if ($res['status'] == 'pass') { $eligible[] = $o; }
    $d = days_left($o['close_date']);
    if ($d !== null && $d <= 14) { $closing[] = $o; }
}

$bookmarks = fetch_all($conn, "SELECT o.opportunity_id, o.title, o.open_date, o.close_date, o.status FROM bookmarks b
                               JOIN opportunities o ON o.opportunity_id = b.opportunity_id
                               WHERE b.student_id = $student_id ORDER BY b.created_at DESC");
$events = fetch_all($conn, "SELECT e.event_title, e.start_datetime, o.title FROM opportunity_events e
                            JOIN opportunities o ON o.opportunity_id = e.opportunity_id
                            JOIN bookmarks b ON b.opportunity_id = o.opportunity_id AND b.student_id = $student_id
                            WHERE e.start_datetime >= NOW() ORDER BY e.start_datetime LIMIT 6");
$notifs = fetch_all($conn, "SELECT * FROM notifications WHERE user_id = $user_id AND is_read = 0 AND notify_at <= NOW() ORDER BY notify_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก | EduHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include("navbar_user.php"); ?>

<div class="container">
    <h2>สวัสดี, <?= htmlspecialchars($_SESSION['name']) ?></h2>

    <div class="grid3">
        <div class="card">
            <h3>ข้อมูลพื้นฐาน</h3>
            <div>รหัสนักเรียน: <b><?= htmlspecialchars($stu['student_code']) ?></b></div>
            <div>โรงเรียน: <?= htmlspecialchars($stu['school_name'] ?? '-') ?></div>
            <div>ระดับชั้น: <?= $stu['current_grade_level'] ? grade_label($stu['current_grade_level']) : '-' ?></div>
            <div>แผนการเรียน: <?= htmlspecialchars($stu['plan_name'] ?? '-') ?></div>
            <div>GPAX: <?= $gpax !== null ? number_format($gpax, 2) : '-' ?></div>
        </div>
        <div class="card stat">
            <div class="num" style="color:#28a745;"><?= count($eligible) ?></div>
            <div>โอกาสที่เปิดรับและตรงตามเกณฑ์ที่ตรวจสอบได้</div>
        </div>
        <div class="card stat">
            <div class="num" style="color:#ff4757;"><?= count($closing) ?></div>
            <div>โอกาสที่ใกล้ปิดรับสมัคร (ภายใน 14 วัน)</div>
        </div>
    </div>

    <div class="grid2">
        <div>
            <div class="card">
                <h3>โอกาสที่ตรงตามเกณฑ์</h3>
                <?php if (count($eligible) == 0) { echo "<p class='muted'>ยังไม่พบโอกาสที่ตรงตามเกณฑ์ กรุณาตรวจสอบข้อมูลโปรไฟล์ให้ครบถ้วน</p>"; } ?>
                <?php foreach ($eligible as $o) { ?>
                    <div style="padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.08);">
                        <a href="user_detail.php?id=<?= $o['opportunity_id'] ?>"><?= htmlspecialchars($o['title']) ?></a>
                        <span class="badge bg-info"><?= htmlspecialchars($o['type_name']) ?></span>
                        <div class="muted">ปิดรับสมัคร <?= showdate($o['close_date']) ?></div>
                    </div>
                <?php } ?>
            </div>
            <div class="card">
                <h3>ใกล้ปิดรับสมัคร</h3>
                <?php if (count($closing) == 0) { echo "<p class='muted'>ไม่มีโครงการที่ใกล้ปิดรับสมัคร</p>"; } ?>
                <?php foreach ($closing as $o) { $d = days_left($o['close_date']); ?>
                    <div style="padding:8px 0;">
                        <a href="user_detail.php?id=<?= $o['opportunity_id'] ?>"><?= htmlspecialchars($o['title']) ?></a>
                        <span class="badge bg-danger">เหลือ <?= max($d, 0) ?> วัน</span>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div>
            <div class="card">
                <h3>รายการที่บันทึกไว้ (<?= count($bookmarks) ?>)</h3>
                <?php if (count($bookmarks) == 0) { echo "<p class='muted'>ยังไม่มีรายการที่บันทึกไว้</p>"; } ?>
                <?php foreach ($bookmarks as $b) { ?>
                    <div style="padding:5px 0;">
                        🔖 <a href="user_detail.php?id=<?= $b['opportunity_id'] ?>"><?= htmlspecialchars($b['title']) ?></a>
                        <?= status_badge(effective_status($b)) ?>
                    </div>
                <?php } ?>
            </div>
            <div class="card">
                <h3>กำหนดการสำคัญ (จากรายการที่บันทึก)</h3>
                <?php if (count($events) == 0) { echo "<p class='muted'>ไม่มีกำหนดการที่กำลังจะถึง</p>"; } ?>
                <?php foreach ($events as $ev) { ?>
                    <div style="padding:5px 0;">
                        <b><?= showdate($ev['start_datetime']) ?></b> — <?= htmlspecialchars($ev['event_title']) ?>
                        <div class="muted"><?= htmlspecialchars($ev['title']) ?></div>
                    </div>
                <?php } ?>
            </div>
            <div class="card">
                <h3>การแจ้งเตือน</h3>
                <?php if (count($notifs) == 0) { echo "<p class='muted'>ไม่มีการแจ้งเตือนใหม่</p>"; } ?>
                <?php foreach ($notifs as $n) { ?>
                    <div style="padding:5px 0;">🔔 <b><?= htmlspecialchars($n['title']) ?></b><br><span class="muted"><?= htmlspecialchars($n['message']) ?></span></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
