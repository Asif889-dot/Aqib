<?php
/**
 * Security Configuration File
 * Include this file in your main configuration
 */

// Prevent direct access
if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

// Security Headers
function setSecurityHeaders() {
    // Prevent XSS attacks
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevent clickjacking
    header("X-Frame-Options: SAMEORIGIN");
    
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Referrer policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy (adjust as needed)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net;");
}

// CSRF Token Generation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Validation
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Sanitize Input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Hash Password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate Random String
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access Denied');
    }
}

// Session timeout check (30 minutes)
function checkSessionTimeout() {
    $timeout = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $timeout) {
            session_unset();
            session_destroy();
            header('Location: /login.php?timeout=1');
            exit;
        }
        // Update last activity time
        $_SESSION['login_time'] = time();
    }
}

// Initialize security
setSecurityHeaders();
checkSessionTimeout();
?>