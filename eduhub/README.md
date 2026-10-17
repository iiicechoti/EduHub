# EduHub (PHP + MySQL แบบพื้นฐาน)

สไตล์เดียวกับโปรเจคเก่า: `mysqli` + `config.php`, ทุกหน้าเป็นไฟล์เดี่ยว (session_start → require config → เช็คสิทธิ์ → query → HTML) ไม่มี framework

## ติดตั้ง (XAMPP)
1. วางโฟลเดอร์ไว้ที่ `htdocs/eduhub` เปิด Apache + MySQL
2. phpMyAdmin → Import ตามลำดับ
   - `database/01_schema.sql` (สร้างฐานข้อมูล `eduhub` + ตาราง)
   - `database/02_real_data.sql` (ข้อมูลจริง) หรือ `database/02b_test_mock_data.sql` (ข้อมูลสมมติไว้ทดสอบตรรกะ เลือกอย่างใดอย่างหนึ่ง)
3. เปิด `http://localhost/eduhub/database/seed_users.php` 1 ครั้ง (สร้างแอดมินและนักเรียนตัวอย่าง) แล้วลบไฟล์นั้นทิ้ง
   - หรือถ้าอยากสร้างแค่แอดมิน ให้ Import `database/create_admin.sql`
4. เข้า `http://localhost/eduhub/login.php`

| บทบาท | ชื่อผู้ใช้ | รหัสผ่าน |
|---|---|---|
| Admin | admin | Admin@1234 |
| นักเรียน | 66001 (ม.6 GPAX 3.62), 66002, 66003 (GED), 66004 | eduhub1234 |

## ไฟล์ในโฟลเดอร์ (ทุกไฟล์อยู่ระดับเดียวกัน)
- `config.php` เชื่อมฐานข้อมูล + ฟังก์ชันช่วย (fetch_one, fetch_all, esc, showdate, status_badge ...)
- `eligibility.php` ฟังก์ชันตรวจคุณสมบัติ `check_eligibility($conn, $student_id, $opp_id)`
- `navbar_user.php`, `navbar_admin.php` เมนูบน (แก้ที่เดียว ทุกหน้าเปลี่ยนตาม) — ลิงก์ของหน้าที่ยังไม่ทำจะเป็น 404 ไปก่อน
- `style.css` ธีมเดียวกันทั้งระบบ
- `login.php` `auth.php` `logout.php` `index.php`
- ทำแล้ว (ตัวอย่างให้ทุกคนดูเป็นแบบ): `user_dashboard.php` `user_search.php` `user_detail.php` `user_bookmark.php` `admin_dashboard.php`

## แบบหน้าใหม่ (คัดลอกโครงนี้)
```php
<?php
session_start();
require_once("config.php");
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') { header("Location: login.php"); exit(); }
$student_id = (int)$_SESSION['student_id'];
// ... query ด้วย fetch_one($conn, "...") / fetch_all($conn, "...") ...
?>
<!DOCTYPE html> ... <link rel="stylesheet" href="style.css"> ... <?php include("navbar_user.php"); ?> ...
```
กติกา 3 ข้อ: (1) ข้อความจากผู้ใช้ที่ใส่ใน SQL ต้องผ่าน `esc($conn, ...)` และตัวเลขผ่าน `(int)` (2) แสดงผลด้วย `htmlspecialchars()` (3) รหัสผ่านใช้ `password_hash()` / `password_verify()` ห้ามเก็บข้อความตรง ๆ

## แบ่งไฟล์ตามคน
**คนที่ 1:** `user_profile.php` `admin_students.php` `admin_schools.php` `admin_categories.php` `user_about.php` + ปรับ `admin_dashboard.php`
**คนที่ 2:** `admin_opportunities.php` (+ฟอร์มเพิ่ม/แก้ `admin_opportunity_form.php`) `admin_universities.php` `user_bookmarks.php` + ปรับ `user_search.php` `user_detail.php` `user_bookmark.php`
**คนที่ 3:** `admin_criteria.php` (+ `admin_criteria_form.php`) `user_eligibility.php` `user_notifications.php` + ทดสอบ `eligibility.php` + ปรับ `user_dashboard.php`
**คนที่ 4:** `user_calendar.php` `user_contact.php` `admin_events.php` `admin_messages.php`

แต่ละคนแก้เฉพาะไฟล์ของตัวเอง; ถ้าต้องเพิ่มลิงก์ใน navbar หรือเพิ่มฟังก์ชันใน config.php ให้บอกกันก่อน (เป็นไฟล์ที่ใช้ร่วมกัน)

## ข้อจำกัดที่ทราบ
- ยังไม่มีการป้องกัน CSRF และการจำกัดจำนวนครั้งที่ล็อกอินผิด (ควรเพิ่มถ้านำไปใช้จริง)
- เงื่อนไขพื้นที่ระดับภาค/อำเภอ ตรวจอัตโนมัติไม่ได้ (คืนผล "ข้อมูลไม่เพียงพอ")
- โค้ดชุดนี้ยังไม่เคยรันบน PHP/MySQL จริง ให้ทดสอบทันทีหลังติดตั้ง
