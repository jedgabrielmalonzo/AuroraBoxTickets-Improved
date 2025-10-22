<div id="reviews" class="acc-card">
  <h3>Reviews</h3>
  <p class="acc-sub">Your submitted reviews.</p>
  <?php if (empty($reviews)): ?>
	  <p>You haven’t submitted any reviews yet. <a href="home.php">Start reviewing!</a></p>
  <?php else: ?>
	  <div class="acc-list">
		  <?php foreach ($reviews as $review): ?>
			  <div class="acc-item">
				  <div class="acc-item-details">
					  <div class="acc-item-title"><?= htmlspecialchars($review['park_name'] ?? 'Park') ?></div>
					  <div class="acc-item-sub">
						  Rating: <?= intval($review['rating']) ?> / 5<br>
						  <?= htmlspecialchars($review['comment']) ?><br>
						  <small>Submitted on: <?= date('M j, Y', strtotime($review['created_at'])) ?></small>
					  </div>
				  </div>
			  </div>
		  <?php endforeach; ?>
	  </div>
  <?php endif; ?>
</div>