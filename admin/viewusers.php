<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection
$mysqli = require __DIR__ . "/../database.php";

// Start the session at the top of your page
session_start();

if (!isset($_SESSION["admin_first_name"])) {
    header("Location: index.php");  // Redirect to login page if not logged in
    exit;
}

// Access admin's name
$first_name = $_SESSION["admin_first_name"];
$last_name = $_SESSION["admin_last_name"];

// Handle add user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Fetch the form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $park_id = $_POST['park_id'] ?? null; // Optional park ID for vendors

    // Validation
    $errors = [];
    
    if (empty($firstname)) {
        $errors[] = "First name is required.";
    }
    if (empty($lastname)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    
    // Check for duplicate email in all user tables
    if (empty($errors)) {
        $tables_to_check = ['user', 'vendor', 'adminuser'];
        $email_exists = false;
        
        foreach ($tables_to_check as $table) {
            $check_sql = "SELECT id FROM $table WHERE email = ?";
            $check_stmt = $mysqli->prepare($check_sql);
            if ($check_stmt) {
                $check_stmt->bind_param('s', $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $email_exists = true;
                    $errors[] = "Email already exists in the system.";
                    break;
                }
                $check_stmt->close();
            }
        }
    }
    
    // If no errors, proceed with insertion
    if (empty($errors)) {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert based on role
        if ($role === 'vendor') {
            $insert_sql = "INSERT INTO vendor (firstname, lastname, email, password_hash, park_id, last_login, is_active) VALUES (?, ?, ?, ?, ?, NULL, 1)";
            $stmt = $mysqli->prepare($insert_sql);
            $stmt->bind_param('ssssi', $firstname, $lastname, $email, $password_hash, $park_id);
        } elseif ($role === 'admin') {
            $insert_sql = "INSERT INTO adminuser (first_name, last_name, email, password_hash, last_login, is_active) VALUES (?, ?, ?, ?, NULL, 1)";
            $stmt = $mysqli->prepare($insert_sql);
            $stmt->bind_param('ssss', $firstname, $lastname, $email, $password_hash);
        } else {
            $insert_sql = "INSERT INTO user (firstname, lastname, email, password_hash, last_login, is_active) VALUES (?, ?, ?, ?, NULL, 1)";
            $stmt = $mysqli->prepare($insert_sql);
            $stmt->bind_param('ssss', $firstname, $lastname, $email, $password_hash);
        }

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>User added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }

        $stmt->close();
    } else {
        // Display validation errors
        $message = "<div class='alert alert-danger'>Please fix the following errors:<br>" . implode("<br>", $errors) . "</div>";
    }
}

// Handle edit user submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = $_POST['id'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $is_active = $_POST['is_active']; // Get active status

    // Validation
    $errors = [];
    
    if (empty($firstname)) {
        $errors[] = "First name is required.";
    }
    if (empty($lastname)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    // Check for duplicate email in all user tables (excluding current user)
    if (empty($errors)) {
        $tables_to_check = ['user', 'vendor', 'adminuser'];
        $email_exists = false;
        
        foreach ($tables_to_check as $table) {
            // For different table structures, we need to handle different id column names
            $id_column = ($table === 'adminuser') ? 'id' : 'id';
            $check_sql = "SELECT id FROM $table WHERE email = ? AND id != ?";
            $check_stmt = $mysqli->prepare($check_sql);
            
            if ($check_stmt) {
                $check_stmt->bind_param('si', $email, $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $email_exists = true;
                    $errors[] = "Email already exists in the system.";
                    break;
                }
                $check_stmt->close();
            }
        }
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        $update_sql = "UPDATE user SET firstname = ?, lastname = ?, email = ?, is_active = ? WHERE id = ?";
        $stmt = $mysqli->prepare($update_sql);
        $stmt->bind_param('ssiii', $firstname, $lastname, $email, $is_active, $id);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>User updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }

        $stmt->close();
    } else {
        // Display validation errors
        $message = "<div class='alert alert-danger'>Please fix the following errors:<br>" . implode("<br>", $errors) . "</div>";
    }
}

// Handle delete user with proper foreign key handling
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Start transaction for safe deletion
    $mysqli->begin_transaction();
    
    try {
        // Delete related records first to avoid foreign key constraints
        // Only include tables that actually exist in your database
        $related_tables = [
            "DELETE FROM cart WHERE user_id = ?",
            "DELETE FROM orders WHERE user_id = ?", 
            "DELETE FROM wishlist WHERE user_id = ?",
            "DELETE FROM reviews WHERE user_id = ?",
            "DELETE FROM promo_redemptions WHERE user_id = ?",
            "DELETE FROM feedback WHERE user_id = ?"
        ];
        
        // Execute deletions for related tables
        foreach ($related_tables as $sql) {
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $delete_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Finally delete the user
        $delete_user_sql = "DELETE FROM user WHERE id = ?";
        $stmt = $mysqli->prepare($delete_user_sql);
        $stmt->bind_param('i', $delete_id);
        
        if ($stmt->execute()) {
            $mysqli->commit(); // Commit all deletions
            $message = "<div class='alert alert-success'>User and all related data deleted successfully!</div>";
        } else {
            throw new Exception("Failed to delete user: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $mysqli->rollback(); // Rollback if any error occurs
        $message = "<div class='alert alert-danger'>Error deleting user: " . $e->getMessage() . "</div>";
    }
}

// Pagination configuration
$users_per_page = 10; // Set how many users you want to display per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $users_per_page;

// Initialize search variables
$search_by_id = isset($_GET['search_id']) ? trim($_GET['search_id']) : '';
$search_by_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// User query logic
if ($search_by_id) {
    $sql = "SELECT * FROM (
                SELECT id, firstname, lastname, email, last_login, is_active, 'user' AS role FROM user
                UNION ALL
                SELECT id, firstname, lastname, email, last_login, is_active, 'vendor' AS role FROM vendor
                UNION ALL
                SELECT id, first_name AS firstname, last_name AS lastname, email, last_login, is_active, 'admin' AS role FROM adminuser
            ) AS all_users WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $search_by_id);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif ($search_by_name) {
    $sql = "SELECT * FROM (
                SELECT id, firstname, lastname, email, last_login, is_active, 'user' AS role FROM user
                UNION ALL
                SELECT id, firstname, lastname, email, last_login, is_active, 'vendor' AS role FROM vendor
                UNION ALL
                SELECT id, first_name AS firstname, last_name AS lastname, email, last_login, is_active, 'admin' AS role FROM adminuser
            ) AS all_users WHERE firstname LIKE ? OR lastname LIKE ? OR email LIKE ? LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    $like_search = "%" . $search_by_name . "%";
    $stmt->bind_param('ssssi', $like_search, $like_search, $like_search, $users_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Original query without search
    $sql = "SELECT * FROM (
                SELECT id, firstname, lastname, email, last_login, is_active, 'user' AS role FROM user
                UNION
                SELECT id, firstname, lastname, email, last_login, is_active, 'vendor' AS role FROM vendor
                UNION
                SELECT id, first_name AS firstname, last_name AS lastname, email, last_login, is_active, 'admin' AS role FROM adminuser
            ) AS all_users LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $users_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Count total users
$total_users_query = "
    SELECT 
        (SELECT COUNT(*) FROM user) +
        (SELECT COUNT(*) FROM vendor) +
        (SELECT COUNT(*) FROM adminuser) AS total
";
$total_users_stmt = $mysqli->prepare($total_users_query);
$total_users_stmt->execute();
$total_users = $total_users_stmt->get_result()->fetch_assoc()['total'];

$total_pages = ceil($total_users / $users_per_page);

// Fetch parks for vendor assignment
$parks_sql = "SELECT id, name FROM parks ORDER BY name";
$parks_result = $mysqli->query($parks_sql);
$parks = [];
if ($parks_result) {
    $parks = $parks_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Maintenance</title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="CSS/sidebar.css" rel="stylesheet">
    <link href="CSS/dashboard.css" rel="stylesheet">
    <link href="CSS/viewusers.css" rel="stylesheet">
    
    <style>
 /* Root colors */
:root {
    --primary: #684D8F;
    --primary-dark: #563E77;
    --success: #28a745;
    --danger: #dc3545;
    --inactive: #6c757d;
    --bg-light: #f5f5f5;
    --table-header-bg: #fff;
    --table-border: #ddd;
}

/* Main content area */
.main-content {
    margin-left: 250px; /* add margin to account for sidebar */
    padding: 40px 20px;
    width: calc(100% - 250px);
    background: var(--bg-light);
    min-height: 100vh;
    font-family: Arial, sans-serif;
}

/* Ensure h2 stays centered */
/* Main content area */
.main-content {
    margin-left: 250px; /* account for sidebar */
    padding: 40px 20px;
    width: calc(100% - 250px);
    background: var(--bg-light);
    min-height: 100vh;
    font-family: Arial, sans-serif;
}

/* Ensure h2 stays perfectly centered */
.main-content h2.text-center {
    width: 100%;         /* full width of main-content */
    text-align: center;  /* center text */
    margin: 0 0 30px 0;  /* keep bottom spacing */
    position: relative;  /* prevent shifts from flex children */
    z-index: 1;          /* stays above other elements if needed */
}


/* Admin greeting */
.admin-name {
    font-size: 1rem;
    color: var(--primary-dark);
    margin-bottom: 20px;
    text-align: right;
}

/* Search forms */
.main-content .d-flex form input[type="text"] {
    border-radius: 6px;
    border: 1px solid #ccc;
    padding: 8px 12px;
    font-size: 0.95rem;
    width: 300px;
    transition: border 0.2s, box-shadow 0.2s;
}

.main-content .d-flex form input[type="text"]:focus {
    border-color: var(--primary);
    box-shadow: 0 0 4px rgba(104, 77, 143, 0.4);
    outline: none;
}

.main-content .d-flex form button,
.main-content .d-flex > button {
    border-radius: 6px;
    padding: 8px 16px;
    font-size: 0.95rem;
    color: #fff;
    background: var(--primary);
    border: none;
    cursor: pointer;
    margin-left: 4px;
    transition: background 0.2s, transform 0.1s;
}

.main-content .d-flex form button:hover,
.main-content .d-flex > button:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

/* Table styling */
#usersTable {
    width: 100%;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    background: #fff;
}

#usersTable thead {
    background: var(--primary);
    color: #fff;
}

#usersTable thead th {
    text-align: left;
    padding: 12px 16px;
    font-weight: 600;
    font-size: 14px;
}

#usersTable tbody tr {
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}

#usersTable tbody tr:nth-child(even) {
    background: #fafafa;
}

#usersTable tbody tr:hover {
    background: #f1ecf8;
}

#usersTable tbody td {
    padding: 12px 16px;
    font-size: 14px;
    color: #333;
    vertical-align: middle;
}

/* Status badges */
#usersTable tbody td:nth-child(6) {
    font-weight: 600;
    text-align: center;
}

.status-active {
    background: var(--success);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
}

.status-inactive {
    background: var(--inactive);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
}

/* Role buttons inside table */
#usersTable tbody td:nth-child(7) .btn {
    font-size: 0.85rem;
    padding: 4px 8px;
    margin: 0 2px;
    transition: background 0.2s, transform 0.1s;
}

.btn-edit {
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 6px;
}

.btn-edit:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-delete {
    background: var(--danger);
    color: #fff;
    border: none;
    border-radius: 6px;
}

.btn-delete:hover {
    background: #b52a36;
    transform: translateY(-1px);
}

/* Pagination */
.main-content .btn-light,
.main-content .btn-secondary,
.main-content .btn-primary {
    border-radius: 6px;
    padding: 6px 12px;
    margin: 2px;
    text-decoration: none;
    transition: background 0.2s, transform 0.1s;
}

.main-content .btn-light.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.main-content .btn-primary {
    background: var(--primary);
    color: #fff;
    border: none;
}

.main-content .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.main-content .btn-secondary {
    background: #6c757d;
    color: #fff;
    border: none;
}

.main-content .btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

/* Modals */
.modal-content {
    border-radius: 12px;
}

.modal-header,
.modal-footer {
    border-color: #eee;
}

.modal-body input,
.modal-body select {
    border-radius: 6px;
    border: 1px solid #ccc;
    padding: 6px 10px;
    font-size: 0.95rem;
    transition: border 0.2s, box-shadow 0.2s;
}

.modal-body input:focus,
.modal-body select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 4px rgba(104, 77, 143, 0.4);
    outline: none;
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
    
    <div id="alertContainer">
        <?php if (isset($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>
    </div>

    <h2 class="text-center">Users Manager</h2>
    
    <div class="mb-3 d-flex justify-content-center align-items-start">
        <form method="GET" action="" class="d-flex me-2">
            <input type="text" id="searchInput" name="search_id" class="form-control me-2" 
                   placeholder="Search by ID" value="<?php echo htmlspecialchars($search_by_id); ?>" 
                   style="width: 300px;">
            <button type="submit" class="btn btn-primary">Search ID</button>
        </form>

        <form method="GET" action="" class="d-flex me-2">
            <input type="text" id="searchInputName" name="search_name" class="form-control me-2" 
                   placeholder="Search by Name/Email/Role" value="<?php echo htmlspecialchars($search_by_name); ?>" 
                   style="width: 300px;">
            <button type="submit" class="btn btn-primary">Search Name/Email</button>
        </form>

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            Add User
        </button>
    </div>

    <?php if ($search_by_id || $search_by_name): ?>
        <div class="mb-3 d-flex justify-content-center">
            <a href="viewusers.php" class="btn btn-secondary">Back to All Users</a>
        </div>
    <?php endif; ?>

    <table class="table table-bordered" id="usersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Last Login</th> <!-- New column for last login -->
                <th>Status</th>     <!-- New column for status -->
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr data-id='" . $row['id'] . "'>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td class='firstname'>" . htmlspecialchars($row['firstname']) . "</td>";
                    echo "<td class='lastname'>" . htmlspecialchars($row['lastname']) . "</td>";
                    echo "<td class='email'>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>" . (isset($row['last_login']) ? date('F j, Y, g:i a', strtotime($row['last_login'])) : 'N/A') . "</td>";
                    echo "<td>" . ($row['is_active'] ? 'Active' : 'Inactive') . "</td>"; // Assuming 'is_active' indicates status
                    echo "<td>";
                    echo "<button class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#editUserModal' 
                        data-id='" . $row['id'] . "' 
                        data-firstname='" . htmlspecialchars($row['firstname']) . "' 
                        data-lastname='" . htmlspecialchars($row['lastname']) . "' 
                        data-email='" . htmlspecialchars($row['email']) . "' 
                        data-is-active='" . $row['is_active'] . "'>Edit</button> ";
                    echo "<button class='btn btn-danger btn-sm deleteUserBtn' data-id='" . $row['id'] . "'>Delete</button>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' class='text-center'>No users found.</td></tr>"; // Adjusted colspan
            }
            ?>
        </tbody>
    </table>

    <?php if (empty($search_by_name) && empty($search_by_id)): ?>
        <div class="d-flex justify-content-center">
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?>" class="btn btn-secondary me-2">Previous</a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);

            if ($start_page > 1) {
                echo '<a href="?page=1" class="btn btn-light">1</a>';
                if ($start_page > 2) {
                    echo '<span class="btn btn-light disabled">...</span>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search_name=<?php echo urlencode($search_by_name); ?>" class="btn <?php echo ($i === $current_page) ? 'btn-primary' : 'btn-light'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="btn btn-light disabled">...</span>';
                }
                echo '<a href="?page=' . $total_pages . '" class="btn btn-light">' . $total_pages . '</a>';
            } ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?>" class="btn btn-secondary ms-2">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" id="userId" name="id">
                    <div class="mb-3">
                        <label for="firstname" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label for="lastname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="is_active" class="form-label">Active Status</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="mb-3">
                        <label for="add_firstname" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="add_firstname" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_lastname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="add_lastname" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="add_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="add_password" name="password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label for="add_role" class="form-label">Role</label>
                        <select class="form-select" id="add_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="user">User</option>
                            <option value="vendor">Vendor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3" id="vendor_park" style="display: none;">
                        <label for="add_park" class="form-label">Select Park</label>
                        <select class="form-select" id="add_park" name="park_id">
                            <option value="">Select Park</option>
                            <?php foreach ($parks as $park): ?>
                                <option value="<?php echo $park['id']; ?>"><?php echo htmlspecialchars($park['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addUserForm" class="btn btn-primary">Add User</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Edit User Modal Setup
        var editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var firstname = button.getAttribute('data-firstname');
            var lastname = button.getAttribute('data-lastname');
            var email = button.getAttribute('data-email');
            var isActive = button.getAttribute('data-is-active');

            // Populate the modal fields
            editUserModal.querySelector('#userId').value = id;
            editUserModal.querySelector('#firstname').value = firstname;
            editUserModal.querySelector('#lastname').value = lastname;
            editUserModal.querySelector('#email').value = email;
            editUserModal.querySelector('#is_active').value = isActive; // Set active status
        });

        // Role Change for Add User
        var roleSelect = document.getElementById('add_role');
        var vendorPark = document.getElementById('vendor_park');

        roleSelect.addEventListener('change', function() {
            const role = this.value;
            vendorPark.style.display = (role === 'vendor') ? 'block' : 'none'; // Show park dropdown for vendors
        });

        // Set default state for vendor note and park
        const initialRole = roleSelect.value;
        vendorPark.style.display = (initialRole === 'vendor') ? 'block' : 'none';

        // Delete User Confirmation
        var deleteButtons = document.querySelectorAll('.deleteUserBtn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function () {
                var userId = this.getAttribute('data-id');
                var confirmMessage = 'Are you sure you want to delete this user?\n\n' +
                                   'This will permanently delete:\n' +
                                   '• User account and profile\n' +
                                   '• All bookings and orders\n' +
                                   '• Wishlist items\n' +
                                   '• Reviews and feedback\n' +
                                   '• Cart items\n' +
                                   '• All related user data\n\n' +
                                   'This action CANNOT be undone!';
                
                if (confirm(confirmMessage)) {
                    window.location.href = 'viewusers.php?delete_id=' + userId; // Redirect to delete
                }
            });
        });
    });
</script>
</body>
</html>