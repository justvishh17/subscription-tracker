<?php
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

// 2. FETCH SETTINGS
$settings_query = $conn->query("SELECT * FROM site_settings WHERE id=1");
$settings = ($settings_query && $settings_query->num_rows > 0) ? $settings_query->fetch_assoc() : ['allow_signups' => 1, 'maintenance_mode' => 0];

// --- HELPER FUNCTIONS ---
function checkLoginAttempts($conn, $identifier) {
    $window_start = date("Y-m-d H:i:s", time() - (24 * 60 * 60));
    $query = $conn->query("SELECT COUNT(*) as cnt, MAX(attempt_time) as last_attempt FROM login_attempts WHERE email_or_user='$identifier' AND attempt_time >= '$window_start'");
    $row = $query->fetch_assoc();
    $total = $row['cnt'];
    $last_attempt_ts = $row['last_attempt'] ? strtotime($row['last_attempt']) : 0;
    
    if ($total < 3) return ['is_locked' => false, 'total_attempts' => $total];
    
    $lockout_minutes = floor($total / 3);
    $unlock_time = $last_attempt_ts + ($lockout_minutes * 60);
    
    return [
        'is_locked' => time() < $unlock_time,
        'total_attempts' => $total,
        'unlock_time' => $unlock_time
    ];
}

function recordFailedAttempt($conn, $identifier) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $current_time = date("Y-m-d H:i:s");
    $conn->query("INSERT INTO login_attempts (email_or_user, ip_address, attempt_time) VALUES ('$identifier', '$ip', '$current_time')");
}

// 3. HANDLE AJAX REQUEST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); 
    
    $input = $conn->real_escape_string($_POST['email_or_user']); 
    $pass = $conn->real_escape_string($_POST['password']);
    
    // A. CHECK LOCK STATUS
    $status = checkLoginAttempts($conn, $input);
    if ($status['is_locked']) {
        echo json_encode(['status' => 'locked', 'unlock_time' => $status['unlock_time']]);
        exit();
    }

    // B. CHECK USER
    $sql = "SELECT * FROM users WHERE email='$input' OR username='$input'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        if (isset($row['status']) && $row['status'] === 'banned') {
            echo json_encode(['status' => 'error', 'message' => 'Account Suspended', 'detail' => 'Contact support.']);
            exit();
        }

        $check_pass = password_verify($pass, $row['password']);
        if(!$check_pass && $pass === $row['password']) { $check_pass = true; } 

        if ($check_pass) {
            // SUCCESS
            $conn->query("DELETE FROM login_attempts WHERE email_or_user='$input'");
            
            if ($settings['maintenance_mode'] == 1 && $row['role'] !== 'admin') {
                echo json_encode(['status' => 'warning', 'message' => 'Under Maintenance']);
                exit();
            }

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['last_activity'] = time();

            $log_uid = $row['id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $conn->query("INSERT INTO login_logs (user_id, ip_address, browser, login_time, status) VALUES ('$log_uid', '$ip', 'Unknown', NOW(), 'Success')");

            echo json_encode(['status' => 'success', 'redirect' => ($row['role'] == 'admin' ? "admin.php" : "index.php")]);
            exit();

        } else {
            // WRONG PASSWORD
            recordFailedAttempt($conn, $input);
            $new_status = checkLoginAttempts($conn, $input);
            
            if ($new_status['is_locked']) {
                echo json_encode(['status' => 'locked', 'unlock_time' => $new_status['unlock_time']]);
            } else {
                $remaining = 3 - $new_status['total_attempts'];
                echo json_encode(['status' => 'fail', 'remaining' => $remaining]);
            }
            exit();
        }
    } else {
        // USER NOT FOUND
        echo json_encode(['status' => 'error', 'message' => 'Account Not Found', 'detail' => 'No account found with that email/username.']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
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
    <div class="brand-side">
        <div class="mb-4"><i class="fas fa-rocket fa-4x mb-4"></i></div>
        <h1 class="fw-bold display-4">Welcome Back</h1>
        <p class="fs-5 opacity-75">Log in to manage your subscriptions.</p>
        <div class="mt-auto"><a href="admin_login.php" class="text-white small opacity-75 text-decoration-none" >Admin Portal</a></div>
    </div>

    <div class="form-side">
        <h2 class="fw-bold mb-1">Login</h2>
        <?php if($settings['maintenance_mode'] == 1): ?>
            <div class="alert alert-warning py-2 small fw-bold mb-3">Maintenance Active</div>
        <?php else: ?>
            <p class="text-muted small mb-4">Secure access to your account.</p>
        <?php endif; ?>

        <form id="loginForm" autocomplete="off">
            <div class="form-floating mb-3">
                <input type="text" name="email_or_user" class="form-control" id="floatingInput" placeholder="User" required autocomplete="off">
                <label for="floatingInput">Email or Username</label>
            </div>
            <div class="input-group mb-3">
                <div class="form-floating flex-grow-1">
                    <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Pass" required autocomplete="new-password">
                    <label for="floatingPassword">Password</label>
                </div>
                <span class="input-group-text bg-light border-start-0" onclick="togglePass()" style="cursor: pointer;">
                    <i class="fas fa-eye text-muted" id="toggleIcon"></i>
                </span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
                </div>
                <a href="forgot_password.php" class="small text-decoration-none fw-bold">Forgot Password?</a>
            </div>

            <button type="submit" id="submitBtn" class="btn btn-primary-modern shadow-sm mb-4">Log In</button>
        </form>

        <div class="text-center mt-2">
            <?php if($settings['allow_signups'] == 1): ?>
                <p class="text-muted small">Don't have an account? <a href="signup.php" class="fw-bold text-primary text-decoration-none">Sign up</a></p>
            <?php else: ?>
                <span class="text-muted small">Registrations closed.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function togglePass() {
        var x = document.getElementById("floatingPassword");
        var icon = document.getElementById("toggleIcon");
        if (x.type === "password") { x.type = "text"; icon.classList.replace("fa-eye", "fa-eye-slash"); } 
        else { x.type = "password"; icon.classList.replace("fa-eye-slash", "fa-eye"); }
    }

    // AJAX LOGIN HANDLER
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        const formData = new FormData(this);
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        submitBtn.disabled = true;

        fetch('login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;

            // --- THE FIX IS HERE ---
            // If it is NOT a success, we clear the password field instantly.
            // But we leave the Username field alone.
            if (data.status !== 'success') {
                document.getElementById('floatingPassword').value = '';
            }
            // -----------------------

            if (data.status === 'success') {
                window.location.href = data.redirect;
            } 
            else if (data.status === 'fail') {
                // Wrong Password
                Swal.fire({
                    icon: 'error',
                    title: 'Wrong Password',
                    text: data.remaining + ' attempts left before lockout.',
                    confirmButtonColor: '#d33'
                });
            }
            else if (data.status === 'error') {
                // Account Not Found
                Swal.fire({
                    icon: 'error',
                    title: data.message,
                    text: data.detail,
                    confirmButtonColor: '#d33'
                });
            }
            else if (data.status === 'warning') {
                Swal.fire({ icon: 'warning', title: 'Notice', text: data.message });
            }
            else if (data.status === 'locked') {
                showLockoutPopup(data.unlock_time);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    // LOCKOUT POPUP LOGIC
    function showLockoutPopup(unlockTime) {
        let lockoutEndTime = unlockTime * 1000;
        let countdownInterval;

        Swal.fire({
            icon: 'warning',
            title: 'LOCKED',
            html: '<div class="mt-3 mb-4"><p>Too many failed attempts!</p><h2 style="color:#d33; font-weight:bold; margin:15px 0;">⏳ <span id="lockout-countdown">Checking...</span></h2></div>' +
                  '<button type="button" id="btn-retry-manual" style="background-color: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Try Different Account</button>',
            showConfirmButton: true,
            confirmButtonText: 'Wait...',
            confirmButtonColor: '#f39c12',
            allowOutsideClick: true,
            didOpen: (modal) => {
                let retryBtn = document.getElementById('btn-retry-manual');
                if(retryBtn) {
                    retryBtn.addEventListener('click', function() {
                        Swal.close();
                        document.getElementById('floatingInput').value = '';
                        document.getElementById('floatingPassword').value = '';
                        document.getElementById('floatingInput').focus();
                    });
                }

                const confirmBtn = modal.querySelector('.swal2-confirm');
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';

                function updateTimer() {
                    let now = new Date().getTime();
                    let timeLeft = Math.max(0, Math.ceil((lockoutEndTime - now) / 1000));
                    let minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;
                    let timerEl = document.getElementById('lockout-countdown');
                    if (timerEl) timerEl.innerText = minutes + 'm ' + (seconds < 10 ? '0' + seconds : seconds) + 's';
                    
                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        confirmBtn.disabled = false;
                        confirmBtn.style.opacity = '1';
                        confirmBtn.textContent = 'Try Again';
                    }
                }

                updateTimer();
                countdownInterval = setInterval(updateTimer, 1000);
            }
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'created') {
        Swal.fire({ icon: 'success', title: 'Account Created!', text: 'Login now.', confirmButtonColor: '#667eea' });
        window.history.replaceState(null, null, window.location.pathname);
    }
</script>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'loggedout'): ?>
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    Toast.fire({ icon: 'success', title: 'Logged out successfully' });
    if (window.history.replaceState) {
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: newUrl }, '', newUrl);
    }
</script>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'loggedout'): ?>
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    Toast.fire({
        icon: 'success',
        title: 'Logged out successfully'
    });

    // Clean the URL so the popup doesn't show again if they refresh
    if (window.history.replaceState) {
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: newUrl }, '', newUrl);
    }
</script>
<?php endif; ?>
</body>
</html>



