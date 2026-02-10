<?php
session_start();
include 'db.php';

// --- 1. SESSION & TIMEOUT LOGIC ---
$timeout = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header("Location: login.php?msg=session_expired"); exit();
}
$_SESSION['last_activity'] = time(); 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];
$msg_script = "";

// --- 2. HANDLE ACTIONS ---

// A. REMOVE PHOTO
if (isset($_POST['remove_photo'])) {
    $query = $conn->query("SELECT profile_pic FROM users WHERE id=$user_id");
    $user_data = $query->fetch_assoc();
    $old_pic = $user_data['profile_pic'];
    if ($old_pic != 'default.png' && !empty($old_pic) && file_exists("uploads/" . $old_pic)) { unlink("uploads/" . $old_pic); }
    $conn->query("UPDATE users SET profile_pic='default.png' WHERE id=$user_id");
    $msg_script = "Swal.fire('Success', 'Profile photo removed.', 'success');";
}

// B. UPLOAD PHOTO
if (isset($_FILES['profile_image']['name']) && $_FILES['profile_image']['name'] != "") {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $file_ext = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
    $new_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
    $target_file = $target_dir . $new_name;
    
    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            $conn->query("UPDATE users SET profile_pic='$new_name' WHERE id=$user_id");
            $msg_script = "Swal.fire('Success', 'Profile photo updated!', 'success');";
        }
    }
}

// C. UPDATE PROFILE INFO
if (isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $location = $conn->real_escape_string($_POST['location']);
    $bio = $conn->real_escape_string($_POST['bio']);
    $phone = $conn->real_escape_string($_POST['phone']);
    
    $conn->query("UPDATE users SET full_name='$full_name', location='$location', bio='$bio', phone_number='$phone' WHERE id=$user_id");
    $msg_script = "Swal.fire('Saved', 'Profile details updated!', 'success');";
}

// D. CHANGE PASSWORD
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $query = $conn->query("SELECT password FROM users WHERE id=$user_id");
    $user_data = $query->fetch_assoc();

    if (password_verify($current_pass, $user_data['password'])) {
        if ($new_pass === $confirm_pass) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$new_hash' WHERE id=$user_id");
            $msg_script = "Swal.fire('Success', 'Password changed successfully!', 'success');";
        } else { $msg_script = "Swal.fire('Error', 'New passwords do not match.', 'error');"; }
    } else { $msg_script = "Swal.fire('Error', 'Current password is incorrect.', 'error');"; }
}

// E. DELETE ACCOUNT
if (isset($_POST['delete_my_account'])) {
    $conn->query("DELETE FROM subscriptions WHERE user_id = $user_id");
    $conn->query("DELETE FROM login_logs WHERE user_id = $user_id");
    $conn->query("DELETE FROM users WHERE id = $user_id");
    session_destroy();
    header("Location: login.php?msg=account_deleted"); exit();
}

// --- 3. FETCH USER DATA ---
$user = $conn->query("SELECT *, DATE_FORMAT(created_at, '%M %Y') as join_date FROM users WHERE id = $user_id")->fetch_assoc();
$stats = $conn->query("SELECT COUNT(*) as total_subs, SUM(price) as total_spend FROM subscriptions WHERE user_id = $user_id")->fetch_assoc();
$total_spend_usd = $stats['total_spend'] ?? 0;

// Determine Profile Pic URL
$user_pic = (!empty($user['profile_pic']) && $user['profile_pic'] != 'default.png' && file_exists("uploads/" . $user['profile_pic'])) 
            ? "uploads/" . $user['profile_pic'] 
            : "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=random&size=128&color=fff";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>👤</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Base Styles */
        body { 
            background-color: #f0f2f5; 
            font-family: 'Segoe UI', sans-serif; 
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .settings-card { 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            overflow: hidden; 
            background: white; 
            transition: background-color 0.3s ease;
        }
        
        .settings-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; padding: 40px 20px; text-align: center; position: relative; 
        }

        /* Profile Pic - INCREASED SIZE */
        .profile-wrapper { 
            width: 160px; height: 160px; /* Changed from 120px to 160px */
            margin: 0 auto 15px; position: relative; 
        }
        .profile-img { 
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%; 
            border: 4px solid rgba(255,255,255,0.3); box-shadow: 0 5px 15px rgba(0,0,0,0.2); 
        }
        .upload-btn {
            position: absolute; bottom: 5px; right: 5px;
            background: white; color: #667eea; border: none;
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .upload-btn:hover { transform: scale(1.1); }

        /* Navigation Pills */
        .nav-pills { justify-content: center; margin-bottom: 30px; }
        .nav-pills .nav-link { 
            color: #6c757d; font-weight: 600; padding: 10px 25px; border-radius: 50px; margin: 0 5px; 
        }
        .nav-pills .nav-link.active { background-color: #667eea; color: white; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3); }

        .header-badge {
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
            padding: 5px 15px; border-radius: 20px; font-size: 0.85rem; backdrop-filter: blur(5px);
            display: inline-flex; align-items: center;
        }
        
        .currency-select {
            background: transparent; border: none; color: white; 
            font-size: 0.85rem; font-weight: bold; cursor: pointer;
            margin-left: 5px; outline: none;
        }
        .currency-select option { color: #333; }

        .readonly-field { background-color: #f8f9fa; cursor: not-allowed; opacity: 0.8; }
        
        .danger-zone { background: #fff5f5; border: 1px solid #ffc9c9; border-radius: 15px; padding: 20px; }

        .btn-nav-custom:hover { background-color: #e9ecef; }

        /* --- DARK MODE STYLES --- */
        body.dark-mode {
            background-color: #0f172a;
            color: #e2e8f0;
        }
        body.dark-mode .settings-card {
            background-color: #1e293b;
            color: #e2e8f0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        body.dark-mode .readonly-field {
            background-color: #0f172a;
            border-color: #334155;
            color: #94a3b8;
        }
        body.dark-mode .form-control {
            background-color: #334155;
            border-color: #475569;
            color: #fff;
        }
        body.dark-mode .form-control:focus {
            background-color: #334155;
            border-color: #667eea;
            color: #fff;
        }
        body.dark-mode .text-muted {
            color: #94a3b8 !important;
        }
        body.dark-mode .nav-link {
            color: #cbd5e1;
        }
        body.dark-mode .list-group-item {
            background-color: transparent;
            color: #e2e8f0;
            border-color: #475569;
        }
        body.dark-mode .danger-zone {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
        }
        body.dark-mode .btn-outline-dark {
            color: #e2e8f0;
            border-color: #e2e8f0;
        }
        body.dark-mode .btn-outline-dark:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg mt-3 mb-4">
    <div class="container">
        <div class="ms-auto">
            <a href="index.php" class="btn btn-outline-dark btn-sm me-2 btn-nav-custom px-3 rounded-pill">
                <i class="fas fa-chart-pie me-1"></i> Dashboard
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm shadow-sm px-3 rounded-pill">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <div class="card settings-card">
                <div class="settings-header">
                    <form method="POST" enctype="multipart/form-data" id="picForm">
                        <div class="profile-wrapper">
                            <img src="<?php echo $user_pic; ?>" class="profile-img">
                            <label for="picUpload" class="upload-btn" title="Change Photo"><i class="fas fa-camera"></i></label>
                            <input type="file" id="picUpload" name="profile_image" style="display:none;" onchange="document.getElementById('picForm').submit()">
                        </div>
                    </form>

                    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($user['username']); ?></h3>
                    <p class="opacity-75 mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <span class="header-badge"><i class="fas fa-layer-group me-1"></i> <?php echo $stats['total_subs']; ?> Subscriptions</span>
                        
                        <span class="header-badge">
                            <i class="fas fa-wallet me-1"></i> 
                            <span id="spendDisplay">...</span>
                            <select id="currencySelector" class="currency-select" onchange="updateCurrency()">
                                <option value="USD">USD ($)</option>
                                <option value="INR">INR (₹)</option>
                                <option value="EUR">EUR (€)</option>
                            </select>
                        </span>
                    </div>

                    <?php if (strpos($user_pic, 'ui-avatars') === false): ?>
                        <form method="POST" class="mt-2">
                            <button type="submit" name="remove_photo" class="btn btn-link text-white text-decoration-none small" style="font-size: 0.8rem; opacity: 0.8;">
                                <i class="fas fa-times me-1"></i> Remove Photo
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card-body p-4">
                    <ul class="nav nav-pills" id="pills-tab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-profile">
                                <i class="fas fa-user-edit me-2"></i>Edit Profile
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-security">
                                <i class="fas fa-shield-alt me-2"></i>Security
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-tabContent">
                        
                        <div class="tab-pane fade show active" id="tab-profile">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Username <i class="fas fa-lock small ms-1 text-muted"></i></label>
                                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Email Address <i class="fas fa-lock small ms-1 text-muted"></i></label>
                                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Full Name</label>
                                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Phone Number</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-muted small fw-bold">Location</label>
                                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($user['location']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-muted small fw-bold">Bio</label>
                                        <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                                    </div>
                                    <div class="col-12 text-end mt-3">
                                        <button type="submit" name="update_profile" class="btn btn-primary px-4" style="background-color: #667eea; border:none;">Save Changes</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="tab-security">
                            <h6 class="fw-bold mb-3 text-primary">Change Password</h6>
                            <form method="POST" class="mb-5">
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                    <div class="text-end mt-1">
                                        <a href="forgot_password.php" class="text-decoration-none small text-primary">Forgot Password?</a>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="password" name="new_password" class="form-control" placeholder="New Password" required minlength="6">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New Password" required>
                                    </div>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-secondary btn-sm mt-3">Update Password</button>
                            </form>

                            <h6 class="fw-bold mb-3 text-primary">Recent Logins</h6>
                            <div class="list-group mb-4">
                                <?php 
                                $check_logs = $conn->query("SHOW TABLES LIKE 'login_logs'");
                                if($check_logs->num_rows > 0) {
                                    $logs = $conn->query("SELECT * FROM login_logs WHERE user_id=$user_id ORDER BY login_time DESC LIMIT 3");
                                    while($log = $logs->fetch_assoc()): 
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-desktop text-muted me-2"></i> 
                                        <span class="small fw-bold"><?php echo $log['ip_address']; ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo date("M d, h:i A", strtotime($log['login_time'])); ?></small>
                                </div>
                                <?php endwhile; 
                                } else { echo "<div class='text-muted small'>No logs found.</div>"; }
                                ?>
                            </div>

                            <div class="danger-zone">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-danger">Delete Account</strong>
                                        <p class="small text-muted mb-0">Permanently delete your data.</p>
                                    </div>
                                    <form id="deleteForm" method="POST">
                                        <input type="hidden" name="delete_my_account" value="1">
                                        <button type="button" onclick="confirmDelete()" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // AUTO-DETECT DARK MODE FROM LOCAL STORAGE
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // SweetAlerts Logic
    <?php echo $msg_script; ?>

    function confirmDelete() {
        Swal.fire({
            title: 'Are you sure?',
            text: "You cannot undo this action!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, Delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').submit();
            }
        });
    }

    // CURRENCY CONVERTER LOGIC
    const baseAmountUSD = <?php echo $total_spend_usd; ?>;
    
    function updateCurrency() {
        const currency = document.getElementById('currencySelector').value;
        const display = document.getElementById('spendDisplay');
        
        // Approximate Exchange Rates (You can update these)
        const rates = {
            'USD': { rate: 1, symbol: '$' },
            'INR': { rate: 87, symbol: '₹' }, // Approx 1 USD = 87 INR
            'EUR': { rate: 0.92, symbol: '€' } // Approx 1 USD = 0.92 EUR
        };

        const selected = rates[currency];
        const converted = (baseAmountUSD * selected.rate).toFixed(2);
        
        display.innerText = selected.symbol + converted;
    }

    // Run once on load
    updateCurrency();
</script>

</body>
</html>