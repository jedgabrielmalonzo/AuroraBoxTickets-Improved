<?php
session_start();
require 'database.php'; // connection ($conn)

// check if logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Not logged in";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    $sender_id = $_SESSION['user_id'];
    $sender_type = "user"; // kasi normal user siya

    // dito depende kung paano mo gusto pumili ng receiver
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $receiver_type = $_POST['receiver_type'] ?? "admin"; // default admin halimbawa

    if ($subject !== "" && $body !== "" && $receiver_id > 0) {
        $stmt = $conn->prepare("
            INSERT INTO messages 
            (sender_id, sender_type, receiver_id, receiver_type, subject, body, parent_id, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NULL, 0, NOW())
        ");
        $stmt->bind_param("isisss", $sender_id, $sender_type, $receiver_id, $receiver_type, $subject, $body);

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Please fill in all fields.";
    }
}