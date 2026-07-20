<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('intern')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

$today = date('Y-m-d');

try {
    // Get today's timelog
    $stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = ?");
    $stmt->execute([$user_id, $today]);
    $timelog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $now = date('H:i:s');
    
    switch ($action) {
        case 'clock_in':
            if ($timelog && $timelog['clock_in']) {
                echo json_encode(['success' => false, 'message' => t('already_clocked_in')]);
                exit;
            }
            
            // Check if it's before 8am (reminder)
            $work_start = getSetting('work_start') ?? '08:00:00';
            if ($now < $work_start) {
                echo json_encode(['success' => false, 'message' => 'Cannot clock in before ' . $work_start]);
                exit;
            }
            
            if (!$timelog) {
                $stmt = $conn->prepare("INSERT INTO time_logs (intern_id, date, clock_in, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$user_id, $today, $now]);
            } else {
                $stmt = $conn->prepare("UPDATE time_logs SET clock_in = ?, status = 'active' WHERE id = ?");
                $stmt->execute([$now, $timelog['id']]);
            }
            
            // Notify supervisor
            $stmt = $conn->prepare("SELECT supervisor_id FROM interns WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $supervisor_id = $stmt->fetchColumn();
            
            if ($supervisor_id) {
                $user = getUserData($user_id);
                $message = t('notification_arrival')
                    . ' ' . t('arrived_at') . ' ' . date('H:i');
                createNotification($supervisor_id, 'clock_in', $message, '/interntrack/supervisor/timelogs.php');
            }
            
            logAudit($user_id, 'clock_in', 'Clocked in at ' . $now);
            echo json_encode(['success' => true, 'time' => $now]);
            break;
            
        case 'clock_out':
            if (!$timelog || !$timelog['clock_in']) {
                echo json_encode(['success' => false, 'message' => 'You must clock in first']);
                exit;
            }
            
            if ($timelog['clock_out']) {
                echo json_encode(['success' => false, 'message' => t('already_clocked_out')]);
                exit;
            }
            
            // Calculate total hours
            $clock_in = strtotime($timelog['clock_in']);
            $clock_out = strtotime($now);
            $total_seconds = $clock_out - $clock_in;
            
            // Subtract break time
            $break_minutes = $timelog['total_break_minutes'] ?? 0;
            $total_minutes = ($total_seconds / 60) - $break_minutes;
            $total_hours = round($total_minutes / 60, 2);
            
            $stmt = $conn->prepare("UPDATE time_logs SET clock_out = ?, total_hours = ?, status = 'completed' WHERE id = ?");
            $stmt->execute([$now, $total_hours, $timelog['id']]);
            
            logAudit($user_id, 'clock_out', 'Clocked out at ' . $now . ' - Total hours: ' . $total_hours);
            echo json_encode(['success' => true, 'time' => $now, 'hours' => $total_hours]);
            break;
            
        case 'start_break':
            if (!$timelog || !$timelog['clock_in']) {
                echo json_encode(['success' => false, 'message' => 'You must clock in first']);
                exit;
            }
            
            if ($timelog['break_start'] && !$timelog['break_end']) {
                echo json_encode(['success' => false, 'message' => 'Break already started']);
                exit;
            }
            
            // Check if it's break time (between 12pm and 2pm)
            $break_start_time = getSetting('break_start') ?? '12:00:00';
            $break_end_time = getSetting('break_end') ?? '14:00:00';
            
            if ($now < $break_start_time || $now > $break_end_time) {
                echo json_encode(['success' => false, 'message' => 'Break is only allowed between ' . $break_start_time . ' and ' . $break_end_time]);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE time_logs SET break_start = ? WHERE id = ?");
            $stmt->execute([$now, $timelog['id']]);
            
            // Create break log
            $stmt = $conn->prepare("INSERT INTO break_logs (time_log_id, break_start) VALUES (?, ?)");
            $stmt->execute([$timelog['id'], $now]);
            
            logAudit($user_id, 'start_break', 'Break started at ' . $now);
            echo json_encode(['success' => true, 'time' => $now]);
            break;
            
        case 'end_break':
            if (!$timelog || !$timelog['break_start']) {
                echo json_encode(['success' => false, 'message' => t('break_not_started')]);
                exit;
            }
            
            if ($timelog['break_end']) {
                echo json_encode(['success' => false, 'message' => 'Break already ended']);
                exit;
            }
            
            // Calculate break duration
            $break_start = strtotime($timelog['break_start']);
            $break_end = strtotime($now);
            $duration_minutes = round(($break_end - $break_start) / 60);
            
            // Check max break duration
            $max_break = getSetting('max_break_minutes') ?? 120;
            if ($duration_minutes > $max_break) {
                // Notify supervisor about long break
                $stmt = $conn->prepare("SELECT supervisor_id FROM interns WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $supervisor_id = $stmt->fetchColumn();
                if ($supervisor_id) {
                    $user = getUserData($user_id);
                    $message = 'Abnormal break duration: ' . $user['first_name'] . ' ' . $user['last_name'] . 
                               ' took a break of ' . $duration_minutes . ' minutes';
                    createNotification($supervisor_id, 'abnormal_break', $message, '/interntrack/supervisor/timelogs.php');
                }
            }
            
            // Update total break minutes
            $total_break_minutes = ($timelog['total_break_minutes'] ?? 0) + $duration_minutes;
            
            $stmt = $conn->prepare("UPDATE time_logs SET break_end = ?, total_break_minutes = ? WHERE id = ?");
            $stmt->execute([$now, $total_break_minutes, $timelog['id']]);
            
            // Update break log
            $stmt = $conn->prepare("UPDATE break_logs SET break_end = ?, duration_minutes = ? WHERE time_log_id = ? AND break_end IS NULL ORDER BY id DESC LIMIT 1");
            $stmt->execute([$now, $duration_minutes, $timelog['id']]);
            
            logAudit($user_id, 'end_break', 'Break ended at ' . $now . ' - Duration: ' . $duration_minutes . ' minutes');
            echo json_encode(['success' => true, 'time' => $now, 'duration' => $duration_minutes]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>