<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get assigned interns with their details
$stmt = $conn->prepare("
    SELECT u.*, i.school, i.field_of_study, i.start_date, i.end_date,
           (SELECT COUNT(*) FROM goals WHERE intern_id = u.id AND status != 'completed') as active_goals,
           (SELECT COUNT(*) FROM time_logs WHERE intern_id = u.id AND date = CURDATE() AND clock_in IS NOT NULL) as clocked_in_today
    FROM users u
    JOIN interns i ON u.id = i.user_id
    WHERE i.supervisor_id = ? AND u.is_active = TRUE
    ORDER BY u.first_name
");
$stmt->execute([$user_id]);
$interns = $stmt->fetchAll();

// Get today's status for each intern
foreach ($interns as &$intern) {
    $stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = CURDATE()");
    $stmt->execute([$intern['id']]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($log && $log['clock_in'] && !$log['clock_out']) {
        if ($log['break_start'] && !$log['break_end']) {
            $intern['today_status'] = 'on_break';
        } else {
            $intern['today_status'] = 'working';
        }
        $intern['today_log'] = $log;
    } elseif ($log && $log['clock_in'] && $log['clock_out']) {
        $intern['today_status'] = 'completed';
        $intern['today_log'] = $log;
    } elseif ($log && !$log['clock_in']) {
        $intern['today_status'] = 'missed';
        $intern['today_log'] = $log;
    } else {
        $intern['today_status'] = 'not_started';
        $intern['today_log'] = null;
    }
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('assigned_interns'); ?></h3>
            <span><?php echo count($interns); ?> interns</span>
        </div>
        
        <?php if ($interns): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>School</th>
                            <th>Status Today</th>
                            <th>Hours Today</th>
                            <th>Active Goals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($interns as $intern): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary-text);">
                                        <?php echo htmlspecialchars($intern['email']); ?>
                                    </div>
                                    <?php if ($intern['start_date'] && $intern['end_date']): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo date('M d, Y', strtotime($intern['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($intern['end_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($intern['school'] ?? 'N/A'); ?>
                                    <?php if ($intern['field_of_study']): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo htmlspecialchars($intern['field_of_study']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $intern['today_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $intern['today_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        if ($intern['today_log'] && $intern['today_log']['total_hours'] > 0) {
                                            echo number_format($intern['today_log']['total_hours'], 2) . 'h';
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php echo $intern['active_goals']; ?>
                                    <?php if ($intern['active_goals'] > 0): ?>
                                        <span style="font-size: 12px; color: var(--secondary-text);">active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <a href="/interntrack/supervisor/timelogs.php?intern_id=<?php echo $intern['id']; ?>" 
                                           class="btn btn-sm btn-secondary"><?php echo t('view'); ?></a>
                                        <a href="/interntrack/supervisor/chat.php?user_id=<?php echo $intern['id']; ?>" 
                                           class="btn btn-sm btn-primary">Chat</a>
                                        <a href="/interntrack/supervisor/goals.php?intern_id=<?php echo $intern['id']; ?>" 
                                           class="btn btn-sm btn-warning">Goals</a>
                                        <a href="/interntrack/profile.php?user_id=<?php echo $intern['id']; ?>" 
                                           class="btn btn-sm btn-secondary">Profile</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                <h3>No Interns Assigned</h3>
                <p style="color: var(--secondary-text);">You haven't been assigned any interns yet.</p>
                <p style="color: var(--secondary-text); font-size: 14px;">Contact the administrator to get interns assigned to you.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>