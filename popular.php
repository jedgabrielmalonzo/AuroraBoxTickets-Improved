<?php
// Just use the existing $conn from index.php
function fetchPopularParks($conn, $limit = 6) {
    $sql = "SELECT p.*, c.category_name,
                   MIN(pt.price) AS min_price,
                   COALESCE(SUM(o.quantity), 0) AS total_sold
            FROM parks p
            LEFT JOIN category c ON p.category = c.category_id
            LEFT JOIN park_tickets pt ON p.id = pt.park_id
            LEFT JOIN orders o ON p.id = o.park_id
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$popularParks = fetchPopularParks($conn, 6);
?>



<!-- POPULAR NOW SECTION -->
<?php if (!empty($popularParks)): ?>
<div class="PARKSHOW">
    <h2>POPULAR NOW</h2>
    <div class="carousel-container">
        <button class="nav-btn prev" onclick="scrollCarousel('popular-carousel', -270)">
            <span>&#10094;</span>
        </button>
        <div class="cards" id="popular-carousel">
            <?php foreach ($popularParks as $park): 
                $images = !empty($park['pictures']) ? explode(',', $park['pictures']) : [];
                $firstImage = !empty($images) ? trim($images[0]) : 'images/default-park.jpg';

                $endDate = isset($park['end_date']) ? $park['end_date'] : null;
            ?>
                <a href="park_info.php?id=<?= $park['id'] ?>" class="card">
                    <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($park['name']) ?>">

                    <?php if ($endDate): ?>
                        <div class="timer-badge" data-end="<?= $endDate ?>"></div>
                    <?php endif; ?>

                    <div class="card-content">
                        <div class="category">
                            <?= htmlspecialchars($park['category_name'] ?? 'Park') ?> • 
                            <?= htmlspecialchars($park['city'] ?? 'Philippines') ?>
                        </div>
                        <div class="title"><?= htmlspecialchars($park['name']) ?></div>
                        <div class="tag">Most Booked</div>
                        <div class="price">₱<?= number_format($park['min_price'] ?? 0) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <button class="nav-btn next" onclick="scrollCarousel('popular-carousel', 270)">
            <span>&#10095;</span>
        </button>
    </div>
</div>
<?php endif; ?>

<!-- CSS (kung hindi pa na-define) -->
<style>
.card { position: relative; }
.timer-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ff4d4f;
    color: #fff;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: bold;
    border-radius: 5px;
    z-index: 10;
}
</style>

<!-- JS -->
<script>
function updateTimers() {
    const badges = document.querySelectorAll('.timer-badge');
    const now = new Date();

    badges.forEach(badge => {
        const endDate = new Date(badge.getAttribute('data-end'));
        const diff = endDate - now;

        if (diff <= 0) {
            badge.textContent = "EXPIRED";
        } else {
            const daysLeft = Math.floor(diff / (1000 * 60 * 60 * 24));

            if (daysLeft < 7) { // Start countdown only if ≤ 7 days
                const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
                const minutes = Math.floor((diff / (1000 * 60)) % 60);
                badge.textContent = `${daysLeft}d ${hours}h ${minutes}m LEFT`;
            } else {
                badge.textContent = ""; // Hide badge if more than 7 days left
            }
        }
    });
}

// Initial update and refresh every minute
updateTimers();
setInterval(updateTimers, 60000);
</script>
