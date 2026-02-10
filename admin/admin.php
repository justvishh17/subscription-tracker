<?php
session_start();
include 'db.php';

// Session timeout
$timeout = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login.php?msg=session_expired"); exit();
}
$_SESSION['last_activity'] = time();

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

$msg = ""; $msg_type = "";

// FETCH SETTINGS
$settings_query = $conn->query("SELECT * FROM site_settings WHERE id=1");
$settings = ($settings_query && $settings_query->num_rows > 0) ? $settings_query->fetch_assoc() : ['allow_signups' => 1, 'maintenance_mode' => 0];
$currency = isset($settings['currency']) ? $settings['currency'] : '$';

// --- 1. AJAX SORT HANDLER (RETURNS ONLY TABLE ROWS) ---
if (isset($_GET['ajax_sort'])) {
    $sort_option = $_GET['ajax_sort'];
    $order_sql = "ORDER BY u.id DESC"; // Default

    switch ($sort_option) {
        case 'spend_high': $order_sql = "ORDER BY monthly_spend DESC"; break;
        case 'spend_low':  $order_sql = "ORDER BY monthly_spend ASC"; break;
        case 'alpha_asc':  $order_sql = "ORDER BY u.username ASC"; break;
        case 'alpha_desc': $order_sql = "ORDER BY u.username DESC"; break;
        case 'oldest':     $order_sql = "ORDER BY u.created_at ASC"; break;
        default:           $order_sql = "ORDER BY u.created_at DESC"; break;
    }

    $users = $conn->query("SELECT u.*,
        COALESCE(SUM(CASE 
            WHEN s.billing_period = 'Yearly' THEN s.price / 12 
            ELSE s.price 
        END), 0) as monthly_spend
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id
        GROUP BY u.id
        $order_sql");

    // Output ONLY the table rows
    while($row = $users->fetch_assoc()): 
        $pic = !empty($row['profile_pic']) && file_exists('uploads/'.$row['profile_pic']) ? "uploads/".$row['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($row['username'])."&background=random";
        $is_banned = isset($row['status']) && $row['status'] == 'banned';
        ?>
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <img src="<?php echo $pic; ?>" class="user-avatar-sm me-3" alt="Profile">
                    <div>
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($row['username']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($row['email']); ?></div>
                    </div>
                </div>
            </td>
            
            <td>
                <span class="text-white fw-bold price-display" data-usd="<?php echo $row['monthly_spend']; ?>" title="Click to convert">
                    <?php echo $currency . number_format($row['monthly_spend'], 2); ?>
                </span>
                <small class="text-muted">/mo</small>
            </td>

            <td><span class="badge rounded-pill <?php echo ($row['role'] == 'admin') ? 'badge-role-admin' : 'badge-role-user'; ?>"><?php echo ucfirst($row['role']); ?></span></td>
            
            <td>
                <?php if($is_banned): ?>
                    <span class="badge badge-banned">Banned</span>
                <?php else: ?>
                    <span class="badge badge-active">Active</span>
                <?php endif; ?>
            </td>
            
            <td class="text-muted"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
            
            <td class="text-end">
                <a href="admin_user_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="View Details"><i class="fas fa-eye"></i></a>
                
                <button class="btn btn-sm btn-outline-light me-1" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo $row['username']; ?>', '<?php echo $row['role']; ?>')"><i class="fas fa-edit"></i></button>
                
                <?php if($row['id'] != $_SESSION['user_id']): ?>
                    <?php if($is_banned): ?>
                        <a href="#" onclick="confirmAction(event, 'admin.php?activate_user=<?php echo $row['id']; ?>', 'unban')" class="btn btn-sm btn-outline-success" title="Unban User"><i class="fas fa-check"></i></a>
                    <?php else: ?>
                        <a href="#" onclick="confirmAction(event, 'admin.php?ban_user=<?php echo $row['id']; ?>', 'ban')" class="btn btn-sm btn-outline-warning" title="Ban User"><i class="fas fa-ban"></i></a>
                    <?php endif; ?>
                    
                    <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmAction(event, 'admin.php?delete_user=<?php echo $row['id']; ?>', 'delete')"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile;
    exit(); // Stop here so we don't reload the whole page
}

// --- NORMAL PAGE LOAD LOGIC ---

// BAN/UNBAN/DELETE Handlers
if (isset($_GET['ban_user'])) {
    $uid = (int)$_GET['ban_user'];
    $conn->query("UPDATE users SET status='banned' WHERE id=$uid");
    header("Location: admin.php?msg=banned"); exit();
}
if (isset($_GET['activate_user'])) {
    $uid = (int)$_GET['activate_user'];
    $conn->query("UPDATE users SET status='active' WHERE id=$uid");
    header("Location: admin.php?msg=activated"); exit();
}
if (isset($_GET['delete_user'])) {
    $uid = (int)$_GET['delete_user'];
    $conn->query("DELETE FROM users WHERE id=$uid");
    header("Location: admin.php?msg=deleted"); exit();
}
if (isset($_POST['send_broadcast'])) {
    $msg = $conn->real_escape_string($_POST['broadcast_message']);
    $conn->query("UPDATE site_settings SET announcement_text='$msg', announcement_active=1 WHERE id=1");
    header("Location: admin.php?msg=broadcast_sent"); exit();
}
if (isset($_POST['update_role'])) {
    $target_uid = $_POST['user_id'];
    $new_role = $_POST['role'];
    $conn->query("UPDATE users SET role='$new_role' WHERE id=$target_uid");
    $msg = "User role updated successfully!"; $msg_type = "success";
}

// STATS FETCHING
function getCount($conn, $query) {
    $result = $conn->query($query);
    if ($result) { $row = $result->fetch_row(); return $row[0] ?? 0; }
    return 0;
}
$user_count = getCount($conn, "SELECT COUNT(*) FROM users WHERE role='user'");
$admin_count = getCount($conn, "SELECT COUNT(*) FROM users WHERE role='admin'");
$total_volume = getCount($conn, "SELECT SUM(price) FROM subscriptions");
$banned_count = getCount($conn, "SELECT COUNT(*) FROM users WHERE status='banned'");

// CHART DATA
$top_apps_query = $conn->query("SELECT service_name, COUNT(*) as count FROM subscriptions GROUP BY service_name ORDER BY count DESC LIMIT 5");
$top_apps_labels = []; $top_apps_data = [];
if ($top_apps_query) {
    while($app = $top_apps_query->fetch_assoc()) {
        $top_apps_labels[] = $app['service_name'];
        $top_apps_data[] = $app['count'];
    }
}

// INITIAL TABLE LOAD (Default: Newest)
$users = $conn->query("SELECT u.*,
        COALESCE(SUM(CASE 
            WHEN s.billing_period = 'Yearly' THEN s.price / 12 
            ELSE s.price 
        END), 0) as monthly_spend
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚙️</text></svg>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --bg-dark: #0f172a; 
            --bg-card: #1e293b; 
            --text-main: #ffffff; 
            --text-muted: #cbd5e1; 
            --border-color: #334155; 
            --accent: #3b82f6; 
        }

        body { 
            background-color: var(--bg-dark); 
            font-family: 'Segoe UI', sans-serif; 
            color: var(--text-main); 
        }

        .text-muted { color: var(--text-muted) !important; }
        
        /* SIDEBAR */
        .sidebar { height: 100vh; width: 260px; position: fixed; top: 0; left: 0; background: #111827; border-right: 1px solid var(--border-color); padding-top: 25px; z-index: 1000; }
        .sidebar .brand { font-size: 1.3rem; font-weight: 700; text-align: center; margin-bottom: 40px; color: white; letter-spacing: 0.5px; text-transform: uppercase; }
        .sidebar a { padding: 16px 25px; text-decoration: none; font-size: 1rem; color: #9ca3af; display: flex; align-items: center; transition: 0.3s; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.05); color: #fff; border-left-color: var(--accent); }
        .sidebar a i { width: 25px; text-align: center; margin-right: 10px; }

        .main-content { margin-left: 260px; padding: 40px; }
        .card-dark { background: var(--bg-card); border-radius: 12px; padding: 25px; border: 1px solid var(--border-color); transition: transform 0.2s; }
        .icon-box { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 15px; }
        
        .table { --bs-table-bg: transparent !important; --bs-table-color: var(--text-main); border-color: var(--border-color); margin-bottom: 0; }
        .table thead th { background-color: #0f172a !important; color: #e2e8f0; border-bottom: 1px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding-bottom: 15px; }
        .table tbody td { background-color: transparent !important; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-main); padding: 15px 10px; }
        .table-hover tbody tr:hover td { background-color: rgba(59, 130, 246, 0.1) !important; }

        .form-control, .form-select { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: white; }
        .form-control:focus, .form-select:focus { background-color: var(--bg-dark); border-color: var(--accent); color: white; box-shadow: none; }
        .modal-content { background-color: var(--bg-card); border: 1px solid var(--border-color); color: white; }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        .user-avatar-sm { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        
        .badge-role-user { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-role-admin { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-banned { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .price-display { cursor: pointer; } 
        .price-display:hover { color: var(--accent) !important; }

        /* --- COMPACT MODERN SEARCH BAR --- */
        .search-wrapper { position: relative; }
        .modern-search {
            width: 150px; /* Smaller default width */
            padding: 8px 15px;
            padding-left: 35px;
            border-radius: 50px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            color: white;
            font-size: 0.9rem;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modern-search:focus, .modern-search:hover {
            width: 220px; /* Smaller expanded width */
            background: rgba(255,255,255,0.1);
            border-color: var(--accent);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8rem;
            pointer-events: none;
        }

        .sort-select {
            border-radius: 50px;
            padding: 8px 20px;
            font-size: 0.9rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            color: white;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <a href="admin.php" class="active"><i class="fas fa-users-cog"></i> User Management</a>
    <a href="admin_analytics.php"><i class="fas fa-chart-line"></i> Global Analytics</a>
    <a href="admin_settings.php"><i class="fas fa-sliders-h"></i> Platform Settings</a>    
    <div style="position: absolute; bottom: 30px; width: 100%;">
        <a href="index.php?logout=true" style="color: #ef4444;"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div><h3 class="fw-bold text-white">Admin Dashboard</h3><p class="text-muted">Overview of platform performance.</p></div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3"><div class="card-dark"><div class="d-flex justify-content-between"><div><h6 class="text-muted">Users</h6><h3><?php echo $user_count; ?></h3></div><div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="fas fa-users"></i></div></div></div></div>
        <div class="col-md-3"><div class="card-dark"><div class="d-flex justify-content-between"><div><h6 class="text-muted">Admins</h6><h3><?php echo $admin_count; ?></h3></div><div class="icon-box bg-warning bg-opacity-10 text-warning"><i class="fas fa-user-shield"></i></div></div></div></div>
        <div class="col-md-3"><div class="card-dark"><div class="d-flex justify-content-between"><div><h6 class="text-muted">Banned</h6><h3><?php echo $banned_count; ?></h3></div><div class="icon-box bg-danger bg-opacity-10 text-danger"><i class="fas fa-ban"></i></div></div></div></div>
        <div class="col-md-3"><div class="card-dark"><div class="d-flex justify-content-between"><div><h6 class="text-muted">Volume</h6><h3><span class="price-display" data-usd="<?php echo $total_volume; ?>"><?php echo $currency . number_format($total_volume, 2); ?></span></h3></div><div class="icon-box bg-success bg-opacity-10 text-success"><i class="fas fa-dollar-sign"></i></div></div></div></div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card-dark p-4">
                <h5 class="mb-4">Most Popular Subscriptions</h5>
                <div style="height: 250px;"><canvas id="topAppsChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dark p-4 h-100 text-center d-flex flex-column justify-content-center">
                <i class="fas fa-bullhorn fa-3x text-warning mb-3"></i>
                <h5 class="mb-2">Urgent Broadcast</h5>
                <p class="text-muted small mb-4">Send a popup alert to all active users immediately.</p>
                <button id="broadcastModalBtn" class="btn btn-warning w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#broadcastModal">Send Alert</button>
            </div>
        </div>
    </div>

    <div class="card-dark">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <h5 class="fw-bold text-white mb-0">Registered Accounts</h5>
            
            <div class="d-flex align-items-center gap-3">
                <select id="sortSelect" class="form-select sort-select shadow-none">
                    <option value="newest">Newest Joined</option>
                    <option value="oldest">Oldest Joined</option>
                    <option value="spend_high">Highest Spenders</option>
                    <option value="spend_low">Lowest Spenders</option>
                    <option value="alpha_asc">A-Z (Username)</option>
                    <option value="alpha_desc">Z-A (Username)</option>
                </select>

                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="userSearch" class="modern-search" placeholder="Search...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead>
                    <tr><th>User</th><th>Monthly Spend</th><th>Role</th><th>Status</th><th>Joined</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody id="userTableBody">
                    <?php while($row = $users->fetch_assoc()): 
                        $pic = !empty($row['profile_pic']) && file_exists('uploads/'.$row['profile_pic']) ? "uploads/".$row['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($row['username'])."&background=random";
                        $is_banned = isset($row['status']) && $row['status'] == 'banned';
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="<?php echo $pic; ?>" class="user-avatar-sm me-3">
                                <div><div class="fw-bold text-white"><?php echo htmlspecialchars($row['username']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($row['email']); ?></div></div>
                            </div>
                        </td>
                        <td><span class="text-white fw-bold price-display" data-usd="<?php echo $row['monthly_spend']; ?>"><?php echo $currency . number_format($row['monthly_spend'], 2); ?></span> <small class="text-muted">/mo</small></td>
                        <td><span class="badge rounded-pill <?php echo ($row['role'] == 'admin') ? 'badge-role-admin' : 'badge-role-user'; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                        <td><?php echo $is_banned ? '<span class="badge badge-banned">Banned</span>' : '<span class="badge badge-active">Active</span>'; ?></td>
                        <td class="text-muted"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                        <td class="text-end">
                            <a href="admin_user_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info me-1"><i class="fas fa-eye"></i></a>
                            <button class="btn btn-sm btn-outline-light me-1" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo $row['username']; ?>', '<?php echo $row['role']; ?>')"><i class="fas fa-edit"></i></button>
                            <?php if($row['id'] != $_SESSION['user_id']): ?>
                                <?php if($is_banned): ?>
                                    <a href="#" onclick="confirmAction(event, 'admin.php?activate_user=<?php echo $row['id']; ?>', 'unban')" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i></a>
                                <?php else: ?>
                                    <a href="#" onclick="confirmAction(event, 'admin.php?ban_user=<?php echo $row['id']; ?>', 'ban')" class="btn btn-sm btn-outline-warning"><i class="fas fa-ban"></i></a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmAction(event, 'admin.php?delete_user=<?php echo $row['id']; ?>', 'delete')"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editRoleModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit User Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="user_id" id="modalUserId"><p class="text-muted">User: <strong id="modalUserName" class="text-white"></strong></p><div class="form-floating"><select name="role" class="form-select" id="modalRoleSelect"><option value="user">User</option><option value="admin">Admin</option></select><label class="text-dark">Select New Role</label></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_role" class="btn btn-primary">Save Changes</button></div></form></div></div></div>
<div class="modal fade" id="broadcastModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content card-dark"><div class="modal-header border-secondary"><h5 class="modal-title">📢 Send Urgent Broadcast</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><p class="text-muted small">This message will appear on the dashboard of all users.</p><textarea name="broadcast_message" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="e.g. Maintenance scheduled for tonight..." required></textarea></div><div class="modal-footer border-secondary"><button type="submit" name="send_broadcast" class="btn btn-warning fw-bold">Send Alert</button></div></form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. AJAX SORT LOGIC (NO REFRESH)
    document.getElementById('sortSelect').addEventListener('change', function() {
        const sortBy = this.value;
        const tbody = document.getElementById('userTableBody');
        
        // Add loading opacity
        tbody.style.opacity = '0.5';

        fetch(`admin.php?ajax_sort=${sortBy}`)
            .then(response => response.text())
            .then(html => {
                tbody.innerHTML = html;
                tbody.style.opacity = '1';
                // Note: price-display listeners are handled via delegation below, so no need to re-attach!
            })
            .catch(err => {
                console.error('Sort failed', err);
                tbody.style.opacity = '1';
            });
    });

    // 2. SEARCH FUNCTION (Client Side)
    document.getElementById('userSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#userTableBody tr');
        rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none'; });
    });

    // 3. EVENT DELEGATION FOR PRICE DISPLAY (Fixed for Volume Card + Table)
    const CURRENCY_SYMBOL = "<?php echo $currency; ?>";
    const INR_RATE = 87;
    let isRupee = false;

    // CHANGE: Listen to the whole document, not just the table body
    document.addEventListener('click', function(e) {
        // Use .closest() to handle clicks on the span or the h3 wrapper
        if (e.target.classList.contains('price-display') || e.target.closest('.price-display')) {
            isRupee = !isRupee;
            updateAllPrices();
        }
    });

    function updateAllPrices() {
        // This selects ALL elements with class 'price-display' (Volume Card AND Table Rows)
        document.querySelectorAll('.price-display').forEach(el => {
            let usdVal = parseFloat(el.getAttribute('data-usd'));
            if(isRupee) {
                // Convert to INR
                el.innerText = "₹" + (usdVal * INR_RATE).toLocaleString('en-IN', { maximumFractionDigits: 0 });
            } else {
                // Revert to USD
                el.innerText = CURRENCY_SYMBOL + usdVal.toLocaleString('en-US', {minimumFractionDigits: 2});
            }
        });
    }
    function updateAllPrices() {
        document.querySelectorAll('.price-display').forEach(el => {
            let usdVal = parseFloat(el.getAttribute('data-usd'));
            if(isRupee) {
                el.innerText = "₹" + (usdVal * INR_RATE).toLocaleString('en-IN', { maximumFractionDigits: 0 });
            } else {
                el.innerText = CURRENCY_SYMBOL + usdVal.toLocaleString('en-US', {minimumFractionDigits: 2});
            }
        });
    }

    // 4. CONFIRMATION POPUP
    function confirmAction(event, url, actionType) {
        event.preventDefault();
        let title, text, btnColor, btnText;
        if (actionType === 'ban') { title = 'Ban User?'; text = "They will lose access!"; btnColor = '#f59e0b'; btnText = 'Yes, Ban'; }
        else if (actionType === 'unban') { title = 'Unban User?'; text = "Access will be restored."; btnColor = '#10b981'; btnText = 'Yes, Unban'; }
        else if (actionType === 'delete') { title = 'Delete User?'; text = "Permanent action!"; btnColor = '#ef4444'; btnText = 'Yes, Delete'; }

        Swal.fire({
            title: title, text: text, icon: 'warning', background: '#1e293b', color: '#fff',
            showCancelButton: true, confirmButtonColor: btnColor, cancelButtonColor: '#64748b', confirmButtonText: btnText
        }).then((res) => { if (res.isConfirmed) window.location.href = url; });
    }

    // 5. CHART & MODAL LOGIC
    function openEditModal(id, name, role) {
        document.getElementById('modalUserId').value = id; 
        document.getElementById('modalUserName').innerText = name; 
        document.getElementById('modalRoleSelect').value = role;
        new bootstrap.Modal(document.getElementById('editRoleModal')).show();
    }
    new Chart(document.getElementById('topAppsChart'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($top_apps_labels); ?>, datasets: [{ label: 'Subscribers', data: <?php echo json_encode($top_apps_data); ?>, backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'], borderRadius: 5 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } }, x: { grid: { display: false }, ticks: { color: '#94a3b8' } } } }
    });

    <?php if(isset($_GET['msg'])): ?>
        Swal.fire({ icon: 'success', title: 'Success', background: '#1e293b', color: '#fff', timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
    <?php endif; ?>
</script>

</body>
</html>