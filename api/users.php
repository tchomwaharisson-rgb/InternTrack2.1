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
    case 'get_users':
        // Only admins can list all users
        if (!hasRole('admin')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $role_filter = $_GET['role'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $sql = "SELECT u.*, 
                       i.school, i.field_of_study, i.supervisor_id,
                       s.department, s.position
                FROM users u
                LEFT JOIN interns i ON u.id = i.user_id
                LEFT JOIN supervisors s ON u.id = s.user_id
                WHERE 1=1";
        $params = [];
        
        if ($role_filter) {
            $sql .= " AND u.role = ?";
            $params[] = $role_filter;
        }
        
        if ($search) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY u.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove sensitive data
        foreach ($users as &$user) {
            unset($user['password']);
            unset($user['verification_token']);
        }
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    case 'get_user':
        $target_user_id = $_GET['user_id'] ?? 0;
        
        // Check permission
        if (!hasRole('admin') && $target_user_id != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $stmt = $conn->prepare("
            SELECT u.*, 
                   i.school, i.field_of_study, i.start_date, i.end_date, i.supervisor_id,
                   s.department, s.position
            FROM users u
            LEFT JOIN interns i ON u.id = i.user_id
            LEFT JOIN supervisors s ON u.id = s.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$target_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            unset($user['password']);
            unset($user['verification_token']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;
        
    case 'update_user':
        // Only admins can update other users
        if (!hasRole('admin')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $target_user_id = $data['user_id'] ?? 0;
        $updates = [];
        $params = [];
        
        $allowed_fields = ['first_name', 'last_name', 'phone', 'address', 'bio', 'is_active'];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        // Don't allow updating admin via API
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($target && $target['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot update admin via API']);
            exit;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $target_user_id;
        
        $stmt = $conn->prepare($sql);
        if ($stmt->execute($params)) {
            logAudit($user_id, 'api_update_user', 'Updated user ID: ' . $target_user_id);
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating user']);
        }
        break;
        
    case 'delete_user':
        // Only admins can delete users
        if (!hasRole('admin')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $target_user_id = $_GET['user_id'] ?? 0;
        
        // Check if user exists and is not admin
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        if ($target['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete admin']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$target_user_id])) {
            logAudit($user_id, 'api_delete_user', 'Deleted user ID: ' . $target_user_id);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting user']);
        }
        break;
        
    case 'get_current_user':
        $stmt = $conn->prepare("
            SELECT u.*, 
                   i.school, i.field_of_study, i.start_date, i.end_date, i.supervisor_id,
                   s.department, s.position
            FROM users u
            LEFT JOIN interns i ON u.id = i.user_id
            LEFT JOIN supervisors s ON u.id = s.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            unset($user['password']);
            unset($user['verification_token']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>