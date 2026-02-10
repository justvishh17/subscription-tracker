<?php
include 'db.php';

$sql = "SELECT id, username, email, role FROM users";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " - Username: " . $row['username'] . " - Email: " . $row['email'] . " - Role: " . $row['role'] . "<br>";
    }
} else {
    echo "No users found.";
}

$conn->close();
?>