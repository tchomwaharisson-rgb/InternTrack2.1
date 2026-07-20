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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'send':
            $receiver_id = $data['receiver_id'] ?? 0;
            $message = $data['message'] ?? '';
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
                exit;
            }
            
            // Verify the receiver is valid (intern or supervisor)
            if ($user_role === 'supervisor') {
                // Verify this is an assigned intern
                $stmt = $conn->prepare("SELECT user_id FROM interns WHERE user_id = ? AND supervisor_id = ?");
                $stmt->execute([$receiver_id, $user_id]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
                    exit;
                }
            } elseif ($user_role === 'intern') {
                // Verify this is the assigned supervisor
                $stmt = $conn->prepare("SELECT supervisor_id FROM interns WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $supervisor_id = $stmt->fetchColumn();
                if ($receiver_id != $supervisor_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid role']);
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            if ($stmt->execute([$user_id, $receiver_id, $message])) {
                $message_id = $conn->lastInsertId();
                
                // Send notification to receiver
                $sender = getUserData($user_id);
                $notification = t('notification_message') . ' from ' . $sender['first_name'] . ' ' . $sender['last_name'];
                createNotification($receiver_id, 'message', $notification, '/interntrack/' . ($user_role === 'supervisor' ? 'supervisor' : 'intern') . '/chat.php');
                
                echo json_encode(['success' => true, 'message_id' => $message_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error sending message']);
            }
            break;
            
        case 'mark_read':
            $sender_id = $data['sender_id'] ?? 0;
            $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?");
            $stmt->execute([$sender_id, $user_id]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $contact_id = $_GET['contact_id'] ?? 0;
    
    switch ($action) {
        case 'get_messages':
            if (empty($contact_id)) {
                echo json_encode(['success' => false, 'message' => 'Contact ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                SELECT * FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = ?)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
            $messages = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
?>