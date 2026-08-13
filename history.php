<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once 'db.php';

// User details and Permissions from session
$user_section = strtolower(trim($_SESSION['assigned_section'] ?? '')); 
$user_role    = strtolower(trim($_SESSION['role'] ?? 'user'));
$can_edit     = (int)($_SESSION['can_edit_report'] ?? 0);
$can_delete   = (int)($_SESSION['can_delete_report'] ?? 0);

// --- FETCH DYNAMIC LINES & SECTIONS ---
$line_res = $conn->query("SELECT DISTINCT line_name FROM `lines_list` ORDER BY line_name ASC");
$sec_res  = $conn->query("SELECT DISTINCT section FROM `production_records` WHERE section != '' ORDER BY section ASC");

// --- PAGINATION SYSTEM ---
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$filter_query = " WHERE is_deleted = 0 ";
$params = "";

// Filter Logic
if (!empty($_GET['f_model'])) {
    $m = $conn->real_escape_string($_GET['f_model']);
    $filter_query .= " AND model_name LIKE '%$m%' ";
    $params .= "&f_model=".urlencode($m);
}
if (!empty($_GET['f_date'])) {
    $d = $conn->real_escape_string($_GET['f_date']);
    $filter_query .= " AND report_date = '$d' ";
    $params .= "&f_date=".urlencode($d);
}
if (!empty($_GET['f_line'])) {
    $l = $conn->real_escape_string($_GET['f_line']);
    $filter_query .= " AND line_name = '$l' ";
    $params .= "&f_line=".urlencode($l);
}
if (!empty($_GET['f_section'])) {
    $s = $conn->real_escape_string($_GET['f_section']);
    $filter_query .= " AND section = '$s' ";
    $params .= "&f_section=".urlencode($s);
}
if (isset($_GET['f_status']) && $_GET['f_status'] !== "") {
    $status = (int)$_GET['f_status'];
    $filter_query .= " AND is_verified = $status ";
    $params .= "&f_status=$status";
}

// Total Count for Pagination
$count_res = $conn->query("SELECT COUNT(DISTINCT report_id) AS total FROM production_records $filter_query");
$total_rows = $count_res->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Main Query
$sql = "SELECT report_id, 
               MAX(report_date) as report_date, 
               MAX(line_name) as line_name, 
               MAX(shift) as shift, 
               MAX(section) as section,
               MAX(drafter_name) as drafter_name,
               MAX(supervisor_name) as supervisor_name,
               MAX(is_verified) as is_verified, 
               MAX(updated_at) as updated_at 
        FROM production_records 
        $filter_query 
        GROUP BY report_id 
        ORDER BY report_date DESC, updated_at DESC 
        LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DPR History</title>
    <style>
        body { font-family: 'Segoe UI', Arial; background: #f0f2f5; padding: 20px; margin: 0; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .filter-card { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; border: 1px solid #dee2e6; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        input, select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; width: 100%; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2c3e50; color: white; padding: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px; border-bottom: 1px solid #eee; text-align: center; font-size: 13px; }
        .badge { padding: 4px 10px; border-radius: 15px; font-size: 10px; font-weight: bold; }
        .bg-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .bg-approved { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .btn { padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 11px; cursor: pointer; border: none; display: inline-block; transition: 0.2s; }
        .btn-home { background: #34495e; color: white; }
        .btn-trash { background: #607d8b; color: white; margin-left: 10px; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-view { background: #17a2b8; color: #fff; }
        .btn-delete { background: #dc3545; color: #fff; margin-left: 5px; }
        .btn:hover { opacity: 0.85; transform: translateY(-1px); }
        
        .pagination { margin-top: 25px; display: flex; justify-content: center; align-items: center; gap: 5px; }
        .page-link { padding: 8px 14px; border: 1px solid #dee2e6; color: #2c3e50; text-decoration: none; background: #fff; border-radius: 4px; transition: 0.3s; }
        .page-link.active { background: #2c3e50; color: #fff; border-color: #2c3e50; }
        .page-disabled { padding: 8px 14px; color: #ccc; border: 1px solid #eee; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-flex">
        <div>
            <a href="index.php" class="btn btn-home">🏠 Home</a>
            <a href="trash_bin.php" class="btn btn-trash">🗑️ Trash Bin</a>
            <h2 style="display:inline; color:#2c3e50; margin-left:15px; vertical-align: middle;">📊 Production History</h2>
        </div>
        <div style="background: #2c3e50; color: white; padding: 8px 15px; border-radius: 5px; font-size: 12px; border-left: 4px solid #ffc107;">
            Logged in as: <b style="color:#ffc107;"><?php echo strtoupper($_SESSION['username']); ?></b> | Access: <b><?php echo strtoupper($user_section ?: 'Global'); ?></b>
        </div>
    </div>

    <form method="GET" class="filter-card">
        <div class="filter-group">
            <label style="font-size:10px; font-weight:bold;">SECTION</label>
            <select name="f_section">
                <option value="">All Sections</option>
                <?php while($s = $sec_res->fetch_assoc()) { 
                    $sel = (($_GET['f_section']??'') == $s['section']) ? 'selected' : '';
                    echo "<option value='".$s['section']."' $sel>".strtoupper($s['section'])."</option>";
                } ?>
            </select>
        </div>
        <div class="filter-group">
            <label style="font-size:10px; font-weight:bold;">LINE</label>
            <select name="f_line">
                <option value="">All Lines</option>
                <?php $line_res->data_seek(0); while($l = $line_res->fetch_assoc()) { 
                    $sel = (($_GET['f_line']??'') == $l['line_name']) ? 'selected' : '';
                    echo "<option value='".$l['line_name']."' $sel>".$l['line_name']."</option>";
                } ?>
            </select>
        </div>
    <div class="filter-group">
            <label style="font-size:10px; font-weight:bold;">STATUS</label>
            <select name="f_status">
                <option value="">All Status</option>
                <option value="0" <?php echo (isset($_GET['f_status']) && $_GET['f_status'] === '0') ? 'selected' : ''; ?>>PENDING</option>
                <option value="1" <?php echo (isset($_GET['f_status']) && $_GET['f_status'] === '1') ? 'selected' : ''; ?>>APPROVED</option>
            </select>
        </div>
        <div class="filter-group">
            <label style="font-size:10px; font-weight:bold;">DATE</label>
            <input type="date" name="f_date" value="<?php echo $_GET['f_date'] ?? ''; ?>">
        </div>
        <div class="filter-group">
            <label style="font-size:10px; font-weight:bold;">MODEL SEARCH</label>
            <input type="text" name="f_model" placeholder="Model name..." value="<?php echo $_GET['f_model'] ?? ''; ?>">
        </div>
        
        <div style="display:flex; gap:5px;">
            <button type="submit" class="btn" style="background:#28a745; color:white; height:36px;">🔍 Filter</button>
            <a href="history.php" class="btn" style="background:#6c757d; color:white; height:20px;">Reset</a>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Section</th><th>Line</th><th>Shift</th><th>Drafter</th><th>Approver</th><th>Status</th><th>Last Update</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($res && $res->num_rows > 0): ?>
                <?php while($row = $res->fetch_assoc()): 
                    $db_sec = strtolower(trim($row['section']));
                    $has_edit_right = ($user_role === 'admin' || ($user_section === 'all') || ($user_section === $db_sec && $can_edit === 1));
                    $has_del_right = ($user_role === 'admin' || ($user_section === 'all') || ($user_section === $db_sec && $can_delete === 1));
                ?>
                <tr>
                    <td><b><?php echo date('d-M-y', strtotime($row['report_date'])); ?></b></td>
                    <td><span style="color:#d35400; font-weight:bold;"><?php echo strtoupper($row['section']); ?></span></td>
                    <td><?php echo $row['line_name']; ?></td>
                    <td><?php echo $row['shift']; ?></td>
                    <td><?php echo $row['drafter_name']; ?></td>
                    <td><?php echo $row['supervisor_name']; ?></td>
                    <td>
                        <span class="badge <?php echo ($row['is_verified'] == 1) ? 'bg-approved' : 'bg-pending'; ?>">
                            <?php echo ($row['is_verified'] == 1) ? 'APPROVED' : 'PENDING'; ?>
                        </span>
                    </td>
                    <td style="font-size:11px; color:#666;"><?php echo $row['updated_at'] ? date('d/m H:i', strtotime($row['updated_at'])) : '--'; ?></td>
                    <td>
                        <?php if($has_edit_right): ?>
                            <a href="view_report.php?id=<?php echo $row['report_id']; ?>" class="btn btn-edit">📝 VERIFY DPR</a>
                        <?php else: ?>
                            <a href="view_report.php?id=<?php echo $row['report_id']; ?>&mode=view" class="btn btn-view">👁️ View Only</a>
                        <?php endif; ?>

                        <?php if($has_del_right): ?>
                            <a href="delete_report.php?report_id=<?php echo $row['report_id']; ?>" class="btn btn-delete">🗑️ Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="padding:60px; color:#999; font-style:italic;">No records found matching your selection.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if($total_pages > 1): ?>
    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?page=1<?php echo $params; ?>" class="page-link">««</a>
            <a href="?page=<?php echo ($page-1).$params; ?>" class="page-link">Prev</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for($i=$start; $i<=$end; $i++): ?>
            <a href="?page=<?php echo $i.$params; ?>" class="page-link <?php echo ($page==$i)?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo ($page+1).$params; ?>" class="page-link">Next</a>
            <a href="?page=<?php echo $total_pages.$params; ?>" class="page-link">»»</a>
        <?php endif; ?>
    </div>
    <div style="text-align:center; font-size:11px; margin-top:10px; color:#666;">
        Page <?php echo $page; ?> / <?php echo $total_pages; ?> | Total <?php echo $total_rows; ?> Reports
    </div>
    <?php endif; ?>
</div>

<div class="footer-gold" style="margin-top:60px; text-align:center; font-size:14px; border-top:1px solid #a89e9e; padding:10px; color:#555; background-color: #3be7f33f;">
      <a href="https://www.iljin.co.in/">COPYRIGHT © 2026 ILJIN ELECTRONICS INDIA PVT LTD || MADE BY RISHIK UPADHYAY</a>
</div>
</body>
</html>