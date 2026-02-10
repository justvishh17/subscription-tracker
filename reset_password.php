<?php
session_start();
include 'db.php';
$msg = "";
$valid_token = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $now = date("Y-m-d H:i:s");
    
    // Validate Token
    $sql = "SELECT * FROM users WHERE reset_token='$token' AND token_expiry > '$now'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $valid_token = true;
    } else {
        $msg = "Invalid or expired link.";
    }
} else {
    header("Location: login.php"); exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_pass = $_POST['new_pass'];
    
    // Hash the new password
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
    
    // Update Password & Clear Token
    $conn->query("UPDATE users SET password='$hashed_pass', reset_token=NULL, token_expiry=NULL WHERE reset_token='$token'");
    
    echo "<script>
        alert('Password Reset Successfully! Login now.');
        window.location.href='login.php';
    </script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Set New Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">

<div class="card shadow p-4" style="max-width: 400px; width: 100%; border-radius: 15px;">
    <?php if ($valid_token): ?>
        <h3 class="fw-bold text-center mb-3">New Password</h3>
        <form method="POST">
            <div class="form-floating mb-3">
                <input type="password" name="new_pass" class="form-control" id="floatPass" placeholder="New Password" required>
                <label for="floatPass">Enter New Password</label>
            </div>
            <div id="password-strength" class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-muted fw-semibold me-3">Strength:</small>
                    <div class="progress flex-grow-1" style="height: 12px; border-radius: 6px; background-color: #e9ecef;">
                        <div class="progress-bar" id="strength-bar" role="progressbar" style="width: 0%; border-radius: 6px; transition: all 0.3s ease;"></div>
                    </div>
                    <div class="d-flex align-items-center ms-3">
                        <i id="strength-icon" class="fas fa-question-circle text-muted me-2"></i>
                        <small id="strength-text" class="fw-semibold">Password Strength</small>
                    </div>
                </div>
                <div class="d-flex justify-content-between text-center">
                    <small class="text-muted flex-fill">Weak</small>
                    <small class="text-muted flex-fill">Fair</small>
                    <small class="text-muted flex-fill">Good</small>
                    <small class="text-muted flex-fill">Strong</small>
                    <small class="text-muted flex-fill">Very Strong</small>
                </div>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2">Update Password</button>
        </form>
    <?php else: ?>
        <div class="text-center">
            <h3 class="text-danger">Error</h3>
            <p><?php echo $msg; ?></p>
            <a href="forgot_password.php" class="btn btn-primary">Try Again</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('floatPass').addEventListener('input', function() {
    const password = this.value;
    const result = zxcvbn(password);
    const score = result.score; // 0-4
    const bar = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');
    const icon = document.getElementById('strength-icon');
    
    // Update progress bar
    const widths = ['0%', '25%', '50%', '75%', '100%'];
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const icons = ['fa-times-circle', 'fa-exclamation-triangle', 'fa-exclamation-circle', 'fa-check-circle', 'fa-shield-alt'];
    const iconColors = ['text-danger', 'text-warning', 'text-warning', 'text-info', 'text-success'];
    
    bar.style.width = widths[score];
    bar.style.backgroundColor = colors[score];
    text.textContent = score === 0 ? 'Password Strength' : labels[score];
    text.className = 'fw-semibold ' + (score === 0 ? 'text-muted' : iconColors[score]);
    
    icon.className = 'fas ' + (score === 0 ? 'fa-question-circle text-muted' : icons[score] + ' ' + iconColors[score]);
    
    if (password.length === 0) {
        bar.style.width = '0%';
        text.textContent = 'Password Strength';
        text.className = 'text-muted fw-semibold';
        icon.className = 'fas fa-question-circle text-muted';
    }
});
</script>

</body>
</html>