<?php
require_once '../config/config.php';
require_once '../config/language.php';

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

include_once '../includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('goals'); ?></h3>
            <div>
                <span style="font-size: 14px; color: var(--secondary-text);">
                    <?php 
                        $completed = array_filter($goals, function($g) { return $g['status'] === 'completed'; });
                        $total = count($goals);
                        echo $total > 0 ? round((count($completed) / $total) * 100) . '% completed' : 'No goals set';
                    ?>
                </span>
            </div>
        </div>
        
        <?php if ($goals): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Goal</th>
                            <th>Supervisor</th>
                            <th>Target Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Updates</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                                    <?php if ($goal['description']): ?>
                                        <div style="font-size: 13px; color: var(--secondary-text); margin-top: 4px;">
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
                                        <div style="flex: 1; background: var(--primary-gray); border-radius: 4px; height: 8px; overflow: hidden;">
                                            <div style="background: var(--primary-red); height: 100%; width: <?php echo $goal['progress']; ?>%;"></div>
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
                                            View (<?php echo count($goal_updates[$goal['id']]); ?>)
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--secondary-text);">No updates</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No goals set yet. Your supervisor will assign goals for your internship.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Goal Updates Modal -->
<div class="modal-overlay" id="updatesModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Goal Progress Updates</h3>
            <button class="modal-close" onclick="closeModal('updatesModal')">&times;</button>
        </div>
        <div class="modal-body" id="updatesContent">
            <!-- Dynamic content -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('updatesModal')">Close</button>
        </div>
    </div>
</div>

<script>
function showUpdates(goalId) {
    // Get updates for this goal from PHP data
    const updates = <?php echo json_encode($goal_updates); ?>;
    const goalUpdates = updates[goalId] || [];
    
    const content = document.getElementById('updatesContent');
    if (goalUpdates.length === 0) {
        content.innerHTML = '<p>No updates yet.</p>';
    } else {
        let html = '';
        goalUpdates.forEach(update => {
            html += `
                <div style="padding: 12px; border-bottom: 1px solid var(--primary-gray-dark);">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Progress:</strong> ${update.progress}%</span>
                        <span style="color: var(--secondary-text); font-size: 12px;">
                            ${new Date(update.created_at).toLocaleDateString()} ${new Date(update.created_at).toLocaleTimeString()}
                        </span>
                    </div>
                    ${update.comment ? `<div style="margin-top: 4px; font-size: 14px; color: var(--secondary-text);">"${update.comment}"</div>` : ''}
                </div>
            `;
        });
        content.innerHTML = html;
    }
    
    document.getElementById('updatesModal').classList.add('show');
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