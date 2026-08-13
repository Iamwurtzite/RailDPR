<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'msg' => 'Session expired', 'reload' => true]);
    exit;
}

require_once 'db.php';

// ── live_row_token column — safe way (IF NOT EXISTS nahi use karte) ──
$col_check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA=DATABASE() 
    AND TABLE_NAME='production_records' 
    AND COLUMN_NAME='live_row_token'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE production_records ADD COLUMN live_row_token VARCHAR(64) NOT NULL DEFAULT ''");
}

// ── Inputs ──────────────────────────────────────────
$esc = function($v) use ($conn) { return $conn->real_escape_string(trim($v ?? '')); };

$date       = $esc($_POST['date']       ?? date('Y-m-d'));
$line       = $esc($_POST['line']       ?? '');
$section    = $esc($_POST['section']    ?? '');
$shift      = $esc($_POST['shift']      ?? '');
$drafter    = $esc($_POST['drafter']    ?? '');
$supervisor = $esc($_POST['supervisor'] ?? '');
$hod        = $esc($_POST['hod']        ?? '');
$from       = $esc($_POST['work_from']  ?? '00:00');
$to         = $esc($_POST['work_to']    ?? '00:00');
$mp_dir     = (int)($_POST['mp_direct']     ?? 0);
$mp_ind     = (int)($_POST['mp_indirect']   ?? 0);
$mp_con     = (int)($_POST['mp_contractor'] ?? 0);
$model_name = $esc($_POST['model_name'] ?? '');
$uph        = (int)($_POST['uph']       ?? 0);
$scrap      = (int)($_POST['scrap']     ?? 0);
$repair     = (int)($_POST['repair']    ?? 0);
$m_input    = (int)($_POST['input']     ?? 0);
$remarks    = $esc($_POST['remarks']    ?? '');
$row_token  = $esc($_POST['row_token']  ?? '');

if (empty($date) || empty($line) || empty($model_name) || empty($row_token)) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'msg' => 'Missing fields',
        'debug' => ['date'=>$date,'line'=>$line,'model'=>$model_name,'token'=>$row_token]]);
    exit;
}

$h = []; $total = 0;
for ($i = 1; $i <= 11; $i++) {
    $h[$i] = (int)($_POST["h$i"] ?? 0);
    $total += $h[$i];
}

// ── Check existing ───────────────────────────────────
$res = $conn->query("SELECT report_id FROM production_records 
    WHERE live_row_token='$row_token' AND is_deleted=0 LIMIT 1");

if ($res && $res->num_rows > 0) {
    $rid = $res->fetch_assoc()['report_id'];
    $sql = "UPDATE production_records SET
        report_date='$date', line_name='$line', section='$section', shift='$shift',
        drafter_name='$drafter', supervisor_name='$supervisor', hod_name='$hod',
        work_from='$from', work_to='$to',
        mp_direct=$mp_dir, mp_indirect=$mp_ind, mp_contractor=$mp_con,
        model_name='$model_name', uph=$uph,
        h1_qty={$h[1]}, h2_qty={$h[2]}, h3_qty={$h[3]}, h4_qty={$h[4]},
        h5_qty={$h[5]}, h6_qty={$h[6]}, h7_qty={$h[7]}, h8_qty={$h[8]},
        h9_qty={$h[9]}, h10_qty={$h[10]}, h11_qty={$h[11]},
        total_qty=$total, scrap_qty=$scrap, repair_qty=$repair,
        remarks='$remarks', input_qty=$m_input
        WHERE live_row_token='$row_token' AND is_deleted=0";
    ob_end_clean();
    if ($conn->query($sql)) {
        echo json_encode(['ok'=>true, 'action'=>'updated', 'report_id'=>$rid, 'total'=>$total]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>'Update error: '.$conn->error]);
    }

} else {
    $report_id = 'LIVE-' . date('Ymd') . '-' . substr(md5($row_token), 0, 8);
    $sql = "INSERT INTO production_records
        (report_id, report_date, line_name, section, shift,
         drafter_name, supervisor_name, hod_name, work_from, work_to,
         mp_direct, mp_indirect, mp_contractor, model_name, uph,
         h1_qty, h2_qty, h3_qty, h4_qty, h5_qty, h6_qty,
         h7_qty, h8_qty, h9_qty, h10_qty, h11_qty,
         total_qty, scrap_qty, repair_qty, remarks, input_qty,
         is_verified, live_row_token)
        VALUES
        ('$report_id','$date','$line','$section','$shift',
         '$drafter','$supervisor','$hod','$from','$to',
         $mp_dir,$mp_ind,$mp_con,'$model_name',$uph,
         {$h[1]},{$h[2]},{$h[3]},{$h[4]},{$h[5]},{$h[6]},
         {$h[7]},{$h[8]},{$h[9]},{$h[10]},{$h[11]},
         $total,$scrap,$repair,'$remarks',$m_input,
         0,'$row_token')";
    ob_end_clean();
    if ($conn->query($sql)) {
        echo json_encode(['ok'=>true, 'action'=>'inserted', 'report_id'=>$report_id, 'total'=>$total]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>'Insert error: '.$conn->error]);
    }
}

$conn->close();