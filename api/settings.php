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
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {
    case 'language':
        $language = $data['language'] ?? 'en';
        if (!in_array($language, ['en', 'fr'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid language']);
            exit;
        }
        
        $_SESSION['language'] = $language;
        
        // Save to database
        $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
        if ($stmt->execute([$language, $user_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save language']);
        }
        break;
        
    case 'theme':
        $theme = $data['theme'] ?? 'light';
        if (!in_array($theme, ['light', 'dark'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid theme']);
            exit;
        }
        
        $_SESSION['theme'] = $theme;
        
        // Save to database
        $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
        if ($stmt->execute([$theme, $user_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save theme']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>