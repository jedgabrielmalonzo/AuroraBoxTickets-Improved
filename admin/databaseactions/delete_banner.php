<?php
session_start();

// Database connection (centralized)
$mysqli = require __DIR__ . '/../../database.php';
if (!$mysqli || $mysqli->connect_error) {
    die('Database connection failed.');
}
$conn = $mysqli;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bannerId = intval($_GET['id']);
    
    // Get banner filename before deleting
    $stmt = $conn->prepare("SELECT filename FROM banners WHERE id = ?");
    $stmt->bind_param("i", $bannerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $banner = $result->fetch_assoc();
        $filename = $banner['filename'];
        $filepath = "banners/" . $filename;
        
        // Delete from database
        $deleteStmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
        $deleteStmt->bind_param("i", $bannerId);
        
        if ($deleteStmt->execute()) {
            // Delete physical file if it exists
            if (file_exists($filepath)) {
                if (unlink($filepath)) {
                    $_SESSION['message'] = "Banner deleted successfully";
                } else {
                    $_SESSION['message'] = "Banner deleted from database, but file could not be removed";
                }
            } else {
                $_SESSION['message'] = "Banner deleted from database (file was already missing)";
            }
        } else {
            $_SESSION['message'] = "Error deleting banner from database";
        }
        
        $deleteStmt->close();
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