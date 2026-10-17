<?php session_start(); ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | EduHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="login-box">
    <h2>🎓 EduHub</h2>
    <p class="muted">ศูนย์รวมโอกาสทางการศึกษาสำหรับนักเรียนมัธยมศึกษา</p>

    <form method="POST" action="auth.php">
        <input type="text" name="username" placeholder="รหัสนักเรียน" required>
        <input type="password" name="password" placeholder="รหัสผ่าน" required>
        <button type="submit" class="btn">เข้าสู่ระบบ</button>
    </form>
</div>

<?php if (isset($_SESSION['error'])) { ?>
<script>
    Swal.fire({ icon: 'error', text: '<?= $_SESSION['error'] ?>', confirmButtonText: 'ตกลง' });
</script>
<?php unset($_SESSION['error']); } ?>

</body>
</html>
