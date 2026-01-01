<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Loans";

// Apply for loan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_loan'])) {
    $loan_type = sanitize($conn, $_POST['loan_type']);
    $loan_amount = sanitize($conn, $_POST['loan_amount']);
    $tenure_months = sanitize($conn, $_POST['tenure_months']);
    $account_id = sanitize($conn, $_POST['account_id']);
    
    $errors = array();
    
    if (empty($loan_amount) || $loan_amount <= 0) {
        $errors[] = "Please enter a valid loan amount";
    }
    
    $max_loan = getSetting($conn, 'max_loan_amount');
    if ($loan_amount > $max_loan) {
        $errors[] = "Maximum loan amount is " . formatCurrency($max_loan);
    }
    
    if (empty($tenure_months) || $tenure_months < 6 || $tenure_months > 360) {
        $errors[] = "Tenure must be between 6 and 360 months";
    }
    
    // Get interest rate
    $interest_key = 'loan_interest_' . $loan_type;
    $interest_rate = getSetting($conn, $interest_key);
    
    if (empty($errors)) {
        // Calculate EMI
        $monthly_emi = calculateEMI($loan_amount, $interest_rate, $tenure_months);
        $total_payable = $monthly_emi * $tenure_months;
        
        // Insert loan application
        $query = "INSERT INTO loans (user_id, account_id, loan_type, loan_amount, interest_rate, tenure_months, monthly_emi, total_payable, status) VALUES ('$user_id', '$account_id', '$loan_type', '$loan_amount', '$interest_rate', '$tenure_months', '$monthly_emi', '$total_payable', 'pending')";
        
        if (mysqli_query($conn, $query)) {
            $loan_id = mysqli_insert_id($conn);
            
            createNotification($conn, $user_id, 'Loan Application Submitted', 'Your ' . $loan_type . ' loan application for ' . formatCurrency($loan_amount) . ' has been submitted successfully.', 'loan');
            
            logActivity($conn, $user_id, 'apply_loan', 'loan', $loan_id, 'Applied for ' . $loan_type . ' loan of ' . formatCurrency($loan_amount));
            
            setAlert('success', 'Loan application submitted successfully! Loan ID: ' . $loan_id);
            header("Location: loans.php");
            exit();
        } else {
            setAlert('error', 'Failed to submit loan application.');
        }
    } else {
        foreach ($errors as $error) {
            setAlert('error', $error);
            break;
        }
    }
}

// Pay EMI
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_emi'])) {
    $loan_id = sanitize($conn, $_POST['loan_id']);
    
    // Get loan details
    $loan_query = "SELECT l.*, a.balance FROM loans l JOIN accounts a ON l.account_id = a.account_id WHERE l.loan_id = '$loan_id' AND l.user_id = '$user_id' AND l.status IN ('approved', 'disbursed')";
    $loan_result = mysqli_query($conn, $loan_query);
    
    if (mysqli_num_rows($loan_result) == 1) {
        $loan = mysqli_fetch_assoc($loan_result);
        $emi_amount = $loan['monthly_emi'];
        
        if ($loan['balance'] >= $emi_amount) {
            $new_balance = $loan['balance'] - $emi_amount;
            $new_amount_paid = $loan['amount_paid'] + $emi_amount;
            
            mysqli_begin_transaction($conn);
            
            try {
                // Insert EMI payment record
                mysqli_query($conn, "INSERT INTO loan_payments (loan_id, payment_amount, payment_method, status) VALUES ('$loan_id', '$emi_amount', 'account_debit', 'completed')");
                
                // Update loan amount paid
                mysqli_query($conn, "UPDATE loans SET amount_paid = '$new_amount_paid' WHERE loan_id = '$loan_id'");
                
                // Check if loan is fully paid
                if ($new_amount_paid >= $loan['total_payable']) {
                    mysqli_query($conn, "UPDATE loans SET status = 'closed' WHERE loan_id = '$loan_id'");
                }
                
                // Deduct from account
                mysqli_query($conn, "UPDATE accounts SET balance = '$new_balance' WHERE account_id = '" . $loan['account_id'] . "'");
                
                // Insert transaction
                $ref = generateReferenceNumber();
                mysqli_query($conn, "INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, reference_number) VALUES ('" . $loan['account_id'] . "', 'withdraw', '$emi_amount', '$new_balance', 'Loan EMI Payment - Loan ID: $loan_id', '$ref')");
                
                createNotification($conn, $user_id, 'EMI Paid', 'EMI payment of ' . formatCurrency($emi_amount) . ' for Loan ID ' . $loan_id . ' successful.', 'loan');
                
                mysqli_commit($conn);
                setAlert('success', 'EMI payment successful! Amount: ' . formatCurrency($emi_amount));
                header("Location: loans.php");
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                setAlert('error', 'EMI payment failed.');
            }
        } else {
            setAlert('error', 'Insufficient balance for EMI payment.');
        }
    }
}

// Get user accounts
$accounts_query = "SELECT * FROM accounts WHERE user_id = '$user_id' AND status = 'active'";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get user loans
$loans_query = "SELECT * FROM loans WHERE user_id = '$user_id' ORDER BY application_date DESC";
$loans_result = mysqli_query($conn, $loans_query);

// Get interest rates
$rates = array(
    'personal' => getSetting($conn, 'loan_interest_personal'),
    'home' => getSetting($conn, 'loan_interest_home'),
    'car' => getSetting($conn, 'loan_interest_car'),
    'education' => getSetting($conn, 'loan_interest_education')
);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-hand-holding-usd"></i> Loan Management</h2>
    
    <!-- Apply for Loan -->
    <div class="form-custom mb-4">
        <h4 class="mb-3"><i class="fas fa-file-invoice-dollar"></i> Apply for New Loan</h4>
        
        <?php if (mysqli_num_rows($accounts_result) > 0): ?>
            <form method="POST" action="" id="loanForm">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="loan_type" class="form-label">Loan Type *</label>
                        <select class="form-select" id="loan_type" name="loan_type" required>
                            <option value="personal">Personal Loan (<?php echo $rates['personal']; ?>%)</option>
                            <option value="home">Home Loan (<?php echo $rates['home']; ?>%)</option>
                            <option value="car">Car Loan (<?php echo $rates['car']; ?>%)</option>
                            <option value="education">Education Loan (<?php echo $rates['education']; ?>%)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="loan_amount" class="form-label">Loan Amount (৳) *</label>
                        <input type="number" class="form-control" id="loan_amount" name="loan_amount" step="1000" min="10000" required>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="tenure_months" class="form-label">Tenure (Months) *</label>
                        <input type="number" class="form-control" id="tenure_months" name="tenure_months" min="6" max="360" required>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="account_id" class="form-label">Disbursement Account *</label>
                        <select class="form-select" id="account_id" name="account_id" required>
                            <?php 
                            mysqli_data_seek($accounts_result, 0);
                            while ($acc = mysqli_fetch_assoc($accounts_result)): 
                            ?>
                                <option value="<?php echo $acc['account_id']; ?>"><?php echo $acc['account_number']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <!-- EMI Calculator Display -->
                <div class="emi-result-box">
                    <h5><i class="fas fa-calculator"></i> EMI Calculation</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Monthly EMI:</strong></p>
                            <p class="emi-value" id="emi_display">৳ 0.00</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Total Interest:</strong></p>
                            <p class="emi-value" id="interest_display">৳ 0.00</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Total Payable:</strong></p>
                            <p class="emi-value" id="total_display">৳ 0.00</p>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="apply_loan" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-paper-plane"></i> Submit Loan Application
                </button>
            </form>
            
            <script>
            // EMI Calculator without external JS
            document.addEventListener('DOMContentLoaded', function() {
                const amountInput = document.getElementById('loan_amount');
                const tenureInput = document.getElementById('tenure_months');
                const typeSelect = document.getElementById('loan_type');
                
                const rates = {
                    'personal': <?php echo $rates['personal']; ?>,
                    'home': <?php echo $rates['home']; ?>,
                    'car': <?php echo $rates['car']; ?>,
                    'education': <?php echo $rates['education']; ?>
                };
                
                function calculateAndDisplay() {
                    const amount = parseFloat(amountInput.value) || 0;
                    const tenure = parseInt(tenureInput.value) || 0;
                    const type = typeSelect.value;
                    const rate = rates[type];
                    
                    if (amount > 0 && tenure > 0) {
                        const monthlyRate = (rate / 12) / 100;
                        const emi = (amount * monthlyRate * Math.pow(1 + monthlyRate, tenure)) / (Math.pow(1 + monthlyRate, tenure) - 1);
                        const total = emi * tenure;
                        const interest = total - amount;
                        
                        document.getElementById('emi_display').textContent = '৳ ' + emi.toFixed(2);
                        document.getElementById('interest_display').textContent = '৳ ' + interest.toFixed(2);
                        document.getElementById('total_display').textContent = '৳ ' + total.toFixed(2);
                    }
                }
                
                amountInput.addEventListener('input', calculateAndDisplay);
                tenureInput.addEventListener('input', calculateAndDisplay);
                typeSelect.addEventListener('change', calculateAndDisplay);
            });
            </script>
            
        <?php else: ?>
            <div class="alert alert-warning">
                You need an active account to apply for loan. <a href="account-manage.php">Create account</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- My Loans -->
    <div class="dashboard-card">
        <h4 class="mb-3"><i class="fas fa-list"></i> My Loans</h4>
        
        <?php if (mysqli_num_rows($loans_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Interest</th>
                            <th>Tenure</th>
                            <th>Monthly EMI</th>
                            <th>Amount Paid</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loan = mysqli_fetch_assoc($loans_result)): ?>
                            <tr>
                                <td><strong>#<?php echo $loan['loan_id']; ?></strong></td>
                                <td><?php echo ucfirst($loan['loan_type']); ?></td>
                                <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td><?php echo $loan['tenure_months']; ?> months</td>
                                <td><?php echo formatCurrency($loan['monthly_emi']); ?></td>
                                <td><?php echo formatCurrency($loan['amount_paid']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'secondary';
                                    if ($loan['status'] == 'approved' || $loan['status'] == 'disbursed') $badge_class = 'success';
                                    if ($loan['status'] == 'pending') $badge_class = 'warning';
                                    if ($loan['status'] == 'rejected') $badge_class = 'danger';
                                    if ($loan['status'] == 'closed') $badge_class = 'info';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($loan['status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($loan['status'] == 'approved' || $loan['status'] == 'disbursed'): ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                                            <button type="submit" name="pay_emi" class="btn btn-sm btn-success" onclick="return confirm('Pay EMI of <?php echo formatCurrency($loan['monthly_emi']); ?>?')">
                                                <i class="fas fa-money-bill"></i> Pay EMI
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No loans yet. Apply for your first loan above!</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>