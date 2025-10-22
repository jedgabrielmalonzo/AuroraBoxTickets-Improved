<?php
require_once __DIR__ . '/config.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$secret_key = PAYMONGO_SECRET_KEY; // PayMongo secret key from env
$payment_id = $_GET['payment_id'] ?? null;

if (!$payment_id) {
    $status = "error";
    $message = "No payment_id provided.";
} else {
    // Connect DB to get amount
  $mysqli = require __DIR__ . "/database.php";
    $stmt = $mysqli->prepare("SELECT amount FROM payments WHERE payment_id=? LIMIT 1");
    $stmt->bind_param("s", $payment_id);
    $stmt->execute();
    $stmt->bind_result($amount);
    if (!$stmt->fetch()) {
        $status = "error";
        $message = "Payment not found.";
    }
    $stmt->close();

    if (!isset($status)) {
        // PayMongo Refund API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/refunds");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode($secret_key . ":"),
            "Content-Type: application/json"
        ]);

        $data = [
            "data" => [
                "attributes" => [
                    "amount" => $amount * 100,   // convert to centavos
                    "payment_id" => $payment_id,
                    "reason" => "requested_by_customer"
                ]
            ]
        ];

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $status = "error";
            $message = "cURL error: " . curl_error($ch);
        }
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['data']['id'])) {
            $status = "success";
            $message = "Refund Successful! Amount ₱" . number_format($amount, 2) . " has been refunded.";

            // Update DB
            $update = $mysqli->prepare("UPDATE payments SET status='refunded' WHERE payment_id=?");
            $update->bind_param("s", $payment_id);
            $update->execute();
            $update->close();

            $update2 = $mysqli->prepare("UPDATE orders SET status='refunded' WHERE payment_ref=?");
            $update2->bind_param("s", $payment_id);
            $update2->execute();
            $update2->close();
        } else {
            $status = "error";
            $message = "Refund Failed: " . ($result['errors'][0]['detail'] ?? "Unknown error");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Refund Status</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, <?= ($status === "success") ? "#4caf50, #2e7d32" : "#dc3545, #8b1c24" ?>);
      height: 100vh;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #333;
      overflow: hidden;
    }

    .container {
      background: #fff;
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
      text-align: center;
      max-width: 500px;
      width: 90%;
      animation: fadeInUp 0.8s ease forwards;
      position: relative;
      z-index: 1;
    }

    h1 {
      font-size: 2rem;
      margin: 20px 0 10px;
      animation: popIn 0.8s ease;
      color: <?= ($status === "success") ? "#28a745" : "#dc3545" ?>;
    }

    p {
      font-size: 1rem;
      color: #555;
      margin-bottom: 20px;
      animation: fadeIn 1s ease forwards;
      animation-delay: 0.5s;
      opacity: 0;
    }

    a {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 25px;
      color: #fff;
      background: <?= ($status === "success") ? "#28a745" : "#dc3545" ?>;
      text-decoration: none;
      border-radius: 30px;
      font-weight: bold;
      transition: transform 0.2s ease, background 0.3s ease;
      animation: fadeIn 1s ease forwards;
      animation-delay: 0.8s;
      opacity: 0;
    }

    a:hover {
      opacity: 0.9;
      transform: translateY(-3px);
    }

    /* Icon Circle */
    .icon {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 5px solid <?= ($status === "success") ? "#28a745" : "#dc3545" ?>;
      animation: scaleUp 0.5s ease forwards;
      margin-bottom: 10px;
    }

    /* Checkmark */
    .icon.success::after {
      content: '';
      width: 25px;
      height: 50px;
      border-right: 5px solid #28a745;
      border-bottom: 5px solid #28a745;
      transform: rotate(45deg);
      opacity: 0;
      animation: drawCheck 0.6s ease forwards;
      animation-delay: 0.4s;
    }

    /* Cross (X) */
    .icon.error::before,
    .icon.error::after {
      content: '';
      position: absolute;
      width: 45px;
      height: 5px;
      background: #dc3545;
      top: 50%;
      left: 50%;
      transform-origin: center;
      opacity: 0;
      animation: fadeInCross 0.6s ease forwards;
      animation-delay: 0.4s;
    }

    .icon.error::before {
      transform: translate(-50%, -50%) rotate(45deg);
    }

    .icon.error::after {
      transform: translate(-50%, -50%) rotate(-45deg);
    }

    /* Animations */
    @keyframes fadeInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @keyframes fadeIn {
      to { opacity: 1; }
    }

    @keyframes popIn {
      0% { transform: scale(0.5); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }

    @keyframes scaleUp {
      from { transform: scale(0); }
      to { transform: scale(1); }
    }

    @keyframes drawCheck {
      from { opacity: 0; transform: rotate(45deg) scale(0.5); }
      to { opacity: 1; transform: rotate(45deg) scale(1); }
    }

    @keyframes fadeInCross {
      to { opacity: 1; }
    }

    /* Confetti Canvas */
    canvas {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }
  </style>
</head>
<body>
  <?php if ($status === "success"): ?>
    <canvas id="confetti"></canvas>
  <?php endif; ?>
  <div class="container">
    <div class="icon <?= ($status === "success") ? "success" : "error" ?>"></div>
    <h1><?= ($status === "success") ? "Refund Successful!" : "Refund Failed" ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="account.php">Back to Account</a>
  </div>

  <?php if ($status === "success"): ?>
  <script>
    // Confetti for success
    const canvas = document.getElementById('confetti');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const pieces = [];
    const numPieces = 150;

    for (let i = 0; i < numPieces; i++) {
      pieces.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height - canvas.height,
        w: 10 + Math.random() * 10,
        h: 10 + Math.random() * 10,
        color: `hsl(${Math.random() * 360}, 70%, 60%)`,
        speed: 2 + Math.random() * 3,
        rotation: Math.random() * 360
      });
    }

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      pieces.forEach(p => {
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rotation * Math.PI / 180);
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
        p.y += p.speed;
        p.rotation += 5;
        if (p.y > canvas.height) {
          p.y = -20;
          p.x = Math.random() * canvas.width;
        }
      });
      requestAnimationFrame(draw);
    }

    draw();

    // Resize handling
    window.addEventListener('resize', () => {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    });
  </script>
  <?php endif; ?>
</body>
</html>
