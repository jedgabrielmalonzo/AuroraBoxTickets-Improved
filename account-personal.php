<div id="personal" class="acc-card active">
	<h3>Personal Information</h3>
	<p class="acc-sub">Update your personal details.</p>
	<form method="POST" action="account.php">
		<input type="hidden" name="action" value="update_profile">
		<?php
		$stmt = $conn->prepare("SELECT firstname, lastname, email, phone, emergency_contact FROM user WHERE id = ?");
		$stmt->bind_param("i", $_SESSION['user_id']);
		$stmt->execute();
		$result = $stmt->get_result();
		$user = $result->fetch_assoc();
		$stmt->close();
		$form_firstname = $user['firstname'] ?? '';
		$form_lastname = $user['lastname'] ?? '';
		$form_email = $user['email'] ?? '';
		$form_phone = '';
		if (is_array($user) && array_key_exists('phone', $user) && !is_null($user['phone']) && trim($user['phone']) !== '') {
				$form_phone = trim($user['phone']);
		}
		$form_emergency = '';
		if (is_array($user) && array_key_exists('emergency_contact', $user) && !is_null($user['emergency_contact']) && trim($user['emergency_contact']) !== '') {
				$form_emergency = trim($user['emergency_contact']);
		}
		if (isset($_SESSION['google_name']) && empty($form_firstname)) {
				$name_parts = explode(' ', $_SESSION['google_name'], 2);
				$form_firstname = $name_parts[0] ?? '';
				if (empty($form_lastname) && isset($name_parts[1])) {
						$form_lastname = $name_parts[1];
				}
		}
		?>
		<div class="acc-grid acc-cols-2">
			<div>
				<label>First Name</label>
				<input class="acc-input" name="firstname" value="<?= htmlspecialchars($form_firstname) ?>" placeholder="First Name" required>
			</div>
			<div>
				<label>Last Name</label>
				<input class="acc-input" name="lastname" value="<?= htmlspecialchars($form_lastname) ?>" placeholder="Last Name" <?= empty($form_lastname) ? 'required' : '' ?>>
			</div>
		</div>
		<div class="acc-grid acc-cols-2" style="margin-top:12px">
			<div>
				<label>Email</label>
				<input class="acc-input" type="email" name="email" value="<?= htmlspecialchars($form_email) ?>" placeholder="Email Address" required>
			</div>
			<div>
				<label>Phone</label>
				<input class="acc-input" type="tel" name="phone" value="<?= htmlspecialchars($form_phone) ?>" placeholder="(+63) 900 000 0000">
			</div>
		</div>
		<div class="acc-grid acc-cols-2" style="margin-top:12px">
			<div>
				<label>Emergency Contact</label>
				<input class="acc-input" type="tel" name="emergency_contact" value="<?= htmlspecialchars($form_emergency) ?>" placeholder="(+63) 900 000 0000" required>
			</div>
		</div>
		<div style="margin-top:16px">
			<button type="submit" class="acc-btn acc-primary">Update Profile</button>
		</div>
	</form>
</div>