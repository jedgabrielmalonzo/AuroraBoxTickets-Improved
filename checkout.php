<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$mysqli = require __DIR__ . "/database.php";

// Initialize variables
$promoCode = '';
$discountAmount = 0;
$selectedItems = [];
$totalAmount = 0;
$errors = [];

// Get user info
$user_info = [];
$stmt = $mysqli->prepare("SELECT firstname, lastname, email FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_info = $row;
}
$stmt->close();

// Load redeemed promo codes that haven't been used in transactions yet
$redeemed_codes = [];
$stmt = $mysqli->prepare("
    SELECT p.code, p.applicable_parks
    FROM promo_redemptions pr
    JOIN promos p ON pr.promo_id = p.id
    WHERE pr.user_id = ? AND pr.used_in_transaction = 0
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $applicable_parks = $row['applicable_parks'] ?? ''; // Default to an empty string if null
    $redeemed_codes[] = ['code' => $row['code'], 'applicable_parks' => explode(',', $applicable_parks)];
}
$stmt->close();

// Load cart items from POST or SESSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selected_items'])) {
    $selectedCartIds = $_POST['selected_items'];
    if (!empty($selectedCartIds)) {
        $sanitizedIds = array_map('intval', $selectedCartIds);
        $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
        $sql = "SELECT c.id, c.ticket_id, c.park_id, c.quantity, c.visit_date, 
                       p.name AS park_name, p.pictures AS park_picture, 
                       t.ticket_name, t.price
                FROM cart c
                JOIN parks p ON c.park_id = p.id
                JOIN park_tickets t ON c.ticket_id = t.id
                WHERE c.id IN ($placeholders) AND c.user_id = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $types = str_repeat('i', count($sanitizedIds)) . 'i';
            $params = array_merge($sanitizedIds, [$_SESSION['user_id']]);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $selectedItems = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Calculate subtotal
            $totalAmount = 0;
            foreach ($selectedItems as $item) {
                $totalAmount += ($item['price'] * $item['quantity']);
            }

            // Apply promo if selected
            $promoCode = $_POST['promo_code'] ?? '';
            $discountAmount = 0;

            if (!empty($promoCode)) {
                // Check if the user has already used this promo code in a transaction
                $checkRedemptionStmt = $mysqli->prepare("SELECT * FROM promo_redemptions WHERE user_id=? AND promo_id=(SELECT id FROM promos WHERE code=?) AND used_in_transaction=1");
                $checkRedemptionStmt->bind_param("is", $_SESSION['user_id'], $promoCode);
                $checkRedemptionStmt->execute();
                $redemptionResult = $checkRedemptionStmt->get_result();

                if ($redemptionResult->num_rows > 0) {
                    $errors[] = "You have already used this promo code in a previous transaction.";
                } else {
                    // Find the selected promo and check its applicable parks
                    $selectedPromo = null;
                    foreach ($redeemed_codes as $redeemed) {
                        if ($redeemed['code'] === $promoCode) {
                            $selectedPromo = $redeemed;
                            break;
                        }
                    }

                    if ($selectedPromo) {
                        // Check if the promo is applicable to any of the selected parks
                        $applicable = false;
                        foreach ($selectedItems as $item) {
                            if (in_array($item['park_id'], $selectedPromo['applicable_parks'])) {
                                $applicable = true;
                                break;
                            }
                        }

                        if ($applicable) {
                            // Proceed with discount calculation
                            $promoStmt = $mysqli->prepare("SELECT * FROM promos WHERE code=? AND status='active' AND expiration_date >= CURDATE()");
                            $promoStmt->bind_param("s", $promoCode);
                            $promoStmt->execute();
                            $promoResult = $promoStmt->get_result();
                            if ($promo = $promoResult->fetch_assoc()) {
                                $discountAmount = ($promo['type'] === 'percentage') ? ($totalAmount * ($promo['value']/100)) : $promo['value'];

                                // Ensure the discount is not greater than the total amount
                                if ($discountAmount > $totalAmount) {
                                    $discountAmount = 0; // Reset to 0 if the discount is greater than the total amount
                                } else {
                                    $totalAmount -= $discountAmount; // Apply discount if applicable
                                }
                            } else {
                                $errors[] = "Invalid or expired promo code.";
                                $discountAmount = 0;
                                $promoCode = '';
                            }
                            $promoStmt->close();
                        } else {
                            $errors[] = "Promo code is not applicable to the selected parks.";
                            $discountAmount = 0;
                            $promoCode = '';
                        }
                    } else {
                        $errors[] = "Invalid or expired promo code.";
                    }
                }

                $checkRedemptionStmt->close();
            }

            // Save to session
            $_SESSION['checkout_items'] = $selectedItems;
            $_SESSION['checkout_total'] = $totalAmount;
            $_SESSION['checkout_discount'] = $discountAmount;
            $_SESSION['checkout_promo'] = $promoCode;
        } else {
            $errors[] = "Database error: " . $mysqli->error;
        }
    } else {
        $errors[] = "No items selected";
    }
} elseif (isset($_SESSION['checkout_items'])) {
    // Load from session
    $selectedItems = $_SESSION['checkout_items'];
    $totalAmount = $_SESSION['checkout_total'];
    $discountAmount = $_SESSION['checkout_discount'] ?? 0;
    $promoCode = $_SESSION['checkout_promo'] ?? '';
} else {
    header("Location: cart.php?error=no_items");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - AuroraBox</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
.container { max-width: 1100px; margin-top: 30px; }
.section-title { font-weight: 600; border-left: 4px solid #684d8f; padding-left: 10px; margin-bottom: 15px; color: #333; }
.card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
.btn-orange { background-color: #684d8f; color: #fff; font-weight: 600; border-radius: 10px; padding: 12px 20px; width: 100%; border: none; }
.btn-orange:hover { background-color: #8564B5; color: #fff; }
.alert-danger { border-radius: 8px; border: none; background-color: #f8d7da; color: #721c24; }
.ticket-item { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
.ticket-item:last-child { border-bottom: none; margin-bottom: 0; }
.ticket-image { width: 80px; height: 60px; object-fit: cover; border-radius: 8px; }
</style>
</head>
<body>
<div class="container">
<h2>Checkout</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($selectedItems)): ?>
<div class="alert alert-warning">
    <p>No items selected. <a href="cart.php">Go back to cart</a></p>
</div>
<?php else: ?>
<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        <form method="POST" action="checkout.php" id="checkout-form">
            <div class="card p-4">
                <h5 class="section-title">Selected Items</h5>
                <?php foreach ($selectedItems as $item): ?>
                <div class="ticket-item d-flex align-items-center">
                    <img src="<?= htmlspecialchars($item['park_picture']) ?>" class="ticket-image me-3" alt="<?= htmlspecialchars($item['park_name']) ?>" onerror="this.src='https://via.placeholder.com/80x60?text=Image'">
                    <div class="flex-grow-1">
                        <h6><?= htmlspecialchars($item['ticket_name']) ?></h6>
                        <p class="small text-muted"><?= htmlspecialchars($item['park_name']) ?></p>
                        <p class="small mb-0">Date: <?= htmlspecialchars($item['visit_date']) ?> | Quantity: <?= $item['quantity'] ?></p>
                    </div>
                    <div class="text-end">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card p-4">
                <h5 class="section-title">Contact Information</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="first_name" class="form-control" placeholder="First Name" value="<?= htmlspecialchars($user_info['firstname'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" value="<?= htmlspecialchars($user_info['lastname'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="country" class="form-control" placeholder="Country" value="Philippines" required>
                    </div>
                    <div class="col-md-6">
                        <input type="tel" name="phone" class="form-control" placeholder="Phone Number" required>
                    </div>
                    <div class="col-12">
                        <input type="email" name="email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($user_info['email'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <h5 class="section-title">Discounts</h5>
                <!-- Preserve selected items in hidden inputs -->
                <?php foreach ($selectedItems as $item): ?>
                    <input type="hidden" name="selected_items[]" value="<?= $item['id'] ?>">
                <?php endforeach; ?>
            
                <select name="promo_code" class="form-select" onchange="document.getElementById('checkout-form').submit();">
                    <option value="">-- Select redeemed promo --</option>
                    <?php foreach ($redeemed_codes as $redeemed): ?>
                        <?php
                        // Check promo validity
                        $promo_stmt = $mysqli->prepare("SELECT * FROM promos WHERE code=? AND status='active' AND expiration_date >= CURDATE()");
                        $promo_stmt->bind_param("s", $redeemed['code']);
                        $promo_stmt->execute();
                        $promo_result = $promo_stmt->get_result();
                        $promo_data = $promo_result->fetch_assoc();

                        // Calculate potential discount and check applicability
                        $potential_discount = 0;
                        $applicable = false; // Track if the promo is applicable to selected parks

                        if ($promo_data) {
                            // Calculate potential discount
                            $potential_discount = ($promo_data['type'] === 'percentage') 
                                ? ($totalAmount * ($promo_data['value'] / 100)) 
                                : $promo_data['value'];

                            // Check if the promo is applicable to any of the selected parks
                            foreach ($selectedItems as $item) {
                                if (in_array($item['park_id'], $redeemed['applicable_parks'])) {
                                    $applicable = true;
                                    break;
                                }
                            }
                        }
                        ?>
                        <?php if ($applicable && $potential_discount <= $totalAmount): ?>
                            <option value="<?= htmlspecialchars($redeemed['code']) ?>" <?= ($promoCode === $redeemed['code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($redeemed['code']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <div class="card p-4">
            <h6>Order Summary</h6>
            <?php foreach ($selectedItems as $item): ?>
            <div class="d-flex justify-content-between">
                <div><?= htmlspecialchars($item['ticket_name']) ?> x <?= $item['quantity'] ?></div>
                <div>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
            </div>
            <?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between">
                <span>Subtotal</span>
                <span>₱<?= number_format($totalAmount + $discountAmount, 2) ?></span>
            </div>
            <?php if ($discountAmount > 0): ?>
            <div class="d-flex justify-content-between text-success">
                <span>Discount</span>
                <span>-₱<?= number_format($discountAmount, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between fw-bold text-danger">
                <span>Total</span>
                <span>₱<?= number_format($totalAmount, 2) ?></span>
            </div>
        </div>
        <a href="create_checkout.php" class="btn-orange mt-3 d-block text-center py-2">Proceed to Payment</a>
        <div class="text-center mt-3">
            <a href="cart.php" class="text-muted">← Back to Cart</a>
        </div>
    </div>
</div>
<?php endif; ?>
</div>
</body>
</html>
<?php $mysqli->close(); ?>