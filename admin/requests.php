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

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['request_id'] ?? 0;
    $admin_comment = $_POST['admin_comment'] ?? '';
    
    switch ($action) {
        case 'approve':
            // Get the request data
            $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                // Create the user account
                $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, is_active) 
                                       VALUES (?, ?, ?, ?, ?, TRUE)");
                $stmt->execute([
                    $request['email'],
                    $request['password_hash'],
                    $request['first_name'],
                    $request['last_name'],
                    $request['role']
                ]);
                
                $user_id = $conn->lastInsertId();
                
                // Create role-specific record
                if ($request['role'] === 'intern') {
                    $stmt = $conn->prepare("INSERT INTO interns (user_id, school, field_of_study) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $request['school'], $request['field_of_study']]);
                } elseif ($request['role'] === 'supervisor') {
                    $stmt = $conn->prepare("INSERT INTO supervisors (user_id, department, position) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $request['department'], $request['position']]);
                }
                
                // Update request status
                $stmt = $conn->prepare("UPDATE registration_requests SET status = 'approved', 
                                       reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $admin_comment, $request_id]);
                
                // Send notification to user
                $message = "Your registration request has been approved! You can now login.";
                createNotification($user_id, 'registration_approved', $message);
                
                $message = t('request_approved');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'approve_registration', 'Approved request ID: ' . $request_id);
            }
            break;
            
        case 'reject':
            $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                   reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
            if ($stmt->execute([$_SESSION['user_id'], $admin_comment, $request_id])) {
                $message = t('request_rejected');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'reject_registration', 'Rejected request ID: ' . $request_id);
            }
            break;
    }
}

// Get all requests
$stmt = $conn->prepare("SELECT * FROM registration_requests ORDER BY created_at DESC");
$stmt->execute();
$requests = $stmt->fetchAll();

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Registration Requests</h3>
        </div>
        
        <?php if ($requests): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $request['role']; ?>">
                                        <?php echo ucfirst($request['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['role'] === 'intern'): ?>
                                        <div style="font-size: 12px;">
                                            School: <?php echo htmlspecialchars($request['school'] ?? 'N/A'); ?>
                                            <br>
                                            Field: <?php echo htmlspecialchars($request['field_of_study'] ?? 'N/A'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 12px;">
                                            Department: <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                                            <br>
                                            Position: <?php echo htmlspecialchars($request['position'] ?? 'N/A'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                    <?php if ($request['admin_comment'] && $request['status'] !== 'pending'): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text); margin-top: 4px;">
                                            "<?php echo htmlspecialchars($request['admin_comment']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="text" name="admin_comment" placeholder="Comment (optional)" 
                                                   style="width: 120px; padding: 4px 8px; border: 1px solid var(--primary-gray-dark); border-radius: 4px; font-size: 12px;">
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <form method="POST" action="" style="display: inline; margin-top: 4px;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="text" name="admin_comment" placeholder="Reason (optional)" 
                                                   style="width: 120px; padding: 4px 8px; border: 1px solid var(--primary-gray-dark); border-radius: 4px; font-size: 12px;">
                                            <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--secondary-text);">Reviewed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No registration requests found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>