<?php
$mysqli = require __DIR__ . "/../../database.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['email'])) {
    
    // Sanitize input
    $id = intval($_POST['id']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }
    
    // First, find out which table the user is in and get current data
    $tables = [
        'user' => ['table' => 'user', 'firstname_col' => 'firstname', 'lastname_col' => 'lastname'],
        'vendor' => ['table' => 'vendor', 'firstname_col' => 'firstname', 'lastname_col' => 'lastname'],
        'adminuser' => ['table' => 'adminuser', 'firstname_col' => 'first_name', 'lastname_col' => 'last_name']
    ];
    
    $table_found = null;
    $current_email = '';
    
    foreach ($tables as $role => $config) {
        $check_sql = "SELECT id, email FROM {$config['table']} WHERE id = ?";
        if ($check_stmt = $mysqli->prepare($check_sql)) {
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                $current_email = $user_data['email'];
                $table_found = $config;
                $table_found['role'] = $role;
                $check_stmt->close();
                break;
            }
            $check_stmt->close();
        }
    }
    
    if (!$table_found) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    
    // Check if email is being changed and if the new email already exists
    if ($current_email !== $email) {
        $email_check_queries = [
            "SELECT id FROM user WHERE email = ? AND id != ?",
            "SELECT id FROM vendor WHERE email = ? AND id != ?",
            "SELECT id FROM adminuser WHERE email = ? AND id != ?"
        ];
        
        foreach ($email_check_queries as $query) {
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists in the system.']);
                exit;
            }
            $stmt->close();
        }
    }
    
    // Update the user in the appropriate table
    $update_sql = "UPDATE {$table_found['table']} SET {$table_found['firstname_col']}=?, {$table_found['lastname_col']}=?, email=? WHERE id=?";
    
    if ($stmt = $mysqli->prepare($update_sql)) {
        $stmt->bind_param("sssi", $firstname, $lastname, $email, $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'User updated successfully!',
                    'user' => [
                        'id' => $id,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'email' => $email
                    ]
                ]);
            } else {
                // Check if no changes were made (same data)
                echo json_encode([
                    'success' => true,
                    'message' => 'No changes were made.',
                    'user' => [
                        'id' => $id,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'email' => $email
                    ]
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $mysqli->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
}
?>