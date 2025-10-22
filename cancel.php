<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Canceled</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #dc3545; /* ✅ solid red background */
      text-align: center;
      padding: 50px;
      color: #fff;
    }
    .container {
      background: #fff;
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      display: inline-block;
      max-width: 500px;
      animation: fadeIn 0.8s ease-in-out;
      color: #333;
    }
    .icon {
      font-size: 80px;
      margin-bottom: 20px;
      color: #dc3545;
      animation: pop 0.6s ease forwards, shake 0.4s ease-in-out 0.6s;
      transform: scale(0);
      display: inline-block;
    }
    h1 {
      color: #dc3545;
      margin-bottom: 10px;
    }
    p {
      font-size: 1.1em;
      color: #555;
    }
    .buttons {
      margin-top: 25px;
      display: flex;
      justify-content: center;
      gap: 15px;
      flex-wrap: wrap;
    }
    a {
      padding: 12px 25px;
      color: #fff;
      text-decoration: none;
      border-radius: 8px;
      font-weight: bold;
      transition: background 0.3s ease, transform 0.2s ease;
    }
    a:hover {
      transform: translateY(-2px);
    }
    .back {
      background: #dc3545;
    }
    .back:hover {
      background: #c82333;
    }
    .retry {
      background: #28a745;
    }
    .retry:hover {
      background: #218838;
    }

    /* Animations */
    @keyframes pop {
      to { transform: scale(1); }
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes shake {
      0% { transform: scale(1) translateX(0); }
      25% { transform: scale(1) translateX(-5px); }
      50% { transform: scale(1) translateX(5px); }
      75% { transform: scale(1) translateX(-5px); }
      100% { transform: scale(1) translateX(0); }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="icon">❌</div>
    <h1>Payment Canceled</h1>
    <p>Your payment was canceled. Please try again if this was a mistake.</p>
    <div class="buttons">
      <a href="cart.php" class="back">Go Back to Cart</a>
      <a href="checkout.php" class="retry">Retry Payment</a>
    </div>
  </div>
</body>
</html>
