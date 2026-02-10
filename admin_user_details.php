<?php
session_start();
include 'db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: admin.php"); exit(); }

$target_id = (int)$_GET['id'];

// 1. HANDLE BAN/UNBAN TOGGLE
if (isset($_POST['toggle_status'])) {
    $check = $conn->query("SELECT status FROM users WHERE id=$target_id")->fetch_assoc();
    $current_status = $check['status'] ?? 'active';
    $new_status = ($current_status === 'banned') ? 'active' : 'banned';
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $target_id);
    $stmt->execute();
    
    header("Location: admin_user_details.php?id=$target_id&msg=updated");
    exit();
}

// 2. FETCH DATA
$settings_query = $conn->query("SELECT * FROM site_settings WHERE id=1");
$settings = ($settings_query && $settings_query->num_rows > 0) ? $settings_query->fetch_assoc() : ['currency_symbol' => '$'];
$currency = $settings['currency_symbol'] ?? '$';

$user = $conn->query("SELECT * FROM users WHERE id=$target_id")->fetch_assoc();
$subs = $conn->query("SELECT * FROM subscriptions WHERE user_id=$target_id ORDER BY price DESC");
$total_spend = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Insights - <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>👑</text></svg>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* --- MODERN DARK THEME --- */
        :root {
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.6);
            --glass-border: 1px solid rgba(255, 255, 255, 0.1);
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --accent-green: #10b981;
        }

        body { 
            background-color: var(--bg-dark); 
            background-image: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 20%);
            color: #f1f5f9; 
            font-family: 'Segoe UI', sans-serif; 
            min-height: 100vh;
            padding: 40px 0;
        }

        /* Back Button */
        .btn-back {
            color: #94a3b8; text-decoration: none; font-weight: 600; 
            transition: 0.3s; display: inline-flex; align-items: center;
        }
        .btn-back:hover { color: #fff; transform: translateX(-5px); }

        /* Glassmorphism Cards */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: var(--glass-border);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(255,255,255,0.2);
        }

        /* Profile Hero Section */
        .profile-avatar {
            width: 100px; height: 100px; border-radius: 50%; object-fit: cover;
            border: 4px solid rgba(255,255,255,0.1);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
        }
        .status-badge {
            font-size: 0.8rem; letter-spacing: 1px; text-transform: uppercase;
            padding: 5px 15px; border-radius: 50px; font-weight: 700;
        }
        .status-active { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-banned { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Subscription Cards */
        .sub-icon-box {
            width: 45px; height: 45px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-right: 15px;
        }
        .price-tag { font-size: 2rem; font-weight: 800; letter-spacing: -1px; cursor: pointer; transition: 0.2s; }
        .price-tag:hover { color: var(--accent-blue); text-shadow: 0 0 15px rgba(59, 130, 246, 0.5); }

        /* Action Buttons */
        .btn-action {
            border-radius: 12px; padding: 12px 25px; font-weight: 600; letter-spacing: 0.5px;
            border: none; transition: 0.3s;
        }
        .btn-ban { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
        .btn-ban:hover { box-shadow: 0 6px 20px rgba(239, 68, 68, 0.6); transform: scale(1.02); }
        
        .btn-unban { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); }
        .btn-unban:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6); transform: scale(1.02); }
    </style>
</head>
<body>

<div class="container">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <a href="admin.php" class="btn-back"><i class="fas fa-arrow-left me-2"></i> Dashboard</a>
        <div class="text-end">
            <h4 class="fw-bold m-0">User Insights</h4>
            <small class="text-muted">Managing ID #<?php echo $target_id; ?></small>
        </div>
    </div>

    <div class="glass-card p-4 p-md-5 mb-5">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center">
                <div class="position-relative">
                    <img src="<?php echo !empty($user['profile_pic']) && file_exists('uploads/'.$user['profile_pic']) ? 'uploads/'.$user['profile_pic'] : 'https://ui-avatars.com/api/?name='.$user['username'].'&background=0f172a&color=fff'; ?>" 
                         class="profile-avatar me-4">
                    <span class="position-absolute bottom-0 end-0 translate-middle p-2 bg-success border border-dark rounded-circle me-4"></span>
                </div>
                
                <div>
                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="text-muted mb-2 font-monospace small"><i class="far fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <?php 
                        $status = $user['status'] ?? 'active'; 
                        $status_class = ($status === 'banned') ? 'status-banned' : 'status-active';
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> <?php echo $status; ?>
                    </span>
                </div>
            </div>

            <div class="col-md-4 text-md-end mt-4 mt-md-0">
                <form method="POST" id="statusForm">
                    <input type="hidden" name="toggle_status" value="1">
                    <?php if($status === 'banned'): ?>
                        <button type="button" class="btn-action btn-unban w-100 w-md-auto" onclick="confirmAction('unban')">
                            <i class="fas fa-lock-open me-2"></i> Reactivate User
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-action btn-ban w-100 w-md-auto" onclick="confirmAction('ban')">
                            <i class="fas fa-ban me-2"></i> Ban Access
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <h5 class="mb-4 text-white opacity-75"><i class="fas fa-wallet me-2"></i>Active Subscriptions</h5>

    <div class="row g-4">
        <?php while($sub = $subs->fetch_assoc()): 
            $monthly_price = ($sub['billing_period'] == 'Yearly') ? $sub['price']/12 : $sub['price'];
            $total_spend += $monthly_price;
            
            // Random colors for icons based on category
            $colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'];
            $icon_color = $colors[array_rand($colors)];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="glass-card p-4 h-100 d-flex flex-column">
                
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="d-flex align-items-center">
                        <div class="sub-icon-box" style="background: <?php echo $icon_color; ?>20; color: <?php echo $icon_color; ?>;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-white"><?php echo htmlspecialchars($sub['service_name']); ?></h5>
                            <span class="badge bg-light text-dark bg-opacity-10 border border-secondary text-white-50" style="font-size: 0.7rem;">
                                <?php echo $sub['billing_period']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="price-tag" data-usd="<?php echo $sub['price']; ?>" title="Click to convert">
                        <?php echo $currency . number_format($sub['price'], 2); ?>
                    </div>
                    <small class="text-muted">per <?php echo strtolower(rtrim($sub['billing_period'], 'ly')); ?></small>
                </div>
                
                <div class="mt-auto pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <small class="text-muted"><i class="fas fa-tag me-1"></i> <?php echo $sub['category']; ?></small>
                    <small class="text-warning"><i class="far fa-clock me-1"></i> <?php echo date("M d, Y", strtotime($sub['next_due_date'])); ?></small>
                </div>

            </div>
        </div>
        <?php endwhile; ?>
        
        <?php if($subs->num_rows == 0): ?>
            <div class="col-12">
                <div class="glass-card p-5 text-center opacity-50">
                    <i class="fas fa-folder-open fa-3x mb-3"></i>
                    <p class="fs-5 m-0">No active subscriptions found.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // 1. CUSTOM CONFIRMATION POPUP (Dark Theme)
    function confirmAction(type) {
        const isBan = type === 'ban';
        
        Swal.fire({
            title: isBan ? 'Ban User?' : 'Unban User?',
            text: isBan ? "They will lose access immediately!" : "They will regain access to their account.",
            icon: isBan ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isBan ? '#ef4444' : '#10b981', // Red for Ban, Green for Unban
            cancelButtonColor: '#64748b',
            confirmButtonText: isBan ? 'Yes, Ban Them!' : 'Yes, Unban!',
            background: '#1e293b', // Matches your Dark Card BG
            color: '#fff', // White text
            iconColor: isBan ? '#ef4444' : '#3b82f6'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('statusForm').submit();
            }
        });
    }

    // 2. SUCCESS TOAST
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            background: '#1e293b',
            color: '#fff'
        });
        Toast.fire({ icon: 'success', title: 'User status updated successfully' });
        
        if (window.history.replaceState) {
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?id=<?php echo $target_id; ?>";
            window.history.replaceState({ path: newUrl }, '', newUrl);
        }
    <?php endif; ?>

    // 3. CURRENCY CONVERTER
    const CURRENCY_SYMBOL = "<?php echo $currency; ?>";
    const INR_RATE = 87; 
    let isRupee = false;
    
    document.querySelectorAll('.price-tag').forEach(el => {
        el.addEventListener('click', function() {
            isRupee = !isRupee; 
            updateAllPrices();
        });
    });

    function updateAllPrices() {
        document.querySelectorAll('.price-tag').forEach(el => {
            let usdVal = parseFloat(el.getAttribute('data-usd'));
            if(isRupee) {
                el.innerText = "₹" + (usdVal * INR_RATE).toLocaleString('en-IN', { maximumFractionDigits: 0 });
            } else {
                el.innerText = CURRENCY_SYMBOL + usdVal.toLocaleString('en-US', {minimumFractionDigits: 2});
            }
        });
    }
</script>

</body>
</html>