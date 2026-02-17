<?php
// Initialize PHP session
session_start();
include 'db.php';

// --- 1. SECURITY CHECK (MOVED TO TOP) ---
// We must check this FIRST so $user_id is available for the logic below
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
$user_id = $_SESSION['user_id']; // <--- Now this variable exists!
// --- AJAX HANDLER (Silent Archive) ---
if (isset($_POST['action']) && $_POST['action'] == 'archive') {
    $sub_id = (int)$_POST['id'];
    // Archive and set auto-delete for 3 days later
    $stmt = $conn->prepare("UPDATE subscriptions SET status = 'archived', archived_at = NOW(), auto_delete_at = DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sub_id, $user_id);
    if ($stmt->execute()) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error']); }
    exit(); 
}

// --- 2. HANDLE "MARK AS PAID" ---
if (isset($_GET['pay_now'])) {
    $id = (int)$_GET['pay_now'];
    
    // Get current details
    $qry = $conn->query("SELECT billing_period, next_due_date FROM subscriptions WHERE id=$id AND user_id=$user_id");
    
    if($qry && $row = $qry->fetch_assoc()) { // Added check to prevent crash
        $current_due = $row['next_due_date'];
        $cycle = $row['billing_period'];
        
        // Calculate New Date
        if ($cycle == 'Yearly') {
            $new_date = date('Y-m-d', strtotime('+1 year', strtotime($current_due)));
        } else {
            $new_date = date('Y-m-d', strtotime('+1 month', strtotime($current_due)));
        }

        // Update Database
        $conn->query("UPDATE subscriptions SET next_due_date='$new_date', snooze_until=NULL, last_used=NOW() WHERE id=$id AND user_id=$user_id");
        header("Location: index.php?msg=paid_updated");
        exit();
    }
}

// --- 3. HANDLE "SNOOZE" ---
if (isset($_GET['snooze'])) {
    $id = (int)$_GET['snooze'];
    $conn->query("UPDATE subscriptions SET snooze_until = DATE_ADD(NOW(), INTERVAL 48 HOUR) WHERE id=$id AND user_id=$user_id");
    header("Location: index.php?msg=snoozed");
    exit();
}
// Session timeout: 30 minutes of inactivity
$timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=session_expired");
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity

// --- 1. FETCH SITE SETTINGS ---
$settings_query = $conn->query("SELECT * FROM site_settings WHERE id = 1");
$site_settings = ($settings_query) ? $settings_query->fetch_assoc() : [];

// --- 2. CHECK MAINTENANCE MODE ---
if (!empty($site_settings['maintenance_mode']) && $site_settings['maintenance_mode'] == 1) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: login.php?msg=maintenance");
        exit();
    }
}
// Security checks
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php?msg=loggedout"); exit(); }
$user_id = $_SESSION['user_id'];
// --- AUTO-CLEANER: Delete items that have been archived for more than 3 days ---
// This runs silently every time the dashboard loads
$clean_sql = "DELETE FROM subscriptions WHERE status = 'archived' AND auto_delete_at IS NOT NULL AND auto_delete_at < NOW() AND user_id = $user_id";
$conn->query($clean_sql);

// --- 3. HANDLE BUDGET UPDATE ---
if (isset($_POST['update_budget'])) {
    $new_limit = $_POST['budget_amount'];
    $conn->query("UPDATE users SET budget_limit = '$new_limit' WHERE id = $user_id");
    header("Location: index.php?msg=budget_updated");
    exit();
}

// --- 4. HANDLE ARCHIVING (SOFT DELETE) ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // --- 5. HANDLE "I USE THIS" CHECK-IN ---
if (isset($_GET['confirm_usage'])) {
    $id = (int)$_GET['confirm_usage'];
    $stmt = $conn->prepare("UPDATE subscriptions SET last_used = NOW() WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    header("Location: index.php?msg=usage_confirmed");
    exit();
}
    // OLD CODE:
    // $stmt = $conn->prepare("UPDATE subscriptions SET status='archived', archived_at=NOW() WHERE id=? AND user_id=?");
    
    // NEW CODE (With 3-Day Timer):
    // This sets the status to 'archived' AND sets a delete timer for 3 days from now
    $stmt = $conn->prepare("UPDATE subscriptions SET status='archived', archived_at=NOW(), auto_delete_at=DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE id=? AND user_id=?");
    
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php?msg=archived_3days");
    exit();
}

// --- 5. FETCH USER DATA & BUDGET ---
$user_sql = "SELECT username, profile_pic, budget_limit FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_query = $stmt->get_result();
$user_data = ($user_query->num_rows > 0) ? $user_query->fetch_assoc() : ['username' => 'User', 'profile_pic' => 'default.png', 'budget_limit' => 500.00];
$stmt->close();

$budget_limit = $user_data['budget_limit'] ?? 500.00;
$user_pic = !empty($user_data['profile_pic']) && $user_data['profile_pic'] != 'default.png' ? "uploads/" . $user_data['profile_pic'] : "https://ui-avatars.com/api/?name=" . $user_data['username'] . "&background=random";
$currency = $site_settings['currency_symbol'] ?? '$';

// --- 6. FETCH SUBSCRIPTIONS ---
// Note: We fetch ALL for the user so JS can filter them instantly without reload
$sql = "SELECT * FROM subscriptions WHERE user_id = $user_id AND status = 'active' ORDER BY next_due_date ASC";
$result = $conn->query($sql);

$subs_array = []; 
// --- SMART SAVINGS ENGINE ---
$savings_tips = []; // We will store recommendations here

// A mini "database" of cheaper alternatives
$knowledge_base = [
    'netflix' => ['alt' => 'Amazon Prime', 'price' => 149, 'msg' => 'Prime includes free shipping too!'],
    'spotify' => ['alt' => 'YouTube Music', 'price' => 99, 'msg' => 'Includes ad-free YouTube videos.'],
    'adobe' =>   ['alt' => 'Canva Pro', 'price' => 499, 'msg' => 'Great for quick designs.'],
    'notion' =>  ['alt' => 'Obsidian', 'price' => 0, 'msg' => 'Obsidian is free and works offline.'],
    'chatgpt' => ['alt' => 'Gemini', 'price' => 0, 'msg' => 'Gemini offers great free AI features.']
];
$calendar_events = []; 
$total_monthly_cost = 0; 
$cat_totals = ['Entertainment' => 0, 'Utilities' => 0, 'Work/Tools' => 0, 'Personal' => 0, 'Other' => 0]; 

// Variables for Projected Savings Logic
$most_expensive_sub = null;
$highest_price = 0;

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $subs_array[] = $row;
        // Normalize name to lowercase for matching
    $service_slug = strtolower($row['service_name']);

    // Check if we have a tip for this service
    foreach ($knowledge_base as $key => $data) {
        // If user has this service AND pays more than the alternative
        if (strpos($service_slug, $key) !== false && $row['price'] > $data['price']) {
            $save_amount = $row['price'] - $data['price'];
            $savings_tips[] = [
                'current' => $row['service_name'],
                'suggest' => $data['alt'],
                'save' => $save_amount,
                'msg' => $data['msg']
            ];
        }
    }
        $monthly_equivalent = ($row['billing_period'] == 'Yearly') ? $row['price'] / 12 : $row['price'];
        
        // --- LOGIC UPDATE: EXCLUDE OVERDUE FROM TOTAL ---
        // 1. Calculate days remaining
        $due_timestamp = strtotime($row['next_due_date']);
        $days_until_due = ceil(($due_timestamp - time()) / (60 * 60 * 24));

        // 2. Only add to total if NOT overdue (days >= 0)
        // If days < 0, it means it's unpaid/overdue, so we don't count it in "Monthly Spend" yet.
        if ($days_until_due >= 0) {
            $total_monthly_cost += $monthly_equivalent;
        }
        // -----------------------------------------------
        
        // --- LOGIC: Find Most Expensive Sub ---
        if ($monthly_equivalent > $highest_price) {
            $highest_price = $monthly_equivalent;
            $most_expensive_sub = $row;
        }

        $cat = !empty($row['category']) ? $row['category'] : 'Other';
        $valid_cats = array_keys($cat_totals);
        if (!in_array($cat, $valid_cats)) $cat = 'Other';
        $cat_totals[$cat] = ($cat_totals[$cat] ?? 0) + $monthly_equivalent;

        $color = '#9966FF'; 
        if($cat == 'Entertainment') $color = '#ff6b6b'; 
        if($cat == 'Utilities') $color = '#4ecdc4'; 
        if($cat == 'Work/Tools') $color = '#ffe66d'; 
        if($cat == 'Personal') $color = '#1a535c'; 

        $calendar_events[] = [
            'title' => $row['service_name'], 
            'start' => $row['next_due_date'], 
            'color' => $color, 
            'extendedProps' => [
                'price' => $currency . $row['price'],
                'category' => $cat
            ]
        ];
    }
}

// --- 7. SMART GAMIFICATION LOGIC ---
$sub_count = count($subs_array);

// Define Levels
$levels = [
    ['limit' => 0,  'name' => 'Newbie',       'icon' => 'fa-seedling',   'color' => 'secondary'],
    ['limit' => 3,  'name' => 'Explorer',     'icon' => 'fa-hiking',     'color' => 'info'],
    ['limit' => 8,  'name' => 'Pro Tracker',  'icon' => 'fa-chart-line', 'color' => 'primary'],
    ['limit' => 15, 'name' => 'Master',       'icon' => 'fa-star',       'color' => 'success'],
    ['limit' => 25, 'name' => 'Sub King',     'icon' => 'fa-crown',      'color' => 'warning']
];

// Calculate Rank & Progress
$current_badge = $levels[0];
$next_badge = null;
$progress_percent = 0;
$progress_msg = "Start tracking!";

foreach ($levels as $index => $level) {
    if ($sub_count >= $level['limit']) {
        $current_badge = $level;
        if (isset($levels[$index + 1])) {
            $next_badge = $levels[$index + 1];
        }
    }
}

if ($next_badge) {
    $subs_needed = $next_badge['limit'] - $sub_count;
    $range = $next_badge['limit'] - $current_badge['limit'];
    $current_progress = $sub_count - $current_badge['limit'];
    $progress_percent = ($range > 0) ? ($current_progress / $range) * 100 : 0;
    $progress_msg = "<b>$subs_needed</b> more to reach <em>{$next_badge['name']}</em>";
} else {
    $progress_percent = 100;
    $progress_msg = "Max Level Reached! 🏆";
}

$badge = $current_badge['name'];
$badge_icon = $current_badge['icon'];
$badge_color = $current_badge['color'];

// --- BUDGET CHECK ---
$is_over_budget = ($total_monthly_cost > $budget_limit);
$spend_card_bg = $is_over_budget ? "linear-gradient(135deg, #ff6b6b 0%, #ee5253 100%)" : "linear-gradient(135deg, #667eea 0%, #764ba2 100%)";
$spend_msg = $is_over_budget ? "<i class='fas fa-exclamation-triangle'></i> Over Budget!" : "Total Monthly Spend";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User'sDashboard - SubTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📊</text></svg>">    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-body: #f8f9fa; 
            --bg-card: #ffffff; 
            --text-main: #212529; 
            --text-muted: #6c757d;
            --border-color: #eef2f5; 
            --navbar-bg: #ffffff; 
            --shadow: 0 2px 15px rgba(0,0,0,0.04);
            /* Calendar variables */
            --cal-btn-bg: #f1f3f5; --cal-btn-text: #495057; --cal-grid: #e9ecef; --cal-today: rgba(102, 126, 234, 0.1);
        }

        body.dark-mode {
            --bg-body: #0f172a; 
            --bg-card: #1e293b; 
            --text-main: #f1f5f9; 
            --text-muted: #94a3b8;
            --border-color: #334155; 
            --navbar-bg: #1e293b; 
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
            /* Dark Calendar */
            --cal-btn-bg: #334155; --cal-btn-text: #e2e8f0; --cal-grid: #334155; --cal-today: rgba(59, 130, 246, 0.15);
        }

/* 1. BODY: STOP THE ANIMATION (Fixes the popup position) */
body { 
    background-color: var(--bg-body); 
    color: var(--text-main); 
    transition: background 0.3s, color 0.3s;
    /* animation: none; <--- REMOVED from here */
}

/* 2. CONTAINER: Animate the content instead */
.container, .navbar { 
    animation: fadeInPage 0.5s ease-out forwards; 
}        .card { background-color: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); transition: 0.3s; }
        .text-muted { color: var(--text-muted) !important; }
        .navbar { background: var(--navbar-bg); box-shadow: var(--shadow); transition: 0.3s; }
        .nav-profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .dropdown-menu { background: var(--bg-card); border-color: var(--border-color); }
        .dropdown-item { color: var(--text-main); }
        .dropdown-item:hover { background: var(--border-color); }
        
        /* HOVER DROPDOWN */
        /* --- HOVER DROPDOWN (For Profile & Notifications) --- */
        .dropdown:hover .dropdown-menu {
            display: block;
            margin-top: 0;
            animation: fadeIn 0.2s;
        }
        /* Fix positioning for right-aligned menus */
        .dropdown-menu-end {
            right: 0;
            left: auto;
        }
        
        .view-btn { cursor: pointer; opacity: 0.6; transition: 0.2s; border-bottom: 3px solid transparent; padding-bottom: 5px; }
        .view-btn.active { opacity: 1; border-color: #667eea; font-weight: bold; color: #667eea; }
        
        /* Interactive Price */
        .price-cursor { cursor: pointer; text-decoration: none; }
        .price-cursor:hover { opacity: 0.8; }

        /* Calendar Styling */
        #calendar a { text-decoration: none; color: inherit; }
        .fc-button-primary { background-color: var(--cal-btn-bg) !important; color: var(--cal-btn-text) !important; border: none !important; font-weight: 600; border-radius: 8px !important; padding: 6px 16px !important; margin: 0 2px; text-transform: capitalize; }
        .fc-button-active { background-color: #667eea !important; color: white !important; }
        .fc-toolbar-title { font-size: 1.5rem !important; font-weight: 700; color: var(--text-main); }
        .fc-theme-standard td, .fc-theme-standard th { border-color: var(--cal-grid) !important; }
        .fc-day-today { background-color: var(--cal-today) !important; }
        .fc-event { border: none !important; border-radius: 6px; padding: 3px 8px; font-size: 0.85rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 4px; cursor: pointer; transition: transform 0.1s; }
        .fc-event:hover { transform: scale(1.02); }
        .fc-daygrid-day-number { color: var(--text-muted); font-weight: 500; text-decoration: none; }
        .renewal-day-marker { width: 6px; height: 6px; border-radius: 50%; background-color: #ff4757; margin: 0 auto; margin-top: 2px; }

        .theme-toggle { cursor: pointer; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--border-color); color: var(--text-main); transition: 0.3s; }
        .theme-toggle:hover { transform: rotate(15deg); }

        @keyframes fadeInPage { 0% { opacity: 0; transform: translateY(15px); } 100% { opacity: 1; transform: translateY(0); } }
        a, button, .btn, .card, .form-control, .form-select, .form-check-input { transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important; }
        .btn:hover, button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; }
        .form-control:focus, .form-select:focus { transform: scale(1.01); }

        /* --- COMPACT CALENDAR STYLING --- */
#calendar {
    max-width: 1000px; /* Prevents it from getting too wide on huge screens */
    margin: 0 auto;    /* Centers it */
    font-family: 'Segoe UI', sans-serif;
}

/* Make the Title smaller */
.fc-toolbar-title {
    font-size: 1.25rem !important;
    font-weight: 700;
}

/* Header Cells (Mon, Tue, Wed...) */
.fc-col-header-cell-cushion {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding-top: 10px !important;
    padding-bottom: 10px !important;
    color: var(--text-muted);
}

/* The Day Numbers */
.fc-daygrid-day-number {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    padding: 8px !important;
}

/* The Events (Subscription Pills) */
.fc-event {
    border-radius: 4px !important;
    font-size: 0.75rem !important; /* Smaller text */
    padding: 2px 5px !important;
}

/* Today's Date Highlight */
.fc-day-today {
    background-color: rgba(255, 193, 7, 0.1) !important; /* Subtle yellow tint */
}

/* --- DARK MODE FIXES --- */
body.dark-mode .form-control, 
body.dark-mode .form-select {
    background-color: #1e293b !important; /* Matches card color */
    border-color: #334155 !important;
    color: #e2e8f0 !important;
}

body.dark-mode .form-control::placeholder {
    color: #64748b !important;
}

body.dark-mode .input-group-text {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #94a3b8 !important;
}

/* Fix the White Buttons in the Toolbar */
body.dark-mode .btn-white {
    background-color: #334155 !important;
    color: #fff !important;
    box-shadow: none !important;
}

/* Fix the Calendar in Dark Mode */
body.dark-mode .fc-theme-standard td, 
body.dark-mode .fc-theme-standard th {
    border-color: #334155 !important;
}
body.dark-mode .fc-col-header-cell-cushion,
body.dark-mode .fc-daygrid-day-number {
    color: #cbd5e1 !important;
}

/* Fix the Dropdown Menus */
body.dark-mode .dropdown-menu {
    background-color: #1e293b !important;
    border-color: #334155 !important;
}
body.dark-mode .dropdown-item {
    color: #cbd5e1 !important;
}
body.dark-mode .dropdown-item:hover {
    background-color: #334155 !important;
    color: #fff !important;
}

/* Make the background a bit 'richer' (less flat blue) */
body.dark-mode {
    --bg-body: #0f172a; 
    --bg-card: #1e293b; 
    --text-main: #f8fafc;
    --text-muted: #94a3b8;
    --border-color: #334155;
}

/* --- MODERN EXPANDING SEARCH BAR --- */
.search-wrapper {
    position: relative;
    height: 40px;
    display: flex;
    align-items: center;
}

.search-input {
    height: 100%;
    width: 0; /* Start hidden */
    padding: 0;
    border: none;
    outline: none;
    background: transparent;
    transition: width 0.4s cubic-bezier(0.075, 0.82, 0.165, 1); /* Smooth spring animation */
    font-size: 0.95rem;
    color: var(--text-main);
    opacity: 0; /* Hide text when collapsed */
}

.search-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #fff;
    border: none;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: 0.3s;
    cursor: pointer;
    color: #64748b;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    z-index: 2; /* Keep button on top */
}

/* HOVER & FOCUS STATES (The Magic) */
.search-wrapper:hover .search-input,
.search-input:focus,
.search-input:not(:placeholder-shown) { /* Keep open if text is inside */
    width: 220px; /* Expand width */
    padding-left: 15px;
    padding-right: 45px; /* Space for the icon */
    background: #fff;
    border-radius: 25px;
    opacity: 1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.search-wrapper:hover .search-btn,
.search-input:focus + .search-btn {
    background: transparent; /* Icon blends in */
    color: #3b82f6; /* Icon turns blue */
    box-shadow: none;
    pointer-events: none; /* Let clicks pass through to input */
    position: absolute;
    right: 0;
}

/* Dark Mode Support */
body.dark-mode .search-btn {
    background: #1e293b;
    color: #cbd5e1;
}
body.dark-mode .search-input {
    color: #fff;
}
body.dark-mode .search-wrapper:hover .search-input,
body.dark-mode .search-input:focus {
    background: #1e293b;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

/* --- UNIVERSAL CURRENCY HOVER --- */
.price-display {
    cursor: pointer;
    transition: transform 0.2s ease, color 0.2s ease;
    display: inline-block; /* Ensures transform works on spans */
}
.price-display:hover {
    transform: scale(1.1); /* Slight zoom */
    color: #000000;        /* Change color to blue (or your theme color) */
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 4px;
}
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold fs-4" style="color: var(--text-main);">
            <i class="fas fa-rocket text-primary me-2"></i>
        </a>
        
        <?php if (!empty($site_settings['announcement_active']) && !empty($site_settings['announcement_text'])): ?>
        <div id="globalBanner" class="bg-warning text-dark text-center py-2 fw-bold shadow-sm rounded px-3 d-none d-md-block position-relative" style="background: #fff3cd; color: #856404; font-size: 0.9rem;">
             <i class="fas fa-bullhorn me-2"></i> <?php echo htmlspecialchars($site_settings['announcement_text']); ?>
             <button type="button" class="btn-close btn-close-dark position-absolute top-50 end-0 translate-middle-y me-2" onclick="hideBanner(); localStorage.setItem('seen_broadcast_msg', currentMsg);" style="font-size: 0.8rem;" title="Dismiss"></button>
        </div>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-3">
             <span class="badge bg-<?php echo $badge_color; ?> rounded-pill px-3 py-2 shadow-sm d-none d-lg-block">
                <i class="fas <?php echo $badge_icon; ?> me-1"></i> <?php echo $badge; ?>
             </span>

            <div class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
                <i class="fas fa-moon" id="themeIcon"></i>
            </div>
            
            <div class="dropdown">
    <div class="position-relative" id="notifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
        <i class="fas fa-bell fa-lg text-secondary" id="bell-icon"></i>
        <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; font-size: 0.6rem;">0</span>
    </div>
    
    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
        <li class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Notifications</h6>
            <small class="text-muted" style="cursor:pointer;" onclick="clearNotifications()">Clear all</small>
        </li>
        <div id="notification-list">
            <li class="p-4 text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</li>
        </div>
    </ul>
</div>
            
            <div class="dropdown">
    <img src="<?php echo $user_pic; ?>" 
         class="nav-profile-img dropdown-toggle" 
         id="userDropdown" 
         data-bs-toggle="dropdown" 
         style="cursor: pointer; width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 2px solid var(--border-color);">
    
    <ul class="dropdown-menu dropdown-menu-end p-2 shadow border-0">
        <li><h6 class="dropdown-header">Hi, <?php echo htmlspecialchars($user_data['username']); ?></h6></li>
        <li><a class="dropdown-item rounded" href="profile.php"><i class="fas fa-user me-2"></i>My Personal Space</a></li>
        <li><a class="dropdown-item rounded" href="security.php"><i class="fas fa-shield-alt me-2"></i>Security Log</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item rounded text-danger" href="index.php?logout=true"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
</div>
        </div>
    </div>
</nav>

<div class="container py-5">
    
    <div class="row mb-5">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 15px;">
                <h6 class="fw-bold text-muted text-center mb-3">BREAKDOWN</h6>
                <div style="position: relative; height: 160px; width: 100%;"><canvas id="spendChart"></canvas></div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <div class="card border-0 shadow-sm p-4 h-100 d-flex flex-column justify-content-center align-items-center text-white" 
                 style="background: <?php echo $spend_card_bg; ?>; border-radius: 15px;">
                <h3 class="fw-light opacity-75 text-center"><?php echo $spend_msg; ?></h3>
                
                <h1 id="totalSpendDisplay" class="display-3 fw-bold my-2 price-display price-cursor" 
                    data-usd="<?php echo $total_monthly_cost; ?>"
                    title="Click to convert Currency">
                    <?php echo $currency; ?><?php echo number_format($total_monthly_cost, 2); ?>
                </h1>
                
                <div class="mt-2 d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(255,255,255,0.2);">
                    <small class="fw-bold">
                        Goal: 
                        <span class="price-display" data-usd="<?php echo $budget_limit; ?>">
                            <?php echo $currency . number_format($budget_limit, 2); ?>
                        </span>
                    </small>
                    <button class="btn btn-sm btn-light text-dark rounded-pill px-3 py-0 fw-bold" style="font-size:0.75rem; height: 24px;" data-bs-toggle="modal" data-bs-target="#budgetModal">Edit</button>
                </div>

                <?php if($most_expensive_sub && $is_over_budget): ?>
                <div class="mt-3 bg-white bg-opacity-25 rounded-pill px-3 py-1 small fw-bold">
                    <i class="fas fa-lightbulb me-1 text-warning"></i> 
                    Cancel <?php echo htmlspecialchars($most_expensive_sub['service_name']); ?> to save 
                    <span class="price-display" data-usd="<?php echo ($highest_price * 12); ?>">
                        <?php echo $currency . number_format($highest_price * 12, 0); ?>
                    </span>/yr!
                </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm p-3 h-100 d-flex flex-column justify-content-center align-items-center text-center" style="border-radius: 15px;">
                
                <div class="position-relative mb-2">
                    <div class="badge bg-<?php echo $badge_color; ?> bg-opacity-10 text-<?php echo $badge_color; ?> p-3 rounded-circle" style="width: 70px; height: 70px; display:flex; align-items:center; justify-content:center; font-size: 2rem;">
                        <i class="fas <?php echo $badge_icon; ?>"></i>
                    </div>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark">
                        Lvl <?php echo array_search($current_badge, $levels) + 1; ?>
                    </span>
                </div>

                <h5 class="fw-bold mb-0 text-dark"><?php echo $badge; ?></h5>
                <small class="text-muted mb-2"><?php echo $sub_count; ?> Active Subs</small>

                <div class="w-100 px-2 mt-2">
                    <div class="progress" style="height: 6px; border-radius: 10px;">
                        <div class="progress-bar bg-<?php echo $badge_color; ?>" role="progressbar" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                    <small class="text-muted mt-1 d-block" style="font-size: 0.7rem;">
                        <?php echo $progress_msg; ?>
                    </small>
                </div>

            </div>
        </div>
    </div>

<?php if (!empty($savings_tips)): ?>
<div id="smartBanner" class="row mb-4 fade-out-target">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: #fff8e1; border-left: 4px solid #ffc107;">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                
                <div class="d-flex align-items-center">
                    <i class="fas fa-lightbulb text-warning fa-2x me-3"></i>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Money Saving Tip!</h6>
                        <small class="text-muted">We found a cheaper alternative for you.</small>
                    </div>
                </div>

                <div class="text-end">
                   <?php foreach ($savings_tips as $tip): ?>
                        <div class="mb-1">
                            <span class="text-muted small">Switch <strong><?php echo htmlspecialchars($tip['current']); ?></strong> to</span>
                            <span class="fw-bold text-success"><?php echo $tip['suggest']; ?></span>
                            
                            <span class="badge bg-success bg-opacity-10 text-success ms-2">
                                +<span class="price-display" data-usd="<?php echo $tip['save']; ?>">
                                    <?php echo $currency . $tip['save']; ?>
                                </span> saved
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn-close ms-3" onclick="dismissBanner()"></button>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            
            <div class="d-flex gap-2 p-1 bg-light rounded-pill">
                <button id="btnList" onclick="switchView('list')" class="btn btn-sm btn-white rounded-pill shadow-sm px-3 fw-bold text-primary">
                    <i class="fas fa-list me-2"></i>List
                </button>
                <button id="btnCal" onclick="switchView('calendar')" class="btn btn-sm btn-transparent rounded-pill px-3 text-muted">
                    <i class="fas fa-calendar-alt me-2"></i>Calendar
                </button>
            </div>

            <div class="d-flex gap-2 align-items-center">
            
            <div class="search-wrapper">
                <input type="text" id="liveSearchInput" class="search-input" placeholder="Search subscriptions..." onkeyup="filterCards()">
                <button class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            <select id="jsSortSelect" class="form-select form-select-sm border-0 shadow-sm" onchange="sortCards()" style="width: 140px; cursor: pointer; background-color: #f8f9fa; font-weight: 500;">
                <option value="date">📅 Due Soon</option>
                <option value="priceHigh">💰 Highest Price</option>
                <option value="priceLow">📉 Lowest Price</option>
                <option value="name">🔤 Name (A-Z)</option>
            </select>

        <div class="vr mx-1 opacity-25"></div> 

        <a href="history.php" class="btn btn-light btn-sm rounded-circle shadow-sm text-secondary" title="Archived History" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-history"></i>
        </a>

        <a href="add.php" class="btn btn-primary btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Add Subscription">
            <i class="fas fa-plus"></i>
        </a>
    </div>
            </div>
    </div>
</div>

    <div id="listView" class="row">
        <?php foreach($subs_array as $row):
            // ... existing date calculations ...
            // Calculate Days Left
    $days_left = ceil((strtotime($row['next_due_date']) - time()) / (60 * 60 * 24));
    
    // Check Snooze Status
    $is_snoozed = false;
    if (!empty($row['snooze_until']) && new DateTime() < new DateTime($row['snooze_until'])) {
        $is_snoozed = true;
    }

    // Determine Card Style
    $card_border = "border-0"; // Default clean look
    $due_badge = "";
    
    if ($days_left <= 0 && !$is_snoozed) {
        // DUE TODAY (And NOT Snoozed) -> Show Red Alert
        $card_border = "border border-2 border-danger shadow-sm"; 
        $due_badge = '<span class="badge bg-danger mb-2">DUE TODAY</span>';
    } 
    elseif ($days_left <= 0 && $is_snoozed) {
        // DUE TODAY (But Snoozed) -> Show Relaxed State
        $card_border = "border border-2 border-warning border-opacity-50"; 
        $due_badge = '<span class="badge bg-warning text-dark mb-2"><i class="fas fa-bed me-1"></i>Snoozed</span>';
    }
            // --- ZOMBIE CHECK (UNUSED DETECTION) ---
            $last_used_date = isset($row['last_used']) ? strtotime($row['last_used']) : strtotime($row['start_date']);
            $days_since_use = ceil((time() - $last_used_date) / (60 * 60 * 24));
            $is_zombie = ($days_since_use > 30); // Flag if unused for > 30 days
            $logo = "https://www.google.com/s2/favicons?domain=" . strtolower($row['service_name']) . ".com&sz=64";
            
            $today = new DateTime();
            $due_date = new DateTime($row['next_due_date']);
            
            if ($due_date < $today) {
                while ($due_date < $today) {
                    if ($row['billing_period'] == 'Yearly') {
                        $due_date->modify('+1 year');
                    } else {
                        $due_date->modify('+1 month');
                    }
                }
            }

            $interval = $today->diff($due_date);
            $days_left = $interval->days;

            $cycle_days = ($row['billing_period'] == 'Yearly') ? 365 : 30;
            $percent_filled = 100 - (($days_left / $cycle_days) * 100);
            if ($percent_filled < 0) $percent_filled = 0;
            if ($percent_filled > 100) $percent_filled = 100;
            
            if ($days_left == 0) { 
                $timer_text = "DUE TODAY"; $timer_badge = "bg-danger"; $bar_color = "bg-danger"; $card_border = "border-danger border-2";
            } elseif ($days_left <= 3) { 
                $timer_text = "⏳ " . $days_left . " Days Left"; $timer_badge = "bg-warning text-dark"; $bar_color = "bg-warning"; $card_border = "border-warning";
            } else { 
                $timer_text = $days_left . " Days Left"; $timer_badge = "bg-light text-muted border"; $bar_color = "bg-success"; $card_border = "";
            }
        ?>
        <div class="col-md-3 col-sm-6 mb-4 sub-card-item" 
             data-name="<?php echo strtolower($row['service_name']); ?>" 
             data-price="<?php echo $row['price']; ?>" 
             data-date="<?php echo $row['next_due_date']; ?>"> <div class="card shadow-sm <?php echo $card_border; ?> h-100" style="border-radius: 12px; transition: transform 0.2s;">
                <div class="card-body p-3">
                    
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <img src="<?php echo $logo; ?>" width="32" height="32" class="rounded">
                        
                        <span class="fw-bold price-display price-cursor" 
                              data-usd="<?php echo $row['price']; ?>"
                              style="font-size: 1.1rem;">
                            <?php echo $currency . $row['price']; ?>
                        </span>
                    </div>
                    
                    <h6 class="fw-bold mb-2 text-truncate service-title"><?php echo htmlspecialchars($row['service_name']); ?></h6>
                    
                            
                            <?php if (!empty($row['tags'])): 
            $tags = explode(',', $row['tags']); // Split tags by comma
            echo '<div class="mb-2 tags-container">';
            foreach($tags as $tag) {
                $tag = trim($tag); // Remove extra spaces
                if(!empty($tag)) {
                    echo '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1 rounded-pill small tag-item" style="font-size: 0.65rem;">
                            <i class="fas fa-hashtag me-1" style="font-size:0.5rem;"></i>' . htmlspecialchars($tag) . '
                        </span>';
                }
            }
    echo '</div>';
endif; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge <?php echo $timer_badge; ?>" style="font-size: 0.7rem;"><?php echo $timer_text; ?></span>
                            <span class="text-muted small" style="font-size: 0.65rem;"><?php echo $row['billing_period']; ?></span>
                        </div>
                        <div class="progress" style="height: 6px; border-radius: 10px;">
                            <div class="progress-bar <?php echo $bar_color; ?>" role="progressbar" 
                                 style="width: <?php echo $percent_filled; ?>%; transition: width 1s ease-in-out;"></div>
                        </div>
                    </div>
                    </div> <div class="card-footer bg-transparent border-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                    
                    <button onclick="archiveSub(this, <?php echo $row['id']; ?>, '<?php echo addslashes($row['service_name']); ?>', <?php echo $monthly_equivalent; ?>)" 
                            class="btn p-0 text-danger opacity-50 small border-0 bg-transparent" title="Archive">
                        <i class="fas fa-trash-alt"></i>
                    </button>

                    <?php if ($days_left <= 3): ?>
                        <div class="d-flex gap-2">
                            <?php if(!$is_snoozed): ?>
                                <a href="index.php?snooze=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-muted border" title="Snooze for 2 days">
                                    <i class="fas fa-bell-slash"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="index.php?pay_now=<?php echo $row['id']; ?>" class="btn btn-sm btn-success text-white fw-bold shadow-sm" title="Mark Paid (+1 Cycle)">
                                <i class="fas fa-check me-1"></i> Paid
                            </a>
                        </div>
                    <?php endif; ?>
                </div> 
            </div>
        </div>
        <?php endforeach; if(empty($subs_array)) echo "<div class='col-12 text-center py-5 text-muted'><i class='fas fa-search fa-2x mb-3 opacity-25'></i><br>No subscriptions found.</div>"; ?>
    </div>
    <div id="calendarView" style="display: none;">
        <div class="card shadow-sm p-4 rounded-4 border-0">
            <div id='calendar'></div>
        </div>
    </div>

</div>

<div class="modal fade" id="budgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            
            <div class="modal-header border-0 p-4 pb-0">
                <div>
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="fas fa-wallet text-primary me-2"></i> Monthly Goal
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Set a limit to get alerts if you overspend.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <div class="modal-body p-4">
                    <div class="position-relative">
                        <span class="position-absolute top-50 start-0 translate-middle-y ps-4 text-muted fw-bold" style="font-size: 1.5rem; opacity: 0.5;">
                            <?php echo $currency; ?>
                        </span>
                        
                        <input type="number" step="any" name="budget_amount" 
                               class="form-control form-control-lg border-0 bg-light fw-bold ps-5 py-4 text-center" 
                               value="<?php echo $budget_limit; ?>" 
                               placeholder="0.00" required
                               style="font-size: 2rem; border-radius: 15px;">
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_budget" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Save Limit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const CURRENCY_SYMBOL = "<?php echo $currency; ?>";
    const userSubs = <?php echo json_encode($subs_array); ?>;
    const calendarEvents = <?php echo json_encode($calendar_events); ?>;
    const INR_RATE = 87; 
    let isRupee = false;
    
    document.querySelectorAll('.price-display').forEach(el => {
        el.addEventListener('click', function() {
            isRupee = !isRupee; 
            updateAllPrices();
        });
    });

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

    // --- NEW: LIVE SEARCH FUNCTION ---
    function filterCards() {
    let input = document.getElementById('liveSearchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('sub-card-item');

    for (let i = 0; i < cards.length; i++) {
        // Get the Service Name
        let title = cards[i].querySelector('.service-title').innerText.toLowerCase();
        
        // Get the Tags (if any exist)
        let tagsContainer = cards[i].querySelector('.tags-container');
        let tags = tagsContainer ? tagsContainer.innerText.toLowerCase() : "";

        // Check if Input matches Name OR Tags
        if (title.indexOf(input) > -1 || tags.indexOf(input) > -1) {
            cards[i].style.display = ""; // Show
        } else {
            cards[i].style.display = "none"; // Hide
        }
    }
}

    // --- NEW: INSTANT CLIENT-SIDE SORTING ---
    function sortCards() {
        let sortBy = document.getElementById('jsSortSelect').value;
        let listContainer = document.getElementById('listView');
        let cards = Array.from(listContainer.getElementsByClassName('sub-card-item'));

        cards.sort((a, b) => {
            let aVal, bVal;

            if (sortBy === 'priceHigh') {
                return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
            } else if (sortBy === 'priceLow') {
                return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
            } else if (sortBy === 'name') {
                return a.dataset.name.localeCompare(b.dataset.name);
            } else { // Default: Date
                // Simple string compare works for YYYY-MM-DD, otherwise parse Date
                return new Date(a.dataset.date) - new Date(b.dataset.date);
            }
        });

        // Re-append sorted cards
        cards.forEach(card => listContainer.appendChild(card));
    }

    function switchView(view) {
        if(view === 'list') {
            document.getElementById('listView').style.display = 'flex';
            document.getElementById('calendarView').style.display = 'none';
            document.getElementById('btnList').classList.add('active');
            document.getElementById('btnCal').classList.remove('active');
        } else {
            document.getElementById('listView').style.display = 'none';
            document.getElementById('calendarView').style.display = 'block';
            document.getElementById('btnList').classList.remove('active');
            document.getElementById('btnCal').classList.add('active');
            setTimeout(() => { calendar.render(); }, 100); 
        }
    }

    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
        events: calendarEvents,
        contentHeight: 'auto',   // Adjusts automatically to fit content
        aspectRatio: 1.8,        // Makes it wider and shorter (Cinematic look)
        eventContent: function(arg) {
            let price = arg.event.extendedProps.price;
            return { html: `<div>${arg.event.title} <b style="opacity:0.8">${price}</b></div>` };
        },
        dayCellDidMount: function(info) {
            let dateStr = info.date.toISOString().split('T')[0];
            let hasEvent = calendarEvents.some(e => e.start === dateStr);
            if (hasEvent) {
                let dot = document.createElement('div');
                dot.className = 'renewal-day-marker';
                info.el.querySelector('.fc-daygrid-day-top').appendChild(dot);
                info.el.style.backgroundColor = document.body.classList.contains('dark-mode') ? 'rgba(102, 126, 234, 0.1)' : '#f8f9fa';
            }
        },
        eventClick: function(info) {
            Swal.fire({ title: info.event.title, text: 'Price: ' + info.event.extendedProps.price, icon: 'info', confirmButtonColor: '#667eea' });
        }
    });

    function confirmDelete(e, url) { e.preventDefault(); Swal.fire({ title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' }).then((result) => { if (result.isConfirmed) window.location.href = url; }); }

    function toggleTheme() {
        document.body.classList.toggle('dark-mode');
        const icon = document.getElementById('themeIcon');
        if (document.body.classList.contains('dark-mode')) {
            icon.classList.replace('fa-moon', 'fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            icon.classList.replace('fa-sun', 'fa-moon');
            localStorage.setItem('theme', 'light');
        }
        setTimeout(() => { calendar.render(); }, 200);
    }
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        document.getElementById('themeIcon').classList.replace('fa-moon', 'fa-sun');
    }

    // --- BREAKDOWN CHART WITH CURRENCY TOGGLE ---
    const ctx = document.getElementById('spendChart');
    
    window.myChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Entertainment', 'Utilities', 'Work', 'Personal', 'Other'],
            datasets: [{
                data: [<?php echo implode(',', array_values($cat_totals)); ?>],
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            cutout: '70%', 
            plugins: { 
                legend: { 
                    position: 'right', 
                    labels: { boxWidth: 10, color: 'var(--text-main)', font: { size: 11 } } 
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            let value = context.raw;
                            
                            // SMART CURRENCY CHECK
                            if (isRupee) {
                                return label + "₹" + (value * INR_RATE).toLocaleString('en-IN', { maximumFractionDigits: 0 });
                            } else {
                                return label + CURRENCY_SYMBOL + value.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                }
            } 
        }
    });
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('view') === 'calendar') { switchView('calendar'); }
</script>
<script src="script.js"></script>
<script>checkRenewals(userSubs);</script>

<?php 
if(!empty($site_settings['announcement_text']) && $site_settings['announcement_active'] == 1) {
    // Pass PHP string safely to JS
    $js_msg = json_encode($site_settings['announcement_text']);
    
    echo "<script>
        const currentMsg = $js_msg;
        const lastSeenMsg = localStorage.getItem('seen_broadcast_msg');
        const banner = document.getElementById('globalBanner');

        // Function to hide banner smoothly
        function hideBanner() {
            if(banner) {
                banner.style.transition = 'opacity 0.5s ease';
                banner.style.opacity = '0';
                setTimeout(() => banner.style.display = 'none', 500);
            }
        }

        // CASE 1: User has already seen/acknowledged this specific message
        if (currentMsg === lastSeenMsg) {
            if(banner) banner.style.display = 'none'; // Hide instantly
        } 
        // CASE 2: New Message
        else {
            // Auto-hide banner after 5 seconds
            setTimeout(() => {
                hideBanner();
                localStorage.setItem('seen_broadcast_msg', currentMsg);
            }, 5000);
        }
    </script>";
}
?>
<script>// --- AUTO-HIDE SMART BANNER ---
document.addEventListener("DOMContentLoaded", function() {
    const banner = document.getElementById('smartBanner');
    
    if (banner) {
        // Wait 5 seconds (5000ms), then fade out
        setTimeout(() => {
            dismissBanner();
        }, 5000);
    }
});

function dismissBanner() {
    const banner = document.getElementById('smartBanner');
    if (banner) {
        // 1. Fade out visually
        banner.style.transition = "opacity 1s ease, transform 1s ease";
        banner.style.opacity = "0";
        banner.style.transform = "translateY(-10px)"; // Slide up slightly

        // 2. Remove from page layout after fade is done
        setTimeout(() => {
            banner.style.display = "none";
        }, 1000);
    }
}

// --- NOTIFICATION CENTER LOGIC (With Memory) ---
document.addEventListener("DOMContentLoaded", function() {
    fetchNotifications();
    setInterval(fetchNotifications, 60000);
});

// Variable to store current list (so we can clear them easily)
let currentNotifications = [];

function fetchNotifications() {
    fetch('fetch_notifications.php')
    .then(response => response.json())
    .then(data => {
        let list = document.getElementById('notification-list');
        let badge = document.getElementById('notification-badge');
        
        // 1. Get the list of IDs the user has already dismissed
        let dismissedIds = JSON.parse(localStorage.getItem('dismissed_notifs') || '[]');

        // 2. Filter out the dismissed ones
        let activeNotifs = data.filter(item => !dismissedIds.includes(item.id));
        currentNotifications = activeNotifs; // Save for later

        list.innerHTML = ""; 

        if (activeNotifs.length > 0) {
            badge.style.display = "block";
            badge.innerText = activeNotifs.length;

            activeNotifs.forEach(item => {
                let html = `
                <li>
                    <div class="dropdown-item d-flex align-items-start p-3 border-bottom position-relative">
                        <div class="bg-light rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                            <i class="fas ${item.icon}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong class="d-block text-dark small">${item.title}</strong>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">${item.text}</small>
                            <small class="text-primary fw-bold" style="font-size: 0.65rem;">${item.time}</small>
                        </div>
                        <button onclick="dismissOne('${item.id}')" class="btn btn-link text-muted p-0 ms-2" style="font-size: 0.8rem; text-decoration: none;">&times;</button>
                    </div>
                </li>`;
                list.innerHTML += html;
            });
        } else {
            badge.style.display = "none";
            list.innerHTML = `
                <li class="p-4 text-center text-muted">
                    <i class="far fa-bell-slash fa-2x mb-2 opacity-50"></i>
                    <p class="small mb-0">No new notifications</p>
                </li>`;
        }
    })
    .catch(error => console.error('Error:', error));
}

function clearNotifications() {
    // 1. Get current saved IDs
    let dismissedIds = JSON.parse(localStorage.getItem('dismissed_notifs') || '[]');
    
    // 2. Add ALL currently visible IDs to the ignore list
    currentNotifications.forEach(item => {
        if (!dismissedIds.includes(item.id)) {
            dismissedIds.push(item.id);
        }
    });

    // 3. Save back to browser memory
    localStorage.setItem('dismissed_notifs', JSON.stringify(dismissedIds));

    // 4. Update UI visually
    fetchNotifications(); // Refresh to apply changes immediately
}

function dismissOne(id) {
    // Helper to dismiss just one item
    event.stopPropagation(); // Stop dropdown from closing
    let dismissedIds = JSON.parse(localStorage.getItem('dismissed_notifs') || '[]');
    dismissedIds.push(id);
    localStorage.setItem('dismissed_notifs', JSON.stringify(dismissedIds));
    fetchNotifications();
}

function archiveSub(btn, id, name, cost) {
    Swal.fire({
        title: 'Archive ' + name + '?',
        text: "It will disappear from here.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'Yes, Archive',
        background: document.body.classList.contains('dark-mode') ? '#1e293b' : '#fff',
        color: document.body.classList.contains('dark-mode') ? '#fff' : '#000'
    }).then((result) => {
        if (result.isConfirmed) {
            // 1. AJAX Request
            const formData = new FormData();
            formData.append('action', 'archive');
            formData.append('id', id);

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // 2. Find the card container (col-md-3)
                    const card = btn.closest('.col-md-3');
                    
                    // 3. Smooth Fade Out
                    card.style.transition = "all 0.5s ease";
                    card.style.opacity = "0";
                    card.style.transform = "scale(0.8)";
                    setTimeout(() => card.remove(), 500);

                    // 4. Update Total Spend Number Instantly
                    updateTotalDisplay(cost);

                    // 5. Success Toast
                    Swal.fire({
                        icon: 'success', title: 'Archived!', 
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 1500,
                        background: document.body.classList.contains('dark-mode') ? '#1e293b' : '#fff',
                        color: document.body.classList.contains('dark-mode') ? '#fff' : '#000'
                    });
                }
            });
        }
    });
}

function updateTotalDisplay(deductAmount) {
    const display = document.getElementById('totalSpendDisplay');
    // Get current raw value from data attribute
    let currentTotal = parseFloat(display.getAttribute('data-usd'));
    let newTotal = currentTotal - deductAmount;
    if (newTotal < 0) newTotal = 0;

    // Update data attribute for future clicks
    display.setAttribute('data-usd', newTotal);

    // Update text
    const currency = "<?php echo $currency; ?>";
    display.innerText = currency + newTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

</body>
</html>