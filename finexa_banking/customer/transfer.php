<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Transfer Money";

// Process transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transfer'])) {
    $from_account_id = sanitize($conn, $_POST['from_account_id']);
    $to_account_number = sanitize($conn, $_POST['to_account_number']);
    $amount = sanitize($conn, $_POST['amount']);
    $description = sanitize($conn, $_POST['description']);
    
    $errors = array();
    
    // Validate amount
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount";
    }
    
    // Verify sender account
    $from_query = "SELECT * FROM accounts WHERE account_id = '$from_account_id' AND user_id = '$user_id' AND status = 'active'";
    $from_result = mysqli_query($conn, $from_query);
    
    if (mysqli_num_rows($from_result) == 0) {
        $errors[] = "Invalid sender account";
    } else {
        $from_account = mysqli_fetch_assoc($from_result);
        
        // Check balance
        $min_balance = getSetting($conn, 'min_balance');
        if ($from_account['balance'] < $amount) {
            $errors[] = "Insufficient balance! Available: " . formatCurrency($from_account['balance']);
        } elseif (($from_account['balance'] - $amount) < $min_balance) {
            $errors[] = "Transfer would result in balance below minimum required (" . formatCurrency($min_balance) . ")";
        }
    }
    
    // Verify receiver account
    $to_query = "SELECT * FROM accounts WHERE account_number = '$to_account_number' AND status = 'active'";
    $to_result = mysqli_query($conn, $to_query);
    
    if (mysqli_num_rows($to_result) == 0) {
        $errors[] = "Receiver account not found or inactive";
    } else {
        $to_account = mysqli_fetch_assoc($to_result);
        
        // Check if not transferring to same account
        if ($from_account_id == $to_account['account_id']) {
            $errors[] = "Cannot transfer to the same account";
        }
    }
    
    if (empty($errors)) {
        $from_new_balance = $from_account['balance'] - $amount;
        $to_new_balance = $to_account['balance'] + $amount;
        $reference = generateReferenceNumber();
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert sender transaction
            $txn1_query = "INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, reference_number, status) VALUES ('$from_account_id', 'transfer_out', '$amount', '$from_new_balance', 'Transfer to " . $to_account['account_number'] . " - $description', '$reference', 'completed')";
            mysqli_query($conn, $txn1_query);
            
            // Insert receiver transaction
            $txn2_query = "INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, reference_number, status) VALUES ('" . $to_account['account_id'] . "', 'transfer_in', '$amount', '$to_new_balance', 'Transfer from " . $from_account['account_number'] . " - $description', '$reference-R', 'completed')";
            mysqli_query($conn, $txn2_query);
            
            // Update sender balance
            $update1_query = "UPDATE accounts SET balance = '$from_new_balance' WHERE account_id = '$from_account_id'";
            mysqli_query($conn, $update1_query);
            
            // Update receiver balance
            $update2_query = "UPDATE accounts SET balance = '$to_new_balance' WHERE account_id = '" . $to_account['account_id'] . "'";
            mysqli_query($conn, $update2_query);
            
            // Create notifications
            createNotification($conn, $user_id, 'Transfer Sent', 'Amount ' . formatCurrency($amount) . ' transferred to ' . $to_account['account_number'], 'transaction');
            createNotification($conn, $to_account['user_id'], 'Transfer Received', 'Amount ' . formatCurrency($amount) . ' received from ' . $from_account['account_number'], 'transaction');
            
            // Log activity
            logActivity($conn, $user_id, 'transfer', 'transaction', mysqli_insert_id($conn), 'Transferred ' . formatCurrency($amount) . ' to ' . $to_account['account_number']);
            
            mysqli_commit($conn);
            
            setAlert('success', 'Transfer successful! Amount: ' . formatCurrency($amount) . ' | Reference: ' . $reference);
            header("Location: transfer.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            setAlert('error', 'Transfer failed. Please try again.');
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
    <h2 class="mb-4"><i class="fas fa-exchange-alt"></i> Transfer Money</h2>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="form-custom">
                <h4 class="mb-3">Transfer Form</h4>
                
                <?php if (mysqli_num_rows($accounts_result) > 0): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="from_account_id" class="form-label">From Account *</label>
                            <select class="form-select" id="from_account_id" name="from_account_id" required>
                                <option value="">Choose your account...</option>
                                <?php 
                                mysqli_data_seek($accounts_result, 0);
                                while ($account = mysqli_fetch_assoc($accounts_result)): 
                                ?>
                                    <option value="<?php echo $account['account_id']; ?>">
                                        <?php echo $account['account_number']; ?> - 
                                        Balance: <?php echo formatCurrency($account['balance']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="to_account_number" class="form-label">To Account Number *</label>
                            <input type="text" class="form-control" id="to_account_number" name="to_account_number" placeholder="Enter receiver account number" required>
                            <small class="text-muted">Enter the full account number of receiver</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (৳) *</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description/Note</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Purpose of transfer..."></textarea>
                        </div>
                        
                        <button type="submit" name="transfer" class="btn btn-info btn-lg w-100">
                            <i class="fas fa-paper-plane"></i> Transfer Money
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
                <h4 class="mb-3"><i class="fas fa-info-circle"></i> Transfer Information</h4>
                
                <div class="alert alert-info">
                    <h6><strong>How to Transfer:</strong></h6>
                    <ol class="mb-0">
                        <li>Select your account (from which to transfer)</li>
                        <li>Enter receiver's account number</li>
                        <li>Enter the transfer amount</li>
                        <li>Add description/note</li>
                        <li>Click "Transfer Money"</li>
                    </ol>
                </div>
                
                <div class="alert alert-warning">
                    <h6><strong>Important Notes:</strong></h6>
                    <ul class="mb-0">
                        <li>Transfers are instant within FINEXA Bank</li>
                        <li>Both accounts must be active</li>
                        <li>Minimum balance must be maintained</li>
                        <li>Double-check account number before transfer</li>
                        <li>You'll receive a reference number for tracking</li>
                        <li>Both sender and receiver get notifications</li>
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