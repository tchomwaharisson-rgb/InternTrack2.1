<?php
// supervisor/dashboard.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);
$today = date('Y-m-d');

// Get assigned interns
$stmt = $conn->prepare("
    SELECT u.*, i.school, i.field_of_study, i.start_date, i.end_date
    FROM users u
    JOIN interns i ON u.id = i.user_id
    WHERE i.supervisor_id = ? AND u.is_active = TRUE
");
$stmt->execute([$user_id]);
$interns = $stmt->fetchAll();

// Get today's status for each intern
$intern_status = [];
$active_count = 0;
foreach ($interns as $intern) {
    $stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = ?");
    $stmt->execute([$intern['id'], $today]);
    $timelog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if $timelog exists before accessing it
    if ($timelog && isset($timelog['clock_in']) && $timelog['clock_in'] && !$timelog['clock_out']) {
        if (isset($timelog['break_start']) && $timelog['break_start'] && !$timelog['break_end']) {
            $status = 'on_break';
        } else {
            $status = 'working';
            $active_count++;
        }
    } elseif ($timelog && isset($timelog['clock_in']) && $timelog['clock_in'] && isset($timelog['clock_out']) && $timelog['clock_out']) {
        $status = 'completed';
    } elseif ($timelog && isset($timelog['clock_in']) && !$timelog['clock_in']) {
        $status = 'missed';
    } else {
        $status = 'not_started';
    }
    
    $intern_status[$intern['id']] = [
        'status' => $status,
        'timelog' => $timelog
    ];
}

// Get pending leave requests
$stmt = $conn->prepare("
    SELECT lr.*, u.first_name, u.last_name, u.email
    FROM leave_requests lr
    JOIN users u ON lr.intern_id = u.id
    WHERE lr.status = 'pending' 
    AND u.id IN (SELECT user_id FROM interns WHERE supervisor_id = ?)
    ORDER BY lr.created_at DESC
");
$stmt->execute([$user_id]);
$pending_leave = $stmt->fetchAll();

// Get unread messages
$stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$unread_messages = (int)$stmt->fetchColumn();

// Get weekly summary
$week_start = date('Y-m-d', strtotime('monday this week'));
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, 
           COALESCE(SUM(tl.total_hours), 0) as total_hours
    FROM users u
    JOIN interns i ON u.id = i.user_id
    LEFT JOIN time_logs tl ON u.id = tl.intern_id AND tl.date BETWEEN ? AND ?
    WHERE i.supervisor_id = ?
    GROUP BY u.id, u.first_name, u.last_name
");
$stmt->execute([$week_start, $today, $user_id]);
$weekly_summary = $stmt->fetchAll();

// Get active goals count
$intern_ids = array_column($interns, 'id');
$active_goals = 0;
if (!empty($intern_ids)) {
    $placeholders = str_repeat('?,', count($intern_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT COUNT(*) FROM goals WHERE intern_id IN ($placeholders) AND status != 'completed'");
    $stmt->execute($intern_ids);
    $active_goals = (int)$stmt->fetchColumn();
}

include_once '../includes/header.php';
?>

<style>
    [data-theme="dark"] .stat-card .stat-value {
        color: white;
    }
</style>

<div class="main-content">
    <!-- Welcome Section -->
    <div class="welcome-section" style="margin-bottom: 24px;">
        <h2 style="font-size: 24px; font-weight: 700; color: var(--gray-800);">
            <?php echo t('welcome_back'); ?>, <?php echo htmlspecialchars($user['first_name']); ?>! 👋
        </h2>
        <p style="color: var(--gray-500);"><?php echo t('supervisor_dashboard_welcome'); ?></p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='/interntrack/supervisor/interns.php'">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo count($interns); ?></div>
            <div class="stat-label"><?php echo t('assigned_interns'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #16a34a;" onclick="window.location.href='/interntrack/supervisor/timelogs.php'">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $active_count; ?></div>
            <div class="stat-label"><?php echo t('active_today'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b; cursor: pointer;" onclick="window.location.href='/interntrack/supervisor/leave.php'">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo count($pending_leave); ?></div>
            <div class="stat-label"><?php echo t('pending_leave'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #3b82f6; cursor: pointer;" onclick="window.location.href='/interntrack/supervisor/chat.php'">
            <div class="stat-icon">💬</div>
            <div class="stat-value"><?php echo $unread_messages; ?></div>
            <div class="stat-label"><?php echo t('unread_messages'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6; cursor: pointer;" onclick="window.location.href='/interntrack/supervisor/goals.php'">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo $active_goals; ?></div>
            <div class="stat-label"><?php echo t('active_goals'); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3><?php echo t('quick_actions'); ?></h3>
        <div class="action-buttons">
            <a href="/interntrack/supervisor/interns.php" class="btn btn-primary">
                <span>👥</span> <?php echo t('view_interns'); ?>
            </a>
            <a href="/interntrack/supervisor/timelogs.php" class="btn btn-secondary">
                <span>⏱️</span> <?php echo t('view_timelogs'); ?>
            </a>
            <a href="/interntrack/supervisor/goals.php" class="btn btn-warning">
                <span>🎯</span> <?php echo t('manage_goals'); ?>
            </a>
            <a href="/interntrack/supervisor/leave.php" class="btn btn-secondary">
                <span>📅</span> <?php echo t('manage_leave'); ?>
            </a>
            <a href="/interntrack/supervisor/chat.php" class="btn btn-secondary">
                <span>💬</span> <?php echo t('send_message'); ?>
            </a>
        </div>
    </div>
    
    <!-- Intern Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('intern_status_today'); ?></h3>
            <span style="font-size: 14px; color: var(--gray-500);"><?php echo date('M d, Y'); ?></span>
        </div>
        <?php if (!empty($interns)): ?>
            <script>console.log('Interns data:', <?php echo json_encode($interns); ?>);</script>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
                            <th><?php echo t('school'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('clock_in'); ?></th>
                            <th><?php echo t('hours'); ?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($interns as $intern): ?>
                            <?php 
                            // Get status with fallback
                            $status_data = isset($intern_status[$intern['id']]) ? $intern_status[$intern['id']] : ['status' => 'not_started', 'timelog' => null];
                            $status = $status_data['status'];
                            $timelog = $status_data['timelog'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        <?php echo htmlspecialchars($intern['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($intern['school'] ?? 'N/A'); ?>
                                    <?php if (!empty($intern['field_of_study'])): ?>
                                        <div style="font-size: 12px; color: var(--gray-500);">
                                            <?php echo htmlspecialchars($intern['field_of_study']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', t($status))); ?>
                                    </span>
                                </td>
                                <td><?php echo (!empty($timelog) && !empty($timelog['clock_in'])) ? formatTime($timelog['clock_in']) : '-'; ?></td>
                                <td><?php echo (!empty($timelog) && isset($timelog['total_hours'])) ? number_format($timelog['total_hours'], 2) : '0.00'; ?></td>
                                <td>
                                    <a href="/interntrack/supervisor/timelogs.php?intern_id=<?php echo $intern['id']; ?>" 
                                       class="btn btn-sm btn-secondary"><?php echo t('view'); ?></a>
                                    <a href="/interntrack/supervisor/chat.php?user_id=<?php echo $intern['id']; ?>" 
                                       class="btn btn-sm btn-primary">💬</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p><?php echo t('no_interns_assigned'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Weekly Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('weekly_hours_summary'); ?></h3>
            <span style="font-size: 14px; color: var(--gray-500);">
                <?php echo date('M d', strtotime($week_start)); ?> - <?php echo date('M d'); ?>
            </span>
        </div>
        <?php if (!empty($weekly_summary)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
                            <th><?php echo t('total_hours'); ?></th>
                            <th><?php echo t('avg_hours_per_day'); ?></th>
                            <th><?php echo t('status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_summary as $summary): ?>
                            <?php 
                            // Ensure values exist with fallbacks
                            $total_hours = isset($summary['total_hours']) ? (float)$summary['total_hours'] : 0;
                            $avg_hours = $total_hours / 5;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($summary['first_name'] . ' ' . $summary['last_name']); ?></td>
                                <td><strong><?php echo number_format($total_hours, 2); ?></strong></td>
                                <td><?php echo number_format($avg_hours, 1); ?></td>
                                <td>
                                    <?php if ($total_hours >= 35): ?>
                                        <span style="color: #4CAF50;">✅ <?php echo t('on_track'); ?></span>
                                    <?php elseif ($total_hours >= 20): ?>
                                        <span style="color: #f59e0b;">⚠️ <?php echo t('partial'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #dc2626;">❌ <?php echo t('below_target'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p><?php echo t('no_weekly_data'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Pending Leave Requests -->
    <?php if (!empty($pending_leave)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('pending_leave_requests'); ?></h3>
                <a href="/interntrack/supervisor/leave.php" class="btn btn-sm btn-secondary"><?php echo t('view_all'); ?></a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
                            <th><?php echo t('type'); ?></th>
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_leave as $leave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                <td><?php echo ucfirst($leave['type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($leave['leave_date'])); ?></td>
                                <td>
                                    <a href="/interntrack/supervisor/leave.php" class="btn btn-sm btn-primary">
                                        <?php echo t('review'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>