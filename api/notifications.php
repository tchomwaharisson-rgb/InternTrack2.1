<?php
// api/notifications.php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'mark_read':
        // Mark all notifications as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        if ($stmt->execute([$user_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
        }
        break;
        
    case 'get_unread_count':
        // Get unread count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        $count = (int)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'get_latest':
        // Get latest 5 notifications
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>