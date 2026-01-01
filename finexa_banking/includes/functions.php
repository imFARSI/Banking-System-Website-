<?php
// ============================================
// FINEXA BANKING - HELPER FUNCTIONS
// File: includes/functions.php
// ============================================

function generateAccountNumber($conn) {
    do {
        $account_number = 'FNX' . date('Y') . rand(100000, 999999);
        $check = mysqli_query($conn, "SELECT account_number FROM accounts WHERE account_number = '$account_number'");
    } while (mysqli_num_rows($check) > 0);
    return $account_number;
}

function generateReferenceNumber() {
    return 'TXN' . date('YmdHis') . rand(1000, 9999);
}

function calculateEMI($principal, $annual_rate, $tenure_months) {
    $monthly_rate = ($annual_rate / 12) / 100;
    if ($monthly_rate == 0) {
        return $principal / $tenure_months;
    }
    $emi = ($principal * $monthly_rate * pow(1 + $monthly_rate, $tenure_months)) / (pow(1 + $monthly_rate, $tenure_months) - 1);
    return round($emi, 2);
}

function sanitize($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: " . BASE_URL . "customer/dashboard.php");
        exit();
    }
}

function createNotification($conn, $user_id, $title, $message, $type) {
    $title = sanitize($conn, $title);
    $message = sanitize($conn, $message);
    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES ('$user_id', '$title', '$message', '$type')";
    return mysqli_query($conn, $query);
}

function logActivity($conn, $user_id, $action_type, $entity_type, $entity_id, $description) {
    $action_type = sanitize($conn, $action_type);
    $entity_type = sanitize($conn, $entity_type);
    $description = sanitize($conn, $description);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, description, ip_address) VALUES ('$user_id', '$action_type', '$entity_type', '$entity_id', '$description', '$ip_address')";
    return mysqli_query($conn, $query);
}

function formatCurrency($amount) {
    return '৳ ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d M Y, h:i A', strtotime($date));
}

function getSetting($conn, $key) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['setting_value'] : null;
}

function setAlert($type, $message) {
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
}

function showAlert() {
    if (isset($_SESSION['alert_message'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];
        $class = 'alert-info';
        
        if ($type == 'success') $class = 'alert-success';
        if ($type == 'error') $class = 'alert-danger';
        if ($type == 'warning') $class = 'alert-warning';
        
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        
        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);
    }
}
?>