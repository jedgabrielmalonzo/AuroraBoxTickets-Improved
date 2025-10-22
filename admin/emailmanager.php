<?php
require_once __DIR__ . '/../config.php';
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Centralized connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$message = "";

// Send Email Announcement
if (isset($_POST['send_email'])) {
    $subject = $_POST['subject'];
    $body = $_POST['message'];
    $recipientType = $_POST['recipientType'];

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
  $mail->Host = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;
  $mail->Password = SMTP_PASS;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = SMTP_PORT;

  $mail->setFrom(SMTP_USER, 'AuroraBox Announcements');
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;

        // Styled HTML email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background:#f4f4f4; padding:20px; }
                .container { max-width:600px; margin:0 auto; background:#fff; border-radius:10px; overflow:hidden; }
                .header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; padding:20px; text-align:center; }
                .content { padding:20px; line-height:1.6; color:#333; }
                .footer { background:#f8f9fa; text-align:center; padding:15px; font-size:12px; color:#666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>📢 AuroraBox Announcement</h2>
                </div>
                <div class='content'>
                    <h3 style='color:#764ba2;margin-top:0;'>$subject</h3>
                    <p>" . nl2br(htmlspecialchars($body)) . "</p>
                </div>
                <div class='footer'>
                    <p>This is an automated announcement. Please do not reply.</p>
                    <p>© " . date("Y") . " AuroraBox. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $userIds = [];

        if ($recipientType === 'all') {
            $users = $conn->query("SELECT id, email FROM user");
            while ($row = $users->fetch_assoc()) {
                $mail->addBCC($row['email']);
                $userIds[] = $row['id'];
            }
        } elseif ($recipientType === 'specified') {
            $specifiedEmails = explode(',', $_POST['userEmails']);
            foreach ($specifiedEmails as $email) {
                $trimmedEmail = trim($email);
                if (!empty($trimmedEmail)) {
                    $mail->addBCC($trimmedEmail);

                    $stmtFind = $conn->prepare("SELECT id FROM user WHERE email = ?");
                    $stmtFind->bind_param("s", $trimmedEmail);
                    $stmtFind->execute();
                    $res = $stmtFind->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $userIds[] = $row['id'];
                    }
                }
            }
        }

        $mail->send();

        // Save announcement with user_ids
        $userIdsStr = implode(',', $userIds);
        $stmt = $conn->prepare("INSERT INTO email_announcements (subject, message, user_ids) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $subject, $body, $userIdsStr);
        $stmt->execute();

        $message = "<div class='alert alert-success'>Announcement sent successfully!</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Mailer Error: {$mail->ErrorInfo}</div>";
    }
}

// Send Direct Message to User
if (isset($_POST['send_direct_message'])) {
  $user_id = $_POST['user_id'];
  $subject = $_POST['dm_subject'];
  $body = $_POST['dm_body'];
  $sender_id = 1; // Admin ID

  if ($subject !== '' && $body !== '') {
    $stmt = $conn->prepare("
      INSERT INTO notifications (sender_id, recipient_id, type, title, content, status, timestamp)
      VALUES (?, ?, 'direct', ?, ?, 'unread', NOW())
    ");
    $stmt->bind_param("iiss", $sender_id, $user_id, $subject, $body);
    if ($stmt->execute()) {
      // Store a flag in session to show success after redirect
      $_SESSION['dm_success'] = true;
    } else {
      $_SESSION['dm_error'] = $stmt->error;
    }
    $stmt->close();
  }
  // Redirect to clear POST data
  header("Location: emailmanager.php#messages");
  exit();
}

// Fetch data
$sql = "SELECT f.id, f.feedback, f.created_at, 
               u.firstname, u.lastname, u.id as user_id
        FROM feedback f
        JOIN user u ON f.user_id = u.id
        ORDER BY f.created_at DESC";

$feedback = $conn->query($sql);
$customerReplies = $conn->query("
    SELECT cr.id, u.firstname, u.lastname, cr.gmail, cr.reply, cr.created_at, u.id as user_id
    FROM customer_replies cr
    JOIN user u ON cr.user_id = u.id
    ORDER BY cr.created_at DESC
");

// Fetch announcement history
$history = $conn->query("SELECT * FROM email_announcements ORDER BY sent_at DESC");

// Fetch messages for admin
$adminMessages = $conn->query("
    SELECT m.*, u.firstname, u.lastname, u.email 
    FROM messages m 
    JOIN user u ON m.sender_id = u.id 
    WHERE m.receiver_id = 1 
    ORDER BY m.created_at DESC
");

// Fetch all users for direct messaging
$users = $conn->query("SELECT id, firstname, lastname, email FROM user ORDER BY firstname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Manager</title>
  <link rel="icon" type="image/x-icon" href="../images/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <link href="CSS/sidebar.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  
  <style>
:root {
  --primary: #684D8F;
  --primary-dark: #563E77;
  --accent: #684D8F;
  --bg: #f7f7f8;
  --card-bg: #fff;
  --text: #333;
  --muted: #666;
}

body {
  font-family: 'Poppins', sans-serif;
  background: var(--bg);
  color: var(--text);
  margin: 0;
}

.card {
  background: var(--card-bg);
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
  margin-bottom: 2rem;
}

form#emailForm, form#directMessageForm {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

form label {
  font-weight: 500;
  font-size: 0.95rem;
  margin-bottom: 0.3rem;
  color: var(--muted);
}

form input.form-control,
form textarea.form-control,
form select.form-control {
  width: 100%;
  padding: 0.75rem;
  font-size: 0.95rem;
  border-radius: 8px;
  border: 1px solid #ddd;
  background: #fafafa;
  transition: all 0.2s;
}

form input.form-control:focus,
form textarea.form-control:focus,
form select.form-control:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 2px rgba(104,77,143,0.2);
  background: #fff;
}

form textarea.form-control {
  resize: vertical;
  min-height: 120px;
}

form button.btn {
  background: var(--accent);
  color: white;
  font-weight: 600;
  padding: 0.9rem;
  font-size: 1rem;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

form button.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(104,77,143,0.3);
}

table.table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  margin-top: 1rem;
}

table.table thead {
  background: var(--primary);
  color: white;
}

table.table thead th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  font-size: 14px;
}

table.table tbody tr {
  border-bottom: 1px solid #eee;
  transition: background 0.2s;
}

table.table tbody tr:nth-child(even) {
  background: #fafafa;
}

table.table tbody tr:hover {
  background: #f1ecf8;
}

table.table tbody td {
  padding: 12px 16px;
  font-size: 14px;
  color: #333;
  vertical-align: middle;
}

.hidden {
  display: none;
}

.nav-tabs .nav-link.active {
  background-color: var(--primary);
  color: white !important;
  border-color: var(--primary);
  font-weight: 600;
}

.nav-tabs .nav-link {
  color: var(--primary);
  font-weight: 500;
}

.nav-tabs .nav-link:hover {
  color: var(--primary-dark);
}

.alert-info {
  background-color: #e8e2f0;
  color: var(--primary);
  border-radius: 8px;
  padding: 1rem;
  margin: 0;
  font-weight: 500;
  text-align: center;
}

.message-bubble {
  max-width: 70%;
  padding: 12px 16px;
  border-radius: 18px;
  margin: 8px 0;
  word-wrap: break-word;
}

.message-sent {
  background: #007bff;
  color: white;
  margin-left: auto;
  border-bottom-right-radius: 4px;
}

.message-received {
  background: #e9ecef;
  color: #333;
  margin-right: auto;
  border-bottom-left-radius: 4px;
}

.message-time {
  font-size: 0.75rem;
  opacity: 0.7;
  margin-top: 4px;
}

.reply-btn {
  background: var(--primary);
  color: white;
  border: none;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.reply-btn:hover {
  background: var(--primary-dark);
}
</style>

</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <img src="/images/logoadminwhite.png" class="logoadmin">

    <!-- Dashboard -->
    <a href="dashboard.php">Dashboard</a>

    <!-- Park & Event Management -->
    <p class="sidebar-p">PARK & EVENT MANAGEMENT</p>
    <a href="parkmanager.php">Manage Parks</a>
    <a href="ticketmanager.php">Tickets & Pricing</a>
    <a href="schedulemanager.php">Schedules</a>

    <!-- Content Management -->
    <p class="sidebar-p">CONTENT MANAGEMENT</p>
    <a href="banners.php">Edit Contents</a>
    <a href="promomanager.php">Promo</a>

    <!-- Communication -->
    <p class="sidebar-p">COMMUNICATION</p>
    <a href="emailmanager.php">Inbox</a>
    <a href="refund_manager.php">Refund Request</a>
    <a href="usernotifications.php">User Notifications</a>

    <!-- Analytics -->
    <p class="sidebar-p">ANALYTICS</p>
    <a href="salesanalytics.php">Sales Reports</a>
    <a href="useranalytics.php">*User Statistics</a>

    <!-- Reports -->
    <p class="sidebar-p">REPORTS</p>
    <a href="transactions.php">Transaction History</a>
    <a href="viewusers.php">Registered Users</a>
    <a href="activitylog.php">*Activity Logs</a>

    <!-- Logout -->
    <a href="logoutadmin.php" class="sidebar-logout">Log Out</a>
</div>


<div class="main-content p-4">
  <?php
    if (isset($_SESSION['dm_success'])) {
      echo "<div class='alert alert-success'>Direct message sent successfully!</div>";
      unset($_SESSION['dm_success']);
    }
    if (isset($_SESSION['dm_error'])) {
      echo "<div class='alert alert-danger'>Failed to send message: " . htmlspecialchars($_SESSION['dm_error']) . "</div>";
      unset($_SESSION['dm_error']);
    }
    if ($message) echo $message;
  ?>
  <h2>Email & Message Manager</h2>

  <!-- Tabs for different sections -->
  <ul class="nav nav-tabs mb-4" id="managerTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements" type="button">Announcements</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button">Direct Messages</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="feedback-tab" data-bs-toggle="tab" data-bs-target="#feedback" type="button">Feedback</button>
    </li>
  </ul>

  <div class="tab-content">
    <!-- Announcements Tab -->
    <div class="tab-pane fade show active" id="announcements" role="tabpanel">
      <!-- send email form -->
      <div class="card p-3 mb-4">
        <h5>Send Announcement</h5>
        <form method="POST" id="emailForm">
          <div class="mb-2">
            <label>Subject</label>
            <input type="text" name="subject" class="form-control" required>
          </div>
          <div class="mb-2">
            <label>Message</label>
            <textarea name="message" class="form-control" rows="5" required></textarea>
          </div>

          <div class="mb-2">
            <label for="recipientType">Send To:</label>
            <select id="recipientType" name="recipientType" onchange="toggleUserInput()" class="form-control">
              <option value="all">Send to All Users</option>
              <option value="specified">Send to Specified Users</option>
            </select>
          </div>

          <div id="specifiedUsers" class="mb-2 hidden">
            <label for="userEmails">Enter User Emails (separated by commas):</label>
            <input type="text" name="userEmails" class="form-control">
          </div>

          <button type="submit" name="send_email" class="btn btn-primary w-100">Send Announcement</button>
        </form>
      </div>

      <!-- announcement history -->
      <div class="card p-3">
        <h5>Announcement History</h5>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Subject</th>
              <th>Message</th>
              <th>Recipients (user_id)</th>
              <th>Sent At</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $history->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['subject'] ?? '') ?></td>
              <td><?= nl2br(htmlspecialchars($row['message'] ?? '')) ?></td>
              <td>
                <?php 
                  if (!empty($row['user_ids'])) {
                      $ids = explode(',', $row['user_ids']);
                      $chunks = array_chunk($ids, 5);
                      $lines = array_map(fn($chunk) => implode(', ', $chunk), $chunks);
                      echo implode('<br>', $lines);
                  }
                ?>
              </td>
              <td><?= $row['sent_at'] ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Direct Messages Tab -->
    <div class="tab-pane fade" id="messages" role="tabpanel">
      <!-- Send Direct Message Form -->
      <div class="card p-3 mb-4">
        <h5>Send Direct Message to User</h5>
        <form method="POST" id="directMessageForm">
          <div class="mb-2">
            <label>Select User:</label>
            <select name="user_id" class="form-control" required>
              <option value="">Select a user...</option>
              <?php while($user = $users->fetch_assoc()): ?>
                <option value="<?= $user['id'] ?>">
                  <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?> (<?= htmlspecialchars($user['email']) ?>)
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-2">
            <label>Subject</label>
            <input type="text" name="dm_subject" class="form-control" required>
          </div>
          <div class="mb-2">
            <label>Message</label>
            <textarea name="dm_body" class="form-control" rows="5" required></textarea>
          </div>
          <button type="submit" name="send_direct_message" class="btn btn-primary w-100">Send Message</button>
        </form>
      </div>

      <!-- Received Messages -->
      <div class="card p-3">
        <h5>Messages from Users</h5>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>From</th>
              <th>Email</th>
              <th>Subject</th>
              <th>Message</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while($msg = $adminMessages->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($msg['firstname'] . ' ' . $msg['lastname']) ?></td>
              <td><?= htmlspecialchars($msg['email']) ?></td>
              <td><?= htmlspecialchars($msg['subject']) ?></td>
              <td><?= nl2br(htmlspecialchars(substr($msg['body'], 0, 100))) ?>...</td>
              <td><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></td>
              <td>
                <button class="reply-btn" onclick="replyToMessage(<?= $msg['sender_id'] ?>, '<?= htmlspecialchars($msg['subject']) ?>')">
                  Reply
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Feedback Tab -->
    <div class="tab-pane fade" id="feedback" role="tabpanel">
      <div class="card p-3">
        <h5>Feedback Section</h5>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="feedbackTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customer" type="button">
              Customer Feedback
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="vendor-tab" data-bs-toggle="tab" data-bs-target="#vendor" type="button">
              Vendor Feedback
            </button>
          </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-3" id="feedbackTabsContent">
          <!-- Customer Feedback -->
          <div class="tab-pane fade show active" id="customer" role="tabpanel">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Feedback</th>
                  <th>Created At</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $feedback->data_seek(0); // Reset pointer
                while($row = $feedback->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['firstname'] . " " . $row['lastname']) ?></td>
                    <td><?= nl2br(htmlspecialchars($row['feedback'])) ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                      <button class="reply-btn" onclick="replyToUser(<?= $row['user_id'] ?>, 'Re: Feedback Response')">
                        Message
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>

          <!-- Vendor Feedback -->
          <div class="tab-pane fade" id="vendor" role="tabpanel">
            <div class="alert alert-info text-center p-4">
              🚧 Vendor feedback coming soon...
            </div>
          </div>
        </div>
      </div>

      <!-- Customer Replies -->
      <div class="card p-3 mt-4">
        <h5>Customer Replies</h5>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Reply</th>
              <th>Created At</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $customerReplies->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['firstname'] . " " . $row['lastname']) ?></td>
                <td><?= htmlspecialchars($row['gmail']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['reply'])) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td>
                  <button class="reply-btn" onclick="replyToUser(<?= $row['user_id'] ?>, 'Re: Your Inquiry')">
                    Reply
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function toggleUserInput() {
  const recipientType = document.getElementById('recipientType').value;
  const specifiedUsers = document.getElementById('specifiedUsers');
  if (recipientType === 'specified') {
    specifiedUsers.classList.remove('hidden');
  } else {
    specifiedUsers.classList.add('hidden');
  }
}

function replyToUser(userId, subject) {
  // Switch to messages tab
  document.getElementById('messages-tab').click();
  
  // Set the user and subject in the form
  document.querySelector('select[name="user_id"]').value = userId;
  document.querySelector('input[name="dm_subject"]').value = subject;
  
  // Focus on the message body
  document.querySelector('textarea[name="dm_body"]').focus();
}

function replyToMessage(userId, subject) {
  replyToUser(userId, 'Re: ' + subject);
}
</script>

<style>
.hidden {
  display: none;
}
</style>
</body>
</html>
<?php $conn->close(); ?>