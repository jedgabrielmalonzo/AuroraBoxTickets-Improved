<?php
require_once __DIR__ . '/config.php';
session_start();
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

$enteredOtp = $_POST["otp"] ?? null;
$email = $_POST["email"] ?? $_SESSION["signup_email"] ?? null; // Get from session if not in POST

if (!$email || !$enteredOtp) {
    echo json_encode(["success" => false, "message" => "Missing email or OTP. Email: $email, OTP: $enteredOtp"]);
    exit;
}

// Check if session email matches the submitted email (security check)
if (!isset($_SESSION["signup_email"]) || $_SESSION["signup_email"] !== $email) {
    echo json_encode(["success" => false, "message" => "Session mismatch. Please sign up again."]);
    exit;
}

// Double-check if email already exists (prevent race condition)
$checkStmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already registered by another user."]);
    exit;
}

// Get latest OTP for this email
$stmt = $conn->prepare("SELECT code, expires_at FROM otps WHERE email = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$otpData = $result->fetch_assoc();

if (!$otpData) {
    echo json_encode(["success" => false, "message" => "No OTP found for this email."]);
    exit;
}

// Check if OTP matches
if ($otpData["code"] != $enteredOtp) {
    echo json_encode(["success" => false, "message" => "Invalid OTP code. Please try again."]);
    exit;
}

// Check if OTP is expired
if (strtotime($otpData["expires_at"]) <= time()) {
    echo json_encode(["success" => false, "message" => "OTP has expired. Please request a new one."]);
    exit;
}

// OTP is valid - proceed with account creation
$name = $_SESSION["signup_name"] ?? "";
$password_hash = $_SESSION["signup_password"] ?? null;

if (!$name || !$password_hash) {
    echo json_encode(["success" => false, "message" => "Session data missing. Please sign up again."]);
    exit;
}

// Split name into firstname / lastname
$firstname = trim($name);
$lastname = "";
if (strpos($name, " ") !== false) {
    $nameParts = explode(" ", $name, 2);
    $firstname = trim($nameParts[0]);
    $lastname = trim($nameParts[1]);
}

// Begin transaction for data consistency
$conn->begin_transaction();

try {
    // Insert into users table - FIXED: Removed columns you don't have
    $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, email, password_hash, email_verified) VALUES (?, ?, ?, ?, 1)");
$stmt->bind_param("ssss", $firstname, $lastname, $email, $password_hash);

    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create user account: " . $stmt->error);
    }
    
    $userId = $stmt->insert_id;
    
    // Delete the used OTP
    $deleteStmt = $conn->prepare("DELETE FROM otps WHERE email = ?");
    $deleteStmt->bind_param("s", $email);
    $deleteStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set user session
    $_SESSION["user_id"] = $userId;
    $_SESSION["user_name"] = $firstname;
    $_SESSION["user_email"] = $email;
    
    // Clean up signup session data
    unset($_SESSION["signup_name"]);
    unset($_SESSION["signup_email"]);
    unset($_SESSION["signup_password"]);
    
    echo json_encode([
        "success" => true, 
        "message" => "Account verified successfully! Welcome to AuroraBox!",
        "user" => [
            "id" => $userId,
            "name" => $firstname,
            "email" => $email
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        "success" => false, 
        "message" => "Account creation failed. Please try again."
    ]);
    
    // Log error for debugging
    error_log("Account creation error: " . $e->getMessage());
}

$conn->close();
?>