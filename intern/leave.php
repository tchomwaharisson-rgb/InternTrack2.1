<?php
// intern/leave.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('intern')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'request_leave') {
        $leave_date = $_POST['leave_date'] ?? '';
        $type = $_POST['type'] ?? '';
        $reason = sanitize($_POST['reason'] ?? '');
        
        if (empty($leave_date) || empty($type)) {
            $message = t('please_fill_all_fields');
            $message_type = 'error';
        } else {
            // Check if leave already requested for this date
            $stmt = $conn->prepare("SELECT id FROM leave_requests WHERE intern_id = ? AND leave_date = ? AND status != 'rejected'");
            $stmt->execute([$user_id, $leave_date]);
            if ($stmt->fetch()) {
                $message = t('leave_already_requested');
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO leave_requests (intern_id, leave_date, type, reason) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $leave_date, $type, $reason])) {
                    $message = t('leave_request_submitted');
                    $message_type = 'success';
                    logAudit($user_id, 'request_leave', 'Requested leave for ' . $leave_date);
                    
                    // Notify supervisor
                    $stmt = $conn->prepare("SELECT supervisor_id FROM interns WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $supervisor_id = $stmt->fetchColumn();
                    if ($supervisor_id) {
                        $user = getUserData($user_id);
                        $notification = $user['first_name'] . ' ' . $user['last_name'] . ' has requested ' . $type . ' leave for ' . date('M d, Y', strtotime($leave_date));
                        createNotification($supervisor_id, 'leave_request', $notification, '/interntrack/supervisor/leave.php');
                    }
                } else {
                    $message = t('error_occurred');
                    $message_type = 'error';
                }
            }
        }
    } elseif ($action === 'cancel_request') {
        $request_id = $_POST['request_id'] ?? 0;
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ? AND intern_id = ? AND status = 'pending'");
        if ($stmt->execute([$request_id, $user_id])) {
            $message = t('leave_request_cancelled');
            $message_type = 'success';
            logAudit($user_id, 'cancel_leave', 'Cancelled leave request ' . $request_id);
        } else {
            $message = t('error_occurred');
            $message_type = 'error';
        }
    }
}

// Get user data
$user = getUserData($user_id);

// Get all leave requests for this intern
$stmt = $conn->prepare("SELECT * FROM leave_requests WHERE intern_id = ? ORDER BY leave_date DESC");
$stmt->execute([$user_id]);
$leave_requests = $stmt->fetchAll();

// Get pending count
$pending_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'pending'; }));

// Get approved count
$approved_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'approved'; }));

// Get rejected count
$rejected_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'rejected'; }));

// Get supervisor info
$stmt = $conn->prepare("SELECT u.* FROM users u JOIN interns i ON u.id = i.supervisor_id WHERE i.user_id = ?");
$stmt->execute([$user_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: #f59e0b;">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo $pending_count; ?></div>
            <div class="stat-label"><?php echo t('pending_leave'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #16a34a;">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $approved_count; ?></div>
            <div class="stat-label"><?php echo t('approved_leave'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #dc2626;">
            <div class="stat-icon">❌</div>
            <div class="stat-value"><?php echo $rejected_count; ?></div>
            <div class="stat-label"><?php echo t('rejected_leave'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #3b82f6;">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo count($leave_requests); ?></div>
            <div class="stat-label"><?php echo t('total_requests'); ?></div>
        </div>
    </div>
    
    <!-- Request Leave Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('request_leave'); ?></h3>
            <?php if ($supervisor): ?>
                <span style="font-size: 14px; color: var(--gray-500);">
                    <?php echo t('supervisor'); ?>: <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                </span>
            <?php else: ?>
                <span style="font-size: 14px; color: var(--gray-500);">
                    <?php echo t('no_supervisor_assigned'); ?>
                </span>
            <?php endif; ?>
        </div>
        <form method="POST" action="" id="leaveForm">
            <input type="hidden" name="action" value="request_leave">
            <div class="form-row">
                <div class="form-group">
                    <label for="leave_date"><?php echo t('leave_date'); ?></label>
                    <input type="date" id="leave_date" name="leave_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="type"><?php echo t('leave_type'); ?></label>
                    <select id="type" name="type" class="form-control" required>
                        <option value=""><?php echo t('select_leave_type'); ?></option>
                        <option value="vacation"><?php echo t('vacation'); ?></option>
                        <option value="sick"><?php echo t('sick_leave'); ?></option>
                        <option value="personal"><?php echo t('personal_leave'); ?></option>
                        <option value="other"><?php echo t('other'); ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="reason"><?php echo t('reason'); ?> (<?php echo t('optional'); ?>)</label>
                <textarea id="reason" name="reason" class="form-control" rows="3" 
                          placeholder="<?php echo t('enter_leave_reason'); ?>"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo t('submit_request'); ?></button>
        </form>
    </div>
    
    <!-- Leave Requests List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('my_leave_requests'); ?></h3>
            <span style="font-size: 14px; color: var(--gray-500);">
                <?php echo count($leave_requests); ?> <?php echo t('requests'); ?>
            </span>
        </div>
        <?php if ($leave_requests): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('type'); ?></th>
                            <th><?php echo t('reason'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('supervisor_comment'); ?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_requests as $request): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($request['leave_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $request['type']; ?>">
                                        <?php echo t($request['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($request['reason'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $request['status']; ?>">
                                        <?php echo t($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($request['supervisor_comment'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel_request">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('<?php echo t('cancel_leave_confirmation'); ?>')">
                                                <?php echo t('cancel'); ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400); font-size: 12px;"><?php echo t('no_actions'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                <h3><?php echo t('no_leave_requests'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_leave_requests_message'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Prevent selecting past dates
document.getElementById('leave_date')?.addEventListener('change', function() {
    const selected = new Date(this.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (selected < today) {
        this.value = today.toISOString().split('T')[0];
        showToast('<?php echo t('past_dates_not_allowed'); ?>', 'error');
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>