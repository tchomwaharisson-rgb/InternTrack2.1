<?php
// supervisor/goals.php
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

// Get assigned interns
$stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name FROM users u 
                        JOIN interns i ON u.id = i.user_id 
                        WHERE i.supervisor_id = ? AND u.is_active = TRUE");
$stmt->execute([$user_id]);
$interns = $stmt->fetchAll();

// Handle goal actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $intern_id = $_POST['intern_id'] ?? 0;
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $end_date = $_POST['end_date'] ?? '';
            
            if (empty($title) || empty($end_date)) {
                $message = t('title_end_date_required');
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO goals (intern_id, supervisor_id, title, description, end_date) 
                                       VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$intern_id, $user_id, $title, $description, $end_date])) {
                    $goal_id = $conn->lastInsertId();
                    $message = t('goal_created');
                    $message_type = 'success';
                    logAudit($user_id, 'create_goal', 'Created goal for intern ' . $intern_id);
                    
                    // Notify intern
                    if ($intern_id) {
                        $intern = getUserData($intern_id);
                        if ($intern) {
                            createNotification($intern_id, 'goal_created', 
                                               t('notification_goal_created', ['title' => $title]),
                                               '/interntrack/intern/goals.php');
                        }
                    }
                } else {
                    $message = t('error_occurred');
                    $message_type = 'error';
                }
            }
            break;
            
        case 'update_progress':
            $goal_id = $_POST['goal_id'] ?? 0;
            $progress = (int)$_POST['progress'] ?? 0;
            $comment = sanitize($_POST['comment'] ?? '');
            
            // Get goal details first for notification
            $stmt = $conn->prepare("SELECT title, intern_id FROM goals WHERE id = ? AND supervisor_id = ?");
            $stmt->execute([$goal_id, $user_id]);
            $goal_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($goal_data) {
                $title = $goal_data['title'];
                $intern_id = $goal_data['intern_id'];
                
                $stmt = $conn->prepare("UPDATE goals SET progress = ?, status = ? WHERE id = ? AND supervisor_id = ?");
                $status = $progress >= 100 ? 'completed' : 'in_progress';
                if ($stmt->execute([$progress, $status, $goal_id, $user_id])) {
                    // Add goal update
                    $stmt = $conn->prepare("INSERT INTO goal_updates (goal_id, progress, comment) VALUES (?, ?, ?)");
                    $stmt->execute([$goal_id, $progress, $comment]);
                    
                    $message = t('goal_updated');
                    $message_type = 'success';
                    logAudit($user_id, 'update_goal', 'Updated goal ' . $goal_id . ' to ' . $progress . '%');
                    
                    // Notify intern
                    if ($intern_id) {
                        $intern = getUserData($intern_id);
                        if ($intern) {
                            createNotification($intern_id, 'goal_update', 
                                               t('notification_goal_updated_progress', ['title' => $title, 'progress' => $progress]),
                                               '/interntrack/intern/goals.php');
                        }
                    }
                } else {
                    $message = t('error_occurred');
                    $message_type = 'error';
                }
            } else {
                $message = t('goal_not_found');
                $message_type = 'error';
            }
            break;
            
        case 'delete':
            $goal_id = $_POST['goal_id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM goals WHERE id = ? AND supervisor_id = ?");
            if ($stmt->execute([$goal_id, $user_id])) {
                $message = t('goal_deleted');
                $message_type = 'success';
                logAudit($user_id, 'delete_goal', 'Deleted goal ' . $goal_id);
            } else {
                $message = t('error_occurred');
                $message_type = 'error';
            }
            break;
    }
}

// Get all goals for assigned interns
$intern_ids = array_column($interns, 'id');
if (empty($intern_ids)) {
    $intern_ids = [0];
}
$placeholders = str_repeat('?,', count($intern_ids) - 1) . '?';

$stmt = $conn->prepare("
    SELECT g.*, u.first_name, u.last_name, u.email
    FROM goals g
    JOIN users u ON g.intern_id = u.id
    WHERE g.intern_id IN ($placeholders)
    ORDER BY g.end_date ASC, g.status ASC
");
$stmt->execute($intern_ids);
$goals = $stmt->fetchAll();

// Get statistics
$total_goals = count($goals);
$completed_goals = count(array_filter($goals, function($g) { return $g['status'] === 'completed'; }));
$active_goals = count(array_filter($goals, function($g) { return $g['status'] === 'in_progress' || $g['status'] === 'pending'; }));
$overdue_goals = count(array_filter($goals, function($g) { return $g['status'] === 'overdue'; }));

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
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo $total_goals; ?></div>
            <div class="stat-label"><?php echo t('total_goals'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b;">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo $active_goals; ?></div>
            <div class="stat-label"><?php echo t('active_goals'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #16a34a;">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $completed_goals; ?></div>
            <div class="stat-label"><?php echo t('completed_goals'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #dc2626;">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?php echo $overdue_goals; ?></div>
            <div class="stat-label"><?php echo t('overdue_goals'); ?></div>
        </div>
    </div>
    
    <!-- Create Goal -->
    <?php if ($interns): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('set_goal'); ?></h3>
            </div>
            <form method="POST" action="" id="createGoalForm">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="intern_id"><?php echo t('intern'); ?></label>
                        <select id="intern_id" name="intern_id" class="form-control" required>
                            <option value=""><?php echo t('select_intern'); ?></option>
                            <?php foreach ($interns as $intern): ?>
                                <option value="<?php echo $intern['id']; ?>">
                                    <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="end_date"><?php echo t('target_date'); ?></label>
                        <input type="date" id="end_date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="title"><?php echo t('goal_title'); ?></label>
                    <input type="text" id="title" name="title" class="form-control" required 
                           placeholder="<?php echo t('enter_goal_title'); ?>">
                </div>
                <div class="form-group">
                    <label for="description"><?php echo t('goal_description'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="<?php echo t('enter_goal_description'); ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo t('set_goal'); ?></button>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('set_goal'); ?></h3>
            </div>
            <div style="text-align: center; padding: 30px 0;">
                <div style="font-size: 36px; margin-bottom: 12px;">👥</div>
                <p style="color: var(--gray-500);"><?php echo t('no_interns_to_set_goals'); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Goals List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('goals_for_interns'); ?></h3>
            <span style="font-size: 14px; color: var(--gray-500);">
                <?php echo $total_goals; ?> <?php echo t('goals'); ?>
            </span>
        </div>
        <?php if ($goals): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
                            <th><?php echo t('goal_title'); ?></th>
                            <th><?php echo t('target_date'); ?></th>
                            <th><?php echo t('progress'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['first_name'] . ' ' . $goal['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        <?php echo htmlspecialchars($goal['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                                    <?php if ($goal['description']): ?>
                                        <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">
                                            <?php echo nl2br(htmlspecialchars($goal['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($goal['end_date'])); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; background: var(--gray-200); border-radius: 4px; height: 8px; overflow: hidden;">
                                            <div style="background: var(--primary-color); height: 100%; width: <?php echo $goal['progress']; ?>%;"></div>
                                        </div>
                                        <span style="font-size: 12px; font-weight: 600; min-width: 40px;">
                                            <?php echo $goal['progress']; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $goal['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $goal['status'])); ?>
                                    </span>
                                    <?php if ($goal['status'] === 'overdue'): ?>
                                        <div style="font-size: 11px; color: #dc2626;">⚠️ <?php echo t('overdue'); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <button class="btn btn-sm btn-primary" onclick="openUpdateProgress(<?php echo $goal['id']; ?>, <?php echo $goal['progress']; ?>)">
                                            📈 <?php echo t('update'); ?>
                                        </button>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?php echo t('delete_goal_confirmation'); ?>')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                🗑️ <?php echo t('delete'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                <h3><?php echo t('no_goals'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_goals_for_interns_message'); ?></p>
                <?php if ($interns): ?>
                    <p style="color: var(--gray-400); font-size: 14px; margin-top: 8px;">
                        <?php echo t('create_first_goal'); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal-overlay" id="progressModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('update_progress'); ?></h3>
            <button class="modal-close" onclick="closeModal('progressModal')">&times;</button>
        </div>
        <form method="POST" action="" id="updateProgressForm">
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" name="goal_id" id="update_goal_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="progress"><?php echo t('progress'); ?> (%)</label>
                    <input type="number" id="update_progress" name="progress" class="form-control" min="0" max="100" required>
                    <small style="color: var(--gray-500); font-size: 12px;"><?php echo t('progress_help_text'); ?></small>
                </div>
                <div class="form-group">
                    <label for="comment"><?php echo t('add_comment'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="update_comment" name="comment" class="form-control" rows="3" 
                              placeholder="<?php echo t('enter_comment'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('progressModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo t('update_progress'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
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
    
    .modal-footer .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }
    
    .modal-footer .btn-secondary:hover {
        background: var(--gray-300);
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
    
    body.dark-mode .modal-footer .btn-secondary {
        background: #4a4a4a;
        color: #f3f4f6;
    }
    
    body.dark-mode .modal-footer .btn-secondary:hover {
        background: #5a5a5a;
    }
</style>

<script>
function openUpdateProgress(goalId, currentProgress) {
    document.getElementById('update_goal_id').value = goalId;
    document.getElementById('update_progress').value = currentProgress;
    document.getElementById('progressModal').classList.add('show');
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
        const modal = document.getElementById('progressModal');
        if (modal && modal.classList.contains('show')) {
            closeModal('progressModal');
        }
    }
});

// Form validation for create goal
document.getElementById('createGoalForm')?.addEventListener('submit', function(e) {
    const internId = document.getElementById('intern_id').value;
    const endDate = document.getElementById('end_date').value;
    const title = document.getElementById('title').value.trim();
    
    if (!internId) {
        e.preventDefault();
        showToast('<?php echo t('select_intern_error'); ?>', 'error');
        return false;
    }
    
    if (!endDate) {
        e.preventDefault();
        showToast('<?php echo t('select_target_date_error'); ?>', 'error');
        return false;
    }
    
    if (!title) {
        e.preventDefault();
        showToast('<?php echo t('enter_goal_title_error'); ?>', 'error');
        return false;
    }
});

function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container') || (() => {
        const el = document.createElement('div');
        el.className = 'toast-container';
        document.body.appendChild(el);
        return el;
    })();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
</script>

<?php include_once '../includes/footer.php'; ?>