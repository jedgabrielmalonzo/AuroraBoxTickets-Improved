<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'gClientSetup.php';

// Check if user is logged in via Google OR password
if (!isset($_SESSION["user_id"]) && !isset($_SESSION['google_email'])) {
  header("Location: login.php"); 
  exit;
}

// Database connection (centralized)
$mysqli = require __DIR__ . '/database.php';
if (!$mysqli || $mysqli->connect_error) {
  die('Database connection failed.');
}
$conn = $mysqli; // gamitin pareho

$userId = $_SESSION['user_id'] ?? null;

// Clean admin session interference if any
if (isset($_SESSION['admin_id']) && !isset($_GET['admin_mode'])) {
    error_log("DEBUG: Admin session detected, but in user mode. User ID: $userId");
}

// Load user info (for normal login users)
$user = null;

if ($userId) {
    // DIRECT APPROACH: Use SELECT * from start to avoid field truncation issues
    error_log("FETCHING USER DATA FOR ID: $userId");
    
    $sql = "SELECT * FROM user WHERE id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        error_log("ERROR: SQL prepare failed: " . $mysqli->error);
        die("Database error occurred.");
    }
    
    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        error_log("ERROR: SQL execute failed: " . $stmt->error);
        die("Query execution failed.");
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        error_log("ERROR: get_result failed: " . $mysqli->error);
        die("Result retrieval failed.");
    }
    
    $user = $result->fetch_assoc();
    
    // Log what we actually got
    error_log("SELECT * QUERY RESULT: " . json_encode($user));
    error_log("TOTAL FIELDS RETRIEVED: " . count($user ?? []));
    
    if ($user) {
        error_log("✅ RETRIEVED FIELDS: " . implode(', ', array_keys($user)));
        error_log("✅ FIRSTNAME: " . ($user['firstname'] ?? 'NULL'));
        error_log("✅ LASTNAME: " . ($user['lastname'] ?? 'NULL'));
        error_log("✅ EMAIL: " . ($user['email'] ?? 'NULL'));
        error_log("✅ PHONE: " . ($user['phone'] ?? 'NULL'));
    } else {
        error_log("❌ NO USER DATA RETRIEVED");
    }
    
    // CRITICAL: Immediate field validation and recovery - FORCE COMPLETE RECOVERY
    $field_count = $user ? count($user) : 0;
    $expected_fields = ['id', 'firstname', 'lastname', 'email', 'phone', 'created', 'last_login', 'email_verified'];
    
    // Secondary recovery if still incomplete
    if (!$user || $field_count < 8 || !array_key_exists('phone', $user ?? []) || !array_key_exists('lastname', $user ?? []) || !array_key_exists('email', $user ?? [])) {
        error_log("FORCING COMPLETE RECOVERY: Got " . $field_count . " fields, need all 8");
        error_log("CURRENT FIELDS: " . implode(', ', array_keys($user ?? [])));
        error_log("MISSING FIELDS: " . implode(', ', array_diff($expected_fields, array_keys($user ?? []))));
        
  // GUARANTEED recovery with fresh connection
  $recovery_conn = require __DIR__ . '/database.php';
        if (!$recovery_conn->connect_error) {
            // Use SELECT * to get ALL fields from database
            $recovery_sql = "SELECT * FROM user WHERE id = ? LIMIT 1";
            $recovery_stmt = $recovery_conn->prepare($recovery_sql);
            
            if ($recovery_stmt) {
                $recovery_stmt->bind_param("i", $userId);
                if ($recovery_stmt->execute()) {
                    $recovery_result = $recovery_stmt->get_result();
                    if ($recovery_result && $recovery_result->num_rows > 0) {
                        $recovered_user = $recovery_result->fetch_assoc();
                        $recovered_count = count($recovered_user ?? []);
                        
                        // FORCE replacement regardless of field count
                        if ($recovered_user && $recovered_count > $field_count) {
                            error_log("✅ RECOVERY SUCCESS: Got " . $recovered_count . " fields via SELECT *");
                            error_log("✅ RECOVERY FIELDS: " . implode(', ', array_keys($recovered_user)));
                            error_log("✅ RECOVERY EMAIL: " . ($recovered_user['email'] ?? 'NULL'));
                            error_log("✅ RECOVERY LASTNAME: " . ($recovered_user['lastname'] ?? 'NULL'));
                            error_log("✅ RECOVERY PHONE: " . ($recovered_user['phone'] ?? 'NULL'));
                            
                            // FORCE replace the incomplete user data
                            $user = $recovered_user;
                            error_log("✅ USER DATA REPLACED SUCCESSFULLY");
                        } else {
                            error_log("❌ RECOVERY FAILED: Got " . $recovered_count . " fields, not better than " . $field_count);
                            error_log("❌ RECOVERY DATA: " . json_encode($recovered_user));
                        }
                    } else {
                        error_log("❌ RECOVERY FAILED: No rows returned from recovery query");
                    }
                } else {
                    error_log("❌ RECOVERY FAILED: Could not execute recovery query - " . $recovery_conn->error);
                }
                $recovery_stmt->close();
            } else {
                error_log("❌ RECOVERY FAILED: Could not prepare recovery query - " . $recovery_conn->error);
            }
        } else {
            error_log("❌ RECOVERY FAILED: Could not connect to database - " . $recovery_conn->connect_error);
        }
        $recovery_conn->close();
    }
    
    // Debug: Check field count and what we got
    error_log("DEBUG: Expected 8 fields, got " . count($user ?? []) . " fields");
    error_log("DEBUG: Available fields: " . implode(', ', array_keys($user ?? [])));
    
    $stmt->close();
    
    // Debug: Check what we actually got from database
    error_log("DEBUG: Raw SQL result for user ID $userId: " . json_encode($user));
    error_log("DEBUG: Phone field check - exists: " . (array_key_exists('phone', $user ?? []) ? 'YES' : 'NO'));
    if ($user && array_key_exists('phone', $user)) {
        error_log("DEBUG: Phone value type: " . gettype($user['phone']) . ", value: '" . $user['phone'] . "'");
    }
    
    // Ensure user exists
    if (!$user) {
        error_log("ERROR: No user found with ID: $userId");
        header("Location: login.php");
        exit;
    }
    
    // Check if we got incomplete data and try to fix it
    $expected_fields = ['id', 'firstname', 'lastname', 'email', 'phone', 'created', 'last_login', 'email_verified'];
    $missing_fields = array_diff($expected_fields, array_keys($user ?? []));
    
    if (!empty($missing_fields) || count($user ?? []) < 8) {
        error_log("CRITICAL: Incomplete user data detected. Got " . count($user ?? []) . " fields, expected 8");
        error_log("Missing fields: " . implode(', ', $missing_fields));
        
        // Force a fresh connection and query
  $fresh_conn = require __DIR__ . '/database.php';
        
        if (!$fresh_conn->connect_error) {
            // Try multiple query approaches
            $queries_to_try = [
                "SELECT * FROM user WHERE id = ?",
                "SELECT id, firstname, lastname, email, phone, created, last_login, email_verified FROM user WHERE id = ?",
                "SELECT id, firstname, lastname, email, COALESCE(phone, '') as phone, created, last_login, email_verified FROM user WHERE id = ?"
            ];
            
            foreach ($queries_to_try as $index => $alt_sql) {
                $alt_stmt = $fresh_conn->prepare($alt_sql);
                if ($alt_stmt) {
                    $alt_stmt->bind_param("i", $userId);
                    if ($alt_stmt->execute()) {
                        $alt_result = $alt_stmt->get_result();
                        $alt_user = $alt_result->fetch_assoc();
                        
                        if ($alt_user && count($alt_user) >= 8) {
                            error_log("SUCCESS: Query " . ($index + 1) . " returned complete data (" . count($alt_user) . " fields)");
                            $user = $alt_user;
                            break;
                        } else {
                            error_log("Query " . ($index + 1) . " failed or incomplete: " . count($alt_user ?? []) . " fields");
                        }
                    }
                    $alt_stmt->close();
                }
            }
        }
        $fresh_conn->close();
    }
    
    // Final validation and fallback
    if (!array_key_exists('phone', $user ?? [])) {
        error_log("FALLBACK: Adding missing phone field as NULL");
        $user['phone'] = null;
    }
    
    // Log final result
    error_log("FINAL: User data has " . count($user ?? []) . " fields: " . implode(', ', array_keys($user ?? [])));
    
    // Handle Google login scenario - merge session data with database data
    if (isset($_SESSION['google_email']) && empty($user['email'])) {
        $user['email'] = $_SESSION['google_email'];
    }
} elseif (isset($_SESSION['google_email'])) {
    $user = [
        'firstname' => $_SESSION['google_name'] ?? '',
        'lastname'  => '',
        'email'     => $_SESSION['google_email'],
        'phone'     => ''
    ];
    error_log("DEBUG: Google login detected.");
}




// Handle logout
if (isset($_GET['logout'])) {
    // Update last_login timestamp before destroying the session
    $update_login_sql = "UPDATE user SET last_login = NOW() WHERE id = ?;";
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

// Fetch bookings
$bookings_sql = "SELECT o.*, p.name as park_name, p.pictures as park_picture, t.ticket_name, o.created_at
                 FROM orders o 
                 LEFT JOIN parks p ON o.park_id = p.id 
                 LEFT JOIN park_tickets t ON o.ticket_id = t.id 
                 WHERE o.user_id = ? 
                 ORDER BY o.created_at DESC 
                 LIMIT 10";
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

      <!-- Personal Info -->
      <div id="personal" class="acc-card active">
        <h3>Personal Information</h3>
        <p class="acc-sub">Update your personal details.</p>
        
        <form method="POST" action="account.php">
          <input type="hidden" name="action" value="update_profile">
          
          <?php
          $stmt = $conn->prepare("SELECT firstname, lastname, email, phone, emergency_contact FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();


          // Dynamic form population - automatically use data from signup/login
          $form_firstname = $user['firstname'] ?? '';
          $form_lastname = $user['lastname'] ?? '';
          $form_email = $user['email'] ?? '';
          
          // Handle phone field dynamically - preserve existing data or allow new entry
          $form_phone = '';
          if (is_array($user) && array_key_exists('phone', $user) && !is_null($user['phone']) && trim($user['phone']) !== '') {
              $form_phone = trim($user['phone']);
          }
          
          // Handle emergency contact field dynamically - preserve existing data or allow new entry
$form_emergency = '';
if (is_array($user) && array_key_exists('emergency_contact', $user) && !is_null($user['emergency_contact']) && trim($user['emergency_contact']) !== '') {
    $form_emergency = trim($user['emergency_contact']);
}

          
          // Auto-populate from Google login if available and database is empty
          if (isset($_SESSION['google_name']) && empty($form_firstname)) {
              $name_parts = explode(' ', $_SESSION['google_name'], 2);
              $form_firstname = $name_parts[0] ?? '';
              if (empty($form_lastname) && isset($name_parts[1])) {
                  $form_lastname = $name_parts[1];
              }
          }
          
          // Debug form values
          error_log("DEBUG: Dynamic form values - firstname: '$form_firstname', lastname: '$form_lastname', email: '$form_email', phone: '$form_phone'");
          ?>
          
          <div class="acc-grid acc-cols-2">
            <div>
              <label>First Name</label>
              <input class="acc-input" name="firstname" value="<?= htmlspecialchars($form_firstname) ?>" placeholder="First Name" required>
            </div>
            <div>
              <label>Last Name</label>
              <input class="acc-input" name="lastname" value="<?= htmlspecialchars($form_lastname) ?>" placeholder="Last Name" <?= empty($form_lastname) ? 'required' : '' ?>>
            </div>
          </div>
          
         <div class="acc-grid acc-cols-2" style="margin-top:12px">
  <div>
    <label>Email</label>
    <input class="acc-input" type="email" name="email" value="<?= htmlspecialchars($form_email) ?>" placeholder="Email Address" required>
  </div>
  <div>
    <label>Phone</label>
    <input class="acc-input" type="tel" name="phone" value="<?= htmlspecialchars($form_phone) ?>" placeholder="(+63) 900 000 0000">
  </div>
</div>

<div class="acc-grid acc-cols-2" style="margin-top:12px">
  <div>
    <label>Emergency Contact</label>
    <input class="acc-input" type="tel" name="emergency_contact" value="<?= htmlspecialchars($form_emergency) ?>" placeholder="(+63) 900 000 0000" required>
  </div>
</div>

          

          
          <div style="margin-top:16px">
            <button type="submit" class="acc-btn acc-primary">Update Profile</button>
          </div>
        </form>
      </div>

     <!-- Bookings -->
<div id="bookings" class="acc-card">
  <h3>Bookings</h3>
  <p class="acc-sub">Your recent activity bookings.</p>

  <?php
    // Setup pagination
    $bookings_per_page = 6;
    $booking_page = isset($_GET['booking_page']) ? max(1, intval($_GET['booking_page'])) : 1;
    $total_bookings = count($bookings);
    $booking_start = ($booking_page - 1) * $bookings_per_page;
    $paginated_bookings = array_slice($bookings, $booking_start, $bookings_per_page);
  ?>

  <?php if (empty($bookings)): ?>
    <p>No bookings found. <a href="home.php">Start exploring!</a></p>
  <?php else: ?>
    <div class="acc-list">
      <?php foreach ($paginated_bookings as $booking): ?>
        <div class="acc-item">
          <img src="<?= htmlspecialchars($booking['park_picture'] ?? 'images/default-park.jpg') ?>" class="acc-thumb" alt="Booking">
          <div class="acc-item-details">
            <div class="acc-item-title">
              <?= htmlspecialchars($booking['ticket_name'] ?? 'Park Ticket') ?> 
              <?php if ($booking['park_name']): ?>
                at <?= htmlspecialchars($booking['park_name']) ?>
              <?php endif; ?>
            </div>
            <div class="acc-item-sub">
              Order #<?= $booking['id'] ?> – 
              <span class="acc-booking-status status-<?= $booking['status'] ?>">
                <?= ucfirst($booking['status']) ?>
              </span>
              <br>
              <small>Booked on: <?= date('M j, Y', strtotime($booking['created_at'])) ?></small>
              <?php if ($booking['visit_date']): ?>
                <br><small>Visit Date: <?= date('M j, Y', strtotime($booking['visit_date'])) ?></small>
              <?php endif; ?>
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-weight: 600;">₱<?= number_format($booking['price'] * $booking['quantity']) ?></div>
            <small>Qty: <?= $booking['quantity'] ?></small>
          
            <?php if ($booking['status'] === 'confirmed'): ?>
              <br>
              <a href="refund.php?payment_id=<?= urlencode($booking['payment_ref']) ?>" 
                 class="btn btn-danger btn-sm mt-2"
                 onclick="return confirm('Are you sure you want to request a refund for this booking?');">
                 Request Refund
              </a>
            <?php elseif ($booking['status'] === 'refunded'): ?>
              <br><span class="badge bg-success mt-2">Refunded</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_bookings > $bookings_per_page): ?>
      <div class="pagination">
        <?php if ($booking_page > 1): ?>
          <a href="?booking_page=<?= $booking_page - 1 ?>#bookings" class="page-link">Prev</a>
        <?php endif; ?>

        <span class="page-info">Page <?= $booking_page ?> of <?= ceil($total_bookings / $bookings_per_page) ?></span>

        <?php if ($booking_page * $bookings_per_page < $total_bookings): ?>
          <a href="?booking_page=<?= $booking_page + 1 ?>#bookings" class="page-link">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

      <!-- Reviews -->
      <div id="reviews" class="acc-card">
        <h3>Reviews</h3>
        <p class="acc-sub">Your submitted reviews.</p>
        
        <?php if (empty($reviews)): ?>
            <p>You haven’t submitted any reviews yet. <a href="home.php">Start reviewing!</a></p>
        <?php else: ?>
            <div class="acc-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="acc-item">
                        <div class="acc-item-details">
                            <div class="acc-item-title"><?= htmlspecialchars($review['park_name'] ?? 'Park') ?></div>
                            <div class="acc-item-sub">
                                Rating: <?= intval($review['rating']) ?> / 5<br>
                                <?= htmlspecialchars($review['comment']) ?><br>
                                <small>Submitted on: <?= date('M j, Y', strtotime($review['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>

     <!-- Wishlist -->
<div id="wishlist" class="acc-card">
  <h3>Wishlist</h3>
  <p class="acc-sub">Saved items you want to book later.</p>

  <?php
    // Setup pagination
    $wishlist_per_page = 6;
    $wishlist_page = isset($_GET['wishlist_page']) ? max(1, intval($_GET['wishlist_page'])) : 1;
    $total_wishlist = count($wishlist_items);
    $wishlist_start = ($wishlist_page - 1) * $wishlist_per_page;
    $paginated_wishlist = array_slice($wishlist_items, $wishlist_start, $wishlist_per_page);
  ?>

  <?php if (empty($wishlist_items)): ?>
    <p>Your wishlist is empty. <a href="home.php">Discover amazing places!</a></p>
  <?php else: ?>
    <div class="acc-list" id="wishlist-list">
      <?php foreach ($paginated_wishlist as $item): ?>
        <div class="acc-item">
          <img src="<?= htmlspecialchars($item['park_picture']) ?>" class="acc-thumb" alt="Wishlist">
          <div class="acc-item-details">
            <div class="acc-item-title"><?= htmlspecialchars($item['park_name']) ?></div>
            <div class="acc-item-sub">Added on: <?= date('M j, Y', strtotime($item['created_at'])) ?></div>
          </div>
          <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="remove_wishlist">
            <input type="hidden" name="park_id" value="<?= $item['park_id'] ?>">
            <button type="submit" class="acc-heart-btn" onclick="return confirm('Remove from wishlist?')">
              <svg viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41 0.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
              </svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_wishlist > $wishlist_per_page): ?>
      <div class="pagination">
        <?php if ($wishlist_page > 1): ?>
          <a href="?wishlist_page=<?= $wishlist_page - 1 ?>#wishlist" class="page-link">Prev</a>
        <?php endif; ?>

        <span class="page-info">Page <?= $wishlist_page ?> of <?= ceil($total_wishlist / $wishlist_per_page) ?></span>

        <?php if ($wishlist_page * $wishlist_per_page < $total_wishlist): ?>
          <a href="?wishlist_page=<?= $wishlist_page + 1 ?>#wishlist" class="page-link">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>


      <!-- Settings -->
<div id="settings" class="acc-card">
    <h3>Settings</h3>
    
    <?php
if (isset($_POST['update_password'])) {
    $newPassword = $_POST['new_password'];

    // Securely hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update query (note: column name = password_hash)
    $stmt = $conn->prepare("UPDATE user SET password_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);

    if ($stmt->execute()) {
        echo '<p style="color:green;">Password updated successfully!</p>';
    } else {
        echo '<p style="color:red;">Error updating password. Please try again.</p>';
    }

    $stmt->close();
}
?>

    <p class="acc-sub">Manage your account settings.</p>

    <form method="POST" action="">
    <div class="acc-grid acc-cols-2">
        <div>
            <label>Current Email</label>
            <input class="acc-input" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
        </div>

        <div>
            <label>Change Password</label>
            <input class="acc-input" type="password" name="new_password" placeholder="Enter new password" required>
        </div>
    </div>

    <button type="submit" name="update_password" class="acc-btn acc-primary">Update Password</button>
</form>


    <form method="POST" action="account.php">
        <input type="hidden" name="action" value="submit_feedback">
        <div style="margin-top:16px">
            <label>Leave Feedback</label>
            <textarea class="acc-input" name="feedback" rows="4" placeholder="Write your feedback here..."></textarea>
        </div>
        <div style="margin-top:12px">
            <button type="submit" class="acc-btn acc-primary">Submit Feedback</button>
        </div>
    </form>

    <div style="margin-top:16px;display:flex;gap:12px">
        <button class="acc-btn acc-primary" disabled>Save Changes</button>
        <button class="acc-btn acc-danger" onclick="if(confirm('Are you sure you want to delete your account? This cannot be undone.')) { alert('Account deletion feature coming soon.'); }">Delete Account</button>
    </div>

    <h3 style="margin-top:28px">Additional Account Info</h3>
    <?php 
$stmt = $conn->prepare("SELECT created, last_login, email_verified FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc(); // <-- dito mo makukuha yung created, last_login, at email_verified
$stmt->close();
?>

    <div class="acc-info-cards">
        <div class="acc-info-card">
            <h4>Account Created:</h4>
<p><?= isset($user['created']) ? date('F j, Y', strtotime($user['created'])) : 'Unknown' ?></p>        </div>
        <div class="acc-info-card">
            <h4>Last Login:</h4>
<p><?= isset($user['last_login']) ? date('F j, Y, g:i A', strtotime($user['last_login'])) : 'Unknown' ?></p>        </div>
        <div class="acc-info-card">
            <h4>Email Verified:</h4>
            <p>
                <p>
    <?php if (isset($user['email_verified']) && $user['email_verified'] == 0): ?>
        <a href="resend_verification.php" class="acc-btn acc-primary">Verify Now</a>
    <?php else: ?>
        <span style="color:green; font-weight:bold;">✔ Verified</span>
    <?php endif; ?>
</p>
            </p>
        </div>
    </div>
</div>

<!-- Promos - CORRECTED SECTION -->
<div id="promos" class="acc-card">
  <h3>Redeemed Promos</h3>
  <p class="acc-sub">Here are the promos you've redeemed.</p>

  <?php if (empty($redeemed_promos)): ?>
    <p>You haven't redeemed any promos yet. <a href="home.php">Browse available promos</a>.</p>
  <?php else: ?>
    <div class="acc-list">
      <?php foreach ($redeemed_promos as $promo): ?>
        <div class="acc-item">
          <div class="acc-item-details">
            <div class="acc-item-title">
              <?= !empty($promo['description']) ? htmlspecialchars($promo['description']) : 'Promo Code' ?>
            </div>
            <div class="acc-item-sub">
              <small>Redeemed on: <?= !empty($promo['redeemed_at']) ? date('M j, Y', strtotime($promo['redeemed_at'])) : 'N/A' ?></small>
              <?php if (!empty($promo['expiration_date'])): ?>
                <br><small>Expires: <?= date('M j, Y', strtotime($promo['expiration_date'])) ?></small>
              <?php endif; ?>
            </div>
          </div>

          <div style="text-align: right; min-width:120px;">
            <div class="promo-code" style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin-bottom: 4px;">
              <?= htmlspecialchars($promo['code'] ?? 'N/A') ?>
            </div>
            <small>
              <?= $promo['type'] == 'percentage' ? $promo['value'].'% OFF' : '₱'.number_format($promo['value']).' OFF' ?>
            </small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>


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