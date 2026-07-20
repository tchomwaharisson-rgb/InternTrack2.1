<?php
// includes/functions.php - Additional helper functions

/**
 * Sanitize input data
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send email using PHP mail function
 */
function sendEmail($to, $subject, $message, $from = null) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    if ($from) {
        $headers .= "From: " . $from . "\r\n";
    } else {
        $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    }
    return mail($to, $subject, $message, $headers);
}

/**
 * Get user by ID
 */
function getUserById($id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get user by email
 */
function getUserByEmail($email) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Check if user has permission for a module
 */
function hasPermission($module, $action = 'view') {
    // Admin has all permissions
    if (hasRole('admin')) return true;
    
    // Define permissions for each role
    $permissions = [
        'intern' => [
            'dashboard' => ['view'],
            'timelog' => ['view', 'create', 'update'],
            'goals' => ['view', 'update'],
            'leave' => ['view', 'create'],
            'chat' => ['view', 'create'],
            'profile' => ['view', 'update'],
        ],
        'supervisor' => [
            'dashboard' => ['view'],
            'interns' => ['view'],
            'timelogs' => ['view'],
            'goals' => ['view', 'create', 'update', 'delete'],
            'leave' => ['view', 'update'],
            'chat' => ['view', 'create'],
            'profile' => ['view', 'update'],
        ]
    ];
    
    $role = $_SESSION['user_role'] ?? '';
    if (!isset($permissions[$role][$module])) return false;
    return in_array($action, $permissions[$role][$module]);
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    // Ensure $i is an integer and within bounds
    $i = (int)$i;
    if ($i >= count($sizes)) {
        $i = count($sizes) - 1;
    }
    
    $size = $bytes / pow($k, $i);
    return round($size, 2) . ' ' . $sizes[$i];
}

/**
 * Upload file
 */
function uploadFile($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf']) {
    $errors = [];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check file type
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = 'File type not allowed. Allowed: ' . implode(', ', $allowed_types);
    }
    
    // Check file size (max 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        $errors[] = 'File size exceeds 5MB limit';
    }
    
    // Check if directory exists, create if not
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    if (empty($errors)) {
        // Generate unique file name
        $new_file_name = uniqid() . '.' . $file_type;
        $target_file = rtrim($target_dir, '/') . '/' . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $target_file)) {
            return ['success' => true, 'file_name' => $new_file_name];
        } else {
            $errors[] = 'Failed to upload file';
        }
    }
    
    return ['success' => false, 'errors' => $errors];
}

/**
 * Get time logs with pagination
 */
function getTimeLogs($intern_id = null, $start_date = null, $end_date = null, $limit = 50, $offset = 0) {
    global $conn;
    $sql = "SELECT tl.*, u.first_name, u.last_name, u.email 
            FROM time_logs tl
            JOIN users u ON tl.intern_id = u.id
            WHERE 1=1";
    $params = [];
    
    if ($intern_id) {
        $sql .= " AND tl.intern_id = ?";
        $params[] = $intern_id;
    }
    if ($start_date) {
        $sql .= " AND tl.date >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND tl.date <= ?";
        $params[] = $end_date;
    }
    
    $sql .= " ORDER BY tl.date DESC, tl.clock_in DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get intern status
 */
function getInternStatus($intern_id) {
    global $conn;
    $today = date('Y-m-d');
    
    try {
        $stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = ?");
        $stmt->execute([$intern_id, $today]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log || !$log['clock_in']) {
            return 'not_started';
        }
        if ($log['clock_out']) {
            return 'completed';
        }
        if ($log['break_start'] && !$log['break_end']) {
            return 'on_break';
        }
        return 'working';
    } catch (PDOException $e) {
        return 'unknown';
    }
}

/**
 * Get intern's supervisor
 */
function getInternSupervisor($intern_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT u.* FROM users u 
                                JOIN interns i ON u.id = i.supervisor_id 
                                WHERE i.user_id = ?");
        $stmt->execute([$intern_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get supervisor's interns
 */
function getSupervisorInterns($supervisor_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT u.*, i.school, i.field_of_study, i.start_date, i.end_date 
                                FROM users u 
                                JOIN interns i ON u.id = i.user_id 
                                WHERE i.supervisor_id = ? AND u.is_active = TRUE");
        $stmt->execute([$supervisor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Calculate work hours for a day
 */
function calculateWorkHours($clock_in, $clock_out, $break_minutes = 0) {
    if (!$clock_in || !$clock_out) return 0;
    try {
        $start = strtotime($clock_in);
        $end = strtotime($clock_out);
        if ($start === false || $end === false) return 0;
        $total_minutes = ($end - $start) / 60;
        $work_minutes = max(0, $total_minutes - (int)$break_minutes);
        return round($work_minutes / 60, 2);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Check if it's within work hours
 */
function isWithinWorkHours() {
    $now = date('H:i:s');
    $work_start = getSetting('work_start') ?? '08:00:00';
    $work_end = getSetting('work_end') ?? '18:00:00';
    return $now >= $work_start && $now <= $work_end;
}

/**
 * Get current theme
 */
function getCurrentTheme() {
    return $_SESSION['theme'] ?? 'light';
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    return $_SESSION['language'] ?? 'en';
}

/**
 * Get time difference in human readable format
 */
function timeDiff($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 0) {
        return 'in the future';
    }
    
    $intervals = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60
    ];
    
    foreach ($intervals as $unit => $seconds) {
        $value = floor($diff / $seconds);
        if ($value >= 1) {
            return $value . ' ' . $unit . ($value > 1 ? 's' : '') . ' ago';
        }
    }
    
    return 'just now';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    $classes = [
        'active' => 'active',
        'inactive' => 'inactive',
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'complete' => 'complete',
        'completed' => 'complete',
        'in_progress' => 'in_progress',
        'overdue' => 'overdue',
        'on_break' => 'on_break',
        'working' => 'working',
        'not_started' => 'not_started',
        'missed' => 'inactive'
    ];
    return $classes[$status] ?? 'pending';
}

/**
 * Get user role display name
 */
function getRoleDisplay($role) {
    $roles = [
        'admin' => 'Administrator',
        'supervisor' => 'Supervisor',
        'intern' => 'Intern'
    ];
    return $roles[$role] ?? ucfirst($role);
}

/**
 * Check if a date is a weekend
 */
function isWeekend($date) {
    $day = date('N', strtotime($date));
    return $day >= 6;
}

/**
 * Get working days between two dates
 */
function getWorkingDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day');
    
    $working_days = 0;
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $date) {
        if (!$date->format('N') >= 6) {
            $working_days++;
        }
    }
    
    return $working_days;
}

/**
 * Generate a random password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get month name
 */
function getMonthName($month_number) {
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];
    return $months[(int)$month_number] ?? '';
}

/**
 * Get day name
 */
function getDayName($day_number) {
    $days = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];
    return $days[(int)$day_number] ?? '';
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Get browser name from user agent
 */
function getBrowser() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    if (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) return 'Internet Explorer';
    
    return 'Unknown';
}

/**
 * Get operating system from user agent
 */
function getOS() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($user_agent, 'Windows') !== false) return 'Windows';
    if (strpos($user_agent, 'Mac') !== false) return 'macOS';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false) return 'iOS';
    
    return 'Unknown';
}
?>