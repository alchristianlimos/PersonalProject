<?php
date_default_timezone_set('Asia/Manila');

$host = "sql104.infinityfree.com";
$user = "if0_41422884";
$pass = "4eho3HbAUAh2";            
$db   = "if0_41422884_db_neulibrary";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<h2 style='color:red;font-family:sans-serif;padding:20px'>
        ❌ Database connection failed: " . $conn->connect_error . "
        <br><small>Make sure MySQL is running.</small>
    </h2>");
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");
?>