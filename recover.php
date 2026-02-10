<?php
session_start();
include 'db.php';
$msg = "";
$msg_type = "";

// 1. FIRST STEP: USER ENTERS EMAIL
// We need to know WHO is trying to recover the account first.
$step = 1;
$security_question = "";
$user_id_recovery = 0;

if (isset($_POST['check_email'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $result = $conn->query("SELECT id, security_question FROM users WHERE email='$email'");
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Check if they actually set a question
        if (!empty($row['security_question'])) {
            $step = 2;
            $security_question = $row['security_question'];
            $user_id_recovery = $row['id'];
        } else {
            $msg = "This account has not set up a security question. Contact Admin.";
            $msg_type = "error";
        }
    } else {
        $msg = "Email not found.";
        $msg_type = "error";
    }
}

// 2. SECOND STEP: VERIFY ANSWER & RESET
if (isset($_POST['reset_password'])) {
    $uid = $_POST['uid'];
    $input_answer = strtolower(trim($_POST['answer'])); // User input
    $new_pass = $_POST['new_pass'];

    // FETCH REAL ANSWER
    $result = $conn->query("SELECT security_answer FROM users WHERE id='$uid'");
    $row = $result->fetch_assoc();
    $real_answer = strtolower(trim($row['security_answer'])); // Database answer

    // *** CRITICAL SECURITY CHECK ***
    if ($input_answer === $real_answer) {
        // MATCH! Update password
        $conn->query("UPDATE users SET password='$new_pass' WHERE id='$uid'");
        $msg = "Password reset successful! Redirecting to login...";
        $msg_type = "success";
        $step = 3; // Success state
    } else {
        // NO MATCH!
        $msg = "Security Answer is INCORRECT. Password was NOT changed.";
        $msg_type = "error";
        
        // Keep them on step 2 to try again, but we need to re-fetch the question text
        $q_res = $conn->query("SELECT security_question FROM users WHERE id='$uid'");
        $security_question = $q_res->fetch_assoc()['security_question'];
        $user_id_recovery = $uid;
        $step = 2; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Recover Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 100%; max-width: 450px; overflow: hidden; }
        .header { background: #fff; padding: 30px 30px 10px; text-align: center; }
        .header h3 { font-weight: 800; color: #444; margin-bottom: 5px; }
        .body { padding: 30px; }
        .form-control { background: #f4f6f8; border: none; padding: 12px; border-radius: 10px; }
        .form-control:focus { background: #fff; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); }
        .btn-primary { background: linear-gradient(to right, #667eea, #764ba2); border: none; padding: 12px; border-radius: 10px; font-weight: 700; width: 100%; transition: 0.3s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h3>Recover Account</h3>
        <p class="text-muted small">Reset your password securely.</p>
    </div>
    
    <div class="body">
        
        <?php if($step == 1): ?>
        <form method="POST">
            <div class="mb-4">
                <label class="form-label fw-bold small text-muted">Enter your Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
            </div>
            <button type="submit" name="check_email" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-2"></i></button>
            <div class="text-center mt-3">
                <a href="login.php" class="text-decoration-none small text-muted">Back to Login</a>
            </div>
        </form>
        <?php endif; ?>

        <?php if($step == 2): ?>
        <form method="POST">
            <input type="hidden" name="uid" value="<?php echo $user_id_recovery; ?>">
            
            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">Security Question:</label>
                <div class="p-3 bg-light rounded text-dark border">
                    <i class="fas fa-question-circle text-primary me-2"></i> 
                    <?php 
                        if($security_question == 'pet') echo "What was your first pet's name?";
                        elseif($security_question == 'city') echo "In what city were you born?";
                        elseif($security_question == 'school') echo "What high school did you attend?";
                        else echo htmlspecialchars($security_question);
                    ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">Your Answer</label>
                <input type="text" name="answer" class="form-control" placeholder="Type answer here..." required autocomplete="off">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-muted">New Password</label>
                <input type="password" name="new_pass" class="form-control" placeholder="Enter new password" required>
            </div>

            <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
            <div class="text-center mt-3">
                <a href="recover.php" class="text-decoration-none small text-muted">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

    </div>
</div>

<?php if($step == 3): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Password updated. Logging you in...',
        timer: 2000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = 'login.php';
    });
</script>
<?php endif; ?>

<?php if($msg && $step != 3): ?>
<script>
    Swal.fire({
        icon: '<?php echo $msg_type; ?>',
        title: '<?php echo ($msg_type == "error") ? "Failed" : "Success"; ?>',
        text: '<?php echo $msg; ?>',
        confirmButtonColor: '#d33'
    });
</script>
<?php endif; ?>

</body>
</html>