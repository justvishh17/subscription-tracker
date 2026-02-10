<?php
session_start();
include 'db.php';

// Security Check
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// Fetch the last 10 logins
$sql = "SELECT * FROM login_logs WHERE user_id = $user_id ORDER BY login_time DESC LIMIT 10";
$result = $conn->query($sql);

// Helper function to clean up the browser name
function getDeviceName($userAgent) {
    $os = "Unknown OS";
    if (strpos($userAgent, 'Windows') !== false) $os = "Windows";
    elseif (strpos($userAgent, 'Mac') !== false) $os = "Mac";
    elseif (strpos($userAgent, 'Linux') !== false) $os = "Linux";
    elseif (strpos($userAgent, 'Android') !== false) $os = "Android";
    elseif (strpos($userAgent, 'iPhone') !== false) $os = "iPhone";

    $browser = "Unknown Browser";
    if (strpos($userAgent, 'Chrome') !== false) $browser = "Chrome";
    elseif (strpos($userAgent, 'Firefox') !== false) $browser = "Firefox";
    elseif (strpos($userAgent, 'Safari') !== false) $browser = "Safari";
    elseif (strpos($userAgent, 'Edge') !== false) $browser = "Edge";

    return "$browser on $os";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-shield-alt text-primary me-2"></i>Login Activity</h2>
        <a href="index.php" class="btn btn-outline-secondary">&larr; Dashboard</a>
    </div>

    <div class="card shadow border-0">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date & Time</th>
                        <th>Device / Browser</th>
                        <th>IP Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo date("M d, Y h:i A", strtotime($row['login_time'])); ?>
                            <?php if($result->current_field == 0): // Highlight the top one ?>
                                <span class="badge bg-success ms-2">Current Session</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <i class="fas fa-desktop text-muted me-2"></i>
                            <?php echo getDeviceName($row['browser']); ?>
                        </td>
                        <td class="font-monospace"><?php echo $row['ip_address']; ?></td>
                        <td>
                            <?php if($row['status'] == 'success'): ?>
                                <span class="badge bg-success">Success</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Failed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-3 text-muted small">
        <i class="fas fa-info-circle me-1"></i> If you see an IP address you don't recognize, change your password immediately.
    </div>
</div>

</body>
</html>