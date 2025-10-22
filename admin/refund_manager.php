<?php
require_once __DIR__ . '/../config.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit;
}

// Get admin details for display
$first_name = $_SESSION['first_name'] ?? 'Admin';
$last_name = $_SESSION['last_name'] ?? '';

// Database connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle refund request actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'] ?? '';
    $action = $_POST['action'];
    $admin_comments = $_POST['admin_comments'] ?? '';
    $admin_id = $_SESSION['admin_id'];
    
    if ($action === 'approve' || $action === 'deny') {
        $new_status = ($action === 'approve') ? 'approved' : 'denied';
        $booking_status = ($action === 'approve') ? 'refunded' : 'refund_denied';
        
        // If approving, process Paymongo refund first
        $paymongo_success = true;
        $paymongo_error = '';
        
        if ($action === 'approve') {
            // Get refund request details
            $refund_sql = "SELECT rr.*, o.price, o.quantity FROM refund_requests rr 
                          JOIN orders o ON rr.booking_id = o.id WHERE rr.id = ?";
            $refund_stmt = $conn->prepare($refund_sql);
            $refund_stmt->bind_param("i", $request_id);
            $refund_stmt->execute();
            $refund_result = $refund_stmt->get_result();
            $refund_data = $refund_result->fetch_assoc();
            $refund_stmt->close();
            
            if ($refund_data) {
                $payment_id = $refund_data['payment_ref'];
                $amount = $refund_data['price'] * $refund_data['quantity'];
                
                // Process Paymongo refund
                $paymongo_result = processPaymongoRefund($payment_id, $amount);
                $paymongo_success = $paymongo_result['success'];
                $paymongo_error = $paymongo_result['error'] ?? '';
                
                if (!$paymongo_success) {
                    $admin_comments .= " (Paymongo refund failed: " . $paymongo_error . " - Manual processing required)";
                    $booking_status = 'refund_approved_pending'; // Custom status for manual processing
                }
            }
        }
        
        // Update refund request
        $update_sql = "UPDATE refund_requests SET status = ?, admin_comments = ?, processed_by = ?, processed_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssii", $new_status, $admin_comments, $admin_id, $request_id);
        
        if ($update_stmt->execute()) {
            // Update booking status
            $booking_sql = "UPDATE orders SET status = ? WHERE id = (SELECT booking_id FROM refund_requests WHERE id = ?)";
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param("si", $booking_status, $request_id);
            $booking_stmt->execute();
            $booking_stmt->close();
            
            if ($action === 'approve') {
                if ($paymongo_success) {
                    $success_message = "Refund request approved and processed successfully via Paymongo.";
                } else {
                    $success_message = "Refund request approved. Manual Paymongo processing required due to: " . $paymongo_error;
                }
            } else {
                $success_message = "Refund request denied successfully.";
            }
        } else {
            $error_message = "Failed to update refund request.";
        }
        
        $update_stmt->close();
    }
    
    // Handle manual processing
    elseif ($action === 'manual_process') {
        $manual_action = $_POST['manual_action'] ?? '';
        $manual_comments = $_POST['manual_comments'] ?? '';
        
        if ($manual_action === 'completed') {
            // Update to completed refund
            $update_sql = "UPDATE orders SET status = 'refunded' WHERE id = (SELECT booking_id FROM refund_requests WHERE id = ?)";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $request_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Update admin comments
            $comment_sql = "UPDATE refund_requests SET admin_comments = CONCAT(COALESCE(admin_comments, ''), ' | Manual processing completed: ', ?) WHERE id = ?";
            $comment_stmt = $conn->prepare($comment_sql);
            $comment_stmt->bind_param("si", $manual_comments, $request_id);
            $comment_stmt->execute();
            $comment_stmt->close();
            
            $success_message = "Manual refund processing marked as completed.";
        } 
        elseif ($manual_action === 'failed') {
            // Update to failed refund
            $update_sql = "UPDATE orders SET status = 'refund_failed' WHERE id = (SELECT booking_id FROM refund_requests WHERE id = ?)";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $request_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Update admin comments
            $comment_sql = "UPDATE refund_requests SET admin_comments = CONCAT(COALESCE(admin_comments, ''), ' | Manual processing failed: ', ?) WHERE id = ?";
            $comment_stmt = $conn->prepare($comment_sql);
            $comment_stmt->bind_param("si", $manual_comments, $request_id);
            $comment_stmt->execute();
            $comment_stmt->close();
            
            $error_message = "Manual refund processing marked as failed: " . $manual_comments;
        }
    }
    
    // Handle retry Paymongo
    elseif ($action === 'retry_paymongo') {
        // Get refund request details
        $refund_sql = "SELECT rr.*, o.price, o.quantity FROM refund_requests rr 
                      JOIN orders o ON rr.booking_id = o.id WHERE rr.id = ?";
        $refund_stmt = $conn->prepare($refund_sql);
        $refund_stmt->bind_param("i", $request_id);
        $refund_stmt->execute();
        $refund_result = $refund_stmt->get_result();
        $refund_data = $refund_result->fetch_assoc();
        $refund_stmt->close();
        
        if ($refund_data) {
            $payment_id = $refund_data['payment_ref'];
            $amount = $refund_data['price'] * $refund_data['quantity'];
            
            // Retry Paymongo refund
            $paymongo_result = processPaymongoRefund($payment_id, $amount);
            
            if ($paymongo_result['success']) {
                // Update to successful refund
                $update_sql = "UPDATE orders SET status = 'refunded' WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $refund_data['booking_id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $success_message = "Paymongo refund retry successful!";
            } else {
                $error_message = "Paymongo refund retry failed: " . ($paymongo_result['error'] ?? 'Unknown error');
            }
        }
    }
}

// Paymongo refund function
function processPaymongoRefund($payment_id, $amount) {
    $secret_key = PAYMONGO_SECRET_KEY; // PayMongo secret key from env
    
    try {
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
            curl_close($ch);
            return ['success' => false, 'error' => 'CURL Error: ' . curl_error($ch)];
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300) {
            // Successful refund
            return ['success' => true, 'data' => $decoded];
        } else {
            // Failed refund
            $error_msg = $decoded['errors'][0]['detail'] ?? 'Unknown Paymongo error';
            return ['success' => false, 'error' => $error_msg];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// Check if refund_requests table exists
$table_check = $conn->query("SHOW TABLES LIKE 'refund_requests'");
if ($table_check->num_rows == 0) {
    // Create the table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `refund_requests` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `booking_id` int(11) NOT NULL,
      `payment_ref` varchar(255) DEFAULT NULL,
      `reason` text NOT NULL,
      `additional_comments` text DEFAULT NULL,
      `status` enum('pending','approved','denied') DEFAULT 'pending',
      `admin_comments` text DEFAULT NULL,
      `processed_by` int(11) DEFAULT NULL,
      `processed_at` timestamp NULL DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `booking_id` (`booking_id`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_table_sql);
}

// Fetch refund requests with user and booking details
$sql = "SELECT rr.*, u.firstname, u.lastname, u.email, o.price, o.quantity, 
               p.name as park_name, pt.ticket_name
        FROM refund_requests rr
        JOIN user u ON rr.user_id = u.id
        JOIN orders o ON rr.booking_id = o.id
        LEFT JOIN parks p ON o.park_id = p.id
        LEFT JOIN park_tickets pt ON o.ticket_id = pt.id
        ORDER BY rr.created_at DESC";

$result = $conn->query($sql);
if (!$result) {
    error_log("SQL Error: " . $conn->error);
    $refund_requests = [];
} else {
    $refund_requests = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Requests Management - AuroraBox Admin</title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="CSS/sidebar.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .sidebar.collapsed + .main-content {
            margin-left: 60px;
        }
        .hamburger {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #333;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Toggle Button for Sidebar -->
    <button class="hamburger" id="sidebarToggle">
        <span></span>
        <span></span>
        <span></span>
    </button>

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

<div class="main-content">
                    <h2 class="mb-4">Refund Requests Management</h2>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    
                    <!-- Refund Requests Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">All Refund Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($refund_requests)): ?>
                                <p class="text-muted">No refund requests found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Customer</th>
                                                <th>Booking Details</th>
                                                <th>Amount</th>
                                                <th>Reason</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($refund_requests as $request): ?>
                                                <tr>
                                                    <td>#<?= $request['id'] ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($request['firstname'] . ' ' . $request['lastname']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($request['email']) ?></small>
                                                    </td>
                                                    <td>
                                                        <strong>Order #<?= $request['booking_id'] ?></strong><br>
                                                        <small><?= htmlspecialchars($request['ticket_name'] ?? 'Park Ticket') ?></small><br>
                                                        <small class="text-muted">at <?= htmlspecialchars($request['park_name']) ?></small>
                                                    </td>
                                                    <td>
                                                        <strong>₱<?= number_format($request['price'] * $request['quantity']) ?></strong><br>
                                                        <small>Qty: <?= $request['quantity'] ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="text-primary"><?= htmlspecialchars($request['reason']) ?></span>
                                                        <?php if (!empty($request['additional_comments'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($request['additional_comments']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = match($request['status']) {
                                                            'pending' => 'bg-warning',
                                                            'approved' => 'bg-success',
                                                            'denied' => 'bg-danger',
                                                            default => 'bg-secondary'
                                                        };
                                                        ?>
                                                        <span class="badge <?= $badge_class ?>"><?= ucfirst($request['status']) ?></span>
                                                        <?php
                                                        // Check booking status for additional info
                                                        $booking_status_sql = "SELECT status FROM orders WHERE id = ?";
                                                        $booking_status_stmt = $conn->prepare($booking_status_sql);
                                                        $booking_status_stmt->bind_param("i", $request['booking_id']);
                                                        $booking_status_stmt->execute();
                                                        $booking_status_result = $booking_status_stmt->get_result();
                                                        $booking_status = $booking_status_result->fetch_assoc()['status'] ?? '';
                                                        $booking_status_stmt->close();
                                                        
                                                        if ($booking_status === 'refund_approved_pending'): ?>
                                                            <br><small class="text-warning">⚠️ Manual Paymongo Processing Required</small>
                                                        <?php elseif ($booking_status === 'refunded' && $request['status'] === 'approved'): ?>
                                                            <br><small class="text-success">✅ Paymongo Refund Processed</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= date('M j, Y', strtotime($request['created_at'])) ?><br>
                                                        <small class="text-muted"><?= date('g:i A', strtotime($request['created_at'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($request['status'] === 'pending'): ?>
                                                            <button class="btn btn-success btn-sm" 
                                                                    onclick="showActionModal(<?= $request['id'] ?>, 'approve', 'Order #<?= $request['booking_id'] ?>')">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <button class="btn btn-danger btn-sm" 
                                                                    onclick="showActionModal(<?= $request['id'] ?>, 'deny', 'Order #<?= $request['booking_id'] ?>')">
                                                                <i class="fas fa-times"></i> Deny
                                                            </button>
                                                        <?php elseif ($request['status'] === 'approved'): ?>
                                                            <?php
                                                            // Check if manual processing is needed
                                                            $booking_status_sql = "SELECT status FROM orders WHERE id = ?";
                                                            $booking_status_stmt = $conn->prepare($booking_status_sql);
                                                            $booking_status_stmt->bind_param("i", $request['booking_id']);
                                                            $booking_status_stmt->execute();
                                                            $booking_status_result = $booking_status_stmt->get_result();
                                                            $current_booking_status = $booking_status_result->fetch_assoc()['status'] ?? '';
                                                            $booking_status_stmt->close();
                                                            
                                                            if ($current_booking_status === 'refund_approved_pending'): ?>
                                                                <button class="btn btn-warning btn-sm" 
                                                                        onclick="showManualProcessModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['payment_ref']) ?>', <?= $request['price'] * $request['quantity'] ?>)">
                                                                    <i class="fas fa-cog"></i> Process Manual Refund
                                                                </button>
                                                                <br>
                                                                <button class="btn btn-primary btn-sm mt-1" 
                                                                        onclick="retryPaymongoRefund(<?= $request['id'] ?>)">
                                                                    <i class="fas fa-redo"></i> Retry Paymongo
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-success">✅ Processed</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($request['admin_comments'])): ?>
                                                                <br><small><?= htmlspecialchars($request['admin_comments']) ?></small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Processed</span>
                                                            <?php if (!empty($request['admin_comments'])): ?>
                                                                <br><small><?= htmlspecialchars($request['admin_comments']) ?></small>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
        </div>
    </div>
    </div>
    
    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p id="actionModalText"></p>
                        <div class="mb-3">
                            <label for="admin_comments" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" id="admin_comments" name="admin_comments" rows="3" 
                                      placeholder="Add any comments or notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" id="request_id" name="request_id">
                        <input type="hidden" id="action" name="action">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="actionButton">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manual Processing Modal -->
    <div class="modal fade" id="manualProcessModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manual Refund Processing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Manual Processing Required</strong><br>
                            The automatic Paymongo refund failed. Please process this refund manually.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Refund Details:</h6>
                                <p><strong>Payment ID:</strong> <span id="manualPaymentId"></span></p>
                                <p><strong>Amount:</strong> ₱<span id="manualAmount"></span></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Manual Processing Options:</h6>
                                <ol>
                                    <li>Go to Paymongo Dashboard</li>
                                    <li>Find payment by ID above</li>
                                    <li>Process refund manually</li>
                                    <li>Mark as completed below</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Processing Status</label>
                            <select class="form-select" name="manual_action" required>
                                <option value="">Select status...</option>
                                <option value="completed">✅ Manual refund completed</option>
                                <option value="failed">❌ Unable to process refund</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="manual_comments" class="form-label">Processing Notes</label>
                            <textarea class="form-control" name="manual_comments" rows="3" 
                                      placeholder="Add notes about the manual processing..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" id="manual_request_id" name="request_id">
                        <input type="hidden" name="action" value="manual_process">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementsByClassName('sidebar')[0];
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        function showActionModal(requestId, action, bookingRef) {
            const modal = new bootstrap.Modal(document.getElementById('actionModal'));
            const title = action === 'approve' ? 'Approve Refund Request' : 'Deny Refund Request';
            const text = action === 'approve' 
                ? `Are you sure you want to approve the refund request for ${bookingRef}?`
                : `Are you sure you want to deny the refund request for ${bookingRef}?`;
            const buttonClass = action === 'approve' ? 'btn-success' : 'btn-danger';
            const buttonText = action === 'approve' ? 'Approve' : 'Deny';
            
            document.getElementById('actionModalTitle').textContent = title;
            document.getElementById('actionModalText').textContent = text;
            document.getElementById('request_id').value = requestId;
            document.getElementById('action').value = action;
            document.getElementById('actionButton').className = `btn ${buttonClass}`;
            document.getElementById('actionButton').textContent = buttonText;
            
            modal.show();
        }
        
        function showManualProcessModal(requestId, paymentId, amount) {
            const modal = new bootstrap.Modal(document.getElementById('manualProcessModal'));
            document.getElementById('manual_request_id').value = requestId;
            document.getElementById('manualPaymentId').textContent = paymentId;
            document.getElementById('manualAmount').textContent = amount.toLocaleString();
            modal.show();
        }
        
        function retryPaymongoRefund(requestId) {
            if (confirm('Retry automatic Paymongo refund processing?')) {
                // Create a form to submit retry request
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="retry_paymongo">
                    <input type="hidden" name="request_id" value="${requestId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>