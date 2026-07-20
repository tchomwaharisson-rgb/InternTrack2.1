<?php
// supervisor/leave.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle leave request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $request_id = $_POST['request_id'] ?? 0;
    $comment = sanitize($_POST['comment'] ?? '');
    
    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        // Verify this leave request belongs to an intern assigned to this supervisor
        $stmt = $conn->prepare("
            SELECT lr.*, u.first_name, u.last_name, u.id as intern_id 
            FROM leave_requests lr
            JOIN interns i ON lr.intern_id = i.user_id
            JOIN users u ON lr.intern_id = u.id
            WHERE lr.id = ? AND i.supervisor_id = ? AND lr.status = 'pending'
        ");
        $stmt->execute([$request_id, $user_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, supervisor_comment = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            if ($stmt->execute([$status, $comment, $user_id, $request_id])) {
                $message = $status === 'approved' ? t('leave_request_approved') : t('leave_request_rejected');
                $message_type = 'success';
                logAudit($user_id, $action . '_leave', $action . 'd leave request ' . $request_id);
                
                // Notify intern
                $notification = 'Your ' . $request['type'] . ' leave request for ' . date('M d, Y', strtotime($request['leave_date'])) . ' has been ' . $status;
                createNotification($request['intern_id'], 'leave_' . $status, $notification, '/interntrack/intern/leave.php');
            } else {
                $message = t('error_occurred');
                $message_type = 'error';
            }
        } else {
            $message = t('invalid_request');
            $message_type = 'error';
        }
    }
}

// Get all leave requests for assigned interns
$stmt = $conn->prepare("
    SELECT lr.*, u.first_name, u.last_name, u.email,
           i.school, i.field_of_study
    FROM leave_requests lr
    JOIN users u ON lr.intern_id = u.id
    JOIN interns i ON u.id = i.user_id
    WHERE i.supervisor_id = ?
    ORDER BY 
        CASE WHEN lr.status = 'pending' THEN 0 ELSE 1 END,
        lr.leave_date DESC
");
$stmt->execute([$user_id]);
$leave_requests = $stmt->fetchAll();

// Get counts
$pending_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'pending'; }));
$approved_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'approved'; }));
$rejected_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'rejected'; }));

// Get assigned interns list for filter
$interns = array_unique(array_column($leave_requests, 'intern_id'));
$interns_list = [];
foreach ($interns as $intern_id) {
    $intern = array_filter($leave_requests, function($r) use ($intern_id) { return $r['intern_id'] == $intern_id; });
    if ($intern) {
        $interns_list[] = reset($intern);
    }
}

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
    
    <!-- Leave Requests List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('intern_leave_requests'); ?></h3>
            <span style="font-size: 14px; color: var(--gray-500);">
                <?php echo count($leave_requests); ?> <?php echo t('requests'); ?>
            </span>
        </div>
        <?php if ($leave_requests): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('type'); ?></th>
                            <th><?php echo t('reason'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        <?php echo htmlspecialchars($request['email']); ?>
                                    </div>
                                    <?php if ($request['school']): ?>
                                        <div style="font-size: 11px; color: var(--gray-400);">
                                            <?php echo htmlspecialchars($request['school']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
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
                                    <?php if ($request['supervisor_comment']): ?>
                                        <div style="font-size: 11px; color: var(--gray-500); margin-top: 4px;">
                                            "<?php echo htmlspecialchars($request['supervisor_comment']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
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
                                            <?php echo $request['reviewed_at'] ? date('M d, Y', strtotime($request['reviewed_at'])) : ''; ?>
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
                <h3><?php echo t('no_leave_requests'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_leave_requests_from_interns'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle"><?php echo t('leave_request_action'); ?></h3>
            <button class="modal-close" onclick="closeModal('actionModal')">&times;</button>
        </div>
        <form method="POST" action="" id="actionForm">
            <input type="hidden" name="request_id" id="action_request_id">
            <input type="hidden" name="action" id="action_type">
            <div class="modal-body">
                <p id="modalMessage"></p>
                <div class="form-group">
                    <label for="comment"><?php echo t('supervisor_comment'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="comment" name="comment" class="form-control" rows="3" 
                              placeholder="<?php echo t('enter_comment'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('actionModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary" id="confirmBtn"><?php echo t('confirm'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(requestId, action) {
    document.getElementById('action_request_id').value = requestId;
    document.getElementById('action_type').value = action;
    
    const title = action === 'approve' ? '<?php echo t('approve_leave_request'); ?>' : '<?php echo t('reject_leave_request'); ?>';
    const message = action === 'approve' ? '<?php echo t('approve_leave_confirmation'); ?>' : '<?php echo t('reject_leave_confirmation'); ?>';
    const btnText = action === 'approve' ? '<?php echo t('approve'); ?>' : '<?php echo t('reject'); ?>';
    const btnClass = action === 'approve' ? 'btn-success' : 'btn-danger';
    
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('confirmBtn').textContent = btnText;
    document.getElementById('confirmBtn').className = 'btn ' + btnClass;
    
    document.getElementById('actionModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close modal on outside click
document.querySelector('.modal-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('show');
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>