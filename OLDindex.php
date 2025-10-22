<?php
session_start();
require __DIR__ . '/gClientSetup.php'; // Needed for Google OAuth link

$mysqli = require __DIR__ . "/database.php";
$sql = "SELECT * FROM user WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $_SESSION["user_id"]); 
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$is_invalid = false; // default, only changes if you process login here
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/landingpage.css" />
    
    

    <!-- =====BOX ICONS===== -->
    <link
      href="https://cdn.jsdelivr.net/npm/boxicons@2.0.5/css/boxicons.min.css"
      rel="stylesheet"
    />
        <!-- =====CAROUSEL===== -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />

    <title>Aurorabox</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
  </head>
  <body>
    <!--===== HEADER =====-->
    <header class="l-header">
      <nav class="nav bd-grid">
        <div>
          <a href="#" class="nav__logo">
            <img src="images/logo.png" alt="Logo" style="height: 40px;">
          </a>
        </div>
    

        <div class="nav__menu" id="nav-menu">
          <ul class="nav__list">
            <li class="nav__item">
              <a href="#home" class="nav__link active-link">Home</a>
            </li>
            <li class="nav__item">
              <a href="guest.php" class="nav__link">Tickets</a>
            </li>
            <li class="nav__item">
                <a href="#" class="nav__link" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</a>
            </li>
           <li class="nav__item">
                <a href="#" class="nav__link" data-bs-toggle="modal" data-bs-target="#signupModal">Sign Up</a>
            </li>
          </ul>
        </div>

        <div class="nav__toggle" id="nav-toggle">
          <i class="bx bx-menu"></i>
        </div>
      </nav>
      <!-- Disclaimer Bar -->
      <div class="disclaimer">
      ⚠️ This website is a student project. All content is for educational purposes only.
      </div>

    </header>

    <main class="l-main">
      
<!--===== HOME =====-->
<section class="home" id="home">
  <div class="home__container">
    <div class="home__data">
      <h1 class="home__title">
        YOUR NEXT <span class="home__title-color">EXPERIENCE</span> STARTS HERE
      </h1>
      <div class="get-started-button">
        <a href="signup.php" class="button">GET STARTED</a>
      </div>
    </div>

    <div class="home__img">
      <img src="/images/kid.png" alt="Movie Ticket Banner" class="home__hero-img">
    </div>
  </div>
</section>

  <div class="page-wrapper">

    <!-- ✅ BOTTOM INFINITE IMAGE STRIP WITH OVERLAY -->
    <div class="image-strip-container">
      <div class="image-strip-track">
        <div class="image-strip-item"><img src="images/carouselphotos/11.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/12.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/13.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/14.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/11.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/12.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/13.jpg" alt=""></div>
        <div class="image-strip-item"><img src="images/carouselphotos/14.jpg" alt=""></div>
      </div>
      <div class="overlay-text">
        <h2>YOUR ONE-STOP ENTERTAINMENT TICKET SITE.</h2>
      </div>
    </div>

    </main>
    
    
    
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
                  <input type="email" name="email" placeholder="Email"
                         value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" required />
                </div>
                
                <div class="input-div">
                  <label for="password">Password</label>
                  <div class="password-field" style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="Password" required />
                    <i class="fas fa-eye password-toggle" id="togglePassword" 
                       style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
                              cursor: pointer; color: #888;"></i>
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
              <form method="post" action="signup.php">
                <div class="input-div">
                  <label for="name">Full Name</label>
                  <input type="text" name="name" placeholder="Your Name" required />
                </div>

                <div class="input-div">
                  <label for="signupEmail">Email</label>
                  <input type="email" name="email" placeholder="Email" required />
                </div>

                <div class="input-div">
                  <label for="signupPassword">Password</label>
                  <div class="password-field" style="position: relative;">
                    <input type="password" id="signupPassword" name="password" placeholder="Password" required />
                    <i class="fas fa-eye password-toggle" id="toggleSignupPassword" 
                       style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
                              cursor: pointer; color: #888;"></i>
                  </div>
                </div>

                <button class="btn btn-primary w-100 mb-2" type="submit">Sign Up</button>
                <a href="<?= $client->createAuthUrl() ?>" class="btn btn-danger w-100 mb-3">
                  <i class="fab fa-google me-2"></i> Sign Up with Google</a>
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



    <!--===== FOOTER =====-->
<footer class="footer">
  <div class="footer__logo">
    <img src="/images/logo.png" alt="Logo" style="height: 40px;">
  </div>
  <p class="footer__copy">&#169; Aurorabox. All rights reserved</p>
</footer>
<script>
  document.getElementById('toggleSignupPassword').addEventListener('click', function () {
    const passwordField = document.getElementById('signupPassword');
    passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
    this.classList.toggle('bx-hide');
  });
</script>
    
    
        <script>
    document.getElementById('togglePassword').addEventListener('click', function () {
      const passwordField = document.getElementById('password');
      passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
    });
    </script>

    <!--===== SCROLL REVEAL =====-->
    <script src="https://unpkg.com/scrollreveal"></script>

    <!--===== MAIN JS =====-->
    <script src="scripts/main.js"></script>

    <!---BOOTSTRAP-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
