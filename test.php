<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection (centralized)
$mysqli = require __DIR__ . '/database.php';
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}
echo 'Connected successfully';
?>
