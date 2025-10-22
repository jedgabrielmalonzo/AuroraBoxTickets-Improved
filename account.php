<?php
require_once __DIR__ . '/config.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'gClientSetup.php';

// Check if user is logged in via Google OR password
if (!isset($_SESSION["user_id"]) && !isset($_SESSION['google_email'])) {
    header("Location: login.php"); 
    exit;
}

// Create database connection
$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$mysqli = $conn; // for compatibility

$userId = $_SESSION['user_id'] ?? null;

// Load user info (for normal login users)
$user = null;

if ($userId) {
    $sql = "SELECT * FROM user WHERE id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        error_log("ERROR: No user found with ID: $userId");
        header("Location: login.php");
        exit;
    }
} elseif (isset($_SESSION['google_email'])) {
    $user = [
        'firstname' => $_SESSION['google_name'] ?? '',
        'lastname'  => '',
        'email'     => $_SESSION['google_email'],
        'phone'     => ''
    ];
}

// Handle logout
if (isset($_GET['logout'])) {
    $update_login_sql = "UPDATE user SET last_login = NOW() WHERE id = ?;";
    $stmt = $conn->prepare($update_login_sql);
    $stmt->bind_param('i', $_SESSION["user_id"]);
    
    if ($stmt->execute()) {
        session_destroy();
        header("Location: index.php");
        exit();
    } else {
        echo "Error updating last login: " . $stmt->error;
    }
    $stmt->close();
}

// Search functionality
if (isset($_GET['query'])) {
    $searchQuery = trim($_GET['query']);
    $searchQuery = $conn->real_escape_string($searchQuery);

    $sql = "SELECT p.id, p.name, c.category_name
            FROM parks p
            LEFT JOIN category c ON p.category = c.category_id
            WHERE p.name LIKE ? OR p.description LIKE ?";
    $stmt = $conn->prepare($sql);
    $likeQuery = "%" . $searchQuery . "%";
    $stmt->bind_param("ss", $likeQuery, $likeQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $category = strtolower($row['category_name']);

        if ($category === 'theme parks') {
            header("Location: themeparks.php?search=" . urlencode($searchQuery));
        } elseif ($category === 'aqua parks') {
            header("Location: aquaparks.php?search=" . urlencode($searchQuery));
        } elseif ($category === 'nature parks') {
            header("Location: natureparks.php?search=" . urlencode($searchQuery));
        } elseif ($category === 'museums') {
            header("Location: museums.php?search=" . urlencode($searchQuery));
        } else {
            header("Location: parks.php?search=" . urlencode($searchQuery));
        }
        exit();
    } else {
        $noResult = "No results found for: " . htmlspecialchars($searchQuery);
    }
}

// Fetch parks by category
function fetchParksByCategory($conn, $categoryName, $limit = 5) {
    $cat_sql = "SELECT category_id FROM category WHERE category_name = ?";
    $cat_stmt = $conn->prepare($cat_sql);
    $cat_stmt->bind_param("s", $categoryName);
    $cat_stmt->execute();
    $cat_result = $cat_stmt->get_result();
    
    if ($cat_result->num_rows > 0) {
        $category = $cat_result->fetch_assoc();
        $category_id = $category['category_id'];
        
        $sql = "SELECT p.*, c.category_name,
                MIN(pt.price) as min_price
                FROM parks p 
                LEFT JOIN category c ON p.category = c.category_id
                LEFT JOIN park_tickets pt ON p.id = pt.park_id
                WHERE p.category = ?
                GROUP BY p.id
                ORDER BY p.id DESC 
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $category_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

$themeParks  = fetchParksByCategory($conn, 'Theme Parks', 5);
$aquaParks   = fetchParksByCategory($conn, 'Aqua Parks', 5);
$natureParks = fetchParksByCategory($conn, 'Nature Parks', 5);
$museums     = fetchParksByCategory($conn, 'Museums', 5);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_profile':
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $emergency = trim($_POST['emergency_contact'] ?? '');

    error_log("DEBUG: Update attempt for userId=$userId");
    error_log("DEBUG: Input data - firstname:$firstname, lastname:$lastname, email:$email, phone:$phone, emergency:$emergency");

    if (empty($firstname)) {
        $error_message = "First name is required.";
        break;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Valid email is required.";
        break;
    }
    if (empty($lastname) && empty($user['lastname'])) {
        $error_message = "Last name is required for new accounts.";
        break;
    }

    if ($userId) {
        // Check for duplicate email
        $check_sql = "SELECT id FROM user WHERE email = ? AND id != ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $userId);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error_message = "Email already exists for another account.";
            $check_stmt->close();
            break;
        }
        $check_stmt->close();

        // Preserve existing values if empty
        $update_firstname = !empty($firstname) ? $firstname : $user['firstname'];
        $update_lastname  = !empty($lastname) ? $lastname : ($user['lastname'] ?? '');
        $update_email     = !empty($email) ? $email : $user['email'];
        $phone_value      = !empty($phone) ? $phone : null;
        $emergency_value  = !empty($emergency) ? $emergency : ($user['emergency_contact'] ?? null);

        // ✅ Include emergency_contact in query
        $update_sql = "UPDATE user SET firstname=?, lastname=?, email=?, phone=?, emergency_contact=? WHERE id=?";
        $update_stmt = $mysqli->prepare($update_sql);
        $update_stmt->bind_param("sssssi", $update_firstname, $update_lastname, $update_email, $phone_value, $emergency_value, $userId);

        if ($update_stmt->execute()) {
            error_log("✅ Update SUCCESS for userId=$userId");
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: account.php");
            exit();
        } else {
            $error_message = "Error updating profile: " . $update_stmt->error;
            error_log("❌ Update FAILED - " . $update_stmt->error);
        }
        $update_stmt->close();
    } else {
        $error_message = "Cannot update profile: no user ID.";
        error_log("DEBUG: No user_id in session.");
    }
    break;

        case 'remove_wishlist':
            $park_id = $_POST['park_id'] ?? 0;
            $remove_sql = "DELETE FROM wishlist WHERE user_id=? AND park_id=?";
            $remove_stmt = $mysqli->prepare($remove_sql);
            $remove_stmt->bind_param("ii", $userId, $park_id);
            $remove_stmt->execute();
            $remove_stmt->close();
            header("Location: account.php#wishlist");
            exit();
            break;

        case 'submit_feedback':
            $feedbackText = $_POST['feedback'] ?? '';
            if (!empty($feedbackText)) {
                $stmt = $mysqli->prepare("INSERT INTO feedback (user_id, feedback) VALUES (?, ?)");
                $stmt->bind_param("is", $userId, $feedbackText);
                if ($stmt->execute()) {
                    $success_message = "Thank you for your feedback!";
                } else {
                    $error_message = "Failed to submit feedback: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Please write something before submitting.";
            }
            break;
    }
}


$bookings_sql = "
    SELECT 
        o.*, 
        p.name AS park_name, 
        p.pictures AS park_picture, 
        t.ticket_name, 
        o.created_at 
    FROM orders o
    LEFT JOIN parks p ON o.park_id = p.id
    LEFT JOIN park_tickets t ON o.ticket_id = t.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
";

$bookings_stmt = $mysqli->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $userId);
$bookings_stmt->execute();

$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);

$bookings_stmt->close();




// Wishlist
$wishlist_sql = "SELECT w.*, p.name as park_name, p.pictures as park_picture, p.id as park_id
                 FROM wishlist w 
                 JOIN parks p ON w.park_id = p.id 
                 WHERE w.user_id = ? 
                 ORDER BY w.created_at DESC";
$wishlist_stmt = $mysqli->prepare($wishlist_sql);
$wishlist_stmt->bind_param("i", $userId);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();
$wishlist_items = $wishlist_result->fetch_all(MYSQLI_ASSOC);
$wishlist_stmt->close();

// Reviews
$reviews_sql = "SELECT r.*, p.name AS park_name
                FROM reviews r
                LEFT JOIN parks p ON r.park_id = p.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";
$reviews_stmt = $mysqli->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $userId);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
$reviews_stmt->close();

// Redeemed promos (only show promos that still exist in the promos table)
$redeemed_promos_sql = "SELECT pr.*, p.code, p.description, p.type, p.value, p.expiration_date
                        FROM promo_redemptions pr 
                        INNER JOIN promos p ON pr.promo_id = p.id 
                        WHERE pr.user_id = ? 
                        ORDER BY pr.redeemed_at DESC";
$redeemed_promos_stmt = $mysqli->prepare($redeemed_promos_sql);
$redeemed_promos_stmt->bind_param("i", $userId);
$redeemed_promos_stmt->execute();
$redeemed_promos_result = $redeemed_promos_stmt->get_result();
$redeemed_promos = $redeemed_promos_result->fetch_all(MYSQLI_ASSOC);
$redeemed_promos_stmt->close();

// Display name
$display_name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
if (empty($display_name)) {
    $display_name = $_SESSION['google_name'] ?? $user['email'] ?? 'User';
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Remove after displaying
}

// Debug user data
error_log("DEBUG: User data - " . json_encode($user));
error_log("DEBUG: Display name: $display_name");

// Debugging
error_log("DEBUG: User created date: " . ($user['created'] ?? 'NULL'));
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account - AuroraBox</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>     
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">   
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/account.css">
  <link rel="stylesheet" href="css/homepage.css">
  <link rel="stylesheet" href="css/login.css">
  <link rel="stylesheet" href="css/loading.css">
</head>
   
<body>
<header>
    <!-------HEADER SECTION------>
   <?php include 'navbar/navbar.php'; ?>
   
</header> 

       <!-- Cart Header Top -->
  <div class="cart-top">
    <h2 class="cart-title">
      <span class="cart-icon">👤</span> Account
    </h2>
  </div>

   <!-- Main -->
  <main class="acc-container">
    <!-- Sidebar -->
    <aside>
      <div class="acc-profile-card">
        <label class="acc-avatar" for="avatarInput">
          <img id="avatarImg" src="https://picsum.photos/seed/<?= md5($user['email'] ?? 'default') ?>/200" alt="Profile">
        </label>
        <input type="file" id="avatarInput" accept="image/*">
        <div>
          <div class="acc-name"><?= htmlspecialchars($display_name) ?></div>
          <div class="acc-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
        </div>
      </div>
      <nav class="acc-menu">
        <a class="active" data-target="personal">Personal Info</a>
        <a data-target="bookings">Bookings</a>
        <a data-target="reviews">Reviews</a>
        <a data-target="wishlist">Wishlist</a>
        <a data-target="settings">Settings</a>
        <a data-target="promos">Promos</a>
      </nav>
    </aside>

    <!-- Content -->
    <section class="acc-content">
      
      <?php if (isset($success_message)): ?>
        <div class="acc-alert acc-success"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="acc-alert acc-error"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <?php include __DIR__ . '/account-personal.php'; ?>
      <?php include __DIR__ . '/account-bookings.php'; ?>
      <?php include __DIR__ . '/account-reviews.php'; ?>
      <?php include __DIR__ . '/account-wishlist.php'; ?>
      <?php include __DIR__ . '/account-settings.php'; ?>
      <?php include __DIR__ . '/account-promos.php'; ?>
            </section>
  </main>

  
  
   <!-------FOOTER SECTION------>
 <?php include 'navbar/footer.html'; ?>
 
       <!-------LOADER SECTION------>
 <?php include 'navbar/loader.html'; ?>



<button id="scrollToTopBtn" title="Go to top">&#8679;</button>

<script src="scripts/accounthandler.js"></script>
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