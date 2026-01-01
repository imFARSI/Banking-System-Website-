<?php


define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'finexa_banking');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (isset($_GET['logout_redirect'])) {
    session_destroy();
    session_start();
}

define('BASE_URL', 'http://localhost/finexa_banking/');
date_default_timezone_set('Asia/Dhaka');
?>