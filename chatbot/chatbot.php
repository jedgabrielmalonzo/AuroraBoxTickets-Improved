<?php
require_once __DIR__ . '/../config.php';
session_start();
header('Content-Type: application/json');

// DB Connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    echo json_encode(["reply" => "⚠️ Database connection failed."]);
    exit;
}

$userMessage = strtolower(trim($_POST['message']));
$response = "🤔 I’m not sure about that. Can you try asking in another way?";

// 📌 Bookings
if (strpos($userMessage, "my booking") !== false || strpos($userMessage, "booking") !== false) {
    if (isset($_SESSION["user_id"])) {
        $uid = $_SESSION["user_id"];
        $sql = "SELECT o.visit_date, o.status, o.price, p.name AS park_name
                FROM orders o
                JOIN parks p ON o.park_id = p.id
                WHERE o.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $response = "📌 <b>Your Latest Booking</b><br>
            • Park: <b>{$row['park_name']}</b><br>
            • Date: <b>{$row['visit_date']}</b><br>
            • Price: ₱{$row['price']}<br>
            • Status: <b>" . ucfirst($row['status']) . "</b>";
        } else {
            $response = "🔍 No bookings found under your account.";
        }
        $stmt->close();
    } else {
        $response = "🔐 Please log in to check your bookings.";
    }
}

// 📌 Payments
elseif (strpos($userMessage, "payment") !== false || strpos($userMessage, "pay") !== false) {
    if (isset($_SESSION["user_id"])) {
        $uid = $_SESSION["user_id"];
        $sql = "SELECT amount, status, payment_method, created_at
                FROM payments
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $response = "💳 <b>Payment Info</b><br>
            • Amount: ₱{$row['amount']}<br>
            • Status: <b>" . ucfirst($row['status']) . "</b><br>
            • Method: {$row['payment_method']}<br>
            • Date: {$row['created_at']}";
        } else {
            $response = "💰 No payment records found.";
        }
        $stmt->close();
    } else {
        $response = "🔐 Please log in to view your payment history.";
    }
}

elseif (strpos($userMessage, "ticket") !== false || strpos($userMessage, "tickets") !== false || strpos($userMessage, "price") !== false) {
    $sql = "SELECT c.category_name, p.name AS park_name, pt.ticket_name, pt.price
            FROM park_tickets pt
            JOIN parks p ON pt.park_id = p.id
            JOIN category c ON p.category = c.category_id
            ORDER BY c.category_name ASC, p.name ASC, pt.price ASC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $ticketsByCategory = [];

        // Group tickets by category & park
        while ($row = $result->fetch_assoc()) {
            $cat = $row['category_name'] ?? 'Other';
            $park = $row['park_name'];

            if (!isset($ticketsByCategory[$cat])) {
                $ticketsByCategory[$cat] = [];
            }
            if (!isset($ticketsByCategory[$cat][$park])) {
                $ticketsByCategory[$cat][$park] = [];
            }
            $ticketsByCategory[$cat][$park][] = "• {$row['ticket_name']}: ₱{$row['price']}";
        }

        // Format response
        $response = "🎟️ <b>Available Tickets</b><br>";
        foreach ($ticketsByCategory as $cat => $parks) {
            $response .= "<br>📂 <b>$cat</b><br>";
            foreach ($parks as $parkName => $tickets) {
                $response .= "🏞️ <u>$parkName</u><br>" . implode("<br>", $tickets) . "<br>";
            }
        }
    } else {
        $response = "😢 No tickets found in the system right now.";
    }
}
// 📌 Fallback → Gemini
else {
    $apiKey = GEMINI_API_KEY;
    $prompt = "You are AuroraBot, a friendly AI assistant for AuroraBox Tickets, a Philippine ticket booking website. 
Answer user questions clearly and politely in **1-2 short sentences**. Focus on ticket bookings, schedules, and park info. 
Mention the relevant category (Theme Park, Aqua Park, Nature Park, Museum) or subcategory if helpful. 
Keep answers simple, direct, and easy to read, like a helpful chat message, not a paragraph: " . $userMessage;



    $data = [
        "model" => "gemini-2.5-flash",
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    if (!$result || curl_errno($ch)) {
        $response = "⚠️ I can’t connect right now. Please check our website for details.";
    } else {
        $res = json_decode($result, true);
        $response = $res['candidates'][0]['content']['parts'][0]['text'] ?? $response;
    }
    curl_close($ch);
}

$conn->close();
echo json_encode(["reply" => $response]);
