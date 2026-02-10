<?php
// 1. START SESSION & SET TIMEOUT
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 30 Minutes Timeout (1500 Seconds)
$timeout_duration = 1500; 

// Check if "last_activity" is set
if (isset($_SESSION['last_activity'])) {
    // Calculate difference between now and last activity
    $duration = time() - $_SESSION['last_activity'];
    
    // If duration > 30 mins, destroy session and redirect
    if ($duration > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: login.php?msg=loggedout"); // Redirect with message
        exit();
    }
}

// Update "last_activity" time stamp
$_SESSION['last_activity'] = time();


// 2. DATABASE CONNECTION (Keep your existing settings)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "subtrackk"; // Make sure this matches your actual DB name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>