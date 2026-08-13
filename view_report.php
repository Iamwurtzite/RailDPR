<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once 'db.php';

// User Permissions Logic
$u_assigned_sec_raw = strtolower(trim($_SESSION['assigned_section'] ?? ''));
$u_sections = array_map('trim', explode(',', $u_assigned_sec_raw));
$u_role     = strtolower(trim($_SESSION['role'] ?? ''));
$u_can_edit = (int)($_SESSION['can_edit_report'] ?? 0);

if(isset($_GET['id'])) { $report_id = $conn->real_escape_string($_GET['id']); } else { die("DPR ID Missing!"); }

// Fetch Report Data
$sql = "SELECT * FROM production_records WHERE report_id = '$report_id'";
$result = $conn->query($sql);
$rows = $result->fetch_all(MYSQLI_ASSOC);
if (!$rows) { die("Data not found."); }
$first = $rows[0];

$db_section = strtolower(trim($first['section'] ?? ''));
$has_section_access = in_array('all', $u_sections) || in_array($db_section, $u_sections);
$can_edit = ($u_role === 'admin' || ($u_can_edit === 1 && $has_section_access));

// Fetch Models
$model_res = $conn->query("SELECT DISTINCT model_name FROM models ORDER BY model_name ASC");
$model_options = "";
while($m = $model_res->fetch_assoc()) {
    $model_options .= "<option value='".htmlspecialchars($m['model_name'])."'>".$m['model_name']."</option>";
}

// OT HOURS CALCULATION
$start = new DateTime($first['work_from']);
$end = new DateTime($first['work_to']);
if ($end < $start) { $end->modify('+1 day'); }
$diff = $start->diff($end);
$total_hrs = $diff->h + ($diff->i / 60);
$ot_hrs = $total_hrs > 8.5 ? ($total_hrs - 8.5) : 0;

// Save Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_dpr']) && $can_edit) {
    $new_date = $_POST['report_date'] ?? $first['report_date'];
    foreach($_POST['row_ids'] as $rid) {
        $id = $conn->real_escape_string($rid);
        $m_name = $conn->real_escape_string($_POST['model_name'][$id] ?? '');
        $uph    = $conn->real_escape_string($_POST['uph'][$id] ?? '');
        $t_qty  = (int)($_POST['total_qty'][$id] ?? 0);  
        $p_qty  = (int)($_POST['packing_qty'][$id] ?? 0); 
        $s_qty  = (int)($_POST['scrap_qty'][$id] ?? 0);
        $r_qty  = (int)($_POST['repair_qty'][$id] ?? 0);
        $rem    = $conn->real_escape_string($_POST['remarks'][$id] ?? '');

        $h_sql = "";
        for($h=1; $h<=11; $h++) {
            $val = (int)($_POST["h$h"][$id] ?? 0);
            $h_sql .= "h{$h}_qty='$val', ";
        }

        $update = "UPDATE production_records SET 
            report_date='$new_date', model_name='$m_name', uph='$uph', 
            input_qty='$p_qty', total_qty='$t_qty', scrap_qty='$s_qty', repair_qty='$r_qty', 
            remarks='$rem', $h_sql is_verified=1 WHERE id='$id'";
        $conn->query($update);
    }
    echo "<script>alert('DPR Saved Successfully!'); window.location.href='history.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ILJIN Daily Production Report</title>
    <style>
        body { font-family: Calibri, sans-serif; background: #f0f2f5; font-size: 11px; margin: 0; padding: 20px; }
        #dpr-container { width: 1150px; margin: auto; background: #fff; padding: 15px; border: 1px solid #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: -1px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        .bg-gray { background: #f2f2f2; font-weight: bold; }
        .in-field { width: 100%; border: none; text-align: center; font-size: 11px; background: transparent; outline: none; }
        
        /* Dropdown Arrow Hiding Logic */
        select.in-field {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
        }
        /* IE specific hide */
        select.in-field::-ms-expand { display: none; }

        .editable { background: #fffde7; }
        .master-total { background: #e8f5e9; font-weight: bold; }
        
        @media print { 
            .no-print { display: none; } 
            #dpr-container { border: none; width: 100%; padding: 0; }
            .editable { background: transparent !important; }
            /* Ensuring arrows are hidden in all browsers during print */
            select.in-field { appearance: none !important; border: none; }
        }
    </style>
    <script>
        function updateCalc(rowId) {
            let total = 0;
            for (let h = 1; h <= 11; h++) {
                total += parseInt(document.getElementById(`h${h}_${rowId}`).value) || 0;
            }
            document.getElementById(`total_${rowId}`).value = total;
            let footerInput = document.getElementById(`footer_input_${rowId}`);
            if(footerInput) footerInput.innerText = total;
            calculateGrand();
        }

        function calculateGrand() {
            for (let h = 1; h <= 11; h++) {
                let sum = 0;
                let cols = document.getElementsByClassName(`col-h${h}`);
                for (let c of cols) { sum += parseInt(c.value) || 0; }
                document.getElementById(`master_h${h}`).innerText = sum;
            }
            let gTotal = 0;
            let rowTotals = document.getElementsByClassName('row-total-val');
            for (let rt of rowTotals) { gTotal += parseInt(rt.value) || 0; }
            document.getElementById('master_grand').innerText = gTotal;
        }

        function syncModel(rowId, val) { document.getElementById(`footer_model_${rowId}`).innerText = val; }
        function syncRemarks(rowId, val) { document.getElementById(`footer_rem_${rowId}`).value = val; }
        function syncDate(val) {
            let dates = document.getElementsByClassName('footer-date-sync');
            for (let d of dates) { d.innerText = val; }
        }
    </script>
</head>
<body>

<form method="POST">
    <div class="no-print" style="text-align:center; margin-bottom:15px;">
        <a href="history.php" style="padding:10px 20px; background:#455a64; color:#fff; text-decoration:none; border-radius:4px;">← BACK</a>
        <?php if($can_edit): ?>
            <button type="submit" name="save_dpr" style="padding:10px 25px; background:#2e7d32; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">✔ SAVE & VERIFY</button>
            <button type="button" onclick="window.print()" style="padding:10px 20px; background:#0277bd; color:#fff; border:none; border-radius:4px; margin-left:10px;">⎙ PRINT</button>
        <?php else: ?>
            <span style="padding:10px 25px; background:#d32f2f; color:#fff; border-radius:4px;">🔒 VIEW ONLY</span>
        <?php endif; ?>
    </div>

    <div id="dpr-container">
        <div style="display:flex; justify-content:space-between; align-items:center; border:1px solid #000; padding:10px; border-bottom:none;">
            <img src="images/iljin logo.png" height="40">
            <h2 style="margin:0; text-decoration:underline;">DAILY PRODUCTION REPORT</h2>
            <div style="text-align:right; font-size:9px;">
                Doc No: 01-PRD40-MI-F-18<br> Issue No/Date: 01/01.06.2024<br> Rev.No/Date: 01/05.07.2025
            </div>
        </div>

        <table>
            <tr class="bg-gray"><th>Date</th><th>Line</th><th>Shift</th><th>Drafter</th><th>Supervisor</th><th>HOD</th></tr>
            <tr>
                <td><input type="date" name="report_date" class="in-field editable" value="<?php echo $first['report_date']; ?>" onchange="syncDate(this.value)" <?php echo !$can_edit?'disabled':''; ?>></td>
                <td><?php echo $first['line_name']; ?></td>
                <td><?php echo $first['shift']; ?></td>
                <td><?php echo $first['drafter_name']; ?></td>
                <td><?php echo $first['supervisor_name']; ?></td>
                <td><?php echo $first['hod_name']; ?></td>
            </tr>
        </table>

        <table>
            <tr class="bg-gray"><th colspan="3">Company</th><th>Contractor</th><th rowspan="2">Total Manpower</th><th colspan="4">Working Status</th><th rowspan="2">Status</th></tr>
            <tr class="bg-gray"><td>Direct</td><td>Indirect</td><td>M/P Status</td><td>Direct</td><td>From</td><td>To</td><td>OT Hrs</td><td>Total Hrs</td></tr>
            <tr>
                <td><?php echo $first['mp_direct']; ?></td><td><?php echo $first['mp_indirect']; ?></td><td>OK</td><td><?php echo $first['mp_contractor']; ?></td>
                <td><?php echo ($first['mp_direct'] + $first['mp_indirect'] + $first['mp_contractor']); ?></td>
                <td><?php echo $first['work_from']; ?></td><td><?php echo $first['work_to']; ?></td>
                <td><?php echo number_format($ot_hrs, 1); ?></td><td><?php echo number_format($total_hrs, 1); ?></td><td>RUNNING</td>
            </tr>
        </table>

        <table style="margin-top:2px;">
            <tr class="bg-gray">
                <th rowspan="2" width="30">Sr.No</th><th rowspan="2" width="180">Model Name</th><th rowspan="2" width="50">UPH</th>
                <th colspan="11">Hourly Slots For Production Details</th><th rowspan="2" width="60">Total Qty</th><th rowspan="2">Remarks</th>
            </tr>
            <tr class="bg-gray">
                <?php
                $slots = ["09:00-10:00<br>20:00-21:00","10:00-11:00<br>21:00-22:00","11:00-12:00<br>22:00-23:00","12:00-13:00<br>23:00-24:00","13:00-14:00<br>24:00-01:00","14:00-15:00<br>01:00-02:00","15:00-16:00<br>02:00-03:00","16:00-17:00<br>03:00-04:00","17:00-18:00<br>04:00-05:00","18:00-19:00<br>05:00-06:00","19:00-21:30<br>06:00-08:00"];
                foreach($slots as $s) echo "<th><span style='font-size:8px;'>$s</span></th>";
                ?>
            </tr>
            <?php 
            for($i=0; $i<15; $i++): 
                $row = $rows[$i] ?? null;
                $rid = $row['id'] ?? null;
            ?>
            <tr>
                <?php if($rid): ?>
                <input type="hidden" name="row_ids[]" value="<?php echo $rid; ?>">
                <td><?php echo $i+1; ?></td>
                <td>
                    <select name="model_name[<?php echo $rid; ?>]" class="in-field editable" onchange="syncModel('<?php echo $rid; ?>', this.value)" <?php echo !$can_edit?'disabled':''; ?>>
                        <option value="<?php echo htmlspecialchars($row['model_name']); ?>"><?php echo $row['model_name']; ?></option>
                        <?php if($can_edit) echo $model_options; ?>
                    </select>
                </td>
                <td><input type="text" name="uph[<?php echo $rid; ?>]" class="in-field editable" value="<?php echo $row['uph']; ?>" <?php echo !$can_edit?'disabled':''; ?>></td>
                <?php for($h=1; $h<=11; $h++): $hv = (int)($row["h{$h}_qty"] ?? 0); ?>
                    <td><input type="number" name="h<?php echo $h; ?>[<?php echo $rid; ?>]" id="h<?php echo $h; ?>_<?php echo $rid; ?>" class="in-field col-h<?php echo $h; ?> editable" value="<?php echo $hv; ?>" oninput="updateCalc('<?php echo $rid; ?>')" <?php echo !$can_edit?'disabled':''; ?>></td>
                <?php endfor; ?>
                <td class="bg-gray"><input type="number" name="total_qty[<?php echo $rid; ?>]" id="total_<?php echo $rid; ?>" class="in-field row-total-val" value="<?php echo $row['total_qty']; ?>" readonly></td>
                <td><input type="text" name="remarks[<?php echo $rid; ?>]" class="in-field editable" value="<?php echo htmlspecialchars($row['remarks']); ?>" oninput="syncRemarks('<?php echo $rid; ?>', this.value)" <?php echo !$can_edit?'disabled':''; ?>></td>
                <?php else: ?>
                <td><?php echo $i+1; ?></td><td></td><td></td><?php echo str_repeat("<td></td>", 11); ?><td></td><td></td>
                <?php endif; ?>
            </tr>
            <?php endfor; ?>
            <tr class="master-total">
                <td colspan="3">MASTER TOTAL</td>
                <?php for($h=1; $h<=11; $h++) echo "<td id='master_h$h'>0</td>"; ?>
                <td id="master_grand">0</td><td></td>
            </tr>
        </table>

        <table style="margin-top:5px;">
            <tr class="bg-gray"><th>S.NO.</th><th>DATE</th><th>MODEL</th><th>PACKING</th><th>INPUT</th><th>SCRAP</th><th>REPAIRING</th><th>REMARKS</th></tr>
            <?php foreach($rows as $idx => $row): $rid = $row['id']; ?>
            <tr>
                <td><?php echo $idx+1; ?></td>
                <td class="footer-date-sync"><?php echo $row['report_date']; ?></td>
                <td id="footer_model_<?php echo $rid; ?>"><?php echo $row['model_name']; ?></td>
                <td><input type="number" name="packing_qty[<?php echo $rid; ?>]" class="in-field editable" value="<?php echo (int)$row['input_qty']; ?>" <?php echo !$can_edit?'disabled':''; ?>></td>
                <td id="footer_input_<?php echo $rid; ?>" class="bg-gray"><?php echo (int)$row['total_qty']; ?></td>
                <td><input type="number" name="scrap_qty[<?php echo $rid; ?>]" class="in-field editable" value="<?php echo (int)$row['scrap_qty']; ?>" <?php echo !$can_edit?'disabled':''; ?>></td>
                <td><input type="number" name="repair_qty[<?php echo $rid; ?>]" class="in-field editable" value="<?php echo (int)$row['repair_qty']; ?>" <?php echo !$can_edit?'disabled':''; ?>></td>
                <td><input type="text" id="footer_rem_<?php echo $rid; ?>" class="in-field" value="<?php echo htmlspecialchars($row['remarks']); ?>" disabled></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</form>
<div class="footer-gold" style="margin-top:200px; text-align:center; font-size:14px; border-top:1px solid #a89e9e; padding:10px; color:#555; background-color:gold;">
      <a href="https://www.iljin.co.in/">COPYRIGHT © 2026 ILJIN ELECTRONICS INDIA PVT LTD || MADE BY RISHIK UPADHYAY</a>
</div>
<script> window.onload = function() { <?php foreach($rows as $r) echo "updateCalc('".$r['id']."');"; ?> }; </script>
</body>
</html>