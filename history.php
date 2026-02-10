<?php
session_start();
include 'db.php';

// Security check
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// --- 1. SINGLE ACTION HANDLERS ---
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $stmt = $conn->prepare("UPDATE subscriptions SET status='active', archived_at=NULL WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    header("Location: history.php?msg=restored"); exit();
}

if (isset($_GET['perm_delete'])) {
    $id = (int)$_GET['perm_delete'];
    $stmt = $conn->prepare("DELETE FROM subscriptions WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    header("Location: history.php?msg=deleted"); exit();
}

// --- 2. BULK ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    
    // Sanitize IDs (ensure they are integers)
    $ids = array_map('intval', $_POST['selected_ids']);
    if (!empty($ids)) {
        $id_list = implode(',', $ids); // Safe comma-separated list for IN clause
        
        if (isset($_POST['bulk_restore'])) {
            // Restore All Selected
            $conn->query("UPDATE subscriptions SET status='active', archived_at=NULL WHERE id IN ($id_list) AND user_id=$user_id");
            header("Location: history.php?msg=bulk_restored"); exit();
        } 
        elseif (isset($_POST['bulk_delete'])) {
            // Delete All Selected
            $conn->query("DELETE FROM subscriptions WHERE id IN ($id_list) AND user_id=$user_id");
            header("Location: history.php?msg=bulk_deleted"); exit();
        }
    }
}

// Fetch Archived Items
$sql = "SELECT * FROM subscriptions WHERE user_id = $user_id AND status = 'archived' ORDER BY archived_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription History</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📜</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .table thead th { background-color: #f1f5f9; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
        
        /* Checkbox Styling */
        .form-check-input { cursor: pointer; border: 2px solid #cbd5e1; width: 1.2em; height: 1.2em; }
        .form-check-input:checked { background-color: #3b82f6; border-color: #3b82f6; }
        
        /* Floating Action Bar */
        #bulkActionBar {
            display: none; /* Hidden by default */
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: #1e293b; color: white; padding: 12px 25px; border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 1000; align-items: center; gap: 20px;
        }
        .btn-bulk { border-radius: 20px; padding: 5px 15px; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Archived Subscriptions</h2>
            <p class="text-muted small">Manage your inactive or cancelled subscriptions.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Dashboard</a>
    </div>

    <form method="POST" id="bulkForm">
        <div class="card">
            <div class="card-body p-0">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleAll(this)">
                                    </th>
                                    <th>Service</th>
                                    <th>Price</th>
                                    <th>Archived On</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="selected_ids[]" value="<?php echo $row['id']; ?>" class="form-check-input item-checkbox" onclick="updateBulkBar()">
                                    </td>
                                    
                                    <td class="fw-bold text-dark">
                                        <img src="https://www.google.com/s2/favicons?domain=<?php echo strtolower($row['service_name']); ?>.com" width="24" class="me-2 rounded">
                                        <?php echo htmlspecialchars($row['service_name']); ?>
                                    </td>
                                    
                                    <td class="fw-bold text-secondary"><?php echo $row['price']; ?></td>
                                    
                                    <td class="small">
                                        <div class="text-muted fw-semibold">
                                            <?php echo date("M d, Y", strtotime($row['archived_at'])); ?>
                                        </div>
                                        <?php if(!empty($row['auto_delete_at'])): 
                                            $days_left = ceil((strtotime($row['auto_delete_at']) - time()) / 86400);
                                        ?>
                                            <?php if($days_left > 0): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill mt-1">
                                                    <i class="fas fa-clock me-1"></i> <?php echo $days_left; ?> days left
                                                </span>
                                            <?php else: ?>
                                                <span class="text-danger fw-bold">Pending Deletion...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>                               
                                    
                                    <td class="text-end">
                                        <button type="button" onclick="confirmSingle('restore', <?php echo $row['id']; ?>)" class="btn btn-sm btn-light border text-success me-1" title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" onclick="confirmSingle('delete', <?php echo $row['id']; ?>)" class="btn btn-sm btn-light border text-danger" title="Delete Permanently">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted opacity-25"><i class="fas fa-box-open fa-4x"></i></div>
                        <h5 class="text-muted">No archived subscriptions found.</h5>
                        <p class="text-muted small">Items you delete from your dashboard will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="bulkActionBar">
            <span class="fw-bold"><span id="selectedCount">0</span> items selected</span>
            <div class="vr bg-secondary mx-2" style="height: 20px;"></div>
            
            <button type="button" name="bulk_restore" class="btn btn-bulk btn-success text-white" onclick="confirmBulk('restore')">
                <i class="fas fa-undo me-1"></i> Restore
            </button>
            
            <button type="button" name="bulk_delete" class="btn btn-bulk btn-danger text-white" onclick="confirmBulk('delete')">
                <i class="fas fa-trash me-1"></i> Delete
            </button>

            <input type="hidden" name="bulk_restore" id="inputRestore" disabled>
            <input type="hidden" name="bulk_delete" id="inputDelete" disabled>
        </div>

    </form>
</div>

<script>
    // 1. SELECT ALL LOGIC
    function toggleAll(source) {
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            cb.checked = source.checked;
        });
        updateBulkBar();
    }

    // 2. SHOW/HIDE BULK BAR
    function updateBulkBar() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const count = checkboxes.length;
        const bar = document.getElementById('bulkActionBar');
        
        document.getElementById('selectedCount').innerText = count;
        
        if (count > 0) {
            bar.style.display = 'flex';
        } else {
            bar.style.display = 'none';
        }
    }

    // 3. SWEETALERT: SINGLE ACTIONS
    function confirmSingle(action, id) {
        const isDelete = action === 'delete';
        Swal.fire({
            title: isDelete ? 'Delete Permanently?' : 'Restore Subscription?',
            text: isDelete ? "This cannot be undone!" : "It will return to your active dashboard.",
            icon: isDelete ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDelete ? '#ef4444' : '#10b981',
            confirmButtonText: isDelete ? 'Yes, delete it!' : 'Yes, restore it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `history.php?${isDelete ? 'perm_delete' : 'restore'}=${id}`;
            }
        });
    }

    // 4. SWEETALERT: BULK ACTIONS
    function confirmBulk(action) {
        const isDelete = action === 'delete';
        const count = document.getElementById('selectedCount').innerText;
        
        Swal.fire({
            title: isDelete ? `Delete ${count} Items?` : `Restore ${count} Items?`,
            text: isDelete ? "These will be permanently removed!" : "These will be moved back to your dashboard.",
            icon: isDelete ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDelete ? '#ef4444' : '#10b981',
            confirmButtonText: isDelete ? 'Yes, delete all!' : 'Yes, restore all!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Enable the hidden input for the specific action so PHP detects it
                if(isDelete) document.getElementById('inputDelete').disabled = false;
                else document.getElementById('inputRestore').disabled = false;
                
                // Submit Form
                document.getElementById('bulkForm').submit();
            }
        });
    }

    // 5. SUCCESS MESSAGES
    <?php if(isset($_GET['msg'])): ?>
        const msg = "<?php echo $_GET['msg']; ?>";
        let title = "Success";
        if(msg === 'restored') title = "Subscription Restored";
        if(msg === 'bulk_restored') title = "Items Restored";
        if(msg === 'deleted') title = "Deleted Permanently";
        if(msg === 'bulk_deleted') title = "Items Deleted Permanently";

        Swal.fire({
            icon: 'success',
            title: title,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
        
        // Clean URL
        window.history.replaceState({}, '', window.location.pathname);
    <?php endif; ?>
</script>

</body>
</html>