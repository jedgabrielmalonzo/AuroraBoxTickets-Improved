<?php
// Include the database connection
require_once "/../../database.php";  // Adjust path if necessary

// Admin credentials
$email = 'jorichadmin@aurorabox.com';
$password = '12345678i';  // Actual password
$first_name = 'Jorich';
$last_name = 'Anday';

// Hash the password for security
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Prepare the SQL statement to insert the admin data into the database
$sql = "INSERT INTO adminuser (email, password_hash, first_name, last_name) 
        VALUES (?, ?, ?, ?)";

// Use a prepared statement to safely insert the data
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssss", $email, $hashed_password, $first_name, $last_name);

// Execute the query
if ($stmt->execute()) {
    echo "Admin user created successfully!";
} else {
    echo "Error: " . $stmt->error;
}

// Close the statement and the connection
$stmt->close();
$mysqli->close();
?>