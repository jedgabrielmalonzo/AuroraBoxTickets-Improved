<?php
require_once __DIR__ . '/config.php';
session_start();

require __DIR__ . "/vendor/autoload.php";

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

// OAuth callback handling
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        $client->setAccessToken($token);
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $_SESSION['google_id'] = $google_account_info->id;
        $_SESSION['google_email'] = $google_account_info->email;
        $_SESSION['google_name'] = $google_account_info->name;
        $_SESSION['google_picture'] = $google_account_info->picture;
    }
}

$user = null;

// Normal login user data
if (isset($_SESSION["user_id"])) {
    $mysqli = require __DIR__ . "/database.php";
    $sql = "SELECT * FROM user WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Google login user data
if (isset($_SESSION['google_id']) && !$user) {
    $user = [
        'id' => $_SESSION['google_id'],
        'name' => $_SESSION['google_name'],
        'email' => $_SESSION['google_email'],
        'picture' => $_SESSION['google_picture']
    ];
}



if (!$user) {
    header("Location: login.php");
    exit;
}
