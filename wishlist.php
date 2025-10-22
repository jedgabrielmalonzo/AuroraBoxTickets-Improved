<?php
require_once __DIR__ . '/config.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'gClientSetup.php';

// Database connection
$mysqli = require __DIR__ . '/database.php';
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$conn = $mysqli; // 🔑 para sa mga lumang code na naka-$conn pa rin


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


// If password-login user, load their DB info
if (isset($_SESSION["user_id"])) {
    $sql = "SELECT * FROM user WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Handle AJAX requests for adding/removing from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'], $data['parkId'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }

    $action = $data['action'];
    $parkId = $data['parkId'];
    $userId = $_SESSION['user_id'];

    if ($action === 'add') {
        // Check if already in wishlist
        $check_sql = "SELECT * FROM wishlist WHERE user_id = ? AND park_id = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("ii", $userId, $parkId);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Already in wishlist']);
            exit;
        }

        // Fetch park details to get pictures and description
$park_sql = "SELECT pictures, description FROM parks WHERE id = ?";
$park_stmt = $mysqli->prepare($park_sql);
$park_stmt->bind_param("i", $parkId);
$park_stmt->execute();
$park_result = $park_stmt->get_result();
$park = $park_result->fetch_assoc();

// Handle images and description
$image = !empty($park['pictures']) ? explode(',', $park['pictures'])[0] : null; // Get the first image
$description = $park['description'];

// Insert into wishlist
$sql = "INSERT INTO wishlist (user_id, park_id, image, description) VALUES (?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("iiss", $userId, $parkId, $image, $description);

       if ($stmt->execute()) {
            // ✅ Kunin bagong count
            $count_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
            $count_stmt = $mysqli->prepare($count_sql);
            $count_stmt->bind_param("i", $userId);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $wishlistCount = $count_result->fetch_assoc()['count'];

            echo json_encode(['status' => 'added', 'wishlistCount' => $wishlistCount]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not add to wishlist']);
        }
    } elseif ($action === 'remove') {
        $sql = "DELETE FROM wishlist WHERE user_id = ? AND park_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $userId, $parkId);

        if ($stmt->execute()) {
            // ✅ Kunin bagong count
            $count_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
            $count_stmt = $mysqli->prepare($count_sql);
            $count_stmt->bind_param("i", $userId);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $wishlistCount = $count_result->fetch_assoc()['count'];

            echo json_encode(['status' => 'removed', 'wishlistCount' => $wishlistCount]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not remove from wishlist']);
        }
    }
    exit;
}

$wishlistItems = [];

if (isset($_SESSION["user_id"])) {
    $sql = "SELECT parks.*, wishlist.image, wishlist.description, parks.category, 
       IFNULL(AVG(reviews.rating), 0) AS avg_rating
        FROM wishlist
        INNER JOIN parks ON wishlist.park_id = parks.id
        LEFT JOIN reviews ON parks.id = reviews.park_id
        WHERE wishlist.user_id = ?
        GROUP BY parks.id";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        // Fetch all rows as associative array
        $wishlistItems = $result->fetch_all(MYSQLI_ASSOC);
    }
}




// Database connection parameters removed (using centralized database.php)
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AuroraBox - Wishlist</title>
  <link rel="icon" type="image/x-icon" href="images/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
  <link rel="stylesheet" href="css/login.css">
  <link rel="stylesheet" href="css/homepage.css">
  <link rel="stylesheet" href="css/WISH.css">
      <link rel="stylesheet" href="css/loading.css">

</head>

<body>
<header>

 <!-------NAVBAR SECTION------>
   <?php include 'navbar/navbar.php'; ?>

</header>


<div class="BOX">
  <!-- Wishlist Header -->
  <div class="wish-top">
  <h2 class="wish-title">
    <span class="wish-icon">🔖</span> Wishlist
  </h2>
</div>

  
  <!-- Sorting & Filtering Controls (INSERTED HERE) -->
  <div class="wishlist-controls">
    <select id="sortSelect" class="wishlist-select" onchange="updateWishlist()">
        <option value="name">Sort: A → Z</option>
        <option value="most-rated">Sort: Most Rated</option>
    </select>

    <select id="filterSelect" class="wishlist-select" onchange="updateWishlist()">
        <option value="all">All Categories</option>
        <option value="1">Theme Parks</option>
        <option value="2">Aqua Parks</option>
        <option value="3">Nature Parks</option>
        <option value="4">Museums</option>
    </select>
</div>


    <!-- WISHLIST GRIID -->
  <div id="wishlist-items" class="wishlist-grid">
    <?php if (!empty($wishlistItems)): ?>
        <?php foreach ($wishlistItems as $row): ?>
            <div class="wishlist-card" data-id="<?= $row['id'] ?>" data-category="<?= $row['category'] ?>" data-rating="<?= $row['avg_rating'] ?>">
                
                <!-- Image -->
                <?php if (!empty($row['image'])): ?>
                    <img src="<?= htmlspecialchars($row['image']) ?>" 
                         alt="<?= htmlspecialchars($row['name']) ?>" 
                         class="wishlist-image">
                <?php endif; ?>

                <!-- Info Section -->
                <div class="wishlist-info">
                    <h2 class="wishlist-name"><?= htmlspecialchars($row['name']) ?></h2>
                    <p class="wishlist-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?= $i <= round($row['avg_rating']) ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                        (<?= round($row['avg_rating'],1) ?>)
                    </p>

                    <p class="wishlist-description" data-full="<?= htmlspecialchars($row['description']) ?>">
                        <?= htmlspecialchars(strlen($row['description']) > 120 ? substr($row['description'], 0, 120) . "..." 
                            : $row['description']) ?>
                    </p>
                    
                    <?php if (strlen($row['description']) > 120): ?>
                        <button class="see-more-btn">More</button>
                    <?php endif; ?>
                </div>

                <!-- Actions (Bottom Row) -->
                <div class="wishlist-actions">
                    <button class="see-details-btn" data-id="<?= $row['id'] ?>">See Details</button>
                    <button class="trash-btn" onclick="removeFromWishlist(<?= $row['id'] ?>)">
                        <span class="material-icons-outlined">delete</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="empty-text">Your wishlist is empty.</p>
    <?php endif; ?>
</div>

</div>



   <!-------FOOTER SECTION------>
 <?php include 'navbar/footer.html'; ?>
 
       <!-------LOADER SECTION------>
 <?php include 'navbar/loader.html'; ?>  


 
<button id="scrollToTopBtn" title="Go to top">&#8679;</button>

<script src="scripts/seemore.js"></script>
<script src="scripts/seedetails.js"></script>
<script src="scripts/removewishlist.js"></script>
<script src="scripts/filter.js"></script>
<script src="wishlist.js"></script> <!-- wala pa wishlist.js -->
<script src="scripts/darkmode.js"></script>
<script src="scripts/hidepass.js"></script>
<script src="scripts/scrolltop.js"></script>
<script src="scripts/loader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
function updateWishlist() {
    const sortValue = document.getElementById("sortSelect").value;
    const filterValue = document.getElementById("filterSelect").value;

    const wishlistGrid = document.getElementById("wishlist-items");
    
    // Fetch wishlist items (assuming you have an AJAX endpoint to get this data)
    fetch('get_wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sort: sortValue,
            filter: filterValue
        })
    })
    .then(response => response.json())
    .then(data => {
        wishlistGrid.innerHTML = ''; // Clear current items
        if (data.length > 0) {
            data.forEach(item => {
                const card = document.createElement('div');
                card.classList.add('wishlist-card');
                card.setAttribute('data-id', item.id);
                card.setAttribute('data-category', item.category);
                card.setAttribute('data-rating', item.avg_rating);

                // Image
                if (item.image) {
                    card.innerHTML += `<img src="${item.image}" alt="${item.name}" class="wishlist-image">`;
                }

                // Info Section
                card.innerHTML += `
                    <div class="wishlist-info">
                        <h2 class="wishlist-name">${item.name}</h2>
                        <p class="wishlist-rating">${'★'.repeat(Math.round(item.avg_rating))} (${item.avg_rating.toFixed(1)})</p>
                        <p class="wishlist-description">${item.description}</p>
                        <button class="see-details-btn" data-id="${item.id}">See Details</button>
                        <button class="trash-btn" onclick="removeFromWishlist(${item.id})">
                            <span class="material-icons-outlined">delete</span>
                        </button>
                    </div>
                `;

                wishlistGrid.appendChild(card);
            });
        } else {
            wishlistGrid.innerHTML = '<p class="empty-text">Your wishlist is empty.</p>';
        }
    })
    .catch(error => console.error('Error fetching wishlist:', error));
}
</script>
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
