<?php
// admin/requests.php
require_once '../config/config.php';
require_once '../config/language.php';
require_once '../includes/email_templates.php';
require_once '../config/email.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Get filter from URL
$filter = $_GET['filter'] ?? 'pending';

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
                // CHECK: Does the email already exist in users table?
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$request['email']]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_user) {
                    $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                           reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], 'Email already exists in the system.', $request_id]);
                    
                    $message = 'Email "' . $request['email'] . '" already exists in the system. Request has been rejected.';
                    $message_type = 'error';
                    logAudit($_SESSION['user_id'], 'reject_registration', 
                             'Rejected request ID: ' . $request_id . ' - Email already exists');
                    break;
                }
                
                // CHECK: Is there another pending request with the same email?
                $stmt = $conn->prepare("SELECT id FROM registration_requests WHERE email = ? AND id != ? AND status = 'pending'");
                $stmt->execute([$request['email'], $request_id]);
                $duplicate_request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate_request) {
                    $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                           reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], 'Duplicate pending request exists.', $request_id]);
                    
                    $message = 'Another pending request with this email already exists.';
                    $message_type = 'error';
                    logAudit($_SESSION['user_id'], 'reject_registration', 
                             'Rejected request ID: ' . $request_id . ' - Duplicate pending request');
                    break;
                }
                
                // All checks passed - proceed with approval
                // USE THE USER'S ORIGINAL PASSWORD
                $password_hash = $request['password_hash'];
                $temp_password = ''; // No temporary password needed, using original
                
                // Create the user account
                $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, is_active) 
                                       VALUES (?, ?, ?, ?, ?, TRUE)");
                try {
                    $stmt->execute([
                        $request['email'],
                        $password_hash,
                        $request['first_name'],
                        $request['last_name'],
                        $request['role']
                    ]);
                } catch (PDOException $e) {
                    $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                           reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], 'Database error: ' . $e->getMessage(), $request_id]);
                    
                    $message = 'Error creating user: ' . $e->getMessage();
                    $message_type = 'error';
                    logAudit($_SESSION['user_id'], 'reject_registration', 
                             'Rejected request ID: ' . $request_id . ' - Database error');
                    break;
                }
                
                $user_id = $conn->lastInsertId();
                
                // Create role-specific record
                if ($request['role'] === 'intern') {
                    try {
                        $stmt = $conn->prepare("INSERT INTO interns (user_id, school, field_of_study, theme, start_date, end_date) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $user_id, 
                            $request['school'] ?? null, 
                            $request['field_of_study'] ?? null,
                            $request['theme'] ?? null,
                            $request['start_date'] ?? date('Y-m-d'),
                            $request['end_date'] ?? date('Y-m-d', strtotime('+3 months'))
                        ]);
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'theme') !== false) {
                            $stmt = $conn->prepare("INSERT INTO interns (user_id, school, field_of_study, start_date, end_date) 
                                                    VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $user_id, 
                                $request['school'] ?? null, 
                                $request['field_of_study'] ?? null,
                                $request['start_date'] ?? date('Y-m-d'),
                                $request['end_date'] ?? date('Y-m-d', strtotime('+3 months'))
                            ]);
                        } else {
                            throw $e;
                        }
                    }
                } elseif ($request['role'] === 'supervisor') {
                    $stmt = $conn->prepare("INSERT INTO supervisors (user_id, department, position) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $user_id, 
                        $request['department'] ?? null, 
                        $request['position'] ?? null
                    ]);
                }
                
                // Update request status
                $stmt = $conn->prepare("UPDATE registration_requests SET status = 'approved', 
                                       reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $admin_comment, $request_id]);
                
                // ================================================
                // SEND EMAIL TO THE USER
                // ================================================
                $userName = $request['first_name'] . ' ' . $request['last_name'];
                $email = $request['email'];
                $role = $request['role'];
                
                // Get the subject
                $subject = "Welcome to " . SITE_NAME . " - Your Registration Has Been Approved";
                
                // Generate email content
                $htmlBody = getRegistrationApprovalEmail($userName, $email, null, $role);
                
                // Send the email
                $emailResult = sendEmail($email, $subject, $htmlBody);
                
                // Log the email sending
                logAudit($_SESSION['user_id'], 'send_approval_email', 
                         'Sent approval email to ' . $email . ' - Result: ' . ($emailResult['success'] ? 'Success' : 'Failed'));
                
                // Create notification for the user
                $notification = "Your registration has been approved! Welcome to " . SITE_NAME . ". Check your email for details.";
                createNotification($user_id, 'registration_approved', $notification);
                
                $message = t('request_approved') . ($emailResult['success'] ? ' - Email sent to user.' : ' - Email sending failed.');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'approve_registration', 
                         'Approved request ID: ' . $request_id . ' - Comment: ' . $admin_comment);
            } else {
                $message = 'Request not found or already processed.';
                $message_type = 'error';
            }
            break;
            
        case 'reject':
            $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($request) {
                $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $admin_comment, $request_id]);

                $message = t('request_rejected');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'reject_registration', 'Rejected request ID: ' . $request_id . ' - Comment: ' . $admin_comment);
            } else {
                $message = 'Request not found or already processed.';
                $message_type = 'error';
            }
            break;

        default:
            $message = 'Invalid request action.';
            $message_type = 'error';
            break;
    }
}

// Helper function to generate random password (only used for admin-created users now)
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Build query based on filter
$sql = "SELECT * FROM registration_requests";
if ($filter === 'pending') {
    $sql .= " WHERE status = 'pending'";
} elseif ($filter === 'approved') {
    $sql .= " WHERE status = 'approved'";
} elseif ($filter === 'rejected') {
    $sql .= " WHERE status = 'rejected'";
}
$sql .= " ORDER BY 
          CASE WHEN status = 'pending' THEN 0 ELSE 1 END,
          created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$requests = $stmt->fetchAll();

// Get counts for each status
$stmt = $conn->query("SELECT status, COUNT(*) as count FROM registration_requests GROUP BY status");
$status_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['status']] = $row['count'];
}

$pending_count = $status_counts['pending'] ?? 0;
$approved_count = $status_counts['approved'] ?? 0;
$rejected_count = $status_counts['rejected'] ?? 0;

include_once '../includes/header.php';
?>

<style>
    [data-theme="dark"] .stat-card .stat-value {
        color: white;
    }
</style>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card <?php echo $filter === 'pending' ? 'active' : ''; ?>" style="border-left-color: #f59e0b; cursor: pointer;" onclick="window.location.href='?filter=pending'">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo $pending_count; ?></div>
            <div class="stat-label"><?php echo t('pending'); ?></div>
        </div>
        <div class="stat-card <?php echo $filter === 'approved' ? 'active' : ''; ?>" style="border-left-color: #16a34a; cursor: pointer;" onclick="window.location.href='?filter=approved'">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $approved_count; ?></div>
            <div class="stat-label"><?php echo t('approved'); ?></div>
        </div>
        <div class="stat-card <?php echo $filter === 'rejected' ? 'active' : ''; ?>" style="border-left-color: #dc2626; cursor: pointer;" onclick="window.location.href='?filter=rejected'">
            <div class="stat-icon">❌</div>
            <div class="stat-value"><?php echo $rejected_count; ?></div>
            <div class="stat-label"><?php echo t('rejected'); ?></div>
        </div>
        <div class="stat-card <?php echo $filter === 'all' ? 'active' : ''; ?>" style="border-left-color: #3b82f6; cursor: pointer;" onclick="window.location.href='?filter=all'">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?php echo $pending_count + $approved_count + $rejected_count; ?></div>
            <div class="stat-label"><?php echo t('all'); ?></div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; border-bottom: 2px solid var(--gray-200); padding-bottom: 12px;">
        <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
            ⏳ <?php echo t('pending'); ?> (<?php echo $pending_count; ?>)
        </a>
        <a href="?filter=approved" class="btn <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
            ✅ <?php echo t('approved'); ?> (<?php echo $approved_count; ?>)
        </a>
        <a href="?filter=rejected" class="btn <?php echo $filter === 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
            ❌ <?php echo t('rejected'); ?> (<?php echo $rejected_count; ?>)
        </a>
        <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
            📋 <?php echo t('all'); ?> (<?php echo $pending_count + $approved_count + $rejected_count; ?>)
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                    if ($filter === 'pending') echo t('pending_requests');
                    elseif ($filter === 'approved') echo t('approved_requests');
                    elseif ($filter === 'rejected') echo t('rejected_requests');
                    else echo t('all_requests');
                ?>
            </h3>
            <span style="font-size: 14px; color: var(--gray-500);">
                <?php echo count($requests); ?> <?php echo t('requests'); ?>
            </span>
        </div>
        
        <?php if ($requests): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('name'); ?></th>
                            <th><?php echo t('email'); ?></th>
                            <th><?php echo t('role'); ?></th>
                            <th><?php echo t('details'); ?></th>
                            <th><?php echo t('theme'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('actions'); ?></th>
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
                                            <strong>School:</strong> <?php echo htmlspecialchars($request['school'] ?? 'N/A'); ?>
                                            <br>
                                            <strong>Field:</strong> <?php echo htmlspecialchars($request['field_of_study'] ?? 'N/A'); ?>
                                            <br>
                                            <strong>Duration:</strong> <?php echo $request['internship_duration'] ?? 'N/A'; ?> months
                                            <br>
                                            <strong>Start:</strong> <?php echo $request['start_date'] ? date('M d, Y', strtotime($request['start_date'])) : 'N/A'; ?>
                                            <br>
                                            <strong>End:</strong> <?php echo $request['end_date'] ? date('M d, Y', strtotime($request['end_date'])) : 'N/A'; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 12px;">
                                            <strong>Department:</strong> <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                                            <br>
                                            <strong>Position:</strong> <?php echo htmlspecialchars($request['position'] ?? 'N/A'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($request['theme'])): ?>
                                        <span style="background: var(--red-50); padding: 2px 8px; border-radius: 4px; font-size: 12px; color: var(--primary-color);">
                                            <?php echo htmlspecialchars($request['theme']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400); font-size: 12px;">Not provided</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                    <?php if ($request['admin_comment'] && $request['status'] !== 'pending'): ?>
                                        <div style="font-size: 11px; color: var(--gray-500); margin-top: 4px;">
                                            "<?php echo htmlspecialchars($request['admin_comment']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" onclick="openActionModal(<?php echo $request['id']; ?>, 'approve')">
                                            ✅ <?php echo t('approve'); ?>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="openActionModal(<?php echo $request['id']; ?>, 'reject')">
                                            ❌ <?php echo t('reject'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400); font-size: 12px;">
                                            <?php echo t('reviewed'); ?>
                                            <?php if ($request['reviewed_at']): ?>
                                                <br><?php echo date('M d, Y', strtotime($request['reviewed_at'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                <h3>
                    <?php 
                        if ($filter === 'pending') echo t('no_pending_requests');
                        elseif ($filter === 'approved') echo t('no_approved_requests');
                        elseif ($filter === 'rejected') echo t('no_rejected_requests');
                        else echo t('no_registration_requests');
                    ?>
                </h3>
                <p style="color: var(--gray-500);">
                    <?php 
                        if ($filter === 'pending') echo t('no_pending_requests_message');
                        elseif ($filter === 'approved') echo t('no_approved_requests_message');
                        elseif ($filter === 'rejected') echo t('no_rejected_requests_message');
                        else echo t('no_registration_requests_message');
                    ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle"><?php echo t('registration_request_action'); ?></h3>
            <button class="modal-close" onclick="closeModal('actionModal')">&times;</button>
        </div>
        <form method="POST" action="" id="actionForm">
            <input type="hidden" name="request_id" id="action_request_id">
            <input type="hidden" name="action" id="action_type">
            <div class="modal-body">
                <p id="modalMessage" style="margin-bottom: 16px;"></p>
                
                <div class="info-box" id="modalInfoBox" style="background: var(--red-50); border: 1px solid var(--red-200); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                    <div style="font-size: 14px; color: var(--gray-700);">
                        <strong id="modalUserInfo"></strong>
                    </div>
                </div>
                
                <?php if ($filter !== 'approved' && $filter !== 'rejected'): ?>
                <div class="form-group">
                    <label for="admin_comment"><?php echo t('admin_comment'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="admin_comment" name="admin_comment" class="form-control" rows="4" 
                              placeholder="<?php echo t('enter_admin_comment_here'); ?>"></textarea>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('actionModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary" id="confirmBtn"><?php echo t('confirm'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Styles -->
<style>
    .stat-card.active {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-left-width: 6px;
    }
    
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 20px;
    }
    
    .modal-overlay.show {
        display: flex;
    }
    
    .modal {
        background: var(--primary-white);
        border-radius: var(--border-radius);
        max-width: 500px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 24px;
        animation: modalIn 0.3s ease;
        box-shadow: var(--shadow-lg);
    }
    
    @keyframes modalIn {
        from {
            transform: scale(0.9) translateY(-20px);
            opacity: 0;
        }
        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .modal-header h3 {
        font-size: 20px;
        font-weight: 600;
        color: var(--gray-800);
    }
    
    .modal-header .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: var(--gray-500);
        transition: var(--transition-speed);
        line-height: 1;
        padding: 0 4px;
    }
    
    .modal-header .modal-close:hover {
        color: var(--primary-color);
        transform: rotate(90deg);
    }
    
    .modal-body {
        margin-bottom: 16px;
    }
    
    .modal-body .info-box {
        background: var(--red-50);
        border-left: 4px solid var(--primary-color);
        padding: 12px 16px;
        border-radius: 6px;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--gray-200);
    }
    
    .modal-footer .btn {
        padding: 10px 24px;
        font-size: 14px;
    }
    
    .modal-footer .btn-primary {
        background: var(--red-gradient);
        color: white;
    }
    
    .modal-footer .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }
    
    .modal-footer .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }
    
    .modal-footer .btn-secondary:hover {
        background: var(--gray-300);
    }
    
    .modal-footer .btn-success {
        background: #16a34a;
        color: white;
    }
    
    .modal-footer .btn-success:hover {
        background: #15803d;
        transform: translateY(-2px);
    }
    
    .modal-footer .btn-danger {
        background: var(--primary-color);
        color: white;
    }
    
    .modal-footer .btn-danger:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    /* Dark Mode Support */
    body.dark-mode .modal {
        background: #2d2d2d;
    }
    
    body.dark-mode .modal-header {
        border-bottom-color: #4a1a1a;
    }
    
    body.dark-mode .modal-header h3 {
        color: #f3f4f6;
    }
    
    body.dark-mode .modal-header .modal-close {
        color: #9ca3af;
    }
    
    body.dark-mode .modal-header .modal-close:hover {
        color: #dc2626;
    }
    
    body.dark-mode .modal-footer {
        border-top-color: #4a1a1a;
    }
    
    body.dark-mode .modal-body .info-box {
        background: #2d1a1a;
        border-color: #4a1a1a;
    }
    
    body.dark-mode .modal-body .info-box strong {
        color: #f3f4f6;
    }
    
    body.dark-mode .modal-body .info-box div[style*="color: var(--gray-700)"] {
        color: #e5e7eb !important;
    }
    
    body.dark-mode .modal-footer .btn-secondary {
        background: #4a4a4a;
        color: #f3f4f6;
    }
    
    body.dark-mode .modal-footer .btn-secondary:hover {
        background: #5a5a5a;
    }
</style>

<script>
function openActionModal(requestId, action) {
    // Set form values
    document.getElementById('action_request_id').value = requestId;
    document.getElementById('action_type').value = action;
    
    // Find the request data from the table
    const row = document.querySelector(`tr:has(button[onclick*="${requestId}"])`);
    let userName = 'User';
    let userEmail = '';
    let userRole = '';
    
    if (row) {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 8) {
            userName = cells[0].textContent.trim();
            userEmail = cells[1].textContent.trim();
            userRole = cells[2].textContent.trim();
        }
    }
    
    // Set modal content based on action
    const title = action === 'approve' ? '✅ Approve Registration Request' : '❌ Reject Registration Request';
    const message = action === 'approve' 
        ? 'Are you sure you want to approve this registration request? The user will be able to login with their registration password.'
        : 'Are you sure you want to reject this registration request? The user will be notified.';
    const btnText = action === 'approve' ? 'Approve' : 'Reject';
    const btnClass = action === 'approve' ? 'btn-success' : 'btn-danger';
    
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('modalUserInfo').innerHTML = 
        '<strong>User:</strong> ' + userName + '<br>' +
        '<strong>Email:</strong> ' + userEmail + '<br>' +
        '<strong>Role:</strong> ' + userRole;
    document.getElementById('confirmBtn').textContent = btnText;
    document.getElementById('confirmBtn').className = 'btn ' + btnClass;
    
    // Clear previous comment
    document.getElementById('admin_comment').value = '';
    
    // Show modal
    document.getElementById('actionModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on outside click
document.querySelector('.modal-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('actionModal');
        if (modal && modal.classList.contains('show')) {
            closeModal('actionModal');
        }
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>