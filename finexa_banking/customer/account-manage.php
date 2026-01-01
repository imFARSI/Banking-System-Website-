<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Manage Accounts";

// Create new account
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_account'])) {
    $account_type = sanitize($conn, $_POST['account_type']);
    
    // Generate unique account number
    $account_number = generateAccountNumber($conn);
    
    // Insert account
    $query = "INSERT INTO accounts (user_id, account_number, account_type, balance, status) VALUES ('$user_id', '$account_number', '$account_type', 0.00, 'active')";
    
    if (mysqli_query($conn, $query)) {
        $account_id = mysqli_insert_id($conn);
        
        // Create notification
        createNotification($conn, $user_id, 'Account Created', 'Your new ' . $account_type . ' account ' . $account_number . ' has been created successfully.', 'account');
        
        // Log activity
        logActivity($conn, $user_id, 'create_account', 'account', $account_id, 'Created new ' . $account_type . ' account');
        
        setAlert('success', 'Account created successfully! Account Number: ' . $account_number);
        header("Location: account-manage.php");
        exit();
    } else {
        setAlert('error', 'Failed to create account. Please try again.');
    }
}

// Get all user accounts
$accounts_query = "SELECT * FROM accounts WHERE user_id = '$user_id' ORDER BY created_at DESC";
$accounts_result = mysqli_query($conn, $accounts_query);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-wallet"></i> Manage Accounts</h2>
    
    <!-- Create New Account Form -->
    <div class="form-custom mb-4">
        <h4 class="mb-3"><i class="fas fa-plus-circle"></i> Create New Account</h4>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Type *</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="account_type" id="savings" value="savings" checked required>
                            <label class="form-check-label" for="savings">
                                <i class="fas fa-piggy-bank"></i> Savings Account
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="account_type" id="current" value="current" required>
                            <label class="form-check-label" for="current">
                                <i class="fas fa-briefcase"></i> Current Account
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3 d-flex align-items-end">
                    <button type="submit" name="create_account" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Account
                    </button>
                </div>
            </div>
        </form>
        
        <div class="alert alert-info mb-0">
            <strong>Note:</strong> Account number will be automatically generated. Initial balance will be ৳0.00
        </div>
    </div>
    
    <!-- All Accounts List -->
    <div class="dashboard-card">
        <h4 class="mb-3"><i class="fas fa-list"></i> My Accounts</h4>
        
        <?php if (mysqli_num_rows($accounts_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Account Number</th>
                            <th>Account Type</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($account = mysqli_fetch_assoc($accounts_result)): ?>
                            <tr>
                                <td><strong><?php echo $account['account_number']; ?></strong></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo ucfirst($account['account_type']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo formatCurrency($account['balance']); ?></strong></td>
                                <td>
                                    <?php if ($account['status'] == 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($account['status'] == 'frozen'): ?>
                                        <span class="badge bg-warning">Frozen</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($account['created_at'])); ?></td>
                                <td>
                                    <a href="transactions.php?account=<?php echo $account['account_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-history"></i> Transactions
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> You don't have any accounts yet. Create your first account above!
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>