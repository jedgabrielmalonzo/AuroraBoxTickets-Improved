<?php
require_once __DIR__ . '/config.php';
session_start(); // Start the session

// Database connection
$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require 'gClientSetup.php';

// Initialize $is_invalid variable
$is_invalid = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // verify password hash
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION["user_id"] = $user['id'];

            $update_sql = "UPDATE user SET is_active = 1, last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('i', $_SESSION["user_id"]);
            $update_stmt->execute();

            header("Location: index.php");
            exit;
        } else {
            $is_invalid = true;
        }
    } else {
        $is_invalid = true;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AuroraBox</title>
  <link rel="icon" type="image/x-icon" href="images/favicon.png" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/navbar-footer.css">
  
</head>
<body style="background: url('/images/carouselphotos/10.jpg') center/cover no-repeat;">

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-body-tertiary">
    <div class="container-fluid">
      <a href="index.php" class="navbar-brand">
        <img src="/images/logo.png" alt="Logo" class="img-fluid" />
      </a>
    </div>
  </nav>

  <!-- Login Card -->
  <div class="d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="card p-4" style="max-width: 600px; width: 100%; border-radius: 15px; background-color: #1c1c1c; color: white;">
      <h2 style="color: #ffb400; font-weight: bold;">WELCOME BACK!</h2>
      <form method="post" action="login.php">
        <div class="mb-4">
          <label for="email" class="form-label">Email</label>
          <input type="email" name="email" class="form-control" id="email" placeholder="Email" 
                 value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" style="border-radius: 8px; padding: 10px;" required>
        </div>

        <div class="mb-4 position-relative">
          <label for="password" class="form-label">Password</label>
          <input type="password" name="password" class="form-control" id="password" placeholder="Password" 
                 style="border-radius: 8px; padding: 10px; padding-right: 40px;" required>
          <i class="bx bx-show" id="togglePassword" style="position: absolute; top: 55%; right: 15px; cursor: pointer; color: gray;"></i>
        </div>

        <?php if ($is_invalid): ?>
          <em style="color: red;">Invalid email or password</em>
        <?php endif; ?>

        <button type="submit" name="login" class="btn w-100" style="background-color: #5c2e91; color: white; border-radius: 8px;">Log In</button>

        <a href="<?= $client->createAuthUrl() ?>" class="btn w-100 mt-3" style="background-color: #5c2e91; color: white; border-radius: 8px;">
          <i class="bx bxl-google"></i> Login with Google
        </a>

        <p class="mt-3 text-center">
          Don't have an account?
          <a href="signup.php" style="color: #ffb400; text-decoration: none;">Sign Up</a>
        </p>
      </form>
    </div>
  </div>

  <script>
    document.getElementById("togglePassword").addEventListener("click", function() {
      const passwordInput = document.getElementById("password");
      const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
      passwordInput.setAttribute("type", type);
      this.classList.toggle("bx-show");
      this.classList.toggle("bx-hide");
    });
  </script>
  
  <style>
      /* Container card for login & signup */
.card {
  max-width: 600px;
  width: 100%;
  border-radius: 15px;
  background-color: #1c1c1c;
  color: white;
  padding: 2rem;
  box-sizing: border-box;
}

/* Form labels */
.form-label {
  color: white;
  font-weight: 500;
}

/* Inputs */
.form-control {
  border-radius: 8px;
  padding: 10px;
  border: none;
  outline: none;
  width: 100%;
  box-sizing: border-box;
}

/* Buttons */
.btn {
  border-radius: 8px;
  background-color: #5c2e91;
  color: white;
  font-weight: 600;
  padding: 10px;
  border: none;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.btn:hover {
  background-color: #7d54bd;
}

/* Spacing */
.mb-3, .mb-4 {
  margin-bottom: 1rem;
}

.mt-3 {
  margin-top: 1rem;
}

/* Password toggle icon */
.position-relative {
  position: relative;
}

.bx-show, .bx-hide {
  position: absolute;
  top: 55%;
  right: 15px;
  cursor: pointer;
  color: gray;
  font-size: 1.2rem;
  user-select: none;
}

/* Text link in form */
p a {
  color: #ffb400;
  text-decoration: none;
  font-weight: 600;
}

p a:hover {
  text-decoration: underline;
}

/* Text center for the paragraph */
.text-center {
  text-align: center;
}

      
  </style>
</body>
</html>
