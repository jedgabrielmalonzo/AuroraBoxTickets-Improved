<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection (centralized)
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
    die('Database connection failed.');
}
$conn = $mysqli;

// FIXED: Consistent paths
$bannerDir = "banners/";  // Relative path for both upload and display
$uploadDir = __DIR__ . "/" . $bannerDir;  // Full physical path for upload

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// TEMPORARY DEBUG - Lagay mo to para makita natin
if (isset($_POST['upload_banner'])) {
    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
    echo "<h3>DEBUG INFO:</h3>";
    echo "Upload Dir: " . $uploadDir . "<br>";
    echo "Upload Dir exists: " . (is_dir($uploadDir) ? 'YES' : 'NO') . "<br>";
    echo "Upload Dir writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "<br>";
    echo "POST data: "; print_r($_POST); echo "<br>";
    echo "FILES data: "; print_r($_FILES); echo "<br>";
    echo "</div>";
}

// Check for session messages first
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
} else {
    $message = "";
}

// Handle display order updates
if (isset($_POST['update_order'])) {
    foreach ($_POST['banner_order'] as $id => $order) {
        $stmt = $conn->prepare("UPDATE banners SET display_order = ? WHERE id = ?");
        $stmt->bind_param("ii", $order, $id);
        $stmt->execute();
        $stmt->close();
    }
    $_SESSION['message'] = "<div class='alert alert-success'>Display order updated!</div>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle banner upload
if (isset($_POST['upload_banner'])) {
    $errors = [];
    $success = false;
    
    // Check if file was uploaded
    if (!isset($_FILES['banner_file']) || $_FILES['banner_file']['error'] !== 0) {
        $upload_errors = [
            1 => 'File too large (server limit)',
            2 => 'File too large (form limit)', 
            3 => 'File partially uploaded',
            4 => 'No file selected',
            6 => 'Temporary directory missing',
            7 => 'Cannot write to disk',
            8 => 'Upload blocked by extension'
        ];
        $errors[] = $upload_errors[$_FILES['banner_file']['error']] ?? 'Upload error occurred';
    } else {
        $filename = basename($_FILES['banner_file']['name']);
        $targetFile = $uploadDir . $filename;
        $title = trim($_POST['banner_title'] ?? '');
        $description = trim($_POST['banner_description'] ?? '');

        // Validate file extension
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WebP allowed.";
        }

        // Check file size (5MB limit)
        if ($_FILES['banner_file']['size'] > 5 * 1024 * 1024) {
            $errors[] = "File too large. Maximum size is 5MB.";
        }

        // Check if file already exists
        if (file_exists($targetFile)) {
            $errors[] = "File with this name already exists!";
        }

        // If no errors, proceed with upload
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $targetFile)) {
                // Check if filename exists in database
                $checkStmt = $conn->prepare("SELECT id FROM banners WHERE filename = ?");
                $checkStmt->bind_param("s", $filename);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $errors[] = "Banner with this filename already exists in database!";
                    unlink($targetFile); // Remove the uploaded file
                } else {
                    // Insert into database
                    $stmt = $conn->prepare("INSERT INTO banners (filename, title, description, status, display_order) VALUES (?, ?, ?, 'active', 0)");
                    $stmt->bind_param("sss", $filename, $title, $description);
                    
                    if ($stmt->execute()) {
                        $success = true;
                    } else {
                        $errors[] = "Database error: " . $stmt->error;
                        unlink($targetFile); // Remove file if DB failed
                    }
                    $stmt->close();
                }
                $checkStmt->close();
            } else {
                $errors[] = "Failed to move uploaded file to destination.";
            }
        }
    }
    
    // Set session message
    if ($success) {
        $_SESSION['message'] = "<div class='alert alert-success'>Banner uploaded successfully!</div>";
    } else {
        $error_msg = "<div class='alert alert-danger'>Upload failed:<ul>";
        foreach ($errors as $error) {
            $error_msg .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $error_msg .= "</ul></div>";
        $_SESSION['message'] = $error_msg;
    }
    
    // Redirect to prevent double submission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Add Founder
if (isset($_POST['add_founder'])) {
    $name = $_POST['name'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = 'active';

    // Upload Image
    $image = null;
    if (!empty($_FILES['founder_image']['name'])) {
        $targetDir = "../uploads/founders/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $filename = time() . "_" . basename($_FILES['founder_image']['name']);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['founder_image']['tmp_name'], $targetFile)) {
            $image = $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO about_founders (name, title, description, image, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $title, $description, $image, $status);
    $stmt->execute();

    header("Location: banners.php");
    exit;
}

if (isset($_POST['update_founder'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $title = $_POST['title'];
    $description = $_POST['description'];

    $image = null;
    if (!empty($_FILES['founder_image']['name'])) {
        $targetDir = "../uploads/founders/";
        $image = time() . "_" . basename($_FILES['founder_image']['name']);
        $targetFile = $targetDir . $image;
        move_uploaded_file($_FILES['founder_image']['tmp_name'], $targetFile);

        // update with image
        $stmt = $conn->prepare("UPDATE about_founders SET name=?, title=?, description=?, image=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $title, $description, $image, $id);
    } else {
        // update without image
        $stmt = $conn->prepare("UPDATE about_founders SET name=?, title=?, description=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $title, $description, $id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Founder updated successfully!'); window.location.href='aboutus.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}



// Handle Add Section
if (isset($_POST['update_section'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    $stmt = $conn->prepare("UPDATE about_sections SET title=?, content=? WHERE id=?");
    $stmt->bind_param("ssi", $title, $content, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Section updated successfully!'); window.location.href='banners.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}


// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM about_sections WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: sectionsmanager.php");
    exit;
}

$result = $conn->query("SELECT * FROM about_sections ORDER BY id ASC");
$sections = $result->fetch_all(MYSQLI_ASSOC);

$result = $conn->query("SELECT * FROM about_founders ORDER BY display_order ASC, id DESC");
$founders = $result->fetch_all(MYSQLI_ASSOC);

// Fetch banners from database
$result = $conn->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC");
$banners = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Banner Manager</title>
<link rel="icon" type="image/x-icon" href="../images/favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="CSS/sidebar.css" rel="stylesheet">
<link href="CSS/banner.css" rel="stylesheet">
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
    <h2>Banner Manager</h2>
    
    <!-- Enhanced message display with auto-hide -->
    <?php if ($message): ?>
        <div class="message-container mb-3" id="messageContainer">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Enhanced Upload Form -->
    <div class="card p-3 mb-4">
        <h5>Upload New Banner</h5>
        <form method="POST" enctype="multipart/form-data" id="bannerForm">
            <div class="mb-2">
                <label class="form-label">Banner Title (Optional)</label>
                <input type="text" name="banner_title" class="form-control" placeholder="Enter banner title" maxlength="255">
            </div>
            <div class="mb-2">
                <label class="form-label">Banner Description (Optional)</label>
                <textarea name="banner_description" class="form-control" rows="2" placeholder="Enter banner description" maxlength="500"></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Select Banner Image</label>
                <input type="file" name="banner_file" class="form-control" required accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <div class="form-text">Maximum file size: 5MB. Supported formats: JPG, PNG, GIF, WebP</div>
            </div>
            <button type="submit" name="upload_banner" class="btn btn-primary w-100" id="uploadBtn">
                Upload Banner
            </button>
        </form>
    </div>

    <!-- Enhanced Banner List -->
    <div class="card p-3">
        <h5>Manage Banners <small class="text-muted">(Drag to reorder)</small></h5>
        
        <?php if(!empty($banners)): ?>
            <form method="POST" id="orderForm">
                <div id="banner-list" class="row sortable-list">
                    <?php foreach($banners as $banner): ?>
                    <div class="col-md-4 mb-3 banner-item" data-id="<?= $banner['id'] ?>">
                        <div class="card text-center p-2" style="cursor: move;">
                            <div class="drag-handle" title="Drag to reorder">⋮⋮</div>
                            
                            <img src="<?= $bannerDir . htmlspecialchars($banner['filename']) ?>" 
                                 class="banner-thumb" 
                                 alt="Banner"
                                 onerror="this.src='../images/placeholder.png'">
                            
                            <?php if (!empty($banner['title'])): ?>
                                <h6 class="mt-2 text-truncate" title="<?= htmlspecialchars($banner['title']) ?>">
                                    <?= htmlspecialchars($banner['title']) ?>
                                </h6>
                            <?php endif; ?>
                            
                            <p class="small text-muted text-truncate" title="<?= htmlspecialchars($banner['filename']) ?>">
                                <?= htmlspecialchars($banner['filename']) ?>
                            </p>
                            
                            <?php if (!empty($banner['description'])): ?>
                                <p class="small text-truncate" title="<?= htmlspecialchars($banner['description']) ?>">
                                    <?= htmlspecialchars($banner['description']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <label class="form-label small">Order:</label>
                                <input type="number" name="banner_order[<?= $banner['id'] ?>]" 
                                       value="<?= $banner['display_order'] ?>" 
                                       class="form-control form-control-sm" min="0" max="999">
                            </div>
                            
                            <div class="actions">
                                <a href="toggle_banner.php?id=<?= $banner['id'] ?>" 
                                   class="btn btn-sm btn-<?= $banner['status']=='active'?'success':'secondary' ?>"
                                   title="Toggle status">
                                    <?= $banner['status']=='active'?'Active':'Inactive' ?>
                                </a>
                                <a href="delete_banner.php?id=<?= $banner['id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this banner? This action cannot be undone.')"
                                   title="Delete banner">Delete</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-3">
                    <button type="submit" name="update_order" class="btn btn-primary" id="updateOrderBtn">
                        Update Display Order
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center py-5">
                <img src="../images/no-banners.svg" alt="No banners" class="mb-3" style="width: 100px; opacity: 0.5;">
                <h6 class="text-muted">No banners yet</h6>
                <p class="text-muted">Upload your first banner above to get started!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <h2>Founders Manager</h2>

    <!-- Add New Founder -->
    <div class="card p-3 mb-4">
        <h5>Add New Founder</h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-2">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-2">
    <label class="form-label">Title</label>
    <select name="title" class="form-control" required>
        <option value="">-- Select Title --</option>
        <option value="CEO">CEO</option>
        <option value="CTO">CTO</option>
        <option value="COO">COO</option>
        <option value="Head of Marketing">Head of Marketing</option>
        <option value="Head of Design">Head of Design</option>
    </select>
</div>

            <div class="mb-2">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Image</label>
                <input type="file" name="founder_image" class="form-control" accept="image/*">
            </div>
            <button type="submit" name="add_founder" class="btn btn-primary">Save Founder</button>
        </form>
    </div>

    <!-- List Founders -->
    <div class="card p-3">
        <h5>Existing Founders</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($founders as $f): ?>
                <tr>
                    <td>
                        <?php if ($f['image']): ?>
                            <img src="../uploads/founders/<?= htmlspecialchars($f['image']) ?>" style="height:50px">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['name']) ?></td>
                    <td><?= htmlspecialchars($f['title']) ?></td>
                    <td><?= htmlspecialchars($f['description']) ?></td>
                    <td><?= htmlspecialchars($f['status']) ?></td>
                    <td>
    <!-- Edit Button -->
    <button 
        class="btn btn-sm btn-warning edit-founder-btn"
        data-id="<?= $f['id'] ?>"
        data-name="<?= htmlspecialchars($f['name']) ?>"
        data-title="<?= htmlspecialchars($f['title']) ?>"
        data-description="<?= htmlspecialchars($f['description']) ?>"
        data-image="<?= htmlspecialchars($f['image']) ?>"
        data-bs-toggle="modal"
        data-bs-target="#editFounderModal">
        Edit
    </button>

    <!-- Delete Button -->
    <a href="?delete=<?= $f['id'] ?>" 
       class="btn btn-sm btn-danger" 
       onclick="return confirm('Delete this founder?')">
       Delete
    </a>
</td>

                </tr>
                <?php endforeach; ?>
                <?php if (empty($founders)): ?>
                <tr><td colspan="6">No founders yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
     <h2>About Sections Manager</h2>

    <!-- Add New Section -->
    <div class="card p-3 mb-4">
        <h5>Add New Section</h5>
        <form method="POST">
            <div class="mb-2">
                <label class="form-label">Section</label>
                <select name="section_key" class="form-control" required>
                    <option value="">-- Select Section --</option>
                    <option value="mission">Mission</option>
                    <option value="offer">What We Offer</option>
                    <option value="support">Customer Support</option>
                    <option value="global">Global Reach</option>
                    <option value="values">Our Values</option>
                    <option value="tech">Technology & Innovation</option>
                    <option value="cta">CTA</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Content</label>
                <textarea name="content" class="form-control" rows="3" required></textarea>
            </div>
            <button type="submit" name="add_section" class="btn btn-primary">Save Section</button>
        </form>
    </div>

   <!-- List Sections -->
<div class="card p-3">
    <h5>Existing Sections</h5>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Section Key</th>
                <th>Title</th>
                <th>Content</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sections as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['section_key']) ?></td>
                <td><?= htmlspecialchars($s['title']) ?></td>
                <td><?= nl2br(htmlspecialchars($s['content'])) ?></td>
                <td>
                    <!-- Edit Button opens modal -->
                    <button 
                        class="btn btn-sm btn-warning edit-btn" 
                        data-id="<?= $s['id'] ?>" 
                        data-key="<?= htmlspecialchars($s['section_key']) ?>"
                        data-title="<?= htmlspecialchars($s['title']) ?>" 
                        data-content="<?= htmlspecialchars($s['content']) ?>"
                        data-bs-toggle="modal" 
                        data-bs-target="#editSectionModal">
                        Edit
                    </button>
                    
                    <!-- Delete Button -->
                    <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Delete this section?')">
                       Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($sections)): ?>
            <tr><td colspan="4">No sections yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</div>
<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title">Edit Section</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="edit-id">
            <div class="mb-3">
                <label class="form-label">Section Key</label>
                <input type="text" class="form-control" name="section_key" id="edit-key" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" name="title" id="edit-title" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Content</label>
                <textarea class="form-control" name="content" id="edit-content" rows="4" required></textarea>
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="update_section" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Edit Founder Modal -->
<div class="modal fade" id="editFounderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Edit Founder</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="edit-founder-id">

            <div class="mb-2">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="edit-founder-name" class="form-control" required>
            </div>

            <div class="mb-2">
                <label class="form-label">Title</label>
                <select name="title" id="edit-founder-title" class="form-control" required>
                    <option value="CEO">CEO</option>
                    <option value="CTO">CTO</option>
                    <option value="COO">COO</option>
                    <option value="Head of Marketing">Head of Marketing</option>
                    <option value="Head of Design">Head of Design</option>
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit-founder-description" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-2">
                <label class="form-label">Current Image</label><br>
                <img id="edit-founder-image-preview" src="" style="max-height:60px; margin-bottom:8px;">
                <input type="file" name="founder_image" class="form-control" accept="image/*">
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="update_founder" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const editButtons = document.querySelectorAll(".edit-founder-btn");

    editButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("edit-founder-id").value = this.dataset.id;
            document.getElementById("edit-founder-name").value = this.dataset.name;
            document.getElementById("edit-founder-title").value = this.dataset.title;
            document.getElementById("edit-founder-description").value = this.dataset.description;

            let image = this.dataset.image;
            document.getElementById("edit-founder-image-preview").src = image 
                ? "../uploads/founders/" + image 
                : "";
        });
    });
});
</script>


<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const editButtons = document.querySelectorAll(".edit-btn");

    editButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("edit-id").value = this.dataset.id;
            document.getElementById("edit-key").value = this.dataset.key;
            document.getElementById("edit-title").value = this.dataset.title;
            document.getElementById("edit-content").value = this.dataset.content;
        });
    });
});
</script>

<script>
    
    
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sortable
    const sortableList = document.getElementById('banner-list');
    if (sortableList) {
        new Sortable(sortableList, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                // Update order inputs based on new positions
                const items = sortableList.querySelectorAll('.banner-item');
                items.forEach((item, index) => {
                    const orderInput = item.querySelector('input[name^="banner_order"]');
                    if (orderInput) {
                        orderInput.value = index;
                    }
                });
            }
        });
    }

    // Auto-hide messages after 5 seconds
    const messageContainer = document.getElementById('messageContainer');
    if (messageContainer) {
        setTimeout(function() {
            messageContainer.style.opacity = '0';
            setTimeout(function() {
                messageContainer.remove();
            }, 300);
        }, 5000);
    }

    // File size validation
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    this.value = '';
                }
            }
        });
    }
});
</script>
</body>
</html>