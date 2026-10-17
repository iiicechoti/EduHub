
<?php
session_start();
require_once("config.php");

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'search_schools'
) {
    header('Content-Type: application/json; charset=utf-8');

    $keyword = trim($_GET['q'] ?? '');

    if ($keyword === '') {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT school_id, school_name
         FROM schools
         WHERE school_name LIKE ?
         ORDER BY school_name
         LIMIT 20"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([]);
        exit();
    }

    $search = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 's', $search);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $schoolsFound = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $schoolsFound[] = $row;
    }

    mysqli_stmt_close($stmt);

    echo json_encode($schoolsFound, JSON_UNESCAPED_UNICODE);
    exit();
}

if (($_POST['type'] ?? '') === 'add') {

    $codeRaw = trim($_POST['student_code'] ?? '');
    $fnameRaw = trim($_POST['first_name'] ?? '');
    $lnameRaw = trim($_POST['last_name'] ?? '');
    $school = (int)($_POST['school_id'] ?? 0);
    $plan = (int)($_POST['study_plan_id'] ?? 0);
    $gradeRaw = $_POST['grade'] ?? 'M1';
    $provinceRaw = trim($_POST['home_province'] ?? '');
    $incomeRaw = trim($_POST['income'] ?? '');
    $gpaxRaw = trim($_POST['gpax'] ?? '');
    $password = $_POST['password'] ?? '';

    $validGrades = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'GED'];

    if (
        $codeRaw === '' ||
        $fnameRaw === '' ||
        $lnameRaw === '' ||
        $password === ''
    ) {
        echo "<script>alert('กรุณากรอกรหัสนักเรียน ชื่อ นามสกุล และรหัสผ่านให้ครบ');history.back();</script>";
        exit();
    }

    if (!in_array($gradeRaw, $validGrades, true)) {
        echo "<script>alert('ระดับชั้นไม่ถูกต้อง');history.back();</script>";
        exit();
    }

    if (
        $gpaxRaw !== '' &&
        (!is_numeric($gpaxRaw) ||
         (float)$gpaxRaw < 0 ||
         (float)$gpaxRaw > 4)
    ) {
        echo "<script>alert('GPAX ต้องอยู่ระหว่าง 0.00 - 4.00');history.back();</script>";
        exit();
    }

    if (
        $incomeRaw !== '' &&
        (!is_numeric($incomeRaw) || (float)$incomeRaw < 0)
    ) {
        echo "<script>alert('รายได้ครอบครัวต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');history.back();</script>";
        exit();
    }

    if ($school > 0) {
        $schoolCheck = fetch_one(
            $conn,
            "SELECT school_id FROM schools WHERE school_id = $school LIMIT 1"
        );

        if (!$schoolCheck) {
            echo "<script>alert('ไม่พบโรงเรียนที่เลือก กรุณาเลือกโรงเรียนจากรายการ');history.back();</script>";
            exit();
        }
    }

    if ($plan > 0) {
        $planCheck = fetch_one(
            $conn,
            "SELECT study_plan_id FROM study_plans WHERE study_plan_id = $plan LIMIT 1"
        );

        if (!$planCheck) {
            echo "<script>alert('ไม่พบแผนการเรียนที่เลือก');history.back();</script>";
            exit();
        }
    }

    $code = esc($conn, $codeRaw);
    $fname = esc($conn, $fnameRaw);
    $lname = esc($conn, $lnameRaw);
    $grade = esc($conn, $gradeRaw);
    $province = esc($conn, $provinceRaw);

    $schoolSQL = $school > 0 ? (string)$school : 'NULL';
    $planSQL = $plan > 0 ? (string)$plan : 'NULL';

    $incomeSQL = $incomeRaw !== ''
        ? (string)(float)$incomeRaw
        : 'NULL';

    $gpax = $gpaxRaw !== '' ? (float)$gpaxRaw : null;

    $dup = fetch_one(
        $conn,
        "SELECT user_id FROM users WHERE username = '$code' LIMIT 1"
    );

    $dup2 = fetch_one(
        $conn,
        "SELECT student_id FROM students WHERE student_code = '$code' LIMIT 1"
    );

    if ($dup || $dup2) {
        echo "<script>alert('รหัสนักเรียนนี้มีอยู่ในระบบแล้ว');history.back();</script>";
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hashEsc = esc($conn, $hash);

        $ok = mysqli_query(
            $conn,
            "INSERT INTO users (username, password_hash, role)
             VALUES ('$code', '$hashEsc', 'student')"
        );

        if (!$ok) {
            throw new Exception(mysqli_error($conn));
        }

        $userId = mysqli_insert_id($conn);

        $ok = mysqli_query($conn, "
            INSERT INTO students (
                user_id, student_code, school_id, study_plan_id,
                first_name, last_name, home_province,
                current_grade_level, family_income_per_year
            ) VALUES (
                $userId, '$code', $schoolSQL, $planSQL,
                '$fname', '$lname', '$province',
                '$grade', $incomeSQL
            )
        ");

        if (!$ok) {
            throw new Exception(mysqli_error($conn));
        }

        $studentId = mysqli_insert_id($conn);

        if ($gradeRaw === 'GED') {
            $ok = mysqli_query($conn, "
                INSERT INTO education_records
                    (student_id, education_type, certificate_name)
                VALUES ($studentId, 'ged', 'GED')
            ");

            if (!$ok) {
                throw new Exception(mysqli_error($conn));
            }

        } elseif ($gpax !== null) {
            $year = (int)date('Y') + 543;

            $ok = mysqli_query($conn, "
                INSERT INTO education_records (
                    student_id, education_type, school_id,
                    academic_year, grade_level, gpax
                ) VALUES (
                    $studentId, 'thai_school', $schoolSQL,
                    $year, '$grade', $gpax
                )
            ");

            if (!$ok) {
                throw new Exception(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);

        echo "<script>
            alert('เพิ่มนักเรียนเรียบร้อยแล้ว');
            window.location.href='admin_students.php';
        </script>";
        exit();

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('EduHub add student: ' . $e->getMessage());

        echo "<script>alert('เพิ่มข้อมูลไม่สำเร็จ กรุณาตรวจสอบข้อมูลและฐานข้อมูล');history.back();</script>";
        exit();
    }
}

if (($_POST['type'] ?? '') === 'delete') {
    $sid = (int)($_POST['student_id'] ?? 0);

    $s = fetch_one(
        $conn,
        "SELECT user_id FROM students WHERE student_id = $sid LIMIT 1"
    );

    if ($s) {
        $uid = (int)$s['user_id'];
        $ok = mysqli_query($conn, "DELETE FROM users WHERE user_id = $uid");

        if (!$ok) {
            error_log('EduHub delete student: ' . mysqli_error($conn));
            echo "<script>alert('ลบข้อมูลไม่สำเร็จ กรุณาตรวจสอบความสัมพันธ์ของข้อมูล');window.location.href='admin_students.php';</script>";
            exit();
        }
    }

    echo "<script>alert('ลบนักเรียนเรียบร้อยแล้ว');window.location.href='admin_students.php';</script>";
    exit();
}

$q = trim($_GET['q'] ?? '');
$where = '1=1';

if ($q !== '') {
    $k = esc($conn, $q);
    $where = "(
        s.student_code LIKE '%$k%'
        OR s.first_name LIKE '%$k%'
        OR s.last_name LIKE '%$k%'
    )";
}

$students = fetch_all($conn, "
    SELECT
        s.*,
        sc.school_name,
        sp.plan_name,
        (
            SELECT e.gpax
            FROM education_records e
            WHERE e.student_id = s.student_id
              AND e.gpax IS NOT NULL
            ORDER BY e.academic_year DESC
            LIMIT 1
        ) AS gpax
    FROM students s
    LEFT JOIN schools sc ON sc.school_id = s.school_id
    LEFT JOIN study_plans sp ON sp.study_plan_id = s.study_plan_id
    WHERE $where
    ORDER BY s.student_code
");

$plans = fetch_all(
    $conn,
    "SELECT study_plan_id, plan_name FROM study_plans ORDER BY study_plan_id"
);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>จัดการนักเรียน | EduHub</title>

    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        .school-autocomplete {
            position: relative;
            flex: 1;
            min-width: 180px;
        }

        .school-autocomplete input[type="text"] {
            width: 100%;
            box-sizing: border-box;
        }

        .school-results {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 240px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0,0,0,.12);
            z-index: 9999;
        }

        .school-option,
        .school-empty {
            padding: 10px 12px;
            color: #222;
        }

        .school-option {
            cursor: pointer;
        }

        .school-option:hover {
            background: #f0f4ff;
        }

        .student-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .student-actions form {
            margin: 0;
        }

        .student-actions .btn {
            padding: 4px 12px;
            text-decoration: none;
            white-space: nowrap;
        }

        .delete-btn {
            color: #ff4757;
            border-color: #ff4757;
        }
    </style>
</head>

<body>
<?php include("navbar_admin.php"); ?>

<div class="container">
    <h2>จัดการนักเรียน</h2>

    <form method="POST" class="card" id="addStudentForm">
        <input type="hidden" name="type" value="add">

        <h3>เพิ่มนักเรียน</h3>

        <div class="form-row">
            <input type="text" name="student_code" placeholder="รหัสนักเรียน *" required>
            <input type="text" name="first_name" placeholder="ชื่อ *" required>
            <input type="text" name="last_name" placeholder="นามสกุล *" required>
        </div>

        <div class="form-row">

            <div class="school-autocomplete">
                <input
                    type="text"
                    id="school_search"
                    placeholder="พิมพ์ชื่อโรงเรียน เช่น วัดราช..."
                    autocomplete="off"
                >

                <input
                    type="hidden"
                    name="school_id"
                    id="school_id"
                    value="0"
                >

                <div id="school_results" class="school-results"></div>
            </div>

            <select name="grade">
                <?php foreach (['M1','M2','M3','M4','M5','M6','GED'] as $g) { ?>
                    <option value="<?= $g ?>">
                        <?= $g === 'GED' ? 'วุฒิ GED' : htmlspecialchars(grade_label($g), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php } ?>
            </select>

            <select name="study_plan_id">
                <option value="0">-- แผนการเรียน --</option>

                <?php foreach ($plans as $p) { ?>
                    <option value="<?= (int)$p['study_plan_id'] ?>">
                        <?= htmlspecialchars($p['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="form-row">
            <input type="text" name="home_province" placeholder="จังหวัดภูมิลำเนา">

            <input
                type="number"
                step="0.01"
                min="0"
                max="4"
                name="gpax"
                placeholder="GPAX ล่าสุด (ถ้ามี)"
            >

            <input
                type="number"
                step="1"
                min="0"
                name="income"
                placeholder="รายได้ครอบครัวต่อปี (ถ้ามี)"
            >
        </div>

        <div class="form-row">
            <input
                type="password"
                name="password"
                placeholder="รหัสผ่านเริ่มต้นของนักเรียน *"
                autocomplete="new-password"
                required
            >

            <button type="submit" class="btn">เพิ่มนักเรียน</button>
        </div>

        <div class="muted">
            นักเรียนล็อกอินด้วยรหัสนักเรียนและรหัสผ่านที่ตั้งไว้
            ระบบจัดเก็บรหัสผ่านในรูปแบบ hash
        </div>
    </form>

    <form method="GET" class="card">
        <div class="form-row">
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="ค้นหารหัสนักเรียน หรือชื่อ"
            >

            <button type="submit" class="btn">ค้นหา</button>

            <a href="admin_students.php" class="btn btn-outline">
                ล้าง
            </a>
        </div>
    </form>

    <div class="card">
        <h3>รายชื่อนักเรียน (<?= count($students) ?> คน)</h3>

        <table>
            <tr>
                <th>รหัส</th>
                <th>ชื่อ-นามสกุล</th>
                <th>โรงเรียน</th>
                <th>ชั้น</th>
                <th>แผนการเรียน</th>
                <th>GPAX</th>
                <th>จัดการ</th>
            </tr>

            <?php if (count($students) === 0) { ?>
                <tr>
                    <td colspan="7" class="muted">ไม่พบข้อมูลนักเรียน</td>
                </tr>
            <?php } ?>

            <?php foreach ($students as $s) { ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($s['student_code'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $s['first_name'] . ' ' . $s['last_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($s['school_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= !empty($s['current_grade_level'])
                            ? htmlspecialchars(grade_label($s['current_grade_level']), ENT_QUOTES, 'UTF-8')
                            : '-' ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($s['plan_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= $s['gpax'] !== null
                            ? number_format((float)$s['gpax'], 2)
                            : '-' ?>
                    </td>

                    <td>
                        <div class="student-actions">
                            <a
                                href="admin_student_edit.php?id=<?= (int)$s['student_id'] ?>"
                                class="btn"
                            >
                                แก้ไข
                            </a>

                            <form
                                method="POST"
                                onsubmit="return confirm('ยืนยันการลบนักเรียนรหัส <?= htmlspecialchars($s['student_code'], ENT_QUOTES, 'UTF-8') ?>? ข้อมูลที่เกี่ยวข้องอาจถูกลบด้วย');"
                            >
                                <input type="hidden" name="type" value="delete">

                                <input
                                    type="hidden"
                                    name="student_id"
                                    value="<?= (int)$s['student_id'] ?>"
                                >

                                <button type="submit" class="btn btn-outline delete-btn">
                                    ลบ
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>

<script>
const schoolInput = document.getElementById('school_search');
const schoolIdInput = document.getElementById('school_id');
const schoolResults = document.getElementById('school_results');

let schoolTimer = null;

schoolInput.addEventListener('input', function () {
    const keyword = this.value.trim();

    schoolIdInput.value = '0';
    clearTimeout(schoolTimer);

    if (keyword === '') {
        schoolResults.style.display = 'none';
        return;
    }

    schoolTimer = setTimeout(async function () {
        try {
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.set('action', 'search_schools');
            url.searchParams.set('q', keyword);

            const response = await fetch(url.toString());

            if (!response.ok) {
                throw new Error('ค้นหาโรงเรียนไม่สำเร็จ');
            }

            const schools = await response.json();
            schoolResults.replaceChildren();

            if (schools.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'school-empty';
                empty.textContent = 'ไม่พบโรงเรียนที่ตรงกัน';
                schoolResults.appendChild(empty);
            } else {
                schools.forEach(function (school) {
                    const option = document.createElement('div');
                    option.className = 'school-option';
                    option.textContent = school.school_name;

                    option.addEventListener('click', function () {
                        schoolInput.value = school.school_name;
                        schoolIdInput.value = school.school_id;
                        schoolResults.style.display = 'none';
                    });

                    schoolResults.appendChild(option);
                });
            }

            schoolResults.style.display = 'block';

        } catch (error) {
            schoolResults.replaceChildren();

            const message = document.createElement('div');
            message.className = 'school-empty';
            message.textContent = 'ค้นหาไม่สำเร็จ กรุณาลองใหม่';
            schoolResults.appendChild(message);

            schoolResults.style.display = 'block';
        }
    }, 250);
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('.school-autocomplete')) {
        schoolResults.style.display = 'none';
    }
});
</script>

</body>
</html>
