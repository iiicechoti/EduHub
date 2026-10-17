<?php
// ค้นหาและคัดกรองโอกาส (ผู้รับผิดชอบ: คนที่ 2)
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

// รับค่าจากฟอร์ม
$q      = isset($_GET['q']) ? trim($_GET['q']) : '';
$type   = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$grade  = isset($_GET['grade']) ? $_GET['grade'] : '';
$gpax   = isset($_GET['gpax']) ? $_GET['gpax'] : '';
$plan   = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
$fac    = isset($_GET['faculty']) ? (int)$_GET['faculty'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

// สร้างเงื่อนไข WHERE ทีละข้อ
$where = "o.status NOT IN ('draft','cancelled')";
if ($q != '') {
    $k = esc($conn, $q);
    $where .= " AND (o.title LIKE '%$k%' OR o.organization LIKE '%$k%' OR o.description LIKE '%$k%' OR u.university_name LIKE '%$k%'
                OR EXISTS (SELECT 1 FROM opportunity_majors om JOIN majors m ON m.major_id = om.major_id JOIN faculties f ON f.faculty_id = m.faculty_id
                           WHERE om.opportunity_id = o.opportunity_id AND (m.major_name LIKE '%$k%' OR f.faculty_name LIKE '%$k%')))";
}
if ($type > 0) {
    $where .= " AND (t.opportunity_type_id = $type OR t.parent_type_id = $type)";
}
if ($grade != '') {
    $g = esc($conn, $grade);
    // รับถ้าไม่ได้กำหนดระดับชั้น หรือกำหนดแล้วตรงกับที่เลือก
    $where .= " AND (NOT EXISTS (SELECT 1 FROM eligibility_criteria c JOIN criteria_grade_levels gl ON gl.criteria_id = c.criteria_id WHERE c.opportunity_id = o.opportunity_id AND c.is_active = 1)
                OR EXISTS (SELECT 1 FROM eligibility_criteria c JOIN criteria_grade_levels gl ON gl.criteria_id = c.criteria_id WHERE c.opportunity_id = o.opportunity_id AND c.is_active = 1 AND gl.grade_level = '$g'))";
}
if ($gpax != '' && is_numeric($gpax)) {
    $gv = (float)$gpax;
    $where .= " AND NOT EXISTS (SELECT 1 FROM eligibility_criteria c WHERE c.opportunity_id = o.opportunity_id AND c.is_active = 1 AND c.min_gpax > $gv)";
}
if ($plan > 0) {
    $where .= " AND (NOT EXISTS (SELECT 1 FROM eligibility_criteria c JOIN criteria_study_plans cp ON cp.criteria_id = c.criteria_id WHERE c.opportunity_id = o.opportunity_id AND c.is_active = 1)
                OR EXISTS (SELECT 1 FROM eligibility_criteria c JOIN criteria_study_plans cp ON cp.criteria_id = c.criteria_id WHERE c.opportunity_id = o.opportunity_id AND c.is_active = 1 AND cp.study_plan_id = $plan))";
}
if ($fac > 0) {
    $where .= " AND EXISTS (SELECT 1 FROM opportunity_majors om JOIN majors m ON m.major_id = om.major_id WHERE om.opportunity_id = o.opportunity_id AND m.faculty_id = $fac)";
}

$sql = "SELECT o.*, t.type_name, u.university_name FROM opportunities o
        JOIN opportunity_types t ON t.opportunity_type_id = o.opportunity_type_id
        LEFT JOIN universities u ON u.university_id = o.university_id
        WHERE $where ORDER BY o.close_date IS NULL, o.close_date LIMIT 200";
$all = fetch_all($conn, $sql);

// กรองสถานะ (คำนวณจากวันที่ จึงกรองด้วย PHP)
$rows = array();
foreach ($all as $o) {
    if ($status == '' || effective_status($o) == $status) { $rows[] = $o; }
}

$types = fetch_all($conn, "SELECT * FROM opportunity_types WHERE is_active = 1 ORDER BY COALESCE(parent_type_id, opportunity_type_id), parent_type_id IS NOT NULL, opportunity_type_id");
$plans = fetch_all($conn, "SELECT * FROM study_plans ORDER BY study_plan_id");
$facs  = fetch_all($conn, "SELECT f.faculty_id, f.faculty_name, u.university_name FROM faculties f JOIN universities u ON u.university_id = f.university_id ORDER BY u.university_name, f.faculty_name");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ค้นหาโอกาส | EduHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include("navbar_user.php"); ?>

<div class="container">
    <h2>ค้นหาโอกาสทางการศึกษา</h2>

    <form method="GET" class="card">
        <div class="form-row">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="ค้นหาชื่อโครงการ มหาวิทยาลัย คณะ สาขา หรือคำสำคัญ">
        </div>
        <div class="form-row">
            <select name="type">
                <option value="0">ทุกประเภท</option>
                <?php foreach ($types as $t) { ?>
                    <option value="<?= $t['opportunity_type_id'] ?>" <?= $type == $t['opportunity_type_id'] ? 'selected' : '' ?>><?= $t['parent_type_id'] ? '— ' : '' ?><?= htmlspecialchars($t['type_name']) ?></option>
                <?php } ?>
            </select>
            <select name="grade">
                <option value="">ทุกระดับชั้น</option>
                <?php foreach (array('M1','M2','M3','M4','M5','M6') as $gl) { ?>
                    <option value="<?= $gl ?>" <?= $grade == $gl ? 'selected' : '' ?>><?= grade_label($gl) ?></option>
                <?php } ?>
            </select>
            <input type="number" step="0.01" min="0" max="4" name="gpax" value="<?= htmlspecialchars($gpax) ?>" placeholder="GPAX ของฉัน">
            <select name="plan">
                <option value="0">ทุกแผนการเรียน</option>
                <?php foreach ($plans as $p) { ?>
                    <option value="<?= $p['study_plan_id'] ?>" <?= $plan == $p['study_plan_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['plan_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-row">
            <select name="faculty">
                <option value="0">ทุกคณะ</option>
                <?php foreach ($facs as $f) { ?>
                    <option value="<?= $f['faculty_id'] ?>" <?= $fac == $f['faculty_id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['faculty_name'] . ' — ' . $f['university_name']) ?></option>
                <?php } ?>
            </select>
            <select name="status">
                <option value="">ทุกสถานะ</option>
                <option value="open" <?= $status == 'open' ? 'selected' : '' ?>>เปิดรับสมัคร</option>
                <option value="upcoming" <?= $status == 'upcoming' ? 'selected' : '' ?>>ยังไม่เปิดรับสมัคร</option>
                <option value="closed" <?= $status == 'closed' ? 'selected' : '' ?>>ปิดรับสมัคร</option>
            </select>
            <button type="submit" class="btn">ค้นหา</button>
            <a href="user_search.php" class="btn btn-outline">ล้าง</a>
        </div>
    </form>

    <p class="muted">พบ <?= count($rows) ?> รายการ</p>
    <?php if (count($rows) == 0) { echo "<div class='card'>ไม่พบข้อมูลที่ตรงกับเงื่อนไข ลองลดตัวกรองลง</div>"; } ?>

    <div class="grid2">
    <?php foreach ($rows as $o) { ?>
        <div class="card">
            <span class="badge bg-info"><?= htmlspecialchars($o['type_name']) ?></span>
            <?= status_badge(effective_status($o)) ?>
            <h3 style="margin-top:10px;"><a href="user_detail.php?id=<?= $o['opportunity_id'] ?>"><?= htmlspecialchars($o['title']) ?></a></h3>
            <div class="muted"><?= htmlspecialchars($o['university_name'] ?? $o['organization'] ?? '') ?> · ปีการศึกษา <?= $o['academic_year'] ?></div>
            <div>ปิดรับสมัคร: <?= showdate($o['close_date']) ?></div>
        </div>
    <?php } ?>
    </div>
</div>

</body>
</html>
