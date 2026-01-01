<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Log activity before destroying session
if (isLoggedIn()) {
    logActivity($conn, $_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: index.php");
exit();
?>