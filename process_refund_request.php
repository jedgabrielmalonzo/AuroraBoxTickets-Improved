<?php
require_once __DIR__ . '/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $booking_id = $_POST['booking_id'] ?? '';
    $payment_ref = $_POST['payment_ref'] ?? '';
    $refund_reason = $_POST['refund_reason'] ?? '';
    $other_reason = $_POST['other_reason'] ?? '';
    $additional_comments = $_POST['additional_comments'] ?? '';
    
    // Validate required fields
    if (empty($booking_id) || empty($refund_reason)) {
        $_SESSION['error_message'] = "Please fill in all required fields.";
        header("Location: account.php#bookings");
        exit;
    }
    
    // If "other" is selected, make sure other_reason is provided
    if ($refund_reason === 'other' && empty($other_reason)) {
        $_SESSION['error_message'] = "Please specify the reason for refund.";
        header("Location: account.php#bookings");
        exit;
    }
    
    // Verify that the booking belongs to the current user and is confirmed
    $verify_sql = "SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'confirmed'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid booking or booking cannot be refunded.";
        header("Location: account.php#bookings");
        exit;
    }
    
    $booking = $verify_result->fetch_assoc();
    $verify_stmt->close();
    
    // Check if refund request already exists
    $check_sql = "SELECT id FROM refund_requests WHERE booking_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "A refund request has already been submitted for this booking.";
        header("Location: account.php#bookings");
        exit;
    }
    $check_stmt->close();
    
    // Prepare the final reason text
    $final_reason = $refund_reason;
    if ($refund_reason === 'other' && !empty($other_reason)) {
        $final_reason = $other_reason;
    }
    
    // Insert refund request
    $insert_sql = "INSERT INTO refund_requests (user_id, booking_id, payment_ref, reason, additional_comments, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iisss", $user_id, $booking_id, $payment_ref, $final_reason, $additional_comments);
    
    if ($insert_stmt->execute()) {
        // Update booking status to 'refund_requested'
        $update_sql = "UPDATE orders SET status = 'refund_requested' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Send notification email to admin (optional)
        // You can implement email notification here
        
        $_SESSION['success_message'] = "Your refund request has been submitted successfully. Our admin team will review it within 3-5 business days.";
    } else {
        $_SESSION['error_message'] = "Failed to submit refund request. Please try again.";
    }
    
    $insert_stmt->close();
    
} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

$conn->close();
header("Location: account.php#bookings");
exit;
?>