<?php
include 'db.php';

// Reset admin password to 'admin123' (you can change this)
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password='$hashed_password' WHERE role='admin'";
if ($conn->query($sql) === TRUE) {
    echo "Admin password reset successfully!<br>";
    echo "New password: $new_password<br>";
    echo "Hashed: $hashed_password<br>";
} else {
    echo "Error resetting password: " . $conn->error;
}

$conn->close();
?>