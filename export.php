
<?php
session_start();
include 'db.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id'])) { exit(); }

$user_id = $_SESSION['user_id'];

// SET HEADERS TO FORCE DOWNLOAD
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=My_Subscriptions_'.date("Y-m-d").'.csv');

// OPEN OUTPUT STREAM
$output = fopen('php://output', 'w');

// ADD COLUMN HEADERS
fputcsv($output, array('Service Name', 'Price', 'Billing Cycle', 'Category', 'Start Date', 'Next Due Date'));

// FETCH DATA
$sql = "SELECT service_name, price, billing_period, category, start_date, next_due_date FROM subscriptions WHERE user_id = $user_id";
$result = $conn->query($sql);

// LOOP AND ADD ROWS
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>