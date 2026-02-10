<?php
include 'db.php';

// Create admin user
$username = 'admin';
$email = 'admin@subtrack.com';
$password = 'admin123'; // Change this to your desired password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', 'admin')";

if ($conn->query($sql) === TRUE) {
    echo "Admin user created successfully!<br>";
    echo "Username: $username<br>";
    echo "Email: $email<br>";
    echo "Password: $password<br>";
    echo "Hashed Password: $hashed_password<br>";
} else {
    echo "Error creating admin user: " . $conn->error;
}

$conn->close();
?>