<?php
// เชื่อมต่อฐานข้อมูล (แก้ชื่อ/รหัสผ่านให้ตรงกับเครื่องตัวเอง)
$conn = mysqli_connect("localhost", "root", "", "eduhub")
    or die(mysqli_connect_error() . " ติดต่อฐานข้อมูลไม่ได้");
mysqli_set_charset($conn, "utf8mb4"); // ให้แสดงภาษาไทยได้
date_default_timezone_set('Asia/Bangkok');

/// ---------- ฟังก์ชันช่วยดึงข้อมูล ----------
function fetch_one($conn, $sql) {      // ดึง 1 แถว
    $r = mysqli_query($conn, $sql);
    return $r ? mysqli_fetch_assoc($r) : null;
}
function fetch_all($conn, $sql) {      // ดึงหลายแถว
    $out = array();
    $r = mysqli_query($conn, $sql);
    if ($r) { while ($row = mysqli_fetch_assoc($r)) { $out[] = $row; } }
    return $out;
}
function esc($conn, $v) {              // กัน SQL injection สำหรับข้อความ
    return mysqli_real_escape_string($conn, $v);
}

/// ---------- ฟังก์ชันวันที่ (ใช้ชุดเดียวกับโปรเจคเก่า) ----------
function tranmonth2text($month){
    $names = array("01"=>"มกราคม","02"=>"กุมภาพันธ์","03"=>"มีนาคม","04"=>"เมษายน","05"=>"พฤษภาคม","06"=>"มิถุนายน",
                   "07"=>"กรกฎาคม","08"=>"สิงหาคม","09"=>"กันยายน","10"=>"ตุลาคม","11"=>"พฤศจิกายน","12"=>"ธันวาคม");
    return isset($names[$month]) ? $names[$month] : "ทุกเดือน";
}
function displaydatetextmonth($date) {   // 2026-10-30 -> 30 ตุลาคม 2569
    $date_array = explode("-", $date);
    $y = $date_array[0] + 543;
    $m = tranmonth2text($date_array[1]);
    $d = $date_array[2];
    return $d . " " . $m . " " . $y;
}
function showdate($date) {               // รับ DATE หรือ DATETIME ถ้าว่างคืน "-"
    if (empty($date)) { return "-"; }
    return displaydatetextmonth(substr($date, 0, 10));
}
function days_left($close) {
    if (empty($close)) { return null; }
    return (int)ceil((strtotime($close) - time()) / 86400);
}

/// ---------- สถานะโครงการ ----------
// สถานะที่แสดงจริง: คำนวณจากวันเปิด-ปิด ยกเว้น draft/cancelled/expired ที่แอดมินกำหนดเอง
function effective_status($o) {
    if (in_array($o['status'], array('draft', 'cancelled', 'expired'))) { return $o['status']; }
    $now = time();
    if (!empty($o['open_date']) && $now < strtotime($o['open_date'])) { return 'upcoming'; }
    if (!empty($o['close_date']) && $now > strtotime($o['close_date'])) { return 'closed'; }
    return 'open';
}
function status_badge($s) {
    $text  = array('open'=>'เปิดรับสมัคร','upcoming'=>'ยังไม่เปิดรับสมัคร','closed'=>'ปิดรับสมัคร',
                   'expired'=>'หมดอายุ','cancelled'=>'ยกเลิก','draft'=>'ร่าง');
    $class = array('open'=>'bg-success','upcoming'=>'bg-info','closed'=>'bg-secondary',
                   'expired'=>'bg-secondary','cancelled'=>'bg-danger','draft'=>'bg-warning');
    return "<span class='badge " . $class[$s] . "'>" . $text[$s] . "</span>";
}
function grade_label($g) {               // M6 -> ม.6
    return preg_match('/^M([1-6])$/', $g, $m) ? 'ม.' . $m[1] : $g;
}
?>
