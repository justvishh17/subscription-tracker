<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit(); }
$user_id = $_SESSION['user_id'];

$notifications = [];

// 1. CHECK UPCOMING BILLS (Keep this!)
// We check for bills due in the next 5 days
$sql = "SELECT id, service_name, next_due_date, price FROM subscriptions 
        WHERE user_id = $user_id AND status = 'active'
        AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    $days = (strtotime($row['next_due_date']) - time()) / (60 * 60 * 24);
    $days = ceil($days);
    
    $msg = ($days <= 0) ? "is due today!" : "is due in $days days.";
    $icon = ($days <= 0) ? "fa-exclamation-circle text-danger" : "fa-clock text-warning";
    
    // Unique ID: Service Name + Due Date
    $unique_id = "bill_" . $row['id'] . "_" . $row['next_due_date'];

    $notifications[] = [
        'id' => $unique_id,
        'type' => 'bill',
        'icon' => $icon,
        'title' => $row['service_name'],
        'text' => $msg . " ($" . $row['price'] . ")",
        'time' => 'Action required'
    ];
}

// --- REMOVED SECURITY SECTION HERE --- 
// (No more "New Login" alerts)

// 2. CHECK ZOMBIE SUBS (Unused > 30 Days)
// This is still useful to save money, so we keep it.
$sql = "SELECT id, service_name, last_used FROM subscriptions 
        WHERE user_id = $user_id AND status = 'active'
        AND last_used < DATE_SUB(NOW(), INTERVAL 30 DAY)";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    $unique_id = "zombie_" . $row['id'];

    $notifications[] = [
        'id' => $unique_id,
        'type' => 'zombie',
        'icon' => 'fa-ghost text-secondary',
        'title' => 'Unused Subscription',
        'text' => "You haven't used " . $row['service_name'] . " in a while.",
        'time' => 'Review now'
    ];
}

echo json_encode($notifications);
?>