<?php
// รายละเอียดโครงการ + ผลตรวจคุณสมบัติ (ผู้รับผิดชอบ: คนที่ 2 ส่วนรายละเอียด / คนที่ 3 ส่วนผลตรวจ)
session_start();
require_once("config.php");
require_once("eligibility.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}
$student_id = (int)$_SESSION['student_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$o = fetch_one($conn, "SELECT o.*, t.type_name, u.university_name FROM opportunities o
                       JOIN opportunity_types t ON t.opportunity_type_id = o.opportunity_type_id
                       LEFT JOIN universities u ON u.university_id = o.university_id
                       WHERE o.opportunity_id = $id AND o.status <> 'draft'");
if (!$o) {
    echo "<script>alert('ไม่พบโครงการที่ต้องการ'); window.location.href='user_search.php';</script>";
    exit();
}

$sch    = fetch_one($conn, "SELECT * FROM scholarship_details WHERE opportunity_id = $id");
$exam   = fetch_one($conn, "SELECT * FROM exam_details WHERE opportunity_id = $id");
$scores = fetch_all($conn, "SELECT * FROM opportunity_scores WHERE opportunity_id = $id");
$events = fetch_all($conn, "SELECT * FROM opportunity_events WHERE opportunity_id = $id ORDER BY start_datetime");
$majors = fetch_all($conn, "SELECT m.major_name, f.faculty_name, om.seats FROM opportunity_majors om
                            JOIN majors m ON m.major_id = om.major_id JOIN faculties f ON f.faculty_id = m.faculty_id
                            WHERE om.opportunity_id = $id");

// ตรวจคุณสมบัติและบันทึกผล
$res = check_eligibility($conn, $student_id, $id);
save_eligibility_result($conn, $student_id, $id, $res);

$saved = fetch_one($conn, "SELECT bookmark_id FROM bookmarks WHERE student_id = $student_id AND opportunity_id = $id");

$ev_label = array('open'=>'เปิดรับสมัคร','close'=>'ปิดรับสมัคร','exam'=>'วันสอบ','interview'=>'สัมภาษณ์',
                  'document'=>'ส่งเอกสาร','result'=>'ประกาศผล','other'=>'อื่น ๆ');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($o['title']) ?> | EduHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include("navbar_user.php"); ?>

<div class="container">
    <a href="user_search.php">← กลับไปค้นหา</a>

    <div class="card" style="margin-top:15px;">
        <span class="badge bg-info"><?= htmlspecialchars($o['type_name']) ?></span>
        <?= status_badge(effective_status($o)) ?>
        <h2><?= htmlspecialchars($o['title']) ?></h2>
        <div class="muted"><?= htmlspecialchars($o['university_name'] ?? $o['organization'] ?? '') ?> · ปีการศึกษา <?= $o['academic_year'] ?><?= $o['round_label'] ? ' · ' . htmlspecialchars($o['round_label']) : '' ?></div>
        <p>เปิดรับสมัคร: <?= showdate($o['open_date']) ?> &nbsp;|&nbsp; ปิดรับสมัคร: <?= showdate($o['close_date']) ?></p>

        <form method="POST" action="user_bookmark.php">
            <input type="hidden" name="opportunity_id" value="<?= $id ?>">
            <button type="submit" class="btn <?= $saved ? '' : 'btn-outline' ?>"><?= $saved ? '🔖 บันทึกแล้ว (กดเพื่อยกเลิก)' : '🔖 บันทึกโอกาสนี้' ?></button>
        </form>
    </div>

    <div class="grid2">
        <div>
            <div class="card">
                <h3>รายละเอียดโครงการ</h3>
                <p><?= nl2br(htmlspecialchars($o['description'] ?? '-')) ?></p>
                <h3>คุณสมบัติผู้สมัคร (ตามประกาศ)</h3>
                <p><?= nl2br(htmlspecialchars($o['eligibility_text'] ?? '-')) ?></p>
                <h3>เอกสารที่ต้องใช้</h3>
                <p><?= nl2br(htmlspecialchars($o['documents_text'] ?? '-')) ?></p>
                <h3>วิธีการสมัคร</h3>
                <p><?= nl2br(htmlspecialchars($o['how_to_apply'] ?? '-')) ?></p>

                <?php if ($sch) { ?>
                    <h3>รายละเอียดทุน</h3>
                    <ul>
                        <li>มูลค่าทุน: <?= $sch['amount'] !== null ? number_format($sch['amount']) . ' บาท' : '-' ?> <?= htmlspecialchars($sch['amount_note'] ?? '') ?></li>
                        <li>จำนวนทุน: <?= htmlspecialchars($sch['number_of_awards'] ?? '-') ?></li>
                        <li>ระยะเวลาสนับสนุน: <?= htmlspecialchars($sch['duration_text'] ?? '-') ?></li>
                        <li>เงื่อนไขการรักษาทุน: <?= htmlspecialchars($sch['retention_conditions'] ?? '-') ?></li>
                    </ul>
                <?php } ?>
                <?php if ($exam) { ?>
                    <h3>รายละเอียดการสอบ</h3>
                    <ul>
                        <li>วิชา: <?= htmlspecialchars($exam['subjects'] ?? '-') ?></li>
                        <li>รูปแบบ: <?= htmlspecialchars($exam['exam_format'] ?? '-') ?></li>
                        <li>สถานที่: <?= htmlspecialchars($exam['venue'] ?? '-') ?></li>
                        <li>ค่าธรรมเนียม: <?= $o['application_fee'] !== null ? number_format($o['application_fee']) . ' บาท' : '-' ?></li>
                    </ul>
                <?php } ?>
                <?php if (count($scores) > 0) { ?>
                    <h3>องค์ประกอบคะแนน</h3>
                    <table>
                        <tr><th>รายการ</th><th>น้ำหนัก (%)</th><th>ขั้นต่ำ</th></tr>
                        <?php foreach ($scores as $s) { ?>
                            <tr><td><?= htmlspecialchars($s['score_name']) ?></td><td><?= $s['weight_percent'] ?? '-' ?></td><td><?= $s['min_score'] ?? '-' ?></td></tr>
                        <?php } ?>
                    </table>
                <?php } ?>
                <?php if (count($majors) > 0) { ?>
                    <h3>คณะ/สาขาที่เปิดรับ</h3>
                    <ul>
                        <?php foreach ($majors as $m) { ?>
                            <li><?= htmlspecialchars($m['major_name']) ?> (<?= htmlspecialchars($m['faculty_name']) ?>)<?= $m['seats'] ? ' รับ ' . $m['seats'] . ' คน' : '' ?></li>
                        <?php } ?>
                    </ul>
                <?php } ?>

                <h3>แหล่งอ้างอิง</h3>
                <p>
                    <?php if ($o['source_url']) { ?><a href="<?= htmlspecialchars($o['source_url']) ?>" target="_blank">เปิดประกาศ/แหล่งข้อมูลต้นทาง</a><?php } else { echo "<span class='muted'>ไม่มีลิงก์ต้นทาง</span>"; } ?>
                    <?php if ($o['apply_url']) { ?> · <a href="<?= htmlspecialchars($o['apply_url']) ?>" target="_blank">ไปหน้าสมัคร</a><?php } ?>
                    <br><span class="muted">วันที่ประกาศ: <?= showdate($o['source_published_date']) ?> · ตรวจสอบล่าสุด: <?= showdate($o['last_verified_at']) ?></span>
                </p>
            </div>
        </div>

        <div>
            <div class="card">
                <h3>ผลตรวจสอบคุณสมบัติเบื้องต้น</h3>
                <p><?= eligibility_badge($res['status']) ?></p>
                <ul class="reasons">
                    <?php foreach ($res['reasons'] as $r) { ?>
                        <li class="<?= $r['level'] ?>"><?= htmlspecialchars($r['text']) ?></li>
                    <?php } ?>
                </ul>
                <p class="muted">ผลนี้เป็นเพียงการประเมินเบื้องต้นจากข้อมูลที่มี ไม่ใช่การรับรองสิทธิ์สมัครหรือผลการคัดเลือก กรุณาตรวจสอบประกาศต้นทางอีกครั้ง</p>
            </div>
            <div class="card">
                <h3>กำหนดการ</h3>
                <?php if (count($events) == 0) { echo "<p class='muted'>ยังไม่มีกำหนดการ</p>"; } ?>
                <?php foreach ($events as $ev) { ?>
                    <div style="padding:6px 0; border-bottom:1px solid rgba(255,255,255,0.06);">
                        <b><?= showdate($ev['start_datetime']) ?></b>
                        <span class="badge bg-secondary"><?= $ev_label[$ev['event_type']] ?></span><br>
                        <?= htmlspecialchars($ev['event_title']) ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
