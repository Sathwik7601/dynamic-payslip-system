<?php
// Database Configuration
// Copy this file to config/connection.php and update with your credentials

$host     = 'localhost';
$username = 'root';       // your DB username
$password = '';           // your DB password
$database = 'payslip_db'; // your database name

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
