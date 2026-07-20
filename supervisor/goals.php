<?php
require_once '../config/config.php';
require_once '../config/language.php';

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
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            
            if (empty($title) || empty($end_date)) {
                $message = 'Title and end date are required';
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
                                               'New goal "' . $title . '" has been assigned to you',
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
            $progress = $_POST['progress'] ?? 0;
            $comment = $_POST['comment'] ?? '';
            
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
                                               'Goal "' . $title . '" progress updated to ' . $progress . '%',
                                               '/interntrack/intern/goals.php');
                        }
                    }
                } else {
                    $message = t('error_occurred');
                    $message_type = 'error';
                }
            } else {
                $message = 'Goal not found or you don\'t have permission';
                $message_type = 'error';
            }
            break;
            
        case 'delete':
            $goal_id = $_POST['goal_id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM goals WHERE id = ? AND supervisor_id = ?");
            if ($stmt->execute([$goal_id, $user_id])) {
                $message = 'Goal deleted successfully';
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

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Create Goal -->
    <?php if ($interns): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Set New Goal</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="intern_id">Intern</label>
                        <select id="intern_id" name="intern_id" class="form-control" required>
                            <option value="">Select Intern</option>
                            <?php foreach ($interns as $intern): ?>
                                <option value="<?php echo $intern['id']; ?>">
                                    <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Target Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="title">Goal Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Set Goal</button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- Goals List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Goals for Assigned Interns</h3>
            <span><?php echo count($goals); ?> goals</span>
        </div>
        <?php if ($goals): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>Goal</th>
                            <th>Target Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['first_name'] . ' ' . $goal['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary-text);">
                                        <?php echo htmlspecialchars($goal['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                                    <?php if ($goal['description']): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text); margin-top: 4px;">
                                            <?php echo nl2br(htmlspecialchars($goal['description'])); ?>
                                        </div>
                                    <?php endif; ?>
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
                                    <?php if ($goal['status'] === 'overdue'): ?>
                                        <div style="font-size: 11px; color: #f44336;">⚠️ Overdue</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="openUpdateProgress(<?php echo $goal['id']; ?>, <?php echo $goal['progress']; ?>)">
                                        Update
                                    </button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this goal?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                <h3>No Goals Set</h3>
                <p style="color: var(--secondary-text);">You haven't set any goals for your interns yet.</p>
                <p style="color: var(--secondary-text); font-size: 14px;">Use the form above to create goals for your assigned interns.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal-overlay" id="progressModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Update Goal Progress</h3>
            <button class="modal-close" onclick="closeModal('progressModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" name="goal_id" id="update_goal_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="progress">Progress (%)</label>
                    <input type="number" id="update_progress" name="progress" class="form-control" min="0" max="100" required>
                    <small style="color: var(--secondary-text);">0% = Not started, 100% = Completed</small>
                </div>
                <div class="form-group">
                    <label for="comment">Comment</label>
                    <textarea id="update_comment" name="comment" class="form-control" rows="3" placeholder="Add a comment about the progress..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('progressModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Progress</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateProgress(goalId, currentProgress) {
    document.getElementById('update_goal_id').value = goalId;
    document.getElementById('update_progress').value = currentProgress;
    document.getElementById('progressModal').classList.add('show');
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