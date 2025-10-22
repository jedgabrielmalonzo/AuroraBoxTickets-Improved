<?php
// DB Connection
$conn = require __DIR__ . '/../database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['category'])) {
    $category = mysqli_real_escape_string($conn, $_GET['category']);
    
    // Get subcategories for the selected category
    $sql = "SELECT DISTINCT s.subcategory_name 
            FROM subcategory s 
            INNER JOIN category c ON s.category_id = c.category_id 
            INNER JOIN parks p ON s.subcategory_id = p.subcategory 
            WHERE c.category_name = ?
            ORDER BY s.subcategory_name";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subcategories = [];
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row['subcategory_name'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($subcategories);
}
?>