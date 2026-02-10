<?php
include 'db.php';
$popup_script = ""; // Variable to hold SweetAlert logic

// --- 1. FETCH SITE SETTINGS (NEW CODE) ---
// We need to check if Signups are allowed OR if Maintenance is active
$settings_query = $conn->query("SELECT allow_signups, maintenance_mode FROM site_settings WHERE id=1");
$settings = ($settings_query && $settings_query->num_rows > 0) ? $settings_query->fetch_assoc() : ['allow_signups' => 1, 'maintenance_mode' => 0];

// BLOCK ACCESS IF MAINTENANCE IS ON
if ($settings['maintenance_mode'] == 1) {
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif; color:#333;'>
            <h2 style='color:#d33;'>⚠️ System Under Maintenance</h2>
            <p>We are currently upgrading the platform. Please try again later.</p>
            <a href='login.php' style='text-decoration:none; color:#667eea; font-weight:bold;'>Back to Login</a>
          </div>";
    exit(); // Stop loading the page
}

// BLOCK ACCESS IF SIGNUPS ARE DISABLED
if ($settings['allow_signups'] == 0) {
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif; color:#333;'>
            <h2 style='color:#f39c12;'>🚫 Registrations Closed</h2>
            <p>We are not accepting new accounts at this time.</p>
            <a href='login.php' style='text-decoration:none; color:#667eea; font-weight:bold;'>Back to Login</a>
          </div>";
    exit(); // Stop loading the page
}
// -----------------------------------------


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Security: Basic sanitization
    $user = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $pass = $_POST['password']; 
    $confirm_pass = $_POST['confirm_password']; 
    $question = $conn->real_escape_string($_POST['question']);
    $answer = strtolower(trim($conn->real_escape_string($_POST['answer'])));

    // 1. VALIDATION: Check if passwords match
    if ($pass !== $confirm_pass) {
        $popup_script = "
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Passwords do not match!',
                text: 'Please re-type your password correctly.',
                confirmButtonColor: '#d33'
            });
        </script>";
    } 
    else {
        // 2. CHECK IF EMAIL EXISTS
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if($check->num_rows > 0){
            $popup_script = "
            <script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Email Taken',
                    text: 'This email is already registered.',
                    confirmButtonColor: '#f39c12'
                });
            </script>";
        } else {
            // 3. CREATE ACCOUNT
            // Hash the password for security
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password, role, security_question, security_answer) 
                    VALUES ('$user', '$email', '$hashed_pass', 'user', '$question', '$answer')";
            
            if ($conn->query($sql) === TRUE) {
                // Send success signal to login page
                header("Location: login.php?success=created");
                exit();
            } else {
                $error_msg = $conn->error;
                $popup_script = "<script>Swal.fire('Error', '$error_msg', 'error');</script>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
</head>
<body>

<div class="signup-container">
    <div class="brand-panel">
        <i class="fas fa-rocket fa-4x mb-4"></i>
        <h1 class="fw-bold display-4">SubTrack</h1>
        <p class="opacity-75 fs-5">Join the Pro community. Track smarter and save more starting today.</p>
        <div class="mt-auto">
            <p class="mb-0 opacity-50 small">Already have an account?</p>
            <a href="login.php" class="text-white fw-bold text-decoration-none">Login here <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>

    <div class="form-panel">
        <h3 class="fw-bold mb-1">Create Account</h3>
        <p class="text-muted small mb-4">Complete your details to get started.</p>
    <form method="POST">
        <!-- Account Information Section -->
        <div class="form-section">
            <h6>Account Information</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" name="username" class="form-control" id="floatingUser" placeholder="Username" required>
                        <label for="floatingUser">Username</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="email" name="email" class="form-control" id="floatingEmail" placeholder="name@example.com" required>
                        <label for="floatingEmail">Email address</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Password Section -->
        <div class="form-section">
            <h6>Password</h6>
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-floating">
                        <input type="password" name="password" class="form-control" id="floatingPass" placeholder="Password" required>
                        <label for="floatingPass">Password</label>
                    </div>
                    <div id="password-strength" class="mt-2">
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
                </div>
                <div class="col-12">
                    <div class="form-floating">
                        <input type="password" name="confirm_password" class="form-control" id="floatingConfirm" placeholder="Confirm Password" required>
                        <label for="floatingConfirm">Confirm Password</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Section -->
        <div class="form-section">
            <h6>Security Question</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-floating">
                        <select name="question" class="form-select" id="floatingSelect" required>
                            <option value="">Select a security question</option>
                            <option value="What is your pet name?">What is your pet's name?</option>
                            <option value="What city were you born in?">What city were you born in?</option>
                            <option value="What is your favorite color?">What is your favorite color?</option>
                        </select>
                        <label for="floatingSelect">Security Question</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" name="answer" class="form-control" id="floatingAns" placeholder="Answer" required>
                        <label for="floatingAns">Your Answer</label>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary-modern">Create Account</button>
    </form>
</div>

<?php echo $popup_script; ?>

<script>
document.getElementById('floatingPass').addEventListener('input', function() {
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