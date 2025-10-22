<?php
require_once __DIR__ . '/config.php';
// send_otp.php - Fixed for AJAX response with resend support
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $isResend = isset($_POST["resend"]) && $_POST["resend"] == "1";
    
    // Basic validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isResend) {
            echo json_encode(["success" => false, "message" => "Invalid email format"]);
        } else {
            echo "error:Invalid email format";
        }
        exit;
    }
    
    // For resend, we don't need name and password
    if (!$isResend) {
        $name = trim($_POST["name"]);
        $password = $_POST["password"];
    }
    
    // Check if email already exists in database (only for new signup, not resend)
    if (!$isResend) {
        $checkStmt = $conn->prepare("SELECT id, firstname, lastname FROM user WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $existingUser = $checkStmt->get_result()->fetch_assoc();
        
        if ($existingUser) {
            $fullName = trim($existingUser['firstname'] . ' ' . $existingUser['lastname']);
            $_SESSION["signup_error"] = "Email already registered";
            $_SESSION["signup_email"] = $email;
            echo "error:Email already exists";
            exit;
        }
        
        // Hash password for new signup
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Generate OTP
    $otp = rand(100000, 999999);
    $expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));
    
    // Save OTP
    $stmt = $conn->prepare("REPLACE INTO otps (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $otp, $expires_at);
    $stmt->execute();
    
    // Send OTP via PHPMailer
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
        
    $mail->setFrom(SMTP_USER, 'AuroraBox Tickets');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code - AuroraBox Registration';
        $mail->Body    = "
            <h2>Welcome to AuroraBox!</h2>
            <p>Your OTP code is: <b style='font-size:24px; color:#007bff;'>$otp</b></p>
            <p>This code will expire in <b>5 minutes</b>.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";
        
        $mail->send();
        
        // Save signup data in session (only for new signup)
        if (!$isResend) {
            $_SESSION["signup_name"] = $name;
            $_SESSION["signup_email"] = $email;
            $_SESSION["signup_password"] = $password_hash;
            $_SESSION["otp_sent"] = true;
        }
        
        // Return success response for AJAX
        if ($isResend) {
            echo json_encode(["success" => true, "message" => "New OTP sent to your email successfully!"]);
        } else {
            echo "success:OTP sent successfully to your email";
        }
        exit;
        
    } catch (Exception $e) {
        // Log the actual error for debugging
        error_log("PHPMailer Error: " . $e->getMessage());
        
        // Return error response for AJAX
        if ($isResend) {
            echo json_encode(["success" => false, "message" => "Failed to send OTP. Please try again."]);
        } else {
            echo "error:Failed to send OTP. Please try again.";
        }
        exit;
    }
}
?>