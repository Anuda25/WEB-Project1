<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Require login, else redirect to login.php
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Check if current user has a specific role
function hasRole($role) {
    if (!isLoggedIn()) return false;
    if (is_array($role)) {
        return in_array($_SESSION['role'], $role);
    }
    return $_SESSION['role'] === $role;
}

// Require specific role(s) to access page
function requireRole($role) {
    requireLogin();
    // Admin has access to everything by default
    if (hasRole('Admin')) return true;
    
    if (!hasRole($role)) {
        http_response_code(403);
        die("Access Denied: You do not have permission to access this page.");
    }
}
?>
