<?php
// PayMongo Webhook Handler - Fixed Version with Promo Redemption
require_once __DIR__ . '/config.php';
require 'send_email.php';

$webhook_secret = PAYMONGO_WEBHOOK_SECRET;
$payload = file_get_contents("php://input");
$signature_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// Debug log
file_put_contents("webhook_debug.txt", date('Y-m-d H:i:s') . " - Webhook received\nPayload: $payload\n\n", FILE_APPEND);

// Verify PayMongo signature
function verifyPayMongoSignature($payload, $signature_header, $secret) {
    $parts = explode(',', $signature_header);
    $timestamp = '';
    $expected_signature = '';
    foreach ($parts as $part) {
        [$key, $value] = explode('=', $part, 2);
        if ($key === 't') $timestamp = $value;
        if ($key === 'te') $expected_signature = $value;
    }
    if (!$timestamp || !$expected_signature) return false;
    $signed_payload = $timestamp . "." . $payload;
    $computed_signature = hash_hmac('sha256', $signed_payload, $secret);
    return hash_equals($computed_signature, $expected_signature);
}

if (!verifyPayMongoSignature($payload, $signature_header, $webhook_secret)) {
    http_response_code(400);
    exit("Invalid signature");
}

// Decode JSON
$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    exit("Invalid JSON");
}

// Only handle payment.paid events
$event_type = $event['data']['attributes']['type'] ?? '';
if ($event_type !== 'checkout_session.payment.paid') {
    http_response_code(200);
    exit("Event ignored");
}

// Connect to DB
$mysqli = require __DIR__ . '/database.php';
if ($mysqli->connect_error) {
    file_put_contents("webhook_debug.txt", "DB error: {$mysqli->connect_error}\n", FILE_APPEND);
    http_response_code(500);
    exit("DB connection error");
}

// Extract payment data
$checkout_data = $event['data']['attributes']['data']['attributes'] ?? [];
$payment_data = $checkout_data['payments'][0] ?? [];

$payment_id = $payment_data['id'] ?? 'pay_' . time() . '_' . rand(1000,9999);
$amount = isset($payment_data['attributes']['amount']) ? $payment_data['attributes']['amount'] / 100 : 0;
$payment_method = $payment_data['attributes']['source']['type'] ?? 'unknown';
$reference_number = $checkout_data['reference_number'] ?? 'REF_' . time();
$billing_email = $payment_data['attributes']['billing']['email'] ?? null;

// --- Get user_id and selected_cart_items and promo ---
$metadata = $checkout_data['metadata'] ?? [];
$user_id = $metadata['user_id'] ?? null;
$selected_cart_items_string = $metadata['selected_cart_items'] ?? '';
$discountAmount = $metadata['discount_amount'] ?? 0;
$promoCode = $metadata['promo_code'] ?? '';

// Parse selected cart items
$selected_cart_items = [];
if ($selected_cart_items_string) {
    $selected_cart_items = array_map('intval', explode(',', $selected_cart_items_string));
}

if ($user_id) {
    file_put_contents("webhook_debug.txt", "user_id found in metadata → $user_id\n", FILE_APPEND);
} elseif ($billing_email) {
    $stmt = $mysqli->prepare("SELECT id FROM user WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $billing_email);
    $stmt->execute();
    $stmt->bind_result($uid);
    if ($stmt->fetch()) $user_id = $uid;
    $stmt->close();
    file_put_contents("webhook_debug.txt", "user_id resolved from email → $user_id\n", FILE_APPEND);
}

if (!$user_id) $user_id = 0;

// Start transaction
$mysqli->autocommit(FALSE);

try {
    // --- Save payment ---
    $stmt = $mysqli->prepare("SELECT id, status FROM payments WHERE payment_id=? LIMIT 1");
    $stmt->bind_param("s", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $payment_db_id = null;
    $existing_status = null;

    if ($row = $result->fetch_assoc()) {
        $payment_db_id = $row['id'];
        $existing_status = $row['status'];

        if ($existing_status === 'paid') {
            // Already processed, exit
            $stmt->close();
            $mysqli->commit();
            http_response_code(200);
            exit("Already processed");
        }
    } else {
        // Insert new payment
        $stmt->close();
        $stmt = $mysqli->prepare("INSERT INTO payments 
            (user_id, payment_id, amount, status, payment_method, reference_number) 
            VALUES (?, ?, ?, 'paid', ?, ?)");
        $stmt->bind_param("isdss", $user_id, $payment_id, $amount, $payment_method, $reference_number);
        if (!$stmt->execute()) throw new Exception("Payment save failed: " . $stmt->error);
        $payment_db_id = $mysqli->insert_id;
        $stmt->close();
    }

    // --- Store order details for email ---
    $orderDetails = [];

    if ($user_id > 0 && !empty($selected_cart_items)) {
        $placeholders = implode(',', array_fill(0, count($selected_cart_items), '?'));
        $cartQuery = $mysqli->prepare("SELECT id, park_id, ticket_id, quantity, unit_price, visit_date 
                                       FROM cart 
                                       WHERE user_id=? AND id IN ($placeholders)");
        $types = str_repeat('i', count($selected_cart_items) + 1);
        $params = array_merge([$user_id], $selected_cart_items);
        $cartQuery->bind_param($types, ...$params);
        $cartQuery->execute();
        $cartResult = $cartQuery->get_result();

        if ($cartResult->num_rows > 0) {
            $orderStmt = $mysqli->prepare("INSERT INTO orders 
                (user_id, payment_db_id, payment_ref, park_id, ticket_id, visit_date, quantity, price, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");
            $processedCartIds = [];
            $totalSelectedAmount = 0;
            $cartData = [];
            while ($cartItem = $cartResult->fetch_assoc()) {
                $cartData[] = $cartItem;
                $totalSelectedAmount += $cartItem['unit_price'] * $cartItem['quantity'];
            }

            foreach ($cartData as $cartItem) {
                $itemTotal = $cartItem['unit_price'] * $cartItem['quantity'];
                $itemDiscount = ($discountAmount / $totalSelectedAmount) * $itemTotal;
                $finalItemPrice = $itemTotal - $itemDiscount;

                $ticket_id = $cartItem['ticket_id'] ?: null;
                $visit_date = $cartItem['visit_date'] ?: null;

                $orderStmt->bind_param(
                    "iissisid",
                    $user_id,
                    $payment_db_id,
                    $payment_id,
                    $cartItem['park_id'],
                    $ticket_id,
                    $visit_date,
                    $cartItem['quantity'],
                    $finalItemPrice
                );

                if (!$orderStmt->execute()) throw new Exception("Order insert failed: " . $orderStmt->error);

                $orderDetails[] = [
                    'park_id' => $cartItem['park_id'],
                    'ticket_id' => $ticket_id,
                    'quantity' => $cartItem['quantity'],
                    'price' => $finalItemPrice,
                    'visit_date' => $visit_date
                ];

                $processedCartIds[] = $cartItem['id'];
            }
            $orderStmt->close();

            if (!empty($processedCartIds)) {
                $deletePlaceholders = implode(',', array_fill(0, count($processedCartIds), '?'));
                $deleteCart = $mysqli->prepare("DELETE FROM cart WHERE user_id=? AND id IN ($deletePlaceholders)");
                $deleteTypes = str_repeat('i', count($processedCartIds) + 1);
                $deleteParams = array_merge([$user_id], $processedCartIds);
                $deleteCart->bind_param($deleteTypes, ...$deleteParams);
                if (!$deleteCart->execute()) throw new Exception("Selected cart items delete failed: " . $deleteCart->error);
                $deleteCart->close();
            }
        }
        $cartQuery->close();
    }

    // --- MARK PROMO AS USED IN TRANSACTION ---
    if ($user_id > 0 && !empty($promoCode)) {
        $stmt = $mysqli->prepare("SELECT id FROM promos WHERE code=? AND status='active' LIMIT 1");
        $stmt->bind_param("s", $promoCode);
        $stmt->execute();
        $promo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($promo) {
            $promo_id = $promo['id'];
            // Check if user has redeemed this promo
            $check = $mysqli->prepare("SELECT id FROM promo_redemptions WHERE user_id=? AND promo_id=? LIMIT 1");
            $check->bind_param("ii", $user_id, $promo_id);
            $check->execute();
            $redemption = $check->get_result()->fetch_assoc();
            $check->close();

            if ($redemption) {
                // Mark as used in transaction
                $update = $mysqli->prepare("UPDATE promo_redemptions SET used_in_transaction = 1 WHERE user_id=? AND promo_id=?");
                $update->bind_param("ii", $user_id, $promo_id);
                $update->execute();
                $update->close();
                file_put_contents("webhook_debug.txt", "Promo '$promoCode' marked as USED IN TRANSACTION for user_id=$user_id\n", FILE_APPEND);
            } else {
                // User somehow used a promo they didn't redeem - add it and mark as used
                $insert = $mysqli->prepare("INSERT INTO promo_redemptions (user_id, promo_id, used_in_transaction) VALUES (?, ?, 1)");
                $insert->bind_param("ii", $user_id, $promo_id);
                $insert->execute();
                $insert->close();
                file_put_contents("webhook_debug.txt", "Promo '$promoCode' added and marked as USED IN TRANSACTION for user_id=$user_id\n", FILE_APPEND);
            }
        }
    }

    $mysqli->commit();
    file_put_contents("webhook_debug.txt", "Transaction completed successfully\n", FILE_APPEND);

} catch (Exception $e) {
    $mysqli->rollback();
    file_put_contents("webhook_debug.txt", "Transaction rolled back: " . $e->getMessage() . "\n\n", FILE_APPEND);
    http_response_code(500);
    exit("Processing failed");
}

$mysqli->autocommit(TRUE);

// --- Send Receipt Email AFTER transaction is committed ---
if ($billing_email && !empty($orderDetails)) {
    $userName = 'Valued Customer';
    if ($user_id > 0) {
        try {
            $userStmt = $mysqli->prepare("SELECT CONCAT(firstname, ' ', lastname) as full_name FROM user WHERE id = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($userRow = $userResult->fetch_assoc()) {
                $userName = trim($userRow['full_name']) ?: 'Valued Customer';
            }
            $userStmt->close();
        } catch (Exception $e) {
            file_put_contents("webhook_debug.txt", "User name fetch error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    $emailResult = sendTicketReceipt(
        $billing_email, 
        $userName, 
        $reference_number, 
        $amount, 
        $payment_method, 
        $orderDetails, 
        $mysqli
    );

    if ($emailResult === true) {
        file_put_contents("webhook_debug.txt", "Receipt email sent successfully to: $billing_email\n", FILE_APPEND);
    } else {
        file_put_contents("webhook_debug.txt", "Email failed: $emailResult\n", FILE_APPEND);
    }
} else {
    file_put_contents("webhook_debug.txt", "No email sent - missing email or order details\n", FILE_APPEND);
}

$mysqli->close();
file_put_contents("webhook_debug.txt", "Webhook processing completed successfully\n\n", FILE_APPEND);
http_response_code(200);
echo "OK";
?>
