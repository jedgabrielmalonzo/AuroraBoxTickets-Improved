<?php
// DB Connection (centralized)
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
    die('Database connection failed.');
}
$conn = $mysqli;

$message = "";

// Add Ticket
if (isset($_POST['add_ticket'])) {
    $park_id = mysqli_real_escape_string($conn, $_POST['park_id']);
    $ticket_name = mysqli_real_escape_string($conn, $_POST['ticket_name']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $details = mysqli_real_escape_string($conn, $_POST['details']);

    $sql = "INSERT INTO park_tickets (park_id, ticket_name, price, details) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("isds", $park_id, $ticket_name, $price, $details);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>New ticket added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Update Ticket
if (isset($_POST['update_ticket'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $ticket_name = mysqli_real_escape_string($conn, $_POST['ticket_name']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $details = mysqli_real_escape_string($conn, $_POST['details']);

    $sql = "UPDATE park_tickets SET ticket_name=?, price=?, details=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sdsi", $ticket_name, $price, $details, $id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Ticket updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating ticket: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Delete Ticket
if (isset($_POST['delete_ticket'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $deleteTicket = $conn->prepare("DELETE FROM park_tickets WHERE id=?");
    if ($deleteTicket) {
        $deleteTicket->bind_param("i", $id);
        if ($deleteTicket->execute()) {
            $message = "<div class='alert alert-success'>Ticket deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting ticket: " . $deleteTicket->error . "</div>";
        }
        $deleteTicket->close();
    }
}

// Fetch Data with Pagination
$limit = 10; // tickets per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Get total tickets for pagination
$total_result = $conn->query("SELECT COUNT(*) as total FROM park_tickets");
$total_row = $total_result->fetch_assoc();
$total_tickets = $total_row['total'];
$total_pages = ceil($total_tickets / $limit);

// Fetch tickets with limit and offset
$ticket_types = $conn->query("SELECT pt.*, p.name AS park_name 
                              FROM park_tickets pt
                              JOIN parks p ON pt.park_id = p.id
                              ORDER BY p.name, pt.ticket_name
                              LIMIT $limit OFFSET $offset");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">
     <link rel="icon" type="image/x-icon" href="/images/favicon.png">
        
    <style>
        :root {
          --accent: #684D8F;
          --bg: #f7f7f8;
          --card: #fff;
          --text: #333;
          --muted: #666;
        }
        
        body {
          margin: 0;
          background: var(--bg);
          font-family: 'Poppins', sans-serif;
          color: var(--text);
        }

        .ticket-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            min-width: 150px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

    /* Form Section */
    .form-section {
      background: var(--card);
      padding: 2rem;
      border-radius: 0.75rem;
      box-shadow: 0 6px 20px rgba(0,0,0,0.05);
      margin-bottom: 20px;
      animation: fadeIn 0.4s ease-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .form-section h5 {
      margin-bottom: 1.5rem;
      color: var(--accent);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    
    /* Labels */
    .form-label {
      font-weight: 500;
      font-size: 0.9rem;
      margin-bottom: 0.3rem;
      display: block;
      color: var(--muted);
    }
    
    /* Inputs, Selects, Textareas */
    .form-control {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #ddd;
      border-radius: 0.5rem;
      font-size: 0.95rem;
      background: #fafafa;
      transition: 0.2s;
    }
    
    .form-control:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 2px rgba(104,77,143,0.2);
      background: #fff;
    }
    
    textarea.form-control {
      resize: vertical;
    }
    
    /* Buttons */
    .btn-success {
      background: var(--accent) !important;
      border: none;
      border-radius: 0.5rem;
      font-weight: 600;
      font-size: 1rem;
      padding: 0.9rem;
      transition: 0.2s ease;
    }
    
    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(104,77,143,0.3);
    }
    
    /* ------------------ Tickets Table ------------------ */
.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card);
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
}

thead {
    background: var(--accent);
    color: white;
}

thead th {
    text-align: left;
    padding: 12px 16px;
    font-weight: 600;
    font-size: 0.95rem;
}

tbody tr {
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}

tbody tr:nth-child(even) {
    background: #f9f9f9;
}

tbody tr:hover {
    background: rgba(104,77,143,0.05);
}

tbody td {
    padding: 12px 16px;
    font-size: 0.9rem;
    color: var(--text);
    vertical-align: middle;
}

.actions {
    display: flex;
    gap: 6px;
    justify-content: center;
    white-space: nowrap;
}

.btn {
    padding: 6px 12px;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: 0.2s ease;
}

.btn-edit {
    background: #fbbf24;
    color: white;
}

.btn-edit:hover {
    background: #f59e0b;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

/* Optional: small badge styling for status or labels inside table */
.badge {
    background: rgba(104,77,143,0.1);
    color: var(--accent);
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 0.5rem;
    font-weight: 600;
}
    .pagination {
      display: flex;
      justify-content: center;
      gap: 8px;
      padding: 12px 20px;
      border-top: 1px solid #ddd;
    }

    .pagination button {
      background: #e5e7eb;
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .pagination button.active {
      background:  var(--accent) !important;
      color: white;
    }

    .pagination button:hover:not(.active) {
      background: #d1d5db;
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

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($message) echo $message; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-ticket-alt me-2"></i>Ticket Manager</h2>
            <a href="parkmanager.php" class="btn btn-success">
                <i class="fas fa-tree me-1"></i>Manage Parks
            </a>
        </div>

        <!-- Ticket Statistics -->
        <?php
        $ticket_stats_sql = "SELECT 
            COUNT(*) as total_tickets,
            COUNT(DISTINCT park_id) as parks_with_tickets,
            AVG(price) as avg_price,
            MAX(price) as max_price,
            MIN(price) as min_price
            FROM park_tickets";
        $ticket_stats_result = $conn->query($ticket_stats_sql);
        $ticket_stats = $ticket_stats_result->fetch_assoc();
        ?>
        
        <div class="ticket-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($ticket_stats['total_tickets']); ?></div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($ticket_stats['parks_with_tickets']); ?></div>
                <div class="stat-label">Parks with Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($ticket_stats['avg_price'], 0); ?></div>
                <div class="stat-label">Average Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($ticket_stats['max_price'], 0); ?></div>
                <div class="stat-label">Highest Price</div>
            </div>
        </div>

        <div class="row">
            <!-- Add Ticket Form -->
            <div class="col-md-12">
                <div class="form-section">
                    <h5><i class="fas fa-plus-circle me-2"></i>Add New Ticket</h5>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Select Park</label>
                                <select name="park_id" class="form-control mb-2" required>
                                    <option value="">Choose a park...</option>
                                    <?php
                                    $parks_list = $conn->query("SELECT id, name FROM parks ORDER BY name");
                                    while($row = $parks_list->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ticket Name</label>
                                <input type="text" name="ticket_name" placeholder="e.g. Adult Ticket, Student Discount" class="form-control mb-2" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Price (₱)</label>
                                <input type="number" step="0.01" name="price" placeholder="0.00" class="form-control mb-2" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ticket Details</label>
                                <textarea name="details" placeholder="Additional information about this ticket type" class="form-control mb-2" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="add_ticket" class="btn btn-success w-100">
                            <i class="fas fa-plus me-1"></i>Add Ticket
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="col-md-12">
                <div class="card p-3">
                    <h5>All Tickets</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Park</th>
                                    <th>Ticket Type</th>
                                    <th>Price</th>
                                    <th>Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $ticket_types->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['park_name']) ?></td>
                                        <td><?= htmlspecialchars($row['ticket_name']) ?></td>
                                        <td>₱<?= number_format($row['price'], 2) ?></td>
                                        <td><?= htmlspecialchars($row['details']) ?></td>
                                        <td>
                                            <!-- Delete Button -->
                                            <form method="POST" style="display:inline-block">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="delete_ticket" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Delete this ticket?')">Delete</button>
                                            </form>

                                            <!-- Edit Button (opens modal) -->
                                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#updateTicketModal"
                                                data-id="<?= $row['id'] ?>"
                                                data-ticket="<?= htmlspecialchars($row['ticket_name']) ?>"
                                                data-price="<?= $row['price'] ?>"
                                                data-details="<?= htmlspecialchars($row['details']) ?>">Edit</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <!-- Pagination -->
                <div class="pagination mt-3">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>"><button>Previous</button></a>
                    <?php endif; ?>
                
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>">
                            <button class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
                        </a>
                    <?php endfor; ?>
                
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>"><button>Next</button></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Editing Ticket -->
    <div class="modal fade" id="updateTicketModal" tabindex="-1" aria-labelledby="updateTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="id" id="ticket-id">
                        <div class="mb-3">
                            <label>Ticket Name</label>
                            <input type="text" name="ticket_name" id="ticket-name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Price (₱)</label>
                            <input type="number" step="0.01" name="price" id="ticket-price" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Details</label>
                            <textarea name="details" id="ticket-details" class="form-control" rows="4"></textarea>
                        </div>
                        <button type="submit" name="update_ticket" class="btn btn-primary w-100">Update Ticket</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Edit Ticket Modal
const updateTicketModal = document.getElementById('updateTicketModal');
updateTicketModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('ticket-id').value = button.getAttribute('data-id');
    document.getElementById('ticket-name').value = button.getAttribute('data-ticket');
    document.getElementById('ticket-price').value = button.getAttribute('data-price');
    document.getElementById('ticket-details').value = button.getAttribute('data-details');
});
</script>
</body>
</html>

<?php $conn->close(); ?>