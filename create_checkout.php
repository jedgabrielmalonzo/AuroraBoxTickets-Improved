<?php
require_once __DIR__ . '/config.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$secret_key = PAYMONGO_SECRET_KEY; // From .env
$user_id = $_SESSION['user_id'];

// --- Get email from DB ---
$mysqli = require __DIR__ . "/database.php";
$stmt = $mysqli->prepare("SELECT email FROM user WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_email);
$stmt->fetch();
$stmt->close();

// Get items, total, discount, and promo from session
$selectedItems = $_SESSION['checkout_items'] ?? [];
$totalAmount = $_SESSION['checkout_total'] ?? 0;
$discountAmount = $_SESSION['checkout_discount'] ?? 0;
$promoCode = $_SESSION['checkout_promo'] ?? '';

if (empty($selectedItems)) {
    die("No items to checkout.");
}

// --- Prepare line items with discount applied proportionally ---
$line_items = [];
$totalItemAmount = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $selectedItems));

foreach ($selectedItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    // Calculate proportional discount for this item
    $itemDiscount = ($discountAmount / $totalItemAmount) * $itemTotal;
    $finalItemPrice = $itemTotal - $itemDiscount;

    // PayMongo requires amount >= 1 cent
    $amountInCents = max(1, round($finalItemPrice * 100));

    $line_items[] = [
        "name" => $item['ticket_name'],
        "quantity" => 1, // already multiplied by quantity
        "amount" => $amountInCents,
        "currency" => "PHP"
    ];
}

// --- PayMongo API Request ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode($secret_key . ":"),
    "Content-Type: application/json"
]);

$data = [
    "data" => [
        "attributes" => [
            "line_items" => $line_items,
            "payment_method_types" => ["card", "gcash", "paymaya", "grab_pay"],
            "success_url" => "https://auroraboxtickets.online/success.php",
            "cancel_url" => "https://auroraboxtickets.online/cancel.php",
            "billing" => [
                "email" => $user_email
            ],
            "metadata" => [
                "user_id" => $user_id,
                "selected_cart_items" => implode(",", array_column($selectedItems, 'id')),
                "promo_code" => $promoCode,
                "discount_amount" => $discountAmount
            ]
        ]
    ]
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
if (curl_errno($ch)) {
    die("cURL error: " . curl_error($ch));
}
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['data']['attributes']['checkout_url'])) {
    $checkout_url = $result['data']['attributes']['checkout_url'];
    header("Location: $checkout_url");
    exit();
} else {
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
