<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location:login.php"); exit(); }

require_once 'db.php';

// --- PERMISSIONS CHECK ---
$is_admin = ($_SESSION['role'] == 'admin');
$can_download = ($is_admin || (isset($_SESSION['can_download_report']) && $_SESSION['can_download_report'] == 1));
$can_user = ($is_admin || (isset($_SESSION['can_create_user']) && $_SESSION['can_create_user'] == 1));
$can_master = ($is_admin || (isset($_SESSION['can_create_model']) && $_SESSION['can_create_model'] == 1));
$can_delete = ($is_admin || (isset($_SESSION['can_delete_model']) && $_SESSION['can_delete_model'] == 1));

if(!$can_download && !$can_user && !$can_master && !$can_delete) { header("Location: index.php"); exit(); }

// --- 1. DOWNLOAD MODELS BY SECTION ---
if(isset($_POST['download_partcodes'])) {
    $sec_filter = $_POST['download_sec'];
    $filename = "Master_Models_" . ($sec_filter ?: "All") . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "Model Name\tSection\n";
    $sql = "SELECT * FROM models";
    if(!empty($sec_filter)) $sql .= " WHERE section = '$sec_filter'";
    $res = $conn->query($sql . " ORDER BY section ASC");
    while($r = $res->fetch_assoc()) {
        echo "{$r['model_name']}\t{$r['section']}\n";
    }
    exit();
}

// --- 2. BULK UPLOAD MODELS ---
if(isset($_POST['bulk_upload']) && $can_master) {
    if($_FILES['csv_file']['name']) {
        $file = fopen($_FILES['csv_file']['tmp_name'], "r");
        fgetcsv($file); 
        $count = 0;
        while (($getData = fgetcsv($file, 10000, ",")) !== FALSE) {
            $m_name = $conn->real_escape_string($getData[0]);
            $m_sec = $conn->real_escape_string($getData[1]);
            if(!empty($m_name)) {
                $conn->query("INSERT INTO models (model_name, section) VALUES ('$m_name', '$m_sec')");
                $count++;
            }
        }
        fclose($file);
        echo "<script>alert('$count Models Uploaded!'); window.location='admin.php';</script>";
    }
}

// --- 3. EXPORT PRODUCTION REPORT ---
if(isset($_POST['export_excel']) && $can_download) {
    $f = $_POST['date_f']; $t = $_POST['date_t']; $mod = $_POST['sel_model'];
    $filename = "Approved_Report_".$f.".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "Date\tLine\tSection\tModel\tUPH\tInput\tPacking\tShift\tDrafter\tH1\tH2\tH3\tH4\tH5\tH6\tH7\tH8\tH9\tH10\tH11\tH12\tFrom\tTo\tScrap\tRepair\tRemarks\tStatus\n";
    $sql = "SELECT * FROM production_records WHERE is_verified = 1 AND report_date BETWEEN '$f' AND '$t'";
    if(!empty($mod)) $sql .= " AND model_name = '$mod'";
    $res = $conn->query($sql);
    while($r = $res->fetch_assoc()) {
        $h12 = $r['h12_qty'] ?? 0;
        echo "{$r['report_date']}\t{$r['line_name']}\t{$r['section']}\t{$r['model_name']}\t{$r['uph']}\t{$r['total_qty']}\t{$r['input_qty']}\t{$r['shift']}\t{$r['drafter_name']}\t{$r['h1_qty']}\t{$r['h2_qty']}\t{$r['h3_qty']}\t{$r['h4_qty']}\t{$r['h5_qty']}\t{$r['h6_qty']}\t{$r['h7_qty']}\t{$r['h8_qty']}\t{$r['h9_qty']}\t{$r['h10_qty']}\t{$r['h11_qty']}\t$h12\t{$r['work_from']}\t{$r['work_to']}\t{$r['scrap_qty']}\t{$r['repair_qty']}\t{$r['remarks']}\tAPPROVED\n";
    }
    exit();
}

// --- 4. SHIFT CALCULATION LOGIC ---
$shift_data = [];
if((isset($_POST['calc_shift']) || isset($_POST['download_shift_report'])) && $can_download) {
    $f = $_POST['shift_f']; $t = $_POST['shift_t'];
    $sql = "SELECT report_date, line_name, shift, MAX(work_from) as work_from, MAX(work_to) as work_to FROM production_records WHERE report_date BETWEEN '$f' AND '$t' GROUP BY report_date, line_name, shift";
    $res = $conn->query($sql);
    if(isset($_POST['download_shift_report'])) {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"Shift_Time_Report.xls\"");
        echo "Date\tLine\tShift\tHours\n";
    }
    while($r = $res->fetch_assoc()) {
        $start = strtotime($r['work_from']);
        $end = strtotime($r['work_to']);
        if($end < $start) $end += 86400;
        $diff = round(($end - $start) / 3600, 2);
        if(isset($_POST['download_shift_report'])) {
            echo "{$r['report_date']}\t{$r['line_name']}\t{$r['shift']}\t$diff\n";
        } else {
            $shift_data[] = ['date' => $r['report_date'], 'line' => $r['line_name'], 'shift' => $r['shift'], 'hours' => $diff];
        }
    }
    if(isset($_POST['download_shift_report'])) exit();
}

// --- 5. CREATE USER ---
// if(isset($_POST['add_user']) && $can_user) {
//     $u = $conn->real_escape_string($_POST['uname']); 
//     $p = $conn->real_escape_string($_POST['pword']);
//     $sec_access = $_POST['access_sections'];
//     $role = ($sec_access == 'ALL') ? 'admin' : 'user';
//     $h = isset($_POST['p_history'])?1:0; $m = isset($_POST['p_model'])?1:0; $uc = isset($_POST['p_user'])?1:0; 
//     $dr = isset($_POST['p_download'])?1:0; $cd = isset($_POST['p_delete'])?1:0; $ce = isset($_POST['p_edit'])?1:0;
//     $conn->query("INSERT INTO users (username, password, role, assigned_section, can_view_history, can_create_model, can_create_user, can_download_report, can_delete_model, can_verify_report) 
//                   VALUES ('$u', '$p', '$role', '$sec_access', '$h', '$m', '$uc', '$dr', '$cd', '$ce')");
//     echo "<script>alert('User Created!');</script>";
// }
// --- 5. CREATE USER (UPDATED TO MATCH YOUR HEADERS) ---
if(isset($_POST['add_user']) && $can_user) {
    $u = $conn->real_escape_string($_POST['uname']); 
    $p = $conn->real_escape_string($_POST['pword']);
    $sec_access = $_POST['access_sections'];
    $role = ($sec_access == 'ALL') ? 'admin' : 'user';
    
    // Checkbox values
    $h = isset($_POST['p_history'])?1:0; 
    $m = isset($_POST['p_model'])?1:0; 
    $uc = isset($_POST['p_user'])?1:0; 
    $dr = isset($_POST['p_download'])?1:0; 
    $cd = isset($_POST['p_delete'])?1:0; 
    $ce = isset($_POST['p_edit'])?1:0;

    // Use the EXACT names from your image:
    // Change 'allowed_sections' to 'assigned_sections'
    // Change 'can_verify_report' to 'can_verify_' (Check your table for the full name)
    $sql = "INSERT INTO users (username, password, role, assigned_section, can_view_history, can_create_model, can_create_user, can_download_report, can_delete_model, can_verify_dpr) 
            VALUES ('$u', '$p', '$role', '$sec_access', '$h', '$m', '$uc', '$dr', '$cd', '$ce')";
            
    if($conn->query($sql)) {
        echo "<script>alert('User Created Successfully!');</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
// --- 6. MASTER DATA ENTRY ---
if(isset($_POST['add_item']) && $can_master) {
    $tbl = $_POST['tbl_name']; $val = $conn->real_escape_string($_POST['item_val']);
    if($tbl == 'models') {
        $sec = $_POST['model_section'];
        $conn->query("INSERT INTO models (model_name, section) VALUES ('$val', '$sec')");
    } elseif($tbl == 'supervisors') {
        $conn->query("INSERT INTO supervisors (supervisor_name) VALUES ('$val')");
    } elseif($tbl == 'hods') {
        $conn->query("INSERT INTO hods (hod_name) VALUES ('$val')");
    } else {
        $col = ($tbl == 'lines_list') ? 'line_name' : 'section_name';
        $conn->query("INSERT INTO $tbl ($col) VALUES ('$val')");
    }
}

if(isset($_GET['delete_model']) && $can_delete) {
    $id = intval($_GET['delete_model']);
    $conn->query("DELETE FROM models WHERE id = $id");
    header("Location: admin.php"); exit();
}

$sections_list = $conn->query("SELECT * FROM sections ORDER BY section_name ASC");
$model_list_dropdown = $conn->query("SELECT DISTINCT model_name FROM models ORDER BY model_name ASC");
$search_query = (isset($_POST['search_model'])) ? " WHERE model_name LIKE '%".$conn->real_escape_string($_POST['search_term'])."%'" : "";
$model_manage_list = $conn->query("SELECT * FROM models $search_query ORDER BY model_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard || RISHIK</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --warning: #f6c23e; --danger: #e74a3b; --dark: #2c3e50; --bg: #f8f9fc; --purple: #6f42c1; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); margin: 0; color: #333; font-size: 13px; }
        .navbar { background: var(--dark); padding: 10px 30px; color: white; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--primary); }
        .container { max-width: 1600px; margin: 20px auto; padding: 0 20px; min-height: 85vh; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(330px, 1fr)); gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-top: 5px solid #ddd; }
        .card h3 { margin-top: 0; font-size: 1rem; border-bottom: 2px solid #f8f9fc; padding-bottom: 10px; margin-bottom: 15px; color: var(--dark); font-weight: 600; }
        input, select { width: 100%; padding: 10px; margin: 8px 0 12px 0; border: 1px solid #d1d3e2; border-radius: 8px; font-size: 13px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; margin-bottom: 8px; }
        .btn-blue { background: var(--primary); } .btn-green { background: var(--success); } .btn-purple { background: var(--purple); } .btn-yellow { background: var(--warning); color: #333; }
        .perm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f8f9fc; padding: 12px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #eee; }
        .perm-grid label { font-size: 11px; display: flex; align-items: center; cursor: pointer; }
        .scroll-box { max-height: 200px; overflow-y: auto; border: 1px solid #eaecf4; border-radius: 8px; background: #fff; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #f8f9fc; padding: 10px; text-align: left; position: sticky; top: 0; }
        td { padding: 10px; border-bottom: 1px solid #f4f4f4; }
        .action-row { display: flex; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd; }
        .template-link { display: block; text-align: center; font-size: 12px; color: var(--primary); text-decoration: none; margin-top: 5px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 style="font-size:1.3rem; margin:0; letter-spacing: 1px;">DPR ADMIN PANEL</h2>
        <span>Admin: <strong><?php echo $_SESSION['username']; ?></strong></span>
    </div>

    <div class="container">
        <div class="grid">
            
            <?php if($can_download): ?>
            <div class="card" style="border-color: var(--primary);">
                <h3>📊 Approved Report Export</h3>
                <form method="POST">
                    <label>Select Model Filter:</label>
                    <select name="sel_model">
                        <option value="">-- All Models --</option>
                        <?php $model_list_dropdown->data_seek(0); while($m = $model_list_dropdown->fetch_assoc()) echo "<option value='{$m['model_name']}'>{$m['model_name']}</option>"; ?>
                    </select>
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1"><label>From:</label><input type="date" name="date_f" required></div>
                        <div style="flex:1"><label>To:</label><input type="date" name="date_t" required></div>
                    </div>
                    <button type="submit" name="export_excel" class="btn btn-blue">Download Excel Report</button>
                </form>
            </div>

            <div class="card" style="border-color: var(--purple);">
                <h3>⏱️ Shift Running Analysis</h3>
                <form method="POST">
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1"><label>Start Date:</label><input type="date" name="shift_f" required value="<?php echo $_POST['shift_f'] ?? ''; ?>"></div>
                        <div style="flex:1"><label>End Date:</label><input type="date" name="shift_t" required value="<?php echo $_POST['shift_t'] ?? ''; ?>"></div>
                    </div>
                    <button type="submit" name="calc_shift" class="btn btn-purple">Calculate Work Hours</button>
                    <?php if(!empty($shift_data)): ?>
                        <button type="submit" name="download_shift_report" class="btn btn-green">Download XLS</button>
                        <div class="scroll-box">
                            <table>
                                <thead><tr><th>Date</th><th>Line</th><th>Shift</th><th>Hrs</th></tr></thead>
                                <tbody>
                                    <?php foreach($shift_data as $sd): ?>
                                    <tr><td><?php echo $sd['date']; ?></td><td><?php echo $sd['line']; ?></td><td><?php echo $sd['shift']; ?></td><td style="color:var(--purple); font-weight:bold;"><?php echo $sd['hours']; ?>h</td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <?php if($can_user): ?>
            <div class="card" style="border-color: var(--success);">
                <h3>👤 User Access Control</h3>
                <form method="POST">
                    <input type="text" name="uname" placeholder="Enter Username" required>
                    <input type="password" name="pword" placeholder="Enter Password" required>
                    <select name="access_sections" required>
                        <option value="">-- Assign Section Access --</option>
                        <option value="ALL" style="background:#e8f5e9; font-weight:bold; color:#2e7d32;">⭐ FULL ACCESS (ALL SECTIONS)</option>
                        <?php $sections_list->data_seek(0); while($s = $sections_list->fetch_assoc()) echo "<option value='{$s['section_name']}'>{$s['section_name']}</option>"; ?>
                    </select>
                    <p style="margin:0 0 5px 0; font-size:11px; font-weight:bold;">Permissions:</p>
                    <div class="perm-grid">
                        <label><input type="checkbox" name="p_history"> View History</label>
                        <label><input type="checkbox" name="p_model"> Master Data</label>
                        <label><input type="checkbox" name="p_user"> User Mgmt</label>
                        <label><input type="checkbox" name="p_download"> Downloads</label>
                        <label><input type="checkbox" name="p_delete"> Delete Rights</label>
                        <label><input type="checkbox" name="p_edit"> Verify/Edit</label>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-green">Create Account</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if($can_master): ?>
            <div class="card" style="border-color: var(--warning);">
                <h3>⚙️ Master Entry & Setup</h3>
                <form method="POST">
                    <select name="tbl_name" onchange="document.getElementById('m_wrap_big').style.display = (this.value=='models')?'block':'none';">
                        <option value="sections">Add New Section</option>
                        <option value="models">Add New Model (Manual)</option>
                        <option value="lines_list">Add Assembly Line</option>
                        <option value="supervisors">Add Supervisor Name</option>
                        <option value="hods">Add HOD Name</option>
                    </select>
                    <div id="m_wrap_big" style="display:none;">
                        <label>Select Parent Section:</label>
                        <select name="model_section">
                            <?php $sections_list->data_seek(0); while($s = $sections_list->fetch_assoc()) echo "<option value='{$s['section_name']}'>{$s['section_name']}</option>"; ?>
                        </select>
                    </div>
                    <input type="text" name="item_val" placeholder="Enter name or value..." required>
                    <button type="submit" name="add_item" class="btn btn-yellow">Add to Database</button>
                </form>
                
                <div class="action-row">
                    <div style="flex:1">
                        <p style="margin:0 0 5px 0; font-weight:bold; font-size:11px;">Section XLS Download</p>
                        <form method="POST" style="display:flex; gap:5px;">
                            <select name="download_sec" style="margin:0;">
                                <option value="">-- All --</option>
                                <?php $sections_list->data_seek(0); while($s = $sections_list->fetch_assoc()) echo "<option value='{$s['section_name']}'>{$s['section_name']}</option>"; ?>
                            </select>
                            <button type="submit" name="download_partcodes" class="btn btn-blue" style="width:60px; margin:0; padding:5px;">XLS</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card" style="border-color: var(--danger);">
                <h3>🚀 Bulk Upload & Search</h3>
                <p style="margin:0 0 5px 0; font-weight:bold; font-size:11px;">Upload Models via CSV</p>
                <form method="POST" enctype="multipart/form-data" style="display:flex; gap:8px;">
                    <input type="file" name="csv_file" accept=".csv" required style="padding:6px; font-size:11px;">
                    <button type="submit" name="bulk_upload" class="btn btn-green" style="width:100px; margin:0;">Upload</button>
                </form>
                <a href="javascript:void(0)" onclick="downloadTemplate()" class="template-link">Download CSV Template</a>

                <div style="margin-top:20px; border-top: 1px solid #eee; padding-top:15px;">
                    <form method="POST" style="display: flex; gap: 8px;">
                        <input type="text" name="search_term" placeholder="Search model name..." style="margin:0;">
                        <button type="submit" name="search_model" class="btn btn-blue" style="width: 70px; margin:0;">Find</button>
                    </form>
                    <div class="scroll-box">
                        <table>
                            <thead><tr><th>Model Name</th><th>section</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php while($row = $model_manage_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['model_name']; ?></td>
                                    <td><?php echo $row['section']; ?></td>
                                    <td>
                                        <?php if($can_delete): ?>
                                        <a href="admin.php?delete_model=<?php echo $row['id']; ?>" onclick="return confirm('Confirm Delete?');" style="color:var(--danger); font-weight:bold; text-decoration:none;">Delete</a>
                                        <?php else: ?>
                                        <span style="color:#ccc;">Fixed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <div style="text-align:center; padding:30px;">
            <a href="index.php" style="text-decoration:none; color:var(--dark); font-weight:600; font-size:15px; border:2px solid var(--dark); padding:10px 25px; border-radius:30px;">← Back to Main Dashboard</a>
        </div>
    </div>

    <script>
    function downloadTemplate() {
        var csv = "ModelName,SectionName\nMODEL_SAMPLE_001,ASSEMBLY\nMODEL_SAMPLE_002,SMT";
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.setAttribute("download", "Model_Bulk_Upload_Template.csv");
        document.body.appendChild(link);
        link.click();
    }
    </script>
</body>
</html>