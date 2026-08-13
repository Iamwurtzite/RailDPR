<?php
session_start();
require_once 'db.php';

if (isset($_POST['login'])) {
    $u = $_POST['username']; 
    $p = $_POST['password'];
    
    $u = $conn->real_escape_string($u);
    $p = $conn->real_escape_string($p);

    // Query to fetch user with all rights
    $res = $conn->query("SELECT * FROM users WHERE username='$u' AND password='$p'");
    
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        
        // Basic Session Info
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['assigned_section'] = $user['assigned_section'];

        // --- Permissions Activation (SQL Table Based) ---
        $_SESSION['can_view_history'] = $user['can_view_history'] ?? 0;
        $_SESSION['can_create_model'] = $user['can_create_model'] ?? 0;
        $_SESSION['can_create_user'] = $user['can_create_user'] ?? 0;
        $_SESSION['can_edit_report'] = $user['can_edit_report'] ?? 0;
        $_SESSION['can_download_report'] = $user['can_download_report'] ?? 0;
        $_SESSION['can_delete_report'] = $user['can_delete_report'] ?? 0;
        $_SESSION['can_delete_model'] = $user['can_delete_model'] ?? 0;

        // Redirect to dashboard
        header("Location: index.php");
        exit();
    } else { 
        echo "<script>alert('Invalid Credential');</script>"; 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login ||Daily Production Report</title>
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe200 100%); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0;
        }
         #bg-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -2; /* Sabse peeche */
            object-fit: cover;
        }

        /* Overlay taaki form clear dikhe */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4); /* Black transparent layer */
            z-index: -1;
        }
        .login-card {
            background: rgba(0, 0, 0, 0.16);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.93);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo-container { margin-bottom: 20px; }
        .logo-text { margin: 0; z-index: -1; }
        .logo-text img { width: 220px; height: auto;  }
        .subtitle { color: #e9b422; font-size: 14px; margin-top: 10px; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #e3f032; font-weight: 600; font-size: 14px; }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
        }
        input:focus { border-color: #ff6600; box-shadow: 0 0 8px rgba(255, 102, 0, 0.2); }
        
        button {
            width: 100%;
            padding: 14px;
            background: #ff6600;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 10px;
        }
        button:hover { background: #e65c00; transform: translateY(-2px); }
        button:active { transform: translateY(0); }
        
        .footer-text { margin-top: 25px; font-size: 12px;  font-weight: bold; color: #09d0f3; cursor: pointer;}
    </style>
</head>
<body>
<video autoplay muted loop id="bg-video">
    <source src="images/VIDEO.mp4" type="video/mp4">
    Your browser does not support HTML5 video.
</video>
<div class="login-card">
    <div class="logo-container">
        <h1 class="logo-text"><img src="images/iljin logo.png" alt="ILJIN Logo"></h1>
        <div class="subtitle">Daily Production Report</div>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter your username" required autocomplete="off">
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
        </div>
        
        <button type="submit" name="login">LOG IN</button>
    </form>
    
    <div class="footer-text">
        <a href="https://www.iljin.co.in/">&copy; 2026 ILJIN Electronics India Pvt. Ltd.</a>
    </div>
</div>

</body>
</html>