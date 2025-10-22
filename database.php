<?php
require_once __DIR__ . '/config.php';

$servername = DB_HOST;
$username   = DB_USER;
$password   = DB_PASS;
$dbname     = DB_NAME;

$mysqli = @new mysqli($servername, $username, $password, $dbname);

if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
}

// Force timezone to Asia/Manila if connected
if ($mysqli && $mysqli->connect_errno === 0) {
    @$mysqli->query("SET time_zone = '+08:00'");
}

return $mysqli;
