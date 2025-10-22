 <?php
require_once __DIR__ . '/config.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'gClientSetup.php';

// Initialize the cart count variable
$cartCount = 0;

// If password-login user, load their DB info
if (isset($_SESSION["user_id"])) {
    $mysqli = require __DIR__ . "/database.php";
    
    // Fetch user details
    $sql = "SELECT * FROM user WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Fetch cart count from the database
    $cart_sql = "SELECT COUNT(*) AS cart_count FROM cart WHERE user_id = ?";
    $cart_stmt = $mysqli->prepare($cart_sql);
    $cart_stmt->bind_param("i", $_SESSION["user_id"]);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cartCount = $cart_result->fetch_assoc()['cart_count'];
}
$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

// Removed duplicate DB params (using database.php)


// Get park ID from URL
$park_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$park_id) {
    header("Location: index.php");
    exit();
}

// After fetching park details
$isWished = false; // Default state

if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];
    $wishlist_sql = "SELECT * FROM wishlist WHERE user_id = ? AND park_id = ?";
    $wishlist_stmt = $conn->prepare($wishlist_sql);
    $wishlist_stmt->bind_param("ii", $user_id, $park_id);
    $wishlist_stmt->execute();
    $wishlist_result = $wishlist_stmt->get_result();

    if ($wishlist_result->num_rows > 0) {
        $isWished = true; // Park is in the wishlist
    }
}

// Fetch park details
$park_sql = "SELECT p.*, c.category_name
FROM parks p
LEFT JOIN category c ON p.category = c.category_id
WHERE p.id = ?";
$stmt = $conn->prepare($park_sql);
$stmt->bind_param("i", $park_id);
$stmt->execute();
$park_result = $stmt->get_result();
$park = $park_result->fetch_assoc();

if (!$park) {
    header("Location: index.php");
    exit();
}

// Fetch park tickets
$tickets_sql = "SELECT * FROM park_tickets WHERE park_id = ? ORDER BY price ASC";
$ticket_stmt = $conn->prepare($tickets_sql);
$ticket_stmt->bind_param("i", $park_id);
$ticket_stmt->execute();
$tickets_result = $ticket_stmt->get_result();
$tickets = $tickets_result->fetch_all(MYSQLI_ASSOC);

// Process images
$images = !empty($park['pictures']) ? array_filter(explode(',', $park['pictures'])) : [];
$main_image = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';
$side_images = array_slice($images, 1, 2);

// Get minimum price
$min_price = !empty($tickets) ? $tickets[0]['price'] : 0;

// --- Add review with photos ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['rating'])) {
    if (isset($_SESSION["user_id"])) {
        $user_id = $_SESSION["user_id"];
        
        // Check if the user has made a purchase for the park
        $booking_check_sql = "SELECT id FROM orders WHERE user_id = ? AND park_id = ? AND status = 'confirmed'";
        $booking_check_stmt = $conn->prepare($booking_check_sql);
        $booking_check_stmt->bind_param("ii", $user_id, $park_id);
        $booking_check_stmt->execute();
        $booking_check_result = $booking_check_stmt->get_result();

        if ($booking_check_result->num_rows === 0) {
            $_SESSION['review_error'] = "❌ You need to book this park before you can leave a review.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $park_id);
            exit();
        }

        // Get the order ID associated with the latest purchase
        $order = $booking_check_result->fetch_assoc();
        $order_id = $order['id'];

        // Check if a review already exists for this order
        $review_check_sql = "SELECT COUNT(*) AS cnt FROM reviews WHERE order_id = ?";
        $review_check_stmt = $conn->prepare($review_check_sql);
        $review_check_stmt->bind_param("i", $order_id);
        $review_check_stmt->execute();
        $review_check_result = $review_check_stmt->get_result()->fetch_assoc();

        if ($review_check_result['cnt'] > 0) {
            $_SESSION['review_error'] = "❌ You can only leave one review per purchase.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $park_id);
            exit();
        }

        // Proceed with inserting the review
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        $insert_sql = "INSERT INTO reviews (user_id, park_id, rating, comment, order_id) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiisi", $user_id, $park_id, $rating, $comment, $order_id);
        $insert_stmt->execute();
        
        // Redirect back to the same page
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $park_id);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}


// --- Fetch reviews with photos ---
$reviews_sql = "SELECT r.*, u.email AS user_name
FROM reviews r
JOIN user u ON r.user_id = u.id
WHERE r.park_id = ?
ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $park_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews = [];

while ($review = $reviews_result->fetch_assoc()) {
    $photo_sql = "SELECT photo_path FROM review_photos WHERE review_id = ?";
    $photo_stmt = $conn->prepare($photo_sql);
    $photo_stmt->bind_param("i", $review['id']);
    $photo_stmt->execute();
    $photo_result = $photo_stmt->get_result();
    $review['photos'] = $photo_result->fetch_all(MYSQLI_ASSOC);

    $reviews[] = $review;
}

// Fetch total bookings for this park using the orders table
$bookings_sql = "SELECT COUNT(*) AS total_bookings
                 FROM orders
                 WHERE park_id = ? AND status = 'confirmed'";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $park_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$total_bookings = $bookings_result->fetch_assoc()['total_bookings'];

// --- Fetch average rating and review count ---
$rating_sql = "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
FROM reviews WHERE park_id = ?";
$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $park_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result()->fetch_assoc();

$avg_rating = $rating_result['avg_rating'] ? round($rating_result['avg_rating'], 1) : 0;
$total_reviews = $rating_result['total_reviews'];

// --- Fetch breakdown per star ---
$breakdown = [];
for ($i = 5; $i >= 1; $i--) {
    $count_sql = "SELECT COUNT(*) as cnt FROM reviews WHERE park_id = ? AND rating = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ii", $park_id, $i);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $breakdown[$i] = $count_result['cnt'];
}

// Close the database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($park['name']) ?> - AuroraBox</title>
<link rel="icon" type="image/x-icon" href="images/favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="css/login.css">
<link rel="stylesheet" href="css/homepage.css">
<link rel="stylesheet" href="css/navbar.css">
<link rel="stylesheet" href="css/navbar-footer.css">
<link rel="stylesheet" href="css/park_info.css">
<link rel="stylesheet" href="css/navnew.css">
<link rel="stylesheet" href="css/sunmoon.css">
<link rel="stylesheet" href="css/scrolltop.css">
<link rel="stylesheet" href="css/loading.css">


<script>
let isWished = false; // Track if the park is wished

function toggleWishlist(parkId) {
const wishlistBtn = document.getElementById('wishlist-btn');
const heartIcon = wishlistBtn.querySelector('i');

// Toggle the state
isWished = !isWished;

if (isWished) {
wishlistBtn.classList.remove('not-wished');
wishlistBtn.classList.add('wished');
heartIcon.classList.remove('far'); // Remove hollow class
heartIcon.classList.add('fas'); // Add solid class
} else {
wishlistBtn.classList.remove('wished');
wishlistBtn.classList.add('not-wished');
heartIcon.classList.remove('fas'); // Remove solid class
heartIcon.classList.add('far'); // Add hollow class
}

// Send AJAX request to add/remove from wishlist
fetch('wishlist.php', {
method: 'POST',
headers: {
'Content-Type': 'application/json',
},
body: JSON.stringify({ action: isWished ? 'add' : 'remove', parkId: parkId }),
})
.then(response => response.json())
.then(data => {
console.log(data); // Check response status
if (data.status === 'added') {
alert('Added to wishlist!');
} else if (data.status === 'removed') {
alert('Removed from wishlist!');
}
});
}
</script>
</head>
<style>
   .notification-dot {
    position: absolute;
    top: -6px;
    right: -8px;
    background: red;
    color: white;
    font-size: 12px;
    font-weight: bold;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    padding: 2px; /* dagdag spacing para di dikit */
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    text-align: center;
    box-sizing: border-box;
}
@keyframes glimmer {
  0% { box-shadow: 0 0 5px 2px rgba(255,0,0,0.6); }
  50% { box-shadow: 0 0 12px 5px rgba(255,0,0,1); }
  100% { box-shadow: 0 0 5px 2px rgba(255,0,0,0.6); }
}

.glimmer {
  animation: glimmer 1s ease-in-out 3; /* uulit ng 3 beses */
}

</style>
<body>

<header>

<!-------NAVBAR SECTION------>
   <?php include 'navbar/navbar.php'; ?>
   
</header>


<div class="PARKSHOW">
<h2 class="d-flex align-items-center">
<?= strtoupper(htmlspecialchars($park['name'])) ?> TICKET
<div class="wishlist-btn <?= $isWished ? 'wished' : 'not-wished' ?> ms-2" id="wishlist-btn" onclick="toggleWishlist(<?= $park['id'] ?>)">
<i class="<?= $isWished ? 'fas' : 'far' ?> fa-heart"></i> <!-- Solid or hollow heart -->
</div>
</h2>
<div class="park-header d-flex align-items-center flex-nowrap">
  <span class="rating me-2">⭐ <?= $avg_rating ?>/5</span>
  <span class="reviews me-2"><?= number_format($total_reviews) ?> reviews</span>
  <span class="booked me-2"><?= number_format($total_bookings) ?> booked</span>

</div>



<div class="park-main">
<div class="main-img">
<img src="<?= htmlspecialchars($main_image) ?>" alt="<?= htmlspecialchars($park['name']) ?>">
</div>
<div class="side-imgs">
<?php foreach($side_images as $img): ?>
<img src="<?= htmlspecialchars(trim($img)) ?>" alt="<?= htmlspecialchars($park['name']) ?>">
<?php endforeach; ?>
</div>
</div>

<div class="info-ticket">
<div class="info">
<h3>BRIEF INFORMATION</h3>
<ul>
<li><?= htmlspecialchars($park['description'] ?: 'Experience an amazing day at ' . $park['name']) ?></li>
<li>Location: <?= htmlspecialchars($park['address'] . ', ' . $park['city'] . ', ' . $park['country']) ?></li>
<li>Category: <?= htmlspecialchars($park['category_name'] ?: 'Entertainment') ?></li>
</ul>
</div>

<div class="ticket-box">
<div id="ticket-content">
<p class="price">Ticket Price<br><span>₱ <?= number_format($min_price) ?></span></p>
</div>
</div>
</div>

<div class="ticket-options">
<h2>| TICKETING OPTIONS</h2>

<?php if (!empty($tickets)): ?>
<?php foreach($tickets as $ticket): ?>
<div class="ticket-card">
<div class="ticket-header">
<h3><?= strtoupper(htmlspecialchars($ticket['ticket_name'])) ?></h3>
<span class="price">₱ <?= number_format($ticket['price']) ?> / PERSON</span>
</div>
<div class="ticket-info">
<p class="note">No cancellation · Valid on the selected date · Enter with voucher · Instant confirmation</p>
<div class="package-details">
<h4>PACKAGE DETAILS</h4>
<p><?= htmlspecialchars($ticket['details'] ?: 'Admission to ' . $park['name']) ?></p>
</div>
<button class="select-btn" onclick="replaceWithBooking(this, '<?= htmlspecialchars($ticket['ticket_name']) ?>', <?= $ticket['price'] ?>, <?= $ticket['id'] ?>)">Select</button>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="info">
<p>Ticket options coming soon!</p>
</div>
<?php endif; ?>
</div>

<div class="what-to-expect">
<h2>| WHAT TO EXPECT</h2>
<p><?= nl2br(htmlspecialchars($park['what_to_expect'] ?: 'Experience the wonders of ' . $park['name'])) ?></p>
</div>

<div class="location">
<h2>| LOCATION</h2>
<p><?= htmlspecialchars($park['address'] . ', ' . $park['city'] . ', ' . $park['country']) ?></p>
<div class="map-container">
<iframe
src="https://www.google.com/maps?q=<?= urlencode($park['address'] . ', ' . $park['city'] . ', ' . $park['country']) ?>&output=embed"
width="100%"
height="350"
style="border:0;"
allowfullscreen=""
loading="lazy">
</iframe>
</div>
</div>

<div class="reviews-section mt-5">
<h2 class="section-title">⭐ REVIEWS & RATINGS</h2>

<?php if (isset($_SESSION["user_id"])): ?>

<?php if (isset($_SESSION['review_error'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var modal = new bootstrap.Modal(document.getElementById('reviewErrorModal'));
    modal.show();
});
</script>
<?php unset($_SESSION['review_error']); ?>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="review-form shadow-sm p-4 mb-4 rounded">
<label class="form-label fw-bold">Your Rating:</label>
<div class="star-rating mb-3">
<?php for ($i=5; $i>=1; $i--): ?>
<input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
<label for="star<?= $i ?>">★</label>
<?php endfor; ?>
</div>

<textarea name="comment" class="form-control mb-3" placeholder="Write your review..." rows="3" required></textarea>

<label class="form-label">Attach Photos (optional):</label>
<input type="file" name="photos[]" class="form-control mb-3" accept="image/*" multiple>

<button type="submit" class="btn btn-primary w-100">Submit Review</button>
</form>
<?php else: ?>
<div class="alert alert-info">🔑 <a href="#" onclick="showLoginModal()">Login</a> to write a review.</div>
<?php endif; ?>

<div class="review-list" id="reviewList">
<?php if (!empty($reviews)): ?>
<?php foreach ($reviews as $index => $review): ?>
<div class="review-card p-3 mb-3 shadow-sm rounded review-item <?= $index >= 3 ? 'extra-review d-none' : '' ?>">
<div class="d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center">
    <?php
      $avatar = $review['avatar_path'] ?? '';
      $name   = $review['user_name'] ?? '';
      $initials = strtoupper(substr($name, 0, 1));
    ?>
    
    <?php if (!empty($avatar)): ?>
      <img src="<?= htmlspecialchars($avatar) ?>" 
           alt="Avatar" 
           class="rounded-circle me-2" 
           style="width:32px; height:32px; object-fit:cover;">
    <?php else: ?>
      <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
           style="width:32px; height:32px; font-size:14px; font-weight:bold;">
        <?= $initials ?>
      </div>
    <?php endif; ?>

    <h6 class="mb-0"><?= htmlspecialchars($review['user_name']) ?></h6>
  </div>
  <small class="text-muted"><?= date("M d, Y", strtotime($review['created_at'])) ?></small>
</div>

<div class="stars mb-1">
<?php for ($i=1; $i<=5; $i++): ?>
<span class="<?= $i <= $review['rating'] ? 'filled' : '' ?>">★</span>
<?php endfor; ?>
</div>
<p class="mb-2"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>

<?php if (!empty($review['photos'])): ?>
<div class="review-photos d-flex flex-wrap gap-2 mt-2">
<?php foreach ($review['photos'] as $photo): ?>
<img src="<?= htmlspecialchars($photo['photo_path']) ?>" class="review-img">
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (count($reviews) > 3): ?>
<div class="text-center mt-3">
<button id="toggleReviews" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
Show More Reviews ▼
</button>
</div>
<?php endif; ?>
<?php else: ?>
<p class="text-muted">No reviews yet. Be the first to review!</p>
<?php endif; ?>
</div>


<div class="rating-summary card p-3 mb-4 shadow-sm rounded">
<div class="d-flex align-items-center mb-3">
<h3 class="mb-0 me-3"><?= $avg_rating ?>/5</h3>
<div class="stars fs-4">
<?php for ($i=1; $i<=5; $i++): ?>
<span class="<?= $i <= round($avg_rating) ? 'filled' : '' ?>">★</span>
<?php endfor; ?>
</div>
</div>
<p class="text-muted mb-3"><?= number_format($total_reviews) ?> total reviews</p>

<?php foreach ($breakdown as $stars => $count):
$percent = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
?>
<div class="d-flex align-items-center mb-2">
<span class="me-2" style="width: 40px;"><?= $stars ?>★</span>
<div class="progress flex-grow-1" style="height: 10px;">
<div class="progress-bar bg-warning" role="progressbar"
style="width: <?= $percent ?>%;"
aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
</div>
<span class="ms-2"><?= $count ?></span>
</div>
<?php endforeach; ?>
</div>



</div>

<!-- Modal for Adding to Wishlist -->
<div class="modal fade" id="wishlistAddedModal" tabindex="-1" aria-labelledby="wishlistAddedModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="DITO">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="wishlistAddedModalLabel">Wishlist Notification</h5>
</div>
<div class="modal-body">
The park has been added to your wishlist!
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <a href="wishlist.php" class="btn btn-primary">Go to Wishlist</a>

</div>
</div></div>
</div>
</div>
<!-- Modal for Removing from Wishlist -->
<div class="modal fade" id="wishlistRemovedModal" tabindex="-1" aria-labelledby="wishlistRemovedModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="DITO">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="wishlistRemovedModalLabel">Wishlist Notification</h5>
</div>
<div class="modal-body">
The park has been removed from your wishlist!
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div></div>
</div>
</div>
</div>

<!-- Modal for Adding to Cart -->
<div class="modal fade" id="cartAddedModal" tabindex="-1" aria-labelledby="cartAddedModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="DITO">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="cartAddedModalLabel">Cart Notification</h5>
        </div>
        <div class="modal-body">
          ✅ The item has been added to your cart!
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <a href="cart.php" class="btn btn-primary">Go to Cart</a>
        </div>
      </div>
    </div>
  </div>
</div>

      <!-------MODAL SECTION------>
 <?php include 'modal/modal-handler.php'; ?>


<script>
function toggleWishlist(parkId) {
    console.log('Is logged in:', isLoggedIn);
    console.log('Park ID:', parkId);

    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
        return;
    }

    const wishlistBtn = document.getElementById('wishlist-btn');
    const heartIcon = wishlistBtn.querySelector('i');
    const dot = document.getElementById('wishlist-notification-dot');

    // Determine current wish state
    const isCurrentlyWished = wishlistBtn.classList.contains('wished');

    // Prepare action
    const action = isCurrentlyWished ? 'remove' : 'add';

    // Send AJAX request to add/remove from wishlist
    fetch('wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, parkId: parkId }),
    })
    .then(response => response.json())
    .then(data => {
        console.log("Wishlist response:", data);

        if (data.status === 'added') {
            wishlistBtn.classList.remove('not-wished');
            wishlistBtn.classList.add('wished');
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');

            // ✅ Update badge number
            if (dot) {
                dot.style.display = "inline-flex";
                dot.innerText = data.wishlistCount ?? 0;

                // ✨ Glimmer when added
                dot.classList.add("glimmer");
                setTimeout(() => dot.classList.remove("glimmer"), 9000);
            }

            const wishlistAddedModal = new bootstrap.Modal(document.getElementById('wishlistAddedModal'));
            wishlistAddedModal.show();

        } else if (data.status === 'removed') {
            wishlistBtn.classList.remove('wished');
            wishlistBtn.classList.add('not-wished');
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');

            // ✅ Update badge number
            if (dot) {
                if (data.wishlistCount > 0) {
                    dot.style.display = "inline-flex";
                    dot.innerText = data.wishlistCount;

                    // ✨ Glimmer when removed
                    dot.classList.add("glimmer");
                    setTimeout(() => dot.classList.remove("glimmer"), 9000);

                } else {
                    dot.style.display = "none"; // Hide if 0
                }
            }

            const wishlistRemovedModal = new bootstrap.Modal(document.getElementById('wishlistRemovedModal'));
            wishlistRemovedModal.show();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}



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



<script>
function replaceWithBooking(button, title, price, ticketId) {
    const card = button.closest(".ticket-card");
    // Proceed with booking logic
    const originalHTML = card.innerHTML;
    card.setAttribute("data-original", originalHTML);

    const today = new Date();
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const month = monthNames[today.getMonth()];

    // ✅ Replace with a "fake form" (no action, no redirect)
    card.innerHTML = `
        <form onsubmit="return false;">
            <div class="booking-header">
                <a href="#" class="back-link-fun" onclick="goBack(this)">👈 Go Back</a>
                <h2>${title}</h2>
                <span class="price">₱ ${price} / PERSON</span>
            </div>
            <div class="booking-tags">
                <span>No cancellation</span>
                <span>Valid on the selected date</span>
                <span>Enter with voucher</span>
                <span>Instant confirmation</span>
            </div>
            <div class="section-title">SELECT DATE & QUANTITY</div>
            <div class="booking-grid">
                <div>
                    <label>Select date</label>
                    <div class="custom-calendar">
                        <div class="calendar-header">${month} ${today.getFullYear()}</div>
                        <div class="calendar-days">
                            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                        </div>
                        <div class="calendar-grid">
                            ${generateCalendarDays(price)}
                        </div>
                    </div>
                </div>
                <div class="quantity-box">
                    <label>Select quantity</label>
                    <div class="quantity-row">
                        <span>Person</span>
                        <div>
                            <button type="button" onclick="changeQty(this, -1)">-</button>
                            <input type="text" name="quantity" value="1" readonly>
                            <button type="button" onclick="changeQty(this, 1)">+</button>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="ticket_id" value="${ticketId}">
            <input type="hidden" id="selected_date" name="visit_date">
            <div class="booking-actions">
                <button type="button" onclick="addToCart(this)" class="cart">ADD TO CART</button>
            </div>
        </form>
    `;
}

function addToCart(button) {
    const isLoggedIn = <?= json_encode(isset($_SESSION["user_id"])) ?>;

    // Check if the user is logged in
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
        return;
    }

    const form = button.closest("form");
    const formData = new FormData(form);

    formData.append("action", "cart"); // required for cart.php

    fetch("cart.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json()) // 👉 parse JSON
    .then(data => {
        console.log("Cart response:", data);

        // ✅ Show success modal
        const cartModal = new bootstrap.Modal(document.getElementById('cartAddedModal'));
        cartModal.show();

        // ✅ Update cart badge with count
        const dot = document.getElementById("cart-notification-dot");
        if (dot) {
            dot.style.display = "inline-flex";
            dot.innerText = data.cartCount ?? 0;

            // ✨ Glimmer effect when item is added
            dot.classList.add("glimmer");
            setTimeout(() => dot.classList.remove("glimmer"), 9000);
        }
    })
    .catch(err => console.error("Error:", err));
}




// IF HINDI PA LOG IN
function showLoginModal() {
const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
loginModal.show();
}

function generateCalendarDays(price) {
let days = '';
let today = new Date();
let year = today.getFullYear();
let month = today.getMonth(); // 0 = January
let currentDay = today.getDate();

// Get the number of days in the current month
let daysInMonth = new Date(year, month + 1, 0).getDate();

for (let i = 1; i <= daysInMonth; i++) {
if (i < currentDay) {
// Past dates → disabled
days += `<span class="unavailable">${i}<br><small>₱${price}</small></span>`;
} else {
// Today and future dates → selectable
days += `<span class="available" onclick="selectDate(this)">${i}<br><small>₱${price}</small></span>`;
}
}
return days;
}

function selectDate(dayElement) {
const selectedDay = document.querySelectorAll('.calendar-grid .available');
selectedDay.forEach(day => day.classList.remove('selected'));
dayElement.classList.add('selected');

// Get the day number
const selectedDate = dayElement.innerText.trim();
const month = new Date().getMonth() + 1; // Current month (1-12)
const year = new Date().getFullYear(); // Current year

// Format the date correctly
const fullDate = `${year}-${month < 10 ? '0' + month : month}-${selectedDate < 10 ? '0' + selectedDate : selectedDate}`;
document.getElementById('selected_date').value = fullDate; // Set hidden input
}

function changeQty(button, change) {
const input = button.parentElement.querySelector('input');
let currentValue = parseInt(input.value);
currentValue += change;
if (currentValue < 1) currentValue = 1; // Minimum quantity
if (currentValue > 10) currentValue = 10; // Maximum quantity
input.value = currentValue;
}

function goBack(link) {
const card = link.closest(".ticket-card");
card.innerHTML = card.getAttribute("data-original");
}
</script>


<script src="scripts/togglereview.js"></script>
<script src="scripts/darkmode.js"></script>
<script src="scripts/scrolltop.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>


</div>
<!-- Footer -->
<footer class="footer-section">
<div class="container">
<div class="footer-cta pt-5 pb-5">
<div class="row">
<div class="col-xl-4 col-md-4 mb-30">
<div class="single-cta">
<i class="fas fa-map-marker-alt"></i>
<div class="cta-text">
<h4>Find us</h4>
<span>1010 Avenue, sw 54321, chandigarh</span>
</div>
</div>
</div>
<div class="col-xl-4 col-md-4 mb-30">
<div class="single-cta">
<i class="fas fa-phone"></i>
<div class="cta-text">
<h4>Call us</h4>
<span>9876543210 0</span>
</div>
</div>
</div>
<div class="col-xl-4 col-md-4 mb-30">
<div class="single-cta">
<i class="far fa-envelope-open"></i>
<div class="cta-text">
<h4>Mail us</h4>
<span>mail@info.com</span>
</div>
</div>
</div>
</div>
</div>
<div class="footer-content pt-5 pb-5">
<div class="row">
<div class="col-xl-4 col-lg-4 mb-50">
<div class="footer-widget">
<div class="footer-logo">
<a href="index.html"><img src="images/STAGE.png" class="img-fluid" alt="logo"></a>
</div>
<div class="footer-text">
<p>One-Stop movie ticketing site, bringing the magic of Aurora Cinemas straight to you!</p>
</div>
<div class="footer-social-icon">
<span>Follow us</span>
<a href="#"><i class="fab fa-facebook-f facebook-bg"></i></a>
<a href="#"><i class="fab fa-twitter twitter-bg"></i></a>
<a href="#"><i class="fab fa-google-plus-g google-bg"></i></a>
</div>
</div>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-30">
<div class="footer-widget">
<div class="footer-widget-heading">
<h3>Useful Links</h3>
</div>
<ul class="two-column-list">
<li><a href="index.php">Home</a></li>
<li><a href="themeparks.php">Theme Parks</a></li>
<li><a href="aquaparks.php">Aqua Parks</a></li>
<li><a href="natureparks.php">Nature Parks</a></li>
<li><a href="museums.php">Museums</a></li>
<li><a href="account.php">Account</a></li>
<li><a href="aboutus.php">About Us</a></li>
</ul>
</div>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-50">
<div class="footer-widget">
<div class="footer-widget-heading">
<h3>Subscribe</h3>
</div>
<div class="footer-text mb-25">
<p>Don't miss to subscribe to our new feeds, kindly fill the form below.</p>
</div>
<div class="subscribe-form">
<form action="#">
<input type="text" placeholder="Email Address">
<button><i class="fab fa-telegram-plane"></i></button>
</form>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="copyright-area">
<div class="container">
<div class="row">
<div class="col-xl-6 col-lg-6 text-center">
<div class="copyright-text">
<p>Copyright &copy; 2025, All Right Reserved</p>
</div>
</div>
</div>
</div>
</div>
</footer>

 <?php include 'navbar/loader.html'; ?>

<script src="scripts/loader.js"></script>
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
<!-----END NG LOADING---->





<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-body p-0">
        <div class="sign-up-form w-100">
          <div class="sign-up-card d-flex flex-wrap">
            <!-- Left: Form -->
            <div class="form-container flex-fill p-4" style="min-width: 300px;">
              <h1 class="welcome-text">WELCOME BACK!</h1>
              <form method="post" action="login.php">
                <div class="input-div">
                  <label for="email">Email</label>
                  <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" required />
                </div>
                <div class="input-div">
                  <label for="password">Password</label>
                  <div class="password-field" style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="Password" required />
                    <i class="fas fa-eye password-toggle" id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;"></i>
                  </div>
                </div>
                <?php if (!empty($is_invalid)): ?>
                  <em style="color: red;">Invalid Login</em>
                <?php endif; ?>
                <button class="btn btn-primary w-100 mb-2" type="submit">Log In</button>
                <a href="<?= $client->createAuthUrl() ?>" class="btn btn-danger w-100 mb-3">
                  <i class="fab fa-google me-2"></i> Login with Google
                </a>
                <p class="text-center">Don't have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal">Sign Up</a></p>
              </form>
            </div>
            <!-- Right: Hero Image -->
            <div class="home__img flex-fill d-none d-md-block">
              <img src="/images/kid.png" alt="Movie Ticket Banner" class="home__hero-img img-fluid" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-body p-0">
        <div class="sign-up-form w-100">
          <div class="sign-up-card d-flex flex-wrap">
            <!-- Left: Form -->
            <div class="form-container flex-fill p-4" style="min-width: 300px;">
              <h1 class="welcome-text">CREATE ACCOUNT</h1>
              <form method="post" action="send_otp.php">
                <div class="input-div">
                  <label for="name">Full Name</label>
                  <input type="text" name="name" placeholder="Your Full Name" required />
                </div>
                <div class="input-div">
                  <label for="signupEmail">Email</label>
                  <input type="email" name="email" placeholder="Email" required />
                </div>
                <div class="input-div">
                  <label for="signupPassword">Password</label>
                  <div class="password-field" style="position: relative;">
                    <input type="password" id="signupPassword" name="password" placeholder="Password" required />
                    <i class="fas fa-eye password-toggle" id="toggleSignupPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;"></i>
                  </div>
                </div>
                <button class="btn btn-primary w-100 mb-2" type="submit">Sign Up</button>
                <a href="<?= $client->createAuthUrl() ?>" class="btn btn-danger w-100 mb-3">
                  <i class="fab fa-google me-2"></i> Sign Up with Google
                </a>
                <p class="text-center">Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</a></p>
              </form>
            </div>
            <!-- Right: Hero Image -->
            <div class="home__img flex-fill d-none d-md-block">
              <img src="/images/kid.png" alt="Movie Ticket Banner" class="home__hero-img img-fluid" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Email Exists Error Modal -->
<div class="modal fade" id="emailExistsModal" tabindex="-1" aria-labelledby="emailExistsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content otp-modal-content">
      <div class="modal-header border-0 otp-modal-header">
        <h5 class="modal-title otp-modal-title" id="emailExistsModalLabel">Account Already Exists</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4 otp-modal-body">
        <div class="mb-3">
          <i class="fas fa-user-check fa-3x otp-icon mb-3"></i>
          <h4 class="otp-modal-title">Welcome Back!</h4>
          <p class="otp-text-muted">The email address:</p>
          <p class="otp-email-display" id="existingEmailDisplay"></p>
          <p class="otp-text-muted">is already registered with AuroraBox.</p>
        </div>
        
        <button id="switchToLoginBtn" class="btn w-100 mb-3 otp-verify-btn" type="button">
          <i class="fas fa-sign-in-alt me-2"></i>Log In Instead
        </button>
        
        <div class="text-center">
          <button type="button" class="btn btn-link p-0 otp-resend-btn" data-bs-dismiss="modal">
            <i class="fas fa-arrow-left me-1"></i>Try Different Email
          </button>
        </div>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content otp-modal-content">
      <div class="modal-header border-0 otp-modal-header">
        <h5 class="modal-title otp-modal-title" id="otpModalLabel">Verify Your Email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4 otp-modal-body">
        <div class="mb-3">
          <i class="fas fa-envelope-circle-check fa-3x otp-icon mb-3"></i>
          <h4 class="otp-modal-title">Check Your Email</h4>
          <p class="otp-text-muted">We've sent a 6-digit verification code to:</p>
          <p class="otp-email-display" id="otpEmailDisplay"></p>
        </div>
        
        <div id="otpMessage"></div>
        
        <div class="mb-3">
          <input type="text" 
                 id="otpInput" 
                 class="form-control form-control-lg otp-input" 
                 placeholder="Enter 6-digit OTP" 
                 maxlength="6" 
                 required>
        </div>
        
        <button id="verifyOtpBtn" class="btn w-100 mb-3 otp-verify-btn" type="button">
          <i class="fas fa-check-circle me-2"></i>Verify OTP
        </button>
        
        <div class="text-center">
          <p class="otp-text-muted mb-2">Didn't receive the code?</p>
          <button id="resendOtpBtn" class="btn btn-link p-0 otp-resend-btn">
            <i class="fas fa-redo me-1"></i>Resend OTP
          </button>
        </div>
        
        <div class="mt-3">
          <small class="otp-text-muted">
            <i class="fas fa-clock me-1"></i>Code expires in 5 minutes
          </small>
        </div>
      </div>
    </div>
  </div>
</div>
 
<!-- Review Error Modal -->
<div class="modal fade" id="reviewErrorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background-color: #ffffff; border-radius: 8px;">
      <div class="modal-header">
        <h5 class="modal-title">⚠️ Cannot Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>You can only review this park if you have booked it. ✅ Please make a booking first before leaving a review.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<script>
    
// Check if need to show email exists error modal
<?php if (isset($_GET['show_error']) && isset($_SESSION['signup_error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('existingEmailDisplay').innerText = '<?php echo $_SESSION["signup_email"]; ?>';
    
    const errorModal = new bootstrap.Modal(document.getElementById('emailExistsModal'), {
        backdrop: 'static',
        keyboard: false
    });
    errorModal.show();
    
    // Remove session flags
    <?php 
    unset($_SESSION['signup_error']); 
    unset($_SESSION['signup_email']); 
    ?>
});
<?php endif; ?>

// Switch to login modal button
document.getElementById('switchToLoginBtn').addEventListener('click', function() {
    // Close error modal
    bootstrap.Modal.getInstance(document.getElementById('emailExistsModal')).hide();
    
    // Show login modal after a brief delay
    setTimeout(() => {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    }, 300);
});
    
// Check if need to show OTP modal on page load
<?php if (isset($_GET['show_otp']) && isset($_SESSION['otp_sent'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Set email in modal
    document.getElementById('otpEmailDisplay').innerText = '<?php echo $_SESSION["signup_email"]; ?>';
    
    // Show OTP modal
    const otpModal = new bootstrap.Modal(document.getElementById('otpModal'), {
        backdrop: 'static', // Prevent closing by clicking outside
        keyboard: false     // Prevent closing with ESC key
    });
    otpModal.show();
    
    // Focus on OTP input when modal is shown
    document.getElementById('otpModal').addEventListener('shown.bs.modal', function() {
        document.getElementById('otpInput').focus();
    });
    
    // Remove the session flag
    <?php unset($_SESSION['otp_sent']); ?>
});
<?php endif; ?>

// OTP Input - Only allow numbers
document.getElementById('otpInput').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // Auto-submit when 6 digits are entered
    if (this.value.length === 6) {
        setTimeout(() => {
            document.getElementById('verifyOtpBtn').click();
        }, 500);
    }
});

// Enter key to verify
document.getElementById('otpInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('verifyOtpBtn').click();
    }
});

// Verify OTP Button
document.getElementById('verifyOtpBtn').addEventListener('click', function() {
    const otp = document.getElementById('otpInput').value.trim();
    const email = document.getElementById('otpEmailDisplay').innerText;
    const messageDiv = document.getElementById('otpMessage');
    
    if (!otp || otp.length !== 6) {
        messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Please enter a valid 6-digit OTP</div>';
        return;
    }
    
    // Disable button and show loading
    const originalText = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
    
    // Clear previous messages
    messageDiv.innerHTML = '';
    
    fetch('verify_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'otp=' + encodeURIComponent(otp) + '&email=' + encodeURIComponent(email)
    })
    .then(response => response.json())
    .then(data => {
        const alertClass = data.success ? 'success' : 'danger';
        const icon = data.success ? 'check-circle' : 'exclamation-circle';
        
        messageDiv.innerHTML = `
            <div class="alert alert-${alertClass}">
                <i class="fas fa-${icon} me-2"></i>${data.message}
            </div>`;
        
        if (data.success) {
            setTimeout(() => {
                // Close modal and reload page to show logged-in state
                bootstrap.Modal.getInstance(document.getElementById('otpModal')).hide();
                location.reload();
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Connection error. Please try again.
            </div>`;
    })
    .finally(() => {
        // Re-enable button
        this.disabled = false;
        this.innerHTML = originalText;
    });
});

// Resend OTP Button
document.getElementById('resendOtpBtn').addEventListener('click', function() {
    const email = document.getElementById('otpEmailDisplay').innerText;
    const messageDiv = document.getElementById('otpMessage');
    
    const originalText = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
    
    fetch('send_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(email) + '&resend=1'
    })
    .then(response => response.json())
    .then(data => {
        const alertClass = data.success ? 'success' : 'danger';
        const icon = data.success ? 'check-circle' : 'exclamation-circle';
        
        messageDiv.innerHTML = `
            <div class="alert alert-${alertClass}">
                <i class="fas fa-${icon} me-2"></i>${data.message}
            </div>`;
        
        if (data.success) {
            // Clear OTP input for new code
            document.getElementById('otpInput').value = '';
            document.getElementById('otpInput').focus();
        }
    })
    .catch(error => {
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Failed to resend OTP. Please try again.
            </div>`;
    })
    .finally(() => {
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = originalText;
        }, 3000);
    });
});
</script>



</body>
</html>

<?php $conn->close(); ?>