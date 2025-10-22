<?php
$mysqli = require __DIR__ . "/../../database.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // First, find out which table the user is in
    $tables = [
        'user' => 'user',
        'vendor' => 'vendor', 
        'adminuser' => 'adminuser'
    ];
    
    $deleted = false;
    $table_found = '';
    
    foreach ($tables as $table => $table_name) {
        // Check if user exists in this table
        $check_sql = "SELECT id FROM $table_name WHERE id = ?";
        if ($check_stmt = $mysqli->prepare($check_sql)) {
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $table_found = $table_name;
                $check_stmt->close();
                break;
            }
            $check_stmt->close();
        }
    }
    
    if ($table_found) {
        // Delete from the found table
        $delete_sql = "DELETE FROM $table_found WHERE id = ?";
        if ($stmt = $mysqli->prepare($delete_sql)) {
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No user found with that ID.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found in any table.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request or missing user ID.']);
}
?>