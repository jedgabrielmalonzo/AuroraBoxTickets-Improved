<?php
function addToCart($userId, $parkId, $ticketId, $quantity, $visitDate) {
    // Database connection
    $mysqli = require __DIR__ . "/database.php"; // Adjust path as necessary

    // SQL to insert into cart
    $sql = "INSERT INTO cart (user_id, park_id, ticket_id, quantity, visit_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("iiiss", $userId, $parkId, $ticketId, $quantity, $visitDate);
    $stmt->execute();

    // Check for errors
    if ($stmt->error) {
        throw new Exception("Error adding to cart: " . $stmt->error);
    }

    $stmt->close();
}
?>