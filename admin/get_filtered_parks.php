<?php
// DB Connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
                            data-category="<?= htmlspecialchars($row['category_name']) ?>"
                            data-subcategory="<?= htmlspecialchars($row['subcategory_name']) ?>">
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