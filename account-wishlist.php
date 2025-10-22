<div id="wishlist" class="acc-card">
	<h3>Wishlist</h3>
	<p class="acc-sub">Saved items you want to book later.</p>
	<?php
		// Setup pagination
		$wishlist_per_page = 6;
		$wishlist_page = isset($_GET['wishlist_page']) ? max(1, intval($_GET['wishlist_page'])) : 1;
		$total_wishlist = count($wishlist_items);
		$wishlist_start = ($wishlist_page - 1) * $wishlist_per_page;
		$paginated_wishlist = array_slice($wishlist_items, $wishlist_start, $wishlist_per_page);
	?>
	<?php if (empty($wishlist_items)): ?>
		<p>Your wishlist is empty. <a href="home.php">Discover amazing places!</a></p>
	<?php else: ?>
		<div class="acc-list" id="wishlist-list">
			<?php foreach ($paginated_wishlist as $item): ?>
				<div class="acc-item">
					<img src="<?= htmlspecialchars($item['park_picture']) ?>" class="acc-thumb" alt="Wishlist">
					<div class="acc-item-details">
						<div class="acc-item-title"><?= htmlspecialchars($item['park_name']) ?></div>
						<div class="acc-item-sub">Added on: <?= date('M j, Y', strtotime($item['created_at'])) ?></div>
					</div>
					<form method="POST" style="display: inline;">
						<input type="hidden" name="action" value="remove_wishlist">
						<input type="hidden" name="park_id" value="<?= $item['park_id'] ?>">
						<button type="submit" class="acc-heart-btn" onclick="return confirm('Remove from wishlist?')">
							<svg viewBox="0 0 24 24">
								<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41 0.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
							</svg>
						</button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<!-- Pagination -->
		<?php if ($total_wishlist > $wishlist_per_page): ?>
			<div class="pagination">
				<?php if ($wishlist_page > 1): ?>
					<a href="?wishlist_page=<?= $wishlist_page - 1 ?>#wishlist" class="page-link">Prev</a>
				<?php endif; ?>
				<span class="page-info">Page <?= $wishlist_page ?> of <?= ceil($total_wishlist / $wishlist_per_page) ?></span>
				<?php if ($wishlist_page * $wishlist_per_page < $total_wishlist): ?>
					<a href="?wishlist_page=<?= $wishlist_page + 1 ?>#wishlist" class="page-link">Next</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>