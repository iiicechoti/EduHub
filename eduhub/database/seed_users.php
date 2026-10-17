<?php
// สร้างบัญชีทดสอบ + นักเรียนตัวอย่าง (รันครั้งเดียวหลังนำเข้า 01_schema.sql และ 02_real_data.sql)
//   วิธีรัน: เปิด http://localhost/eduhub/database/seed_users.php  หรือ  php database/seed_users.php
//   ใช้เสร็จแล้วให้ลบไฟล์นี้ทิ้ง
//   admin / Admin@1234     นักเรียน 66001-66004 / eduhub1234
require_once(__DIR__ . "/../config.php");

if (fetch_one($conn, "SELECT user_id FROM users WHERE username = 'admin'")) {
    $admin_id = fetch_one($conn, "SELECT user_id FROM users WHERE username = 'admin'")['user_id'];
} else {
    $h = password_hash('Admin@1234', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (username, password_hash, role) VALUES ('admin', '" . $h . "', 'admin')");
    $admin_id = mysqli_insert_id($conn);
}
if (fetch_one($conn, "SELECT user_id FROM users WHERE username = '66001'")) {
    die("มีนักเรียนตัวอย่างอยู่แล้ว ไม่สร้างซ้ำ");
}

$pw = password_hash('eduhub1234', PASSWORD_DEFAULT);
// รหัส, ชื่อ, นามสกุล, school_id, study_plan_id, ระดับชั้น, จังหวัด, รายได้
$students = array(
    array('66001', 'สมชาย', 'ใจดี',     1,      1,      'M6',  'เชียงใหม่',      80000),
    array('66002', 'สมหญิง', 'รักเรียน', 2,      3,      'M5',  'กรุงเทพมหานคร', 250000),
    array('66003', 'ธนากร', 'เก่งกล้า',  'NULL', 'NULL', 'GED', 'ขอนแก่น',        120000),
    array('66004', 'นภัสสร', 'แสนงาม',   3,      1,      'M4',  'เชียงราย',       'NULL'),
);
$sid = array(); $uid = array();
foreach ($students as $s) {
    mysqli_query($conn, "INSERT INTO users (username, password_hash, role) VALUES ('$s[0]', '" . $pw . "', 'student')");
    $u = mysqli_insert_id($conn);
    mysqli_query($conn, "INSERT INTO students (user_id, student_code, school_id, study_plan_id, first_name, last_name, home_province, current_grade_level, family_income_per_year)
                         VALUES ($u, '$s[0]', $s[3], $s[4], '$s[1]', '$s[2]', '$s[6]', '$s[5]', $s[7])");
    $sid[$s[0]] = mysqli_insert_id($conn);
    $uid[$s[0]] = $u;
}

// ผลการเรียน (gpax ตามใบแสดงผลการเรียน)
$edu = array(
    array('66001', 1, 2568, 'M5', 1, 3.55, 3.55, 11.0), array('66001', 1, 2568, 'M5', 2, 3.70, 3.62, 11.5),
    array('66002', 2, 2568, 'M4', 1, 2.70, 2.70, 11.0), array('66002', 2, 2568, 'M4', 2, 2.90, 2.80, 11.0),
    array('66004', 3, 2568, 'M3', 1, 3.10, 3.10, 11.0), array('66004', 3, 2568, 'M3', 2, 3.30, 3.20, 11.0),
);
foreach ($edu as $e) {
    mysqli_query($conn, "INSERT INTO education_records (student_id, education_type, school_id, academic_year, grade_level, semester, gpa, gpax, credits_earned)
                         VALUES (" . $sid[$e[0]] . ", 'thai_school', $e[1], $e[2], '$e[3]', $e[4], $e[5], $e[6], $e[7])");
}
// GED (ไม่มี GPA/GPAX)
mysqli_query($conn, "INSERT INTO education_records (student_id, education_type, certificate_name, certificate_date) VALUES (" . $sid['66003'] . ", 'ged', 'GED', '2026-06-15')");
$ged_id = mysqli_insert_id($conn);
foreach (array(array('RLA', 160), array('Math', 155), array('Science', 158), array('Social Studies', 152)) as $g) {
    mysqli_query($conn, "INSERT INTO ged_scores (education_record_id, subject, score, test_date) VALUES ($ged_id, '$g[0]', $g[1], '2026-06-15')");
}

// คะแนนสอบ / เกรดรายวิชา
foreach (array(
    array('66001', 'subject_gpa', 'คณิตศาสตร์', 3.50, 2568), array('66001', 'exam', 'A-Level คณิต1', 55, 2569), array('66001', 'exam', 'TGAT', 62, 2569),
    array('66003', 'exam', 'TGAT', 45, 2569), array('66004', 'subject_gpa', 'คณิตศาสตร์', 3.00, 2568)) as $s) {
    mysqli_query($conn, "INSERT INTO student_scores (student_id, score_type, score_name, score_value, academic_year) VALUES (" . $sid[$s[0]] . ", '$s[1]', '$s[2]', $s[3], $s[4])");
}

// บันทึกโอกาส (ใช้ id ที่มีทั้งชุดข้อมูลจริงและชุดทดสอบ)
foreach (array(array('66001', 1), array('66001', 7), array('66001', 10), array('66002', 2), array('66003', 9)) as $b) {
    mysqli_query($conn, "INSERT INTO bookmarks (student_id, opportunity_id) VALUES (" . $sid[$b[0]] . ", $b[1])");
}
// แจ้งเตือน (สร้างจากชื่อโครงการในฐานข้อมูล)
foreach (array(array('66001', 1), array('66001', 7), array('66002', 2)) as $n) {
    $o = fetch_one($conn, "SELECT title, close_date FROM opportunities WHERE opportunity_id = $n[1]");
    if ($o) {
        $msg = esc($conn, $o['title'] . ($o['close_date'] ? ' ปิดรับสมัคร ' . showdate($o['close_date']) : ''));
        mysqli_query($conn, "INSERT INTO notifications (user_id, opportunity_id, notification_type, title, message, notify_at) VALUES (" . $uid[$n[0]] . ", $n[1], 'deadline', 'กำหนดการสำคัญ', '$msg', NOW())");
    }
}
// ข้อความติดต่อ
mysqli_query($conn, "INSERT INTO contact_messages (user_id, opportunity_id, subject, message_body, status) VALUES (" . $uid['66001'] . ", 1, 'สอบถามเอกสารที่ต้องใช้', 'ต้องใช้ ปพ.1 หรือใบรับรองผลการเรียนจากโรงเรียนคะ', 'replied')");
$m1 = mysqli_insert_id($conn);
mysqli_query($conn, "INSERT INTO contact_replies (message_id, admin_user_id, reply_body) VALUES ($m1, $admin_id, 'ใช้ใบแสดงผลการเรียนที่โรงเรียนรับรองได้ ตามรายละเอียดในประกาศต้นทาง')");
mysqli_query($conn, "INSERT INTO contact_messages (user_id, subject, message_body, status) VALUES (" . $uid['66002'] . ", 'ขอแก้ไขข้อมูลแผนการเรียน', 'ข้อมูลแผนการเรียนในโปรไฟล์ไม่ถูกต้อง ต้องการให้แก้ไข', 'pending')");

echo "สร้างข้อมูลทดสอบเรียบร้อย";
