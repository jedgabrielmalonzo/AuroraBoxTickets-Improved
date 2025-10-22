<?php 
require_once __DIR__ . '/config.php';
session_start(); 
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
require 'gClientSetup.php'; 

// Always Philippine time
date_default_timezone_set('Asia/Manila');

// Database connection
$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Force MySQL timezone to Philippine Time
@$conn->query("SET time_zone = '+08:00'");



// ---------------- GOOGLE LOGIN ----------------
if (isset($_GET['google_login'])) {
    $google_user = handleGoogleLogin(); 

    if ($google_user) {
        $email = $google_user['email'];
        $phil_time = date("Y-m-d H:i:s");

        // Check if the user already exists
        $sql = "SELECT * FROM user WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
        } else {
            // Create new user
            $nameParts = explode(' ', $google_user['name'], 2);
            $firstname = $nameParts[0];
            $lastname  = isset($nameParts[1]) ? $nameParts[1] : '';

            $stmt = $conn->prepare("INSERT INTO user (email, firstname, lastname, is_active, created) VALUES (?, ?, ?, 1, ?, )");
            $stmt->bind_param("sssss", $email, $firstname, $lastname, $phil_time);
            $stmt->execute();
            $user_id = $stmt->insert_id;
            $_SESSION["user_id"] = $user_id;
            $_SESSION['firstname'] = $user['firstname'];
            $user = ["id" => $user_id];
        }

        // Update user's last_login
        $update_sql = "UPDATE user SET is_active = 1,  WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('si', $user['id']);
        if ($update_stmt->execute()) {
            header("Location: index.php");
            exit;
        } else {
            echo "Error updating user status: " . $update_stmt->error;
        }
    }
}


// ---------------- EMAIL LOGIN ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION["user_id"] = $user['id'];
            $phil_time = date("Y-m-d H:i:s");

            $update_sql = "UPDATE user SET is_active = 1,  WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $phil_time, $_SESSION["user_id"]);
            $update_stmt->execute();

            header("Location: index.php");
            exit;
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "No user found with that email.";
    }
}


// ---------------- LOGOUT ----------------
if (isset($_GET['logout'])) {
   $phil_time = date("Y-m-d H:i:s");
   $update_login_sql = "UPDATE user SET last_login = ?, is_active = 0 WHERE id = ?";
   $stmt = $conn->prepare($update_login_sql);
   $stmt->bind_param('si', $phil_time, $_SESSION["user_id"]);

    if ($stmt->execute()) {
        session_destroy();
        header("Location: index.php");
        exit();
    } else {
        echo "Error updating last login: " . $stmt->error;
    }
}


// ---------------- SIGNUP ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phil_time = date("Y-m-d H:i:s");

    $insert_sql = "INSERT INTO user (email, password_hash, created, last_login, is_active) VALUES (?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssss", $email, $password, $phil_time, $phil_time);

    if ($stmt->execute()) {
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}


// ---------------- FUNCTIONS ----------------

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
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

// Fetch recommended parks
function fetchRecommendedParksByReviews($conn, $limit = 10) {
    $sql = "SELECT p.*, c.category_name,
                   MIN(pt.price) as min_price,
                   AVG(r.rating) as avg_rating,
                   COUNT(r.id) as total_reviews
            FROM parks p
            LEFT JOIN category c ON p.category = c.category_id
            LEFT JOIN park_tickets pt ON p.id = pt.park_id
            LEFT JOIN reviews r ON p.id = r.park_id
            WHERE r.id IS NOT NULL
            GROUP BY p.id
            HAVING total_reviews > 0
            ORDER BY avg_rating DESC, total_reviews DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch newly added parks (last X days, fixed version)
function fetchNewlyAddedParks($conn, $limit = 10, $days = 14) {
    // Compute cutoff datetime in PHP
    $cutoff = date("Y-m-d H:i:s", strtotime("-{$days} days"));

    $sql = "SELECT p.*, c.category_name,
                   MIN(pt.price) as min_price
            FROM parks p
            LEFT JOIN category c ON p.category = c.category_id
            LEFT JOIN park_tickets pt ON p.id = pt.park_id
            WHERE p.created_at >= ?
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $cutoff, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}


// ---------------- FETCH DATA ----------------
$themeParks      = fetchParksByCategory($conn, 'Theme Parks', 5);
$aquaParks       = fetchParksByCategory($conn, 'Aqua Parks', 5);
$natureParks     = fetchParksByCategory($conn, 'Nature Parks', 5);
$museums         = fetchParksByCategory($conn, 'Museums', 5);
$recommendedParks = fetchRecommendedParksByReviews($conn, 10);
$newlyAddedParks  = fetchNewlyAddedParks($conn, 10, 14);

// Banners
$bannerQuery = "SELECT * FROM banners WHERE status = 'active' ORDER BY display_order ASC, id DESC";
$bannerResult = $conn->query($bannerQuery);
$activeBanners = $bannerResult ? $bannerResult->fetch_all(MYSQLI_ASSOC) : [];
$fallbackImages = ['images/1.png','images/2.png','images/3.png','images/4.png','images/5.png'];
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuroraBox</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/layout_park.css">
    <link rel="stylesheet" href="css/chatbot.css">
    <link rel="stylesheet" href="css/loading.css">
    <link rel="stylesheet" href="css/otp.css">
    <link rel="stylesheet" href="css/promo.css">
    <link rel="stylesheet" href="css/why.css">


</head>
<style>
.card {
    position: relative; /* kailangan para gumana yung absolute positioning */
}

.new-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #fdb11d;
    color: white;
    font-size: 12px;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    z-index: 5;
}

.notification-dot {
    position: absolute;
    top: -6px;
    right: -6px;
    background: red;
    color: white;
    font-size: 11px;
    font-weight: bold;
    border-radius: 50%; /* Gawing bilog */
    width: 18px;  /* fixed width */
    height: 18px; /* fixed height */
    display: none; /* Default hidden */
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 18px; /* para gitna yung number */
    box-sizing: border-box;
}


</style>
<body>
<header>
 
    <!-------NAVBAR SECTION------>
    <?php include 'navbar/navbar.php'; ?>
</header>
   
<!-------Dynamic Carousel SECTION------>
<div id="carouselExampleFade" class="carousel carousel-fade slide" data-bs-ride="carousel" data-bs-interval="5000">
    <div class="carousel-inner">
        <?php if (!empty($activeBanners)): ?>
            <!-- Dynamic banners from admin panel -->
            <?php foreach ($activeBanners as $index => $banner): ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <img src="admin/banners/<?= htmlspecialchars($banner['filename']) ?>" 
                         class="d-block w-100" 
                         alt="<?= htmlspecialchars($banner['title'] ?: $banner['filename']) ?>"
                         loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
                    <div class="overlay"></div>
                    
                    <!-- Optional: Add banner title/description overlay -->
                    <?php if (!empty($banner['title']) || !empty($banner['description'])): ?>
                        <div class="carousel-caption d-none d-md-block">
                            <?php if (!empty($banner['title'])): ?>
                                <h5><?= htmlspecialchars($banner['title']) ?></h5>
                            <?php endif; ?>
                            <?php if (!empty($banner['description'])): ?>
                                <p><?= htmlspecialchars($banner['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback to default images if no active banners -->
            <?php foreach ($fallbackImages as $index => $image): ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <img src="<?= $image ?>" 
                         class="d-block w-100" 
                         alt="Slide <?= $index + 1 ?>"
                         loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
                    <div class="overlay"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Carousel Indicators -->
    <?php 
    $totalSlides = !empty($activeBanners) ? count($activeBanners) : count($fallbackImages);
    if ($totalSlides > 1): 
    ?>
        <div class="carousel-indicators">
            <?php for ($i = 0; $i < $totalSlides; $i++): ?>
                <button type="button" 
                        data-bs-target="#carouselExampleFade" 
                        data-bs-slide-to="<?= $i ?>" 
                        <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                        aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    
    <!-- Navigation Controls -->
    <?php if ($totalSlides > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    <?php endif; ?>
</div>



<!-- CATEGORIES -->

<?php include 'popular.php'; ?>



<!----THEME PARKS SECTION----->
<div class="PARKSHOW">
    <h2>RECOMMENDED PARKS</h2>
    <div class="carousel-container">
        <button class="nav-btn prev" onclick="scrollCarousel('recommended-carousel', -270)"><span>&#10094;</span></button>
        <div class="cards" id="recommended-carousel">
            <?php if (!empty($recommendedParks)): ?>
                <?php foreach ($recommendedParks as $park): 
                    $images = !empty($park['pictures']) ? explode(',', $park['pictures']) : [];
                    $firstImage = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';

                    $endDate = isset($park['end_date']) ? $park['end_date'] : null;
                ?>
                    <a href="park_info.php?id=<?= $park['id'] ?>" class="card">
                        <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($park['name']) ?>">
                        
                        <?php if ($endDate): ?>
                            <div class="timer-badge" data-end="<?= $endDate ?>"></div>
                        <?php endif; ?>

                        <div class="card-content">
                            <div class="category"><?= htmlspecialchars($park['category_name'] ?? 'Park') ?> • <?= htmlspecialchars($park['city'] ?? 'Philippines') ?></div>
                            <div class="title"><?= htmlspecialchars($park['name']) ?></div>
                            <div class="tag">Highest Rating</div>
                            <div class="price">₱<?= number_format($park['min_price'] ?? 0) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No recommended parks available at the moment.</div>
            <?php endif; ?>
        </div>
        <button class="nav-btn next" onclick="scrollCarousel('recommended-carousel', 270)"><span>&#10095;</span></button>
    </div>

<!-- CSS -->
<style>
.card { position: relative; }
.timer-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ff4d4f;
    color: #fff;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: bold;
    border-radius: 5px;
    z-index: 10;
}
</style>

<!-- JS -->
<script>
function updateTimers() {
    const badges = document.querySelectorAll('.timer-badge');
    const now = new Date();

    badges.forEach(badge => {
        const endDate = new Date(badge.getAttribute('data-end'));
        const diff = endDate - now;

        if (diff <= 0) {
            badge.textContent = "EXPIRED";
        } else {
            const daysLeft = Math.floor(diff / (1000 * 60 * 60 * 24));

            if (daysLeft < 7) { // Start countdown only if ≤ 7 days
                const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
                const minutes = Math.floor((diff / (1000 * 60)) % 60);
                badge.textContent = `${daysLeft}d ${hours}h ${minutes}m LEFT`;
            } else {
                badge.textContent = ""; // Hide badge if more than 7 days left
            }
        }
    });
}

// Initial update and refresh every minute
updateTimers();
setInterval(updateTimers, 60000);
</script>


    
    <h2>NEWLY ADDED</h2>
    <div class="carousel-container">
        <button class="nav-btn prev" onclick="scrollCarousel('new-carousel', -270)">
            <span>&#10094;</span>
        </button>
        <div class="cards" id="new-carousel">
            <?php if (!empty($newlyAddedParks)): ?>
                <?php foreach ($newlyAddedParks as $park): 
                    $images = !empty($park['pictures']) ? explode(',', $park['pictures']) : [];
                    $firstImage = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';
                ?>
                    <a href="park_info.php?id=<?= $park['id'] ?>" class="card">
                        <div class="new-badge">NEW</div>
                        <img src="<?= htmlspecialchars($firstImage) ?>" 
                             alt="<?= htmlspecialchars($park['name']) ?>">
                        <div class="card-content">
                            <div class="category"><?= htmlspecialchars($park['category_name'] ?? 'Park') ?> • <?= htmlspecialchars($park['city'] ?? 'Philippines') ?></div>
                            <div class="title"><?= htmlspecialchars($park['name']) ?></div>
                            <div class="tag">Free cancellation</div>
                            <div class="price">₱<?= number_format($park['min_price'] ?? 0) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">🚧 New parks coming soon!</div>
            <?php endif; ?>
        </div>
        <button class="nav-btn next" onclick="scrollCarousel('new-carousel', 270)">
            <span>&#10095;</span>
        </button>
    </div>
</div>


    <!-------CONTENT SECTION------>
<?php include 'navbar/content.html';?>

    <!-------PROMO SECTION------>
     
<?php include 'php/promo.php'; ?>

     <!-------SLIDER SECTION------>
 <?php include 'navbar/slider.html'; ?>
 
    <!-------CHATBOT SECTION------>
 <?php include 'chatbot/chatbot-widget.html'; ?>
 
     <!-------FOOTER SECTION------>
 <?php include 'navbar/footer.html'; ?>
 
      <!-------LOADER SECTION------>
 <?php include 'navbar/loader.html'; ?>

      <!-------MODAL SECTION------>
 <?php include 'modal/modal-handler.php'; ?>





<button id="scrollToTopBtn" title="Go to top">&#8679;</button>

<script src="https://cdn.jsdelivr.net/npm/fuse.js@6.6.2"></script>
<script src="scripts/search.js"></script>
<script src="scripts/darkmode.js"></script>
<script src="scripts/hidepass.js"></script>
<script src="scripts/scrolltop.js"></script>
<script src="scripts/loader.js"></script>
<script src="scripts/carousel.js"></script>
<script src="scripts/chatbot.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
 function handleAccClick(event) {
    event.preventDefault(); // para hindi agad mag-redirect

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