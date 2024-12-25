<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "bracket_messenger";

$conn = mysqli_connect($host, $user, $pass, $dbname);
mysqli_set_charset($conn, "utf8mb4");

if (!$conn){
    die("Database connection failed: " . mysqli_connect_error());
}
?>
