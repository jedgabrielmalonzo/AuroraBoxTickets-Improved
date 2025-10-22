<?php
session_start();
require 'gClientSetup.php';
$mysqli = require __DIR__ . '/database.php';
$conn = $mysqli;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Mark as read if opening a message
if (isset($_GET['open']) && is_numeric($_GET['open'])) {
    $msg_id = (int)$_GET['open'];
    $stmt = $conn->prepare("UPDATE notifications SET status='read' WHERE id=? AND (recipient_id=? OR recipient_id IS NULL)");
    $stmt->bind_param('ii', $msg_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch all messages for this user (direct or announcement)
$sql = "
    SELECT id, title, content, 'notification' AS source, type, timestamp, status
    FROM notifications
    WHERE (recipient_id = ? OR recipient_id IS NULL)

    UNION ALL

    SELECT id, subject AS title, message AS content, 'announcement' AS source, 'announcement' AS type, sent_at AS timestamp, 'read' AS status
    FROM email_announcements
    ORDER BY timestamp DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Count unread
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE (recipient_id = ? OR recipient_id IS NULL) AND status = 'unread'
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($unread_count);
$stmt->fetch();
$stmt->close();

// Try notifications first
$stmt = $conn->prepare("SELECT id, title, content, type, timestamp, status FROM notifications WHERE id=? AND (recipient_id=? OR recipient_id IS NULL)");
$stmt->bind_param('ii', $msg_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$opened_msg = $result->fetch_assoc();
$stmt->close();

// If not found, try email_announcements
if (!$opened_msg) {
    $stmt = $conn->prepare("SELECT id, subject AS title, message AS content, 'announcement' AS type, sent_at AS timestamp, 'read' AS status FROM email_announcements WHERE id=?");
    $stmt->bind_param('i', $msg_id);
    $stmt->execute();
    $opened_msg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inbox - AuroraBox</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/inbox.css">
    <link rel="stylesheet" href="css/cart.css">



</head>
<body>
<header>

    
    <!-------NAVBAR SECTION------>
    <?php include 'navbar/navbar.php'; ?>
</header>


         <!-- Cart Header Top -->
  <div class="cart-top">
    <h2 class="cart-title">
      <span class="cart-icon">📩</span> Inbox
    </h2>
  </div>


<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <span class="badge bg-danger">Unread: <?= $unread_count ?></span>
    </div>
    <div class="row g-4">
        <!-- LEFT SIDE: Inbox list -->
            <div class="col-md-4 inbox-list-panel p-3" style="height:80vh; overflow-y:auto;">
            <?php if (empty($messages)): ?>
                <div class="text-muted text-center p-3">No messages yet</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $isUnread = $msg['status'] === 'unread';
                    $typeTag = $msg['type'] === 'announcement'
                        ? '<span class="badge badge-announcement ms-2">Announcement</span>'
                        : '<span class="badge badge-direct ms-2">Direct</span>';
                    $avatar = $msg['type'] === 'announcement'
                        ? '<span class="inbox-avatar">📢</span>'
                        : '<span class="inbox-avatar">U</span>';
                    ?>
                    <div class="inbox-item <?= $isUnread ? 'unread' : '' ?> p-2 px-3 border-bottom d-flex align-items-center gap-2"
                         onclick="window.location='inbox.php?open=<?= $msg['id'] ?>'">
                            <?= $avatar ?>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span style="font-weight:600; color:var(--primary);">
                                        <?= htmlspecialchars($msg['title']) ?>
                                    </span>
                                    <?= $typeTag ?>
                                </div>
                                <div class="inbox-preview-text">
                                    <?= htmlspecialchars(substr(strip_tags($msg['content']), 0, 40)) ?>...
                                </div>
                            </div>
                            <span class="inbox-date ms-2">
                                <?= date('M d', strtotime($msg['timestamp'])) ?>
                            </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- RIGHT SIDE: Message detail -->
            <div class="col-md-8 inbox-detail-panel p-4" style="height:80vh; overflow-y:auto;">
                <?php if ($opened_msg): ?>
                    <div>
                        <div class="inbox-detail-title mb-2">
                            <?= htmlspecialchars($opened_msg['title']) ?>
                        </div>
                        <div class="mb-2">
                            <span class="badge <?= $opened_msg['type'] === 'announcement' ? 'badge-announcement' : 'badge-direct' ?>">
                                <?= ucfirst($opened_msg['type']) ?>
                            </span>
                            <span class="ms-2 inbox-date">Sent: <?= date('F j, Y g:i A', strtotime($opened_msg['timestamp'])) ?></span>
                        </div>
                        <div class="inbox-detail-content mb-3">
                            <?= nl2br(htmlspecialchars($opened_msg['content'])) ?>
                        </div>
                        <?php if ($opened_msg['status'] === 'unread'): ?>
                            <span class="badge bg-success">Marked as read</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center mt-5">
                        <i class="fas fa-envelope-open-text fa-2x mb-2" style="color:var(--primary);"></i><br>
                        Select a message to read
                    </div>
                <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'navbar/footer.html'; ?>


<script src="wishlist.js"></script>
<script src="scripts/darkmode.js"></script>
<script src="scripts/hidepass.js"></script>
<script src="scripts/scrolltop.js"></script>
<script src="scripts/loader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
// Session-based navigation handlers
const isLoggedIn = <?= json_encode(isset($_SESSION["user_id"])) ?>;
function handleWishlistClick() {
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        window.location.href = 'wishlist.php';
    }
}
function handleCartClick() {
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        window.location.href = 'cart.php';
    }
}
function handleAccountClick() {
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        window.location.href = 'account.php';
    }
}
function handleAccClick(event) {
    event.preventDefault();
    if (!isLoggedIn) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    } else {
        window.location.href = 'account.php';
    }
}
</script>
</body>
</html>