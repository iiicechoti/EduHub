<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<div class="navbar">
    <div class="logo">🎓 EduHub Admin</div>
    <div class="center-menu">
        <a href="admin_dashboard.php" class="<?= $cur == 'admin_dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="admin_students.php" class="<?= $cur == 'admin_students.php' ? 'active' : '' ?>">นักเรียน</a>
        <a href="admin_schools.php" class="<?= $cur == 'admin_schools.php' ? 'active' : '' ?>">โรงเรียน</a>
        <a href="admin_categories.php" class="<?= $cur == 'admin_categories.php' ? 'active' : '' ?>">หมวดหมู่</a>
        <a href="admin_universities.php" class="<?= $cur == 'admin_universities.php' ? 'active' : '' ?>">มหาวิทยาลัย/คณะ/สาขา</a>
        <a href="admin_opportunities.php" class="<?= $cur == 'admin_opportunities.php' ? 'active' : '' ?>">โอกาสการศึกษา</a>
        <a href="admin_criteria.php" class="<?= $cur == 'admin_criteria.php' ? 'active' : '' ?>">เกณฑ์คุณสมบัติ</a>
        <a href="admin_events.php" class="<?= $cur == 'admin_events.php' ? 'active' : '' ?>">กำหนดการ</a>
        <a href="admin_messages.php" class="<?= $cur == 'admin_messages.php' ? 'active' : '' ?>">ข้อความสอบถาม</a>
    </div>
    <div class="right-menu">
        <span class="greeting">👋 สวัสดี, <?= htmlspecialchars($_SESSION['name']) ?></span>
        <a href="logout.php" class="btn-logout">ออกจากระบบ</a>
    </div>
</div>
