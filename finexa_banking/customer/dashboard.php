<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Dashboard";

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notif_id = sanitize($conn, $_GET['mark_read']);
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = '$notif_id' AND user_id = '$user_id'");
    header("Location: dashboard.php");
    exit();
}

// Get accounts
$accounts_query = "SELECT * FROM accounts WHERE user_id = '$user_id' ORDER BY created_at DESC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get recent transactions
$transactions_query = "SELECT t.*, a.account_number, a.account_type FROM transactions t JOIN accounts a ON t.account_id = a.account_id WHERE a.user_id = '$user_id' ORDER BY t.transaction_date DESC LIMIT 10";
$transactions_result = mysqli_query($conn, $transactions_query);

// Get active loans
$loans_query = "SELECT * FROM loans WHERE user_id = '$user_id' AND status IN ('approved', 'disbursed') ORDER BY application_date DESC";
$loans_result = mysqli_query($conn, $loans_query);

// Get notifications
$notifications_query = "SELECT * FROM notifications WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 5";
$notifications_result = mysqli_query($conn, $notifications_query);

// Calculate total balance
$total_balance = 0;
$account_count = 0;
if (mysqli_num_rows($accounts_result) > 0) {
    mysqli_data_seek($accounts_result, 0);
    while ($acc = mysqli_fetch_assoc($accounts_result)) {
        if ($acc['status'] == 'active') {
            $total_balance += $acc['balance'];
            $account_count++;
        }
    }
}

// Count unread notifications
$unread_count = 0;
if (mysqli_num_rows($notifications_result) > 0) {
    mysqli_data_seek($notifications_result, 0);
    while ($notif = mysqli_fetch_assoc($notifications_result)) {
        if (!$notif['is_read']) $unread_count++;
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">
        <i class="fas fa-home"></i> Welcome back, <?php echo $_SESSION['name']; ?>!
    </h2>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h3><?php echo formatCurrency($total_balance); ?></h3>
                <p><i class="fas fa-wallet"></i> Total Balance</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3><?php echo $account_count; ?></h3>
                <p><i class="fas fa-credit-card"></i> Active Accounts</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3><?php echo mysqli_num_rows($loans_result); ?></h3>
                <p><i class="fas fa-hand-holding-usd"></i> Active Loans</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3><?php echo $unread_count; ?></h3>
                <p><i class="fas fa-bell"></i> New Notifications</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="dashboard-card mb-4">
        <h4 class="mb-3"><i class="fas fa-bolt"></i> Quick Actions</h4>
        <div class="row">
            <div class="col-md-3 mb-2">
                <a href="deposit.php" class="btn btn-success w-100">
                    <i class="fas fa-plus-circle"></i> Deposit Money
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="withdraw.php" class="btn btn-warning w-100">
                    <i class="fas fa-minus-circle"></i> Withdraw Money
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="transfer.php" class="btn btn-info w-100">
                    <i class="fas fa-exchange-alt"></i> Transfer Money
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="loans.php" class="btn btn-primary w-100">
                    <i class="fas fa-hand-holding-usd"></i> Apply for Loan
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- My Accounts -->
        <div class="col-lg-6 mb-4">
            <div class="dashboard-card">
                <h4 class="mb-3"><i class="fas fa-wallet"></i> My Accounts</h4>
                
                <?php 
                mysqli_data_seek($accounts_result, 0);
                if (mysqli_num_rows($accounts_result) > 0): 
                ?>
                    <?php while ($account = mysqli_fetch_assoc($accounts_result)): ?>
                        <div class="account-card mb-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small class="text-uppercase"><?php echo $account['account_type']; ?> Account</small>
                                    <div class="account-number"><?php echo $account['account_number']; ?></div>
                                    <div class="account-balance"><?php echo formatCurrency($account['balance']); ?></div>
                                </div>
                                <span class="badge bg-light text-dark"><?php echo ucfirst($account['status']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <a href="account-manage.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus"></i> Create New Account
                    </a>
                <?php else: ?>
                    <div class="alert alert-info">
                        No accounts found. <a href="account-manage.php" class="alert-link">Create your first account</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="col-lg-6 mb-4">
            <div class="dashboard-card">
                <h4 class="mb-3"><i class="fas fa-bell"></i> Recent Notifications</h4>
                
                <?php 
                mysqli_data_seek($notifications_result, 0);
                if (mysqli_num_rows($notifications_result) > 0): 
                ?>
                    <?php while ($notif = mysqli_fetch_assoc($notifications_result)): ?>
                        <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo $notif['title']; ?></strong>
                                    <p class="mb-0"><?php echo $notif['message']; ?></p>
                                    <div class="notification-time"><?php echo formatDate($notif['created_at']); ?></div>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-primary">Mark Read</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">No notifications yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="dashboard-card">
        <h4 class="mb-3"><i class="fas fa-history"></i> Recent Transactions</h4>
        
        <?php if (mysqli_num_rows($transactions_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($txn = mysqli_fetch_assoc($transactions_result)): ?>
                            <tr>
                                <td><?php echo formatDate($txn['transaction_date']); ?></td>
                                <td><?php echo $txn['account_number']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo str_replace('_', ' ', ucfirst($txn['transaction_type'])); ?></span></td>
                                <td class="<?php echo in_array($txn['transaction_type'], ['deposit', 'transfer_in']) ? 'transaction-credit' : 'transaction-debit'; ?>">
                                    <?php echo in_array($txn['transaction_type'], ['deposit', 'transfer_in']) ? '+' : '-'; ?>
                                    <?php echo formatCurrency($txn['amount']); ?>
                                </td>
                                <td><?php echo formatCurrency($txn['balance_after']); ?></td>
                                <td><small><?php echo $txn['reference_number']; ?></small></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <a href="transactions.php" class="btn btn-outline-primary">View All Transactions <i class="fas fa-arrow-right"></i></a>
        <?php else: ?>
            <div class="alert alert-info">No transactions yet. Start by making a deposit!</div>
        <?php endif; ?>
    </div>
    
    <!-- Active Loans -->
    <?php if (mysqli_num_rows($loans_result) > 0): ?>
    <div class="dashboard-card mt-4">
        <h4 class="mb-3"><i class="fas fa-hand-holding-usd"></i> Active Loans</h4>
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Loan Type</th>
                        <th>Amount</th>
                        <th>Monthly EMI</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($loans_result, 0);
                    while ($loan = mysqli_fetch_assoc($loans_result)): 
                    ?>
                        <tr>
                            <td><?php echo ucfirst($loan['loan_type']); ?> Loan</td>
                            <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                            <td><?php echo formatCurrency($loan['monthly_emi']); ?></td>
                            <td><span class="badge bg-success"><?php echo ucfirst($loan['status']); ?></span></td>
                            <td><a href="loans.php" class="btn btn-sm btn-primary">View Details</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>