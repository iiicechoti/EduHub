<?php
// ตรวจสอบคุณสมบัติเบื้องต้น (ผู้รับผิดชอบ: คนที่ 3)
// ใช้: $res = check_eligibility($conn, $student_id, $opp_id);
// คืนค่า array('status' => 'pass'|'fail'|'insufficient', 'reasons' => array(array('level'=>'ok|fail|missing','text'=>'...')), 'criteria_id' => ...)
// กติกา: มี fail แม้ข้อเดียว = fail / ไม่มี fail แต่มี missing = insufficient / นอกนั้น = pass
// ผลนี้เป็นเพียงการประเมินเบื้องต้น ไม่ใช่การรับรองสิทธิ์

function add_reason(&$arr, $level, $text) {
    $arr[] = array('level' => $level, 'text' => $text);
}

function check_eligibility($conn, $student_id, $opp_id) {
    $student_id = (int)$student_id;
    $opp_id = (int)$opp_id;
    $reasons = array();

    $opp = fetch_one($conn, "SELECT * FROM opportunities WHERE opportunity_id = $opp_id");
    if (!$opp) {
        add_reason($reasons, 'missing', 'ไม่พบโครงการ');
        return array('status' => 'insufficient', 'reasons' => $reasons, 'criteria_id' => null);
    }

    // เกณฑ์เวอร์ชันล่าสุดที่ยังใช้งาน
    $c = fetch_one($conn, "SELECT * FROM eligibility_criteria WHERE opportunity_id = $opp_id AND is_active = 1 ORDER BY version_no DESC LIMIT 1");
    if (!$c) {
        add_reason($reasons, 'missing', 'ผู้ดูแลระบบยังไม่ได้บันทึกเกณฑ์ของโครงการนี้ กรุณาตรวจสอบประกาศต้นทาง');
        return array('status' => 'insufficient', 'reasons' => $reasons, 'criteria_id' => null);
    }
    // กันใช้เกณฑ์ผิดปี
    if ($c['academic_year'] != $opp['academic_year']) {
        add_reason($reasons, 'missing', 'เกณฑ์ที่บันทึกเป็นของปีการศึกษา ' . $c['academic_year'] . ' ไม่ตรงกับโครงการปี ' . $opp['academic_year'] . ' กรุณาตรวจสอบประกาศต้นทาง');
        return array('status' => 'insufficient', 'reasons' => $reasons, 'criteria_id' => (int)$c['criteria_id']);
    }
    $cid = (int)$c['criteria_id'];

    $stu = fetch_one($conn, "SELECT * FROM students WHERE student_id = $student_id");
    $recs = fetch_all($conn, "SELECT * FROM education_records WHERE student_id = $student_id ORDER BY academic_year DESC, semester DESC, education_record_id DESC");

    // 1) ประเภทวุฒิ
    if ($c['education_type_required'] != 'any') {
        $types = array('thai_school' => 'โรงเรียนไทย', 'ged' => 'GED', 'other' => 'วุฒิอื่น');
        $have = count($recs) > 0 ? $recs[0]['education_type'] : null;
        if ($have === null) { add_reason($reasons, 'missing', 'ยังไม่มีข้อมูลประเภทวุฒิการศึกษา'); }
        elseif ($have == $c['education_type_required']) { add_reason($reasons, 'ok', 'ประเภทวุฒิตรงเกณฑ์ (' . $types[$have] . ')'); }
        else { add_reason($reasons, 'fail', 'ประเภทวุฒิไม่ตรง: โครงการรับ ' . $types[$c['education_type_required']] . ' แต่ข้อมูลของคุณคือ ' . $types[$have]); }
    }

    // 2) ระดับชั้น
    $grades = array();
    foreach (fetch_all($conn, "SELECT grade_level FROM criteria_grade_levels WHERE criteria_id = $cid") as $g) { $grades[] = $g['grade_level']; }
    if (count($grades) > 0) {
        $list = implode(', ', array_map('grade_label', $grades));
        if (empty($stu['current_grade_level'])) { add_reason($reasons, 'missing', 'ยังไม่มีข้อมูลระดับชั้น (โครงการรับ ' . $list . ')'); }
        elseif (in_array($stu['current_grade_level'], $grades)) { add_reason($reasons, 'ok', 'ระดับชั้นตรงเกณฑ์ (' . grade_label($stu['current_grade_level']) . ')'); }
        else { add_reason($reasons, 'fail', 'ระดับชั้นไม่ตรง: โครงการรับ ' . $list . ' แต่คุณคือ ' . grade_label($stu['current_grade_level'])); }
    }

    // 3) GPAX (ใช้ค่าสะสมล่าสุดตามใบแสดงผลการเรียน ไม่คำนวณเฉลี่ยเอง)
    if ($c['min_gpax'] !== null) {
        $gpax = null;
        foreach ($recs as $r) { if ($r['gpax'] !== null) { $gpax = (float)$r['gpax']; break; } }
        if ($gpax === null) { add_reason($reasons, 'missing', 'ไม่มีข้อมูล GPAX (เช่น วุฒิ GED) โครงการกำหนดขั้นต่ำ ' . number_format($c['min_gpax'], 2) . ' กรุณาตรวจสอบประกาศต้นทาง'); }
        elseif ($gpax + 0.000001 < (float)$c['min_gpax']) { add_reason($reasons, 'fail', 'GPAX ' . number_format($gpax, 2) . ' ต่ำกว่าเกณฑ์ขั้นต่ำ ' . number_format($c['min_gpax'], 2)); }
        else { add_reason($reasons, 'ok', 'GPAX ' . number_format($gpax, 2) . ' ผ่านเกณฑ์ขั้นต่ำ ' . number_format($c['min_gpax'], 2)); }
    }
    if ($c['min_semesters_considered'] !== null) {
        $n = 0;
        foreach ($recs as $r) { if ($r['gpa'] !== null || $r['gpax'] !== null) { $n++; } }
        if ($n < (int)$c['min_semesters_considered']) { add_reason($reasons, 'missing', 'โครงการพิจารณาผลการเรียน ' . $c['min_semesters_considered'] . ' ภาคเรียน แต่มีข้อมูลเพียง ' . $n . ' ภาคเรียน'); }
    }

    // 4) แผนการเรียน
    $plans = fetch_all($conn, "SELECT sp.study_plan_id, sp.plan_name FROM criteria_study_plans cp JOIN study_plans sp ON sp.study_plan_id = cp.study_plan_id WHERE cp.criteria_id = $cid");
    if (count($plans) > 0) {
        $ids = array(); $names = array();
        foreach ($plans as $p) { $ids[] = $p['study_plan_id']; $names[] = $p['plan_name']; }
        if (empty($stu['study_plan_id'])) { add_reason($reasons, 'missing', 'ยังไม่มีข้อมูลแผนการเรียน (โครงการรับ ' . implode(', ', $names) . ')'); }
        elseif (in_array($stu['study_plan_id'], $ids)) { add_reason($reasons, 'ok', 'แผนการเรียนตรงเกณฑ์'); }
        else { add_reason($reasons, 'fail', 'แผนการเรียนไม่ตรง: โครงการรับ ' . implode(', ', $names)); }
    }

    // 5) เกรดรายวิชา
    foreach (fetch_all($conn, "SELECT subject_name, min_gpa FROM criteria_subjects WHERE criteria_id = $cid") as $sub) {
        if ($sub['min_gpa'] === null) { continue; }
        $sn = esc($conn, $sub['subject_name']);
        $v = fetch_one($conn, "SELECT score_value FROM student_scores WHERE student_id = $student_id AND score_type = 'subject_gpa' AND score_name = '$sn' ORDER BY academic_year DESC LIMIT 1");
        if (!$v) { add_reason($reasons, 'missing', 'ไม่มีเกรดวิชา' . $sub['subject_name'] . ' (โครงการกำหนดขั้นต่ำ ' . number_format($sub['min_gpa'], 2) . ')'); }
        elseif ((float)$v['score_value'] + 0.000001 < (float)$sub['min_gpa']) { add_reason($reasons, 'fail', 'เกรดวิชา' . $sub['subject_name'] . ' ' . number_format($v['score_value'], 2) . ' ต่ำกว่าเกณฑ์ ' . number_format($sub['min_gpa'], 2)); }
        else { add_reason($reasons, 'ok', 'เกรดวิชา' . $sub['subject_name'] . ' ' . number_format($v['score_value'], 2) . ' ผ่านเกณฑ์'); }
    }

    // 6) คะแนนสอบขั้นต่ำ
    foreach (fetch_all($conn, "SELECT score_name, min_score FROM opportunity_scores WHERE opportunity_id = $opp_id AND min_score IS NOT NULL") as $sc) {
        $sn = esc($conn, $sc['score_name']);
        $v = fetch_one($conn, "SELECT score_value FROM student_scores WHERE student_id = $student_id AND score_type = 'exam' AND score_name = '$sn' ORDER BY academic_year DESC LIMIT 1");
        if (!$v) { add_reason($reasons, 'missing', 'ขาดคะแนนสอบ ' . $sc['score_name'] . ' (ขั้นต่ำ ' . (float)$sc['min_score'] . ')'); }
        elseif ((float)$v['score_value'] + 0.000001 < (float)$sc['min_score']) { add_reason($reasons, 'fail', 'คะแนน ' . $sc['score_name'] . ' ' . (float)$v['score_value'] . ' ต่ำกว่าขั้นต่ำ ' . (float)$sc['min_score']); }
        else { add_reason($reasons, 'ok', 'คะแนน ' . $sc['score_name'] . ' ' . (float)$v['score_value'] . ' ผ่านขั้นต่ำ ' . (float)$sc['min_score']); }
    }

    // 7) รายได้ครอบครัว
    if ($c['max_family_income'] !== null) {
        if ($stu['family_income_per_year'] === null) { add_reason($reasons, 'missing', 'ไม่มีข้อมูลรายได้ครอบครัว (โครงการกำหนดไม่เกิน ' . number_format($c['max_family_income']) . ' บาท/ปี)'); }
        elseif ((float)$stu['family_income_per_year'] > (float)$c['max_family_income']) { add_reason($reasons, 'fail', 'รายได้ครอบครัวสูงกว่าเกณฑ์ (ไม่เกิน ' . number_format($c['max_family_income']) . ' บาท/ปี)'); }
        else { add_reason($reasons, 'ok', 'รายได้ครอบครัวอยู่ในเกณฑ์'); }
    }

    // 8) จังหวัด / พื้นที่ / โรงเรียน (แต่ละแถวเงื่อนไขเป็น "หรือ")
    if (!empty($c['home_province_required'])) {
        if (empty($stu['home_province'])) { add_reason($reasons, 'missing', 'ไม่มีข้อมูลจังหวัดภูมิลำเนา'); }
        elseif ($stu['home_province'] == $c['home_province_required']) { add_reason($reasons, 'ok', 'จังหวัดภูมิลำเนาตรงเกณฑ์'); }
        else { add_reason($reasons, 'fail', 'โครงการรับเฉพาะภูมิลำเนาจังหวัด' . $c['home_province_required']); }
    }
    $locs = fetch_all($conn, "SELECT * FROM opportunity_locations WHERE opportunity_id = $opp_id");
    if (count($locs) > 0) {
        $matched = false; $unchecked = false; $provs = array();
        foreach ($locs as $l) {
            if ($l['condition_type'] == 'province') { $provs[] = $l['province']; if ($l['province'] == $stu['home_province']) { $matched = true; } }
            elseif ($l['condition_type'] == 'school') { if ($l['school_id'] !== null && $l['school_id'] == $stu['school_id']) { $matched = true; } }
            else { $unchecked = true; } // ภาค/อำเภอ ระบบยังตรวจอัตโนมัติไม่ได้
        }
        if ($matched) { add_reason($reasons, 'ok', 'เงื่อนไขพื้นที่/โรงเรียนตรงเกณฑ์'); }
        elseif ($unchecked) { add_reason($reasons, 'missing', 'โครงการมีเงื่อนไขพื้นที่ระดับภาค/อำเภอ ระบบตรวจอัตโนมัติไม่ได้ กรุณาตรวจประกาศต้นทาง'); }
        elseif (empty($stu['home_province']) && empty($stu['school_id'])) { add_reason($reasons, 'missing', 'ไม่มีข้อมูลจังหวัดหรือโรงเรียนสำหรับตรวจเงื่อนไขพื้นที่'); }
        else { add_reason($reasons, 'fail', 'ไม่ตรงเงื่อนไขพื้นที่/โรงเรียน' . (count($provs) > 0 ? ' (โครงการรับจังหวัด ' . implode(', ', $provs) . ')' : '')); }
    }

    // 9) เงื่อนไขพิเศษที่ระบบตรวจไม่ได้
    if (!empty(trim((string)$c['other_conditions']))) {
        add_reason($reasons, 'missing', 'เงื่อนไขอื่นที่ต้องตรวจเอง: ' . trim($c['other_conditions']));
    }

    // สรุปผล
    $status = 'pass';
    foreach ($reasons as $r) { if ($r['level'] == 'fail') { $status = 'fail'; } }
    if ($status == 'pass') {
        foreach ($reasons as $r) { if ($r['level'] == 'missing') { $status = 'insufficient'; } }
    }
    return array('status' => $status, 'reasons' => $reasons, 'criteria_id' => $cid);
}

// บันทึกผลลง eligibility_results (เก็บ criteria_id ที่ใช้ตรวจจริง) ไม่บันทึกซ้ำถ้าผลเหมือนครั้งก่อน
function save_eligibility_result($conn, $student_id, $opp_id, $res) {
    if ($res['criteria_id'] === null) { return; }
    $student_id = (int)$student_id; $opp_id = (int)$opp_id; $cid = (int)$res['criteria_id'];
    $last = fetch_one($conn, "SELECT criteria_id, result_status FROM eligibility_results WHERE student_id = $student_id AND opportunity_id = $opp_id ORDER BY checked_at DESC, result_id DESC LIMIT 1");
    if ($last && $last['criteria_id'] == $cid && $last['result_status'] == $res['status']) { return; }
    $json = esc($conn, json_encode($res['reasons'], JSON_UNESCAPED_UNICODE));
    $status = esc($conn, $res['status']);
    mysqli_query($conn, "INSERT INTO eligibility_results (student_id, opportunity_id, criteria_id, result_status, reasons)
                         VALUES ($student_id, $opp_id, $cid, '$status', '$json')");
}

function eligibility_badge($s) {
    if ($s == 'pass') { return "<span class='badge bg-success'>ตรงตามเกณฑ์ที่ตรวจสอบได้</span>"; }
    if ($s == 'fail') { return "<span class='badge bg-danger'>ไม่ตรงตามเกณฑ์</span>"; }
    return "<span class='badge bg-warning'>ข้อมูลไม่เพียงพอ / ต้องตรวจเพิ่มเติม</span>";
}
?>
