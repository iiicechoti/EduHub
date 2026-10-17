<?php
// Dashboard ผู้ดูแลระบบ (ผู้รับผิดชอบ: คนที่ 1)
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$n_students = fetch_one($conn, "SELECT COUNT(*) AS n FROM students")['n'];
$n_opps     = fetch_one($conn, "SELECT COUNT(*) AS n FROM opportunities")['n'];
$n_open     = fetch_one($conn, "SELECT COUNT(*) AS n FROM opportunities WHERE status = 'open' AND (close_date IS NULL OR close_date >= NOW())")['n'];
$n_pending  = fetch_one($conn, "SELECT COUNT(*) AS n FROM contact_messages WHERE status = 'pending'")['n'];

$by_type = fetch_all($conn, "SELECT t.type_name, COUNT(o.opportunity_id) AS n
                             FROM opportunity_types t LEFT JOIN opportunities o ON o.opportunity_type_id = t.opportunity_type_id
                             GROUP BY t.opportunity_type_id, t.type_name ORDER BY n DESC");
$pending = fetch_all($conn, "SELECT m.subject, m.created_at, u.username FROM contact_messages m
                             JOIN users u ON u.user_id = m.user_id WHERE m.status = 'pending' ORDER BY m.created_at LIMIT 5");
// สถานะค้าง: ยังเป็น open แต่เลยวันปิดรับแล้ว
$stale = fetch_all($conn, "SELECT title, close_date FROM opportunities WHERE status = 'open' AND close_date < NOW()");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ผู้ดูแลระบบ | EduHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include("navbar_admin.php"); ?>

<div class="container">
    <h2>Dashboard ผู้ดูแลระบบ</h2>

    <div class="grid3" style="grid-template-columns:repeat(4,1fr);">
        <div class="card stat"><div class="num"><?= $n_students ?></div><div class="muted">นักเรียน</div></div>
        <div class="card stat"><div class="num"><?= $n_opps ?></div><div class="muted">โอกาสทั้งหมด</div></div>
        <div class="card stat"><div class="num" style="color:#28a745;"><?= $n_open ?></div><div class="muted">เปิดรับสมัครอยู่</div></div>
        <div class="card stat"><div class="num" style="color:#ffc107;"><?= $n_pending ?></div><div class="muted">ข้อความรอตอบ</div></div>
    </div>

    <div class="grid2">
        <div class="card">
            <h3>จำนวนโอกาสแยกตามประเภท</h3>
            <table>
                <?php foreach ($by_type as $t) { ?>
                    <tr><td><?= htmlspecialchars($t['type_name']) ?></td><td style="text-align:right;"><?= $t['n'] ?></td></tr>
                <?php } ?>
            </table>
        </div>
        <div>
            <div class="card">
                <h3>ข้อความที่รอตอบ</h3>
                <?php if (count($pending) == 0) { echo "<p class='muted'>ไม่มีข้อความค้าง</p>"; } ?>
                <?php foreach ($pending as $m) { ?>
                    <div style="padding:5px 0;"><b><?= htmlspecialchars($m['subject']) ?></b> — <?= htmlspecialchars($m['username']) ?> (<?= showdate($m['created_at']) ?>)</div>
                <?php } ?>
            </div>
            <div class="card">
                <h3>ต้องอัปเดตสถานะ (เลยกำหนดปิดรับแต่ยังเป็น "เปิด")</h3>
                <?php if (count($stale) == 0) { echo "<p class='muted'>ไม่มีรายการค้าง</p>"; } ?>
                <?php foreach ($stale as $s) { ?>
                    <div style="padding:5px 0;"><?= htmlspecialchars($s['title']) ?> — ปิด <?= showdate($s['close_date']) ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
