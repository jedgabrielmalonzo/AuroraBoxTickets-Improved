<?php
// DB Connection
// Centralized connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// Add Park
if (isset($_POST['add_park'])) {
    // Escape and sanitize input data
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $expect = mysqli_real_escape_string($conn, $_POST['expect']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $subcategory = mysqli_real_escape_string($conn, $_POST['subcategory']);

    // Handle image uploads
    $uploadedImages = [];
    if (!empty($_FILES['park_images']['name'][0])) {
        foreach ($_FILES['park_images']['tmp_name'] as $idx => $tmpName) {
            $imgName = basename($_FILES['park_images']['name'][$idx]);
            $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($imgExt, $allowed)) {
                $imgNewName = uniqid('park_', true) . '.' . $imgExt;
                $imgDest = __DIR__ . '/../uploads/' . $imgNewName;
                if (move_uploaded_file($tmpName, $imgDest)) {
                    $uploadedImages[] = 'uploads/' . $imgNewName;
                }
            }
        }
    }
    $pictures = mysqli_real_escape_string($conn, implode(',', $uploadedImages));

    // Use prepared statement for better security and handling of long text
// Kunin end_date from service_contracts based on park name
$contractQuery = "SELECT end_date FROM service_contracts WHERE park_name = ? ORDER BY id DESC LIMIT 1";
$contractStmt = $conn->prepare($contractQuery);
$contractStmt->bind_param("s", $name);
$contractStmt->execute();
$contractResult = $contractStmt->get_result();
$contract = $contractResult->fetch_assoc();
$end_date = $contract ? $contract['end_date'] : null; // fallback kung wala

// INSERT to parks
$sql = "INSERT INTO parks (name, address, city, country, pictures, description, what_to_expect, category, subcategory, end_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // 🔴 FIXED: 10 parameters dapat dito, mali yung bind_param mo kanina (9 lang)
    $stmt->bind_param("ssssssssss", $name, $address, $city, $country, $pictures, $description, $expect, $category, $subcategory, $end_date);

    if ($stmt->execute()) {
        // Link park to service_contracts
        $park_id = $stmt->insert_id;
        $updateLink = "UPDATE service_contracts SET park_id = ? WHERE park_name = ?";
        $linkStmt = $conn->prepare($updateLink);
        $linkStmt->bind_param("is", $park_id, $name);
        $linkStmt->execute();

        $_SESSION['add_park_success'] = true;
        header("Location: parkmanager.php");
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
} else {
    $message = "<div class='alert alert-danger'>Error preparing statement: " . $conn->error . "</div>";
}

}

// Show success message after redirect
if (isset($_SESSION['add_park_success'])) {
    echo "<div class='alert alert-success'>New park added successfully!</div>";
    unset($_SESSION['add_park_success']);
}

// Update Park
if (isset($_POST['update_park'])) {
    // Get data from form
    $id = $_POST['id']; // hidden input in modal
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $pictures = mysqli_real_escape_string($conn, $_POST['pictures']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $expect = mysqli_real_escape_string($conn, $_POST['what_to_expect']); // fixed name
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $subcategory = mysqli_real_escape_string($conn, $_POST['subcategory']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);

    // ✅ Update query (no start_date)
    $sql = "UPDATE parks 
            SET name=?, address=?, city=?, country=?, pictures=?, description=?, what_to_expect=?, category=?, subcategory=?, end_date=? 
            WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssssi",
        $name,
        $address,
        $city,
        $country,
        $pictures,
        $description,
        $expect,
        $category,
        $subcategory,
        $end_date,
        $id
    );

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Park updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Error updating park: " . $stmt->error . "</div>";
    }
}




// Delete Park
if (isset($_POST['delete_park'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);

    // Step 1: Delete from cart
    $deleteCart = $conn->prepare("DELETE FROM cart WHERE park_id=?");
    if ($deleteCart) {
        $deleteCart->bind_param("i", $id);
        $deleteCart->execute();
        $deleteCart->close();
    }

    // Step 2: Delete from park_tickets
    $deleteTickets = $conn->prepare("DELETE FROM park_tickets WHERE park_id=?");
    if ($deleteTickets) {
        $deleteTickets->bind_param("i", $id);
        $deleteTickets->execute();
        $deleteTickets->close();
    }

    // Step 3: Delete the park itself
    $deletePark = $conn->prepare("DELETE FROM parks WHERE id=?");
    if ($deletePark) {
        $deletePark->bind_param("i", $id);
        if ($deletePark->execute()) {
            if ($deletePark->affected_rows > 0) {
                $message = "<div class='alert alert-success'>Park deleted successfully!</div>";
            } else {
                $message = "<div class='alert alert-warning'>No park found with that ID.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Error deleting park: " . $deletePark->error . "</div>";
        }
        $deletePark->close();
    }
}

// Fetch Data
// Build the query with filters
$sql = "SELECT p.*, c.category_name, s.subcategory_name 
FROM parks p 
LEFT JOIN category c ON p.category = c.category_id 
LEFT JOIN subcategory s ON p.subcategory = s.subcategory_id 
WHERE 1=1";

if (isset($_GET['filter_category']) && !empty($_GET['filter_category'])) {
    $category = mysqli_real_escape_string($conn, $_GET['filter_category']);
    $sql .= " AND c.category_name = '$category'";
}
if (isset($_GET['filter_subcategory']) && !empty($_GET['filter_subcategory'])) {
    $subcategory = mysqli_real_escape_string($conn, $_GET['filter_subcategory']);
    $sql .= " AND s.subcategory_name = '$subcategory'";
}
$parks = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Park Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">
     <link rel="icon" type="image/x-icon" href="/images/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
     
<style>
    :root {
        --accent: #684D8F;
        --bg: #f7f7f8;
        --card: #fff;
        --text: #333;
        --muted: #666;
    }

    body {
        background: var(--bg);
        color: var(--text);
        font-family: 'Poppins', sans-serif;
    }

    .main-content {
        margin-left: 250px;
        padding: 20px;
    }

    .card { 
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
        border-radius: 8px;
        border: none;
    }

    img.park-img { 
        width: 70px; 
        height: 50px; 
        object-fit: cover; 
        border-radius: 5px; 
    }
    
    /* Park stats */
    .park-stats {
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
    
    /* Table */
    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    table thead {
        background: var(--accent);
        color: #fff;
    }
    
    .category-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .long-textarea {
        min-height: 120px;
        resize: vertical;
    }
    
    /* -------------------------------
       Add Park Form (new design merged)
    -------------------------------- */
    .form-section {
        background: var(--card);
        padding: 2rem;
        border-radius: 0.75rem;
        box-shadow: 0 6px 20px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from {opacity: 0; transform: translateY(10px);}
        to {opacity: 1; transform: translateY(0);}
    }

    .form-section h5 {
        margin-bottom: 1.5rem;
        color: var(--accent);
        font-weight: 600;
    }

    .form-label {
        font-weight: 500;
        font-size: 0.9rem;
        margin-bottom: 0.3rem;
        display: block;
        color: var(--muted);
    }

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

    .btn-primary {
        grid-column: 1 / -1;
        padding: 0.9rem;
        border: none;
        border-radius: 0.5rem;
        background: var(--accent);
        color: white;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: 0.2s ease;
        width: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(104,77,143,0.3);
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
    <!-- Main Content -->
    <div class="main-content">
        <?php if ($message) echo $message; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Park Manager</h2>
            <a href="ticketmanager.php" class="btn btn-success">
                <i class="fas fa-ticket-alt me-1"></i>Manage Tickets
            </a>
        </div>

        <!-- Park Statistics -->
        <?php
        $park_stats_sql = "SELECT 
            COUNT(*) as total_parks,
            COUNT(CASE WHEN category LIKE '%adventure%' THEN 1 END) as adventure_parks,
            COUNT(CASE WHEN category LIKE '%family%' THEN 1 END) as family_parks,
            COUNT(CASE WHEN city IS NOT NULL AND city != '' THEN 1 END) as parks_with_location
            FROM parks";
        $park_stats_result = $conn->query($park_stats_sql);
        $park_stats = $park_stats_result->fetch_assoc();
        ?>
        
        <div class="park-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($park_stats['total_parks']); ?></div>
                <div class="stat-label">Total Parks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($park_stats['adventure_parks']); ?></div>
                <div class="stat-label">Adventure Parks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($park_stats['family_parks']); ?></div>
                <div class="stat-label">Family Parks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($park_stats['parks_with_location']); ?></div>
                <div class="stat-label">With Locations</div>
            </div>
        </div>

        <div class="row">
            <!-- Add Park Form -->
            <div class="col-md-12">
                <div class="form-section">
                    <h5><i class="fas fa-plus-circle me-2"></i>Add New Park</h5>
                    <form method="POST" enctype="multipart/form-data" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Park Name</label>
<select name="name" id="park_name" class="form-control mb-2" required>
    <option value="">Select Park</option>
    <?php
    $parks = $conn->query("SELECT park_name, end_date FROM service_contracts WHERE status='active'");
    while($p = $parks->fetch_assoc()):
    ?>
        <option value="<?= $p['park_name'] ?>" data-end="<?= $p['end_date'] ?>">
            <?= $p['park_name'] ?>
        </option>
    <?php endwhile; ?>
</select>

                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" placeholder="Complete address" class="form-control mb-2" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" name="city" placeholder="City name" class="form-control mb-2" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Country</label>
                                <input type="text" name="country" placeholder="Country" class="form-control mb-2" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Upload Images</label>
                            <input type="file" name="park_images[]" class="form-control mb-2" accept="image/*" multiple required>
                            <small class="text-muted">Upload one or more images for the park.</small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Description</label>
                            <textarea name="description" placeholder="Park description" class="form-control mb-2 long-textarea"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">What to Expect</label>
                            <textarea name="expect" placeholder="What visitors can expect" class="form-control mb-2 long-textarea" maxlength="2000"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" id="category" class="form-control mb-2" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $cat_result = $conn->query("SELECT * FROM category");
                                    while($cat = $cat_result->fetch_assoc()):
                                    ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subcategory</label>
                                <select name="subcategory" id="subcategory" class="form-control mb-2" required>
                                    <option value="">Select Subcategory</option>
                                </select>
                            </div>
                            <div class="row">
    <div class="col-md-6">
    <label class="form-label">End Date</label>
    <input type="date" id="end_date" name="end_date" class="form-control mb-2" readonly required>
</div>

</div>

                        </div>
                        <button type="submit" name="add_park" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i>Add Park
                        </button>
                        <form method="POST" enctype="multipart/form-data">
                        <form method="POST" enctype="multipart/form-data">
                        <form method="POST" enctype="multipart/form-data">
                    </form>
                </div>
            </div>

            <!-- Parks Table -->
            <div class="col-md-12">
                <div class="card p-3">
                    <!-- Filter Form -->
                    <form id="filterForm" class="mb-4">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Filter by Category</label>
                                <select name="filter_category" id="filterCategory" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php
                                    $categories = $conn->query("SELECT DISTINCT c.category_name FROM category c INNER JOIN parks p ON c.category_id = p.category ORDER BY c.category_name");
                                    while($cat = $categories->fetch_assoc()) {
                                        $selected = (isset($_GET['filter_category']) && $_GET['filter_category'] === $cat['category_name']) ? 'selected' : '';
                                        echo "<option value='".htmlspecialchars($cat['category_name'])."' $selected>".htmlspecialchars($cat['category_name'])."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Filter by Subcategory</label>
                                <select name="filter_subcategory" id="filterSubcategory" class="form-select">
                                    <option value="">All Subcategories</option>
                                    <?php
                                    $subcategories = $conn->query("SELECT DISTINCT s.subcategory_name FROM subcategory s INNER JOIN parks p ON s.subcategory_id = p.subcategory ORDER BY s.subcategory_name");
                                    while($subcat = $subcategories->fetch_assoc()) {
                                        $selected = (isset($_GET['filter_subcategory']) && $_GET['filter_subcategory'] === $subcat['subcategory_name']) ? 'selected' : '';
                                        echo "<option value='".htmlspecialchars($subcat['subcategory_name'])."' $selected>".htmlspecialchars($subcat['subcategory_name'])."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </div>
                    </form>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="fas fa-list me-2"></i>All Parks</h5>
                        <small class="text-muted">
                            Showing <?php echo $parks->num_rows; ?> park(s)
                        </small>
                    </div>
                    <div class="table-responsive" id="parksTableContainer">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Park Name</th>
                                    <th>Location</th>
                                    <th>Images</th>
                                    <th>Category</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $parks->fetch_assoc()):
                                    $pics = array_filter(explode(',', $row['pictures'])); ?>
                                    <tr>
                                        <td><strong><?= $row['id'] ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($row['address']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['city']) ?>, <?= htmlspecialchars($row['country']) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($pics)): ?>
                                                <?php foreach(array_slice($pics, 0, 2) as $pic): ?>
                                                    <img src="<?= htmlspecialchars(trim($pic)) ?>" class="park-img me-1" alt="Park">
                                                <?php endforeach; ?>
                                                <?php if (count($pics) > 2): ?>
                                                    <small class="text-muted">+<?= count($pics) - 2 ?> more</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small class="text-muted">No images</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="category-badge">
                                                <?= htmlspecialchars($row['category_name']) ?>
                                            </span>
                                            <?php if ($row['subcategory_name']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($row['subcategory_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <button class="btn btn-warning btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#updateModal"
                                                    data-id="<?= $row['id'] ?>"
                                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                                    data-address="<?= htmlspecialchars($row['address']) ?>"
                                                    data-city="<?= htmlspecialchars($row['city']) ?>"
                                                    data-country="<?= htmlspecialchars($row['country']) ?>"
                                                    data-pictures="<?= htmlspecialchars($row['pictures']) ?>"
                                                    data-description="<?= htmlspecialchars($row['description']) ?>"
                                                    data-expect="<?= htmlspecialchars($row['what_to_expect']) ?>"
                                                    data-category="<?= htmlspecialchars($row['category']) ?>"
                                                    data-subcategory="<?= htmlspecialchars($row['subcategory']) ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display:inline-block">
                                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="delete_park" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this park?')">
                                                        <i class="fas fa-trash"></i> Delete
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
            </div>
        </div>
    </div>
<script>
document.getElementById('park_name').addEventListener('change', function () {
    var selected = this.options[this.selectedIndex];
    var endDate = selected.getAttribute('data-end');
    document.getElementById('end_date').value = endDate ? endDate : '';
});
</script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterCategory = document.getElementById('filterCategory');
        const filterSubcategory = document.getElementById('filterSubcategory');
        const tableContainer = document.getElementById('parksTableContainer');

        function updateSubcategories(category) {
            // Reset subcategory dropdown
            filterSubcategory.innerHTML = '<option value="">All Subcategories</option>';
            
            if (!category) {
                return;
            }

            // Fetch subcategories for selected category
            fetch(`get_subcategories_by_category.php?category=${encodeURIComponent(category)}`)
                .then(response => response.json())
                .then(subcategories => {
                    subcategories.forEach(subcategory => {
                        const option = document.createElement('option');
                        option.value = subcategory;
                        option.textContent = subcategory;
                        filterSubcategory.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching subcategories:', error);
                });
        }

        function updateTable() {
            const category = filterCategory.value;
            const subcategory = filterSubcategory.value;

            // Show loading indicator
            tableContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';

            // Create URL with parameters
            const params = new URLSearchParams();
            if (category) params.append('filter_category', category);
            if (subcategory) params.append('filter_subcategory', subcategory);

            // Fetch filtered data
            fetch(`get_filtered_parks.php?${params.toString()}`)
                .then(response => response.text())
                .then(html => {
                    tableContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    tableContainer.innerHTML = '<div class="alert alert-danger">Error loading data</div>';
                });
        }

        // Add event listeners to filters
        filterCategory.addEventListener('change', function() {
            updateSubcategories(this.value);
            updateTable();
        });
        filterSubcategory.addEventListener('change', updateTable);
    });
    </script>

    <!-- Modal for Editing Park -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Park</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="update-id">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label>Park Name</label>
                                <input type="text" name="name" id="update-name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Address</label>
                                <input type="text" name="address" id="update-address" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label>City</label>
                                <input type="text" name="city" id="update-city" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Country</label>
                                <input type="text" name="country" id="update-country" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label>Pictures</label>
                            <textarea name="pictures" id="update-pictures" class="form-control"></textarea>
                        </div>
                        <div class="mb-2">
                            <label>Description</label>
                            <textarea name="description" id="update-description" class="form-control long-textarea"></textarea>
                        </div>
                        <div class="mb-2">
                            <label>What to Expect</label>
                            <textarea name="expect" id="update-expect" class="form-control long-textarea" maxlength="2000"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label>Category</label>
                                <input type="text" name="category" id="update-category" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Subcategory</label>
                                <input type="text" name="subcategory" id="update-subcategory" class="form-control">
                            </div>
                            <div class="col-md-6">
    <label class="form-label">End Date</label>
    <input type="date" name="end_date" class="form-control mb-2" 
        value="<?= htmlspecialchars($park['end_date']) ?>">
</div>

                        </div>
                        <button type="submit" name="update_park" class="btn btn-primary">Update Park</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
const updateModal = document.getElementById('updateModal');
updateModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('update-id').value = button.getAttribute('data-id');
    document.getElementById('update-name').value = button.getAttribute('data-name');
    document.getElementById('update-address').value = button.getAttribute('data-address');
    document.getElementById('update-city').value = button.getAttribute('data-city');
    document.getElementById('update-country').value = button.getAttribute('data-country');
    document.getElementById('update-pictures').value = button.getAttribute('data-pictures');
    document.getElementById('update-description').value = button.getAttribute('data-description');
    document.getElementById('update-expect').value = button.getAttribute('data-expect');
    document.getElementById('update-category').value = button.getAttribute('data-category');
    document.getElementById('update-subcategory').value = button.getAttribute('data-subcategory');
});

// Category-Subcategory dropdown functionality
document.getElementById('category').addEventListener('change', function() {
    let categoryId = this.value;
    let subcategoryDropdown = document.getElementById('subcategory');
    subcategoryDropdown.innerHTML = '<option value="">Loading...</option>';

    if(categoryId !== "") {
        fetch('get_subcategories.php?category_id=' + categoryId)
            .then(response => response.json())
            .then(data => {
                subcategoryDropdown.innerHTML = '<option value="">Select Subcategory</option>';
                data.forEach(subcat => {
                    subcategoryDropdown.innerHTML += `<option value="${subcat.subcategory_id}">${subcat.subcategory_name}</option>`;
                });
            })
            .catch(error => {
                console.error('Error:', error);
                subcategoryDropdown.innerHTML = '<option value="">Error loading subcategories</option>';
            });
    } else {
        subcategoryDropdown.innerHTML = '<option value="">Select Subcategory</option>';
    }
});

// Character counter for long text areas
document.querySelectorAll('textarea[maxlength]').forEach(function(textarea) {
    textarea.addEventListener('input', function() {
        const maxLength = this.getAttribute('maxlength');
        const currentLength = this.value.length;
        
        // Find or create character counter
        let counter = this.parentNode.querySelector('.char-counter');
        if (!counter) {
            counter = document.createElement('small');
            counter.className = 'char-counter text-muted';
            this.parentNode.appendChild(counter);
        }
        
        counter.textContent = `${currentLength}/${maxLength} characters`;
        
        if (currentLength >= maxLength * 0.9) {
            counter.classList.add('text-warning');
        } else {
            counter.classList.remove('text-warning');
        }
    });
});
</script>
</body>
</html>

<?php $conn->close(); ?>