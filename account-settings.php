<div id="settings" class="acc-card">
	<h3>Settings</h3>
	<?php
if (isset($_POST['update_password'])) {
	$newPassword = $_POST['new_password'];
	$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
	$stmt = $conn->prepare("UPDATE user SET password_hash = ? WHERE id = ?");
	$stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
	if ($stmt->execute()) {
		echo '<p style="color:green;">Password updated successfully!</p>';
	} else {
		echo '<p style="color:red;">Error updating password. Please try again.</p>';
	}
	$stmt->close();
}
?>
	<p class="acc-sub">Manage your account settings.</p>
	<form method="POST" action="">
	<div class="acc-grid acc-cols-2">
		<div>
			<label>Current Email</label>
			<input class="acc-input" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
		</div>
		<div>
			<label>Change Password</label>
			<input class="acc-input" type="password" name="new_password" placeholder="Enter new password" required>
		</div>
	</div>
	<button type="submit" name="update_password" class="acc-btn acc-primary">Update Password</button>
</form>
	<form method="POST" action="account.php">
		<input type="hidden" name="action" value="submit_feedback">
		<div style="margin-top:16px">
			<label>Leave Feedback</label>
			<textarea class="acc-input" name="feedback" rows="4" placeholder="Write your feedback here..."></textarea>
		</div>
		<div style="margin-top:12px">
			<button type="submit" class="acc-btn acc-primary">Submit Feedback</button>
		</div>
	</form>
	<div style="margin-top:16px;display:flex;gap:12px">
		<button class="acc-btn acc-primary" disabled>Save Changes</button>
		<button class="acc-btn acc-danger" onclick="if(confirm('Are you sure you want to delete your account? This cannot be undone.')) { alert('Account deletion feature coming soon.'); }">Delete Account</button>
	</div>
	<h3 style="margin-top:28px">Additional Account Info</h3>
	<?php 
$stmt = $conn->prepare("SELECT created, last_login, email_verified FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
	<div class="acc-info-cards">
		<div class="acc-info-card">
			<h4>Account Created:</h4>
<p><?= isset($user['created']) ? date('F j, Y', strtotime($user['created'])) : 'Unknown' ?></p>        </div>
		<div class="acc-info-card">
			<h4>Last Login:</h4>
<p><?= isset($user['last_login']) ? date('F j, Y, g:i A', strtotime($user['last_login'])) : 'Unknown' ?></p>        </div>
		<div class="acc-info-card">
			<h4>Email Verified:</h4>
			<p>
	<?php if (isset($user['email_verified']) && $user['email_verified'] == 0): ?>
		<a href="resend_verification.php" class="acc-btn acc-primary">Verify Now</a>
	<?php else: ?>
		<span style="color:green; font-weight:bold;">✔ Verified</span>
	<?php endif; ?>
			</p>
		</div>
	</div>
</div>