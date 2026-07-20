<?php
// api/upload.php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'upload_profile_picture') {
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['profile_picture'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size: 5MB']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Delete old profile picture if exists
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old_picture = $stmt->fetchColumn();
        
        if ($old_picture && file_exists($upload_dir . $old_picture)) {
            unlink($upload_dir . $old_picture);
        }
        
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        if ($stmt->execute([$filename, $user_id])) {
            logAudit($user_id, 'upload_profile_picture', 'Uploaded new profile picture');
            echo json_encode([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'filename' => $filename,
                'url' => '/interntrack/uploads/profiles/' . $filename
            ]);
        } else {
            // Delete uploaded file if database update fails
            unlink($filepath);
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
    exit;
}

if ($action === 'remove_profile_picture') {
    // Get current profile picture
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $old_picture = $stmt->fetchColumn();
    
    if ($old_picture) {
        $upload_dir = __DIR__ . '/../uploads/profiles/';
        if (file_exists($upload_dir . $old_picture)) {
            unlink($upload_dir . $old_picture);
        }
    }
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        logAudit($user_id, 'remove_profile_picture', 'Removed profile picture');
        echo json_encode(['success' => true, 'message' => 'Profile picture removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove profile picture']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>