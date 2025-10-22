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

$first_name = $_SESSION["admin_first_name"] ?? "";
$last_name  = $_SESSION["admin_last_name"] ?? "";

$categoryNames = [
    1 => "THEME PARKS",
    2 => "AQUA PARKS",
    3 => "NATURE PARKS",
    4 => "MUSEUMS"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Park Agreements</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="CSS/sidebar.css" rel="stylesheet">
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
<div class="sidebar">
    <img src="/images/logoadminwhite.png" class="logoadmin">
    <a href="dashboard.php">Dashboard</a>
    <p class="sidebar-p">PARK & EVENT MANAGEMENT</p>
    <a href="parkmanager.php">Manage Parks</a>
    <a href="ticketmanager.php">Tickets & Pricing</a>
    <a href="schedulemanager.php" class="active">Park Agreements</a>
    <p class="sidebar-p">CONTENT MANAGEMENT</p>
    <a href="banners.php">Edit Contents</a>
    <a href="promomanager.php">Promo</a>
    <p class="sidebar-p">COMMUNICATION</p>
    <a href="emailmanager.php">Inbox</a>
    <a href="usernotifications.php">User Notifications</a>
    <p class="sidebar-p">ANALYTICS</p>
    <a href="salesanalytics.php">Sales Reports</a>
    <a href="useranalytics.php">User Statistics</a>
    <p class="sidebar-p">REPORTS</p>
    <a href="transactions.php">Transaction History</a>
    <a href="viewusers.php">Registered Users</a>
    <a href="activitylog.php">Activity Logs</a>
    <a href="logoutadmin.php" class="sidebar-logout">Log Out</a>
</div>

<div class="main-content">
  <h2>Park Agreements</h2>
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
</body>
</html>
