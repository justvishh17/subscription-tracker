<?php
session_start();
include 'db.php';

$step = 1;
$script_output = "";
$user_id = "";
$saved_question = "";
$email_addr = "";

// --- STEP 1: VERIFY EMAIL ---
if (isset($_POST['check_email'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $step = 2; // Move to Security Question
        $saved_question = $user['security_question'];
        $user_id = $user['id'];
        $email_addr = $user['email'];
    } else {
        $script_output = "<script>Swal.fire({icon: 'error', title: 'Not Found', text: 'We could not find an account with that email.', confirmButtonColor: '#d33'});</script>";
    }
}

// --- ACTION: SWITCH TO OTP MODE ---
if (isset($_POST['request_otp'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    
    // 1. Generate 6-Digit OTP
    $otp = rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
    
    // 2. Save to Database
    $conn->query("UPDATE users SET reset_token='$otp', token_expiry='$expiry' WHERE id='$user_id'");
    
    // 3. SEND EMAIL (Developer Mode Simulation)
    // In a real app, use: mail($email, "Your OTP", "Code: $otp");
    $step = 'otp'; 
    $script_output = "
    <script>
        Swal.fire({
            icon: 'info',
            title: 'OTP Generated',
            html: 'Since you are on localhost, here is your code:<br><b style=\"font-size: 24px; color: #667eea; letter-spacing: 2px;\">$otp</b><br><small class=\"text-muted\">Copy this code to proceed.</small>',
            confirmButtonText: 'OK, I copied it'
        });
    </script>";
}

// --- STEP 2A: VERIFY SECURITY ANSWER ---
if (isset($_POST['verify_answer'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $answer = strtolower(trim($conn->real_escape_string($_POST['answer'])));
    
    $sql = "SELECT security_answer FROM users WHERE id='$user_id'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if ($answer == strtolower($row['security_answer'])) {
        $step = 3; // Go to Reset Password
    } else {
        // Fetch question again to keep form valid
        $u_res = $conn->query("SELECT security_question FROM users WHERE id='$user_id'");
        $saved_question = $u_res->fetch_assoc()['security_question'];
        $step = 2;
        $script_output = "<script>Swal.fire({icon: 'error', title: 'Wrong Answer', text: 'That is not the correct security answer.', confirmButtonColor: '#d33'});</script>";
    }
}

// --- STEP 2B: VERIFY OTP ---
if (isset($_POST['verify_otp'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $input_otp = $conn->real_escape_string($_POST['otp_code']);
    
    $sql = "SELECT reset_token, token_expiry FROM users WHERE id='$user_id'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    if ($row['reset_token'] == $input_otp && strtotime($row['token_expiry']) > time()) {
        $step = 3; // Success! Move to reset
    } else {
        $step = 'otp'; // Stay on OTP screen
        $script_output = "<script>Swal.fire({icon: 'error', title: 'Invalid Code', text: 'The code is wrong or expired.', confirmButtonColor: '#d33'});</script>";
    }
}

// --- STEP 3: RESET PASSWORD (Final) ---
if (isset($_POST['reset_final'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $new_pass = $_POST['new_pass'];
    
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
    
    // Update Password AND Clear OTP
    $conn->query("UPDATE users SET password='$hashed_pass', reset_token=NULL, token_expiry=NULL WHERE id='$user_id'");
    
    $script_output = "
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Password Reset!',
            text: 'You can now login with your new password.',
            confirmButtonText: 'Go to Login',
            confirmButtonColor: '#667eea',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = 'login.php'; }
        });
    </script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recovery - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛡️</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { animation: fadeInPage 0.5s ease-out forwards; } 
        @keyframes fadeInPage { 0% { opacity: 0; transform: translateY(15px); } 100% { opacity: 1; transform: translateY(0); } } 
    </style>
</head>
<body>

<div class="auth-container" style="height: 600px;"> 
    
    <div class="brand-side" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="mb-4"><i class="fas fa-shield-alt fa-4x mb-4"></i></div>
        <h1 class="fw-bold display-5">Account Recovery</h1>
        <p class="fs-5 opacity-75">Verify your identity to regain access.</p>
        <div class="mt-auto">
            <a href="login.php" class="text-white small opacity-75 text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
        </div>
    </div>

    <div class="form-side">
        
        <?php if($step == 1): ?>
            <h2 class="fw-bold mb-1">Forgot Password?</h2>
            <p class="text-muted small mb-4">Enter your email to search for your account.</p>
            <form method="POST">
                <div class="form-floating mb-3">
                    <input type="email" name="email" class="form-control" id="resetEmail" placeholder="Email" required>
                    <label>Email Address</label>
                </div>
                <button type="submit" name="check_email" class="btn btn-primary-modern shadow-sm w-100 mb-3">Next <i class="fas fa-arrow-right ms-1"></i></button>
            </form>
        <?php endif; ?>

        <?php if($step == 2): ?>
            <h2 class="fw-bold mb-1">Security Check</h2>
            <p class="text-muted small mb-4">Answer your security question.</p>
            
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                
                <div class="alert alert-light border shadow-sm mb-3">
                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Question</small>
                    <div class="fw-bold text-dark"><i class="fas fa-question-circle text-warning me-1"></i> <?php echo $saved_question; ?></div>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" name="answer" class="form-control" placeholder="Answer" required>
                    <label>Your Answer</label>
                </div>

                <button type="submit" name="verify_answer" class="btn btn-primary-modern shadow-sm w-100 mb-3">Verify Answer</button>
            </form>

            <div class="text-center mt-3">
                <p class="small text-muted mb-2">Don't remember the answer?</p>
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <button type="submit" name="request_otp" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                        <i class="fas fa-envelope me-1"></i> Try Email OTP
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if($step == 'otp'): ?>
            <div class="text-center mb-4">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                    <i class="fas fa-envelope-open-text fa-2x"></i>
                </div>
                <h3 class="fw-bold">Enter OTP</h3>
                <p class="text-muted small">We sent a 6-digit code to your email.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                
                <div class="form-floating mb-3">
                    <input type="number" name="otp_code" class="form-control text-center fw-bold letter-spacing-2" style="font-size: 1.5rem; letter-spacing: 5px;" placeholder="000000" required>
                    <label class="text-center w-100">6-Digit Code</label>
                </div>

                <button type="submit" name="verify_otp" class="btn btn-primary-modern shadow-sm w-100 mb-3">Verify Code</button>
            </form>
            <div class="text-center">
                <a href="forgot_password.php" class="small text-muted">Cancel</a>
            </div>
        <?php endif; ?>

        <?php if($step == 3): ?>
            <h2 class="fw-bold mb-1">Reset Password</h2>
            <p class="text-muted small mb-4">Create a new, strong password.</p>

            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <div class="form-floating mb-3">
                    <input type="password" name="new_pass" class="form-control" placeholder="New Password" required minlength="6">
                    <label>New Password</label>
                </div>
                <button type="submit" name="reset_final" class="btn btn-success shadow-sm w-100 mb-3">Update Password</button>
            </form>
        <?php endif; ?>

    </div>
</div>

<?php echo $script_output; ?>

</body>
</html>