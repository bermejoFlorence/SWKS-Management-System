<?php
/* SWKS – MySQL connection for Hostinger */
$host = 'localhost';                     // OR the "MySQL Host" shown in hPanel
$port = 3306;                            // default
$db   = 'u578970591_swks_db';            // from your screenshot
$user = 'u578970591_swks';               // from your screenshot
$pass = 'Swks_db2025'; // the password you set for this DB user

// Create connection with sane defaults
$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

if (!@mysqli_real_connect($conn, $host, $user, $pass, $db, $port)) {
    // Friendly fatal with log
    error_log('DB connect error: '. mysqli_connect_error());
    http_response_code(500);
    exit('Sorry, the database connection failed. Please try again later.');
}

// Charset & timezone (good for PHP + UTF-8 apps in PH)
if (!@$conn->set_charset('utf8mb4')) {
    error_log('Failed to set charset: '.$conn->error);
}
@$conn->query("SET time_zone = '+08:00'"); // Asia/Manila

// Optional: strict-ish SQL mode (comment out if you hit legacy issues)
// @$conn->query("SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE'");
