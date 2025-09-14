<?php
$host = "localhost";      // usually localhost
$user = "root";           // default user for XAMPP/Laragon
$pass = "";               // leave blank if no password set
$dbname = "swks_db";      // your database name

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
