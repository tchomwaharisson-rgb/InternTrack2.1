<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    switch ($action) {
        case 'activate':
            $stmt = $conn->prepare("UPDATE users SET is_active = TRUE WHERE id = ? AND role != 'admin'");
            if ($stmt->execute([$user_id])) {
                $message = t('user_updated');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'activate_user', 'Activated user ID: ' . $user_id);
            }
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE users SET is_active = FALSE WHERE id = ? AND role != 'admin'");
            if ($stmt->execute([$user_id])) {
                $message = t('user_updated');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'deactivate_user', 'Deactivated user ID: ' . $user_id);
            }
            break;
            
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            if ($stmt->execute([$user_id])) {
                $message = t('user_deleted');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'delete_user', 'Deleted user ID: ' . $user_id);
            }
            break;
            
        case 'assign_supervisor':
            $intern_id = $_POST['intern_id'] ?? 0;
            $supervisor_id = $_POST['supervisor_id'] ?? null;
            
            $stmt = $conn->prepare("UPDATE interns SET supervisor_id = ? WHERE user_id = ?");
            if ($stmt->execute([$supervisor_id, $intern_id])) {
                $message = t('user_updated');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'assign_supervisor', 
                         'Assigned supervisor ' . $supervisor_id . ' to intern ' . $intern_id);
            }
            break;
            
        case 'create_user':
            $email = $_POST['email'] ?? '';
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $role = $_POST['role'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'Email already exists';
                $message_type = 'error';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, is_active) 
                                       VALUES (?, ?, ?, ?, ?, TRUE)");
                if ($stmt->execute([$email, $password_hash, $first_name, $last_name, $role])) {
                    $user_id = $conn->lastInsertId();
                    
                    // Create role-specific record
                    if ($role === 'intern') {
                        $stmt = $conn->prepare("INSERT INTO interns (user_id) VALUES (?)");
                        $stmt->execute([$user_id]);
                    } elseif ($role === 'supervisor') {
                        $stmt = $conn->prepare("INSERT INTO supervisors (user_id) VALUES (?)");
                        $stmt->execute([$user_id]);
                    }
                    
                    $message = t('user_created');
                    $message_type = 'success';
                    logAudit($_SESSION['user_id'], 'create_user', 'Created user: ' . $email);
                }
            }
            break;
    }
}

// Get all users
$stmt = $conn->prepare("
    SELECT u.*, 
           i.school, i.field_of_study, i.supervisor_id,
           s.department, s.position
    FROM users u
    LEFT JOIN interns i ON u.id = i.user_id
    LEFT JOIN supervisors s ON u.id = s.user_id
    WHERE u.role != 'admin'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get all supervisors for assignment
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'supervisor' AND is_active = TRUE");
$stmt->execute();
$supervisors = $stmt->fetchAll();

// Get all interns for assignment
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'intern' AND is_active = TRUE");
$stmt->execute();
$interns = $stmt->fetchAll();

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Create User -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Create New User</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_user">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="intern">Intern</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
    
    <!-- User List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">User Management</h3>
        </div>
        <?php if ($users): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Supervisor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    <?php if ($user['role'] === 'intern'): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo htmlspecialchars($user['school'] ?? ''); ?>
                                            <?php if ($user['field_of_study']): ?>
                                                - <?php echo htmlspecialchars($user['field_of_study']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($user['role'] === 'supervisor'): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo htmlspecialchars($user['department'] ?? ''); ?>
                                            <?php if ($user['position']): ?>
                                                - <?php echo htmlspecialchars($user['position']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'intern'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="assign_supervisor">
                                            <input type="hidden" name="intern_id" value="<?php echo $user['id']; ?>">
                                            <select name="supervisor_id" class="form-control" style="width: 150px; display: inline-block;" onchange="this.form.submit()">
                                                <option value="">None</option>
                                                <?php foreach ($supervisors as $supervisor): ?>
                                                    <option value="<?php echo $supervisor['id']; ?>" 
                                                            <?php echo ($user['supervisor_id'] == $supervisor['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this user?')">
                                                Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Activate this user?')">
                                                Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user permanently?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>