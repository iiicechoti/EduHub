
<?php
session_start();
require_once("config.php");

// ==================================================
// 1. ตรวจสอบสิทธิ์แอดมิน
// ==================================================
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

// ==================================================
// 2. ระบบค้นหาโรงเรียน AJAX ภายในไฟล์เดียวกัน
// ==================================================
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

// ==================================================
// 3. รับรหัสนักเรียน
// ==================================================
$id = (int)($_GET['id'] ?? $_POST['student_id'] ?? 0);

if ($id <= 0) {
    header("Location: admin_students.php");
    exit();
}

$error = '';

// ==================================================
// 4. ดึงข้อมูลนักเรียน
// ==================================================
$student = fetch_one($conn, "
    SELECT
        s.*,
        u.username,
        sc.school_name,
        (
            SELECT e.gpax
            FROM education_records e
            WHERE e.student_id = s.student_id
              AND e.gpax IS NOT NULL
            ORDER BY e.academic_year DESC
            LIMIT 1
        ) AS gpax
    FROM students s
    INNER JOIN users u ON u.user_id = s.user_id
    LEFT JOIN schools sc ON sc.school_id = s.school_id
    WHERE s.student_id = $id
    LIMIT 1
");

if (!$student) {
    echo "<script>
        alert('ไม่พบข้อมูลนักเรียน');
        window.location.href='admin_students.php';
    </script>";
    exit();
}

// ==================================================
// 5. ดึงแผนการเรียน
// ==================================================
$plans = fetch_all(
    $conn,
    "SELECT study_plan_id, plan_name
     FROM study_plans
     ORDER BY study_plan_id"
);

// ==================================================
// 6. เตรียมข้อมูลสำหรับฟอร์ม
// ==================================================
$form = [
    'student_code' => $student['student_code'] ?? '',
    'first_name' => $student['first_name'] ?? '',
    'last_name' => $student['last_name'] ?? '',
    'school_id' => $student['school_id'] ?? 0,
    'study_plan_id' => $student['study_plan_id'] ?? 0,
    'grade' => $student['current_grade_level'] ?? 'M1',
    'home_province' => $student['home_province'] ?? '',
    'income' => $student['family_income_per_year'] ?? '',
    'gpax' => $student['gpax'] ?? ''
];

// ==================================================
// 7. บันทึกข้อมูลเมื่อกดปุ่ม
// ==================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($form as $key => $value) {
        if (isset($_POST[$key])) {
            $form[$key] = trim((string)$_POST[$key]);
        }
    }

    $code = $form['student_code'];
    $fname = $form['first_name'];
    $lname = $form['last_name'];
    $school = (int)$form['school_id'];
    $plan = (int)$form['study_plan_id'];
    $grade = $form['grade'];
    $province = $form['home_province'];
    $incomeText = $form['income'];
    $gpaxText = $form['gpax'];
    $newPassword = $_POST['password'] ?? '';

    $validGrades = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'GED'];

    if ($code === '' || $fname === '' || $lname === '') {
        $error = 'กรุณากรอกรหัสนักเรียน ชื่อ และนามสกุล';

    } elseif (!in_array($grade, $validGrades, true)) {
        $error = 'ระดับชั้นไม่ถูกต้อง';

    } elseif (
        $gpaxText !== '' &&
        (!is_numeric($gpaxText) ||
        (float)$gpaxText < 0 ||
        (float)$gpaxText > 4)
    ) {
        $error = 'GPAX ต้องอยู่ระหว่าง 0.00 - 4.00';

    } elseif (
        $incomeText !== '' &&
        (!is_numeric($incomeText) || (float)$incomeText < 0)
    ) {
        $error = 'รายได้ครอบครัวต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป';

    } elseif ($newPassword !== '' && strlen($newPassword) > 4096) {
        $error = 'รหัสผ่านยาวเกินไป';
    }

    // ตรวจสอบโรงเรียน
    if ($error === '' && $school > 0) {
        $schoolCheck = fetch_one(
            $conn,
            "SELECT school_id
             FROM schools
             WHERE school_id = $school
             LIMIT 1"
        );

        if (!$schoolCheck) {
            $error = 'ไม่พบโรงเรียนที่เลือก กรุณาค้นหาและเลือกใหม่';
        }
    }

    // ตรวจสอบแผนการเรียน
    if ($error === '' && $plan > 0) {
        $planCheck = fetch_one(
            $conn,
            "SELECT study_plan_id
             FROM study_plans
             WHERE study_plan_id = $plan
             LIMIT 1"
        );

        if (!$planCheck) {
            $error = 'ไม่พบแผนการเรียนที่เลือก';
        }
    }

    // ตรวจสอบรหัสนักเรียนซ้ำ
    if ($error === '') {
        $codeEsc = esc($conn, $code);
        $uid = (int)$student['user_id'];

        $duplicateUser = fetch_one($conn, "
            SELECT user_id
            FROM users
            WHERE username = '$codeEsc'
              AND user_id <> $uid
            LIMIT 1
        ");

        $duplicateStudent = fetch_one($conn, "
            SELECT student_id
            FROM students
            WHERE student_code = '$codeEsc'
              AND student_id <> $id
            LIMIT 1
        ");

        if ($duplicateUser || $duplicateStudent) {
            $error = 'รหัสนักเรียนนี้มีอยู่ในระบบแล้ว';
        }
    }

    // ==================================================
    // 8. บันทึกลงฐานข้อมูล
    // ==================================================
    if ($error === '') {
        $uid = (int)$student['user_id'];

        $codeEsc = esc($conn, $code);
        $fnameEsc = esc($conn, $fname);
        $lnameEsc = esc($conn, $lname);
        $provinceEsc = esc($conn, $province);
        $gradeEsc = esc($conn, $grade);

        $schoolSQL = $school > 0 ? (string)$school : 'NULL';
        $planSQL = $plan > 0 ? (string)$plan : 'NULL';

        $incomeSQL = $incomeText !== ''
            ? (string)(float)$incomeText
            : 'NULL';

        $gpax = $gpaxText !== '' ? (float)$gpaxText : null;

        mysqli_begin_transaction($conn);

        try {
            // 8.1 แก้ไขข้อมูลนักเรียน
            $sql = "
                UPDATE students SET
                    student_code = '$codeEsc',
                    first_name = '$fnameEsc',
                    last_name = '$lnameEsc',
                    school_id = $schoolSQL,
                    study_plan_id = $planSQL,
                    home_province = '$provinceEsc',
                    current_grade_level = '$gradeEsc',
                    family_income_per_year = $incomeSQL
                WHERE student_id = $id
            ";

            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }

            // 8.2 เปลี่ยนชื่อผู้ใช้ให้ตรงกับรหัสนักเรียน
            $sql = "
                UPDATE users
                SET username = '$codeEsc'
                WHERE user_id = $uid
            ";

            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }

            // 8.3 เปลี่ยนรหัสผ่านเมื่อมีการกรอกใหม่
            if ($newPassword !== '') {
                $hash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $hashEsc = esc($conn, $hash);

                $sql = "
                    UPDATE users
                    SET password_hash = '$hashEsc'
                    WHERE user_id = $uid
                ";

                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            // 8.4 จัดการประวัติ GED
            if ($grade === 'GED') {
                $record = fetch_one($conn, "
                    SELECT student_id
                    FROM education_records
                    WHERE student_id = $id
                      AND education_type = 'ged'
                    LIMIT 1
                ");

                if ($record) {
                    $sql = "
                        UPDATE education_records
                        SET certificate_name = 'GED'
                        WHERE student_id = $id
                          AND education_type = 'ged'
                        LIMIT 1
                    ";
                } else {
                    $sql = "
                        INSERT INTO education_records
                            (student_id, education_type, certificate_name)
                        VALUES ($id, 'ged', 'GED')
                    ";
                }

                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(mysqli_error($conn));
                }

            } elseif ($gpax !== null) {
                // 8.5 อัปเดต GPAX ของโรงเรียนไทย
                $record = fetch_one($conn, "
                    SELECT student_id
                    FROM education_records
                    WHERE student_id = $id
                      AND education_type = 'thai_school'
                    ORDER BY academic_year DESC
                    LIMIT 1
                ");

                if ($record) {
                    $sql = "
                        UPDATE education_records
                        SET school_id = $schoolSQL,
                            grade_level = '$gradeEsc',
                            gpax = $gpax
                        WHERE student_id = $id
                          AND education_type = 'thai_school'
                        ORDER BY academic_year DESC
                        LIMIT 1
                    ";
                } else {
                    $year = (int)date('Y') + 543;

                    $sql = "
                        INSERT INTO education_records
                            (student_id, education_type, school_id,
                             academic_year, grade_level, gpax)
                        VALUES
                            ($id, 'thai_school', $schoolSQL,
                             $year, '$gradeEsc', $gpax)
                    ";
                }

                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            echo "<script>
                alert('แก้ไขข้อมูลนักเรียนเรียบร้อยแล้ว');
                window.location.href = 'admin_students.php';
            </script>";
            exit();

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('EduHub edit student: ' . $e->getMessage());

            $error = 'บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบโครงสร้างฐานข้อมูล';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลนักเรียน | EduHub</title>

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
    </style>
</head>

<body>

<?php include("navbar_admin.php"); ?>

<div class="container">
    <h2>แก้ไขข้อมูลนักเรียน</h2>

    <?php if ($error !== '') { ?>
        <div class="card" style="color:#ff4757;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php } ?>

    <form method="POST" class="card">
        <input type="hidden" name="student_id" value="<?= $id ?>">

        <h3>ข้อมูลส่วนตัว</h3>

        <div class="form-row">
            <input
                type="text"
                name="student_code"
                placeholder="รหัสนักเรียน *"
                required
                value="<?= htmlspecialchars($form['student_code'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <input
                type="text"
                name="first_name"
                placeholder="ชื่อ *"
                required
                value="<?= htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <input
                type="text"
                name="last_name"
                placeholder="นามสกุล *"
                required
                value="<?= htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <div class="form-row">

            <!-- ช่องค้นหาโรงเรียน -->
            <div class="school-autocomplete">
                <input
                    type="text"
                    id="school_search"
                    placeholder="พิมพ์ชื่อโรงเรียนเพื่อค้นหา..."
                    autocomplete="off"
                    value="<?= htmlspecialchars($form['school_id'] ? ($student['school_name'] ?? '') : '', ENT_QUOTES, 'UTF-8') ?>"
                >

                <input
                    type="hidden"
                    name="school_id"
                    id="school_id"
                    value="<?= (int)$form['school_id'] ?>"
                >

                <div id="school_results" class="school-results"></div>
            </div>

            <select name="grade">
                <?php foreach (['M1','M2','M3','M4','M5','M6','GED'] as $g) { ?>
                    <option
                        value="<?= $g ?>"
                        <?= $form['grade'] === $g ? 'selected' : '' ?>
                    >
                        <?= $g === 'GED'
                            ? 'วุฒิ GED'
                            : htmlspecialchars(grade_label($g), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php } ?>
            </select>

            <select name="study_plan_id">
                <option value="0">-- แผนการเรียน --</option>

                <?php foreach ($plans as $p) { ?>
                    <option
                        value="<?= (int)$p['study_plan_id'] ?>"
                        <?= (int)$form['study_plan_id'] === (int)$p['study_plan_id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($p['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="form-row">
            <input
                type="text"
                name="home_province"
                placeholder="จังหวัดภูมิลำเนา"
                value="<?= htmlspecialchars($form['home_province'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <input
                type="number"
                name="gpax"
                step="0.01"
                min="0"
                max="4"
                placeholder="GPAX ล่าสุด"
                value="<?= htmlspecialchars((string)$form['gpax'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <input
                type="number"
                name="income"
                step="1"
                min="0"
                placeholder="รายได้ครอบครัวต่อปี"
                value="<?= htmlspecialchars((string)$form['income'], ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <h3>เปลี่ยนรหัสผ่าน</h3>

        <div class="form-row">
            <input
                type="password"
                name="password"
                placeholder="รหัสผ่านใหม่ (เว้นว่างหากไม่เปลี่ยน)"
                autocomplete="new-password"
            >
        </div>

        <p class="muted">
            ชื่อผู้ใช้จะตรงกับรหัสนักเรียนโดยอัตโนมัติ
            หากไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นช่องรหัสผ่านใหม่ว่างไว้
        </p>

        <div class="form-row">
            <button type="submit" class="btn">บันทึกการแก้ไข</button>

            <a
                href="admin_students.php"
                class="btn btn-outline"
                style="text-decoration:none;"
            >
                ยกเลิก
            </a>
        </div>
    </form>
</div>

<script>
const schoolInput = document.getElementById('school_search');
const schoolIdInput = document.getElementById('school_id');
const schoolResults = document.getElementById('school_results');

let schoolTimer = null;

schoolInput.addEventListener('input', function () {
    const keyword = this.value.trim();

    // เมื่อแก้ข้อความ ต้องเลือกโรงเรียนจากรายการอีกครั้ง
    schoolIdInput.value = '';
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
                throw new Error('Search failed');
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
