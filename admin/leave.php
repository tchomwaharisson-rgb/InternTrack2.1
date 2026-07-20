<?php
// admin/leave.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
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
        
        // Verify this leave request exists
        $stmt = $conn->prepare("
            SELECT lr.*, u.first_name, u.last_name, u.id as intern_id,
                   i.supervisor_id
            FROM leave_requests lr
            JOIN users u ON lr.intern_id = u.id
            JOIN interns i ON u.id = i.user_id
            WHERE lr.id = ? AND lr.status = 'pending'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, supervisor_comment = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            if ($stmt->execute([$status, $comment, $user_id, $request_id])) {
                $message = $status === 'approved' ? t('leave_request_approved') : t('leave_request_rejected');
                $message_type = 'success';
                logAudit($user_id, 'admin_' . $action . '_leave', $action . 'd leave request ' . $request_id);
                
                // Notify intern
                $notification = 'Your ' . $request['type'] . ' leave request for ' . date('M d, Y', strtotime($request['leave_date'])) . ' has been ' . $status . ' by admin';
                createNotification($request['intern_id'], 'leave_' . $status, $notification, '/interntrack/intern/leave.php');
                
                // Also notify supervisor
                if ($request['supervisor_id']) {
                    $notification = 'Leave request for ' . $request['first_name'] . ' ' . $request['last_name'] . ' has been ' . $status . ' by admin';
                    createNotification($request['supervisor_id'], 'leave_' . $status, $notification, '/interntrack/supervisor/leave.php');
                }
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

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$intern_filter = $_GET['intern_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT lr.*, u.first_name, u.last_name, u.email,
               i.school, i.field_of_study,
               s.first_name as supervisor_first_name, s.last_name as supervisor_last_name
        FROM leave_requests lr
        JOIN users u ON lr.intern_id = u.id
        JOIN interns i ON u.id = i.user_id
        LEFT JOIN users s ON i.supervisor_id = s.id
        WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $sql .= " AND lr.status = ?";
    $params[] = $status_filter;
}

if (!empty($intern_filter)) {
    $sql .= " AND lr.intern_id = ?";
    $params[] = $intern_filter;
}

if (!empty($date_from)) {
    $sql .= " AND lr.leave_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND lr.leave_date <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY 
            CASE WHEN lr.status = 'pending' THEN 0 ELSE 1 END,
            lr.leave_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leave_requests = $stmt->fetchAll();

// Get counts
$pending_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'pending'; }));
$approved_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'approved'; }));
$rejected_count = count(array_filter($leave_requests, function($r) { return $r['status'] === 'rejected'; }));

// Get all interns for filter
$stmt = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'intern' ORDER BY first_name");
$interns = $stmt->fetchAll();

// Get all supervisors for filter
$stmt = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'supervisor' ORDER BY first_name");
$supervisors = $stmt->fetchAll();

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
    
    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('filter_leave_requests'); ?></h3>
        </div>
        <form method="GET" action="" id="filterForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="status"><?php echo t('status'); ?></label>
                    <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                        <option value=""><?php echo t('all_statuses'); ?></option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                            <?php echo t('pending'); ?>
                        </option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>
                            <?php echo t('approved'); ?>
                        </option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>
                            <?php echo t('rejected'); ?>
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="intern_id"><?php echo t('intern'); ?></label>
                    <select id="intern_id" name="intern_id" class="form-control" onchange="this.form.submit()">
                        <option value=""><?php echo t('all_interns'); ?></option>
                        <?php foreach ($interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>" 
                                    <?php echo $intern_filter == $intern['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from"><?php echo t('date_from'); ?></label>
                    <input type="date" id="date_from" name="date_from" class="form-control" 
                           value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label for="date_to"><?php echo t('date_to'); ?></label>
                    <input type="date" id="date_to" name="date_to" class="form-control" 
                           value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary"><?php echo t('filter'); ?></button>
                <a href="/interntrack/admin/leave.php" class="btn btn-secondary"><?php echo t('clear'); ?></a>
            </div>
        </form>
    </div>
    
    <!-- Leave Requests List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('all_leave_requests'); ?></h3>
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
                            <th><?php echo t('supervisor'); ?></th>
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
                                <td>
                                    <?php if ($request['supervisor_first_name']): ?>
                                        <?php echo htmlspecialchars($request['supervisor_first_name'] . ' ' . $request['supervisor_last_name']); ?>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400);"><?php echo t('not_assigned'); ?></span>
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
                <p style="color: var(--gray-500);"><?php echo t('no_leave_requests_in_system'); ?></p>
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
                    <label for="comment"><?php echo t('admin_comment'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="comment" name="comment" class="form-control" rows="3" 
                              placeholder="<?php echo t('enter_admin_comment'); ?>"></textarea>
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
    const message = action === 'approve' ? '<?php echo t('admin_approve_leave_confirmation'); ?>' : '<?php echo t('admin_reject_leave_confirmation'); ?>';
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