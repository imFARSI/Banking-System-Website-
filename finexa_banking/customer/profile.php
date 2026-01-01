<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "My Profile";

// Get user details
$user_query = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($conn, $_POST['name']);
    $phone = sanitize($conn, $_POST['phone']);
    $date_of_birth = sanitize($conn, $_POST['date_of_birth']);
    $address = sanitize($conn, $_POST['address']);
    
    if (!empty($name)) {
        $query = "UPDATE users SET name = '$name', phone = '$phone', date_of_birth = '$date_of_birth', address = '$address' WHERE user_id = '$user_id'";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['name'] = $name;
            createNotification($conn, $user_id, 'Profile Updated', 'Your profile information has been updated successfully.', 'system');
            logActivity($conn, $user_id, 'update_profile', 'user', $user_id, 'Updated profile information');
            
            setAlert('success', 'Profile updated successfully!');
            header("Location: profile.php");
            exit();
        } else {
            setAlert('error', 'Failed to update profile.');
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = array();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect";
    }
    
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = '$hashed_password' WHERE user_id = '$user_id'";
        
        if (mysqli_query($conn, $query)) {
            createNotification($conn, $user_id, 'Password Changed', 'Your password has been changed successfully.', 'system');
            logActivity($conn, $user_id, 'change_password', 'user', $user_id, 'Changed password');
            
            setAlert('success', 'Password changed successfully!');
            header("Location: profile.php");
            exit();
        } else {
            setAlert('error', 'Failed to change password.');
        }
    } else {
        foreach ($errors as $error) {
            setAlert('error', $error);
            break;
        }
    }
}

// Reload user data
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-user"></i> My Profile</h2>
    
    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-6 mb-4">
            <div class="form-custom">
                <h4 class="mb-3"><i class="fas fa-user-edit"></i> Profile Information</h4>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $user['name']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" value="<?php echo $user['email']; ?>" disabled>
                        <small class="text-muted">Email cannot be changed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $user['date_of_birth']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo $user['address']; ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="col-lg-6 mb-4">
            <div class="form-custom">
                <h4 class="mb-3"><i class="fas fa-key"></i> Change Password</h4>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="dashboard-card mt-4">
                <h5 class="mb-3"><i class="fas fa-info-circle"></i> Account Information</h5>
                
                <table class="table table-sm">
                    <tr>
                        <th>User ID:</th>
                        <td><?php echo $user['user_id']; ?></td>
                    </tr>
                    <tr>
                        <th>Account Status:</th>
                        <td>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Member Since:</th>
                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated:</th>
                        <td><?php echo formatDate($user['updated_at']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Activity Summary -->
    <div class="dashboard-card">
        <h4 class="mb-3"><i class="fas fa-chart-line"></i> Account Summary</h4>
        
        <div class="row">
            <div class="col-md-3">
                <?php
                $accounts_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM accounts WHERE user_id = '$user_id'"));
                ?>
                <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                    <h3 class="text-primary"><?php echo $accounts_count; ?></h3>
                    <p class="mb-0">Bank Accounts</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <?php
                $transactions_count = mysqli_num_rows(mysqli_query($conn, "SELECT t.* FROM transactions t JOIN accounts a ON t.account_id = a.account_id WHERE a.user_id = '$user_id'"));
                ?>
                <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                    <h3 class="text-success"><?php echo $transactions_count; ?></h3>
                    <p class="mb-0">Total Transactions</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <?php
                $loans_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM loans WHERE user_id = '$user_id'"));
                ?>
                <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                    <h3 class="text-warning"><?php echo $loans_count; ?></h3>
                    <p class="mb-0">Loan Applications</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <?php
                $tickets_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM support_tickets WHERE user_id = '$user_id'"));
                ?>
                <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                    <h3 class="text-info"><?php echo $tickets_count; ?></h3>
                    <p class="mb-0">Support Tickets</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>