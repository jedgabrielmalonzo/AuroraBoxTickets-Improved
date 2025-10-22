<?php
// DB Connection
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
  die('Database connection failed.');
}
$conn = $mysqli;

$message = "";

// ADD PROMO
if (isset($_POST['add_promo'])) {
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $value = mysqli_real_escape_string($conn, $_POST['value']);
    $expiration_date = mysqli_real_escape_string($conn, $_POST['expiration_date']);
    $usage_limit = mysqli_real_escape_string($conn, $_POST['usage_limit']);
    
    // Convert selected parks to comma-separated string
    $applicable_parks = !empty($_POST['applicable_parks']) ? implode(',', $_POST['applicable_parks']) : '';

    // Insert into promos table
    $stmt = $conn->prepare("INSERT INTO promos 
        (code, description, type, value, expiration_date, usage_limit, used_count, status, applicable_parks) 
        VALUES (?, ?, ?, ?, ?, ?, 0, 'active', ?)");
    $stmt->bind_param("sssisss", $code, $description, $type, $value, $expiration_date, $usage_limit, $applicable_parks);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>New promo added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// DELETE PROMO
if (isset($_POST['delete_promo'])) {
    $id = intval($_POST['id']);

    // Delete from promos table
    $del = $conn->prepare("DELETE FROM promos WHERE id=?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        $message = "<div class='alert alert-success'>Promo deleted successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $del->error . "</div>";
    }
    $del->close();
}

// UPDATE PROMO
if (isset($_POST['update_promo'])) {
    $id = intval($_POST['id']);
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $value = mysqli_real_escape_string($conn, $_POST['value']);
    $expiration_date = mysqli_real_escape_string($conn, $_POST['expiration_date']);
    $usage_limit = mysqli_real_escape_string($conn, $_POST['usage_limit']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Convert selected parks to comma-separated string
    $applicable_parks = !empty($_POST['applicable_parks']) ? implode(',', $_POST['applicable_parks']) : '';

    $stmt = $conn->prepare("UPDATE promos 
        SET code=?, description=?, type=?, value=?, expiration_date=?, usage_limit=?, status=?, applicable_parks=? 
        WHERE id=?");
    $stmt->bind_param("sssissssi", $code, $description, $type, $value, $expiration_date, $usage_limit, $status, $applicable_parks, $id);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Promo updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error updating promo: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// ✅ FETCH PROMOS
$promos = $conn->query("SELECT * FROM promos ORDER BY id DESC");

// ✅ FETCH PARKS ONCE
$parks_by_cat = [];
$parks_result = $conn->query("SELECT id, name, category FROM parks ORDER BY category, name");
while ($p = $parks_result->fetch_assoc()) {
    $cat = $p['category'] ?? 'Uncategorized';
    $parks_by_cat[$cat][] = $p;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Promo Manager</title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="CSS/sidebar.css" rel="stylesheet">
  
  <style>
/* -------------------------------
   Promo Manager Form Styling
   (aligned sa new global design)
-------------------------------- */
:root {
  --accent: #684D8F;
  --bg: #f7f7f8;
  --card: #fff;
  --text: #333;
  --muted: #666;
}

.card.p-3.mb-4 {
  background: var(--card);
  padding: 2rem !important;
  border-radius: 0.75rem;
  box-shadow: 0 6px 20px rgba(0,0,0,0.05);
  border: none;
  animation: fadeIn 0.5s ease-out;
}

.card.p-3.mb-4 h5 {
  margin-bottom: 1.5rem;
  color: var(--accent);
  font-weight: 600;
}

/* Labels */
.card.p-3.mb-4 label {
  font-weight: 500;
  font-size: 0.9rem;
  margin-bottom: 0.3rem;
  display: block;
  color: var(--muted);
}

/* Inputs & selects */
.card.p-3.mb-4 .form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 0.5rem;
  font-size: 0.95rem;
  background: #fafafa;
  transition: 0.2s;
}

.card.p-3.mb-4 .form-control:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 2px rgba(104,77,143,0.2);
  background: #fff;
}

/* Buttons */
.card.p-3.mb-4 .btn-primary {
  padding: 0.9rem;
  border: none;
  border-radius: 0.5rem;
  background: var(--accent);
  color: white;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: 0.2s ease;
}

.card.p-3.mb-4 .btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(104,77,143,0.3);
}

/* Fade-in animation */
@keyframes fadeIn {
  from {opacity: 0; transform: translateY(10px);}
  to {opacity: 1; transform: translateY(0);}
}

/* Promo Table Styling */
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

/* Pagination buttons */
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
    background: var(--accent);
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
<div class="main-content p-4">
  <?php if ($message) echo $message; ?>

  <h2>Promo Manager</h2>
  
<!-- Add Promo Form -->
<div class="card p-3 mb-4">
  <h5>Add New Promo</h5>
  <form method="POST">
    <div class="row mb-2">
      <div class="col-md-6">
        <label>Promo Code</label>
        <input type="text" name="code" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label>Description</label>
        <input type="text" name="description" class="form-control" required>
      </div>
    </div>
    <div class="row mb-2">
      <div class="col-md-4">
        <label>Type</label>
        <select name="type" class="form-control" required>
          <option value="percentage">Percentage</option>
          <option value="fixed">Fixed Amount</option>
        </select>
      </div>
      <?php
// ✅ Fetch all parks from the database
$parks_query = $conn->query("SELECT id, name FROM parks ORDER BY name ASC");

// ✅ For edit mode (optional): check if editing existing promo
$saved_parks = [];
if (isset($promo) && !empty($promo['applicable_parks'])) {
    $saved_parks = explode(',', $promo['applicable_parks']);
}
?>

<!-- 🏞️ Applicable Parks Section -->
<div class="acc-grid acc-cols-2" style="margin-top:12px">
  <div>
    <label><strong>Applicable Parks</strong></label>
    <p style="font-size:13px;color:#666;margin-bottom:6px;">Select one or more parks where this promo applies:</p>

    <div class="dropdown" style="position: relative;">
      <button type="button" id="dropdownBtn" class="btn btn-light" style="width:100%; text-align:left; border:1px solid #ccc; padding:8px; border-radius:6px;">
        Select Parks ▼
      </button>

      <div id="dropdownMenu" style="
        display:none;
        position:absolute;
        background:#fff;
        border:1px solid #ccc;
        border-radius:6px;
        padding:10px 15px;
        margin-top:4px;
        width:100%;
        max-height:340px;
        overflow-y:auto;
        box-shadow:0 2px 6px rgba(0,0,0,0.1);
        z-index:1000;
      ">
        <div style="margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:6px;">
          <div class="form-check">
            <input type="checkbox" id="selectAllParks" class="form-check-input">
            <label for="selectAllParks" class="form-check-label" style="font-weight:600; color:#333;">
              Select All Parks
            </label>
          </div>
        </div>

        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f1f1f1; border-bottom:1px solid #ddd;">
              <th style="text-align:left; padding:6px;">Category</th>
              <th style="text-align:left; padding:6px;">Parks</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Fetch and group parks by category
            $parks_by_cat = [];
            $parks_result = $conn->query("SELECT id, name, category FROM parks ORDER BY category, name");
            while ($row = $parks_result->fetch_assoc()) {
                $parks_by_cat[$row['category']][] = $row;
            }

            foreach ($parks_by_cat as $category => $parks): ?>
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px; vertical-align:top; font-weight:600; color:#333;">
                  <?= htmlspecialchars($category) ?>
                </td>
                <td style="padding:8px;">
                  <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:6px 12px;">
                    <?php foreach ($parks as $park): ?>
                      <div class="form-check" style="margin-bottom:4px;">
                        <input 
                          class="form-check-input park-checkbox"
                          type="checkbox"
                          name="applicable_parks[]"
                          value="<?= htmlspecialchars($park['id']) ?>"
                          id="park<?= htmlspecialchars($park['id']) ?>"
                          <?= in_array($park['id'], $saved_parks) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="park<?= htmlspecialchars($park['id']) ?>">
                          <?= htmlspecialchars($park['name']) ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  const dropdownBtn = document.getElementById('dropdownBtn');
  const dropdownMenu = document.getElementById('dropdownMenu');
  const selectAll = document.getElementById('selectAllParks');

  dropdownBtn.addEventListener('click', () => {
    dropdownMenu.style.display = dropdownMenu.style.display === 'none' || dropdownMenu.style.display === '' ? 'block' : 'none';
  });

  // ✅ Close dropdown when clicking outside
  document.addEventListener('click', function(event) {
    if (!dropdownBtn.contains(event.target) && !dropdownMenu.contains(event.target)) {
      dropdownMenu.style.display = 'none';
    }
  });

  // ✅ Select All toggle logic
  selectAll.addEventListener('change', () => {
    const allCheckboxes = document.querySelectorAll('.park-checkbox');
    allCheckboxes.forEach(cb => cb.checked = selectAll.checked);
  });
</script>


      <div class="col-md-4">
        <label>Value</label>
        <input type="number" name="value" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label>Usage Limit</label>
        <input type="number" name="usage_limit" class="form-control" required>
      </div>
    </div>
    <div class="row mb-2">
      <div class="col-md-6">
        <label>Expiration Date</label>
        <input type="date" name="expiration_date" class="form-control" required>
      </div>
    </div>
    <button type="submit" name="add_promo" class="btn btn-primary w-100">Add Promo</button>
  </form>
</div>

<!-- Promo Table -->
<div class="card p-3">
  <h5>All Promos</h5>
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Code</th>
        <th>Description</th>
        <th>Applicable Parks</th> <!-- 🆕 New Column -->
        <th>Type</th>
        <th>Value</th>
        <th>Expiration</th>
        <th>Usage Limit</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while($row = $promos->fetch_assoc()): ?>
      <?php
        // Get park names from applicable_parks (comma-separated IDs)
        $parks_list = [];
        if (!empty($row['applicable_parks'])) {
          $park_ids = explode(',', $row['applicable_parks']);
          $ids_placeholder = implode(',', array_fill(0, count($park_ids), '?'));
          $types = str_repeat('i', count($park_ids));

          // Prepare statement dynamically
          $parks_stmt = $conn->prepare("SELECT name FROM parks WHERE id IN ($ids_placeholder)");
          $parks_stmt->bind_param($types, ...$park_ids);
          $parks_stmt->execute();
          $parks_result = $parks_stmt->get_result();
          while ($p = $parks_result->fetch_assoc()) {
              $parks_list[] = $p['name'];
          }
          $parks_stmt->close();
        }
        $parks_display = !empty($parks_list) ? implode(', ', $parks_list) : '<i>No parks set</i>';
      ?>

      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['code']) ?></td>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td><?= $parks_display ?></td> <!-- 🆕 Display applicable parks -->
        <td><?= ucfirst($row['type']) ?></td>
        <td><?= $row['value'] ?></td>
        <td><?= $row['expiration_date'] ?></td>
        <td><?= $row['usage_limit'] ?></td>
        <td><?= ucfirst($row['status']) ?></td>
        <td>
          <form method="POST" style="display:inline-block">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="submit" name="delete_promo" class="btn btn-danger btn-sm" onclick="return confirm('Delete this promo?')">Delete</button>
          </form>
          <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPromo<?= $row['id'] ?>">Edit</button>
        </td>
      </tr>


<?php
// PRE-FETCH parks grouped by category (do this once before rendering promos)
$parks_by_cat = [];
$parks_result = $conn->query("SELECT id, name, category FROM parks ORDER BY category, name");
while ($p = $parks_result->fetch_assoc()) {
    $cat = $p['category'] ?? 'Uncategorized';
    $parks_by_cat[$cat][] = $p;
}
?>

      <!-- Edit Promo Modal -->
<div class="modal fade" id="editPromo<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Promo #<?= $row['id'] ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <!-- existing fields (code, description, etc.) here -->

          <!-- ===== NEW: Applicable Parks (grouped by category) ===== -->
          <?php
            // safe parse saved park ids into ints
            $current_parks = [];
            if (!empty($row['applicable_parks'])) {
                $tmp = array_filter(explode(',', $row['applicable_parks']), function($v){ return trim($v) !== ''; });
                $current_parks = array_map('intval', $tmp);
            }
          ?>
          <div class="mb-3">
            <label class="form-label"><strong>Applicable Parks</strong></label>
            <p style="font-size:13px;color:#666;margin-bottom:6px;">Select one or more parks where this promo applies:</p>

            <div style="max-height:250px; overflow-y:auto; padding:6px; border:1px solid #eee; border-radius:6px;">
              <?php foreach ($parks_by_cat as $category => $parks_arr): ?>
                <div style="margin-bottom:10px;">
                  <div style="font-weight:600; color:#333; margin-bottom:6px;"><?= htmlspecialchars($category) ?></div>

                  <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:6px 12px;">
                    <?php foreach ($parks_arr as $park): 
                      $pid = (int)$park['id'];
                      $inputId = "edit-park-{$row['id']}-{$pid}";
                    ?>
                      <div class="form-check" style="margin-bottom:4px;">
                        <input 
                          class="form-check-input"
                          type="checkbox"
                          name="applicable_parks[]"
                          value="<?= $pid ?>"
                          id="<?= $inputId ?>"
                          <?= in_array($pid, $current_parks) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= $inputId ?>">
                          <?= htmlspecialchars($park['name']) ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <!-- ===== end Applicable Parks section ===== -->

          <!-- rest of modal inputs (type, value, usage_limit, expiration, status) -->
          <div class="row mb-2">
            <div class="col-md-4">
              <label>Type</label>
              <select name="type" class="form-control" required>
                <option value="percentage" <?= $row['type']=='percentage'?'selected':'' ?>>Percentage</option>
                <option value="fixed" <?= $row['type']=='fixed'?'selected':'' ?>>Fixed Amount</option>
              </select>
            </div>
            <div class="col-md-4">
              <label>Value</label>
              <input type="number" name="value" class="form-control" value="<?= $row['value'] ?>" required>
            </div>
            <div class="col-md-4">
              <label>Usage Limit</label>
              <input type="number" name="usage_limit" class="form-control" value="<?= $row['usage_limit'] ?>" required>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-6">
              <label>Expiration Date</label>
              <input type="date" name="expiration_date" class="form-control" value="<?= $row['expiration_date'] ?>" required>
            </div>
            <div class="col-md-6">
              <label>Status</label>
              <select name="status" class="form-control">
                <option value="active" <?= $row['status']=='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $row['status']=='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" name="update_promo" class="btn btn-primary">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

    <?php endwhile; ?>
    </tbody>
  </table>
</div>


</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>