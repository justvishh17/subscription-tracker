<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- 1. DATE RANGE FILTER LOGIC ---
$filter = isset($_GET['range']) ? $_GET['range'] : 'all';
$sub_cond = "1=1"; // Default condition (All Time)
$user_cond = "1=1"; 
$range_label = "All Time";

switch($filter) {
    case '30days':
        $sub_cond = "start_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $user_cond = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $range_label = "Last 30 Days";
        break;
    case '6months':
        $sub_cond = "start_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $user_cond = "created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $range_label = "Last 6 Months";
        break;
    case 'this_year':
        $sub_cond = "YEAR(start_date) = YEAR(CURDATE())";
        $user_cond = "YEAR(created_at) = YEAR(CURDATE())";
        $range_label = "This Year";
        break;
}

// --- DATA FETCHING (Now Filtered) ---

// 1. Categories (Pie Chart - Count)
$cat_query = $conn->query("SELECT category, COUNT(*) as count FROM subscriptions WHERE $sub_cond GROUP BY category");
$cat_labels = []; $cat_data = [];
while($row = $cat_query->fetch_assoc()) {
    $cat_labels[] = empty($row['category']) ? 'Other' : $row['category'];
    $cat_data[] = $row['count'];
}

// 2. Billing Cycle (Doughnut Chart)
$bill_query = $conn->query("SELECT billing_period, COUNT(*) as count FROM subscriptions WHERE $sub_cond GROUP BY billing_period");
$bill_labels = []; $bill_data = [];
while($row = $bill_query->fetch_assoc()) {
    $bill_labels[] = $row['billing_period'];
    $bill_data[] = $row['count'];
}

// 3. Top Spenders (Bar Chart)
$top_query = $conn->query("
    SELECT u.username, SUM(s.price) as total_spend 
    FROM subscriptions s 
    JOIN users u ON s.user_id = u.id 
    WHERE $sub_cond 
    GROUP BY s.user_id 
    ORDER BY total_spend DESC LIMIT 5
");
$user_labels = []; $user_spend = [];
while($row = $top_query->fetch_assoc()) {
    $user_labels[] = $row['username'];
    $user_spend[] = number_format((float)$row['total_spend'], 2, '.', '');
}

// 4. User Growth (Line Chart) - Uses $user_cond
$growth_query = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users WHERE $user_cond GROUP BY month ORDER BY month ASC LIMIT 6");
$growth_labels = []; $growth_data = [];
while($row = $growth_query->fetch_assoc()) {
    $growth_labels[] = date("M Y", strtotime($row['month']));
    $growth_data[] = $row['count'];
}

// 5. Total Volume by Category (Pie Chart - Money)
$vol_query = $conn->query("SELECT category, SUM(CASE WHEN billing_period='Yearly' THEN price/12 ELSE price END) as total_vol FROM subscriptions WHERE $sub_cond GROUP BY category");
$vol_labels = []; $vol_data = [];
while($row = $vol_query->fetch_assoc()) {
    $vol_labels[] = empty($row['category']) ? 'Other' : $row['category'];
    $vol_data[] = number_format((float)$row['total_vol'], 2, '.', '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Analytics - Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔒</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- DARK THEME VARIABLES (MATCHING ADMIN.PHP) --- */
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

        /* Sidebar */
        .sidebar {
            height: 100vh; width: 260px; position: fixed; top: 0; left: 0;
            background: #111827; 
            border-right: 1px solid var(--border-color);
            padding-top: 25px; z-index: 1000;
        }
        .sidebar .brand { 
            font-size: 1.3rem; font-weight: 700; text-align: center; margin-bottom: 40px; 
            color: white; letter-spacing: 0.5px; text-transform: uppercase;
        }
        .sidebar a {
            padding: 16px 25px; text-decoration: none; font-size: 1rem; 
            color: #9ca3af; display: flex; align-items: center; transition: 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover, .sidebar a.active { 
            background: rgba(255,255,255,0.05); 
            color: #fff; 
            border-left-color: var(--accent); 
        }
        .sidebar a i { width: 25px; text-align: center; margin-right: 10px; }

        .main-content { margin-left: 260px; padding: 40px; }
        
        /* Chart Cards - Dark Mode */
        .chart-card { 
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
            border: 1px solid var(--border-color); 
            height: 100%; 
        }

        /* Nav Pills - Dark Mode */
        .nav-pills {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
        }
        .nav-pills .nav-link { 
            color: var(--text-muted); 
            font-weight: 600; 
            padding: 10px 20px; 
            border-radius: 8px; 
        }
        .nav-pills .nav-link:hover { color: white; }
        .nav-pills .nav-link.active { 
            background-color: var(--accent); 
            color: white; 
        }

        /* Interactive Elements */
        .btn-currency {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 6px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .btn-currency:hover, .btn-currency.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .form-select-dark {
            background-color: var(--bg-card);
            color: white;
            border: 1px solid var(--border-color);
            padding: 6px 30px 6px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .form-select-dark:focus {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
            border-color: var(--accent);
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</div>
    <a href="admin.php"><i class="fas fa-users-cog"></i> User Management</a>
    <a href="admin_analytics.php" class="active"><i class="fas fa-chart-line"></i> Global Analytics</a>
    <a href="admin_settings.php"><i class="fas fa-sliders-h"></i> Platform Settings</a>    
    <div style="position: absolute; bottom: 30px; width: 100%;">
        <a href="index.php?logout=true" style="color: #ef4444;"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-white mb-1">Global Analytics</h3>
            <p class="text-muted m-0 small">Performance metrics for <strong><?php echo $range_label; ?></strong></p>
        </div>
        
        <div class="d-flex gap-3 align-items-center">
            
            <form method="GET" class="d-flex align-items-center">
                <i class="fas fa-calendar-alt text-muted me-2"></i>
                <select name="range" class="form-select-dark" onchange="this.form.submit()">
                    <option value="all" <?php if($filter=='all') echo 'selected'; ?>>All Time</option>
                    <option value="30days" <?php if($filter=='30days') echo 'selected'; ?>>Last 30 Days</option>
                    <option value="6months" <?php if($filter=='6months') echo 'selected'; ?>>Last 6 Months</option>
                    <option value="this_year" <?php if($filter=='this_year') echo 'selected'; ?>>This Year</option>
                </select>
            </form>

            <div class="vr bg-secondary mx-2" style="height: 20px;"></div>

            <button class="btn btn-currency" id="currencyToggle" onclick="toggleCurrency()">
                <i class="fas fa-coins me-1"></i> Switch to INR (₹)
            </button>

            <ul class="nav nav-pills p-1 rounded shadow-sm bg-dark border border-secondary" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-finance">
                        <i class="fas fa-wallet me-2"></i>Financials
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-users">
                        <i class="fas fa-user-friends me-2"></i>Users
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="pills-tabContent">
        
        <div class="tab-pane fade show active" id="pills-finance">
            
            <div class="row mb-4">
                <div class="col-12">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-white m-0">Total Monthly Spending by Category (Volume)</h5>
                            <span class="badge bg-success bg-opacity-25 text-success" id="volCurrencyBadge">USD</span>
                        </div>
                        <div style="height: 350px;">
                            <canvas id="volumeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="chart-card">
                        <h5 class="fw-bold text-center mb-4 text-white">Subscription Count by Category</h5>
                        <div style="height: 300px;">
                            <canvas id="catChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="chart-card">
                        <h5 class="fw-bold text-center mb-4 text-white">Billing Cycles</h5>
                        <div style="height: 300px;">
                            <canvas id="billChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pills-users">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-white m-0">Top 5 Highest Spenders</h5>
                            <span class="badge bg-success bg-opacity-25 text-success" id="userCurrencyBadge">USD</span>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="userChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="chart-card">
                        <h5 class="fw-bold text-center mb-4 text-white">User Signups (Trend)</h5>
                        <div style="height: 300px;">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- CONFIGURE GLOBAL CHART COLORS ---
    Chart.defaults.color = '#cbd5e1';  
    Chart.defaults.borderColor = '#334155'; 

    // --- DATA PREP FOR CURRENCY TOGGLE ---
    const INR_RATE = 87; // Conversion Rate
    // Check localStorage for currency preference
    let isRupee = localStorage.getItem('adminCurrency') === 'INR';

    // Volume Data
    const volLabels = <?php echo json_encode($vol_labels); ?>;
    const volDataUSD = <?php echo json_encode($vol_data); ?>;
    const volDataINR = volDataUSD.map(val => (val * INR_RATE).toFixed(2));

    // User Spend Data
    const userLabels = <?php echo json_encode($user_labels); ?>;
    const userSpendUSD = <?php echo json_encode($user_spend); ?>;
    const userSpendINR = userSpendUSD.map(val => (val * INR_RATE).toFixed(2));

    // --- CHART INITIALIZATION ---

    // 1. Total Volume Pie Chart
    const ctxVol = document.getElementById('volumeChart').getContext('2d');
    let volumeChart = new Chart(ctxVol, {
        type: 'pie',
        data: {
            labels: volLabels,
            datasets: [{
                label: 'Total Volume ($)',
                data: volDataUSD,
                backgroundColor: ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6'],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: { 
            maintainAspectRatio: false,
            plugins: { 
                legend: { position: 'right', labels: { color: '#ffffff', font: { size: 14 } } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw;
                            let symbol = isRupee ? '₹' : '$';
                            return label + ': ' + symbol + value;
                        }
                    }
                }
            } 
        }
    });

    // 2. Count Pie Chart
    new Chart(document.getElementById('catChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_data); ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: { 
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#ffffff' } } }
        }
    });

    // 3. Billing Cycle Chart
    new Chart(document.getElementById('billChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($bill_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($bill_data); ?>,
                backgroundColor: ['#2ecc71', '#e67e22'],
                borderWidth: 0,
                hoverOffset: 12
            }]
        },
        options: { 
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#ffffff' } } }
        }
    });

    // 4. Top Spenders Chart
    const ctxUser = document.getElementById('userChart').getContext('2d');
    let userChart = new Chart(ctxUser, {
        type: 'bar',
        data: {
            labels: userLabels,
            datasets: [{
                label: 'Total Spend ($)',
                data: userSpendUSD,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            }]
        },
        options: { 
            maintainAspectRatio: false, 
            scales: { x: { grid: { display: false } } },
            plugins: { 
                tooltip: { callbacks: { label: function(context) { return 'Total: ' + (isRupee ? '₹' : '$') + context.raw; } } } 
            }
        }
    });

    // 5. Growth Chart
    const ctxGrowth = document.getElementById('growthChart').getContext('2d');
    let growthChart = new Chart(ctxGrowth, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($growth_labels); ?>,
            datasets: [{
                label: 'New Users',
                data: <?php echo json_encode($growth_data); ?>,
                borderColor: '#9b59b6',
                backgroundColor: 'rgba(155, 89, 182, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { maintainAspectRatio: false, scales: { x: { grid: { display: false } } } }
    });

    // --- FIX BLANK CHARTS ON TAB SWITCH ---
    document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => { userChart.resize(); growthChart.resize(); volumeChart.resize(); });
    });

    // --- CURRENCY TOGGLE LOGIC ---
    function updateCurrencyUI() {
        const btn = document.getElementById('currencyToggle');
        const volBadge = document.getElementById('volCurrencyBadge');
        const userBadge = document.getElementById('userCurrencyBadge');

        if(isRupee) {
            // Update Volume Chart to INR
            volumeChart.data.datasets[0].data = volDataINR;
            volumeChart.data.datasets[0].label = 'Total Volume (₹)';
            
            // Update User Chart to INR
            userChart.data.datasets[0].data = userSpendINR;
            userChart.data.datasets[0].label = 'Total Spend (₹)';
            
            btn.innerHTML = '<i class="fas fa-coins me-1"></i> Switch to USD ($)';
            volBadge.innerText = "INR";
            userBadge.innerText = "INR";
        } else {
            // Update Volume Chart to USD
            volumeChart.data.datasets[0].data = volDataUSD;
            volumeChart.data.datasets[0].label = 'Total Volume ($)';
            
            // Update User Chart to USD
            userChart.data.datasets[0].data = userSpendUSD;
            userChart.data.datasets[0].label = 'Total Spend ($)';
            
            btn.innerHTML = '<i class="fas fa-coins me-1"></i> Switch to INR (₹)';
            volBadge.innerText = "USD";
            userBadge.innerText = "USD";
        }
        volumeChart.update();
        userChart.update();
    }

    function toggleCurrency() {
        isRupee = !isRupee;
        localStorage.setItem('adminCurrency', isRupee ? 'INR' : 'USD');
        updateCurrencyUI();
    }

    // Initialize currency on load
    updateCurrencyUI();

</script>

</body>
</html>