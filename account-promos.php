<div id="promos" class="acc-card">
	<h3>Redeemed Promos</h3>
	<p class="acc-sub">Here are the promos you've redeemed.</p>
	<?php if (empty($redeemed_promos)): ?>
		<p>You haven't redeemed any promos yet. <a href="home.php">Browse available promos</a>.</p>
	<?php else: ?>
		<div class="acc-list">
			<?php foreach ($redeemed_promos as $promo): ?>
				<div class="acc-item">
					<div class="acc-item-details">
						<div class="acc-item-title">
							<?= !empty($promo['description']) ? htmlspecialchars($promo['description']) : 'Promo Code' ?>
						</div>
						<div class="acc-item-sub">
							<small>Redeemed on: <?= !empty($promo['redeemed_at']) ? date('M j, Y', strtotime($promo['redeemed_at'])) : 'N/A' ?></small>
							<?php if (!empty($promo['expiration_date'])): ?>
								<br><small>Expires: <?= date('M j, Y', strtotime($promo['expiration_date'])) ?></small>
							<?php endif; ?>
						</div>
					</div>
					<div style="text-align: right; min-width:120px;">
						<div class="promo-code" style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin-bottom: 4px;">
							<?= htmlspecialchars($promo['code'] ?? 'N/A') ?>
						</div>
						<small>
							<?= $promo['type'] == 'percentage' ? $promo['value'].'% OFF' : '₱'.number_format($promo['value']).' OFF' ?>
						</small>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>