<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Successful</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #4caf50, #2e7d32);
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
      max-width: 400px;
      width: 90%;
      animation: fadeInUp 0.8s ease forwards;
      position: relative;
      z-index: 1;
    }

    h1 {
      color: #28a745;
      font-size: 2rem;
      margin: 20px 0 10px;
      animation: popIn 0.8s ease;
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
      background: #28a745;
      text-decoration: none;
      border-radius: 30px;
      font-weight: bold;
      transition: transform 0.2s ease, background 0.3s ease;
      animation: fadeIn 1s ease forwards;
      animation-delay: 0.8s;
      opacity: 0;
    }

    a:hover {
      background: #218838;
      transform: translateY(-3px);
    }

    /* Success Check Animation */
    .checkmark {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 5px solid #28a745;
      position: relative;
      animation: scaleUp 0.5s ease forwards;
    }

    .checkmark::after {
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
  <canvas id="confetti"></canvas>
  <div class="container">
    <div class="checkmark"></div>
    <h1>Payment Successful!</h1>
    <p>Thank you for your payment. We’re verifying your order.</p>
    <a href="index.php">Return to Home</a>
  </div>

  <script>
    // Simple Confetti Animation
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
</body>
</html>
