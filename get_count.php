<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Assuming user_id is stored in session
$user_id = $_SESSION["user_id"];

// Fetch wishlist count
$wishlist_sql = "SELECT COUNT(*) AS count FROM wishlist WHERE user_id = ?";
$stmt = $mysqli->prepare($wishlist_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist_count = $stmt->get_result()->fetch_assoc()['count'];

// Fetch cart count
$cart_sql = "SELECT COUNT(*) AS count FROM cart WHERE user_id = ?";
$stmt = $mysqli->prepare($cart_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_count = $stmt->get_result()->fetch_assoc()['count'];

echo json_encode(['wishlist_count' => $wishlist_count, 'cart_count' => $cart_count]);
?>