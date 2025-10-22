<?php
require_once __DIR__ . '/../config.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["admin_first_name"])) {
    header("Location: index.php");
    exit;
}

// Access admin's name
$first_name = $_SESSION["admin_first_name"];
$last_name = $_SESSION["admin_last_name"];

// Database connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle search and filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the WHERE clause dynamically
$where_conditions = [];
$params = [];
$param_types = "";

// Search condition
if (!empty($search)) {
    $where_conditions[] = "(u.firstname LIKE ? OR u.lastname LIKE ? OR p.payment_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $param_types .= "sss";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Construct WHERE clause
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// --- PAGINATION SETTINGS ---
$records_per_page = 10; 
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $records_per_page;

// --- COUNT TOTAL RECORDS for pagination ---
$count_sql = "
SELECT COUNT(*) as total 
FROM payments p
LEFT JOIN user u ON p.user_id = u.id
$where_clause
";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// --- MAIN SQL QUERY with LIMIT & OFFSET ---
$sql = "
SELECT 
    p.id as payment_table_id,
    p.payment_id,
    p.user_id,
    CONCAT(u.firstname, ' ', u.lastname) AS user_name,
    p.amount,
    p.status,
    p.payment_method,
    p.reference_number,
    p.created_at AS payment_date
FROM 
    payments p
LEFT JOIN 
    user u ON p.user_id = u.id
$where_clause
ORDER BY 
    p.created_at DESC
LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

// Bind filters + pagination
if (!empty($params)) {
    $param_types_with_limit = $param_types . "ii"; 
    $params_with_limit = [...$params, $records_per_page, $offset];
    $stmt->bind_param($param_types_with_limit, ...$params_with_limit);
} else {
    $stmt->bind_param("ii", $records_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Get payment status options for filter dropdown
$status_sql = "SELECT DISTINCT status FROM payments ORDER BY status";
$status_result = $conn->query($status_sql);
$payment_statuses = [];
while ($status_row = $status_result->fetch_assoc()) {
    $payment_statuses[] = $status_row['status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - Aurora Box</title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">
    <link href="CSS/dashboard.css" rel="stylesheet">
    <link href="CSS/moviemanage.css" rel="stylesheet">
   
<style>
/* Keep existing stats intact */
.transaction-stats {
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

/* Table Styles */

.table-history {
  background: var(--accent);
}
.table-responsive {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.table-responsive table {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial, sans-serif;
}

.table-responsive thead {
    background: #684D8F;
    color: white;
}

.table-responsive thead th {
    padding: 12px 16px;
    font-weight: 600;
    text-align: left;
    font-size: 0.9rem;
}

.table-responsive tbody tr {
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}

.table-responsive tbody tr:nth-child(even) {
    background: #fafafa;
}

.table-responsive tbody tr:hover {
    background: #f1ecf8;
}

.table-responsive tbody td {
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #333;
    vertical-align: middle;
    word-break: break-word;
}

.table-responsive tbody td small {
    font-size: 0.75rem;
    color: #777;
    display: block;
    margin-top: 2px;
}

/* Wrap long lists of IDs */
.wrap-ids {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.wrap-ids span {
    display: inline-block;
    margin-right: 5px;
}

/* Status badges */
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}
.status-paid { background: #d4edda; color: #155724; }
.status-refund { background: #ffcb80; color: #FF9800; }
.status-pending { background: #fff3cd; color: #856404; }
.status-failed { background: #f8d7da; color: #721c24; }
.status-cancelled { background: #e2e3e5; color: #383d41; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 12px 0;
}

.pagination .page-item .page-link {
    background: #e5e7eb;
    border-radius: 6px;
    padding: 6px 12px;
    color: #333;
    text-decoration: none;
    transition: background 0.2s;
}

.pagination .page-item.disabled .page-link {
    opacity: 0.5;
    cursor: default;
}

.pagination .page-item .page-link:hover:not(.active) {
    background: #d1d5db;
}

.pagination .page-item.active .page-link {
    background: #684D8F;
    color: white;
}

/* Alerts */
.alert-info {
    background-color: #e8e2f0;
    color: #684D8F;
    border-radius: 8px;
    padding: 1rem;
    font-weight: 500;
    text-align: center;
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
    <a href="serviceagreement.php">Service Agreement</a>
    <a href="ticketmanager.php">Tickets & Pricing</a>
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
        <div class="admin-name">
            <a>Hello, <?php echo htmlspecialchars($first_name . " " . $last_name); ?>!</a>
        </div>
        <br>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-receipt me-2"></i>Transaction History</h2>
            <button class="btn btn-success" onclick="exportTransactions()">
                <i class="fas fa-download me-1"></i>Export CSV
            </button>
        </div>

        <!-- Transaction Statistics -->
        <?php
        $stats_sql = "SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'completed' OR status = 'success' THEN amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status = 'completed' OR status = 'success' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM payments";
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result->fetch_assoc();
        ?>
        
        <div class="transaction-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_transactions']); ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['completed_count']); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['pending_count']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- Search and Filter Form -->
        <div class="search-filters">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" placeholder="Name or Payment ID" 
                           value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach ($payment_statuses as $status): ?>
                            <option value="<?php echo $status; ?>" 
                                    <?php echo ($status_filter == $status) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="transactions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Count -->
        <div class="mb-3">
            <small class="text-muted">
                Showing <?php echo $result->num_rows; ?> of <?php echo $total_records; ?> transaction(s)
                <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                    with applied filters
                <?php endif; ?>
            </small>
        </div>

        <!-- Transaction Table -->
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-history">
                        <tr>
                            <th>Payment ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Reference #</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row["payment_id"]); ?></strong>
                                    <br><small class="text-muted">User ID: <?php echo $row["user_id"]; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row["user_name"] ?? 'N/A'); ?></td>
                                <td><strong>₱<?php echo number_format($row["amount"], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($row["payment_method"] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    if ($row["reference_number"]) {
                                        echo '<small>' . htmlspecialchars($row["reference_number"]) . '</small>';
                                    } else {
                                        echo '<small class="text-muted">N/A</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status = strtolower($row["status"]);
                                    // Normalize refunded to refund
                                    if ($status == 'refunded') {
                                        $badge_class = "status-refund";
                                    } else {
                                        $badge_class = "status-" . $status;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($row["status"]); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row["payment_date"])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-3">
                        <!-- Previous Button -->
                        <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                Previous
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $max_links = 5; 
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor;
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => $total_pages])).'">'.$total_pages.'</a></li>';
                        }
                        ?>

                        <!-- Next Button -->
                        <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No transactions found matching your criteria.
            </div>
        <?php endif; ?>
    </div>    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportTransactions() {
            const urlParams = new URLSearchParams(window.location.search);
            const exportUrl = 'export_transactions.php?' + urlParams.toString();
            window.open(exportUrl, '_blank');
        }
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
