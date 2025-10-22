<?php
// sales_report.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection (centralized)
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
  die('Database connection failed.');
}
$conn = $mysqli;

// --- Handle filters (default: last 30 days) ---
$group_by = isset($_GET['group_by']) && $_GET['group_by'] === 'month' ? 'month' : 'day';

$from_date = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to_date   = !empty($_GET['to'])   ? $_GET['to']   : date('Y-m-d');

$from_datetime = $from_date . " 00:00:00";
$to_datetime   = $to_date . " 23:59:59";

// --- Summary numbers ---
$summary_sql = "
    SELECT 
      COALESCE(SUM(price * quantity), 0) AS total_revenue,
      COALESCE(SUM(quantity), 0) AS total_tickets,
      COUNT(*) AS total_orders,
      COUNT(DISTINCT park_id) AS total_parks
    FROM orders
    WHERE created_at BETWEEN ? AND ?
";
$stmt = $conn->prepare($summary_sql);
$stmt->bind_param("ss", $from_datetime, $to_datetime);
$stmt->execute();
$summary_res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_revenue = (float)$summary_res['total_revenue'];
$total_tickets = (int)$summary_res['total_tickets'];
$total_orders  = (int)$summary_res['total_orders'];
$total_parks   = (int)$summary_res['total_parks'];

// --- Sales by park ---
$bypark_sql = "
    SELECT 
      o.park_id,
      COALESCE(p.name, CONCAT('Park #', o.park_id)) AS park_name,
      COALESCE(SUM(o.quantity),0) AS tickets_sold,
      COALESCE(SUM(o.price * o.quantity),0) AS revenue,
      COUNT(*) AS orders_count
    FROM orders o
    LEFT JOIN parks p ON p.id = o.park_id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY o.park_id
    ORDER BY revenue DESC
";
$stmt = $conn->prepare($bypark_sql);
$stmt->bind_param("ss", $from_datetime, $to_datetime);
$stmt->execute();
$bypark_result = $stmt->get_result();
$parks_data = [];
while ($r = $bypark_result->fetch_assoc()) {
    $parks_data[] = $r;
}
$stmt->close();

// --- Sales over time (for chart) ---
$chart_label_sql = ($group_by === 'month') ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE(created_at)";
$chart_sql = "
    SELECT
      {$chart_label_sql} AS label,
      COALESCE(SUM(price * quantity),0) AS revenue
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY label
    ORDER BY label ASC
";
$stmt = $conn->prepare($chart_sql);
$stmt->bind_param("ss", $from_datetime, $to_datetime);
$stmt->execute();
$chart_result = $stmt->get_result();

$chart_labels = [];
$chart_data = [];
while ($c = $chart_result->fetch_assoc()) {
    $chart_labels[] = $c['label'];
    $chart_data[] = (float)$c['revenue'];
}
$stmt->close();


// --- Prepare data for "Sales by Park" chart ---
$park_chart_labels = $chart_labels; // same date labels as main chart
$park_chart_datasets = [];

foreach ($parks_data as $pd) {
    $park_id = $pd['park_id'];
    
    // Query total revenue per park per day/month
    $stmt = $conn->prepare("
        SELECT 
            " . ($group_by==='month' ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE(created_at)") . " AS label,
            COALESCE(SUM(price * quantity),0) AS revenue
        FROM orders
        WHERE park_id = ? AND created_at BETWEEN ? AND ?
        GROUP BY label
        ORDER BY label ASC
    ");
    $stmt->bind_param("iss", $park_id, $from_datetime, $to_datetime);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $park_revenue = [];
    // Initialize revenue for each label as 0
    foreach ($park_chart_labels as $lbl) $park_revenue[$lbl] = 0;
    
    while ($row = $res->fetch_assoc()) {
        $park_revenue[$row['label']] = (float)$row['revenue'];
    }
    
    $stmt->close();
    
    $park_chart_datasets[] = [
        'label' => $pd['park_name'],
        'data' => array_values($park_revenue),
        'borderColor' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)),
        'backgroundColor' => 'rgba(0,0,0,0)',
        'fill' => false,
        'tension' => 0.25
    ];
}
$conn->close();

function money($v) {
    return number_format((float)$v, 2);
}


$first_name = $_SESSION["admin_first_name"];
$last_name = $_SESSION["admin_last_name"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales Statistics Dashboard</title>
  <link rel="icon" type="image/x-icon" href="../images/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="CSS/sidebar.css" rel="stylesheet">
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
      
      
    <h2>Sales Statistics</h2>

    <!-- Filters -->
    <form method="GET" class="row g-2 align-items-end mb-3">
      <div class="col-auto">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label">Group By</label>
        <select name="group_by" class="form-control">
          <option value="day" <?= $group_by === 'day' ? 'selected' : '' ?>>Day</option>
          <option value="month" <?= $group_by === 'month' ? 'selected' : '' ?>>Month</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary">Apply</button>
      </div>
      <div class="col" style="text-align:right;">
        <small class="text-muted">Showing results from <strong><?= htmlspecialchars($from_date) ?></strong> to <strong><?= htmlspecialchars($to_date) ?></strong></small>
      </div>
    </form>

    <!-- Summary cards -->
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card stat-card p-3">
          <div class="text-muted">Total Revenue</div>
          <div class="stat-value">₱ <?= money($total_revenue) ?></div>
          <div class="text-muted small">Sum of price × quantity</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card p-3">
          <div class="text-muted">Tickets Sold</div>
          <div class="stat-value"><?= number_format($total_tickets) ?></div>
          <div class="text-muted small">Total quantity sold</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card p-3">
          <div class="text-muted">Total Orders</div>
          <div class="stat-value"><?= number_format($total_orders) ?></div>
          <div class="text-muted small">Number of order records</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card p-3">
          <div class="text-muted">Parks</div>
          <div class="stat-value"><?= number_format($total_parks) ?></div>
          <div class="text-muted small">Distinct parks in orders</div>
        </div>
      </div>
    </div>

    <!-- Chart -->
   <!-- Sales Chart -->
<div class="card shadow-sm mt-4 mb-5">
  <div class="card-header text-white" style="background-color: #684D8F;">
  <h5 class="mb-0">Sales Over Time</h5>
</div>

  <div class="card-body d-flex justify-content-center align-items-center">
    <!-- ✅ FIXED-SIZE CONTAINER -->
    <div class="chart-container" style="position: relative; width: 90%; max-width: 1000px; height: 400px;">
      <canvas id="salesChart"></canvas>
    </div>
  </div>
</div>

<!-- Sales by Park Chart -->
<div class="card shadow-sm mt-4 mb-5">
  <div class="card-header text-white" style="background-color: #684D8F;">
    <h5 class="mb-0">Sales by Park</h5>
  </div>
  <div class="card-body">

    <!-- Park Dropdown -->
    <div class="dropdown mb-3">
      <button class="btn btn-primary dropdown-toggle" type="button" id="parkDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        Select Park
      </button>
      <ul class="dropdown-menu p-2" aria-labelledby="parkDropdown" style="min-width:300px;">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-2">
          <?php foreach($parks_data as $pd): ?>
            <div class="col">
              <li>
                <button class="dropdown-item park-btn" data-park="<?= htmlspecialchars($pd['park_name']) ?>">
                  <?= htmlspecialchars($pd['park_name']) ?>
                </button>
              </li>
            </div>
          <?php endforeach; ?>
          <div class="col">
            <li>
              <button class="dropdown-item text-success" id="showAllParks">Show All</button>
            </li>
          </div>
        </div>
      </ul>
    </div>

    <!-- Chart container -->
    <div class="chart-container" style="height:400px;">
      <canvas id="salesByParkChart"></canvas>
    </div>

  </div>
</div>



    <div class="row">
      <div class="col-lg-7">
        <!-- Sales by Park -->
        <div class="card p-3 mb-3">
          <h5>Sales by Park</h5>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Park</th>
                  <th>Tickets Sold</th>
                  <th>Revenue</th>
                  <th>Orders</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($parks_data)): ?>
                  <tr><td colspan="4" class="text-muted">No data for selected range.</td></tr>
                <?php else: ?>
                  <?php foreach ($parks_data as $pd): ?>
                    <tr>
                      <td><?= htmlspecialchars($pd['park_name']) ?></td>
                      <td><?= number_format($pd['tickets_sold']) ?></td>
                      <td>₱ <?= money($pd['revenue']) ?></td>
                      <td><?= number_format($pd['orders_count']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <!-- Top Parks -->
        <div class="card p-3 mb-3">
          <h5>Top Parks (by Revenue)</h5>
          <?php if (empty($parks_data)): ?>
            <p class="text-muted">No data available.</p>
          <?php else: ?>
            <ul class="list-group">
              <?php $top = array_slice($parks_data, 0, 5); foreach ($top as $t): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($t['park_name']) ?></div>
                    <div class="small text-muted"><?= number_format($t['tickets_sold']) ?> tickets</div>
                  </div>
                  <div style="font-weight:700">₱ <?= money($t['revenue']) ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const labels = <?= json_encode($chart_labels, JSON_HEX_TAG) ?>;
    const data = <?= json_encode($chart_data, JSON_HEX_TAG) ?>;

    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Revenue',
          data: data,
          fill: true,
          tension: 0.25,
          borderWidth: 2,
          pointRadius: 3,
          borderColor: '#684D8F',
          backgroundColor: 'rgba(104,77,143,0.1)',
        }]
      },
      options: {
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: v => '₱' + Number(v).toLocaleString() }
          }
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: ctx => 'Revenue: ₱ ' + Number(ctx.parsed.y).toLocaleString()
            }
          }
        }
      }
    });
  
  </script>
  
  <script>
const parkLabels = <?= json_encode($park_chart_labels) ?>;
const parkDatasets = <?= json_encode($park_chart_datasets) ?>;

// Initialize chart
const salesByParkCtx = document.getElementById('salesByParkChart').getContext('2d');
const salesByParkChart = new Chart(salesByParkCtx, {
    type: 'line',
    data: {
        labels: parkLabels,
        datasets: parkDatasets
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ₱' + Number(ctx.parsed.y).toLocaleString()
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString() } }
        }
    }
});

// Park buttons inside dropdown
const parkButtons = document.querySelectorAll('.park-btn');
parkButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const selectedPark = btn.dataset.park;

        // Hide all datasets except selected
        salesByParkChart.data.datasets.forEach(ds => ds.hidden = ds.label !== selectedPark);
        salesByParkChart.update();
    });
});

// Show all button
document.getElementById('showAllParks').addEventListener('click', () => {
    salesByParkChart.data.datasets.forEach(ds => ds.hidden = false);
    salesByParkChart.update();
});
</script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
