<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'intern_summary':
        $intern_id = $_GET['intern_id'] ?? 0;
        $period = $_GET['period'] ?? 'month';
        
        $end_date = date('Y-m-d');
        switch ($period) {
            case 'week':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                break;
            case 'month':
                $start_date = date('Y-m-01');
                break;
            case 'year':
                $start_date = date('Y-01-01');
                break;
            default:
                $start_date = date('Y-m-01');
        }
        
        // Get intern data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'intern'");
        $stmt->execute([$intern_id]);
        $intern = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$intern) {
            echo json_encode(['success' => false, 'message' => 'Intern not found']);
            exit;
        }
        
        // Get time logs
        $stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date BETWEEN ? AND ? ORDER BY date");
        $stmt->execute([$intern_id, $start_date, $end_date]);
        $logs = $stmt->fetchAll();
        
        // Calculate statistics
        $total_hours = array_sum(array_column($logs, 'total_hours'));
        $days_worked = count(array_filter($logs, function($log) { return $log['clock_in'] !== null; }));
        $avg_hours = $days_worked > 0 ? $total_hours / $days_worked : 0;
        
        // Get goals
        $stmt = $conn->prepare("SELECT * FROM goals WHERE intern_id = ? ORDER BY end_date DESC");
        $stmt->execute([$intern_id]);
        $goals = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'intern' => $intern,
                'summary' => [
                    'total_hours' => $total_hours,
                    'days_worked' => $days_worked,
                    'avg_hours' => $avg_hours,
                    'logs' => $logs
                ],
                'goals' => $goals
            ]
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>