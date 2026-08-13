<?php
session_start();
// Agar user login nahi hai toh login page par bhejein
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once 'db.php';

// Report ID check
if (!isset($_GET['report_id'])) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>Error: Report ID is missing!</h2>");
}

$report_id = $conn->real_escape_string($_GET['report_id']);
$current_user = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Unknown User'; 

$error = "";

// --- DELETE LOGIC AFTER PASSWORD VERIFICATION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    $password = $_POST['admin_password'];
    
    // Aapka set kiya hua password (Isse aap change kar sakte hain)
    if ($password === "Iamwurtzite@#8445") { 
        // Update query: setting is_deleted AND identifying the user who did it
        $sql = "UPDATE production_records SET 
                is_deleted = 1, 
                deleted_by = '$current_user' 
                WHERE report_id = '$report_id'";
        
        if ($conn->query($sql)) {
            echo "<script>
                    alert('Report ID: $report_id moved to Trash Bin by $current_user'); 
                    window.location.href='history.php';
                  </script>";
            exit();
        } else {
            $error = "Database Error: " . $conn->error;
        }
    } else {
        $error = "❌ Incorrect Password! Access Denied.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Deletion - DPR</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .delete-box { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.15); width: 100%; max-width: 400px; text-align: center; border-top: 6px solid #dc3545; animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        h2 { color: #dc3545; margin-top: 0; font-size: 24px; }
        p { color: #666; font-size: 14px; line-height: 1.6; }
        .id-badge { background: #fff5f5; color: #dc3545; padding: 3px 10px; border-radius: 5px; border: 1px solid #ffc1c1; font-weight: bold; }
        .input-group { margin: 25px 0; text-align: left; }
        label { font-size: 11px; font-weight: bold; color: #444; text-transform: uppercase; letter-spacing: 1px; margin-left: 2px; }
        input[type="password"] { width: 100%; padding: 12px; margin-top: 8px; border: 2px solid #eee; border-radius: 8px; outline: none; transition: 0.3s; box-sizing: border-box; font-size: 18px; text-align: center; }
        input[type="password"]:focus { border-color: #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.1); }
        .btn-container { display: flex; gap: 12px; margin-top: 10px; }
        .btn { flex: 1; padding: 12px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-confirm { background: #dc3545; color: white; }
        .btn-confirm:hover { background: #bd2130; }
        .btn-cancel { background: #e9ecef; color: #495057; }
        .btn-cancel:hover { background: #dde2e6; }
        .error-msg { color: #721c24; background: #f8d7da; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="delete-box">
    <h2>⚠️ Confirm Delete</h2>
    <p>You are moving report <span class="id-badge"><?php echo $report_id; ?></span> to the Trash Bin. This action will be logged under your username: <b><?php echo $current_user; ?></b>.</p>
    
    <?php if($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label>Admin Access Password</label>
            <input type="password" name="admin_password" placeholder="••••••••" required autofocus>
        </div>

        <div class="btn-container">
            <a href="history.php" class="btn btn-cancel">Go Back</a>
            <button type="submit" name="confirm_delete" class="btn btn-confirm">Move to Trash</button>
        </div>
    </form>
</div>

</body>
</html>