<?php
require_once __DIR__ . '/config.php';
session_start();
require 'gClientSetup.php';

// Database connection
$mysqli = require __DIR__ . '/database.php';
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? 'message';

if ($type === 'announcement') {
    // Fetch announcement
    $stmt = $mysqli->prepare("SELECT * FROM email_announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    
    if ($message) {
        echo '<div class="p-3">';
        echo '<h4>' . htmlspecialchars($message['subject']) . '</h4>';
        echo '<small class="text-muted">📢 Announcement - ' . date("F j, Y g:i A", strtotime($message['sent_at'])) . '</small>';
        echo '<div class="mt-3">' . nl2br(htmlspecialchars($message['message'])) . '</div>';
        echo '</div>';
    } else {
        echo '<div class="text-muted text-center p-3">Announcement not found</div>';
    }
} else {
    // Fetch message and its thread (including replies)
    $stmt = $mysqli->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'customer' THEN u.firstname
                   WHEN m.sender_type = 'admin' THEN a.first_name
                   WHEN m.sender_type = 'vendor' THEN a.first_name
                   ELSE 'Unknown'
               END AS sender_name,
               CASE 
                   WHEN m.sender_type = 'customer' THEN 'Customer'
                   WHEN m.sender_type = 'admin' THEN 'Admin'
                   WHEN m.sender_type = 'vendor' THEN 'Vendor'
                   ELSE 'Unknown'
               END AS sender_display,
               m.sender_type
        FROM messages m
        LEFT JOIN user u ON (m.sender_type = 'customer' AND m.sender_id = u.id)
        LEFT JOIN adminuser a ON (m.sender_type IN ('admin', 'vendor') AND m.sender_id = a.id)
        WHERE (m.id = ? OR m.parent_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($messages)) {
        echo '<div class="text-muted text-center p-3">Message not found</div>';
        exit;
    }
    
    $original_msg = $messages[0];
    
    echo '<div class="p-3">';
    echo '<h4>' . htmlspecialchars($original_msg['subject']) . '</h4>';
    echo '<small class="text-muted">From: ' . htmlspecialchars($original_msg['sender_name'] ?? 'Unknown') . ' (' . ($original_msg['sender_display'] ?? 'Unknown') . ')</small>';
    echo '<small class="text-muted d-block">Started: ' . date("F j, Y g:i A", strtotime($original_msg['created_at'])) . '</small>';
    echo '<hr>';
    
    // Display message thread
    foreach ($messages as $msg) {
        $is_sent = ($msg['sender_id'] == $_SESSION['user_id'] && $msg['sender_type'] == 'customer');
        $sender_name = $msg['sender_name'] ?? 'Unknown';
        $sender_display = $msg['sender_display'] ?? 'Unknown';
        
        echo '<div class="message-bubble ' . ($is_sent ? 'message-sent' : 'message-received') . '">';
        
        if (!$is_sent) {
            echo '<div class="fw-bold" style="font-size: 0.85rem; margin-bottom: 4px;">' . 
                 htmlspecialchars($sender_name) . ' (' . htmlspecialchars($sender_display) . ')</div>';
        }
        
        echo '<div>' . nl2br(htmlspecialchars($msg['body'])) . '</div>';
        echo '<div class="message-time">' . date("M j, g:i A", strtotime($msg['created_at'])) . '</div>';
        echo '</div>';
    }
    
    // Reply form - only show if user has permission to reply

    if (isset($_SESSION['user_id'])) {
        echo '<form method="POST" action="inbox.php" class="mt-4">';
        echo '<input type="hidden" name="message_id" value="' . (int)$id . '">';
        echo '<input type="hidden" name="original_subject" value="' . htmlspecialchars($original_msg['subject']) . '">';
        echo '<input type="hidden" name="receiver_type" value="' . htmlspecialchars($original_msg['sender_type']) . '">';
        echo '<input type="hidden" name="receiver_id" value="' . (int)$original_msg['sender_id'] . '">';
        echo '<div class="mb-3">';
        echo '<label class="form-label">Reply:</label>';
        echo '<textarea name="reply_text" rows="3" class="form-control" placeholder="Type your reply..." required></textarea>';
        echo '</div>';
        echo '<div class="d-flex gap-2">';
        echo '<button type="submit" class="btn btn-primary">Send Reply</button>';
        echo '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'messageDetail\').innerHTML = \'<div class=\\\"text-muted text-center mt-5\\\">Select a message to read</div>\';">Close</button>';
        echo '</div>';
        echo '</form>';
    }

    
    echo '</div>';
    
    // Mark message as read (optional)
    if (isset($_SESSION['user_id'])) {
        $stmt = $mysqli->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $id, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
}

$mysqli->close();
?>