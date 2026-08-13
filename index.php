<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once 'db.php';

// AJAX: Fetch models based on section
if(isset($_GET['action']) && $_GET['action'] == 'get_models') {
    $sec = $conn->real_escape_string($_GET['section']);
    $res = $conn->query("SELECT DISTINCT model_name FROM models WHERE section = '$sec' ORDER BY model_name ASC");
    $opt = "<option value=''>--Select Part--</option>";
    while($row = $res->fetch_assoc()) {
        $opt .= "<option value='".htmlspecialchars($row['model_name'])."'>".htmlspecialchars($row['model_name'])."</option>";
    }
    echo $opt; exit;
}

// SAVE LOGIC (Final Submit — live records update karo, naye insert karo)
if (isset($_POST['save_data'])) {
    $date       = $conn->real_escape_string($_POST['date']);
    $line       = $conn->real_escape_string($_POST['line']);
    $section    = $conn->real_escape_string($_POST['section']);
    $shift      = $conn->real_escape_string($_POST['shift']);
    $drafter    = $conn->real_escape_string($_POST['drafter_name']);
    $supervisor = $conn->real_escape_string($_POST['supervisor_name']);
    $hod        = $conn->real_escape_string($_POST['hod_name']);
    $from       = $_POST['work_from'];
    $to         = $_POST['work_to'];
    $mp_dir     = (int)$_POST['mp_direct'];
    $mp_ind     = (int)$_POST['mp_indirect'];
    $mp_con     = (int)$_POST['mp_contractor'];

    // Ek hi report_id poore form ke liye
    $report_id = 'R-' . strtoupper(preg_replace('/[^a-z0-9]/i','', $line)) . '-' . date('Ymd') . '-' . uniqid();
    $report_id = $conn->real_escape_string($report_id);

    foreach ($_POST['models'] as $m) {
        if(empty($m['name'])) continue;
        $model_name = $conn->real_escape_string($m['name']);
        $uph        = empty($m['uph'])    ? 0 : (int)$m['uph'];
        $scrap      = empty($m['scrap'])  ? 0 : (int)$m['scrap'];
        $repair     = empty($m['repair']) ? 0 : (int)$m['repair'];
        $m_input    = empty($m['input'])  ? 0 : (int)$m['input'];
        $remarks    = $conn->real_escape_string($m['remarks'] ?? '');
        $row_token  = $conn->real_escape_string($m['row_token'] ?? '');

        $h = []; $total_qty = 0;
        for($i=1;$i<=11;$i++) { $h[$i]=empty($m['h'][$i])?0:(int)$m['h'][$i]; $total_qty+=$h[$i]; }

        // Agar live_row_token se koi record already hai toh sirf update karo
        if(!empty($row_token)) {
            $chk = $conn->query("SELECT report_id FROM production_records 
                WHERE live_row_token='$row_token' AND is_deleted=0 LIMIT 1");
            if($chk && $chk->num_rows > 0) {
                $existing_rid = $chk->fetch_assoc()['report_id'];
                $conn->query("UPDATE production_records SET
                    report_id='$report_id',
                    report_date='$date', line_name='$line', section='$section', shift='$shift',
                    drafter_name='$drafter', supervisor_name='$supervisor', hod_name='$hod',
                    work_from='$from', work_to='$to',
                    mp_direct=$mp_dir, mp_indirect=$mp_ind, mp_contractor=$mp_con,
                    model_name='$model_name', uph=$uph,
                    h1_qty={$h[1]},h2_qty={$h[2]},h3_qty={$h[3]},h4_qty={$h[4]},
                    h5_qty={$h[5]},h6_qty={$h[6]},h7_qty={$h[7]},h8_qty={$h[8]},
                    h9_qty={$h[9]},h10_qty={$h[10]},h11_qty={$h[11]},
                    total_qty=$total_qty, scrap_qty=$scrap, repair_qty=$repair,
                    remarks='$remarks', input_qty=$m_input, is_verified=0
                    WHERE live_row_token='$row_token' AND is_deleted=0");
                continue; // next model
            }
        }

        // Naya insert
        $sql = "INSERT INTO production_records 
            (report_id, report_date, line_name, section, shift,
             drafter_name, supervisor_name, hod_name, work_from, work_to,
             model_name, uph, h1_qty, h2_qty, h3_qty, h4_qty, h5_qty, h6_qty,
             h7_qty, h8_qty, h9_qty, h10_qty, h11_qty,
             total_qty, scrap_qty, repair_qty, remarks, mp_direct, mp_indirect,
             mp_contractor, input_qty, live_row_token)
            VALUES
            ('$report_id','$date','$line','$section','$shift',
             '$drafter','$supervisor','$hod','$from','$to',
             '$model_name',$uph,{$h[1]},{$h[2]},{$h[3]},{$h[4]},{$h[5]},{$h[6]},
             {$h[7]},{$h[8]},{$h[9]},{$h[10]},{$h[11]},
             $total_qty,$scrap,$repair,'$remarks',$mp_dir,$mp_ind,
             $mp_con,$m_input,'$row_token')";
        $conn->query($sql);
    }
    echo "<script>alert('Report Saved Successfully!'); sessionStorage.setItem('isLocked', 'false'); window.onbeforeunload = null; location.href='index.php';</script>";
}

function getOptions($conn, $table, $col) {
    $res = $conn->query("SELECT DISTINCT $col FROM $table ORDER BY $col ASC");
    $opt = "";
    if($res) { while($row = $res->fetch_assoc()) { $opt .= "<option value='".htmlspecialchars($row[$col])."'>".htmlspecialchars($row[$col])."</option>"; } }
    return $opt;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Production Report || Rishik</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --dark: #222e3c; --bg: #f8f9fc; --danger: #e74a3b; }
        body { font-family: 'Poppins', sans-serif; margin: 0; background: var(--bg); color: #333; font-size:13px; }
        .header-bar { background: var(--dark); color: white; padding: 12px 30px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .nav-links a { color: #fff; text-decoration: none; margin-left: 15px; font-size: 12px; padding: 8px 15px; border-radius: 6px; background: rgba(255,255,255,0.1); transition: 0.3s; }
        .nav-links a:hover { background: var(--primary); }
        .no-print { width: 95%; margin: 20px auto; }
        .form-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 20px; border: 1px solid #e3e6f0; }
        .card-title { font-weight: 600; font-size: 1.1rem; color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; }
        .card-title::before { content: ''; width: 4px; height: 18px; background: var(--primary); margin-right: 10px; border-radius: 2px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 8px; color: #5a5c69; }
        input, select, textarea { padding: 10px; border: 1px solid #d1d3e2; border-radius: 6px; width: 100%; box-sizing: border-box; outline: none; }
        .hourly-grid { display: grid; grid-template-columns: repeat(11, 1fr); gap: 5px; margin-top: 15px; }
        .hourly-grid input { text-align: center; font-weight: 600; font-size: 13px; }
        input[readonly] { background: #eaecf4 !important; border: 1px solid var(--primary); color: var(--primary); cursor: pointer; }
        #reloadLock, #passOverlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:10001; justify-content:center; align-items:center; }
        .lock-box { background:white; padding:30px; border-radius:15px; text-align:center; width:350px; box-shadow:0 10px 30px rgba(0,0,0,0.5); }
        
        /* Template Fixes */
        #dpr-template { width: 100%; max-width: 1150px; margin: 30px auto; background: white; padding: 20px; border: 1.5px solid #000; color: #000; box-sizing: border-box; }
        #dpr-template table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 10px; }
        #dpr-template th, #dpr-template td { border: 1px solid #000; padding: 4px; text-align: center; font-size: 10px; word-wrap: break-word; }
        .bg-grey { background: #f2f2f2 !important; font-weight: bold; }
        .btn-add { background: #fff; color: var(--primary); border: 2px dashed var(--primary); padding: 12px; width: 100%; cursor: pointer; font-weight: bold; margin-bottom: 20px; border-radius: 8px; }
        .btn-save { background: var(--success); color: white; padding: 15px 60px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px; box-shadow: 0 4px 12px rgba(28,200,138,0.3); }
        @media print { .no-print, .header-bar, .btn-save, .btn-add { display: none !important; } #dpr-template { margin: 0; width: 100%; border: none; } }
    </style>
</head>
<body>

<div id="reloadLock">
    <div class="lock-box">
        <h2 style="color:var(--danger)">🔒 Page Access Locked</h2>
        <p>This report is protected. Please verify to continue.</p>
        <input type="password" id="unlockPass" placeholder="Enter Password" style="margin:20px 0; text-align:center; font-size:18px;">
        <button onclick="unlockPage()" class="btn-save" style="width:100%">Verify & Unlock</button>
    </div>
</div>

<div id="passOverlay">
    <div class="lock-box">
        <h3>🔑 Edit Access</h3>
        <p>Enter Master Password to modify value</p>
        <input type="password" id="securePassInput" placeholder="••••" style="margin:20px 0; text-align:center; font-size:22px; letter-spacing:8px;">
        <div style="display:flex; gap:10px;">
            <button onclick="checkSecurePass()" class="btn-save" style="flex:1; padding:10px;">Unlock</button>
            <button onclick="$('#passOverlay').hide()" style="flex:1; background:#858796; color:white; border:none; border-radius:5px; cursor:pointer;">Cancel</button>
        </div>
    </div>
</div>

<div class="header-bar">
    <div style="font-size:18px; font-weight:600;">ILJIN DAILY PRODUCTION REPORT</div>
    <div class="nav-links">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="history.php">📊 View History</a>            
        <a href="admin.php" style="background:var(--danger)">⚙️ Admin Control</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
</div>

<div class="no-print" id="mainContent">
    <form method="POST" id="dprForm" onkeydown="return event.key != 'Enter';">
        <div class="form-card">
            <div class="card-title">General Information</div>
            <div class="form-grid">
                <div><label>Date</label><input type="date" name="date" id="in_date" value="<?=date('Y-m-d')?>"></div>
                <div><label>Section</label>
                    <select name="section" id="in_section" class="searchable" onchange="loadModelsBySection(this.value)">
                        <option value="">--Select Section--</option><?=getOptions($conn, 'models', 'section')?>
                    </select>
                </div>
                <div><label>Line</label><select name="line" id="in_line" class="searchable"><option value="">--Select--</option><?=getOptions($conn, 'lines_list', 'line_name')?></select></div>
                <div><label>Shift</label><select name="shift" id="in_shift"><option value="Day">Day</option><option value="Night">Night</option></select></div>
                <div><label>Drafter Name</label><input type="text" name="drafter_name" id="in_drafter" placeholder="Drafter Name" required></div>
            </div>
            <div class="form-grid" style="margin-top:15px; padding-top:15px; border-top:1px solid #eee;">
                <div><label>Supervisor</label><select name="supervisor_name" id="in_supervisor" class="searchable"><option value="">--Select--</option><?=getOptions($conn, 'supervisors', 'name')?></select></div>
                <div><label>HOD</label><select name="hod_name" id="in_hod" class="searchable"><option value="">--Select--</option><?=getOptions($conn, 'hods', 'name')?></select></div>
                <div><label>From Time</label><input type="time" name="work_from" id="in_from" value="00:00"></div>
                <div><label>To Time</label><input type="time" name="work_to" id="in_to" value="00:00"></div>
            </div>
            <div class="form-grid" style="margin-top:15px;">
                <div><label>Direct M/P</label><input type="number" name="mp_direct" id="in_mp_dir" value="0"></div>
                <div><label>Indirect M/P</label><input type="number" name="mp_indirect" id="in_mp_ind" value="0"></div>
                <div><label>Contractor</label><input type="number" name="mp_contractor" id="in_mp_con" value="0"></div>
            </div>
        </div>

        <div id="models-input-container">
            <div class="model-entry form-card" data-row="0">
                <div class="card-title">Part / Model Details #1</div>
                <div class="form-grid">
                    <div style="grid-column: span 2;"><label>Model Name / Part No</label>
                        <select name="models[0][name]" class="m_name searchable model-dropdown"><option value="">--Select Section First--</option></select>
                    </div>
                    <div><label>UPH</label><input type="number" name="models[0][uph]" class="m_uph" placeholder="0"></div>
                    <div><label>Packing</label><input type="number" name="models[0][input]" class="m_input" placeholder="0"></div>
                    <div><label>Scrap</label><input type="number" name="models[0][scrap]" class="m_scrap" placeholder="0"></div>
                    <div><label>Repair</label><input type="number" name="models[0][repair]" class="m_repair" placeholder="0"></div>
                </div>
                <div class="hourly-grid">
                    <?php for($i=1; $i<=11; $i++) echo "<input type='number' name='models[0][h][$i]' class='m_h$i lockable' data-h='$i' placeholder='H$i'>"; ?>
                </div>
                <textarea name="models[0][remarks]" class="m_rem" rows="2" style="margin-top:10px;" placeholder="Production Remarks..."></textarea>
                <input type="hidden" name="models[0][row_token]" class="m_row_token">
            </div>
        </div>

        <button type="button" class="btn-add" onclick="addNewModelRow()">➕ Add Another Model Row</button>

        <div style="text-align:center; padding-bottom:50px;">
            <button type="submit" name="save_data" class="btn-save" onclick="window.onbeforeunload=null">💾 SAVE PRODUCTION REPORT</button>
        </div>
    </form>
</div>

<div id="dpr-template">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #000; padding:8px;">
        <img src="images/iljin logo.png" height="42" onerror="this.src='https://via.placeholder.com/100x40?text=ILJIN+LOGO'">
        <b style="font-size:18px; text-decoration: underline;">DAILY PRODUCTION REPORT</b>
        <div style="font-size:7px; text-align:left;">Doc No: 01-PRD40-MI-F-18<br>Issue Date: 01.06.2024<br>Rev.Date: 05.07.2025</div>
    </div>
    
    <table>
        <tr class="bg-grey"><th>Date</th><th>Line</th><th>Shift</th><th>Drafter</th><th>Supervisor</th><th>HOD</th></tr>
        <tr><td id="v_date"></td><td id="v_line"></td><td id="v_shift"></td><td id="v_drafter"></td><td id="v_supervisor"></td><td id="v_hod"></td></tr>
    </table>

    <table>
        <tr class="bg-grey"><th colspan="3">Company Manpower</th><th>Contractor</th><th rowspan="2">Total M/P</th><th colspan="2">Working Time</th><th rowspan="2">OT Hrs</th><th rowspan="2">Total Hrs</th></tr>
        <tr class="bg-grey"><td>Direct</td><td>Indirect</td><td>Status</td><td>Direct</td><td>From</td><td>To</td></tr>
        <tr><td id="v_mp_dir">0</td><td id="v_mp_ind">0</td><td>OK</td><td id="v_mp_con">0</td><td id="v_mp_total">0</td><td id="v_from"></td><td id="v_to"></td><td id="v_ot">0</td><td id="v_total_hrs">0</td></tr>
    </table>

    <table>
        <thead>
            <tr class="bg-grey">
                <th rowspan="2" width="25">Sr</th><th rowspan="2" width="140">Model Name</th><th rowspan="2" width="30">UPH</th>
                <th colspan="11">Time Slots Production Details</th><th rowspan="2" width="40">Total</th><th rowspan="2">Remarks</th>
            </tr>
            <tr class="bg-grey">
                <?php
                $slots = ["09:00-10:00<br>20:00-21:00","10:00-11:00<br>21:00-22:00","11:00-12:00<br>22:00-23:00","12:00-13:00<br>23:00-24:00","13:00-14:00<br>24:00-01:00","14:00-15:00<br>01:00-02:00","15:00-16:00<br>02:00-03:00","16:00-17:00<br>03:00-04:00","17:00-18:00<br>04:00-05:00","18:00-19:00<br>05:00-06:00","19:00-21:30<br>06:00-08:00"];
                foreach($slots as $s) echo "<th><span style='font-size:7px;'>$s</span></th>";
                ?>
            </tr>
        </thead>
        <tbody id="v_prod_body"></tbody>
        <tfoot id="v_prod_foot"></tfoot>
    </table>

    <table style="margin-top:5px;">
        <thead><tr class="bg-grey"><th>Sr</th><th>Date</th><th>Model Name</th><th>Packing</th><th>Input</th><th>Scrap</th><th>Repair</th><th>Remarks</th></tr></thead>
        <tbody id="v_scrap_body"></tbody>
    </table>
</div>

<div class="footer-gold" style="margin-top:15px; text-align:center; font-size:14px; border-top:1px solid #a89e9e; padding:10px; color:#555; background-color:gold;">
      <a href="https://www.iljin.co.in/">COPYRIGHT © 2026 ILJIN ELECTRONICS INDIA PVT LTD || MADE BY RISHIK UPADHYAY</a>
</div>

<script>
const MASTER_KEY = "Rishik@123";
let fieldToUnlock = null;

if (sessionStorage.getItem('isLocked') === 'true') {
    $('#mainContent').hide();
    $('#reloadLock').css('display', 'flex');
}
window.onbeforeunload = function() {
    sessionStorage.setItem('isLocked', 'true');
    return "Discard changes?";
};

function unlockPage() {
    if($('#unlockPass').val() === MASTER_KEY) {
        sessionStorage.setItem('isLocked', 'false');
        $('#reloadLock').hide();
        $('#mainContent').show();
    } else { alert("Incorrect Password!"); }
}

function addNewModelRow() {
    let container = $('#models-input-container');
    let rowCount = $('.model-entry').length;
    let newRow = $('.model-entry:first').clone();
    
    newRow.attr('data-row', rowCount);
    newRow.find('.card-title').text('Part / Model Details #' + (rowCount + 1));
    newRow.find('input, textarea').val('').removeAttr('readonly');
    
    // Fix Select2 and Input names for multi-row saving
    newRow.find('.m_name').attr('name', `models[${rowCount}][name]`).val('').removeClass('select2-hidden-accessible').next('.select2-container').remove();
    newRow.find('.m_uph').attr('name', `models[${rowCount}][uph]`);
    newRow.find('.m_input').attr('name', `models[${rowCount}][input]`);
    newRow.find('.m_scrap').attr('name', `models[${rowCount}][scrap]`);
    newRow.find('.m_repair').attr('name', `models[${rowCount}][repair]`);
    newRow.find('.m_rem').attr('name', `models[${rowCount}][remarks]`);
    newRow.find('.m_row_token').attr('name', `models[${rowCount}][row_token]`).val('');

    for(let i=1; i<=11; i++) {
        newRow.find(`.m_h${i}`).attr('name', `models[${rowCount}][h][${i}]`);
    }
    
    container.append(newRow);
    $('.searchable').last().select2({ width: '100%' });
}

$(document).ready(function() {
    $('.searchable').select2({ width: '100%' });
    $(document).on('change input', 'input, select, textarea', updateView);
    $(document).on('blur', '.lockable', function() {
        let $input = $(this);
        let val = parseInt($input.val()) || 0;
        if(val > 0) {
            $input.attr('readonly', true);
        }
        // Har blur par — value ho ya na ho — live save karo
        saveLiveHourly($input.closest('.model-entry'));
    });

    $(document).on('click', '.lockable[readonly]', function() {
        fieldToUnlock = $(this);
        $('#securePassInput').val('');
        $('#passOverlay').css('display', 'flex').find('input').focus();
    });

   
    $(document).on('keydown', 'input, select, textarea', function(e) {
        if (e.which === 13) { // If Enter is pressed
            e.preventDefault(); // Stop form submission
            
           
            let $inputs = $(this).closest('form').find(':input:not([type=hidden]):visible');
            
          
            let $allowedInputs = $inputs.filter(function() {
                return !this.disabled && (!this.readOnly || $(this).hasClass('lockable'));
            });

            let nextIdx = $allowedInputs.index(this) + 1;
            if (nextIdx < $allowedInputs.length) {
                $allowedInputs.eq(nextIdx).focus(); 
            }
        }
    });

    updateView();
});

function checkSecurePass() {
    if($('#securePassInput').val() === MASTER_KEY) {
        fieldToUnlock.removeAttr('readonly').focus();
        $('#passOverlay').hide();
    } else { alert("Unauthorized!"); }
}

// ═══════════════════════════════════════════════════
// LIVE HOURLY SAVE — H-cell blur hone par DB save
// ═══════════════════════════════════════════════════
function saveLiveHourly($modelEntry) {
    let date       = $('#in_date').val();
    let line       = $('#in_line').val();
    let section    = $('#in_section').val();
    let shift      = $('#in_shift').val();
    let drafter    = $('#in_drafter').val();
    let supervisor = $('#in_supervisor').val() || $('[name="supervisor_name"]').val();
    let hod        = $('#in_hod').val() || $('[name="hod_name"]').val();
    let from       = $('#in_from').val();
    let to         = $('#in_to').val();
    let mp_dir     = $('#in_mp_dir').val() || 0;
    let mp_ind     = $('#in_mp_ind').val() || 0;
    let mp_con     = $('#in_mp_con').val() || 0;

    // Basic validation — line aur date zaruri hain
    if(!date || !line) return;

    let modelName = $modelEntry.find('.m_name').val() || '';
    if(!modelName) return; // model select nahi kiya toh skip

    // Row token: page load par generate, same row ke sab saves ek hi record update karein
    if(!$modelEntry.data('live-token')) {
        $modelEntry.data('live-token', 'tok_' + Date.now() + '_' + Math.floor(Math.random()*9999));
    }
    let rowToken = $modelEntry.data('live-token');

    let hData = {};
    for(let i=1;i<=11;i++) {
        hData['h'+i] = parseInt($modelEntry.find('.m_h'+i).val()) || 0;
    }

    let payload = {
        action:      'save_hourly_live',
        date:        date, line: line, section: section, shift: shift,
        drafter:     drafter, supervisor: supervisor, hod: hod,
        work_from:   from, work_to: to,
        mp_direct:   mp_dir, mp_indirect: mp_ind, mp_contractor: mp_con,
        model_name:  modelName,
        uph:         $modelEntry.find('.m_uph').val() || 0,
        scrap:       $modelEntry.find('.m_scrap').val() || 0,
        repair:      $modelEntry.find('.m_repair').val() || 0,
        input:       $modelEntry.find('.m_input').val() || 0,
        remarks:     $modelEntry.find('.m_rem').val() || '',
        row_token:   rowToken,
        ...hData
    };

    // Live save indicator dikhao
    let $title = $modelEntry.find('.card-title');
    $title.find('.live-status').remove();
    $title.append('<span class="live-status" style="font-size:10px;margin-left:8px;color:#f59e0b;">💾 Saving...</span>');

    // Token ko hidden input mein bhi sync karo for final submit
    $modelEntry.find('.m_row_token').val(rowToken);

    $.post('save_live.php', payload, function(res) {
        $title.find('.live-status').remove();
        if(res.ok) {
            $title.append('<span class="live-status" style="font-size:10px;margin-left:8px;color:#10b981;">✔ Saved</span>');
            setTimeout(()=>$title.find('.live-status').fadeOut(500, function(){$(this).remove();}), 2500);
            $modelEntry.data('live-report-id', res.report_id);
        } else {
            $title.append('<span class="live-status" style="font-size:10px;margin-left:8px;color:#ef4444;">✘ Error</span>');
            console.warn('Live save failed:', res.msg);
        }
    }, 'json').fail(function(xhr, status, err) {
        $title.find('.live-status').remove();
        // Exact error console mein dekho (F12 > Console)
        console.error('LIVE SAVE FAILED:', status, err);
        console.error('Server response:', xhr.responseText);
        $title.append('<span class="live-status" style="font-size:10px;margin-left:8px;color:#ef4444;" title="' 
            + (xhr.responseText||'').substring(0,100).replace(/"/g,"'") 
            + '">✘ Error (F12 dekho)</span>');
    });
}

function loadModelsBySection(section) {
    if(!section) return;
    $.get(window.location.href, { action: 'get_models', section: section }, function(data) {
        $('.model-dropdown').html(data).trigger('change');
    });
}

function updateView() {
    $('#v_date').text($('#in_date').val());
    $('#v_line').text($('#in_line').val());
    $('#v_shift').text($('#in_shift').val());
    $('#v_drafter').text($('#in_drafter').val());
    $('#v_supervisor').text($('#in_supervisor').val());
    $('#v_hod').text($('#in_hod').val());
    
    let d = parseInt($('#in_mp_dir').val())||0, i = parseInt($('#in_mp_ind').val())||0, c = parseInt($('#in_mp_con').val())||0;
    $('#v_mp_dir').text(d); $('#v_mp_ind').text(i); $('#v_mp_con').text(c); $('#v_mp_total').text(d+i+c);
    $('#v_from').text($('#in_from').val()); $('#v_to').text($('#in_to').val());

    let start = new Date("2026-01-01 " + $('#in_from').val());
    let end = new Date("2026-01-01 " + $('#in_to').val());
    if (end < start) end.setDate(end.getDate() + 1);
    let diffHrs = (end - start) / 3600000;
    $('#v_total_hrs').text(diffHrs.toFixed(2));
    $('#v_ot').text(diffHrs > 8 ? (diffHrs - 8).toFixed(2) : '0.00');

    let prodRows = "", scrapRows = "", colTotals = Array(12).fill(0);
    $('.model-entry').each(function(idx) {
        let modelSelect = $(this).find('.m_name');
        let name = modelSelect.find('option:selected').text() || "";
        if(name === "" || name.includes("--Select")) name = "";

        let rowTotal = 0, hCells = "";
        for(let h=1; h<=11; h++) {
            let v = parseInt($(this).find('.m_h'+h).val()) || 0;
            rowTotal += v; colTotals[h-1] += v;
            hCells += `<td>${v || ''}</td>`;
        }
        colTotals[11] += rowTotal;
        prodRows += `<tr><td>${idx+1}</td><td>${name}</td><td>${$(this).find('.m_uph').val()}</td>${hCells}<td><b>${rowTotal}</b></td><td>${$(this).find('.m_rem').val()}</td></tr>`;
        
        if(name !== "") {
            scrapRows += `<tr><td>${idx+1}</td><td>${$('#in_date').val()}</td><td>${name}</td><td>${$(this).find('.m_input').val()}</td><td>${rowTotal}</td><td>${$(this).find('.m_scrap').val()}</td><td>${$(this).find('.m_repair').val()}</td><td>${$(this).find('.m_rem').val()}</td></tr>`;
        }
    });

    for(let r=$('.model-entry').length; r<12; r++) prodRows += `<tr><td>${r+1}</td><td></td><td></td>${'<td></td>'.repeat(11)}<td></td><td></td></tr>`;
    
    let foot = `<tr class="bg-grey"><td colspan="3">TOTAL</td>`;
    for(let t=0; t<11; t++) foot += `<td>${colTotals[t]}</td>`;
    foot += `<td>${colTotals[11]}</td><td></td></tr>`;
    
    $('#v_prod_body').html(prodRows); $('#v_prod_foot').html(foot); $('#v_scrap_body').html(scrapRows);
}
</script>
</body>
</html>