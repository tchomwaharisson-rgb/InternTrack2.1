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

// Get filter from URL
$filter = $_GET['filter'] ?? 'pending'; // pending, approved, rejected, all

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

// Build query based on filter
$sql = "
    SELECT lr.*, u.first_name, u.last_name, u.email,
           i.school, i.field_of_study
    FROM leave_requests lr
    JOIN users u ON lr.intern_id = u.id
    JOIN interns i ON u.id = i.user_id
    WHERE i.supervisor_id = ?";
$params = [$user_id];

if ($filter === 'pending') {
    $sql .= " AND lr.status = 'pending'";
} elseif ($filter === 'approved') {
    $sql .= " AND lr.status = 'approved'";
} elseif ($filter === 'rejected') {
    $sql .= " AND lr.status = 'rejected'";
}

$sql .= " ORDER BY 
          CASE WHEN lr.status = 'pending' THEN 0 ELSE 1 END,
          lr.leave_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leave_requests = $stmt->fetchAll();

// Get counts for each status
$stmt = $conn->prepare("
    SELECT lr.status, COUNT(*) as count 
    FROM leave_requests lr
    JOIN interns i ON lr.intern_id = i.user_id
    WHERE i.supervisor_id = ?
    GROUP BY lr.status
");
$stmt->execute([$user_id]);
$status_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['status']] = $row['count'];
}

$pending_count = $status_counts['pending'] ?? 0;
$approved_count = $status_counts['approved'] ?? 0;
$rejected_count = $status_counts['rejected'] ?? 0;

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
    
    <!-- Leave Requests List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                    if ($filter === 'pending') echo t('pending_leave_requests');
                    elseif ($filter === 'approved') echo t('approved_leave_requests');
                    elseif ($filter === 'rejected') echo t('rejected_leave_requests');
                    else echo t('all_leave_requests');
                ?>
            </h3>
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
                <h3>
                    <?php 
                        if ($filter === 'pending') echo t('no_pending_leave_requests');
                        elseif ($filter === 'approved') echo t('no_approved_leave_requests');
                        elseif ($filter === 'rejected') echo t('no_rejected_leave_requests');
                        else echo t('no_leave_requests');
                    ?>
                </h3>
                <p style="color: var(--gray-500);">
                    <?php 
                        if ($filter === 'pending') echo t('no_pending_leave_requests_from_interns');
                        elseif ($filter === 'approved') echo t('no_approved_leave_requests_from_interns');
                        elseif ($filter === 'rejected') echo t('no_rejected_leave_requests_from_interns');
                        else echo t('no_leave_requests_from_interns');
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
            <h3 id="modalTitle"><?php echo t('leave_request_action'); ?></h3>
            <button class="modal-close" onclick="closeModal('actionModal')">&times;</button>
        </div>
        <form method="POST" action="" id="actionForm">
            <input type="hidden" name="request_id" id="action_request_id">
            <input type="hidden" name="action" id="action_type">
            <div class="modal-body">
                <p id="modalMessage" style="margin-bottom: 16px;"></p>
                <div class="form-group">
                    <label for="comment"><?php echo t('supervisor_comment'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="comment" name="comment" class="form-control" rows="3" 
                              placeholder="<?php echo t('enter_supervisor_comment_here'); ?>"></textarea>
                </div>
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
    
    .badge-vacation { background: #3b82f6; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
    .badge-sick { background: #16a34a; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
    .badge-personal { background: #f59e0b; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
    .badge-other { background: #6b7280; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
    
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
    document.getElementById('action_request_id').value = requestId;
    document.getElementById('action_type').value = action;
    
    const title = action === 'approve' ? '✅ Approve Leave Request' : '❌ Reject Leave Request';
    const message = action === 'approve' 
        ? 'Are you sure you want to approve this leave request?'
        : 'Are you sure you want to reject this leave request?';
    const btnText = action === 'approve' ? 'Approve' : 'Reject';
    const btnClass = action === 'approve' ? 'btn-success' : 'btn-danger';
    
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('confirmBtn').textContent = btnText;
    document.getElementById('confirmBtn').className = 'btn ' + btnClass;
    
    document.getElementById('comment').value = '';
    
    document.getElementById('actionModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

document.querySelector('.modal-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('show');
        document.body.style.overflow = '';
    }
});

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