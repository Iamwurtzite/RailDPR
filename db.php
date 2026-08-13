<?php
$db_host = getenv('MYSQLHOST') ?: 'localhost';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: 'Rishik@8445';
$db_name = getenv('MYSQLDATABASE') ?: 'DAILY_PRODUCTION_REPORT';
$db_port = getenv('MYSQLPORT') ?: '3306';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}
?>
