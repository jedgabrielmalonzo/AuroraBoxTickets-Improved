<?php
session_start();

// Database connection (centralized)
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
    die('Database connection failed.');
}
$conn = $mysqli;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bannerId = intval($_GET['id']);
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM banners WHERE id = ?");
    $stmt->bind_param("i", $bannerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $banner = $result->fetch_assoc();
        $currentStatus = $banner['status'];
        
        // Toggle status
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        
        // Update status
        $updateStmt = $conn->prepare("UPDATE banners SET status = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newStatus, $bannerId);
        
        if ($updateStmt->execute()) {
            $_SESSION['message'] = "Banner status updated to " . $newStatus;
        } else {
            $_SESSION['message'] = "Error updating banner status";
        }
        
        $updateStmt->close();
    } else {
        $_SESSION['message'] = "Banner not found";
    }
    
    $stmt->close();
} else {
    $_SESSION['message'] = "Invalid banner ID";
}

$conn->close();

// Redirect back to banners page
header("Location: banners.php");
exit();
?>