<?php
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    die('Database connection failed.');
}
$conn = $mysqli;

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$result = $conn->query("SELECT subcategory_id, subcategory_name FROM subcategory WHERE category_id = $category_id");

$subcategories = [];
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}

header('Content-Type: application/json');
echo json_encode($subcategories);

$conn->close();
?>
