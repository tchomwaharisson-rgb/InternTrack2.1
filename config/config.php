<?php
// config/config.php

// ============ TIME ZONE SETTINGS ============
// Set timezone to Cameroon (West Africa Time - UTC+1)
date_default_timezone_set('Africa/Douala');

// ============ ERROR REPORTING ============
// For development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For production (comment out the above and uncomment below)
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/../logs/error.log');

// ============ SESSION CONFIGURATION ============
// Configure session settings BEFORE starting the session
// Set session timeout (30 minutes)
ini_set('session.gc_maxlifetime', 1800);
ini_set('session.cookie_lifetime', 1800);

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'domain' => '',
    'secure' => false, // Set to true for HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

// ============ START SESSION ============
// Now start the session after all settings are configured
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ SESSION REGENERATION ============
// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['created'])) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// ============ APPLICATION CONSTANTS ============
define('BASE_URL', 'http://localhost/interntrack/');
define('SITE_NAME', 'InternTrack');
define('SITE_ICON', BASE_URL . 'assets/images/logo-icon.png');
define('ADMIN_EMAIL', 'admin@interntrack.com');

// ============ INCLUDE REQUIRED FILES ============
// Database
require_once __DIR__ . '/database.php';

// Language
require_once __DIR__ . '/language.php';

// Email (optional - comment out if not using email)
// require_once __DIR__ . '/email.php';

// ============ DATABASE CONNECTION ============
// Initialize database connection as global
$db = new Database();
$conn = $db->getConnection();

// Make $conn available globally
$GLOBALS['conn'] = $conn;

// ============ SESSION INITIALIZATION ============
// Set default language if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

// Set default theme if not set
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}

// ============ HELPER FUNCTIONS ============

/**
 * Check if user is logged in
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check user role
 * @param string $role Role to check (admin, supervisor, intern)
 * @return bool True if user has the specified role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Get user data by ID
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function getUserData($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getUserData: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user full name by ID
 * @param int $user_id User ID
 * @return string Full name or 'Unknown User'
 */
function getUserFullName($user_id) {
    $user = getUserData($user_id);
    return $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Unknown User';
}

/**
 * Get system setting by key
 * @param string $key Setting key
 * @return string|null Setting value or null if not found
 */
function getSetting($key) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        error_log("Error in getSetting: " . $e->getMessage());
        return null;
    }
}

/**
 * Update system setting
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool True on success
 */
function updateSetting($key, $value) {
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    } catch (PDOException $e) {
        error_log("Error in updateSetting: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for a user
 * @param int $user_id User ID
 * @param string $type Notification type
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return bool True on success
 */
function createNotification($user_id, $type, $message, $link = null) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $message, $link]);
    } catch (PDOException $e) {
        error_log("Error in createNotification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications count for a user
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function getUnreadNotifications($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error in getUnreadNotifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Log audit action
 * @param int|null $user_id User ID (null for system actions)
 * @param string $action Action performed
 * @param string|null $details Additional details
 * @return bool True on success
 */
function logAudit($user_id, $action, $details = null) {
    global $conn;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $details_full = $details . ' | IP: ' . $ip . ' | UA: ' . $user_agent;
        
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $action, $details_full, $ip]);
    } catch (PDOException $e) {
        error_log("Error in logAudit: " . $e->getMessage());
        return false;
    }
}

/**
 * Sanitize input data
 * @param string|array $input Input to sanitize
 * @return string|array Sanitized input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format time for display
 * @param string|null $time Time string (H:i:s)
 * @return string Formatted time or '-'
 */
function formatTime($time) {
    if (!$time) return '-';
    return date('H:i', strtotime($time));
}

/**
 * Format date for display
 * @param string|null $date Date string
 * @param string $format Date format
 * @return string Formatted date or '-'
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 * @param string|null $datetime Datetime string
 * @return string Formatted datetime or '-'
 */
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('M d, Y H:i', strtotime($datetime));
}

/**
 * Get current date in Cameroon time
 * @param string $format Date format
 * @return string Formatted date
 */
function getCurrentDate($format = 'Y-m-d') {
    return date($format);
}

/**
 * Get current time in Cameroon time
 * @param string $format Time format
 * @return string Formatted time
 */
function getCurrentTime($format = 'H:i:s') {
    return date($format);
}

/**
 * Get current datetime in Cameroon time
 * @param string $format Datetime format
 * @return string Formatted datetime
 */
function getCurrentDateTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * Convert time to Cameroon timezone
 * @param string $datetime Datetime string
 * @param string $format Output format
 * @return string Formatted datetime in Cameroon time
 */
function convertToCameroonTime($datetime, $format = 'Y-m-d H:i:s') {
    if (!$datetime) return '-';
    
    // Create datetime object in UTC
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    // Convert to Cameroon timezone
    $dt->setTimezone(new DateTimeZone('Africa/Douala'));
    return $dt->format($format);
}

/**
 * Check if current time is within work hours
 * @return bool True if within work hours
 */
function isWithinWorkHours() {
    $now = date('H:i:s');
    $work_start = getSetting('work_start') ?? '08:00:00';
    $work_end = getSetting('work_end') ?? '18:00:00';
    return $now >= $work_start && $now <= $work_end;
}

/**
 * Check if current time is break time
 * @return bool True if within break time
 */
function isBreakTime() {
    $now = date('H:i:s');
    $break_start = getSetting('break_start') ?? '12:00:00';
    $break_end = getSetting('break_end') ?? '14:00:00';
    return $now >= $break_start && $now <= $break_end;
}

/**
 * Get current theme preference
 * @return string 'light' or 'dark'
 */
function getCurrentTheme() {
    return $_SESSION['theme'] ?? 'light';
}

/**
 * Get current language preference
 * @return string 'en' or 'fr'
 */
function getCurrentLanguage() {
    return $_SESSION['language'] ?? 'en';
}

/**
 * Get available languages
 * @return array Array of language codes and names
 */
function getAvailableLanguages() {
    return ['en' => 'English', 'fr' => 'Français'];
}

/**
 * Generate a random password
 * @param int $length Password length
 * @return string Random password
 */
// function generateRandomPassword($length = 10) {
//     $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
//     $password = '';
//     for ($i = 0; $i < $length; $i++) {
//         $password .= $chars[random_int(0, strlen($chars) - 1)];
//     }
//     return $password;
// }

/**
 * Generate a random token
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if email is valid
 * @param string $email Email address
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if request is AJAX
 * @return bool True if AJAX request
 */
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redirect to a URL
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Show 404 page
 */
function show404() {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

/**
 * Show 403 page
 */
function show403() {
    header('HTTP/1.0 403 Forbidden');
    include '403.php';
    exit;
}

// ============ MAINTENANCE MODE ============
// Check if system is in maintenance mode
$maintenance_mode = getSetting('maintenance_mode');
if ($maintenance_mode === 'true') {
    // Allow admins to access during maintenance
    if (!isLoggedIn() || !hasRole('admin')) {
        include __DIR__ . '/../maintenance.php';
        exit;
    }
}

// ============ DEBUG INFORMATION ============
// Log current timezone for debugging
if (defined('DEBUG') && DEBUG === true) {
    error_log('Current Timezone: ' . date_default_timezone_get());
    error_log('Current DateTime: ' . date('Y-m-d H:i:s'));
}
?>