<?php
session_start();
require "database.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['code'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$code = $_POST['code'];
$user_id = $_SESSION['user_id'];

// Get promo ID and check if it's already redeemed
$stmt = $mysqli->prepare("SELECT id FROM promos WHERE code = ? AND status='active' AND expiration_date >= CURDATE()");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
if ($promo = $result->fetch_assoc()) {
    $promo_id = $promo['id'];
    
    // Check if the promo has already been redeemed by the user
    $check = $mysqli->prepare("SELECT * FROM promo_redemptions WHERE user_id=? AND promo_id=?");
    $check->bind_param("ii", $user_id, $promo_id);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows == 0) {
        // Insert into promo_redemptions if not already redeemed
        $insert = $mysqli->prepare("INSERT INTO promo_redemptions (user_id, promo_id) VALUES (?, ?)");
        $insert->bind_param("ii", $user_id, $promo_id);
        $insert->execute();
        $insert->close();
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Promo already redeemed"]);
    }
    $check->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid promo"]);
}
$stmt->close();
?>