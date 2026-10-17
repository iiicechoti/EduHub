-- สร้างบัญชีผู้ดูแลระบบ (รันหลัง 01_schema.sql)
-- ชื่อผู้ใช้: admin   รหัสผ่าน: Admin@1234   (เก็บเป็น bcrypt hash ใช้กับ password_verify ของ PHP)
USE eduhub;
INSERT INTO users (username, email, password_hash, role, status)
VALUES ('admin', 'admin@example.com', '$2y$10$X3N.5FTjgWtztTjIb/jGXu7UQPQb0t9LkbIxlmKrM1vW2YsXFxawK', 'admin', 'active');
