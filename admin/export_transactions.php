<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin_first_name"])) {
    header("Location: index.php");
    exit;
}

// Database connection (centralized)
$mysqli = require __DIR__ . '/../database.php';
if (!$mysqli || $mysqli->connect_error) {
    die('Database connection failed.');
}
$conn = $mysqli;

// Get the same filter parameters from the main page
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the WHERE clause dynamically (same as main page)
$where_conditions = [];
$params = [];
$param_types = "";

// Search condition
if (!empty($search)) {
    $where_conditions[] = "(u.firstname LIKE ? OR u.lastname LIKE ? OR p.payment_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $param_types .= "sss";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Payment method filter
if (!empty($payment_method_filter)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $payment_method_filter;
    $param_types .= "s";
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Construct WHERE clause
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Same SQL query as main page
$sql = "
SELECT 
    p.id as payment_table_id,
    p.payment_id,
    p.user_id,
    CONCAT(u.firstname, ' ', u.lastname) AS user_name,
    u.email as user_email,
    u.firstname,
    u.lastname,
    p.amount,
    p.status,
    p.payment_method,
    p.reference_number,
    p.created_at AS payment_date
FROM 
    payments p
LEFT JOIN 
    user u ON p.user_id = u.id
$where_clause
ORDER BY 
    p.created_at DESC
";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Generate filename with current date and filters
$filename = 'aurora_transactions_' . date('Y-m-d_H-i-s');
if (!empty($search)) $filename .= '_search-' . preg_replace('/[^a-zA-Z0-9]/', '', $search);
if (!empty($status_filter)) $filename .= '_' . $status_filter;
if (!empty($payment_method_filter)) $filename .= '_' . $payment_method_filter;
if (!empty($date_from) || !empty($date_to)) {
    $filename .= '_' . ($date_from ?: 'start') . '-to-' . ($date_to ?: 'end');
}
$filename .= '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Create file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Payment ID',
    'User ID', 
    'Customer Name',
    'First Name',
    'Last Name',
    'Email',
    'Amount (PHP)',
    'Status',
    'Payment Method',
    'Reference Number',
    'Transaction Date',
    'Transaction Time'
]);

// Add data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transaction_date = date('Y-m-d', strtotime($row['payment_date']));
        $transaction_time = date('H:i:s', strtotime($row['payment_date']));
        
        fputcsv($output, [
            $row['payment_id'],
            $row['user_id'],
            $row['user_name'] ?? 'N/A',
            $row['firstname'] ?? 'N/A',
            $row['lastname'] ?? 'N/A', 
            $row['user_email'] ?? 'N/A',
            number_format($row['amount'], 2),
            ucfirst($row['status']),
            ucfirst(str_replace('_', ' ', $row['payment_method'] ?? 'N/A')),
            $row['reference_number'] ?? 'N/A',
            $transaction_date,
            $transaction_time
        ]);
    }
} else {
    // If no data found, add a row indicating this
    fputcsv($output, ['No transactions found with the applied filters']);
}

// Add summary at the end
fputcsv($output, []); // Empty row
fputcsv($output, ['=== EXPORT SUMMARY ===']);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Records:', $result->num_rows]);
fputcsv($output, ['Exported by:', $_SESSION["admin_first_name"] . " " . $_SESSION["admin_last_name"]]);

if (!empty($search)) fputcsv($output, ['Search Filter:', $search]);
if (!empty($status_filter)) fputcsv($output, ['Status Filter:', $status_filter]);
if (!empty($payment_method_filter)) fputcsv($output, ['Payment Method Filter:', $payment_method_filter]);
if (!empty($date_from)) fputcsv($output, ['Date From:', $date_from]);
if (!empty($date_to)) fputcsv($output, ['Date To:', $date_to]);

// Close the file pointer
fclose($output);

// Close database connections
$stmt->close();
$conn->close();

// Exit to prevent any additional output
exit;
?>