<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection (centralized)
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
  die('Database connection failed.');
}
$conn = $mysqli;

// Fetch all users
$sql = "SELECT id, firstname, lastname, email, is_active, created, last_login FROM user ORDER BY id ASC";
$result = $conn->query($sql);

// Initialize counts
$totalUsers = $activeUsers = $inactiveUsers = 0;
$registrations = [];

$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
        $totalUsers++;
        if ($row['is_active']) {
            $activeUsers++;
        } else {
            $inactiveUsers++;
        }

        // Group by month for registrations
        if (!empty($row['created'])) {
            $month = date("Y-m", strtotime($row['created']));
            if (!isset($registrations[$month])) {
                $registrations[$month] = 0;
            }
            $registrations[$month]++;
        }
    }
}

// Fetch logins per day (last 7 days) from last_login
$sql_logins = "
  SELECT DATE(last_login) as login_date, COUNT(*) as login_count
  FROM user
  WHERE last_login IS NOT NULL
  GROUP BY DATE(last_login)
  ORDER BY login_date DESC
  LIMIT 7
";
$result_logins = $conn->query($sql_logins);

$login_dates = [];
$login_counts = [];
if ($result_logins && $result_logins->num_rows > 0) {
    while ($row = $result_logins->fetch_assoc()) {
        $login_dates[] = $row['login_date'];
        $login_counts[] = $row['login_count'];
    }
}
// Reverse for chronological order
$login_dates = array_reverse($login_dates);
$login_counts = array_reverse($login_counts);

$conn->close();

$first_name = $_SESSION["admin_first_name"] ?? "";
$last_name  = $_SESSION["admin_last_name"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Statistics</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="CSS/sidebar.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<style>
    .chart-card {
  height: 400px; /* same height for both */
}
.chart-card canvas {
  max-height: 100% !important;
}

</style>
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
  <h2>User Analytics</h2>
  <p class="text-muted">Welcome, <?= htmlspecialchars($first_name . " " . $last_name) ?></p>

  <!-- Top Stats Cards -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-center p-3 shadow-sm">
        <h5>Total Users</h5>
        <h2><?= $totalUsers ?></h2>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center p-3 shadow-sm">
        <h5>Active Users</h5>
        <h2 class="text-success"><?= $activeUsers ?></h2>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center p-3 shadow-sm">
        <h5>Inactive Users</h5>
        <h2 class="text-secondary"><?= $inactiveUsers ?></h2>
      </div>
    </div>
  </div>

  <!-- Graphs -->
  <div class="row">
  <div class="col-md-6">
    <div class="card p-3 shadow-sm chart-card">
      <h5>User Status Distribution</h5>
      <canvas id="statusChart" style="max-width:250px; max-height:250px; margin:auto;"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3 shadow-sm chart-card">
      <h5>Registrations Per Month</h5>
      <canvas id="registrationChart"></canvas>
    </div>
  </div>
</div>


  <!-- Login Frequency -->
  <div class="row mt-4">
    <div class="col-md-12">
      <div class="card p-3 shadow-sm">
        <h5>Login Frequency (Last 7 Days)</h5>
        <canvas id="loginChart"></canvas>
      </div>
    </div>
  </div>

  <!-- User Table -->
  <div class="card p-3 mt-4">
    <h5>All Users</h5>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Firstname</th>
            <th>Lastname</th>
            <th>Email</th>
            <th>Status</th>
            <th>Registered</th>
            <th>Last Login</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['firstname']) ?></td>
                <td><?= htmlspecialchars($u['lastname']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <?php if ($u['is_active']): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <td><?= !empty($u['created']) ? date("M d, Y", strtotime($u['created'])) : '-' ?></td>
                <td><?= !empty($u['last_login']) ? date("M d, Y H:i", strtotime($u['last_login'])) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-muted">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Pie Chart - Active vs Inactive
const ctxStatus = document.getElementById('statusChart').getContext('2d');
new Chart(ctxStatus, {
  type: 'pie',
  data: {
    labels: ['Active', 'Inactive'],
    datasets: [{
      data: [<?= $activeUsers ?>, <?= $inactiveUsers ?>],
      backgroundColor: ['#28a745', '#6c757d']
    }]
  }
});

// Bar Chart - Registrations per Month
const ctxReg = document.getElementById('registrationChart').getContext('2d');
new Chart(ctxReg, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($registrations)) ?>,
    datasets: [{
      label: 'Users Registered',
      data: <?= json_encode(array_values($registrations)) ?>,
      backgroundColor: '#007bff'
    }]
  },
  options: {
    scales: { y: { beginAtZero: true } }
  }
});

// Line Chart - Login Frequency
const ctxLogin = document.getElementById('loginChart').getContext('2d');
new Chart(ctxLogin, {
  type: 'line',
  data: {
    labels: <?= json_encode($login_dates ?? []) ?>,
    datasets: [{
      label: 'Logins',
      data: <?= json_encode($login_counts ?? []) ?>,
      fill: true,
      borderColor: '#17a2b8',
      backgroundColor: 'rgba(23, 162, 184, 0.2)',
      tension: 0.3,
      pointBackgroundColor: '#17a2b8'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      title: { display: true, text: 'User Logins per Day' }
    },
    scales: {
      y: { beginAtZero: true }
    }
  }
});
</script>
</body>
</html>
