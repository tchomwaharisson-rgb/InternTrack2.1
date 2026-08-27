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
            $intern['today_status'] = t('on_break');
        } else {
            $intern['today_status'] = t('working');
        }
        $intern['today_log'] = $log;
    } elseif ($log && $log['clock_in'] && $log['clock_out']) {
        $intern['today_status'] = t('completed');
        $intern['today_log'] = $log;
    } elseif ($log && !$log['clock_in']) {
        $intern['today_status'] = t('missed');
        $intern['today_log'] = $log;
    } else {
        $intern['today_status'] = t('not_started');
        $intern['today_log'] = null;
    }
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('assigned_interns'); ?></h3>
            <span><?php echo count($interns); ?> <?php echo t('interns'); ?></span>
        </div>
        
         <?php if (!empty($interns)):?>            
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
                         <script>console.log('Interns data:', <?php echo json_encode($interns); ?>);</script>
                            
                        <?php for ($i = 0; $i < count($interns); $i++): ?>
                             <script>console.log('Interns data:', <?php echo json_encode($interns); ?>);</script>
                            
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($interns[$i]['first_name'] . ' ' . $interns[$i]['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary-text);">
                                        <?php echo htmlspecialchars($interns[$i]['email']); ?>
                                    </div>
                                    <?php if ($interns[$i]['start_date'] && $interns[$i]['end_date']): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo date('M d, Y', strtotime($interns[$i]['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($interns[$i]['end_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($interns[$i]['school'] ?? 'N/A'); ?>
                                    <?php if ($interns[$i]['field_of_study']): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo htmlspecialchars($interns[$i]['field_of_study']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $interns[$i]['today_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $interns[$i]['today_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        if ($interns[$i]['today_log'] && $interns[$i]['today_log']['total_hours'] > 0) {
                                            echo number_format($interns[$i]['today_log']['total_hours'], 2) . 'h';
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php echo $interns[$i]['active_goals']; ?>
                                    <?php if ($interns[$i]['active_goals'] > 0): ?>
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
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                <h3><?php echo t('no_interns_assigned'); ?></h3>
                <p style="color: var(--secondary-text);"><?php echo t('no_interns_assigned_message'); ?></p>
                <p style="color: var(--secondary-text); font-size: 14px;"><?php echo t('contact_admin_to_assign_interns'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>