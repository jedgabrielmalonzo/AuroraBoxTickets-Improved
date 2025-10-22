<?php
require_once __DIR__ . '/../config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION["admin_first_name"])) {
    header("Location: index.php");
    exit;
}
// Create connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize message variable
$message = "";

// Fetch total sales for the current month
$salesCurrentMonthQuery = "SELECT SUM(amount) AS total_sales_month FROM payments WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$salesCurrentMonthResult = $conn->query($salesCurrentMonthQuery);
$totalSalesCurrentMonth = $salesCurrentMonthResult->fetch_assoc()['total_sales_month'] ?? 0;

// Fetch total sales for the current day
$salesTodayQuery = "SELECT SUM(amount) AS total_sales_today FROM payments WHERE DATE(created_at) = CURDATE()";
$salesTodayResult = $conn->query($salesTodayQuery);
$totalSalesToday = $salesTodayResult->fetch_assoc()['total_sales_today'] ?? 0;

// Fetch user count from the users table
$usersQuery = "SELECT COUNT(DISTINCT id) AS user_count FROM user";
$usersResult = $conn->query($usersQuery);
$totalUsers = $usersResult->fetch_assoc()['user_count'] ?? 0;

// No movies table, set total events to 0
$totalEvents = 0;


// Fetch recent activity logs
$sql_activity = "SELECT * FROM admin_activity_log ORDER BY activity_time DESC LIMIT 5";
$result_activity = $conn->query($sql_activity);

// Fetch total sales from November 2024 to May 2025
$salesGraphQuery = "
SELECT
    DATE_FORMAT(created_at, '%Y-%m') AS month,
    SUM(amount) AS total_sales
FROM
    payments
WHERE
    created_at BETWEEN '2024-11-01' AND '2025-05-31'
GROUP BY
    month
ORDER BY
    month ASC
";
$salesGraphResult = $conn->query($salesGraphQuery);
$salesData = [];
while ($row = $salesGraphResult->fetch_assoc()) {
    $salesData[$row['month']] = (float)$row['total_sales'];
}

$months = [];
$sales = [];

for ($month = 11; $month <= 12; $month++) {
    $date = "2024-" . str_pad($month, 2, '0', STR_PAD_LEFT);
    $months[] = $date;
    $sales[] = $salesData[$date] ?? 0;
}

for ($month = 1; $month <= 5; $month++) {
    $date = "2025-" . str_pad($month, 2, '0', STR_PAD_LEFT);
    $months[] = $date;
    $sales[] = $salesData[$date] ?? 0;
}
// No movies or seat_purchases table, set counts to 0
$totalEvents = 0;
$movieCount = 0;

// Prepare data for the pie chart (empty or static)
$categories = [
    'Nature Parks' => 0,
    'Theme Parks' => 0,
    'Aqua Parks' => 0,
    'Museums' => 0,
];

$labels = array_keys($categories);
$data = array_values($categories);

$first_name = $_SESSION["admin_first_name"];
$last_name = $_SESSION["admin_last_name"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">
    <link href="CSS/dashboard.css" rel="stylesheet">
    <link href="CSS/moviemanage.css" rel="stylesheet">
    <style>
        .activity-feed {
            list-style: none;
            padding: 0;
        }
        .activity-feed li {
            margin-bottom: 5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .activity-feed .timestamp {
            font-size: 0.8em;
            color: #888;
        }
        .chart-container {
            width: 100%;
            max-width: 600px;
            margin: auto;
        }
        canvas {
            width: 100% !important;
            height: auto !important;
        }
        .box {
            background-color: #f0f0f0; /* Light gray background */
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <!-- Toggle Button for Sidebar -->
    <button class="hamburger" id="sidebarToggle">
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
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
        <div class="admin-name">
            <a>Hello, <?php echo htmlspecialchars($first_name . " " . $last_name); ?>!</a>
        </div>
        
        
        
        
        
        <br>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="dashboard-cards row">
            <div class="card col-12 col-md-3">
                <h2>Total Sales for Current Month</h2>
                <p>₱<?php echo number_format($totalSalesCurrentMonth, 2); ?></p>
            </div>
            <div class="card col-12 col-md-3">
                <h2>No. of Users</h2>
                <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="card col-12 col-md-3">
                <h2>Total Sales of the Day</h2>
                <p>₱<?php echo number_format($totalSalesToday, 2); ?></p>
            </div>
            <div class="card col-12 col-md-3">
                <h2>Total Events Registered</h2>
                <p><?php echo $totalEvents; ?></p>
            </div>
        </div>

        <!-- Charts Section -->
        <h3 class="mt-5">Sales Overview</h3>
        <div class="row">
            <div class="col-md-6">
                <h4>Sales from November 2024 to May 2025</h4>
                <div class="chart-container">
                    <canvas id="salesChart" width="350" height="250"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <h4>Sales by Category</h4>
                <div class="chart-container">
                    <canvas id="categoryChart" width="100" height="100"></canvas>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const ctxSales = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Total Sales',
                        data: <?php echo json_encode($sales); ?>,
                        borderColor: 'rgba(128, 0, 128, 1)',
                        backgroundColor: 'rgba(128, 0, 128, 0.2)',
                        borderWidth: 2,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            const ctxCategory = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(ctxCategory, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Sales by Category',
                        data: <?php echo json_encode($data); ?>,
                        backgroundColor: ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0'],
                    }]
                },
                options: {
                    responsive: true
                }
            });
        </script>
        <!-- Recent Activity Feed -->
        <h3 class="mt-5">Recent Activity</h3>
        <ul class="activity-feed">
            <?php
            if ($result_activity->num_rows > 0) {
                while ($row = $result_activity->fetch_assoc()) {
                    echo "<li>";
                    echo "<span class='timestamp'>" . date("Y-m-d H:i:s", strtotime($row['timestamp'])) . "</span> - ";
                    echo htmlspecialchars($row['activity_description']);
                    echo "</li>";
                }
            } else {
                echo "<li>No activity found.</li>";
            }
            ?>
        </ul>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementsByClassName('sidebar')[0];
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
