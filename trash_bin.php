<?php
session_start();
// Security Check: Only Admin can access Trash Bin
if(!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') { 
    echo "<h1 style='color:red; text-align:center; margin-top:50px;'>🚫 Access Denied! Admins Only.</h1>";
    echo "<p style='text-align:center;'><a href='history.php'>Go Back to History</a></p>";
    exit(); 
}

require_once 'db.php';
$MASTER_ADMIN_PASSWORD = "Iamwurtzite@#8445"; 

// 1. RESTORE LOGIC
if(isset($_GET['restore_id'])) {
    $id = $conn->real_escape_string($_GET['restore_id']);
    // Restore and clear the deleted_by tag
    $conn->query("UPDATE production_records SET is_deleted = 0, deleted_by = NULL WHERE report_id = '$id'");
    echo "<script>alert('Report Restored Successfully!'); window.location='history.php';</script>";
}

// 2. PERMANENT DELETE LOGIC
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['perm_delete_id'])) {
    $id = $conn->real_escape_string($_POST['perm_delete_id']);
    $pass = $_POST['admin_pass'];
    
    if($pass === $MASTER_ADMIN_PASSWORD) {
        $conn->query("DELETE FROM production_records WHERE report_id = '$id'");
        echo "<script>alert('Data Permanently Erased!'); window.location='trash_bin.php';</script>";
    } else {
        echo "<script>alert('Incorrect Master Password! Deletion Blocked.'); window.location='trash_bin.php';</script>";
    }
}

// Fetch only deleted records
$sql = "SELECT DISTINCT report_id, report_date, line_name, deleted_by, updated_at 
        FROM production_records 
        WHERE is_deleted = 1 
        ORDER BY updated_at DESC";
$res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trash Bin - Secure Admin Area</title>
    <style>
        body { font-family: 'Segoe UI', Arial; background: #fdf2f2; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
        .header-box { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #dc3545; padding-bottom: 15px; margin-bottom: 20px; }
        h2 { color: #dc3545; margin: 0; display: flex; align-items: center; gap: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dc3545; color: white; padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .btn { padding: 8px 16px; border-radius: 5px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; font-size: 12px; transition: 0.2s; }
        .btn-restore { background: #28a745; color: white; margin-right: 5px; }
        .btn-perm { background: #000; color: white; }
        .btn-restore:hover { background: #218838; }
        .btn-perm:hover { background: #333; }
        .deleter-info { color: #d32f2f; font-weight: bold; background: #ffebee; padding: 3px 8px; border-radius: 4px; border: 1px solid #ffcdd2; font-size: 11px; }
        
        /* Modal UI */
        #passwordModal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); align-items: center; justify-content: center; backdrop-filter: blur(4px);
        }
        .modal-box {
            background: white; padding: 35px; border-radius: 20px; width: 400px; text-align: center;
            border-top: 8px solid #000;
        }
        .modal-box input[type="password"] {
            width: 100%; padding: 15px; margin: 20px 0; border: 2px solid #ddd; border-radius: 10px;
            font-size: 18px; text-align: center; outline: none;
        }
        .modal-box input:focus { border-color: #dc3545; }
    </style>
</head>
<body>

<div id="passwordModal">
    <div class="modal-box">
        <h3 style="color:#000; margin:0;">🛑 FINAL CONFIRMATION</h3>
        <p style="color:#666; font-size:13px;">This data will be <b>destroyed forever</b>.<br>Type the Master Password to proceed:</p>
        
        <input type="password" id="admin_pass_input" placeholder="••••••••">
        <input type="hidden" id="target_report_id">

        <div style="display:flex; gap:10px; justify-content:center;">
            <button onclick="submitPermanentDelete()" class="btn btn-perm" style="flex:1;">DESTROY DATA</button>
            <button onclick="closeModal()" class="btn" style="background:#6c757d; color:white; flex:1;">CANCEL</button>
        </div>
    </div>
</div>

<div class="container">
    <div class="header-box">
        <h2>🗑️ TRASH BIN (ADMIN ONLY)</h2>
        <a href="history.php" style="color:#666; text-decoration:none; font-weight:bold;">✖ CLOSE BIN</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Report Date</th>
                <th>Report ID</th>
                <th>Deleted By (Username)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($res && $res->num_rows > 0): ?>
                <?php while($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><b><?php echo date('d-M-Y', strtotime($row['report_date'])); ?></b></td>
                    <td><code style="background:#f4f4f4; padding:2px 5px;"><?php echo $row['report_id']; ?></code></td>
                    <td>
                        <span class="deleter-info">
                            👤 <?php echo htmlspecialchars($row['deleted_by'] ?? 'Manual System'); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?restore_id=<?php echo $row['report_id']; ?>" class="btn btn-restore" onclick="return confirm('Restore this report to history?')">🔄 RESTORE</a>
                        <button onclick="openModal('<?php echo $row['report_id']; ?>')" class="btn btn-perm">🔥 PERMANENT</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" align="center" style="padding:60px; color:#aaa;">Trash Bin is currently empty.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function openModal(reportId) {
        document.getElementById('target_report_id').value = reportId;
        document.getElementById('passwordModal').style.display = 'flex';
        document.getElementById('admin_pass_input').value = '';
        document.getElementById('admin_pass_input').focus();
    }

    function closeModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }

    function submitPermanentDelete() {
        let reportId = document.getElementById('target_report_id').value;
        let pass = document.getElementById('admin_pass_input').value;

        if (!pass) { alert("Master Password required!"); return; }

        let form = document.createElement("form");
        form.method = "POST";
        form.action = "trash_bin.php";
        form.innerHTML = `
            <input type='hidden' name='perm_delete_id' value='${reportId}'>
            <input type='hidden' name='admin_pass' value='${pass}'>
        `;
        document.body.appendChild(form);
        form.submit();
    }

    window.onclick = function(e) {
        if (e.target == document.getElementById('passwordModal')) closeModal();
    }
</script>

</body>
</html>