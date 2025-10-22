<?php
session_start();
header('Content-Type: application/json');

// Simple debug endpoint
echo json_encode([
    "success" => true,
    "message" => "Debug endpoint working",
    "session_user_id" => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
    "post_data" => $_POST,
    "get_data" => $_GET,
    "current_time" => date('Y-m-d H:i:s'),
    "php_errors" => error_get_last()
]);
?>