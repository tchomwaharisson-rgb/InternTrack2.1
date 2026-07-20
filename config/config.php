<?php
session_start();

define('BASE_URL', 'http://localhost/interntrack/');
define('SITE_NAME', 'InternTrack');
define('SITE_ICON', BASE_URL . 'assets/images/logo-icon.png');
define('ADMIN_EMAIL', 'admin@interntrack.com');

// Timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database
require_once __DIR__ . '/database.php';

// Initialize database connection as global
$db = new Database();
$conn = $db->getConnection();

// Include language
require_once __DIR__ . '/language.php';

// Make $conn available globally
$GLOBALS['conn'] = $conn;

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Get user data
function getUserData($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Get user full name
function getUserFullName($user_id) {
    $user = getUserData($user_id);
    return $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Unknown User';
}

// Get settings
function getSetting($key) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

// Update setting
function updateSetting($key, $value) {
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    } catch (PDOException $e) {
        return false;
    }
}

// Create notification
function createNotification($user_id, $type, $message, $link = null) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $message, $link]);
    } catch (PDOException $e) {
        return false;
    }
}

// Get unread notifications count
function getUnreadNotifications($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Log audit action
function logAudit($user_id, $action, $details = null) {
    global $conn;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (PDOException $e) {
        return false;
    }
}

// Sanitize function
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Format time
function formatTime($time) {
    if (!$time) return '-';
    return date('H:i', strtotime($time));
}
?>