<?php
// auth/logout.php
require_once '../config/config.php';

// Log the logout
if (isLoggedIn()) {
    logAudit($_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: /interntrack/auth/login.php');
exit;
?>