<?php
// Only start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/../config.php';
$mysqli = require __DIR__ . '/../database.php';
if ($mysqli->connect_errno) {
    die("DB Connection failed: " . $mysqli->connect_error);
}

// Fetch active promos that are not expired
$result = $mysqli->query("
    SELECT * FROM promos 
    WHERE status='active' 
      AND expiration_date >= CURDATE() 
    ORDER BY id DESC
");
?>

<div class="why-aurorabox">
  <h2>PROMO CODES HERE</h2>
  <div class="wrap">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($promo = $result->fetch_assoc()): ?>
        <div class="promo-ticket">
          <div class="ticket-left">
            <h3>
              <?= htmlspecialchars($promo['code']) ?> 
              - <?= ucfirst($promo['type']) ?> discount
            </h3>
            <div class="promo-code">
              Promo code: <strong><?= htmlspecialchars($promo['code']) ?></strong>
            </div>
          </div>
          <div class="ticket-right">
            <div class="usd-value">
              <?= $promo['type'] == 'percentage' ? $promo['value'].'% Off' : '₱'.number_format($promo['value'], 2) . ' Off' ?>
            </div>
            <div class="min-spend">
              Valid until: <?= $promo['expiration_date'] ?>
            </div>
            <!-- Redeem Button - Show different states based on login -->
            <?php if (isset($_SESSION['user_id'])): ?>
              <button 
                class="redeem-btn" 
                type="button" 
                onclick="redeemPromo('<?= htmlspecialchars($promo['code']) ?>')"
              >
                Redeem
              </button>
            <?php else: ?>
              <button 
                class="redeem-btn login-required" 
                type="button" 
                onclick="showLoginPrompt()"
              >
                Login to Redeem
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No active promos available at the moment.</p>
    <?php endif; ?>
  </div>
</div>

<script>
// Toast Notification System
class ToastNotification {
    constructor() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.id = 'toastContainer';
            document.body.appendChild(this.container);
        }
    }

    show(options = {}) {
        const {
            type = 'promo',
            title = '',
            message = '',
            duration = 5000,
            showProgress = true
        } = options;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            promo: '🎉'
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.promo}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${title}</div>` : ''}
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            ${showProgress ? '<div class="toast-progress"><div class="toast-progress-bar"></div></div>' : ''}
        `;

        this.container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);

        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        return toast;
    }

    promo(message, title = 'Promo Redeemed! 🎉') {
        return this.show({ type: 'promo', title, message });
    }

    error(message, title = 'Error!') {
        return this.show({ type: 'error', title, message });
    }

    warning(message, title = 'Login Required') {
        return this.show({ type: 'warning', title, message });
    }
}

// Initialize Toast
const Toast = new ToastNotification();

function redeemPromo(code) {
    console.log('Attempting to redeem promo:', code);
    
    fetch('redeem_promo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'code=' + encodeURIComponent(code)
    })
    .then(res => {
        console.log('Response status:', res.status);
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            Toast.promo(
                `Promo code '${code}' successfully added to your account! Use it during checkout.`,
                "Promo Redeemed! 🎉"
            );
        } else {
            Toast.error(
                data.message || "Failed to redeem promo code. Please try again.",
                "Redemption Failed"
            );
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        Toast.error(
            "Network error occurred. Please check your connection and try again.",
            "Connection Error"
        );
    });
}

function showLoginPrompt() {
    Toast.warning(
        "Please log in to redeem exclusive promo codes and enjoy amazing discounts!",
        "Login Required"
    );
    
    setTimeout(() => {
        if (typeof bootstrap !== 'undefined' && document.getElementById('loginModal')) {
            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        } else {
            window.location.href = "login.php";
        }
    }, 2000);
}
</script>