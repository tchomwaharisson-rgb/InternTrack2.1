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

// Get all goals for this intern
$stmt = $conn->prepare("
    SELECT g.*, u.first_name, u.last_name 
    FROM goals g
    JOIN users u ON g.supervisor_id = u.id
    WHERE g.intern_id = ?
    ORDER BY g.status ASC, g.end_date ASC
");
$stmt->execute([$user_id]);
$goals = $stmt->fetchAll();

// Get goal updates
$goal_updates = [];
if ($goals) {
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
$completion_rate = $total_goals > 0 ? round(($completed_goals / $total_goals) * 100) : 0;

include_once '../includes/header.php';
?>

<div class="main-content">
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
        
        <?php if ($goals): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('goal_title'); ?></th>
                            <th><?php echo t('supervisor'); ?></th>
                            <th><?php echo t('target_date'); ?></th>
                            <th><?php echo t('progress'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('updates'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                                    <?php if ($goal['description']): ?>
                                        <div style="font-size: 13px; color: var(--gray-500); margin-top: 4px;">
                                            <?php echo nl2br(htmlspecialchars($goal['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($goal['first_name'] . ' ' . $goal['last_name']); ?>
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
                                </td>
                                <td>
                                    <?php if (isset($goal_updates[$goal['id']]) && !empty($goal_updates[$goal['id']])): ?>
                                        <button class="btn btn-sm btn-secondary" onclick="showUpdates(<?php echo $goal['id']; ?>)">
                                            👁️ <?php echo count($goal_updates[$goal['id']]); ?> <?php echo t('updates'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400); font-size: 12px;"><?php echo t('no_updates'); ?></span>
                                    <?php endif; ?>
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
                <p style="color: var(--gray-500);"><?php echo t('no_goals_message'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Goal Updates Modal -->
<div class="modal-overlay" id="updatesModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('goal_progress_updates'); ?></h3>
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
function showUpdates(goalId) {
    // Get updates for this goal from PHP data
    const updates = <?php echo json_encode($goal_updates); ?>;
    const goalUpdates = updates[goalId] || [];
    
    const content = document.getElementById('updatesContent');
    if (goalUpdates.length === 0) {
        content.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;"><?php echo t('no_updates'); ?></p>';
    } else {
        let html = '';
        goalUpdates.forEach(update => {
            html += `
                <div style="padding: 12px; border-bottom: 1px solid var(--gray-200);">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong><?php echo t('progress'); ?>:</strong> ${update.progress}%</span>
                        <span style="color: var(--gray-500); font-size: 12px;">
                            ${new Date(update.created_at).toLocaleDateString()} ${new Date(update.created_at).toLocaleTimeString()}
                        </span>
                    </div>
                    ${update.comment ? `<div style="margin-top: 4px; font-size: 14px; color: var(--gray-600);">"${update.comment}"</div>` : ''}
                </div>
            `;
        });
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
document.querySelector('.modal-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('updatesModal');
        if (modal && modal.classList.contains('show')) {
            closeModal('updatesModal');
        }
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>