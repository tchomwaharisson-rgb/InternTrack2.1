<?php
// api/settings.php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

header('Content-Type: application/json');

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// if (!isLoggedIn()) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

$user_id = $_SESSION['user_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if (!in_array($action, ['language', 'theme'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

switch ($action) {
    case 'language':
        $language = $data['language'] ?? 'en';
        if (!in_array($language, ['en', 'fr'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid language']);
            exit;
        }
        
        // Update session
        $_SESSION['language'] = $language;
        setcookie('language', $language, time() + 60 * 60 * 24 * 365, '/', '', false, true);
        
        // Save to database if user is logged in
        if (isLoggedIn() && $user_id !== null) {
            // Save to database for authenticated users
            $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
            if ($stmt->execute([$language, $user_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save language']);
            }
        } else {
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'theme':
        $theme = $data['theme'] ?? 'light';
        if (!in_array($theme, ['light', 'dark'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid theme']);
            exit;
        }
        
        $_SESSION['theme'] = $theme;
        
        if (isLoggedIn()) {
            $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
            $stmt->execute([$theme, $user_id]);
        }
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>