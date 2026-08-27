<?php
// intern/goals.php
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

// Handle goal update submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_progress') {
        $goal_id = (int)$_POST['goal_id'] ?? 0;
        $progress = (int)$_POST['progress'] ?? 0;
        $comment = sanitize($_POST['comment'] ?? '');
        
        // Verify the goal belongs to this intern
        $stmt = $conn->prepare("SELECT id, title, supervisor_id FROM goals WHERE id = ? AND intern_id = ?");
        $stmt->execute([$goal_id, $user_id]);
        $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($goal) {
            // Validate progress
            if ($progress < 0 || $progress > 100) {
                $message = t('progress_invalid');
                $message_type = 'error';
            } else {
                // Update goal progress
                $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'pending');
                $stmt = $conn->prepare("UPDATE goals SET progress = ?, status = ? WHERE id = ?");
                if ($stmt->execute([$progress, $status, $goal_id])) {
                    // Add goal update/improvement record
                    $stmt = $conn->prepare("INSERT INTO goal_updates (goal_id, progress, comment) VALUES (?, ?, ?)");
                    $stmt->execute([$goal_id, $progress, $comment]);
                    
                    $message = t('goal_updated');
                    $message_type = 'success';
                    logAudit($user_id, 'update_goal_progress', 'Updated goal ' . $goal_id . ' to ' . $progress . '%');
                    
                    // Notify supervisor
                    if ($goal['supervisor_id']) {
                        $supervisor = getUserData($goal['supervisor_id']);
                        if ($supervisor) {
                            $notification = 'Intern has updated goal "' . $goal['title'] . '" progress to ' . $progress . '%';
                            createNotification($goal['supervisor_id'], 'goal_update', $notification, '/interntrack/supervisor/goals.php');
                        }
                    }
                } else {
                    $message = t('error_occurred');
                    $message_type = 'error';
                }
            }
        } else {
            $message = t('goal_not_found');
            $message_type = 'error';
        }
    } elseif ($action === 'add_improvement') {
        $goal_id = (int)$_POST['goal_id'] ?? 0;
        $improvement = sanitize($_POST['improvement'] ?? '');
        $progress = (int)$_POST['progress'] ?? 0;
        
        if (empty($improvement)) {
            $message = t('improvement_required');
            $message_type = 'error';
        } else {
            // Verify the goal belongs to this intern
            $stmt = $conn->prepare("SELECT id, title, supervisor_id FROM goals WHERE id = ? AND intern_id = ?");
            $stmt->execute([$goal_id, $user_id]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($goal) {
                // If progress is provided, update it too
                if ($progress > 0 && $progress <= 100) {
                    $status = $progress >= 100 ? 'completed' : 'in_progress';
                    $stmt = $conn->prepare("UPDATE goals SET progress = ?, status = ? WHERE id = ?");
                    $stmt->execute([$progress, $status, $goal_id]);
                }
                
                // Add improvement as a goal update
                $stmt = $conn->prepare("INSERT INTO goal_updates (goal_id, progress, comment) VALUES (?, ?, ?)");
                if ($stmt->execute([$goal_id, $progress, $improvement])) {
                    $message = t('improvement_added');
                    $message_type = 'success';
                    logAudit($user_id, 'add_goal_improvement', 'Added improvement to goal ' . $goal_id);
                    
                    // Notify supervisor
                    if ($goal['supervisor_id']) {
                        $supervisor = getUserData($goal['supervisor_id']);
                        if ($supervisor) {
                            $notification = 'Intern added improvement to goal "' . $goal['title'] . '"';
                            createNotification($goal['supervisor_id'], 'goal_improvement', $notification, '/interntrack/supervisor/goals.php');
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
        }
    }
}

// Get all goals for this intern
$stmt = $conn->prepare("
    SELECT g.*, u.first_name, u.last_name 
    FROM goals g
    JOIN users u ON g.supervisor_id = u.id
    WHERE g.intern_id = ?
    ORDER BY 
        CASE 
            WHEN g.status = 'completed' THEN 3
            WHEN g.status = 'overdue' THEN 2
            WHEN g.status = 'in_progress' THEN 1
            ELSE 0
        END,
        g.end_date ASC
");
$stmt->execute([$user_id]);
$goals = $stmt->fetchAll();

// Get goal updates/improvements
$goal_updates = [];
if (!empty($goals)) {
    $goal_ids = array_column($goals, 'id');
    $placeholders = str_repeat('?,', count($goal_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT * FROM goal_updates WHERE goal_id IN ($placeholders) ORDER BY created_at DESC");
    $stmt->execute($goal_ids);
    $updates = $stmt->fetchAll();
    foreach ($updates as $update) {
        $goal_updates[$update['goal_id']][] = $update;
    }
}

// Get statistics
$total_goals = count($goals);
$completed_goals = count(array_filter($goals, function($g) { return $g['status'] === 'completed'; }));
$active_goals = count(array_filter($goals, function($g) { return $g['status'] === 'in_progress' || $g['status'] === 'pending'; }));
$overdue_goals = count(array_filter($goals, function($g) { return $g['status'] === 'overdue'; }));
$completion_rate = $total_goals > 0 ? round(($completed_goals / $total_goals) * 100) : 0;

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
        <div class="stat-card" style="border-left-color: #3b82f6;">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo $completion_rate; ?>%</div>
            <div class="stat-label"><?php echo t('completion_rate'); ?></div>
        </div>
        <?php if ($overdue_goals > 0): ?>
            <div class="stat-card" style="border-left-color: #dc2626;">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value"><?php echo $overdue_goals; ?></div>
                <div class="stat-label"><?php echo t('overdue_goals'); ?></div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('goals'); ?></h3>
            <div>
                <span style="font-size: 14px; color: var(--gray-500);">
                    <?php echo $total_goals; ?> <?php echo t('goals'); ?> • 
                    <?php echo $completed_goals; ?> <?php echo t('completed'); ?>
                </span>
            </div>
        </div>
        
        <?php if (!empty($goals)): ?>
            <div class="goals-container">
                <?php foreach ($goals as $goal): ?>
                    <div class="goal-card" style="background: var(--white); border-radius: var(--border-radius); padding: 20px; margin-bottom: 16px; box-shadow: var(--shadow-sm); border-left: 4px solid <?php 
                        echo $goal['status'] === 'completed' ? '#16a34a' : 
                            ($goal['status'] === 'overdue' ? '#dc2626' : 
                            ($goal['status'] === 'in_progress' ? '#3b82f6' : '#f59e0b')); 
                    ?>; transition: all var(--transition-speed);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                            <div style="flex: 1; min-width: 200px;">
                                <h4 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 600; color: var(--gray-800);">
                                    <?php echo htmlspecialchars($goal['title']); ?>
                                </h4>
                                <div style="font-size: 13px; color: var(--gray-500);">
                                    <?php echo t('assigned_by'); ?>: <?php echo htmlspecialchars($goal['first_name'] . ' ' . $goal['last_name']); ?>
                                    • <?php echo t('target_date'); ?>: <?php echo date('M d, Y', strtotime($goal['end_date'])); ?>
                                </div>
                                <?php if (!empty($goal['description'])): ?>
                                    <div style="font-size: 14px; color: var(--gray-600); margin-top: 8px; padding: 8px 12px; background: var(--gray-50); border-radius: 6px;">
                                        <?php echo nl2br(htmlspecialchars($goal['description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <span class="status-badge <?php echo $goal['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', t($goal['status']))); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div style="margin-top: 16px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--gray-600); margin-bottom: 4px;">
                                <span><?php echo t('progress'); ?></span>
                                <span><strong><?php echo $goal['progress']; ?>%</strong></span>
                            </div>
                            <div style="background: var(--gray-200); border-radius: 6px; height: 10px; overflow: hidden; position: relative;">
                                <div style="background: <?php 
                                    echo $goal['progress'] >= 100 ? '#16a34a' : 
                                        ($goal['progress'] >= 50 ? '#3b82f6' : '#f59e0b'); 
                                ?>; height: 100%; width: <?php echo $goal['progress']; ?>%; transition: width 0.5s ease;"></div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap;">
                            <button class="btn btn-sm btn-primary" onclick="openUpdateModal(<?php echo $goal['id']; ?>, <?php echo $goal['progress']; ?>)">
                                📈 <?php echo t('update_progress'); ?>
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="openImprovementModal(<?php echo $goal['id']; ?>, <?php echo $goal['progress']; ?>)">
                                💡 <?php echo t('add_improvement'); ?>
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="showUpdates(<?php echo $goal['id']; ?>)">
                                👁️ <?php echo t('view_updates'); ?> 
                                <?php if (isset($goal_updates[$goal['id']])): ?>
                                    (<?php echo count($goal_updates[$goal['id']]); ?>)
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                <h3><?php echo t('no_goals'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_goals_message'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal-overlay" id="updateModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('update_progress'); ?></h3>
            <button class="modal-close" onclick="closeModal('updateModal')">&times;</button>
        </div>
        <form method="POST" action="" id="updateForm">
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" name="goal_id" id="update_goal_id">
            <div class="modal-body">
                <p style="color: var(--gray-600); margin-bottom: 16px;">
                    <?php echo t('update_progress_help'); ?>
                </p>
                <div class="form-group">
                    <label for="progress"><?php echo t('progress'); ?> (%)</label>
                    <input type="number" id="update_progress" name="progress" class="form-control" min="0" max="100" required>
                    <small style="color: var(--gray-500); font-size: 12px;"><?php echo t('progress_help_text'); ?></small>
                </div>
                <div class="form-group">
                    <label for="comment"><?php echo t('add_comment'); ?> (<?php echo t('optional'); ?>)</label>
                    <textarea id="update_comment" name="comment" class="form-control" rows="3" 
                              placeholder="<?php echo t('enter_comment_about_progress'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo t('update_progress'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Add Improvement Modal -->
<div class="modal-overlay" id="improvementModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('add_improvement'); ?></h3>
            <button class="modal-close" onclick="closeModal('improvementModal')">&times;</button>
        </div>
        <form method="POST" action="" id="improvementForm">
            <input type="hidden" name="action" value="add_improvement">
            <input type="hidden" name="goal_id" id="improvement_goal_id">
            <div class="modal-body">
                <p style="color: var(--gray-600); margin-bottom: 16px;">
                    <?php echo t('add_improvement_help'); ?>
                </p>
                <div class="form-group">
                    <label for="improvement_progress"><?php echo t('progress'); ?> (%) (<?php echo t('optional'); ?>)</label>
                    <input type="number" id="improvement_progress" name="progress" class="form-control" min="0" max="100">
                    <small style="color: var(--gray-500); font-size: 12px;"><?php echo t('progress_help_text'); ?></small>
                </div>
                <div class="form-group">
                    <label for="improvement"><?php echo t('improvement_details'); ?> <span style="color: #dc2626;">*</span></label>
                    <textarea id="improvement" name="improvement" class="form-control" rows="4" required
                              placeholder="<?php echo t('describe_improvement'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('improvementModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary" style="color: #dc2626;"><?php echo t('add_improvement'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- View Updates Modal -->
<div class="modal-overlay" id="updatesModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('goal_updates_history'); ?></h3>
            <button class="modal-close" onclick="closeModal('updatesModal')">&times;</button>
        </div>
        <div class="modal-body" id="updatesContent">
            <!-- Dynamic content -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('updatesModal')"><?php echo t('close'); ?></button>
        </div>
    </div>
</div>

<!-- Modal Styles -->
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
        max-width: 550px;
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
        color:#dc2626;
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
    
    .goal-card {
        transition: all 0.3s ease;
    }
    
    .goal-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    /* Dark Mode Support */
    body.dark-mode .goal-card {
        background: #2d2d2d !important;
    }
    
    body.dark-mode .goal-card h4 {
        color: #f3f4f6;
    }
    
    body.dark-mode .goal-card .goal-description {
        background: #3a3a3a;
        color: #e5e7eb;
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
    
    body.dark-mode .modal-body p {
        color: #9ca3af !important;
    }
    
    body.dark-mode .update-item {
        border-bottom-color: #4a1a1a;
    }
    
    body.dark-mode .update-item .update-comment {
        color: #e5e7eb;
    }
    
    body.dark-mode .update-item .update-meta {
        color: #9ca3af;
    }
</style>

<script>
function openUpdateModal(goalId, currentProgress) {
    document.getElementById('update_goal_id').value = goalId;
    document.getElementById('update_progress').value = currentProgress;
    document.getElementById('update_comment').value = '';
    document.getElementById('updateModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function openImprovementModal(goalId, currentProgress) {
    document.getElementById('improvement_goal_id').value = goalId;
    document.getElementById('improvement_progress').value = currentProgress;
    document.getElementById('improvement').value = '';
    document.getElementById('improvementModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function showUpdates(goalId) {
    const updates = <?php echo json_encode($goal_updates); ?>;
    const goalUpdates = updates[goalId] || [];
    
    const content = document.getElementById('updatesContent');
    if (goalUpdates.length === 0) {
        content.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;"><?php echo t('no_updates'); ?></p>';
    } else {
        let html = '<div class="updates-timeline">';
        goalUpdates.forEach(update => {
            const date = new Date(update.created_at);
            html += `
                <div class="update-item" style="padding: 12px; border-bottom: 1px solid var(--gray-200);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-weight: 600; color: var(--primary-color);">${update.progress}%</span>
                            <span style="color: var(--gray-500); font-size: 13px; margin-left: 8px;"><?php echo t('progress'); ?></span>
                        </div>
                        <span style="color: var(--gray-500); font-size: 12px;">
                            ${date.toLocaleDateString()} ${date.toLocaleTimeString()}
                        </span>
                    </div>
                    ${update.comment ? `<div class="update-comment" style="margin-top: 6px; font-size: 14px; color: var(--gray-600);">"${update.comment}"</div>` : ''}
                </div>
            `;
        });
        html += '</div>';
        content.innerHTML = html;
    }
    
    document.getElementById('updatesModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on outside click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

// Form validation for improvement
document.getElementById('improvementForm')?.addEventListener('submit', function(e) {
    const improvement = document.getElementById('improvement').value.trim();
    if (!improvement) {
        e.preventDefault();
        showToast('<?php echo t('improvement_required'); ?>', 'error');
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