<?php
session_start();
include 'db.php';

// Session timeout: 30 minutes
$timeout = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login.php?msg=session_expired"); exit();
}
$_SESSION['last_activity'] = time();

// 1. INITIALIZE VARIABLES
$popup_script = "";
$currency = '$'; 
$user_id = $_SESSION['user_id'] ?? null;

// SECURITY CHECK
if (!$user_id) { header("Location: login.php"); exit(); }

// --- REFRESH FIX (PRG Pattern) ---
if (isset($_SESSION['flash_popup'])) {
    $flash = $_SESSION['flash_popup'];
    if ($flash['type'] == 'success') {
        $popup_script = "
        <script>
            Swal.fire({
                title: 'Added!', 
                text: '{$flash['text']}', 
                icon: 'success', 
                confirmButtonColor: '#667eea', 
                confirmButtonText: 'Go to Dashboard', 
                showCancelButton: true, 
                cancelButtonText: 'Add Another' 
            }).then((result) => { 
                if (result.isConfirmed) { window.location.href = 'index.php'; } 
            });
        </script>";
    } elseif ($flash['type'] == 'duplicate') {
        $popup_script = "<script>Swal.fire({ title: 'Already Tracked!', text: '{$flash['text']}', icon: 'warning', confirmButtonColor: '#f39c12' });</script>";
    } elseif ($flash['type'] == 'error') {
        $popup_script = "<script>Swal.fire('Error', '{$flash['text']}', 'error');</script>";
    }
    unset($_SESSION['flash_popup']);
}

// 2. FETCH SETTINGS & CATALOG
$setting_query = $conn->query("SELECT currency_symbol FROM site_settings WHERE id=1");
if ($setting_query && $setting_query->num_rows > 0) {
    $currency = $setting_query->fetch_assoc()['currency_symbol'];
}

$catalog_query = $conn->query("SELECT * FROM service_catalog ORDER BY name ASC");
$service_catalog = [];
if ($catalog_query) {
    while($row = $catalog_query->fetch_assoc()) {
        $row['plans'] = json_decode($row['plans_json'], true);
        unset($row['plans_json']);
        $service_catalog[strtolower($row['name'])] = $row;
    }
}

// 3. FORM HANDLING
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $raw_name = trim($_POST['service_name']);
    $service_name = $conn->real_escape_string($raw_name);
    $category = $conn->real_escape_string($_POST['category']);
    $price = $conn->real_escape_string($_POST['price']); 
    $billing_period = $conn->real_escape_string($_POST['billing_period']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $tags = isset($_POST['tags']) ? $conn->real_escape_string($_POST['tags']) : '';

    // Check Duplicate
    $is_duplicate = false;
    $check_res = $conn->query("SELECT service_name FROM subscriptions WHERE user_id='$user_id'");
    while($r = $check_res->fetch_assoc()) {
        if (strtolower(trim($r['service_name'])) === strtolower($raw_name)) { $is_duplicate = true; break; }
    }

    if ($is_duplicate) {
        $_SESSION['flash_popup'] = ['type' => 'duplicate', 'text' => "You are already tracking $raw_name."];
    } else {
        $next_due_date = ($billing_period == 'Monthly') ? date('Y-m-d', strtotime('+1 month', strtotime($start_date))) : date('Y-m-d', strtotime('+1 year', strtotime($start_date)));
        
        $sql = "INSERT INTO subscriptions (user_id, service_name, category, price, billing_period, start_date, next_due_date, tags)
                VALUES ('$user_id', '$service_name', '$category', '$price', '$billing_period', '$start_date', '$next_due_date', '$tags')";
                
        if ($conn->query($sql) === TRUE) {
            $_SESSION['flash_popup'] = ['type' => 'success', 'text' => "$service_name has been tracked."];
        } else {
            $_SESSION['flash_popup'] = ['type' => 'error', 'text' => $conn->error];
        }
    }
    header("Location: add.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Subscription</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>➕</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 20px 0; }
        .main-card { background: white; border-radius: 20px; box-shadow: 0 15px 50px rgba(0,0,0,0.1); overflow: hidden; border: none; height: 85vh; }
        
        /* --- SCROLL FIX: HEIGHT 100% & REMOVE FLEX CENTERING --- */
        .form-side { 
            padding: 40px; 
            overflow-y: auto; 
            height: 100%; 
            display: block; /* Ensures natural flow for scrolling */
        }
        
        .info-side { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 30px; overflow-y: auto; height: 100%; }
        
        /* Custom Scrollbars */
        .form-side::-webkit-scrollbar, .info-side::-webkit-scrollbar { width: 8px; }
        .form-side::-webkit-scrollbar-thumb, .info-side::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 4px; }
        .info-side::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); }

        .app-badge { background: rgba(255, 255, 255, 0.15); border-radius: 10px; padding: 8px 12px; display: inline-flex; align-items: center; margin: 4px; font-size: 0.9rem; backdrop-filter: blur(5px); cursor: pointer; border: 1px solid rgba(255,255,255,0.05); transition: 0.2s; }
        .app-badge:hover { background: white; color: #1e3c72; transform: translateY(-2px); }
        .btn-submit { background: #1e3c72; border: none; padding: 12px; border-radius: 12px; font-weight: 600; font-size: 1.1rem; transition: 0.3s; }
        .btn-submit:hover { background: #2a5298; transform: translateY(-2px); }
        
        /* PLAN CARDS STYLING */
        .plan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-bottom: 20px; display: none; }
        .plan-card {
            border: 2px solid #eee; border-radius: 12px; padding: 10px; cursor: pointer; transition: 0.2s; text-align: center; background: #fff;
        }
        .plan-card:hover { border-color: #1e3c72; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .plan-card.active { border-color: #1e3c72; background-color: #eef2ff; }
        .plan-name { font-size: 0.8rem; font-weight: 600; color: #555; display: block; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .plan-price { font-size: 1.1rem; font-weight: 800; color: #1e3c72; display: block; }
        .plan-cycle { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }

        datalist { position: absolute; max-height: 20px; border: 1px solid #ddd; overflow-y: auto; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-11 col-lg-12">
            <div class="card main-card">
                <div class="row g-0 h-100">
                    
                    <div class="col-md-6 form-side">
                        <h3 class="fw-bold mb-1 text-dark">Track New Subscription</h3>
                        <p class="text-muted mb-4">Select an app or type to see <strong>Real Plans</strong>.</p>
                        
                        <form method="POST" id="subForm">
                            <div class="form-floating mb-3">
                                <input type="text" name="service_name" class="form-control" id="floatService" 
                                       placeholder="Netflix" list="serviceList" oninput="checkService()" autocomplete="off" required>
                                <label>Service Name</label>
                                <datalist id="serviceList">
                                    <?php foreach ($service_catalog as $key => $service): ?>
                                        <option value="<?php echo htmlspecialchars($service['name']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div id="plansGrid" class="plan-grid"></div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select name="category" class="form-select" id="floatCat">
                                            <option value="Entertainment">Entertainment 🎬</option>
                                            <option value="Utilities">Utilities 💡</option>
                                            <option value="Work/Tools">Work & Tools 🛠️</option>
                                            <option value="Personal">Personal 🧘</option>
                                            <option value="Other">Other 📦</option>
                                        </select>
                                        <label>Category</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3" id="priceContainer">
                                        <input type="number" step="0.01" name="price" class="form-control" id="floatPrice" placeholder="0.00" required>
                                        <label>Price (<?php echo $currency; ?>)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select name="billing_period" class="form-select" id="floatCycle">
                                            <option value="Monthly">Monthly</option>
                                            <option value="Yearly">Yearly</option>
                                        </select>
                                        <label>Billing Cycle</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-4">
                                        <input type="date" name="start_date" class="form-control" id="floatDate" required>
                                        <label>Start Date</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating mb-3">
                                        <input type="text" name="tags" class="form-control" id="floatTags" placeholder="Tags">
                                        <label><i class="fas fa-hashtag text-muted me-1"></i>Tags</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-submit text-white mb-3">
                                <i class="fas fa-plus-circle me-2"></i> Add Subscription
                            </button>
                            <div class="text-center">
                                <a href="index.php" class="text-decoration-none text-secondary small">← Back to Dashboard</a>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-6 info-side">
                        <h4 class="fw-bold mb-1">Quick Catalog</h4>
                        <p class="small opacity-75 mb-3">Tap to see real pricing plans.</p>

                        <?php 
                        $cats = ['Entertainment', 'Work/Tools', 'Utilities', 'Personal'];
                        foreach($cats as $cat): 
                        ?>
                            <div class="mb-3">
                                <div style="font-size:0.8rem; text-transform:uppercase; border-bottom:1px solid rgba(255,255,255,0.2); margin-bottom:8px;"><?php echo $cat; ?></div>
                                <div class="d-flex flex-wrap">
                                    <?php 
                                    $count = 0;
                                    foreach($service_catalog as $s): 
                                        if($s['category'] == $cat && $count < 8): 
                                            $count++;
                                    ?>
                                        <div class="app-badge" onclick="fillForm('<?php echo addslashes($s['name']); ?>')">
                                            <i class="<?php echo $s['logo_icon']; ?>"></i> <?php echo $s['name']; ?>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const masterCatalog = <?php echo json_encode($service_catalog); ?>;
    const currencySymbol = "<?php echo $currency; ?>";
    const plansGrid = document.getElementById('plansGrid');

    function checkService() {
        let inputName = document.getElementById('floatService').value.toLowerCase().trim();
        
        // Hide grid initially
        plansGrid.style.display = 'none';
        plansGrid.innerHTML = '';

        if (masterCatalog[inputName]) {
            let service = masterCatalog[inputName];
            
            // Auto-Select Category
            let catSelect = document.getElementById('floatCat');
            for(let i=0; i<catSelect.options.length; i++){
                if(catSelect.options[i].value === service.category) {
                    catSelect.selectedIndex = i; break;
                }
            }

            // Generate Plan Cards
            let plans = service.plans;
            if(plans && plans.length > 0) {
                plansGrid.style.display = 'grid'; // Show grid
                
                plans.forEach(plan => {
                    let card = document.createElement('div');
                    card.className = 'plan-card';
                    card.innerHTML = `
                        <span class="plan-name" title="${plan.name}">${plan.name}</span>
                        <span class="plan-price">${currencySymbol}${plan.price}</span>
                        <span class="plan-cycle">${plan.cycle}</span>
                    `;
                    
                    // Click Event
                    card.onclick = function() {
                        // Remove active class from all
                        document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('active'));
                        // Add to current
                        this.classList.add('active');
                        
                        // Fill Form
                        document.getElementById('floatPrice').value = plan.price;
                        document.getElementById('floatCycle').value = plan.cycle;
                        
                        // Visual Feedback on Inputs
                        document.getElementById('floatPrice').style.backgroundColor = "#eef2ff";
                        setTimeout(() => { document.getElementById('floatPrice').style.backgroundColor = ""; }, 500);
                    };
                    
                    plansGrid.appendChild(card);
                });
            }
        }
    }

    function fillForm(name) {
        document.getElementById('floatService').value = name;
        checkService(); // Trigger logic
    }
</script>

<?php echo $popup_script; ?>

</body>
</html>