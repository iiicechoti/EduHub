<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<div class="navbar">
    <div class="logo">🎓 EduHub</div>
    <div class="center-menu">
        <a href="user_dashboard.php" class="<?= $cur == 'user_dashboard.php' ? 'active' : '' ?>">หน้าหลัก</a>
        <a href="user_search.php" class="<?= $cur == 'user_search.php' ? 'active' : '' ?>">ค้นหาโอกาส</a>
        <a href="user_eligibility.php" class="<?= $cur == 'user_eligibility.php' ? 'active' : '' ?>">ตรวจสอบคุณสมบัติ</a>
        <a href="user_bookmarks.php" class="<?= $cur == 'user_bookmarks.php' ? 'active' : '' ?>">รายการที่บันทึก</a>
        <a href="user_calendar.php" class="<?= $cur == 'user_calendar.php' ? 'active' : '' ?>">ปฏิทิน</a>
        <a href="user_notifications.php" class="<?= $cur == 'user_notifications.php' ? 'active' : '' ?>">แจ้งเตือน</a>
        <a href="user_profile.php" class="<?= $cur == 'user_profile.php' ? 'active' : '' ?>">โปรไฟล์</a>
        <a href="user_contact.php" class="<?= $cur == 'user_contact.php' ? 'active' : '' ?>">ติดต่อผู้ดูแล</a>
        <a href="user_about.php" class="<?= $cur == 'user_about.php' ? 'active' : '' ?>">เกี่ยวกับเรา</a>
    </div>
    <div class="right-menu">
        <span class="greeting">👋 สวัสดี, <?= htmlspecialchars($_SESSION['name']) ?></span>
        <a href="logout.php" class="btn-logout">ออกจากระบบ</a>
    </div>
</div>
