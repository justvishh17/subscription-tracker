<?php
session_start();
include 'db.php';
$error_script = "";

// HANDLE ADMIN LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = $conn->real_escape_string($_POST['username']);
    $pass = $conn->real_escape_string($_POST['password']);

    // Check for user with admin role
    $sql = "SELECT * FROM users WHERE (email='$input' OR username='$input') AND role='admin'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        // Verify Password (assuming hashed, but if plain text in your legacy, update accordingly)
        // Using password_verify for security standard
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = 'admin';
            header("Location: admin.php"); // Redirect to Admin Dashboard
            exit();
        } else {
             // Fallback for plain text if you haven't hashed admin passwords yet
            if($pass === $row['password']) {
                 $_SESSION['user_id'] = $row['id'];
                 $_SESSION['username'] = $row['username'];
                 $_SESSION['role'] = 'admin';
                 header("Location: admin.php");
                 exit();
            }
            $error_script = "<script>Swal.fire({icon: 'error', title: 'Access Denied', text: 'Incorrect Password!', confirmButtonColor: '#dc3545'});</script>";
        }
    } else {
        $error_script = "<script>Swal.fire({icon: 'error', title: 'Unauthorized', text: 'Admin account not found.', confirmButtonColor: '#dc3545'});</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Portal - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>👑</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Override Brand Side for Admin (Darker/Red Tint) */
        .brand-side-admin {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 100%);
        }
        
        /* Admin Specific Button (Red Gradient) */
        .btn-admin {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 75, 43, 0.4);
        }
        
        body { animation: fadeInPage 0.5s ease-out forwards; }
        @keyframes fadeInPage {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="auth-container" style="height: 600px;">
    
    <div class="brand-side brand-side-admin">
        <div class="mb-4">
            <i class="fas fa-user-shield fa-4x text-white"></i>
        </div>
        <h1 class="fw-bold display-5 text-white">Admin Portal</h1>
        <p class="fs-5 opacity-75 text-white">Restricted Access. Authorized personnel only.</p>
        
        <div class="mt-auto">
            <a href="login.php" class="text-white fw-bold text-decoration-none small">
                <i class="fas fa-arrow-left me-2"></i> Return to User Login
            </a>
        </div>
    </div>

    <div class="form-side">
        <div class="mb-4">
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-1">
                <i class="fas fa-lock me-1"></i> Secure Area
            </span>
        </div>
        <h2 class="fw-bold mb-1">Admin Login</h2>
        <p class="text-muted small mb-4">Please verify your credentials.</p>

        <form method="POST">
            <div class="form-floating mb-3">
                <input type="text" name="username" class="form-control" id="adminUser" placeholder="Admin" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                       required autocomplete="username">
                <label for="adminUser">Admin Username or Email</label>
            </div>

            <div class="input-group mb-4">
                <div class="form-floating flex-grow-1">
                    <input type="password" name="password" class="form-control" id="adminPass" placeholder="Pass" 
                           required autocomplete="off">
                    <label for="adminPass">Password</label>
                </div>
                <span class="input-group-text bg-light border-start-0" onclick="togglePass()" style="cursor: pointer;">
                    <i class="fas fa-eye text-muted" id="toggleIcon"></i>
                </span>
            </div>

            <button type="submit" class="btn btn-primary-modern btn-admin w-100 py-3 fw-bold shadow-sm">
                Access Dashboard
            </button>
        </form>
        
        <div class="text-center mt-4">
            <p class="text-muted small" style="font-size: 0.75rem;">
                <i class="fas fa-info-circle me-1"></i> All activities are monitored and logged.
            </p>
        </div>
    </div>
</div>

<script>
    function togglePass() {
        var x = document.getElementById("adminPass");
        var icon = document.getElementById("toggleIcon");
        if (x.type === "password") {
            x.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            x.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
</script>

<?php echo $error_script; ?>

</body>
</html>