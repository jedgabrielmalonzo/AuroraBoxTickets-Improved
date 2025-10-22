<?php
// Service Agreements - Contract-based Park Management
// File: service-agreements-parkmanager.php
// Assumes $conn is a mysqli connection returned from ../database.php

$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
$message = "";

// Create contracts table if it doesn't exist (safe to run)
$create_table_sql = "CREATE TABLE IF NOT EXISTS service_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    park_name VARCHAR(255) NOT NULL,
    vendor_name VARCHAR(255) DEFAULT NULL,
    service_period_months INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_table_sql);

// Price tiers (could later be moved to a DB table)
$price_tiers = [
    6 => 10000.00,
    12 => 17500.00,
    24 => 26000.00
];

// Helper: compute price from months
function price_for_months($months, $tiers) {
    if (isset($tiers[$months])) return $tiers[$months];
    // fallback: prorate by month using 12-month price
    if (isset($tiers[12])) return round($tiers[12] * ($months / 12), 2);
    return 0.00;
}

// Auto-deactivate expired contracts (run on page load)
$deactivate_sql = "UPDATE service_contracts SET status='inactive' WHERE end_date < CURDATE() AND status='active'";
$conn->query($deactivate_sql);

// Add Contract
if (isset($_POST['add_contract'])) {
    $park_name = mysqli_real_escape_string($conn, $_POST['park_name']);
    $vendor_name = mysqli_real_escape_string($conn, $_POST['vendor_name']);
    $service_months = intval($_POST['service_months']);

    // Start date = today (auto)
    $start_date = date('Y-m-d');

    // Compute End Date = Start + Service Period
    $start = date_create_from_format('Y-m-d', $start_date);
    $end = clone $start;
    $end->modify("+{$service_months} months");
    $end_date = $end->format('Y-m-d');

    // Compute price from months
    $price = price_for_months($service_months, $price_tiers);

    // Insert record
    $sql = "INSERT INTO service_contracts (park_name, vendor_name, service_period_months, start_date, end_date, price, status, created_at)
            VALUES ('$park_name', '$vendor_name', '$service_months', '$start_date', '$end_date', '$price', 'active', NOW())";

    if ($conn->query($sql)) {
        $message = "<div class='alert alert-success'>New contract added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// ✅ Fetch parks grouped by category
$sql = "SELECT id, name, city, country, category, created_at AS start_date, end_date 
        FROM parks 
        ORDER BY category, name ASC";
$result = $conn->query($sql);

$parksByCategory = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parksByCategory[$row['category']][] = $row;
    }
}

$categoryNames = [
    1 => "THEME PARKS",
    2 => "AQUA PARKS",
    3 => "NATURE PARKS",
    4 => "MUSEUMS"
];

// Renew Contract - extend by months
if (isset($_POST['renew_contract'])) {
    $id = intval($_POST['contract_id'] ?? 0);
    $add_months = intval($_POST['add_months'] ?? 0);

    if ($id <= 0 || $add_months <= 0) {
        $message = "<div class='alert alert-danger'>Invalid contract or period.</div>";
    } else {
        // Fetch current end_date
        $q = $conn->prepare("SELECT end_date, service_period_months FROM service_contracts WHERE id = ? LIMIT 1");
        $q->bind_param('i', $id);
        $q->execute();
        $res = $q->get_result();
        if ($row = $res->fetch_assoc()) {
            $current_end = $row['end_date'];
            $new_end_dt = date_create_from_format('Y-m-d', $current_end);
            if (!$new_end_dt) $new_end_dt = new DateTime();
            $new_end_dt->modify("+{$add_months} months");
            $new_end = $new_end_dt->format('Y-m-d');

            // Update price: add price_for_months for the added months
            $added_price = price_for_months($add_months, $price_tiers);

            $u = $conn->prepare("UPDATE service_contracts SET end_date = ?, service_period_months = service_period_months + ?, price = price + ?, status = 'active' WHERE id = ?");
            $u->bind_param('sidi', $new_end, $add_months, $added_price, $id);
            if ($u->execute()) {
                $message = "<div class='alert alert-success'>Contract renewed successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error renewing contract: " . htmlspecialchars($u->error) . "</div>";
            }
            $u->close();
        } else {
            $message = "<div class='alert alert-warning'>Contract not found.</div>";
        }
        $q->close();
    }
}

// Deactivate contract (set inactive) - not delete
if (isset($_POST['deactivate_contract'])) {
    $id = intval($_POST['contract_id'] ?? 0);
    if ($id > 0) {
        $d = $conn->prepare("UPDATE service_contracts SET status='inactive' WHERE id = ?");
        $d->bind_param('i', $id);
        if ($d->execute()) {
            $message = "<div class='alert alert-success'>Contract set to inactive.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . htmlspecialchars($d->error) . "</div>";
        }
        $d->close();
    }
}

// Delete contract permanently
if (isset($_POST['delete_contract'])) {
    $id = intval($_POST['contract_id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM service_contracts WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Contract deleted successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting contract: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}


// Fetch contracts with optional filters
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$sql = "SELECT * FROM service_contracts WHERE 1=1";
if ($filter_status !== '') {
    $allowed = ['active','inactive'];
    if (in_array($filter_status, $allowed)) {
        $sql .= " AND status = '" . $filter_status . "'";
    }
}
$sql .= " ORDER BY end_date ASC";
$contracts = $conn->query($sql);

// Statistics
$stats_q = $conn->query("SELECT
    COUNT(*) AS total_contracts,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_contracts,
    SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) AS inactive_contracts,
    SUM(CASE WHEN DATEDIFF(end_date, CURDATE()) <= 30 AND status='active' THEN 1 ELSE 0 END) AS expiring_soon
FROM service_contracts");
$stats = $stats_q->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Agreements - Contract Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/images/favicon.png">
    <style>
        :root { --accent: #684D8F; --bg: #f7f7f8; --card: #fff; }
        body { background: var(--bg); font-family: 'Poppins', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { border-radius: 8px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; min-width: 150px; text-align: center; }
    </style>
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

  .nav-tabs .nav-link {
    color: var(--accent);
    font-weight: 500;
  }
  .nav-tabs .nav-link.active {
    background: var(--accent);
    color: #fff;
    border-radius: 0.5rem 0.5rem 0 0;
  }

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
    background: var(--accent) !important;
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
    <?php if ($message) echo $message; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Service Agreements</h2>
    </div>
    <!-- Add Contract Form -->
<div class="card p-3 mb-4">
    <h5>Add New Service Contract</h5>
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Park Name</label>
            <input type="text" name="park_name" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Vendor Name</label>
            <input type="text" name="vendor_name" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Service Period</label>
            <select name="service_months" class="form-select" required>
                <option value="">Choose...</option>
                <option value="6">6 months</option>
                <option value="12">12 months</option>
                <option value="24">24 months</option>
            </select>
        </div>

        <!-- Auto Start Date Display -->
        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="hidden" name="start_date" value="<?php echo date('Y-m-d'); ?>">
            <p class="form-control-plaintext"><strong><?php echo date('F d, Y'); ?></strong></p>
        </div>

        <div class="col-12">
            <button type="submit" name="add_contract" class="btn btn-primary">Add Contract</button>
        </div>
    </form>
</div>


    <div class="park-stats d-flex gap-3 mb-4">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['total_contracts']); ?></div>
            <div class="stat-label">Total Contracts</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['active_contracts']); ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['expiring_soon']); ?></div>
            <div class="stat-label">Expiring ≤ 30 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['inactive_contracts']); ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>

    <!-- Price tiers quick reference -->
    <div class="card p-3 mb-4">
        <h5>Pricing (Example contract fees)</h5>
        <ul>
            <li>6 months — PHP <?php echo number_format($price_tiers[6],2); ?></li>
            <li>12 months — PHP <?php echo number_format($price_tiers[12],2); ?></li>
            <li>24 months — PHP <?php echo number_format($price_tiers[24],2); ?></li>
        </ul>
        <small class="text-muted">Revenue will be collected per contract and stored in the system. Splits between AuroraBox and vendors can be calculated separately in Transactions.</small>
    </div>

    <!-- Filters -->
    <form class="mb-3 row g-2" method="GET">
        <div class="col-md-3">
            <select name="filter_status" class="form-select">
                <option value="">All Statuses</option>
                <option value="active" <?php if($filter_status=='active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if($filter_status=='inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-secondary">Apply</button>
        </div>
    </form>

    <div class="card p-3">
        <h5>Contracts</h5>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Park / Vendor</th>
                        <th>Service Period</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $contracts->fetch_assoc()):
                        $end = new DateTime($c['end_date']);
                        $today = new DateTime();
                        $diff = (int)$today->diff($end)->format('%r%a'); // days left (can be negative)
                    ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($c['park_name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($c['vendor_name']); ?></small>
                        </td>
                        <td><?php echo intval($c['service_period_months']); ?> months</td>
                        <td><?php echo htmlspecialchars($c['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($c['end_date']); ?>
                            <?php if ($diff <= 30 && $diff >= 0 && $c['status']=='active'): ?>
                                <br><small class="text-warning"><?php echo $diff; ?> day(s) left</small>
                            <?php elseif ($diff < 0 && $c['status']=='inactive'): ?>
                                <br><small class="text-danger">Expired</small>
                            <?php endif; ?>
                        </td>
                        <td>PHP <?php echo number_format($c['price'],2); ?></td>
                        <td><?php echo ucfirst($c['status']); ?></td>
                        <td>
    <div class="btn-group" role="group">
        <?php if ($c['status']=='active' && $diff <= 30 && $diff >= 0): ?>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#renewModal" 
                data-id="<?php echo $c['id']; ?>" data-park="<?php echo htmlspecialchars($c['park_name']); ?>">
                Renew Contract
            </button>
        <?php endif; ?>

        <!-- Set Inactive Button -->
        <form method="POST" style="display:inline-block; margin-left:6px;">
            <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
            <button type="submit" name="deactivate_contract" class="btn btn-sm btn-outline-danger"
                onclick="return confirm('Set contract to inactive?')">
                Set Inactive
            </button>
        </form>

        <!-- ✅ Delete Button -->
        <form method="POST" style="display:inline-block; margin-left:6px;">
            <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
            <button type="submit" name="delete_contract" class="btn btn-sm btn-danger"
                onclick="return confirm('Are you sure you want to permanently delete this contract?')">
                Delete
            </button>
        </form>
    </div>
</td>

                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br>
<div class="card p-3">
<h2>PARK MONITORING</h2>
  <?php if (!empty($parksByCategory)): ?>
    <!-- Tabs -->
    <ul class="nav nav-tabs mt-3" id="parkTabs" role="tablist">
      <?php $i=0; foreach ($parksByCategory as $category => $parks): ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $i===0?'active':'' ?>" id="tab-<?= $category ?>" 
                  data-bs-toggle="tab" data-bs-target="#cat-<?= $category ?>" 
                  type="button" role="tab">
            <?= htmlspecialchars($categoryNames[$category] ?? $category) ?>
          </button>
        </li>
      <?php $i++; endforeach; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content mt-3">
      <?php $i=0; foreach ($parksByCategory as $category => $parks): ?>
        <div class="tab-pane fade <?= $i===0?'show active':'' ?>" id="cat-<?= $category ?>" role="tabpanel">
          <div class="table-responsive">
            <table class="table" id="table-<?= $category ?>">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Park Name</th>
                  <th>City</th>
                  <th>Country</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($parks as $p): ?>
                  <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['city']) ?></td>
                    <td><?= htmlspecialchars($p['country']) ?></td>
                    <td><?= date("M d, Y", strtotime($p['start_date'])) ?></td>
                    <td><?= !empty($p['end_date']) ? date("M d, Y", strtotime($p['end_date'])) : '<span class="badge">No deadline</span>' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="pagination" id="pagination-<?= $category ?>"></div>
        </div>
      <?php $i++; endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted">No parks found.</p>
  <?php endif; ?>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ Pagination per table (10 rows each)
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("table").forEach((table) => {
    const rows = table.querySelectorAll("tbody tr");
    const perPage = 10;
    const numPages = Math.ceil(rows.length / perPage);
    const category = table.id.split("-")[1];
    const pagination = document.getElementById("pagination-" + category);

    function showPage(page) {
      rows.forEach((row, i) => {
        row.style.display = (i >= (page-1)*perPage && i < page*perPage) ? "" : "none";
      });
      pagination.querySelectorAll("button").forEach((btn, i) => {
        btn.classList.toggle("active", i+1 === page);
      });
    }

    if (numPages > 1) {
      for (let i=1; i<=numPages; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        btn.addEventListener("click", () => showPage(i));
        pagination.appendChild(btn);
      }
    }
    showPage(1);
  });
});
</script>
</div>



<!-- Renew Modal -->
<div class="modal fade" id="renewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Renew Contract</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
      <div class="modal-body">
          <input type="hidden" name="contract_id" id="renew-contract-id">
          <div class="mb-2">
            <label class="form-label">Add Months</label>
            <select name="add_months" class="form-select" required>
                <option value="">Choose...</option>
                <option value="6">6 months</option>
                <option value="12">12 months</option>
                <option value="24">24 months</option>
            </select>
          </div>
          <p class="small text-muted">Renewing will extend the end date and add the corresponding fee to the contract price.</p>
      </div>
      <div class="modal-footer">
        <button type="submit" name="renew_contract" class="btn btn-success">Renew</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
var renewModal = document.getElementById('renewModal');
renewModal.addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var park = button.getAttribute('data-park');
    document.getElementById('renew-contract-id').value = id;
});
</script>
</body>
</html>

<?php $conn->close(); ?>
