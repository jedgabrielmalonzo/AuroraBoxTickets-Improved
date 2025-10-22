<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'gClientSetup.php';

// If password-login user, load their DB info
if (isset($_SESSION["user_id"])) {
    $mysqli = require __DIR__ . "/database.php";
    $sql = "SELECT * FROM user WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

$mysqli = require __DIR__ . '/database.php';
$conn = $mysqli;
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Function to fetch aqua park subcategories
function fetchAquaSubcategories($conn) {
    // First get the Aqua Parks category ID
    $cat_sql = "SELECT category_id FROM category WHERE category_name = 'Aqua Parks'";
    $cat_result = $conn->query($cat_sql);
    
    if ($cat_result->num_rows > 0) {
        $category = $cat_result->fetch_assoc();
        $aqua_category_id = $category['category_id'];
        
        // Now get all subcategories for Aqua Parks
        $sub_sql = "SELECT subcategory_id, subcategory_name FROM subcategory WHERE category_id = ? ORDER BY subcategory_name";
        $sub_stmt = $conn->prepare($sub_sql);
        $sub_stmt->bind_param("i", $aqua_category_id);
        $sub_stmt->execute();
        $sub_result = $sub_stmt->get_result();
        return $sub_result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

// Function to fetch parks by subcategory
function fetchParksBySubcategory($conn, $subcategory_id, $limit = 10) {
    $sql = "SELECT p.*, c.category_name, s.subcategory_name,
            MIN(pt.price) as min_price
            FROM parks p 
            LEFT JOIN category c ON p.category = c.category_id
            LEFT JOIN subcategory s ON p.subcategory = s.subcategory_id
            LEFT JOIN park_tickets pt ON p.id = pt.park_id
            WHERE p.subcategory = ?
            GROUP BY p.id
            ORDER BY p.id DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $subcategory_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all aqua park subcategories
$aquaSubcategories = fetchAquaSubcategories($conn);

// Fetch parks for each subcategory
$subcategoryParks = [];
foreach ($aquaSubcategories as $subcategory) {
    $subcategoryParks[$subcategory['subcategory_id']] = [
        'name' => $subcategory['subcategory_name'],
        'parks' => fetchParksBySubcategory($conn, $subcategory['subcategory_id'], 10)
    ];
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuroraBox - Aqua Parks</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/layout_park.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/chatbot.css">
    <link rel="stylesheet" href="css/loading.css">
    <link rel="stylesheet" href="css/otp.css">
    <link rel="stylesheet" href="css/searchbox.css">
<style>
    .section-note {
    font-size: 14px;
    color: #666;
    margin: 5px 0 20px 0;
    font-style: italic;
}

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

</style>
</head>




<body>
<header>

    </div>

   <!-------NAVBAR SECTION------>
   <?php include 'navbar/navbar.php'; ?>
   
</header>


<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1>Aqua Parks</h1>
        <p>Discover serene aqua parks with refreshing waters and unforgettable moments</p>
    </div>
</div>

<!----DYNAMIC AQUA PARKS SECTIONS---->
<?php
// --- SAFEGUARD: Ensure arrays exist ---
$subcategoryParks = $subcategoryParks ?? [];

// --- BUILD $allParks if not provided ---
if (!isset($allParks) || !is_array($allParks)) {
    $allParks = [];
    if (!empty($subcategoryParks) && is_array($subcategoryParks)) {
        foreach ($subcategoryParks as $subcat) {
            if (!empty($subcat['parks']) && is_array($subcat['parks'])) {
                foreach ($subcat['parks'] as $p) {
                    if (is_array($p)) {
                        $allParks[] = $p;
                    }
                }
            }
        }
    }
}

// --- FIND NEWLY ADDED (within last 14 days) ---
$newParks   = [];
$today      = new DateTime();

foreach ($allParks as $park) {
    if (!is_array($park)) continue;

    if (!empty($park['created_at'])) {
        try {
            $createdAt = new DateTime($park['created_at']);
            $diffSec   = $today->getTimestamp() - $createdAt->getTimestamp();

            // Past 14 days = 14 * 86400 seconds
            if ($diffSec >= 0 && $diffSec <= 14 * 86400) {
                $newParks[] = $park;
            }
        } catch (Exception $e) {
            // ignore invalid date formats
        }
    }
}
?>

<div class="PARKSHOW">

     <!-- NEWLY ADDED SECTION -->
   <?php if (!empty($newParks)): ?>
<section id="newly-added">
    <h2>NEWLY ADDED AQUA PARKS</h2>
    <p class="section-note">These aqua parks were added in the past 2 weeks</p>

    <div class="carousel-container">
        <button class="nav-btn prev" onclick="scrollCarousel('newly-added-carousel', -270)">
            <span>&#10094;</span>
        </button>

        <div class="cards" id="newly-added-carousel">
            <?php foreach ($newParks as $park):
                $images = !empty($park['pictures']) ? explode(',', $park['pictures']) : [];
                $firstImage = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';
                $parkId = htmlspecialchars($park['id'] ?? '');
            ?>
                <a href="park_info.php?id=<?= $parkId ?>" class="card">
                    <!-- NEW badge -->
                    <div class="new-badge">NEW</div>

                    <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($park['name'] ?? '') ?>">
                    <div class="card-content">
                        <div class="category"><?= htmlspecialchars($park['subcategory_name'] ?? 'Aqua Park') ?> • <?= htmlspecialchars($park['city'] ?? 'Philippines') ?></div>
                        <div class="title"><?= htmlspecialchars($park['name'] ?? '') ?></div>
                        <div class="price">₱<?= number_format($park['min_price'] ?? 0) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <button class="nav-btn next" onclick="scrollCarousel('newly-added-carousel', 270)">
            <span>&#10095;</span>
        </button>
    </div>
</section>
<?php endif; ?>

    <br><br>


   <!-- SUBCATEGORY SECTIONS -->
<?php foreach ($subcategoryParks as $subcategoryId => $subcategoryData): ?>
    <?php if (!empty($subcategoryData['parks']) && is_array($subcategoryData['parks'])): ?>
        <section id="<?= strtolower(str_replace(' ', '-', $subcategoryData['name'])) ?>">
            <h2><?= strtoupper(htmlspecialchars($subcategoryData['name'])) ?></h2>
            <div class="carousel-container">
                <button class="nav-btn prev" onclick="scrollCarousel('carousel-<?= $subcategoryId ?>', -270)">
                    <span>&#10094;</span>
                </button>

                <div class="cards" id="carousel-<?= $subcategoryId ?>">
                    <?php foreach ($subcategoryData['parks'] as $park):
                        if (!is_array($park)) continue;

                        $images = !empty($park['pictures']) ? explode(',', $park['pictures']) : [];
                        $firstImage = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';

                        // check kung NEW
                        $isNew = false;
                        if (!empty($park['created_at'])) {
                            try {
                                $createdAt = new DateTime($park['created_at']);
                                $diffSec   = $today->getTimestamp() - $createdAt->getTimestamp();
                                $isNew     = ($diffSec >= 0 && $diffSec <= 14 * 86400);
                            } catch (Exception $e) {
                                $isNew = false;
                            }
                        }

                        // end date for countdown
                        $endDate = isset($park['end_date']) ? $park['end_date'] : null;
                    ?>
                        <a href="park_info.php?id=<?= htmlspecialchars($park['id'] ?? '') ?>" class="card">
                            <?php if ($isNew): ?>
                                <div class="new-badge">NEW</div>
                            <?php endif; ?>

                            <?php if ($endDate): ?>
                                <div class="timer-badge" data-end="<?= $endDate ?>"></div>
                            <?php endif; ?>

                            <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($park['name'] ?? '') ?>">
                            <div class="card-content">
                                <div class="category"><?= htmlspecialchars($park['subcategory_name'] ?? $subcategoryData['name']) ?> • <?= htmlspecialchars($park['city'] ?? 'Philippines') ?></div>
                                <div class="title"><?= htmlspecialchars($park['name'] ?? '') ?></div>
                                <div class="tag">Free cancellation</div>
                                <div class="price">₱<?= number_format($park['min_price'] ?? 0) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button class="nav-btn next" onclick="scrollCarousel('carousel-<?= $subcategoryId ?>', 270)">
                    <span>&#10095;</span>
                </button>
            </div>
        </section>
        <br><br>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (empty($subcategoryParks) && empty($newParks)): ?>
    <div class="no-data">
        <h3>No aqua parks available at the moment.</h3>
    </div>
<?php endif; ?>

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



    <!-------CHATBOT SECTION------>
 <?php include 'chatbot/chatbot-widget.html'; ?>
 
     <!-------FOOTER SECTION------>
 <?php include 'navbar/footer.html'; ?>
 
 
      <!-------LOADER SECTION------>
 <?php include 'navbar/loader.html'; ?>  

      <!-------MODAL SECTION------>
 <?php include 'modal/modal-handler.php'; ?>
 



<button id="scrollToTopBtn" title="Go to top">&#8679;</button>
<script src="scripts/darkmode.js"></script>
<script src="scripts/hidepass.js"></script>
<script src="scripts/scrolltop.js"></script>
<script src="scripts/loader.js"></script>
<script src="scripts/carousel.js"></script>
<script src="scripts/chatbot.js"></script>
<script src="scripts/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

<!--- KAPAG HINDI PA LOGIN--->
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