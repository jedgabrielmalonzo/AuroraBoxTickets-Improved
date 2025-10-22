<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . "/vendor/autoload.php"; // Autoload Google Client

// Email function using PHP's mail()
function sendWelcomeEmail($email, $firstName, $lastName) {
    $to = $email;
    $subject = "Welcome to Aurora Box Tickets! 🎫";
    
    $message = "
    <html>
    <head>
        <title>Welcome to Aurora Box Tickets</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎫 Welcome to Aurora Box Tickets!</h1>
                <p>Your Gateway to Amazing Events</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($firstName) . "!</h2>
                <p>Salamat sa pag-join sa Aurora Box Tickets! We're thrilled to have you as part of our community.</p>
                
                <p><strong>What's next?</strong></p>
                <ul>
                    <li>🎭 Explore upcoming events</li>
                    <li>🎪 Book your favorite places</li>
                    <li>⭐ Get exclusive member deals</li>
                    <li>📱 Easy mobile ticket access</li>
                </ul>
                
                <p>Ready to discover amazing events?</p>
                <a href='https://auroraboxtickets.online' class='button'>Start Exploring Events</a>
                
                <p>If you have any questions, feel free to reach out to our support team.</p>
                
                <p>Welcome aboard!<br>
                <strong>The Aurora Box Tickets Team</strong></p>
            </div>
            <div class='footer'>
                <p>© 2024 Aurora Box Tickets. All rights reserved.</p>
                <p>This email was sent because you created an account with us.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Aurora Box Tickets <noreply@auroraboxtickets.online>" . "\r\n";
    $headers .= "Reply-To: support@auroraboxtickets.online" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Google Client Setup
$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

// Force account chooser
$client->setPrompt('select_account');

// Database Connection
$mysqli = require __DIR__ . '/database.php';
if ($mysqli->connect_errno) {
    die("Connection error: " . $mysqli->connect_error);
}

// Check if we received an authorization code
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (!isset($token['error'])) {
        $client->setAccessToken($token);
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        // Store Google data in session
        $_SESSION['google_id'] = $google_account_info->id;
        $_SESSION['google_email'] = $google_account_info->email;
        $_SESSION['google_name'] = $google_account_info->name;
        $_SESSION['google_picture'] = $google_account_info->picture;
        
        // Split name into first and last
        $name_parts = explode(" ", $_SESSION['google_name'], 2);
        $firstName = $name_parts[0];
        $lastName = isset($name_parts[1]) ? $name_parts[1] : "";
        
        // Check if user already exists
        $sql = "SELECT id FROM user WHERE email = ?";
        $stmt = $mysqli->prepare($sql);
        
        if (!$stmt) {
            die("SQL Error (SELECT): " . $mysqli->error);
        }
        
        $stmt->bind_param("s", $_SESSION['google_email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $db_user = $result->fetch_assoc();
        
        $isNewUser = false;
        
    $sql = "SELECT id, firstname FROM user WHERE email = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $_SESSION['google_email']);
$stmt->execute();
$result = $stmt->get_result();
$db_user = $result->fetch_assoc();

if ($db_user) {
    // Existing user - just login
    $_SESSION['user_id'] = $db_user['id'];

    // Mark as active + verified
    $update_sql = "UPDATE user 
                   SET is_active = 1, email_verified = 1 
                   WHERE id = ?";
    $update_stmt = $mysqli->prepare($update_sql);
    $update_stmt->bind_param('i', $db_user['id']);

    if ($update_stmt->execute()) {
        $_SESSION['login_message'] = "Welcome back, " . $db_user['firstname'] . "!";
        header("Location: index.php");
        exit;
    }
    else {
        echo "Error updating user status: " . $update_stmt->error;
    }
}

 else {
    // New user - signup
   $insert_sql = "INSERT INTO user (firstname, lastname, email, email_verified, is_active, last_login, created) 
               VALUES (?, ?, ?, 1, 1, NOW(), NOW())";
    $insert_stmt = $mysqli->prepare($insert_sql);
    
    if (!$insert_stmt) {
        die("SQL Error (INSERT): " . $mysqli->error);
    }
    
    $insert_stmt->bind_param("sss", $firstName, $lastName, $_SESSION['google_email']);
    
    if ($insert_stmt->execute()) {
        $_SESSION['user_id'] = $insert_stmt->insert_id;
        $isNewUser = true;
        
        // Send welcome email for new users only
        if (sendWelcomeEmail($_SESSION['google_email'], $firstName, $lastName)) {
            $_SESSION['signup_message'] = "Welcome to Aurora Box Tickets! Check your email for a special welcome message.";
        } else {
            $_SESSION['signup_message'] = "Welcome to Aurora Box Tickets! (Email notification failed, but your account is ready!)";
        }
    } else {
        die("Error creating account: " . $insert_stmt->error);
    }
}

        
        // Redirect to prevent code reuse
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }
}

// Optional: Load user data for the session
$user = null;
if (isset($_SESSION["user_id"])) {
    $sql = "SELECT * FROM user WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Display toast messages if they exist
if (isset($_SESSION['signup_message']) || isset($_SESSION['login_message'])) {
    echo "<style>
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 350px;
    padding: 16px 20px;
    background: #C4A7FF;  /* Changed to Option 3 */
    color: white;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 14px;
    animation: slideIn 0.5s ease-out, fadeOut 0.5s ease-in 4.5s forwards;
    display: flex;
    align-items: center;
    gap: 12px;
}

.toast-notification.success {
    background: #9176B8;  /* Changed to your color */
}

.toast-notification.info {
    background: #9176B8;  /* Changed to your color */
}    }
        
        .toast-icon {
            font-size: 20px;
        }
        
        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .toast-close:hover {
            opacity: 1;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        @media (max-width: 480px) {
            .toast-notification {
                right: 10px;
                left: 10px;
                min-width: auto;
                width: auto;
            }
        }
    </style>";
    
    if (isset($_SESSION['signup_message'])) {
        echo "<div class='toast-notification success' id='toastMessage'>
                <span class='toast-icon'>🎫</span>
                <div>" . $_SESSION['signup_message'] . "</div>
                <button class='toast-close' onclick='closeToast()'>×</button>
              </div>";
        unset($_SESSION['signup_message']);
    }
    
    if (isset($_SESSION['login_message'])) {
        echo "<div class='toast-notification info' id='toastMessage'>
                <span class='toast-icon'>👋</span>
                <div>" . $_SESSION['login_message'] . "</div>
                <button class='toast-close' onclick='closeToast()'>×</button>
              </div>";
        unset($_SESSION['login_message']);
    }
    
    echo "<script>
        function closeToast() {
            const toast = document.getElementById('toastMessage');
            if (toast) {
                toast.style.animation = 'fadeOut 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            const toast = document.getElementById('toastMessage');
            if (toast) {
                closeToast();
            }
        }, 5000);
    </script>";
}
?>