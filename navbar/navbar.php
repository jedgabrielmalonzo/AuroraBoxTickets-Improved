<?php
if (!isset($_SESSION)) session_start();
$userId = $_SESSION['user_id'] ?? null;
$user = null;

if ($userId) {
  $stmt = $conn->prepare("SELECT firstname FROM user WHERE id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

/* Wishlist Count */
$wishlistCount = 0;
if ($userId) {
  $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM wishlist WHERE user_id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $wishlistCount = $result['cnt'] ?? 0;
  $stmt->close();
}

/* Cart Count */
$cartCount = 0;
if ($userId) {
  $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM cart WHERE user_id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute(); 
  $result = $stmt->get_result()->fetch_assoc();
  $cartCount = $result['cnt'] ?? 0;
  $stmt->close();
}

/* Inbox Count = ALL notifications + ALL announcements addressed to the user */
$inboxCount = 0;
if ($userId) {
  // Count ALL notifications (read + unread)
  $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE recipient_id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $notifCount = $result['cnt'] ?? 0;
  $stmt->close();

  // Count announcements for this user
  $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM email_announcements WHERE FIND_IN_SET(?, user_ids) > 0");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $announceCount = $result['cnt'] ?? 0;
  $stmt->close();

  $inboxCount = $notifCount + $announceCount;
}

/* Inbox Messages (latest 5 for dropdown) */
$inboxMessages = [];

if ($userId) {
  // 1. Email Announcements
  $stmt = $conn->prepare("
    SELECT subject AS title, message AS content, sent_at AS timestamp, 'announcement' AS source
    FROM email_announcements 
    WHERE FIND_IN_SET(?, user_ids) > 0
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $inboxMessages[] = $row;
  }
  $stmt->close();

  // 2. Notifications
  $stmt = $conn->prepare("
    SELECT title, content, timestamp, status, 'notification' AS source
    FROM notifications
    WHERE recipient_id = ?
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $inboxMessages[] = $row;
  }
  $stmt->close();
}

// Sort combined messages by timestamp (latest first)
usort($inboxMessages, function($a, $b) {
  return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Limit to 5 items
$inboxMessages = array_slice($inboxMessages, 0, 5);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/navnew.css">
  <link rel="stylesheet" href="css/sunmoon.css">
  <link rel="stylesheet" href="css/navbar-footer.css">
  <link rel="stylesheet" href="css/scrolltop.css">
</head>
<body>
<!-- TOP CONTACT BAR: Now part of navbar.php for unified include -->
<div class="top-bar py-2" style="background:#684d8f; position:fixed; top:0; left:0; width:100%; z-index:1001;">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <a class="navbar-brand me-3" href="index.php">
        <img src="images/logo.png" alt="Logo" class="img-fluid logo">
      </a>
      <form id="searchForm" method="GET" action="search.php" class="d-flex align-items-center">
        <div class="input-wrapper d-flex align-items-center">
          <button class="icon me-2" type="submit" aria-label="Search">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="22px" width="22px">
              <path stroke-linejoin="round" stroke-linecap="round" stroke-width="1.5" stroke="#fff" d="M11.5 21C16.7 21 21 16.7 21 11.5C21 6.3 16.7 2 11.5 2C6.3 2 2 6.3 2 11.5C2 16.7 6.3 21 11.5 21Z"/>
              <path stroke-linejoin="round" stroke-linecap="round" stroke-width="1.5" stroke="#fff" d="M22 22L20 20"/>
            </svg>
          </button>
          <input placeholder="Search by title..." class="form-control form-control-sm" name="query" type="text" aria-label="Search input" id="searchInput">
          <input type="hidden" name="fuzzy_ids" id="fuzzyIds">
        </div>
      </form>
      <div id="search-results" class="mt-2"></div>
    </div>
    <div class="d-flex align-items-center">
      <?php if (isset($_SESSION['user_id'])): ?>
        <span class="nav-link text-white me-3">
          Welcome, <?= htmlspecialchars($user['firstname'] ?? 'User'); ?>!
        </span>
        <a class="logout-btn me-3" href="?logout=true" aria-label="Logout">
          <i class="fas fa-sign-out-alt"></i>
          <span class="logout-text">Logout</span>
        </a>
      <?php else: ?>
        <span class="nav-link me-3">
          <a class="nav-link" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</a>
        </span>
        <span class="nav-link">
          <a class="nav-link" data-bs-toggle="modal" data-bs-target="#signupModal">Sign Up</a>
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
/* Wrapper ng icons (parent) */
.navbar-icons {
  position: relative; /* para yung dropdown ma-attach dito */
}

#inboxToggle {
  background: none;
  border: none;
  padding: 0;
  margin: 0;
  cursor: pointer;
}

#inboxToggle:focus {
  outline: none; /* alisin yung blue outline pag click */
  box-shadow: none;
}


/* Dropdown inbox */
.inbox-dropdowns {
  position: absolute;
  top: 40px; /* space mula sa icon pababa */
  right: 0; /* dikit sa kanan ng icon */
  width: 320px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.25);
  display: none;
  z-index: 99999;
}


.inbox-header, .inbox-footer {
  padding: 10px;
  border-bottom: 1px solid #eee;
  font-weight: bold;
}

.inbox-footer {
  border-top: 1px solid #eee;
  text-align: center;
}

.inbox-body {
  max-height: 300px;
  overflow-y: auto;
  padding: 10px;

  display: flex;        /* make children align in a row */
  flex-wrap: wrap;      /* wrap to next line if they overflow */
  gap: 2px;            /* spacing between messages */
}

.message {
  flex: 1 1 200px;      /* width: at least 200px, grow if possible */
  padding: 8px;
  border: 1px solid #f1f1f1;
  border-radius: 8px;
  background: #f9f9f9;
  font-size: 14px;
}


.message {
  display: flex;
  flex-direction: column; /* stack subject, message, date vertically */
  gap: 4px;               /* spacing between lines */
  padding: 8px;
  border-bottom: 1px solid #f1f1f1;
}

.message strong {
  font-weight: bold;
}

.message small {
  color: gray;
  font-size: 12px;
}


.message small {
  color: gray;
}

.close-inbox {
  float: right;
  cursor: pointer;
}


.unread-msg {
  background: #ffecec;
  font-weight: bold;
}

.nav-icon {
  position: relative; /* para yung badge ma-attach sa icon */
  font-size: 22px;    /* size ng icon (adjust as needed) */
}

.notification-dot {
  position: absolute;
  top: -6px;    /* adjust depende sa laki ng icon */
  right: -6px;
  background: red;
  color: white;
  font-size: 11px;
  font-weight: bold;
  width: 18px;
  height: 18px;
  border-radius: 50%;  /* gawin siyang bilog */
  display: flex;       /* para centered yung number */
  align-items: center;
  justify-content: center;
  line-height: 1;
}
/* apply to all sections na target ng hash */
[id] {
  scroll-margin-top: 130px; /* adjust based on navbar height */
}

</style>
<body>
    
   <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="position:fixed; top:61px; left:0; width:100%; z-index:999; background:#FFFDF7;">
      <div class="container">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
          <ul class="navbar-nav mx-auto">
            <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
<body style="margin-top:120px;">
<body style="margin-top:120px;">
<body style="margin-top:120px;">
                    
                    <!-- Theme Parks -->
          <li class="nav-item dropdown">
    <a class="nav-link" href="themeparks.php" onclick="navigateTo('themeparks.php')">Theme Parks</a>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="themeparks.php" onclick="navigateTo('themeparks.php')">ALL THEME PARKS</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="themeparks.php#fantasy" onclick="navigateTo('themeparks.php#fantasy')"><img src="images/nav/theme1.png" alt="Water Spa" class="dropdown-icon"> Fantasy</a></li>
        <li><a class="dropdown-item" href="themeparks.php#carnival" onclick="navigateTo('themeparks.php#carnival')"><img src="images/nav/theme2.png" alt="Water Spa" class="dropdown-icon">Carnival</a></li>
        <li><a class="dropdown-item" href="themeparks.php#animal" onclick="navigateTo('themeparks.php#animal')"><img src="images/nav/theme3.png" alt="Water Spa" class="dropdown-icon">Animal</a></li>
        <li><a class="dropdown-item" href="themeparks.php#haunted" onclick="navigateTo('themeparks.php#haunted')"><img src="images/nav/theme4.png" alt="Water Spa" class="dropdown-icon">Haunted</a></li>
        <li><a class="dropdown-item" href="themeparks.php#children" onclick="navigateTo('themeparks.php#children')"><img src="images/nav/theme5.png" alt="Water Spa" class="dropdown-icon">Children</a></li>
    </ul>
</li>

                    <!-- Aqua Parks -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="aquaparks.php">Aqua Parks</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="aquaparks.php">ALL AQUA PARKS</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="aquaparks.php#water-spa"><img src="images/nav/aqua1.png" alt="Water Spa" class="dropdown-icon"> Water Spa</a></li>
                            <li><a class="dropdown-item" href="aquaparks.php#hot-spring"><img src="images/nav/aqua2.png" alt="Hot Spring" class="dropdown-icon"> Hot Spring</a></li>
                            <li><a class="dropdown-item" href="aquaparks.php#inflatables"><img src="images/nav/aqua3.png" alt="Inflatables" class="dropdown-icon"> Inflatables</a></li>
                            <li><a class="dropdown-item" href="aquaparks.php#resorts"><img src="images/nav/aqua4.png" alt="Resorts" class="dropdown-icon"> Resorts</a></li>
                            <li><a class="dropdown-item" href="aquaparks.php#river-adventure"><img src="images/nav/aqua5.png" alt="River Adventure" class="dropdown-icon"> River Adventure</a></li>
                        </ul>
                    </li>

                    <!-- Nature Parks -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="natureparks.php">Nature Parks</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="natureparks.php">ALL NATURE PARKS</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="natureparks.php#forest"><img src="images/nav/nature1.png" alt="Forest Park" class="dropdown-icon"> Forest Park</a></li>
                            <li><a class="dropdown-item" href="natureparks.php#geological"><img src="images/nav/nature2.png" alt="Geological" class="dropdown-icon"> Geological</a></li>
                            <li><a class="dropdown-item" href="natureparks.php#wildlife"><img src="images/nav/nature3.png" alt="Wildlife" class="dropdown-icon"> Wildlife</a></li>
                            <li><a class="dropdown-item" href="natureparks.php#subterranean"><img src="images/nav/nature4.png" alt="Subterranean" class="dropdown-icon"> Subterranean</a></li>
                            <li><a class="dropdown-item" href="natureparks.php#aquatic"><img src="images/nav/nature5.png" alt="Aquatic" class="dropdown-icon"> Aquatic</a></li>
                        </ul>
                    </li>

                    <!-- Museums -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="museums.php">Museums</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="museums.php">ALL MUSEUMS</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="museums.php#art"><img src="images/nav/museum1.png" alt="Art" class="dropdown-icon"> Art</a></li>
                            <li><a class="dropdown-item" href="museums.php#heritage-homes"><img src="images/nav/museum2.png" alt="Heritage Homes" class="dropdown-icon"> Heritage Homes</a></li>
                            <li><a class="dropdown-item" href="museums.php#military"><img src="images/nav/museum3.png" alt="Military" class="dropdown-icon"> Military</a></li>
                            <li><a class="dropdown-item" href="museums.php#sci-tech"><img src="images/nav/museum4.png" alt="Sci-Tech" class="dropdown-icon"> Sci-Tech</a></li>
                            <li><a class="dropdown-item" href="museums.php#nature"><img src="images/nav/museum5.png" alt="Nature" class="dropdown-icon"> Nature</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Icons with tooltip text -->
                <div class="d-flex align-items-center navbar-icons">
                    
           

<script>
function navigateTo(url) {
    const currentUrl = window.location.href.split('#')[0]; // current page without hash
    const targetUrl = url.split('#')[0]; // target page without hash
    const hash = url.split('#')[1]; // get section ID if any

    if (currentUrl === targetUrl) {
        // Same page → scroll smoothly with navbar offset
        if (hash) {
            const targetElement = document.getElementById(hash);
            if (targetElement) {
                const navbarHeight = document.querySelector('.navbar').offsetHeight || 60; 
                const elementPos = targetElement.getBoundingClientRect().top + window.pageYOffset;
                window.scrollTo({
                    top: elementPos - navbarHeight - 20, // offset + extra spacing
                    behavior: 'smooth'
                });
            }
        }
        return false; // Prevent reload
    } else {
        // Different page → go there (browser will handle hash scroll)
        window.location.href = url;
    }
}

</script>

<!-- Inbox Icon -->
<button type="button" class="nav-icon" id="inboxToggle" 
        onclick="window.location.href='inbox.php'" 
        data-label="Inbox"
        data-logged-in="<?= isset($_SESSION['user_id']) ? 1 : 0 ?>">
  <i class="fa-solid fa-envelope"></i>
  <span class="notification-dot" id="inbox-notification-dot"
        style="display: inline-flex; visibility: <?= ($inboxCount > 0 ? 'visible' : 'hidden') ?>;">
        <?= $inboxCount ?>
  </span>
</button>


                    
                    <a href="javascript:void(0)" class="nav-icon" data-label="Wishlist" onclick="handleWishlistClick()">
                        <i class="fa-solid fa-heart"></i>
                        <span class="notification-dot" id="wishlist-notification-dot" 
                              style="display: <?= ($wishlistCount > 0 ? 'inline-flex' : 'none') ?>;">
                              <?= $wishlistCount ?>
                        </span>
                    </a>                  

                    <a href="javascript:void(0)" class="nav-icon" data-label="Cart" onclick="handleCartClick()">
    <i class="fa-solid fa-cart-shopping"></i>
    <span class="notification-dot" id="cart-notification-dot" 
          style="display: <?= ($cartCount > 0 ? 'inline-flex' : 'none') ?>;">
          <?= $cartCount ?>
    </span>
</a>

                    <a href="javascript:void(0)" class="nav-icon" data-label="Account" onclick="handleAccountClick()">
                        <i class="fa-solid fa-user"></i>
                    </a>

                    <!-- Dark Mode Toggle -->
                    <label class="switch ms-3">
                        <input type="checkbox" id="darkModeToggle">
                        <span class="slider">
                            <i class="fas fa-moon moon-icon"></i>
                            <i class="fas fa-sun sun-icon"></i>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </nav>

    <script src="scripts/darkmode.js"></script>
    <script>
    document.getElementById("inboxToggle").addEventListener("click", function(e) {
  e.preventDefault();
  const loggedIn = this.getAttribute("data-logged-in") === "1";

  if (!loggedIn) {
    // Show login modal if not logged in
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
    return;
  }

  toggleInbox();
});

function toggleInbox() {
  const inbox = document.getElementById("inboxDropdown");
  if (inbox) {
    inbox.style.display = inbox.style.display === "block" ? "none" : "block";
  }
}

// Close inbox when clicking outside
document.addEventListener("click", function(e) {
  const inbox = document.getElementById("inboxDropdown");
  const toggle = document.getElementById("inboxToggle");
  if (inbox && !inbox.contains(e.target) && !toggle.contains(e.target)) {
    inbox.style.display = "none";
  }
});


    // Notification dot fix: always use inline-flex and hide with visibility, not display
function updateNotificationDot(id, count) {
    var dot = document.getElementById(id);
    if (!dot) return;
    dot.style.display = 'inline-flex';
    dot.style.visibility = (count > 0) ? 'visible' : 'hidden';
    dot.textContent = count > 0 ? count : '';
}
// On DOMContentLoaded, update all notification dots
window.addEventListener('DOMContentLoaded', function() {
    updateNotificationDot('inbox-notification-dot', <?= json_encode($inboxCount ?? 0) ?>);
    updateNotificationDot('wishlist-notification-dot', <?= json_encode($wishlistCount ?? 0) ?>);
    updateNotificationDot('cart-notification-dot', <?= json_encode($cartCount ?? 0) ?>);
});

    // Auto-refresh wishlist count on page load
    document.addEventListener("DOMContentLoaded", function () {
        fetch('wishlist.php?action=get_counts')
            .then(response => response.json())
            .then(data => {
                const dot = document.getElementById('wishlist-notification-dot');
                if (dot) {
                    if (data.wishlist > 0) {
                        dot.style.display = "inline-flex";
                        dot.innerText = data.wishlist;
                    } else {
                        dot.style.display = "none";
                    }
                }
            })
            .catch(err => console.error("Error loading wishlist count:", err));
    });
    </script>
</body>
</html>
