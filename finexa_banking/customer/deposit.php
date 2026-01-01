<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Deposit Money";

// Process deposit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deposit'])) {
    $account_id = sanitize($conn, $_POST['account_id']);
    $amount = sanitize($conn, $_POST['amount']);
    $description = sanitize($conn, $_POST['description']);
    
    $errors = array();
    
    // Validate amount
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount";
    }
    
    $min_deposit = getSetting($conn, 'min_deposit');
    if ($amount < $min_deposit) {
        $errors[] = "Minimum deposit amount is " . formatCurrency($min_deposit);
    }
    
    // Verify account belongs to user
    $check_query = "SELECT * FROM accounts WHERE account_id = '$account_id' AND user_id = '$user_id' AND status = 'active'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 0) {
        $errors[] = "Invalid account selected";
    }
    
    if (empty($errors)) {
        $account = mysqli_fetch_assoc($check_result);
        $new_balance = $account['balance'] + $amount;
        $reference = generateReferenceNumber();
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert transaction record
            $txn_query = "INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, reference_number, status) VALUES ('$account_id', 'deposit', '$amount', '$new_balance', '$description', '$reference', 'completed')";
            mysqli_query($conn, $txn_query);
            
            // Update account balance
            $update_query = "UPDATE accounts SET balance = '$new_balance' WHERE account_id = '$account_id'";
            mysqli_query($conn, $update_query);
            
            // Create notification
            createNotification($conn, $user_id, 'Deposit Successful', 'Amount ' . formatCurrency($amount) . ' deposited to account ' . $account['account_number'], 'transaction');
            
            // Log activity
            logActivity($conn, $user_id, 'deposit', 'transaction', mysqli_insert_id($conn), 'Deposited ' . formatCurrency($amount) . ' to account ' . $account['account_number']);
            
            mysqli_commit($conn);
            
            setAlert('success', 'Deposit successful! Amount: ' . formatCurrency($amount) . ' | Reference: ' . $reference);
            header("Location: deposit.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            setAlert('error', 'Deposit failed. Please try again.');
        }
    } else {
        foreach ($errors as $error) {
            setAlert('error', $error);
            break;
        }
    }
}

// Get user accounts
$accounts_query = "SELECT * FROM accounts WHERE user_id = '$user_id' AND status = 'active' ORDER BY account_number";
$accounts_result = mysqli_query($conn, $accounts_query);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-plus-circle"></i> Deposit Money</h2>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="form-custom">
                <h4 class="mb-3">Deposit Form</h4>
                
                <?php if (mysqli_num_rows($accounts_result) > 0): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="account_id" class="form-label">Select Account *</label>
                            <select class="form-select" id="account_id" name="account_id" required>
                                <option value="">Choose account...</option>
                                <?php 
                                mysqli_data_seek($accounts_result, 0);
                                while ($account = mysqli_fetch_assoc($accounts_result)): 
                                ?>
                                    <option value="<?php echo $account['account_id']; ?>">
                                        <?php echo $account['account_number']; ?> - 
                                        <?php echo ucfirst($account['account_type']); ?> - 
                                        Balance: <?php echo formatCurrency($account['balance']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (৳) *</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            <small class="text-muted">Minimum deposit: <?php echo formatCurrency(getSetting($conn, 'min_deposit')); ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optional note..."></textarea>
                        </div>
                        
                        <button type="submit" name="deposit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-check-circle"></i> Deposit Money
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You don't have any active accounts. 
                        <a href="account-manage.php" class="alert-link">Create an account first</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h4 class="mb-3"><i class="fas fa-info-circle"></i> Deposit Information</h4>
                
                <div class="alert alert-info">
                    <h6><strong>How to Deposit:</strong></h6>
                    <ol class="mb-0">
                        <li>Select the account you want to deposit into</li>
                        <li>Enter the deposit amount</li>
                        <li>Add an optional description</li>
                        <li>Click "Deposit Money"</li>
                    </ol>
                </div>
                
                <div class="alert alert-warning">
                    <h6><strong>Important Notes:</strong></h6>
                    <ul class="mb-0">
                        <li>Minimum deposit: <?php echo formatCurrency(getSetting($conn, 'min_deposit')); ?></li>
                        <li>Deposits are instant and reflected immediately</li>
                        <li>You will receive a reference number for each deposit</li>
                        <li>All deposits are recorded in transaction history</li>
                    </ul>
                </div>
                
                <?php if (mysqli_num_rows($accounts_result) > 0): ?>
                <div class="mt-3">
                    <h6><strong>Your Accounts:</strong></h6>
                    <?php 
                    mysqli_data_seek($accounts_result, 0);
                    while ($account = mysqli_fetch_assoc($accounts_result)): 
                    ?>
                        <div class="p-2 mb-2" style="background: #f8f9fa; border-radius: 5px;">
                            <strong><?php echo $account['account_number']; ?></strong><br>
                            <small><?php echo ucfirst($account['account_type']); ?> Account</small><br>
                            <strong class="text-success">Balance: <?php echo formatCurrency($account['balance']); ?></strong>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>