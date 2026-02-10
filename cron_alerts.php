<?php
include 'db.php';

// 1. CALCULATE TOMORROW'S DATE
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// 2. FIND SUBSCRIPTIONS DUE TOMORROW
$sql = "SELECT s.*, u.username, u.email 
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.next_due_date = '$tomorrow'";

$result = $conn->query($sql);

echo "<h2>📧 Email Alert System</h2>";
echo "<p>Checking for bills due on: <strong>$tomorrow</strong></p>";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $user = $row['username'];
        $email = $row['email'];
        $service = $row['service_name'];
        $price = $row['price'];

        // --- SIMULATION MODE (Because Localhost email is hard) ---
        echo "<div style='border:1px solid #ddd; padding:10px; margin-bottom:10px; border-left: 5px solid #667eea;'>";
        echo "<strong>To:</strong> $email ($user)<br>";
        echo "<strong>Subject:</strong> 🔔 Reminder: $service is due tomorrow!<br>";
        echo "<strong>Message:</strong> Hey $user, just a heads up that your $service subscription is renewing tomorrow for $$price.";
        echo "</div>";

        // --- REAL EMAIL LOGIC (Uncomment if you have PHPMailer) ---
        // mail($email, "Subject", "Message");
    }
} else {
    echo "<div style='color: green;'>No bills due tomorrow! Relax. 🍵</div>";
}
?>