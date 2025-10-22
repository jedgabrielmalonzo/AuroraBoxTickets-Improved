<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$mysqli = require __DIR__ . "/database.php";
$mysqli->set_charset("utf8mb4");
$conn = $mysqli;

// --- Smart Search Logic ---
$parks = [];
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';
$sortBy = $_GET['sort'] ?? 'relevance';
$selectedCategories = $_GET['category'] ?? [];
$selectedSubcategories = $_GET['subcategory'] ?? [];

// --- Fetch categories and subcategories ---
$categoryQuery = "SELECT c.category_id, c.category_name, s.subcategory_id, s.subcategory_name FROM category c LEFT JOIN subcategory s ON c.category_id = s.category_id";
$categoryResult = $mysqli->query($categoryQuery);
$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    if (!isset($categories[$row['category_id']])) {
        $categories[$row['category_id']] = [
            'name' => $row['category_name'],
            'subcategories' => []
        ];
    }
    if ($row['subcategory_id']) {
        $categories[$row['category_id']]['subcategories'][] = [
            'id' => $row['subcategory_id'],
            'name' => $row['subcategory_name']
        ];
    }
}

// --- Build Smart Search Query ---
$sql = "SELECT p.*, t.ticket_name, MIN(t.price) AS min_price, c.category_name FROM parks p LEFT JOIN park_tickets t ON p.id = t.park_id LEFT JOIN category c ON p.category = c.category_id WHERE 1";
$params = [];
$types = "";

if ($searchQuery !== '') {
    // Prioritize exact match, then LIKE, then SOUNDEX
    $sql .= " AND (p.name = ? OR p.city = ?";
    $params[] = $searchQuery;
    $params[] = $searchQuery;
    $types .= "ss";
    $sql .= " OR p.name LIKE ? OR p.city LIKE ?";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $types .= "ss";
    $sql .= " OR SOUNDEX(p.name) = SOUNDEX(?) OR SOUNDEX(p.city) = SOUNDEX(?) )";
    $params[] = $searchQuery;
    $params[] = $searchQuery;
    $types .= "ss";
}

if (!empty($selectedCategories)) {
    $catPlaceholders = implode(',', array_fill(0, count($selectedCategories), '?'));
    $sql .= " AND p.category IN ($catPlaceholders)";
    foreach ($selectedCategories as $cat) {
        $types .= "i";
        $params[] = (int)$cat;
    }
}
if (!empty($selectedSubcategories)) {
    $subPlaceholders = implode(',', array_fill(0, count($selectedSubcategories), '?'));
    $sql .= " AND p.subcategory IN ($subPlaceholders)";
    foreach ($selectedSubcategories as $sub) {
        $types .= "i";
        $params[] = (int)$sub;
    }
}
$sql .= " GROUP BY p.id";

// --- Sorting ---
if ($sortBy === 'price_low') {
    $sql .= " ORDER BY min_price ASC";
} elseif ($sortBy === 'price_high') {
    $sql .= " ORDER BY min_price DESC";
} elseif ($sortBy === 'name') {
    $sql .= " ORDER BY p.name ASC";
} else {
    // Default: relevance (exact match first, then LIKE, then SOUNDEX)
    $sql .= " ORDER BY (p.name = ?) DESC, (p.name LIKE ?) DESC, (SOUNDEX(p.name) = SOUNDEX(?)) DESC, p.name ASC";
    $params[] = $searchQuery;
    $params[] = "%$searchQuery%";
    $params[] = $searchQuery;
    $types .= "sss";
}

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $parks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle logout
if (isset($_GET['logout'])) {
    // Update last_login timestamp before destroying the session
    $update_login_sql = "UPDATE user SET last_login = NOW(), is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($update_login_sql);
    $stmt->bind_param('i', $_SESSION["user_id"]); // Assuming user_id is stored in session
    
    if ($stmt->execute()) {
        session_destroy(); // Destroy the session
        header("Location: index.php"); // Redirect back to the home page
        exit();
    } else {
        echo "Error updating last login: " . $stmt->error;
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuroraBox</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/homepage.css">
        <link rel="stylesheet" href="css/navnew.css">
    <link rel="stylesheet" href="css/layout_park.css"> <!-- Use shared card styles -->
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Inter', Arial, sans-serif;
    }
    .search-container {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }
    .filter-sidebar {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        padding: 24px 20px 20px 20px;
        min-width: 280px;
        max-width: 320px;
        flex: 0 0 320px;
        margin: 20px 0 24px 0;
        position: sticky;
        top: 24px;
        height: fit-content;
    }
    .filter-sidebar h5 {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 18px;
        color: #684D8F;
        letter-spacing: 0.5px;
    }
    .search-title {
        font-size: 2.1rem;
        font-weight: 700;
        color: #684D8F;
        margin: 8rem 0 0 0;
        letter-spacing: 0.5px;
        text-align: center;
    }
    .search-query {
        font-size: 1.15rem;
        color: #333;
        margin: 6px 0 2rem 0;
        font-weight: 500;
        text-align: center;
    }
    .search-query span {
        background: #f3eaff;
        color: #684D8F;
        border-radius: 6px;
        padding: 2px 8px;
        font-weight: 600;
        font-size: 1.1rem;
        margin-left: 2px;
    }
    .search-sub {
        color: #888;
        font-size: 1rem;
        font-weight: 400;
        margin-top: 4px;
    }
    @media (max-width: 1024px) {
        .search-container {
            flex-direction: column;
            padding: 0 15px;
        }
        .filter-sidebar {
            max-width: 100%;
            min-width: unset;
            position: static;
            margin: 10px 0;
        }
        .search-title {
            font-size: 1.8rem;
            margin: 6rem 0 0 0;
        }
        .parks-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }
    }
    @media (max-width: 768px) {
        .search-center-wrapper {
            padding: 0 10px;
        }
        .parks-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }
        .sort-container {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        }\n    \n    .filter-group {
        border-bottom: 1px solid #eee;
        padding-bottom: 6px;
        margin-bottom: 8px;
    }
    .filter-group:last-child {
        border-bottom: none;
    }
    .filter-sub {
        margin-left: 1.5rem;
        margin-top: 4px;
        border-left: 2px solid #eee;
        padding-left: 10px;
        background: #faf8ff;
        border-radius: 0 0 8px 8px;
    }
    .category-header {
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .category-header:hover {
        background: #f3eaff;
        border-radius: 8px;
    }
    .dropdown-arrow {
        margin-left: 8px;
        color: #684D8F;
        transition: transform 0.2s;
        user-select: none;
    }
    .search-center-wrapper {
        margin: 0;
        padding: 0 20px;
    }
    .parks-container {
        flex: 1;
        min-width: 0;
    }
    .parks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 20px;
    }
    .no-data {
        text-align: center;
        width: 100%;
        padding: 40px 0;
        font-size: 1.2rem;
        color: #666;
        grid-column: 1 / -1;
    }
    .sort-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .sort-container select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
        color: #333;
        font-size: 14px;
    }
    .results-count {
        color: #666;
        font-size: 14px;
        font-weight: 500;
    }
    </style>
</head>
<body>
<header>

    <!-------NAVBAR SECTION------>
    <?php include 'navbar/navbar.php'; ?>
    
    
    <?php if ($searchQuery !== ''): ?>
    <div class="search-header" style="margin-left:24px;">
            <h1 class="search-title">Search Results</h1>
            <div class="search-query">for <span>"<?= htmlspecialchars(stripslashes(trim(str_replace('%', '', $searchQuery))) ) ?>"</span></div>
        </div>
    <?php else: ?>
        <div class="search-header">
            <h1 class="search-title">Find your next adventure!</h1>
            <div class="search-query search-sub">Use the search box above to discover parks, tickets, and more.</div>
        </div>
    <?php endif; ?>
</header>
<div class="search-center-wrapper">
    <div class="search-container mt-4">
    <div class="filter-sidebar">
        <h5>Filter Parks</h5>
        <form method="GET" action="search.php">
            <input type="hidden" name="query" value="<?= htmlspecialchars(stripslashes(trim($_GET['query'] ?? ''))) ?>">
            <?php foreach ($categories as $categoryId => $category): ?>
                <div class="filter-group mb-2">
                    <div class="d-flex align-items-center justify-content-between category-header" style="cursor:pointer;" onclick="toggleSubcats('subcat-<?= $categoryId ?>', this)">
                        <div>
                            <input type="checkbox" name="category[]" value="<?= $categoryId ?>" id="category<?= $categoryId ?>" <?= in_array($categoryId, $selectedCategories) ? 'checked' : '' ?> onclick="event.stopPropagation();">
                            <label for="category<?= $categoryId ?>" style="font-weight:600; margin-bottom:0; cursor:pointer;"> <?= htmlspecialchars($category['name']) ?> </label>
                        </div>
                        <?php if (!empty($category['subcategories'])): ?>
                            <span class="dropdown-arrow" style="font-size:1.1em; transition:transform 0.2s;">&#9660;</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($category['subcategories'])): ?>
                        <div class="filter-sub ms-4 mt-1 subcat-dropdown" id="subcat-<?= $categoryId ?>" style="display:none;">
                            <?php foreach ($category['subcategories'] as $subcategory): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="subcategory[]" value="<?= $subcategory['id'] ?>" id="subcategory<?= $subcategory['id'] ?>" <?= in_array($subcategory['id'], $selectedSubcategories) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="subcategory<?= $subcategory['id'] ?>">
                                        <?= htmlspecialchars($subcategory['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn filter-button mt-2">Apply Filters</button>
        </form>
    </div>
    <div class="parks-container">
        <div class="sort-container">
            <div class="results-count">
                <?= count($parks) ?> result<?= count($parks) !== 1 ? 's' : '' ?> found
            </div>
            <form method="GET" action="search.php" style="margin: 0;">
                <!-- Hidden fields to preserve filters -->
                <input type="hidden" name="query" value="<?= htmlspecialchars(stripslashes(trim($_GET['query'] ?? ''))) ?>">
                <?php foreach ($selectedCategories as $cat): ?>
                    <input type="hidden" name="category[]" value="<?= $cat ?>">
                <?php endforeach; ?>
                <?php foreach ($selectedSubcategories as $sub): ?>
                    <input type="hidden" name="subcategory[]" value="<?= $sub ?>">
                <?php endforeach; ?>
                <label for="sort" style="margin-right: 8px; font-weight: 500;">Sort by:</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <option value="relevance" <?= $sortBy === 'relevance' ? 'selected' : '' ?>>Relevance</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
            </form>
        </div>

        <div class="parks-grid">
            <?php if (count($parks) > 0): ?>
                <?php foreach ($parks as $park): 
                    $images = !empty($park['pictures']) ? explode(',', $park['pictures']) : [];
                    $firstImage = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';
                ?>
                    <a href="park_info.php?id=<?= $park['id'] ?>" class="card">
                        <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($park['name']) ?>">
                        <div class="card-content">
                            <div class="category"><?= htmlspecialchars($park['category_name'] ?? 'Park') ?> • <?= htmlspecialchars($park['city'] ?? 'Philippines') ?></div>
                            <div class="title"><?= htmlspecialchars($park['name']) ?></div>
                            <div class="tag">Free cancellation</div>
                            <div class="price">₱<?= number_format($park['min_price'] ?? 0) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-search" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i><br>
                    <?php if ($searchQuery !== ''): ?>
                        No results found for "<?= htmlspecialchars(stripslashes(trim($_GET['query'] ?? ''))) ?>".<br>
                        <small style="color: #999; margin-top: 8px; display: block;">Try adjusting your search terms or filters.</small>
                    <?php else: ?>
                        Use the search box above to find parks and attractions.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-------FOOTER SECTION------>
    <?php include 'navbar/footer.html'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="search.js"></script>
    <script>
    function toggleSubcats(id, header) {
        var el = document.getElementById(id);
        if (!el) return;
        var arrow = header.querySelector('.dropdown-arrow');
        if (el.style.display === 'none' || el.style.display === '') {
            el.style.display = 'block';
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        } else {
            el.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>
</body>
</html>