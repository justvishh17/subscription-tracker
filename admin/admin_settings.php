<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

// 2. AUTO-FIX: Ensure Settings Row Exists
$check_settings = $conn->query("SELECT * FROM site_settings WHERE id=1");
if ($check_settings->num_rows == 0) {
    $conn->query("INSERT INTO site_settings (id, site_name, currency, allow_signups, maintenance_mode, announcement_active, announcement_text) 
                  VALUES (1, 'SubTrack', '$', 1, 0, 0, '')");
}

$msg = ""; 

// 3. HANDLE UPDATES
if (isset($_POST['update_settings'])) {
    $site_name = trim($_POST['site_name']);
    $currency = trim($_POST['currency']);
    $announce_text = trim($_POST['announcement_text']);
    
    // VALIDATION
    if (empty($site_name) || empty($currency)) {
        $msg = "cannot_empty";
    } else {
        $allow_signups = isset($_POST['allow_signups']) ? 1 : 0;
        $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
        $announce_active = isset($_POST['announcement_active']) ? 1 : 0;
        
        $site_name = $conn->real_escape_string($site_name);
        $currency = $conn->real_escape_string($currency);
        $announce_text = $conn->real_escape_string($announce_text);

        $sql = "UPDATE site_settings SET 
                site_name='$site_name', 
                currency='$currency', 
                allow_signups='$allow_signups', 
                maintenance_mode='$maintenance',
                announcement_active='$announce_active',
                announcement_text='$announce_text' 
                WHERE id=1";

        if ($conn->query($sql)) {
            header("Location: admin_settings.php?msg=saved");
            exit();
        }
    }
}

// 4. FETCH CURRENT SETTINGS
$settings = $conn->query("SELECT * FROM site_settings WHERE id=1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Platform Settings - SubTrack</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚙️</text></svg>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --bg-dark: #0f172a; 
            --bg-card: #1e293b; 
            --input-bg: #334155; 
            --text-main: #ffffff; 
            --text-bright: #e2e8f0; /* Very bright grey for helper text */
            --border-color: #475569; 
            --accent: #3b82f6; 
        }

        body { 
            background-color: var(--bg-dark); 
            font-family: 'Segoe UI', sans-serif; 
            color: var(--text-main); 
        }

        /* --- VISIBILITY FIXES --- */
        /* Overwriting Bootstrap's muted text to be BRIGHT */
        .text-muted, .form-text, .small {
            color: #cbd5e1 !important; /* Bright Silver/White */
            opacity: 1 !important;
            font-weight: 500;
        }

        /* Labels above inputs */
        .form-label { 
            font-weight: 600; 
            color: #94a3b8; /* Slightly darker than main text, but still very visible */
            font-size: 0.95rem; 
            margin-bottom: 8px; 
        }

        /* Inputs */
        .form-control, .form-select { 
            background-color: var(--input-bg) !important; 
            border: 1px solid var(--border-color); 
            color: #ffffff !important; 
            padding: 12px; 
            border-radius: 8px; 
            font-size: 1rem;
        }
        .form-control:focus, .form-select:focus { 
            background-color: #475569 !important; 
            border-color: var(--accent); 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); 
        }

        /* --- FIXED SIDEBAR --- */
        .sidebar { 
            height: 100vh; width: 260px; position: fixed; top: 0; left: 0; 
            background: #111827; border-right: 1px solid var(--border-color); 
            padding: 25px 0; z-index: 1000; display: flex; flex-direction: column; 
        }
        .sidebar-brand { font-size: 1.3rem; font-weight: 700; text-align: center; margin-bottom: 30px; color: white; letter-spacing: 0.5px; text-transform: uppercase; }
        .nav-links { flex-grow: 1; display: flex; flex-direction: column; }
        .sidebar a { padding: 16px 25px; text-decoration: none; font-size: 1rem; color: #9ca3af; display: flex; align-items: center; transition: 0.3s; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.05); color: #fff; border-left-color: var(--accent); }
        .sidebar a i { width: 25px; text-align: center; margin-right: 10px; }
        .logout-btn { margin-top: auto; color: #ef4444 !important; border-top: 1px solid var(--border-color); }
        .logout-btn:hover { background: rgba(239, 68, 68, 0.1) !important; color: #f87171 !important; border-left-color: #ef4444 !important; }

        /* --- CONTENT --- */
        .main-content { margin-left: 260px; padding: 40px; }
        .card-dark { 
            background: var(--bg-card); 
            border-radius: 16px; 
            padding: 30px; 
            border: 1px solid var(--border-color); 
            margin-bottom: 25px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .section-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .section-title i { margin-right: 12px; color: var(--accent); background: rgba(59, 130, 246, 0.15); padding: 10px; border-radius: 8px; }

        /* Toggle Rows */
        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(255,255,255,0.03); padding: 15px 20px;
            border-radius: 10px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.05);
        }
        
        /* Switch Styling */
        .form-switch .form-check-input {
            width: 3.5em; height: 1.8em; 
            background-color: #64748b; border-color: #64748b;
            cursor: pointer; transition: 0.2s;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
        }
        .form-switch .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <div class="nav-links">
        <a href="admin.php"><i class="fas fa-users-cog"></i> User Management</a>
        <a href="admin_analytics.php"><i class="fas fa-chart-line"></i> Global Analytics</a>
        <a href="admin_settings.php" class="active"><i class="fas fa-sliders-h"></i> Platform Settings</a>    
    </div>
    <a href="index.php?logout=true" class="logout-btn"><i class="fas fa-power-off"></i> Logout</a>
</div>

<div class="main-content">
    <div class="mb-5">
        <h3 class="fw-bold text-white">Platform Settings</h3>
        <p class="text-muted">Configure global application preferences.</p>
    </div>

    <form method="POST">
        <div class="row">
            
            <div class="col-lg-7">
                <div class="card-dark">
                    <div class="section-title"><i class="fas fa-globe"></i> General Information</div>
                    
                    <div class="mb-4">
                        <label class="form-label">Application Name <span class="text-danger">*</span></label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'SubTrack'); ?>" required>
                        <div class="form-text">This name appears on the dashboard and emails.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Currency Symbol <span class="text-danger">*</span></label>
                        <select name="currency" class="form-select">
                            <option value="$" <?php if(($settings['currency'] ?? '$') == '$') echo 'selected'; ?>>$ (USD)</option>
                            <option value="₹" <?php if(($settings['currency'] ?? '$') == '₹') echo 'selected'; ?>>₹ (INR)</option>
                            <option value="€" <?php if(($settings['currency'] ?? '$') == '€') echo 'selected'; ?>>€ (EUR)</option>
                            <option value="£" <?php if(($settings['currency'] ?? '$') == '£') echo 'selected'; ?>>£ (GBP)</option>
                        </select>
                    </div>
                </div>

                <div class="card-dark">
                    <div class="section-title"><i class="fas fa-bullhorn"></i> Global Announcement</div>
                    
                    <div class="toggle-row">
                        <div>
                            <div class="fw-bold text-white">Activate Banner</div>
                            <small class="text-muted">Show alert on user dashboards</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="announcement_active" <?php if($settings['announcement_active']) echo 'checked'; ?>>
                        </div>
                    </div>

                    <label class="form-label mt-2">Message Content</label>
                    <textarea name="announcement_text" class="form-control" rows="3" placeholder="Enter broadcast message..."><?php echo htmlspecialchars($settings['announcement_text'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card-dark h-100 d-flex flex-column">
                    <div class="section-title"><i class="fas fa-lock"></i> Access Control</div>
                    
                    <div class="toggle-row">
                        <div>
                            <div class="fw-bold text-white">Allow Registrations</div>
                            <small class="text-muted">New users can sign up</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="allow_signups" <?php if($settings['allow_signups']) echo 'checked'; ?>>
                        </div>
                    </div>

                    <div class="toggle-row" style="border-left: 4px solid #ef4444;">
                        <div>
                            <div class="fw-bold text-white">Maintenance Mode</div>
                            <small class="text-muted">Block user access (Admins only)</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input bg-danger border-danger" type="checkbox" name="maintenance_mode" <?php if($settings['maintenance_mode']) echo 'checked'; ?>>
                        </div>
                    </div>

                    <div class="mt-auto pt-4">
                        <button type="submit" name="update_settings" class="btn btn-primary w-100 fw-bold py-3 shadow-lg fs-5">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Saved!',
            text: 'Configuration updated successfully.',
            background: '#1e293b',
            color: '#fff',
            timer: 2000,
            showConfirmButton: false
        });
        window.history.replaceState({}, '', window.location.pathname);
    <?php elseif($msg == 'cannot_empty'): ?>
        Swal.fire({
            icon: 'error',
            title: 'Oops!',
            text: 'Application Name and Currency cannot be empty.',
            background: '#1e293b',
            color: '#fff'
        });
    <?php endif; ?>
</script>

</body>
</html>