<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once 'db.php';

$report_id = $conn->real_escape_string($_GET['id']);
$can_verify = ($_SESSION['role'] == 'admin' || (isset($_SESSION['can_verify_report']) && $_SESSION['can_verify_report'] == 1));

// UPDATE & VERIFY LOGIC
if (isset($_POST['update_btn'])) {
    $status = isset($_POST['verify_action']) ? 1 : 0;
    
    // Purana data delete karke naya insert karein
    $conn->query("DELETE FROM production_records WHERE report_id = '$report_id'");

    foreach ($_POST['models'] as $m) {
        if(empty($m['name'])) continue;
        
        $m_name = $conn->real_escape_string($m['name']);
        $uph = (int)$m['uph'];
        // Yahan (int) casting empty value ko 0 bana degi taaki error na aaye
        $h1 = (int)$m['h1']; $h2 = (int)$m['h2']; $h3 = (int)$m['h3']; $h4 = (int)$m['h4'];
        $h5 = (int)$m['h5']; $h6 = (int)$m['h6']; $h7 = (int)$m['h7']; $h8 = (int)$m['h8'];
        $h9 = (int)$m['h9']; $h10 = (int)$m['h10']; $h11 = (int)$m['h11'];
        $scrap = (int)$m['scrap'];
        $repair = (int)$m['repair'];
        $rem = $conn->real_escape_string($m['rem']);

        $sql = "INSERT INTO production_records (
            report_id, report_date, line_name, shift, drafter_name, supervisor_name, hod_name, 
            work_from, work_to, mp_direct, mp_indirect, mp_contractor, 
            model_name, uph, h1_qty, h2_qty, h3_qty, h4_qty, h5_qty, h6_qty, h7_qty, h8_qty, h9_qty, h10_qty, h11_qty, 
            scrap_qty, repair_qty, remarks, status
        ) VALUES (
            '$report_id', '{$_POST['date']}', '{$_POST['line']}', '{$_POST['shift']}', '{$_POST['drafter']}', '{$_POST['supervisor']}', '{$_POST['hod']}',
            '{$_POST['from']}', '{$_POST['to']}', '{$_POST['mp_d']}', '{$_POST['mp_i']}', '{$_POST['mp_c']}',
            '$m_name', '$uph', '$h1', '$h2', '$h3', '$h4', '$h5', '$h6', '$h7', '$h8', '$h9', '$h10', '$h11',
            '$scrap', '$repair', '$rem', '$status'
        )";
        $conn->query($sql);
    }
    echo "<script>alert('DPR Updated Successfully!'); window.location='history.php';</script>";
}

// FETCH PRE-FILLED DATA
$res = $conn->query("SELECT * FROM production_records WHERE report_id = '$report_id'");
$data = $res->fetch_all(MYSQLI_ASSOC);
if(!$data) die("Report Not Found!");
$h = $data[0]; 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit/Verify DPR</title>
    <style>
        body { font-family: Arial; background: #f0f2f5; padding: 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; max-width: 1200px; margin: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .model-row { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 15px; border-radius: 5px; }
        .btn-update { background: #007bff; color: white; padding: 12px 20px; border: none; cursor:pointer; font-weight: bold; border-radius: 4px; }
        .btn-verify { background: #28a745; color: white; padding: 12px 20px; border: none; cursor:pointer; font-weight: bold; border-radius: 4px; }
        h3 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 25px; }
    </style>
</head>
<body>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>📝 Edit / Verify Report (<?php echo $report_id; ?>)</h2>
        <a href="history.php" style="text-decoration:none; color:#666;">← Cancel</a>
    </div>
    
    <form method="POST">
        <div class="grid">
            <div><label>Date</label><input type="date" name="date" value="<?php echo $h['report_date']; ?>"></div>
            <div><label>Line Name</label><input type="text" name="line" value="<?php echo $h['line_name']; ?>"></div>
            <div><label>Shift</label><input type="text" name="shift" value="<?php echo $h['shift']; ?>"></div>
            <div><label>Drafter Name</label><input type="text" name="drafter" value="<?php echo $h['drafter_name']; ?>"></div>
            <div><label>Supervisor Name</label><input type="text" name="supervisor" value="<?php echo $h['supervisor_name']; ?>"></div>
            <div><label>HOD Name</label><input type="text" name="hod" value="<?php echo $h['hod_name']; ?>"></div>
        </div>

        <input type="hidden" name="from" value="<?php echo $h['work_from']; ?>">
        <input type="hidden" name="to" value="<?php echo $h['work_to']; ?>">
        <input type="hidden" name="mp_d" value="<?php echo $h['mp_direct']; ?>">
        <input type="hidden" name="mp_i" value="<?php echo $h['mp_indirect']; ?>">
        <input type="hidden" name="mp_c" value="<?php echo $h['mp_contractor']; ?>">

        <h3>Production Details (Hourly Data)</h3>
        <?php foreach($data as $idx => $row): ?>
        <div class="model-row">
            <div class="grid">
                <div><small>Model Name</small><input type="text" name="models[<?php echo $idx; ?>][name]" value="<?php echo $row['model_name']; ?>"></div>
                <div><small>UPH</small><input type="number" name="models[<?php echo $idx; ?>][uph]" value="<?php echo $row['uph']; ?>"></div>
                <div><small>Remarks</small><input type="text" name="models[<?php echo $idx; ?>][rem]" value="<?php echo $row['remarks']; ?>"></div>
            </div>
            
            <div style="display:grid; grid-template-columns: repeat(11, 1fr); gap:5px; margin-top:10px;">
                <?php for($i=1; $i<=11; $i++): 
                    $col_name = "h".$i."_qty"; // Database column name
                ?>
                    <div style="text-align:center">
                        <small>H<?php echo $i; ?></small>
                        <input type="number" name="models[<?php echo $idx; ?>][h<?php echo $i; ?>]" value="<?php echo $row[$col_name]; ?>" style="padding:5px;">
                    </div>
                <?php endfor; ?>
            </div>
            
            <div style="display:flex; gap:20px; margin-top:15px; background:#fff; padding:10px; border-radius:4px;">
                <div><small>Scrap Qty</small><br><input type="number" name="models[<?php echo $idx; ?>][scrap]" value="<?php echo $row['scrap_qty']; ?>" style="width:100px;"></div>
                <div><small>Repair Qty</small><br><input type="number" name="models[<?php echo $idx; ?>][repair]" value="<?php echo $row['repair_qty']; ?>" style="width:100px;"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:30px; text-align:center; padding-top:20px; border-top:1px solid #eee;">
            <button type="submit" name="update_btn" class="btn-update">💾 Save Changes (Pending)</button>
            <?php if($can_verify): ?>
                <button type="submit" name="update_btn" name="verify_action" value="1" class="btn-verify" style="margin-left:20px;">✅ Verify & Accept DPR</button>
            <?php endif; ?>
        </div>
    </form>
</div>
</body>
</html>