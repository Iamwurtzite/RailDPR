<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location:login.php"); exit(); }

require_once 'db.php';

// 1. User Rights & Section Check
$u_id = $_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = '$u_id'")->fetch_assoc();

$is_admin = ($_SESSION['role'] == 'admin');
$has_verify_right = (isset($user_data['can_verify_dpr']) && $user_data['can_verify_dpr'] == 1);
$user_section = $user_data['assigned_section'] ?? '';

if(!$is_admin && !$has_verify_right) { echo "Access Denied!"; exit(); }

// 2. APPROVE & DECLINE Logic (Same as before)
if(isset($_GET['approve_id'])) {
    $aid = intval($_GET['approve_id']);
    $conn->query("UPDATE production_records SET is_verified = 1 WHERE id = $aid");
    header("Location: verify.php?msg=Approved"); exit();
}

if(isset($_GET['decline_id'])) {
    $did = intval($_GET['decline_id']);
    $conn->query("DELETE FROM production_records WHERE id = $did");
    header("Location: verify.php?msg=Declined_and_Deleted"); exit();
}

// --- NEW: FILTER & PAGINATION LOGIC ---

// Get Filter Values
$filter_date = $_GET['filter_date'] ?? '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // Default 10 rows
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Base Query
$where_clauses = ["is_verified = 0"];

// Section Filter
if(!$is_admin) {
    $where_clauses[] = "section = '$user_section'";
}

// Date Filter
if(!empty($filter_date)) {
    $where_clauses[] = "report_date = '$filter_date'";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

// Count total records for pagination
$total_res = $conn->query("SELECT COUNT(*) as count FROM production_records $where_sql");
$total_records = $total_res->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Fetch Data with Limit
$query = "SELECT * FROM production_records $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset";
$pending_list = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DPR APPROVED</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --success: #1cc88a; --danger: #e74a3b; --dark: #2c3e50; --bg: #f8f9fc; --blue: #4e73df; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); margin: 0; padding: 20px; }
        .header { background: var(--dark); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Filter Bar Style */
        .filter-bar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; color: #666; }
        .filter-bar input, .filter-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 5px; outline: none; }
        .btn-filter { background: var(--blue); color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; }

        .table-container { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f4f6f9; padding: 12px; text-align: left; border-bottom: 2px solid #eee; font-size: 0.85rem; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        
        .btn { padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; color: white; text-decoration: none; font-weight: 600; font-size: 0.75rem; }
        .btn-approve { background: var(--success); }
        .btn-decline { background: var(--danger); }
        .badge { background: #eee; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; }

        /* Pagination Style */
        .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 5px; }
        .pagination a { padding: 8px 12px; background: white; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 5px; font-size: 0.85rem; }
        .pagination a.active { background: var(--blue); color: white; border-color: var(--blue); }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <h2 style="margin:0;">Pending DPR Verification</h2>
            <small>Section: <?php echo $is_admin ? "ALL ACCESS" : $user_section; ?> | Total Pending: <?php echo $total_records; ?></small>
        </div>
        <a href="admin.php" style="color:white; font-size: 0.9rem;">← Back to Admin</a>
    </div>

    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label>Filter by Date</label>
            <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
        </div>
        <div class="filter-group">
            <label>Rows Per Page</label>
            <select name="limit">
                <option value="10" <?php if($limit==10) echo 'selected'; ?>>10 Rows</option>
                <option value="25" <?php if($limit==25) echo 'selected'; ?>>25 Rows</option>
                <option value="50" <?php if($limit==50) echo 'selected'; ?>>50 Rows</option>
                <option value="100" <?php if($limit==100) echo 'selected'; ?>>100 Rows</option>
            </select>
        </div>
        <button type="submit" class="btn-filter">Apply Filter</button>
        <a href="verify.php" style="font-size: 0.8rem; color: #666; margin-bottom: 8px;">Reset</a>
    </form>

    <?php if(isset($_GET['msg'])) echo "<p style='color:var(--success); font-weight:600; margin-left:10px;'>Action: ".htmlspecialchars($_GET['msg'])."</p>"; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Line</th>
                    <th>Section</th>
                    <th>Model</th>
                    <th>Qty</th>
                    <th>Drafter</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($pending_list->num_rows > 0): ?>
                    <?php while($row = $pending_list->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d-M-Y', strtotime($row['report_date'])); ?></td>
                        <td><?php echo $row['line_name']; ?></td>
                        <td><span class="badge"><?php echo $row['section']; ?></span></td>
                        <td><strong><?php echo $row['model_name']; ?></strong></td>
                        <td><?php echo $row['total_qty']; ?></td>
                        <td><?php echo $row['drafter_name']; ?></td>
                        <td style="display: flex; gap: 5px;">
                            <a href="verify.php?approve_id=<?php echo $row['id']; ?>" class="btn btn-approve" onclick="return confirm('Approve this DPR?')">Approve</a>
                            <a href="verify.php?decline_id=<?php echo $row['id']; ?>" class="btn btn-decline" onclick="return confirm('Decline will DELETE this record. Are you sure?')">Decline</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px; color: #999;">No records found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_pages > 1): ?>
    <div class="pagination">
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&filter_date=<?php echo $filter_date; ?>&limit=<?php echo $limit; ?>" 
               class="<?php echo ($page == $i) ? 'active' : ''; ?>">
               <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</body>
</html>