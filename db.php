<?php
$host = "sql###.infinityfree.com";  // ← their MySQL host
$user = "your_db_username";          // ← your DB username
$pass = "your_db_password";          // ← your DB password
$db   = "your_db_name";              // ← your DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>