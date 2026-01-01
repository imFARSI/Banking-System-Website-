<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/admin-dashboard.php");
    } else {
        header("Location: customer/dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = sanitize($conn, $_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $query = "SELECT * FROM users WHERE email = '$email' AND status = 'active'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                logActivity($conn, $user['user_id'], 'login', 'user', $user['user_id'], 'User logged in');
                
                if ($user['role'] == 'admin') {
                    header("Location: admin/admin-dashboard.php");
                } else {
                    header("Location: customer/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}

$page_title = "Welcome";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - FINEXA Banking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="landing-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 text-lg-start text-center mb-4 mb-lg-0">
                <h1><i class="fas fa-university"></i> FINEXA</h1>
                <p class="lead">Secure, Simple, and Smart Banking Solution</p>
                <p>Manage your accounts, transactions, loans, and more - all in one place.</p>
            </div>
            <div class="col-lg-6">
                <div class="auth-card">
                    <h2><i class="fas fa-sign-in-alt"></i> Login</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['alert_message'])): ?>
                        <?php showAlert(); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100 btn-custom">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                    
                    <div class="text-center border mt-3">
                        <p> <a href="register.php">Register here</a></p>
                        <hr>
                        <small class="text-muted">
                            <strong>Demo Admin Login:</strong><br>
                            Email: admin@finexa.com<br>
                            Password: admin123
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <h2 class="text-center mb-4">Our Banking Features</h2>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <i class="fas fa-wallet fa-3x text-primary mb-3"></i>
                <h4>Account Management</h4>
                <p>Create and manage multiple savings and current accounts with ease</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <i class="fas fa-exchange-alt fa-3x text-success mb-3"></i>
                <h4>Easy Transactions</h4>
                <p>Deposit, withdraw, and transfer money securely and instantly</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <i class="fas fa-hand-holding-usd fa-3x text-warning mb-3"></i>
                <h4>Loan Services</h4>
                <p>Apply for personal, home, car, and education loans online</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <i class="fas fa-history fa-3x text-info mb-3"></i>
                <h4>Transaction History</h4>
                <p>Track all transactions with detailed history and reports</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <i class="fas fa-headset fa-3x text-danger mb-3"></i>
                <h4>24/7 Support</h4>
                <p>Get help anytime with our customer support ticket system</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <i class="fas fa-bell fa-3x text-secondary mb-3"></i>
                <h4>Real-time Notifications</h4>
                <p>Stay updated with instant transaction and account alerts</p>
            </div>
        </div>
    </div>
</div>

<footer class="footer bg-dark text-white text-center py-3">
    <div class="container">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> FINEXA Banking System. All rights reserved.</p>
        <small>Secure Banking for Everyone</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>