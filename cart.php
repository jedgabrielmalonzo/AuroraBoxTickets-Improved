<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy(); // Destroy the session
    header("Location: index.php"); // Redirect back to the home page
    exit();
}
// Database connection
$mysqli = require __DIR__ . "/database.php";

$conn = $mysqli; // ✅ para gumana yung navbar.php na naka-$conn
// Fetch logged-in user info
$userId = $_SESSION['user_id'];
$user_sql = "SELECT firstname, lastname, email FROM user WHERE id = ?";
$user_stmt = $mysqli->prepare($user_sql);
$user_stmt->bind_param("i", $userId);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_SESSION['user_id'];

    // Handle JSON input for removing multiple items
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);

        // Handle removal of multiple items
        if (isset($input['action']) && $input['action'] === 'remove_multiple' && isset($input['cart_ids'])) {
            $cartIds = $input['cart_ids'];

            if (is_array($cartIds) && count($cartIds) > 0) {
                $ids = implode(',', array_map('intval', $cartIds)); // Sanitize input
                $remove_sql = "DELETE FROM cart WHERE id IN ($ids) AND user_id = ?";
                $remove_stmt = $mysqli->prepare($remove_sql);
                $remove_stmt->bind_param("i", $userId);
                if ($remove_stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $remove_stmt->error]);
                }
                $remove_stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid input or no items to remove']);
            }
            exit();
        }

        // Handle updating quantity
        if (isset($input['action']) && $input['action'] === 'update_quantity' && isset($input['cart_id']) && isset($input['quantity'])) {
            $cartId = intval($input['cart_id']);
            $quantity = intval($input['quantity']);
            
            if ($quantity < 1) $quantity = 1; // Minimum quantity is 1
            
            $update_sql = "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("iii", $quantity, $cartId, $userId);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update quantity']);
            }
            $update_stmt->close();
            exit();
        }
    }
    
    // Adding items to the cart
if ($_POST['action'] === 'cart' && isset($_POST['ticket_id']) && isset($_POST['visit_date'])) {
    $ticketId = $_POST['ticket_id'];
    $quantity = $_POST['quantity'];
    $visitDate = $_POST['visit_date'];

    // Fetch ticket details including price
    $ticket_sql = "SELECT park_id, price FROM park_tickets WHERE id = ?";
    $ticket_stmt = $mysqli->prepare($ticket_sql);
    $ticket_stmt->bind_param("i", $ticketId);
    $ticket_stmt->execute();
    $ticket_result = $ticket_stmt->get_result();

    if ($ticket_result->num_rows > 0) {
        $ticket_row = $ticket_result->fetch_assoc();
        $parkId = $ticket_row['park_id'];
        $ticketPrice = $ticket_row['price']; // Store the price

        // Check if the item already exists in the cart
        $check_sql = "SELECT id, quantity FROM cart WHERE user_id = ? AND ticket_id = ? AND visit_date = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("iis", $userId, $ticketId, $visitDate);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update existing item
            $row = $check_result->fetch_assoc();
            $new_quantity = $row['quantity'] + $quantity;

            $update_sql = "UPDATE cart SET quantity = ? WHERE id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_quantity, $row['id']);
            if (!$update_stmt->execute()) {
                error_log("Error updating quantity: " . $update_stmt->error);
            }
            $update_stmt->close();
        } else {
            // Add new item to cart with price
            $sql = "INSERT INTO cart (user_id, ticket_id, park_id, quantity, visit_date, unit_price) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("iiissd", $userId, $ticketId, $parkId, $quantity, $visitDate, $ticketPrice);
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'error' => "Error adding to cart: " . $stmt->error]);
                exit();
            }
            $stmt->close();
        }

        // ✅ Get latest cart count for this user
        $count_sql = "SELECT COUNT(*) AS cartCount FROM cart WHERE user_id = ?";
        $count_stmt = $mysqli->prepare($count_sql);
        $count_stmt->bind_param("i", $userId);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $count_stmt->close();

        echo json_encode(['success' => true, 'cartCount' => $count_result['cartCount']]);
        exit();

    } else {
        echo json_encode(['success' => false, 'error' => "Ticket not found."]);
    }
}


    // Removing items from the cart
    if (isset($_POST['action']) && $_POST['action'] === 'remove' && isset($_POST['cart_id'])) {
        $cartId = $_POST['cart_id']; // ID of the cart item to remove

        $remove_sql = "DELETE FROM cart WHERE id = ? AND user_id = ?";
        $remove_stmt = $mysqli->prepare($remove_sql);
        $remove_stmt->bind_param("ii", $cartId, $userId);
        if ($remove_stmt->execute()) {
            header("Location: cart.php");
            exit();
        } else {
            echo "Error removing item: " . $remove_stmt->error;
        }
        $remove_stmt->close();
    }
}

// Handle logout

if (isset($_GET['logout'])) {
    // Update last_login timestamp before destroying the session
    $update_login_sql = "UPDATE user SET last_login = NOW(), is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($update_login_sql);
    $stmt->bind_param('i', $_SESSION["user_id"]); // Assuming user_id is stored in session
    
    if ($stmt->execute()) {
        session_destroy(); // Destroy the session
        header("Location: index.php"); // Redirect back to the home page
        exit();
    } else {
        echo "Error updating last login: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch cart items (if needed)
$userId = $_SESSION['user_id']; // Get the user ID from session or other source
$sql = "SELECT c.*, p.name AS park_name, p.pictures AS park_picture, t.ticket_name, t.price AS unit_price 
        FROM cart c 
        JOIN parks p ON c.park_id = p.id 
        JOIN park_tickets t ON c.ticket_id = t.id 
        WHERE c.user_id = ? LIMIT 0, 25";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId); // Bind the user_id parameter
$stmt->execute();
$result = $stmt->get_result();
$cartItems = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuroraBox</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/cart.css">
    <link rel="stylesheet" href="css/loading.css">
</head>
<body>
<header>


  <!-------NAVBAR SECTION------>
   <?php include 'navbar/navbar.php'; ?>
</header>
    
     <!-- Cart Header Top -->
  <div class="cart-top">
    <h2 class="cart-title">
      <span class="cart-icon">🛒</span> Cart
    </h2>
  </div>
  
  <!-- CART CONTAINER -->
<div class="cart-container">
  <div class="cart-items">
    <div class="cart-header">
      <label class="checkbox-container">
        <input type="checkbox" id="select-all">
        <span class="checkmark"></span>
        All
      </label>
      <button class="remove-btn" onclick="removeSelectedItems()">Remove selected activity</button>
    </div>

    <?php if (empty($cartItems)): ?>
        <p>Your cart is empty. <a href="index.php">Continue shopping</a></p>
    <?php else: ?>
        <?php foreach ($cartItems as $item): ?>
            <div class="cart-item" data-unit-price="<?= $item['unit_price'] ?>">
                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                <label class="checkbox-container">
                    <input type="checkbox" class="item-checkbox" onchange="updateSubtotal()">
                    <span class="checkmark"></span>
                </label>
                
                <img src="<?= htmlspecialchars($item['park_picture']) ?>" alt="<?= htmlspecialchars($item['park_name']) ?>" class="cart-image">
                
                <div class="cart-details">
                    <h3><?= htmlspecialchars($item['ticket_name']) ?> at <?= htmlspecialchars($item['park_name']) ?></h3>
                    <p><small><?= htmlspecialchars($item['visit_date']) ?></small></p>
        
                    <div class="cart-quantity">
                        Adult:
                        <button class="qty-btn" onclick="changeQty(this, -1)">-</button>
                        <span class="qty"><?= $item['quantity'] ?></span>
                        <button class="qty-btn" onclick="changeQty(this, 1)">+</button>
                    </div>
        
<div class="manage-links">
    <a href="park_info.php?id=<?= $item['park_id'] ?>" class="more-info-link">See Details</a>
    <form action="cart.php" method="POST" style="display:inline;">
        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
        <button type="submit" name="action" value="remove" class="remove-link" title="Remove from cart">
            <i class="fas fa-trash"></i>
        </button>
    </form>
</div>
        
                    <div class="cart-price">₱ <?= number_format($item['unit_price'] * $item['quantity']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="cart-summary">
    <div class="summary-line">
      <span>Subtotal</span>
      <span id="subtotal">₱ 0.00</span>
    </div>
    <button class="checkout-btn" onclick="bookNow()">Book now</button>
  </div>
</div>


     <!-------FOOTER SECTION------>
 <?php include 'navbar/footer.html'; ?>
 
      <!-------LOADER SECTION------>
 <?php include 'navbar/loader.html'; ?>



 
<button id="scrollToTopBtn" title="Go to top">&#8679;</button>

<script src="scripts/selectallcart.js"></script>
<script src="scripts/darkmode.js"></script>
<script src="scripts/hidepass.js"></script>
<script src="scripts/scrolltop.js"></script>
<script src="scripts/loader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

<script>
     const isLoggedIn = <?= json_encode(isset($_SESSION["user_id"])) ?>;
    // IF HINDI PA LOG IN
function handleWishlistClick() {
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        // Redirect to wishlist page
        window.location.href = 'wishlist.php';
    }
}

function handleCartClick() {
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        // Redirect to cart page
        window.location.href = 'cart.php';
    }
}

function handleAccountClick() {
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        // Redirect to account page
        window.location.href = 'account.php';
    }
}
</script>

</body>
</html>

<?php $mysqli->close(); ?>