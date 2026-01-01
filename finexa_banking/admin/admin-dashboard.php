<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = "Admin Dashboard";

// Handle loan approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_loan'])) {
    $loan_id = sanitize($conn, $_POST['loan_id']);
    $action = sanitize($conn, $_POST['action']);
    $remarks = sanitize($conn, $_POST['remarks']);
    
    if ($action == 'approve') {
        $loan_query = "SELECT * FROM loans WHERE loan_id = '$loan_id'";
        $loan_result = mysqli_query($conn, $loan_query);
        $loan = mysqli_fetch_assoc($loan_result);
        
        mysqli_begin_transaction($conn);
        try {
            // Update loan status
            mysqli_query($conn, "UPDATE loans SET status = 'approved', approved_by = '" . $_SESSION['user_id'] . "', approval_date = NOW(), remarks = '$remarks' WHERE loan_id = '$loan_id'");
            
            // Disburse amount to account
            mysqli_query($conn, "UPDATE accounts SET balance = balance + " . $loan['loan_amount'] . " WHERE account_id = '" . $loan['account_id'] . "'");
            
            // Create transaction
            $ref = generateReferenceNumber();
            $new_balance_query = mysqli_query($conn, "SELECT balance FROM accounts WHERE account_id = '" . $loan['account_id'] . "'");
            $new_balance = mysqli_fetch_assoc($new_balance_query)['balance'];
            mysqli_query($conn, "INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, reference_number) VALUES ('" . $loan['account_id'] . "', 'deposit', '" . $loan['loan_amount'] . "', '$new_balance', 'Loan disbursement - Loan ID: $loan_id', '$ref')");
            
            // Notify user
            createNotification($conn, $loan['user_id'], 'Loan Approved', 'Your loan application #' . $loan_id . ' has been approved!', 'loan');
            
            mysqli_commit($conn);
            setAlert('success', 'Loan approved and amount disbursed!');
        } catch (Exception $e) {
            mysqli_rollback($conn);
            setAlert('error', 'Failed to approve loan.');
        }
    } elseif ($action == 'reject') {
        mysqli_query($conn, "UPDATE loans SET status = 'rejected', approved_by = '" . $_SESSION['user_id'] . "', approval_date = NOW(), remarks = '$remarks' WHERE loan_id = '$loan_id'");
        
        $loan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM loans WHERE loan_id = '$loan_id'"));
        createNotification($conn, $loan['user_id'], 'Loan Rejected', 'Your loan application #' . $loan_id . ' has been rejected.', 'loan');
        
        setAlert('warning', 'Loan rejected.');
    }
    
    header("Location: admin-dashboard.php?tab=loans");
    exit();
}

// Handle ticket response
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_ticket'])) {
    $ticket_id = sanitize($conn, $_POST['ticket_id']);
    $message = sanitize($conn, $_POST['message']);
    $new_status = sanitize($conn, $_POST['status']);
    
    mysqli_query($conn, "INSERT INTO ticket_responses (ticket_id, user_id, message, response_type) VALUES ('$ticket_id', '" . $_SESSION['user_id'] . "', '$message', 'admin')");
    mysqli_query($conn, "UPDATE support_tickets SET status = '$new_status', assigned_to = '" . $_SESSION['user_id'] . "', updated_at = NOW() WHERE ticket_id = '$ticket_id'");
    
    $ticket = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM support_tickets WHERE ticket_id = '$ticket_id'"));
    createNotification($conn, $ticket['user_id'], 'Support Reply', 'Admin replied to your ticket #' . $ticket_id, 'ticket');
    
    setAlert('success', 'Reply sent!');
    header("Location: admin-dashboard.php?tab=tickets");
    exit();
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    foreach ($_POST as $key => $value) {
        if ($key != 'update_settings' && strpos($key, 'setting_') === 0) {
            $setting_key = str_replace('setting_', '', $key);
            $setting_value = sanitize($conn, $value);
            mysqli_query($conn, "UPDATE settings SET setting_value = '$setting_value' WHERE setting_key = '$setting_key'");
        }
    }
    setAlert('success', 'Settings updated successfully!');
    header("Location: admin-dashboard.php?tab=settings");
    exit();
}

// Stats
$total_users = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role = 'customer'"));
$total_accounts = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM accounts"));
$pending_loans = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM loans WHERE status = 'pending'"));
$open_tickets = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM support_tickets WHERE status IN ('open', 'in_progress')"));

$today_deposits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE transaction_type = 'deposit' AND DATE(transaction_date) = CURDATE()"))['total'];
$today_withdrawals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE transaction_type = 'withdraw' AND DATE(transaction_date) = CURDATE()"))['total'];

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card" style="padding: 20px;">
                <h4><?php echo $total_users; ?></h4>
                <p><i class="fas fa-users"></i> Users</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px;">
                <h4><?php echo $total_accounts; ?></h4>
                <p><i class="fas fa-wallet"></i> Accounts</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px;">
                <h4><?php echo formatCurrency($today_deposits); ?></h4>
                <p><i class="fas fa-arrow-down"></i> Today Deposits</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); padding: 20px;">
                <h4><?php echo formatCurrency($today_withdrawals); ?></h4>
                <p><i class="fas fa-arrow-up"></i> Today Withdrawals</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding: 20px;">
                <h4><?php echo $pending_loans; ?></h4>
                <p><i class="fas fa-hourglass-half"></i> Pending Loans</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); padding: 20px;">
                <h4><?php echo $open_tickets; ?></h4>
                <p><i class="fas fa-ticket-alt"></i> Open Tickets</p>
            </div>
        </div>
    </div>
    
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'users' ? 'active' : ''; ?>" href="?tab=users">
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'accounts' ? 'active' : ''; ?>" href="?tab=accounts">
                <i class="fas fa-wallet"></i> Accounts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'loans' ? 'active' : ''; ?>" href="?tab=loans">
                <i class="fas fa-hand-holding-usd"></i> Loans <?php if ($pending_loans > 0): ?><span class="badge bg-danger"><?php echo $pending_loans; ?></span><?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'tickets' ? 'active' : ''; ?>" href="?tab=tickets">
                <i class="fas fa-ticket-alt"></i> Support <?php if ($open_tickets > 0): ?><span class="badge bg-warning"><?php echo $open_tickets; ?></span><?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'settings' ? 'active' : ''; ?>" href="?tab=settings">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'logs' ? 'active' : ''; ?>" href="?tab=logs">
                <i class="fas fa-list"></i> Activity Logs
            </a>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content">
        
        <?php if ($active_tab == 'dashboard'): ?>
            <!-- Dashboard Overview -->
            <div class="dashboard-card">
                <h5><i class="fas fa-chart-bar"></i> Recent Activity</h5>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr><th>Time</th><th>User</th><th>Action</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $logs = mysqli_query($conn, "SELECT al.*, u.name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 15");
                            while ($log = mysqli_fetch_assoc($logs)):
                            ?>
                                <tr>
                                    <td><?php echo formatDate($log['created_at']); ?></td>
                                    <td><?php echo $log['name'] ?? 'System'; ?></td>
                                    <td><span class="badge bg-primary"><?php echo $log['action_type']; ?></span></td>
                                    <td><?php echo $log['description']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <?php elseif ($active_tab == 'users'): ?>
            <!-- Users Management -->
            <div class="dashboard-card">
                <h5><i class="fas fa-users"></i> All Users</h5>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
                            while ($user = mysqli_fetch_assoc($users)):
                            ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo $user['name']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo $user['phone']; ?></td>
                                    <td><span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <?php elseif ($active_tab == 'accounts'): ?>
            <!-- Accounts Management -->
            <div class="dashboard-card">
                <h5><i class="fas fa-wallet"></i> All Bank Accounts</h5>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr><th>Account Number</th><th>User</th><th>Type</th><th>Balance</th><th>Status</th><th>Created</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $accounts = mysqli_query($conn, "SELECT a.*, u.name FROM accounts a JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC");
                            while ($acc = mysqli_fetch_assoc($accounts)):
                            ?>
                                <tr>
                                    <td><strong><?php echo $acc['account_number']; ?></strong></td>
                                    <td><?php echo $acc['name']; ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($acc['account_type']); ?></span></td>
                                    <td><strong><?php echo formatCurrency($acc['balance']); ?></strong></td>
                                    <td><span class="badge bg-<?php echo $acc['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($acc['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($acc['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <?php elseif ($active_tab == 'loans'): ?>
            <!-- Loans Management -->
            <div class="dashboard-card">
                <h5><i class="fas fa-hand-holding-usd"></i> Loan Applications</h5>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr><th>Loan ID</th><th>User</th><th>Type</th><th>Amount</th><th>EMI</th><th>Tenure</th><th>Status</th><th>Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $loans = mysqli_query($conn, "SELECT l.*, u.name FROM loans l JOIN users u ON l.user_id = u.user_id ORDER BY l.application_date DESC");
                            while ($loan = mysqli_fetch_assoc($loans)):
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $loan['loan_id']; ?></strong></td>
                                    <td><?php echo $loan['name']; ?></td>
                                    <td><?php echo ucfirst($loan['loan_type']); ?></td>
                                    <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                    <td><?php echo formatCurrency($loan['monthly_emi']); ?></td>
                                    <td><?php echo $loan['tenure_months']; ?>m</td>
                                    <td><span class="badge bg-<?php echo $loan['status'] == 'pending' ? 'warning' : ($loan['status'] == 'approved' ? 'success' : 'secondary'); ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($loan['application_date'])); ?></td>
                                    <td>
                                        <?php if ($loan['status'] == 'pending'): ?>
                                            <button class="btn btn-sm btn-success" onclick="document.getElementById('loanModal<?php echo $loan['loan_id']; ?>').style.display='block'">Approve</button>
                                            <button class="btn btn-sm btn-danger" onclick="document.getElementById('rejectModal<?php echo $loan['loan_id']; ?>').style.display='block'">Reject</button>
                                            
                                            <!-- Approve Modal -->
                                            <div id="loanModal<?php echo $loan['loan_id']; ?>" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
                                                <div style="background:white; margin:10% auto; padding:20px; width:50%; border-radius:10px;">
                                                    <h5>Approve Loan #<?php echo $loan['loan_id']; ?></h5>
                                                    <form method="POST">
                                                        <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <div class="mb-3">
                                                            <label>Remarks:</label>
                                                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                                                        </div>
                                                        <button type="submit" name="update_loan" class="btn btn-success">Approve & Disburse</button>
                                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('loanModal<?php echo $loan['loan_id']; ?>').style.display='none'">Cancel</button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <!-- Reject Modal -->
                                            <div id="rejectModal<?php echo $loan['loan_id']; ?>" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
                                                <div style="background:white; margin:10% auto; padding:20px; width:50%; border-radius:10px;">
                                                    <h5>Reject Loan #<?php echo $loan['loan_id']; ?></h5>
                                                    <form method="POST">
                                                        <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <div class="mb-3">
                                                            <label>Rejection Reason:</label>
                                                            <textarea class="form-control" name="remarks" rows="2" required></textarea>
                                                        </div>
                                                        <button type="submit" name="update_loan" class="btn btn-danger">Reject</button>
                                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejectModal<?php echo $loan['loan_id']; ?>').style.display='none'">Cancel</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <?php elseif ($active_tab == 'tickets'): ?>
            <!-- Support Tickets -->
            <div class="dashboard-card">
                <h5><i class="fas fa-ticket-alt"></i> Support Tickets</h5>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr><th>Ticket ID</th><th>User</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Created</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $tickets = mysqli_query($conn, "SELECT st.*, u.name FROM support_tickets st JOIN users u ON st.user_id = u.user_id ORDER BY st.created_at DESC");
                            while ($ticket = mysqli_fetch_assoc($tickets)):
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $ticket['ticket_id']; ?></strong></td>
                                    <td><?php echo $ticket['name']; ?></td>
                                    <td><?php echo $ticket['subject']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($ticket['category']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $ticket['priority'] == 'high' ? 'danger' : 'warning'; ?>"><?php echo ucfirst($ticket['priority']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $ticket['status'] == 'open' ? 'warning' : 'info'; ?>"><?php echo ucfirst($ticket['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($ticket['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="document.getElementById('ticketModal<?php echo $ticket['ticket_id']; ?>').style.display='block'">Reply</button>
                                        
                                        <!-- Reply Modal -->
                                        <div id="ticketModal<?php echo $ticket['ticket_id']; ?>" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
                                            <div style="background:white; margin:5% auto; padding:20px; width:70%; border-radius:10px; max-height:80%; overflow-y:auto;">
                                                <h5>Ticket #<?php echo $ticket['ticket_id']; ?>: <?php echo $ticket['subject']; ?></h5>
                                                <p><strong>Description:</strong> <?php echo nl2br($ticket['description']); ?></p>
                                                <hr>
                                                <h6>Responses:</h6>
                                                <?php
                                                $responses = mysqli_query($conn, "SELECT tr.*, u.name FROM ticket_responses tr JOIN users u ON tr.user_id = u.user_id WHERE tr.ticket_id = '" . $ticket['ticket_id'] . "' ORDER BY tr.created_at ASC");
                                                while ($resp = mysqli_fetch_assoc($responses)):
                                                ?>
                                                    <div class="p-2 mb-2" style="background:#f8f9fa; border-radius:5px;">
                                                        <strong><?php echo $resp['name']; ?>:</strong> <?php echo $resp['message']; ?>
                                                        <br><small><?php echo formatDate($resp['created_at']); ?></small>
                                                    </div>
                                                <?php endwhile; ?>
                                                
                                                <form method="POST">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                    <div class="mb-3">
                                                        <label>Your Reply:</label>
                                                        <textarea class="form-control" name="message" rows="3" required></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Update Status:</label>
                                                        <select class="form-select" name="status">
                                                            <option value="in_progress">In Progress</option>
                                                            <option value="resolved">Resolved</option>
                                                            <option value="closed">Closed</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" name="reply_ticket" class="btn btn-primary">Send Reply</button>
                                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('ticketModal<?php echo $ticket['ticket_id']; ?>').style.display='none'">Close</button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <?php elseif ($active_tab == 'settings'): ?>
            <!-- System Settings -->
            <div class="dashboard-card">
                <h5><i class="fas fa-cog"></i> System Settings</h5>
                <form method="POST">
                    <?php
                    $settings = mysqli_query($conn, "SELECT * FROM settings ORDER BY setting_id");
                    while ($setting = mysqli_fetch_assoc($settings)):
                    ?>
                        <div class="mb-3">
                            <label class="form-label"><strong><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>:</strong></label>
                            <input type="text" class="form-control" name="setting_<?php echo $setting['setting_key']; ?>" value="<?php echo $setting['setting_value']; ?>">
                            <small class="text-muted"><?php echo $setting['description']; ?></small>
                        </div>
                    <?php endwhile; ?>
                    <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        
        <?php elseif ($active_tab == 'logs'): ?>
            <!-- Activity Logs -->
            <div class="dashboard-card">
                <h5><i class="fas fa-list"></i> Activity Logs</h5>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr><th>Date & Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP Address</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $logs = mysqli_query($conn, "SELECT al.*, u.name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 100");
                            while ($log = mysqli_fetch_assoc($logs)):
                            ?>
                                <tr>
                                    <td><?php echo formatDate($log['created_at']); ?></td>
                                    <td><?php echo $log['name'] ?? 'System'; ?></td>
                                    <td><span class="badge bg-primary"><?php echo $log['action_type']; ?></span></td>
                                    <td><?php echo $log['entity_type']; ?></td>
                                    <td><?php echo $log['description']; ?></td>
                                    <td><small><?php echo $log['ip_address']; ?></small></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include '../includes/footer.php'; ?>