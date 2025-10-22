<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AuroraBox</title>
  <link rel="icon" type="image/x-icon" href="images/favicon.png" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css"
    integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
  <script src="https://unpkg.com/just-validate@latest/dist/just-validate.production.min.js" defer></script>
  <script src="scripts/validation.js" defer></script>
  <style><?php include 'css/signup.css'; ?></style>
  <style><?php include 'css/navbar-footer.css'; ?></style>
</head>
<body>
  <div class="log-bg-image">
    <nav class="navbar navbar-expand-lg navbar-light bg-body-tertiary">
      <div class="container-fluid">
        <div class="row w-100 align-items-center justify-content-between">
          <div class="col-4 text-center">
            <div class="navbar-brand">
              <a href="index.php"><img src="/images/logo.png" alt="Logo" class="img-fluid" /></a>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <div class="sign-up-form">
      <div class="sign-up-card">
        <h1 class="log-in-text">CREATE AN ACCOUNT</h1>
        <div class="content-container">
          <!-- Form Section -->
          <div class="form-container">
            <form action="process-signup.php" method="post" id="signup">
              <div class="row">
                <div class="col-12 col-sm-6">
                  <div class="input-div">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" placeholder="First Name" />
                  </div>
                </div>
                <div class="col-12 col-sm-6">
                  <div class="input-div">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" placeholder="Last Name" />
                  </div>
                </div>
              </div>

              <div class="input-div">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Email" />
              </div>

             <div class="input-div password-field">
  <label for="password">Password</label>
  <div class="input-wrapper">
    <input type="password" id="password" name="password" placeholder="Password" />
    <i class="fas fa-eye toggle-password" data-target="password"></i>
  </div>
</div>

<div class="input-div password-field">
  <label for="password_confirmation">Confirm Password</label>
  <div class="input-wrapper">
    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm Password" />
    <i class="fas fa-eye toggle-password" data-target="password_confirmation"></i>
  </div>
</div>


              <button class="btn">Sign Up</button>
            </form>

            <p>Already have an account? <a href="login.php">Log In</a></p>
          </div>

          <!-- Image Section -->
          <div class="home__img">
            <img src="/images/kid.png" alt="Movie Ticket Banner" class="home__hero-img img-fluid" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
    integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.7/dist/umd/popper.min.js"
    integrity="sha384-nmDcf8eY73M3PzF+4kA/Z0wHzCk1Y7+/2n/Gzdf4F/MZCBlzOU1TQ7qZftP5ODZ7" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"
    integrity="sha384-C8K1m1gq0D/s3h6KcT7wN1Z7tVxB5X8wU4Pp+75tx6VX76LuTft4U6tK0W7kTQ8" crossorigin="anonymous"></script>
  <script src="/scripts/LandingPage.js"></script>

  <!-- Password Toggle Script -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const toggles = document.querySelectorAll(".toggle-password");

      toggles.forEach(function (toggle) {
        toggle.style.position = "absolute";
        toggle.style.right = "10px";
        toggle.style.top = "50%";
        toggle.style.transform = "translateY(-50%)";
        toggle.style.cursor = "pointer";
        toggle.style.color = "#888";

        toggle.addEventListener("click", function () {
          const targetId = this.getAttribute("data-target");
          const input = document.getElementById(targetId);

          const isHidden = input.type === "password";
          input.type = isHidden ? "text" : "password";

          this.classList.remove("fa-eye", "fa-eye-slash");
          this.classList.add(isHidden ? "fa-eye-slash" : "fa-eye");
        });
      });
    });
  </script>
</body>
</html>
