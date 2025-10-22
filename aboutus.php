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

$result = $conn->query("
    SELECT * FROM about_founders 
    WHERE status='active' 
    ORDER BY FIELD(title, 'CEO', 'CTO', 'COO', 'Head of Marketing', 'Head of Design')
");
$founders = $result->fetch_all(MYSQLI_ASSOC);

$result = $conn->query("SELECT * FROM about_sections WHERE status='active' ORDER BY id ASC");
$sections = $result->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->query("SELECT * FROM about_founders WHERE status='active' ORDER BY display_order ASC, id ASC");
$founders = $stmt->fetch_all(MYSQLI_ASSOC);
// Removed duplicate DB params
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuroraBox - About Us</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Stylesheets -->
    <link rel="stylesheet" href="css/navbar.css">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style><?php include 'css/aboutpage.css';?></style>
         <link rel="stylesheet" href="css/login.css">
  <link rel="stylesheet" href="css/homepage.css">
      <link rel="stylesheet" href="css/loading.css">

</head>
<body>
<header>
       
    
    <!-------NAVBAR SECTION------>
     <?php include 'navbar/navbar.php'; ?>

   
</header>


<style>
    body.about-aurorabox {
      font-family: Arial, sans-serif;
      background: #f9f9f9;
      color: #333;
      margin: 0;
      padding: 0;
      line-height: 1.6;
    }

    /* HEADER */
    .about-header {
      position: relative;
      height: 320px;
      background: url('images/asdd.png') center/cover no-repeat;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #340e79c5;
    }

    .about-header .overlay {
      
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .about-header h1 {
      margin: 0;
      font-size: 2.8rem;
      animation: fadeDown 1s ease-in-out;
    }

    .about-header p {
      font-size: 1.2rem;
      margin-top: 0.5rem;
      animation: fadeUp 1s ease-in-out;
    }

    /* SECTIONS */
    .about-section {
      max-width: 1000px;
      margin: 2rem auto;
      padding: 2rem;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      animation: fadeUp 1s ease;
    }

    .about-section h2 {
      color: #684D8F;
      margin-bottom: 1rem;
    }

    /* FOUNDERS */
  .about-founders {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  justify-items: center; /* pantay ang cards */
}

.about-card {
  background: #f3f3ff;
  padding: 1rem;
  border-radius: 10px;
  text-align: center;
  transition: transform 0.4s ease, box-shadow 0.4s ease;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  width: 100%;
  max-width: 260px;
  opacity: 0;               /* hidden sa simula */
  transform: translateY(30px); /* naka-slide pababa muna */
  animation: fadeInUp 0.8s forwards;
}

.about-card:nth-child(1) { animation-delay: 0.2s; }
.about-card:nth-child(2) { animation-delay: 0.4s; }
.about-card:nth-child(3) { animation-delay: 0.6s; }
.about-card:nth-child(4) { animation-delay: 0.8s; }
.about-card:nth-child(5) { animation-delay: 1s; }

.about-card:hover {
  transform: translateY(-10px) scale(1.05);
  box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.about-card img {
  width: 120px;
  height: 120px;
  object-fit: cover;
  border-radius: 50%;
  margin-bottom: 1rem;
  border: 3px solid #684D8F;
  transition: transform 0.3s ease;
}

.about-card:hover img {
  transform: rotate(5deg) scale(1.1);
}

/* Animation Keyframes */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

    .about-card h3 {
      margin: 0.5rem 0 0.2rem;
      color: #333;
    }

    .about-card p {
      font-size: 0.9rem;
      color: #555;
    }

  

    /* VALUES LIST */
    .about-section ul {
      list-style: none;
      padding: 0;
    }
    .about-section ul li {
      margin-bottom: 0.8rem;
      padding-left: 1.5rem;
      position: relative;
    }
    .about-section ul li::before {
      content: "✔";
      position: absolute;
      left: 0;
      color: #684D8F;
      font-weight: bold;
    }

    /* CTA */
    .about-cta {
      position: relative;
      background: linear-gradient(135deg, rgba(104, 77, 143, 0.95), rgba(138, 107, 191, 0.85)),
                  url('images/bg2.png') center/cover no-repeat;
      color: #fff;
      text-align: center;
      padding: 4rem 2rem;
      margin-top: 3rem;
      border-radius: 15px 15px 0 0;
      animation: fadeUp 1.2s ease;
    }   

    .about-cta h2 {
      font-size: 2rem;
      margin-bottom: 1rem;
      font-weight: bold;
      letter-spacing: 1px;
    }

    .about-cta p {
      font-size: 1.1rem;
      max-width: 700px;
      margin: 0 auto 2rem;
      opacity: 0.9;
    }

    .about-cta .cta-btn {
      background: #ff6a3d;
      color: #fff;
      padding: 0.9rem 2rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
      letter-spacing: 1px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .about-cta .cta-btn:hover {
      background: #684D8F;
      transform: translateY(-4px);
      box-shadow: 0 6px 18px rgba(0,0,0,0.3);
    }

    /* FOOTER */
    .about-footer {
      text-align: center;
      padding: 1.5rem;
      background: #684D8F;
      color: #fff;
      margin-top: 0;
    }

    /* ANIMATIONS */
    @keyframes fadeUp {
      from {opacity: 0; transform: translateY(20px);}
      to {opacity: 1; transform: translateY(0);}
    }
    @keyframes fadeDown {
      from {opacity: 0; transform: translateY(-20px);}
      to {opacity: 1; transform: translateY(0);}
    }
  </style>
</head>
<body class="about-aurorabox">
  <!-- HEADER -->
  <header class="about-header">
     
    <div class="overlay">
      <h1>About AuroraBox</h1>
      <p>Unforgettable entertainment experiences — anytime, anywhere.</p>
    </div>
  </header>

<!-- FOUNDERS -->
<section class="about-section">
  <h2>Founders</h2>
  <div class="about-founders">
    <?php
    require 'database.php';
    // fixed order: CEO → CTO → COO → Head of Marketing → Head of Design
    $result = $conn->query("
      SELECT * FROM about_founders 
      WHERE status='active' 
      ORDER BY FIELD(title, 'CEO', 'CTO', 'COO', 'Head of Marketing', 'Head of Design')
    ");
    $founders = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($founders as $f): ?>
      <div class="about-card">
        <img src="uploads/founders/<?= htmlspecialchars($f['image'] ?? 'default.png') ?>" 
             alt="<?= htmlspecialchars($f['name']) ?>">
        <h3><?= htmlspecialchars($f['name']) ?></h3>
        <p><strong><?= htmlspecialchars($f['title']) ?></strong></p>
        <p><?= htmlspecialchars($f['description']) ?></p>
      </div>
    <?php endforeach; ?>

    <?php if (empty($founders)): ?>
      <p class="text-center">🚧 No founders added yet...</p>
    <?php endif; ?>
  </div>
</section>



  <?php foreach ($sections as $s): ?>
  <?php if ($s['section_key'] === 'values'): ?>
    <!-- VALUES (may list format) -->
    <section class="about-section">
      <h2><?= htmlspecialchars($s['title']) ?></h2>
      <ul>
        <?php foreach (explode(',', $s['content']) as $value): ?>
          <li><?= htmlspecialchars(trim($value)) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php elseif ($s['section_key'] === 'cta'): ?>
    <!-- CTA (may button) -->
    <section class="about-cta">
      <div class="cta-content">
        <h2><?= htmlspecialchars($s['title']) ?></h2>
        <p><?= nl2br(htmlspecialchars($s['content'])) ?></p>
        <a href="index.php" class="cta-btn">Browse Events</a>
      </div>
    </section>
  <?php else: ?>
    <!-- Default sections -->
    <section class="about-section">
      <h2><?= htmlspecialchars($s['title']) ?></h2>
      <p><?= nl2br(htmlspecialchars($s['content'])) ?></p>
    </section>
  <?php endif; ?>
<?php endforeach; ?>


</main>
</div>

<?php include 'navbar/footer.html'; ?>
 
       <!-------LOADER SECTION------>
 <?php include 'navbar/loader.html'; ?>  


  <!-------MODAL SECTION------>
 <?php include 'modal/modal-handler.php'; ?>
<button id="scrollToTopBtn" title="Go to top">&#8679;</button>


<script src="wishlist.js"></script> <!-- wala pa wishlist.js -->
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
 
  
 
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
    const categoryButtons = document.querySelectorAll('.category-btn');
    const faqCategories = document.querySelectorAll('.faq-category');

    // Initially hide all sections except the first one
    faqCategories.forEach((categorySection, index) => {
        if (index !== 0) {
            categorySection.style.display = 'none';
        } else {
            categorySection.classList.add('active'); // Show the first section
        }
    });

    // Category button click handler
    categoryButtons.forEach(button => {
        button.addEventListener('click', function () {
            const category = button.getAttribute('data-category');
            const newActiveCategory = document.querySelector(`.faq-category[data-category="${category}"]`);
            const activeCategory = document.querySelector('.faq-category.active');

            if (activeCategory) {
                // Add 'exiting' class to trigger fade-out animation
                activeCategory.classList.add('exiting');

                // Use a timeout to wait for the animation to complete before hiding the element
                setTimeout(() => {
                    activeCategory.classList.remove('active', 'exiting');
                    activeCategory.style.display = 'none';

                    // Show the new category after the old one is hidden
                    if (newActiveCategory) {
                        newActiveCategory.style.display = 'block';
                        requestAnimationFrame(() => {
                            newActiveCategory.classList.add('active');
                        });
                    }
                }, 300); // Duration should match the transition duration in CSS
            } else {
                // If no active category, show the new category immediately
                if (newActiveCategory) {
                    newActiveCategory.style.display = 'block';
                    requestAnimationFrame(() => {
                        newActiveCategory.classList.add('active');
                    });
                }
            }

            // Update active button styling
            categoryButtons.forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');
        });
    });

    const faqQuestions = document.querySelectorAll('.faq-question');

    faqQuestions.forEach(question => {
        question.addEventListener('click', function () {
            const answer = question.nextElementSibling;

            // Close all other answers
            faqQuestions.forEach(otherQuestion => {
                const otherAnswer = otherQuestion.nextElementSibling;
                if (otherAnswer !== answer) {
                    otherAnswer.classList.remove('open');
                    otherAnswer.style.maxHeight = null; // Reset height
                    const otherArrow = otherQuestion.querySelector('.arrow');
                    if (otherArrow) {
                        otherArrow.innerHTML = "&#9660;"; // Reset to downward arrow (▼)
                    }

                    // Reset the color of other questions
                    otherQuestion.querySelector('h3').style.color = ''; // Reset color
                }
            });

            // Toggle the visibility of the currently clicked answer
            if (answer.classList.contains('open')) {
                answer.classList.remove('open');
                answer.style.maxHeight = null; // Reset height
                question.querySelector('.arrow').innerHTML = "&#9660;"; // Downward arrow (▼)

                // Reset the color of the question when it's collapsed
                question.querySelector('h3').style.color = '';
            } else {
                answer.classList.add('open');
                answer.style.maxHeight = answer.scrollHeight + "px"; // Set height to allow transition
                question.querySelector('.arrow').innerHTML = "&#9650;"; // Upward arrow (▲)

                // Change the color of the active question
                question.querySelector('h3').style.color = '#FFA500'; // Set to orange when active
            }
        });
    });
});

// CHANGE//
document.addEventListener("DOMContentLoaded", function() {
    const dropdownLink = document.getElementById('dropdownMenuNOW');
    if (!dropdownLink) {
        console.error('Element with id "dropdownMenuNOW" not found!');
        return;
    }

    let originalText = dropdownLink.innerText;
    let isChooseEvent = false;
    let dropdownOpen = false;

    dropdownLink.addEventListener('click', function(event) {
        event.stopPropagation();

        const that = this; // Store 'this' for use in the setTimeout

        if (!dropdownOpen) {
            // Open the dropdown and change text
            that.classList.add('fade-text');
            setTimeout(() => {
                that.innerText = 'Choose';
                that.classList.remove('fade-text');
                isChooseEvent = true;
                dropdownOpen = true;
            }, 0);
        } else {
            // Close the dropdown and revert to original text
            that.classList.add('fade-text');
            setTimeout(() => {
                that.innerText = originalText;
                that.classList.remove('fade-text');
                isChooseEvent = false;
                dropdownOpen = false;
            }, 0);
        }
    });

    document.addEventListener('click', function(event) {
        if (dropdownOpen && !dropdownLink.contains(event.target)) {
            // Clicked outside the dropdown link
            dropdownLink.classList.add('fade-text');
            setTimeout(() => {
                dropdownLink.innerText = originalText;
                dropdownLink.classList.remove('fade-text');
                isChooseEvent = false;
                dropdownOpen = false;
            }, 0);
        }
    });
});
</script>
    
</script>
</body>
</html>
