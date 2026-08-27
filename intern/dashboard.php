<?php
// intern/dashboard.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('intern')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);
$today = date('Y-m-d');

// Get today's timelog
$stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$today_timelog = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current status
$status = 'not_started';
if ($today_timelog) {
    if ($today_timelog['clock_in'] && !$today_timelog['clock_out']) {
        if ($today_timelog['break_start'] && !$today_timelog['break_end']) {
            $status = 'on_break';
        } else {
            $status = 'working';
        }
    } elseif ($today_timelog['clock_in'] && $today_timelog['clock_out']) {
        $status = 'completed';
    }
}

// Get weekly hours
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_hours), 0) as total FROM time_logs 
                        WHERE intern_id = ? AND date BETWEEN ? AND ?");
$stmt->execute([$user_id, $week_start, $week_end]);
$weekly_hours = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get monthly hours
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_hours), 0) as total FROM time_logs 
                        WHERE intern_id = ? AND date BETWEEN ? AND ?");
$stmt->execute([$user_id, $month_start, $month_end]);
$monthly_hours = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total hours
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_hours), 0) as total FROM time_logs WHERE intern_id = ?");
$stmt->execute([$user_id]);
$total_hours = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get unread notifications count
$unread_notifications = getUnreadNotifications($user_id);

// Get assigned supervisor
$stmt = $conn->prepare("SELECT u.* FROM users u 
                        JOIN interns i ON u.id = i.supervisor_id 
                        WHERE i.user_id = ?");
$stmt->execute([$user_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent time logs
$stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? ORDER BY date DESC LIMIT 7");
$stmt->execute([$user_id]);
$recent_logs = $stmt->fetchAll();

// Get pending leave requests
$stmt = $conn->prepare("SELECT * FROM leave_requests WHERE intern_id = ? AND status = 'pending' ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$pending_leave = $stmt->fetchAll();

// Get active goals
$stmt = $conn->prepare("SELECT * FROM goals WHERE intern_id = ? AND status IN ('pending', 'in_progress') ORDER BY end_date ASC");
$stmt->execute([$user_id]);
$active_goals = $stmt->fetchAll();

// Get unread messages
$stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

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
        <p style="color: var(--gray-500);"><?php echo t('intern_dashboard_welcome'); ?></p>
        <?php if ($supervisor): ?>
            <p style="color: var(--gray-500); font-size: 14px;">
                <?php echo t('supervisor'); ?>: <strong><?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?></strong>
            </p>
        <?php endif; ?>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('total_hours'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #3b82f6;">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo number_format($weekly_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('weekly_hours'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6;">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($monthly_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('monthly_hours'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b; cursor: pointer;" onclick="window.location.href='/interntrack/intern/goals.php'">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo count($active_goals); ?></div>
            <div class="stat-label"><?php echo t('active_goals'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #16a34a; cursor: pointer;" onclick="window.location.href='/interntrack/intern/chat.php'">
            <div class="stat-icon">💬</div>
            <div class="stat-value"><?php echo $unread_messages; ?></div>
            <div class="stat-label"><?php echo t('unread_messages'); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3><?php echo t('quick_actions'); ?></h3>
        <div class="action-buttons">
            <?php if ($status === t('not_started')): ?>
                <button class="btn btn-primary" onclick="clockIn()">
                    <span>✅</span> <?php echo t('clock_in'); ?>
                </button>
            <?php elseif ($status === t('working')): ?>
                <button class="btn btn-warning" onclick="startBreak()">
                    <span>⏸️</span> <?php echo t('start_break'); ?>
                </button>
                <button class="btn btn-danger" onclick="clockOut()">
                    <span>🚪</span> <?php echo t('clock_out'); ?>
                </button>
            <?php elseif ($status === t('on_break')): ?>
                <button class="btn btn-success" onclick="endBreak()">
                    <span>▶️</span> <?php echo t('end_break'); ?>
                </button>
                <button class="btn btn-danger" onclick="clockOut()">
                    <span>🚪</span> <?php echo t('clock_out'); ?>
                </button>
            <?php endif; ?>
            
            <a href="/interntrack/intern/timelogs.php" class="btn btn-secondary">
                <span>⏱️</span> <?php echo t('view_timelogs'); ?>
            </a>
            <a href="/interntrack/intern/chat.php" class="btn btn-secondary">
                <span>💬</span> <?php echo t('send_message'); ?>
            </a>
            <a href="/interntrack/intern/leave.php" class="btn btn-secondary">
                <span>📅</span> <?php echo t('request_leave'); ?>
            </a>
            <a href="/interntrack/intern/goals.php" class="btn btn-secondary">
                <span>🎯</span> <?php echo t('view_goals'); ?>
            </a>
        </div>
    </div>
    
    <!-- Today's Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('today_status'); ?></h3>
            <span class="status-badge <?php echo $status; ?>">
                <?php echo  ucfirst(str_replace('_', ' ', t($status))); ?>
            </span>
        </div>
        <?php if ($today_timelog): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                <?php if ($today_timelog['clock_in']): ?>
                    <div>
                        <strong><?php echo t('clock_in'); ?></strong>
                        <div><?php echo formatTime($today_timelog['clock_in']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['break_start']): ?>
                    <div>
                        <strong><?php echo t('break_start'); ?></strong>
                        <div><?php echo formatTime($today_timelog['break_start']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['break_end']): ?>
                    <div>
                        <strong><?php echo t('break_end'); ?></strong>
                        <div><?php echo formatTime($today_timelog['break_end']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['clock_out']): ?>
                    <div>
                        <strong><?php echo t('clock_out'); ?></strong>
                        <div><?php echo formatTime($today_timelog['clock_out']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['total_hours'] > 0): ?>
                    <div>
                        <strong><?php echo t('total_hours'); ?></strong>
                        <div><?php echo number_format($today_timelog['total_hours'], 2); ?>h</div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['total_break_minutes'] > 0): ?>
                    <div>
                        <strong><?php echo t('break_duration'); ?></strong>
                        <div><?php echo $today_timelog['total_break_minutes']; ?> min</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p><?php echo t('no_activity_today'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('recent_activity'); ?></h3>
            <a href="/interntrack/intern/timelog.php" class="btn btn-sm btn-secondary"><?php echo t('view_all'); ?></a>
        </div>
        <?php if ($recent_logs): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('clock_in'); ?></th>
                            <th><?php echo t('clock_out'); ?></th>
                            <th><?php echo t('hours'); ?></th>
                            <th><?php echo t('status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($log['date'])); ?></td>
                                <td><?php echo $log['clock_in'] ? formatTime($log['clock_in']) : '-'; ?></td>
                                <td><?php echo $log['clock_out'] ? formatTime($log['clock_out']) : '-'; ?></td>
                                <td><?php echo number_format($log['total_hours'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $log['status']; ?>">
                                        <?php echo ucfirst(t($log['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p><?php echo t('no_activity'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Active Goals -->
    <?php if ($active_goals): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('active_goals'); ?></h3>
                <a href="/interntrack/intern/goals.php" class="btn btn-sm btn-secondary"><?php echo t('view_all'); ?></a>
            </div>
            <?php foreach ($active_goals as $goal): ?>
                <div style="padding: 12px; border-bottom: 1px solid var(--gray-200);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                            <div style="font-size: 13px; color: var(--gray-500);">
                                <?php echo t('due'); ?>: <?php echo date('M d, Y', strtotime($goal['end_date'])); ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $goal['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', t($goal['status']))); ?>
                        </span>
                    </div>
                    <div style="margin-top: 8px;">
                        <div style="background: var(--gray-200); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: var(--primary-color); height: 100%; width: <?php echo $goal['progress']; ?>%;"></div>
                        </div>
                        <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">
                            <?php echo t('progress'); ?>: <?php echo $goal['progress']; ?>%
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Pending Leave Requests -->
    <?php if ($pending_leave): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('pending_leave_requests'); ?></h3>
                <a href="/interntrack/intern/leave.php" class="btn btn-sm btn-secondary"><?php echo t('view_all'); ?></a>
            </div>
            <?php foreach ($pending_leave as $leave): ?>
                <div style="padding: 12px; border-bottom: 1px solid var(--gray-200);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo ucfirst($leave['type']); ?> <?php echo t('leave'); ?></strong>
                            <div style="font-size: 13px; color: var(--gray-500);">
                                <?php echo date('M d, Y', strtotime($leave['leave_date'])); ?>
                            </div>
                            <?php if ($leave['reason']): ?>
                                <div style="font-size: 13px;"><?php echo htmlspecialchars($leave['reason']); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge pending"><?php echo t('pending'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function clockIn() {
    fetch('/interntrack/api/clock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'clock_in' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('clock_in_success'); ?>'.replace('{time}', data.time), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || '<?php echo t('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('error_occurred'); ?>', 'error');
        console.error(error);
    });
}

function clockOut() {
    if (!confirm('<?php echo t('clock_out_confirmation'); ?>')) return;
    
    fetch('/interntrack/api/clock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'clock_out' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('clock_out_success'); ?>'.replace('{time}', data.time), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || '<?php echo t('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('error_occurred'); ?>', 'error');
        console.error(error);
    });
}

function startBreak() {
    fetch('/interntrack/api/clock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'start_break' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('break_start_success'); ?>'.replace('{time}', data.time), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || '<?php echo t('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('error_occurred'); ?>', 'error');
        console.error(error);
    });
}

function endBreak() {
    fetch('/interntrack/api/clock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'end_break' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('break_end_success'); ?>'
                .replace('{time}', data.time)
                .replace('{duration}', data.duration), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || '<?php echo t('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('error_occurred'); ?>', 'error');
        console.error(error);
    });
}

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