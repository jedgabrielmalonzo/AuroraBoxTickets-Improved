<?php
$mysqli = require __DIR__ . "/../../database.php";

header('Content-Type: application/json');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log("POST data: " . print_r($_POST, true));
    
    $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
    $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';

    // Validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    $valid_roles = ['user', 'vendor', 'admin'];
    if (!in_array($role, $valid_roles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists in any table
    $email_check_queries = [
        "SELECT id FROM user WHERE email = ?",
        "SELECT id FROM vendor WHERE email = ?", 
        "SELECT id FROM adminuser WHERE email = ?"
    ];

    foreach ($email_check_queries as $query) {
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed for email check: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Database error during email validation.']);
            exit;
        }
        
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists in the system.']);
            exit;
        }
        $stmt->close();
    }

    // Insert user based on role
    $insert_success = false;
    $new_user_id = 0;
    $error_message = '';

    try {
        switch ($role) {
            case 'user':
                error_log("Inserting user into 'user' table");
                $stmt = $mysqli->prepare("INSERT INTO user (firstname, lastname, email, password_hash) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    $error_message = "Prepare failed for user table: " . $mysqli->error;
                    break;
                }
                $stmt->bind_param('ssss', $firstname, $lastname, $email, $password_hash);
                break;

            case 'vendor':
                error_log("Inserting user into 'vendor' table");
                $company_name = $lastname . " Company";
                $stmt = $mysqli->prepare("INSERT INTO vendor (firstname, lastname, email, password_hash, company_name) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error_message = "Prepare failed for vendor table: " . $mysqli->error;
                    break;
                }
                $stmt->bind_param('sssss', $firstname, $lastname, $email, $password_hash, $company_name);
                break;

            case 'admin':
                error_log("Inserting user into 'adminuser' table");
                $stmt = $mysqli->prepare("INSERT INTO adminuser (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    $error_message = "Prepare failed for adminuser table: " . $mysqli->error;
                    break;
                }
                $stmt->bind_param('ssss', $firstname, $lastname, $email, $password_hash);
                break;
        }

        if ($error_message) {
            error_log($error_message);
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }

        if ($stmt->execute()) {
            $new_user_id = $mysqli->insert_id;
            $insert_success = true;
            error_log("Successfully inserted user with ID: " . $new_user_id);
        } else {
            $error_message = 'Execute failed: ' . $stmt->error;
            error_log($error_message);
        }

        $stmt->close();

    } catch (Exception $e) {
        $error_message = 'Exception: ' . $e->getMessage();
        error_log($error_message);
    }

    if ($insert_success) {
        echo json_encode([
            'success' => true,
            'message' => ucfirst($role) . ' added successfully!',
            'user' => [
                'id' => $new_user_id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'role' => $role
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $error_message ?: 'Failed to add user.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>