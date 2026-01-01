<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Transaction History";

// Get user accounts for filter
$accounts_query = "SELECT * FROM accounts WHERE user_id = '$user_id' ORDER BY account_number";
$accounts_result = mysqli_query($conn, $accounts_query);

// Build query with filters
$where_conditions = array();
$where_conditions[] = "a.user_id = '$user_id'";

// Filter by account
$selected_account = '';
if (isset($_GET['account']) && !empty($_GET['account'])) {
    $selected_account = sanitize($conn, $_GET['account']);
    $where_conditions[] = "t.account_id = '$selected_account'";
}

// Filter by transaction type
$selected_type = '';
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $selected_type = sanitize($conn, $_GET['type']);
    $where_conditions[] = "t.transaction_type = '$selected_type'";
}

// Filter by date range
$from_date = '';
$to_date = '';
if (isset($_GET['from_date']) && !empty($_GET['from_date'])) {
    $from_date = sanitize($conn, $_GET['from_date']);
    $where_conditions[] = "DATE(t.transaction_date) >= '$from_date'";
}
if (isset($_GET['to_date']) && !empty($_GET['to_date'])) {
    $to_date = sanitize($conn, $_GET['to_date']);
    $where_conditions[] = "DATE(t.transaction_date) <= '$to_date'";
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination
$records_per_page = 20;
$page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page_num - 1) * $records_per_page;

// Get total records
$count_query = "SELECT COUNT(*) as total FROM transactions t JOIN accounts a ON t.account_id = a.account_id WHERE $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get transactions
$transactions_query = "SELECT t.*, a.account_number, a.account_type FROM transactions t JOIN accounts a ON t.account_id = a.account_id WHERE $where_clause ORDER BY t.transaction_date DESC LIMIT $offset, $records_per_page";
$transactions_result = mysqli_query($conn, $transactions_query);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-history"></i> Transaction History</h2>
    
    <!-- Filters -->
    <div class="dashboard-card mb-4">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Filter Transactions</h5>
        
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="account" class="form-label">Account</label>
                    <select class="form-select" id="account" name="account">
                        <option value="">All Accounts</option>
                        <?php 
                        mysqli_data_seek($accounts_result, 0);
                        while ($account = mysqli_fetch_assoc($accounts_result)): 
                        ?>
                            <option value="<?php echo $account['account_id']; ?>" <?php echo $selected_account == $account['account_id'] ? 'selected' : ''; ?>>
                                <?php echo $account['account_number']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="type" class="form-label">Transaction Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <option value="deposit" <?php echo $selected_type == 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                        <option value="withdraw" <?php echo $selected_type == 'withdraw' ? 'selected' : ''; ?>>Withdraw</option>
                        <option value="transfer_in" <?php echo $selected_type == 'transfer_in' ? 'selected' : ''; ?>>Transfer In</option>
                        <option value="transfer_out" <?php echo $selected_type == 'transfer_out' ? 'selected' : ''; ?>>Transfer Out</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo $from_date; ?>">
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo $to_date; ?>">
                </div>
                
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
            
            <?php if (!empty($selected_account) || !empty($selected_type) || !empty($from_date) || !empty($to_date)): ?>
                <a href="transactions.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Transactions Table -->
    <div class="dashboard-card">
        <h5 class="mb-3">
            <i class="fas fa-list"></i> Transactions 
            <span class="badge bg-primary"><?php echo $total_records; ?> Total</span>
        </h5>
        
        <?php if (mysqli_num_rows($transactions_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($txn = mysqli_fetch_assoc($transactions_result)): ?>
                            <tr>
                                <td><?php echo formatDate($txn['transaction_date']); ?></td>
                                <td>
                                    <strong><?php echo $txn['account_number']; ?></strong><br>
                                    <small><?php echo ucfirst($txn['account_type']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type_class = 'secondary';
                                    if ($txn['transaction_type'] == 'deposit') $type_class = 'success';
                                    if ($txn['transaction_type'] == 'withdraw') $type_class = 'warning';
                                    if ($txn['transaction_type'] == 'transfer_in') $type_class = 'info';
                                    if ($txn['transaction_type'] == 'transfer_out') $type_class = 'primary';
                                    ?>
                                    <span class="badge bg-<?php echo $type_class; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($txn['transaction_type'])); ?>
                                    </span>
                                </td>
                                <td class="<?php echo in_array($txn['transaction_type'], ['deposit', 'transfer_in']) ? 'transaction-credit' : 'transaction-debit'; ?>">
                                    <strong>
                                        <?php echo in_array($txn['transaction_type'], ['deposit', 'transfer_in']) ? '+' : '-'; ?>
                                        <?php echo formatCurrency($txn['amount']); ?>
                                    </strong>
                                </td>
                                <td><strong><?php echo formatCurrency($txn['balance_after']); ?></strong></td>
                                <td>
                                    <small><?php echo !empty($txn['description']) ? $txn['description'] : '-'; ?></small>
                                </td>
                                <td><small class="text-muted"><?php echo $txn['reference_number']; ?></small></td>
                                <td>
                                    <?php if ($txn['status'] == 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($txn['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page_num > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page_num - 1); ?>&account=<?php echo $selected_account; ?>&type=<?php echo $selected_type; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page_num ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&account=<?php echo $selected_account; ?>&type=<?php echo $selected_type; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page_num < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page_num + 1); ?>&account=<?php echo $selected_account; ?>&type=<?php echo $selected_type; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No transactions found. 
                <?php if (!empty($selected_account) || !empty($selected_type) || !empty($from_date) || !empty($to_date)): ?>
                    Try changing your filters.
                <?php else: ?>
                    Start by making a <a href="deposit.php" class="alert-link">deposit</a>.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>