-- =====================================================================
-- EduHub : เว็บแอปพลิเคชันศูนย์รวมโอกาสทางการศึกษาสำหรับนักเรียนมัธยมศึกษา
-- Database schema  (MySQL 8.0+ / MariaDB 10.5+, InnoDB, utf8mb4)
-- วิธีใช้: mysql -u root -p < eduhub_schema.sql
-- หมายเหตุ: ยังไม่ได้สร้างบัญชี Admin ใน SQL เพราะต้องใช้ password_hash() ของ PHP
--          (ดูวิธีสร้างท้ายไฟล์)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS eduhub
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE eduhub;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- กลุ่ม 1: ผู้ใช้งานและนักเรียน
-- ---------------------------------------------------------------------

CREATE TABLE users (
  user_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสผู้ใช้งาน',
  username VARCHAR(50) NOT NULL COMMENT 'ชื่อผู้ใช้สำหรับเข้าสู่ระบบ (นักเรียนใช้ค่าเดียวกับรหัสนักเรียน)',
  email VARCHAR(100) NULL COMMENT 'อีเมล (ไม่บังคับ เพราะนักเรียนล็อกอินด้วยรหัสนักเรียน)',
  password_hash VARCHAR(255) NOT NULL COMMENT 'รหัสผ่านที่เข้ารหัสด้วย password_hash() ห้ามเก็บข้อความธรรมดา',
  role ENUM('student','admin') NOT NULL DEFAULT 'student' COMMENT 'บทบาทผู้ใช้งาน',
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active' COMMENT 'สถานะบัญชี',
  last_login_at DATETIME NULL COMMENT 'เวลาเข้าสู่ระบบล่าสุด',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้างบัญชี',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB COMMENT='บัญชีผู้ใช้งานระบบ (นักเรียนและผู้ดูแลระบบ)';

CREATE TABLE schools (
  school_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสโรงเรียน',
  school_name VARCHAR(150) NOT NULL COMMENT 'ชื่อโรงเรียน',
  province VARCHAR(50) NULL COMMENT 'จังหวัดที่ตั้ง',
  district VARCHAR(50) NULL COMMENT 'อำเภอ/เขต',
  school_type VARCHAR(50) NULL COMMENT 'ประเภทโรงเรียน เช่น รัฐบาล เอกชน',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่เพิ่มข้อมูล',
  PRIMARY KEY (school_id),
  UNIQUE KEY uq_schools_name_province (school_name, province),
  KEY idx_schools_province (province)
) ENGINE=InnoDB COMMENT='ข้อมูลโรงเรียน';

CREATE TABLE study_plans (
  study_plan_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสแผนการเรียน',
  plan_name VARCHAR(100) NOT NULL COMMENT 'ชื่อแผนการเรียน เช่น วิทย์-คณิต',
  PRIMARY KEY (study_plan_id),
  UNIQUE KEY uq_study_plans_name (plan_name)
) ENGINE=InnoDB COMMENT='ข้อมูลอ้างอิงแผนการเรียน';

CREATE TABLE students (
  student_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสนักเรียน',
  user_id INT UNSIGNED NOT NULL COMMENT 'บัญชีผู้ใช้ของนักเรียน',
  student_code VARCHAR(20) NOT NULL COMMENT 'รหัสนักเรียน (ใช้เข้าสู่ระบบ Admin เป็นผู้เพิ่มให้)',
  school_id INT UNSIGNED NULL COMMENT 'โรงเรียนปัจจุบัน (ว่างได้ กรณีวุฒิ GED/อื่น ๆ)',
  study_plan_id INT UNSIGNED NULL COMMENT 'แผนการเรียนปัจจุบัน',
  first_name VARCHAR(100) NOT NULL COMMENT 'ชื่อ',
  last_name VARCHAR(100) NOT NULL COMMENT 'นามสกุล',
  birth_date DATE NULL COMMENT 'วันเกิด',
  phone VARCHAR(20) NULL COMMENT 'เบอร์โทรศัพท์',
  home_province VARCHAR(50) NULL COMMENT 'จังหวัดภูมิลำเนา (ใช้ตรวจโควตาพื้นที่)',
  current_grade_level VARCHAR(10) NULL COMMENT 'ระดับชั้นปัจจุบัน เช่น M4 M5 M6 GED',
  family_income_per_year DECIMAL(12,2) NULL COMMENT 'รายได้ครอบครัวต่อปี (ไม่บังคับ ใช้ตรวจทุนขาดแคลน)',
  interests VARCHAR(255) NULL COMMENT 'ความสนใจ/คำสำคัญ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้างข้อมูล',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_students_user (user_id),
  UNIQUE KEY uq_students_code (student_code),
  KEY idx_students_school (school_id),
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools (school_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_students_plan FOREIGN KEY (study_plan_id) REFERENCES study_plans (study_plan_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ข้อมูลโปรไฟล์นักเรียน';

CREATE TABLE education_records (
  education_record_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสประวัติการศึกษา',
  student_id INT UNSIGNED NOT NULL COMMENT 'นักเรียนเจ้าของข้อมูล',
  education_type ENUM('thai_school','ged','other') NOT NULL DEFAULT 'thai_school' COMMENT 'ประเภทวุฒิการศึกษา',
  school_id INT UNSIGNED NULL COMMENT 'โรงเรียนที่ศึกษา (ว่างได้สำหรับ GED/อื่น ๆ)',
  academic_year SMALLINT UNSIGNED NULL COMMENT 'ปีการศึกษา (พ.ศ.)',
  grade_level VARCHAR(10) NULL COMMENT 'ระดับชั้น เช่น M4 M5 M6',
  semester TINYINT UNSIGNED NULL COMMENT 'ภาคเรียน 1 หรือ 2 (ว่างได้หากเป็นสรุปสะสม)',
  gpa DECIMAL(3,2) NULL COMMENT 'เกรดเฉลี่ยของภาคเรียนนั้น',
  gpax DECIMAL(3,2) NULL COMMENT 'เกรดเฉลี่ยสะสม ณ ภาคเรียนนั้น (ตามใบแสดงผลการเรียนจริง)',
  credits_earned DECIMAL(5,1) NULL COMMENT 'หน่วยกิตที่ได้ในภาคเรียนนั้น (ใช้คำนวณถ่วงน้ำหนัก)',
  certificate_name VARCHAR(150) NULL COMMENT 'ชื่อวุฒิ/ใบรับรอง เช่น GED, ปวช.',
  certificate_date DATE NULL COMMENT 'วันที่ได้รับวุฒิ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่บันทึก',
  PRIMARY KEY (education_record_id),
  UNIQUE KEY uq_edu_student_term (student_id, education_type, academic_year, grade_level, semester),
  KEY idx_edu_school (school_id),
  CONSTRAINT fk_edu_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_edu_school FOREIGN KEY (school_id) REFERENCES schools (school_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_edu_gpa CHECK (gpa IS NULL OR (gpa >= 0 AND gpa <= 4)),
  CONSTRAINT chk_edu_gpax CHECK (gpax IS NULL OR (gpax >= 0 AND gpax <= 4))
) ENGINE=InnoDB COMMENT='ประวัติการศึกษาและผลการเรียนรายภาคเรียน (รองรับ GED/วุฒิอื่น)';

CREATE TABLE ged_scores (
  ged_score_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสคะแนน GED',
  education_record_id INT UNSIGNED NOT NULL COMMENT 'ประวัติการศึกษาที่เป็นวุฒิ GED',
  subject VARCHAR(50) NOT NULL COMMENT 'รายวิชา GED เช่น RLA, Math, Science, Social Studies',
  score SMALLINT UNSIGNED NOT NULL COMMENT 'คะแนนรายวิชา',
  test_date DATE NULL COMMENT 'วันที่สอบ',
  PRIMARY KEY (ged_score_id),
  UNIQUE KEY uq_ged_record_subject (education_record_id, subject),
  CONSTRAINT fk_ged_record FOREIGN KEY (education_record_id) REFERENCES education_records (education_record_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='คะแนน GED รายวิชา';

CREATE TABLE student_scores (
  student_score_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสคะแนน',
  student_id INT UNSIGNED NOT NULL COMMENT 'นักเรียนเจ้าของคะแนน',
  score_type ENUM('exam','subject_gpa') NOT NULL COMMENT 'ชนิด: คะแนนสอบ หรือ เกรดรายวิชา',
  score_name VARCHAR(100) NOT NULL COMMENT 'ชื่อคะแนน เช่น TGAT1, A-Level คณิต1, วิชาคณิตศาสตร์',
  score_value DECIMAL(6,2) NOT NULL COMMENT 'ค่าคะแนนหรือเกรด',
  academic_year SMALLINT UNSIGNED NULL COMMENT 'ปีการศึกษาที่ได้คะแนน (พ.ศ.)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่บันทึก',
  PRIMARY KEY (student_score_id),
  UNIQUE KEY uq_student_score (student_id, score_type, score_name, academic_year),
  CONSTRAINT fk_sscore_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='คะแนนสอบและเกรดรายวิชาของนักเรียน (ใช้ตรวจคุณสมบัติ)';

-- ---------------------------------------------------------------------
-- กลุ่ม 2: มหาวิทยาลัย คณะ สาขา
-- ---------------------------------------------------------------------

CREATE TABLE universities (
  university_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสมหาวิทยาลัย',
  university_name VARCHAR(150) NOT NULL COMMENT 'ชื่อมหาวิทยาลัย',
  province VARCHAR(50) NULL COMMENT 'จังหวัดที่ตั้ง',
  website_url VARCHAR(255) NULL COMMENT 'เว็บไซต์หลัก',
  PRIMARY KEY (university_id),
  UNIQUE KEY uq_universities_name (university_name)
) ENGINE=InnoDB COMMENT='ข้อมูลมหาวิทยาลัย';

CREATE TABLE faculties (
  faculty_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสคณะ',
  university_id INT UNSIGNED NOT NULL COMMENT 'มหาวิทยาลัยที่คณะสังกัด',
  faculty_name VARCHAR(150) NOT NULL COMMENT 'ชื่อคณะ',
  PRIMARY KEY (faculty_id),
  UNIQUE KEY uq_faculties_uni_name (university_id, faculty_name),
  CONSTRAINT fk_faculties_university FOREIGN KEY (university_id) REFERENCES universities (university_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ข้อมูลคณะ';

CREATE TABLE majors (
  major_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสสาขา',
  faculty_id INT UNSIGNED NOT NULL COMMENT 'คณะที่สาขาสังกัด',
  major_name VARCHAR(150) NOT NULL COMMENT 'ชื่อสาขา/หลักสูตร',
  major_code VARCHAR(30) NULL COMMENT 'รหัสโครงการ/สาขา (ถ้ามี)',
  PRIMARY KEY (major_id),
  UNIQUE KEY uq_majors_faculty_name (faculty_id, major_name),
  CONSTRAINT fk_majors_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (faculty_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ข้อมูลสาขา';

-- ---------------------------------------------------------------------
-- กลุ่ม 3: โอกาสทางการศึกษา
-- ---------------------------------------------------------------------

CREATE TABLE opportunity_types (
  opportunity_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสประเภท/หมวดหมู่',
  parent_type_id INT UNSIGNED NULL COMMENT 'หมวดหมู่แม่ (ว่าง = หมวดหลัก)',
  type_code VARCHAR(30) NOT NULL COMMENT 'รหัสประเภท เช่น scholarship competition tcas1',
  type_name VARCHAR(100) NOT NULL COMMENT 'ชื่อประเภทที่แสดง',
  description VARCHAR(255) NULL COMMENT 'คำอธิบายประเภท',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'เปิดใช้งาน (1) หรือปิด (0)',
  PRIMARY KEY (opportunity_type_id),
  UNIQUE KEY uq_opptypes_code (type_code),
  CONSTRAINT fk_opptypes_parent FOREIGN KEY (parent_type_id) REFERENCES opportunity_types (opportunity_type_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='หมวดหมู่/ประเภทโอกาสทางการศึกษา';

CREATE TABLE opportunities (
  opportunity_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสโอกาสทางการศึกษา',
  opportunity_type_id INT UNSIGNED NOT NULL COMMENT 'ประเภทของโอกาส',
  university_id INT UNSIGNED NULL COMMENT 'มหาวิทยาลัยเจ้าของโครงการ (ถ้ามี)',
  title VARCHAR(255) NOT NULL COMMENT 'ชื่อโครงการ',
  organization VARCHAR(200) NULL COMMENT 'หน่วยงานผู้จัด/ผู้ให้ทุน',
  description TEXT NULL COMMENT 'รายละเอียดโครงการ',
  eligibility_text TEXT NULL COMMENT 'คุณสมบัติผู้สมัครตามประกาศ (ข้อความ)',
  documents_text TEXT NULL COMMENT 'เอกสารที่ต้องใช้',
  how_to_apply TEXT NULL COMMENT 'วิธีการสมัคร',
  apply_url VARCHAR(500) NULL COMMENT 'ลิงก์สมัครที่เว็บต้นทาง',
  source_url VARCHAR(500) NULL COMMENT 'ลิงก์แหล่งอ้างอิงของประกาศ',
  source_published_date DATE NULL COMMENT 'วันที่ประกาศของแหล่งอ้างอิง',
  academic_year SMALLINT UNSIGNED NOT NULL COMMENT 'ปีการศึกษา (พ.ศ.)',
  round_label VARCHAR(50) NULL COMMENT 'รอบรับสมัคร เช่น TCAS รอบ 1',
  seats INT UNSIGNED NULL COMMENT 'จำนวนรับ',
  application_fee DECIMAL(10,2) NULL COMMENT 'ค่าสมัคร/ค่าธรรมเนียม',
  open_date DATETIME NULL COMMENT 'วันเปิดรับสมัคร',
  close_date DATETIME NULL COMMENT 'วันปิดรับสมัคร',
  status ENUM('draft','upcoming','open','closed','expired','cancelled') NOT NULL DEFAULT 'draft' COMMENT 'สถานะโครงการ',
  last_verified_at DATETIME NULL COMMENT 'วันที่ผู้ดูแลตรวจสอบข้อมูลล่าสุด',
  created_by INT UNSIGNED NULL COMMENT 'ผู้สร้างรายการ',
  updated_by INT UNSIGNED NULL COMMENT 'ผู้แก้ไขล่าสุด',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้าง',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (opportunity_id),
  KEY idx_opp_title (title),
  KEY idx_opp_type_status (opportunity_type_id, status),
  KEY idx_opp_year_round (academic_year, round_label),
  KEY idx_opp_close_date (close_date),
  KEY idx_opp_university (university_id),
  CONSTRAINT fk_opp_type FOREIGN KEY (opportunity_type_id) REFERENCES opportunity_types (opportunity_type_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_opp_university FOREIGN KEY (university_id) REFERENCES universities (university_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_opp_created_by FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_opp_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_opp_dates CHECK (open_date IS NULL OR close_date IS NULL OR open_date <= close_date)
) ENGINE=InnoDB COMMENT='ตารางกลางโอกาสทางการศึกษา (ทุน การสอบ TCAS โควตา)';

CREATE TABLE scholarship_details (
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โอกาสที่เป็นทุนการศึกษา',
  scholarship_category ENUM('merit','need','talent','other') NOT NULL DEFAULT 'other' COMMENT 'ประเภททุน: เรียนดี ขาดแคลน ความสามารถพิเศษ อื่น ๆ',
  provider_type ENUM('university','government','private','other') NOT NULL DEFAULT 'other' COMMENT 'ประเภทผู้ให้ทุน',
  amount DECIMAL(12,2) NULL COMMENT 'มูลค่าทุนต่อทุน (บาท)',
  amount_note VARCHAR(255) NULL COMMENT 'หมายเหตุมูลค่าทุน เช่น ครอบคลุมค่าเล่าเรียนตลอดหลักสูตร',
  number_of_awards INT UNSIGNED NULL COMMENT 'จำนวนทุน',
  duration_text VARCHAR(150) NULL COMMENT 'ระยะเวลาสนับสนุน',
  max_family_income DECIMAL(12,2) NULL COMMENT 'รายได้ครอบครัวสูงสุดต่อปีตามประกาศ',
  retention_conditions TEXT NULL COMMENT 'เงื่อนไขการรักษาทุน',
  PRIMARY KEY (opportunity_id),
  CONSTRAINT fk_schdet_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='รายละเอียดเฉพาะของทุนการศึกษา';

CREATE TABLE exam_details (
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โอกาสที่เป็นการสอบ/การแข่งขัน',
  subjects VARCHAR(255) NULL COMMENT 'วิชาที่สอบ/สาขาที่แข่งขัน',
  exam_format VARCHAR(100) NULL COMMENT 'รูปแบบการสอบ เช่น ออนไลน์ ปรนัย อัตนัย',
  venue VARCHAR(255) NULL COMMENT 'สถานที่สอบ',
  min_score DECIMAL(6,2) NULL COMMENT 'คะแนนขั้นต่ำ (ถ้ามี)',
  PRIMARY KEY (opportunity_id),
  CONSTRAINT fk_examdet_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='รายละเอียดเฉพาะของการสอบแข่งขัน';

CREATE TABLE opportunity_majors (
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการ',
  major_id INT UNSIGNED NOT NULL COMMENT 'สาขาที่เปิดรับในโครงการ',
  seats INT UNSIGNED NULL COMMENT 'จำนวนรับของสาขานี้ในโครงการ',
  PRIMARY KEY (opportunity_id, major_id),
  CONSTRAINT fk_oppmaj_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_oppmaj_major FOREIGN KEY (major_id) REFERENCES majors (major_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ความสัมพันธ์โครงการกับสาขา (many-to-many)';

CREATE TABLE opportunity_scores (
  opportunity_score_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสองค์ประกอบคะแนน',
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการ',
  score_name VARCHAR(100) NOT NULL COMMENT 'ชื่อองค์ประกอบคะแนน เช่น TGAT, A-Level คณิต1, GPAX',
  weight_percent DECIMAL(5,2) NULL COMMENT 'น้ำหนักคะแนน (%)',
  min_score DECIMAL(6,2) NULL COMMENT 'คะแนนขั้นต่ำ',
  max_score DECIMAL(6,2) NULL COMMENT 'คะแนนเต็ม',
  PRIMARY KEY (opportunity_score_id),
  UNIQUE KEY uq_oppscore (opportunity_id, score_name),
  CONSTRAINT fk_oppscore_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='องค์ประกอบคะแนน น้ำหนัก และคะแนนขั้นต่ำของโครงการ';

CREATE TABLE opportunity_locations (
  opportunity_location_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสเงื่อนไขพื้นที่',
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการ (โควตา)',
  condition_type ENUM('region','province','district','school') NOT NULL COMMENT 'ชนิดเงื่อนไข: ภาค จังหวัด อำเภอ โรงเรียน',
  region VARCHAR(50) NULL COMMENT 'ภูมิภาค',
  province VARCHAR(50) NULL COMMENT 'จังหวัด',
  district VARCHAR(50) NULL COMMENT 'อำเภอ',
  school_id INT UNSIGNED NULL COMMENT 'โรงเรียนเครือข่าย (กรณีโควตาโรงเรียน)',
  note VARCHAR(255) NULL COMMENT 'หมายเหตุเงื่อนไข',
  PRIMARY KEY (opportunity_location_id),
  KEY idx_oppLoc_province (province),
  CONSTRAINT fk_opploc_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_opploc_school FOREIGN KEY (school_id) REFERENCES schools (school_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='เงื่อนไขพื้นที่/โรงเรียนสำหรับโควตา';

CREATE TABLE opportunity_events (
  event_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสกำหนดการ',
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการ',
  event_type ENUM('open','close','exam','interview','document','result','other') NOT NULL COMMENT 'ประเภทกำหนดการ',
  event_title VARCHAR(200) NOT NULL COMMENT 'ชื่อกำหนดการ',
  start_datetime DATETIME NOT NULL COMMENT 'วันเวลาเริ่ม',
  end_datetime DATETIME NULL COMMENT 'วันเวลาสิ้นสุด (ถ้ามี)',
  location VARCHAR(255) NULL COMMENT 'สถานที่ (ถ้ามี)',
  note VARCHAR(255) NULL COMMENT 'หมายเหตุ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้าง',
  PRIMARY KEY (event_id),
  KEY idx_events_start (start_datetime),
  KEY idx_events_opp (opportunity_id, event_type),
  CONSTRAINT fk_events_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='กำหนดการของโครงการ (เปิด/ปิดรับสมัคร สอบ สัมภาษณ์ ประกาศผล)';

-- ---------------------------------------------------------------------
-- กลุ่ม 4: เกณฑ์คุณสมบัติและผลการตรวจสอบ
-- ---------------------------------------------------------------------

CREATE TABLE eligibility_criteria (
  criteria_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสเกณฑ์',
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการที่ใช้เกณฑ์นี้',
  version_no SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'เวอร์ชันของเกณฑ์',
  academic_year SMALLINT UNSIGNED NOT NULL COMMENT 'ปีการศึกษาที่เกณฑ์นี้ใช้ (พ.ศ.)',
  source_url VARCHAR(500) NULL COMMENT 'แหล่งอ้างอิงของเกณฑ์',
  education_type_required ENUM('any','thai_school','ged','other') NOT NULL DEFAULT 'any' COMMENT 'ประเภทวุฒิที่กำหนด',
  min_gpax DECIMAL(3,2) NULL COMMENT 'GPAX ขั้นต่ำ',
  min_semesters_considered TINYINT UNSIGNED NULL COMMENT 'จำนวนภาคเรียนที่ใช้พิจารณา GPAX',
  max_family_income DECIMAL(12,2) NULL COMMENT 'รายได้ครอบครัวสูงสุดต่อปี',
  home_province_required VARCHAR(50) NULL COMMENT 'จังหวัดภูมิลำเนาที่กำหนด (ถ้ามีเฉพาะจังหวัดเดียว)',
  other_conditions TEXT NULL COMMENT 'เงื่อนไขพิเศษที่ระบบตรวจอัตโนมัติไม่ได้',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'เกณฑ์เวอร์ชันที่ใช้งานอยู่ (1) หรือเลิกใช้ (0)',
  created_by INT UNSIGNED NULL COMMENT 'ผู้บันทึกเกณฑ์',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่บันทึก',
  PRIMARY KEY (criteria_id),
  UNIQUE KEY uq_criteria_version (opportunity_id, version_no),
  KEY idx_criteria_active (opportunity_id, is_active),
  CONSTRAINT fk_criteria_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_criteria_user FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='เกณฑ์คุณสมบัติของโครงการ (แยกเวอร์ชันและปีการศึกษา)';

CREATE TABLE criteria_grade_levels (
  criteria_id INT UNSIGNED NOT NULL COMMENT 'เกณฑ์',
  grade_level VARCHAR(10) NOT NULL COMMENT 'ระดับชั้นที่รับ เช่น M4 M5 M6',
  PRIMARY KEY (criteria_id, grade_level),
  CONSTRAINT fk_cgl_criteria FOREIGN KEY (criteria_id) REFERENCES eligibility_criteria (criteria_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ระดับชั้นที่เกณฑ์รองรับ';

CREATE TABLE criteria_study_plans (
  criteria_id INT UNSIGNED NOT NULL COMMENT 'เกณฑ์',
  study_plan_id INT UNSIGNED NOT NULL COMMENT 'แผนการเรียนที่รับ',
  PRIMARY KEY (criteria_id, study_plan_id),
  CONSTRAINT fk_csp_criteria FOREIGN KEY (criteria_id) REFERENCES eligibility_criteria (criteria_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_csp_plan FOREIGN KEY (study_plan_id) REFERENCES study_plans (study_plan_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='แผนการเรียนที่เกณฑ์รองรับ';

CREATE TABLE criteria_majors (
  criteria_id INT UNSIGNED NOT NULL COMMENT 'เกณฑ์',
  major_id INT UNSIGNED NOT NULL COMMENT 'สาขาที่เกณฑ์นี้ใช้กับ',
  PRIMARY KEY (criteria_id, major_id),
  CONSTRAINT fk_cmaj_criteria FOREIGN KEY (criteria_id) REFERENCES eligibility_criteria (criteria_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cmaj_major FOREIGN KEY (major_id) REFERENCES majors (major_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='สาขาที่เกี่ยวข้องกับเกณฑ์';

CREATE TABLE criteria_subjects (
  criteria_subject_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสเกณฑ์รายวิชา',
  criteria_id INT UNSIGNED NOT NULL COMMENT 'เกณฑ์',
  subject_name VARCHAR(100) NOT NULL COMMENT 'ชื่อรายวิชาที่กำหนด',
  min_gpa DECIMAL(3,2) NULL COMMENT 'เกรดขั้นต่ำของรายวิชา',
  PRIMARY KEY (criteria_subject_id),
  UNIQUE KEY uq_csub (criteria_id, subject_name),
  CONSTRAINT fk_csub_criteria FOREIGN KEY (criteria_id) REFERENCES eligibility_criteria (criteria_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='เกณฑ์เกรดรายวิชา';

CREATE TABLE eligibility_results (
  result_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสผลตรวจสอบ',
  student_id INT UNSIGNED NOT NULL COMMENT 'นักเรียนที่ถูกตรวจ',
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการที่ตรวจ',
  criteria_id INT UNSIGNED NOT NULL COMMENT 'เกณฑ์เวอร์ชันที่ใช้ตรวจ (ป้องกันใช้เกณฑ์เก่า)',
  result_status ENUM('pass','fail','insufficient') NOT NULL COMMENT 'ตรงเกณฑ์ / ไม่ตรงเกณฑ์ / ข้อมูลไม่เพียงพอ',
  reasons JSON NULL COMMENT 'เหตุผลประกอบรายข้อ เช่น GPAX ไม่ถึงเกณฑ์ ขาดคะแนนสอบ',
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันเวลาที่ตรวจ',
  PRIMARY KEY (result_id),
  KEY idx_result_student_opp (student_id, opportunity_id, checked_at),
  CONSTRAINT fk_result_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_result_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_result_criteria FOREIGN KEY (criteria_id) REFERENCES eligibility_criteria (criteria_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ผลตรวจสอบคุณสมบัติเบื้องต้น (ไม่ใช่การรับรองสิทธิ์)';

-- ---------------------------------------------------------------------
-- กลุ่ม 5: การใช้งานของนักเรียน
-- ---------------------------------------------------------------------

CREATE TABLE bookmarks (
  bookmark_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสรายการที่บันทึก',
  student_id INT UNSIGNED NOT NULL COMMENT 'นักเรียน',
  opportunity_id INT UNSIGNED NOT NULL COMMENT 'โครงการที่บันทึก',
  note VARCHAR(255) NULL COMMENT 'บันทึกส่วนตัว',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่บันทึก',
  PRIMARY KEY (bookmark_id),
  UNIQUE KEY uq_bookmark (student_id, opportunity_id),
  CONSTRAINT fk_bm_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bm_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='โอกาสที่นักเรียนบันทึกไว้';

CREATE TABLE notifications (
  notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสการแจ้งเตือน',
  user_id INT UNSIGNED NOT NULL COMMENT 'ผู้รับการแจ้งเตือน',
  opportunity_id INT UNSIGNED NULL COMMENT 'โครงการที่เกี่ยวข้อง',
  event_id INT UNSIGNED NULL COMMENT 'กำหนดการที่เกี่ยวข้อง',
  notification_type ENUM('deadline','event','status_change','reply','system') NOT NULL DEFAULT 'system' COMMENT 'ประเภทการแจ้งเตือน',
  title VARCHAR(200) NOT NULL COMMENT 'หัวข้อ',
  message TEXT NULL COMMENT 'ข้อความ',
  is_read TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'อ่านแล้ว (1) / ยังไม่อ่าน (0)',
  notify_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาที่ควรแจ้งเตือน',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้าง',
  PRIMARY KEY (notification_id),
  KEY idx_notif_user_read (user_id, is_read, notify_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_notif_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_notif_event FOREIGN KEY (event_id) REFERENCES opportunity_events (event_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='การแจ้งเตือนกำหนดการและข้อความ';

CREATE TABLE contact_messages (
  message_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสข้อความ',
  user_id INT UNSIGNED NOT NULL COMMENT 'ผู้ส่งข้อความ (นักเรียน)',
  opportunity_id INT UNSIGNED NULL COMMENT 'โครงการที่สอบถาม (ถ้ามี)',
  subject VARCHAR(200) NOT NULL COMMENT 'หัวข้อ',
  message_body TEXT NOT NULL COMMENT 'เนื้อหาข้อความ',
  status ENUM('pending','replied','closed') NOT NULL DEFAULT 'pending' COMMENT 'สถานะข้อความ: รอตอบ / ตอบแล้ว / ปิดเรื่อง',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่ส่ง',
  PRIMARY KEY (message_id),
  KEY idx_cmsg_status (status, created_at),
  CONSTRAINT fk_cmsg_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cmsg_opp FOREIGN KEY (opportunity_id) REFERENCES opportunities (opportunity_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ข้อความสอบถามจากนักเรียน';

CREATE TABLE contact_replies (
  reply_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสการตอบกลับ',
  message_id INT UNSIGNED NOT NULL COMMENT 'ข้อความที่ตอบ',
  admin_user_id INT UNSIGNED NULL COMMENT 'ผู้ดูแลที่ตอบ',
  reply_body TEXT NOT NULL COMMENT 'เนื้อหาตอบกลับ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่ตอบ',
  PRIMARY KEY (reply_id),
  KEY idx_creply_msg (message_id),
  CONSTRAINT fk_creply_msg FOREIGN KEY (message_id) REFERENCES contact_messages (message_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_creply_admin FOREIGN KEY (admin_user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ข้อความตอบกลับจากผู้ดูแลระบบ';

CREATE TABLE audit_logs (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสบันทึก',
  user_id INT UNSIGNED NULL COMMENT 'ผู้ดำเนินการ',
  action_type ENUM('create','update','delete','login','status_change') NOT NULL COMMENT 'ชนิดการกระทำ',
  table_name VARCHAR(64) NOT NULL COMMENT 'ตารางที่ถูกกระทำ',
  record_id VARCHAR(64) NULL COMMENT 'รหัสแถวที่ถูกกระทำ',
  old_values JSON NULL COMMENT 'ค่าก่อนเปลี่ยน',
  new_values JSON NULL COMMENT 'ค่าหลังเปลี่ยน',
  ip_address VARCHAR(45) NULL COMMENT 'IP ของผู้ดำเนินการ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันเวลาที่เกิดเหตุการณ์',
  PRIMARY KEY (audit_id),
  KEY idx_audit_table_record (table_name, record_id),
  KEY idx_audit_user (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='ประวัติการจัดการข้อมูลสำคัญ';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- ข้อมูลอ้างอิงเริ่มต้น (Seed) : ประเภท/หมวดหมู่และแผนการเรียน
-- =====================================================================

INSERT INTO opportunity_types (opportunity_type_id, parent_type_id, type_code, type_name, description) VALUES
 (1, NULL, 'scholarship',  'ทุนการศึกษา', 'ทุนจากมหาวิทยาลัย หน่วยงานรัฐ และเอกชน'),
 (2, NULL, 'competition',  'การสอบแข่งขัน', 'การแข่งขันและการสอบวัดความรู้/ความถนัด'),
 (3, NULL, 'admission',    'การศึกษาต่อ/TCAS', 'โครงการรับสมัครเข้ามหาวิทยาลัย'),
 (4, 3,    'tcas1',        'TCAS รอบ 1 Portfolio', NULL),
 (5, 3,    'tcas2',        'TCAS รอบ 2 Quota', NULL),
 (6, 3,    'tcas3',        'TCAS รอบ 3 Admission', NULL),
 (7, 3,    'quota_area',   'โควตาพื้นที่/ภูมิภาค', NULL),
 (8, 3,    'quota_school', 'โควตาโรงเรียนเครือข่าย', NULL),
 (9, 3,    'direct',       'โครงการรับตรงเฉพาะคณะ/สาขา', NULL);

INSERT INTO study_plans (plan_name) VALUES
 ('วิทยาศาสตร์-คณิตศาสตร์'), ('ศิลป์-คำนวณ'), ('ศิลป์-ภาษา'),
 ('ศิลป์-สังคม'), ('อาชีวศึกษา (ปวช.)'), ('อื่น ๆ');

-- ---------------------------------------------------------------------
-- วิธีสร้างบัญชี Admin คนแรก (ทำใน PHP หรือ CLI):
--   php -r "echo password_hash('รหัสผ่านของคุณ', PASSWORD_DEFAULT);"
-- แล้วนำค่า hash ที่ได้มาใส่:
--   INSERT INTO users (username, email, password_hash, role)
--   VALUES ('admin', 'admin@example.com', '<hash>', 'admin');
-- =====================================================================
