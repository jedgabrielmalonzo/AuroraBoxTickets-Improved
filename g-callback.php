<?php
session_start();
require __DIR__ . '/gClientSetup.php';

// Check if the OAuth code is present
if (!isset($_GET['code'])) {
    header('Location: index.php');
    exit;
}

// Fetch the access token using the authorization code
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$client->setAccessToken($token['access_token']);

// Get user info from Google
$google_oauth = new Google_Service_Oauth2($client);
$google_account_info = $google_oauth->userinfo->get();

$email       = $google_account_info->email;
$is_verified = $google_account_info->verifiedEmail;
$firstname   = $google_account_info->givenName;
$lastname    = $google_account_info->familyName;

if ($is_verified) {
    $mysqli = require __DIR__ . "/database.php";

    // Check if user already exists
    $stmt = $mysqli->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        // Create new user
        $stmt = $mysqli->prepare("
            INSERT INTO user (firstname, lastname, email, email_verified, created, is_active)
            VALUES (?, ?, ?, 1, NOW(), 1)
        ");
        $stmt->bind_param("sss", $firstname, $lastname, $email);
        $stmt->execute();

        $user_id = $stmt->insert_id;
    } else {
        // Existing user
        $user_id   = $user['id'];
        $firstname = $user['firstname'];
        $lastname  = $user['lastname'];
    }

    // ✅ Save session data
    $_SESSION['user_id']   = $user_id;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname']  = $lastname;
    $_SESSION['email']     = $email;

    header('Location: index.php');
    exit;
} else {
    echo "Google account email not verified!";
}

?>