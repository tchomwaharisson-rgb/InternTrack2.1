<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn()) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

if (!hasRole('intern')) {
    header('Location: /interntrack/dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
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

// Get recent notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$recent_notifications = $stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="main-content">
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-label">Total Hours</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo number_format($weekly_hours, 1); ?></div>
            <div class="stat-label">This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($monthly_hours, 1); ?></div>
            <div class="stat-label">This Month</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo count($active_goals); ?></div>
            <div class="stat-label">Active Goals</div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('quick_actions'); ?></h3>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <?php if ($status === 'not_started'): ?>
                <button class="btn btn-primary" onclick="clockIn()">
                    <span>✅</span> <?php echo t('clock_in'); ?>
                </button>
            <?php elseif ($status === 'working'): ?>
                <button class="btn btn-warning" onclick="startBreak()">
                    <span>⏸️</span> <?php echo t('start_break'); ?>
                </button>
                <button class="btn btn-danger" onclick="clockOut()">
                    <span>🚪</span> <?php echo t('clock_out'); ?>
                </button>
            <?php elseif ($status === 'on_break'): ?>
                <button class="btn btn-success" onclick="endBreak()">
                    <span>▶️</span> <?php echo t('end_break'); ?>
                </button>
                <button class="btn btn-danger" onclick="clockOut()">
                    <span>🚪</span> <?php echo t('clock_out'); ?>
                </button>
            <?php endif; ?>
            
            <a href="/interntrack/intern/timelogs.php" class="btn btn-secondary">
                <span>📋</span> <?php echo t('view_timelog'); ?>
            </a>
            <a href="/interntrack/intern/chat.php" class="btn btn-secondary">
                <span>💬</span> <?php echo t('send_message'); ?>
            </a>
            <a href="/interntrack/intern/leave.php" class="btn btn-secondary">
                <span>📅</span> <?php echo t('request_leave'); ?>
            </a>
        </div>
    </div>
    
    <!-- Today's Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Today's Status</h3>
            <span class="status-badge <?php echo $status; ?>">
                <?php 
                    switch($status) {
                        case 'not_started': echo 'Not Started'; break;
                        case 'working': echo 'Working'; break;
                        case 'on_break': echo 'On Break'; break;
                        case 'completed': echo 'Completed'; break;
                    }
                ?>
            </span>
        </div>
        <?php if ($today_timelog): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                <?php if ($today_timelog['clock_in']): ?>
                    <div>
                        <strong>Clock In</strong>
                        <div><?php echo formatTime($today_timelog['clock_in']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['break_start']): ?>
                    <div>
                        <strong>Break Start</strong>
                        <div><?php echo formatTime($today_timelog['break_start']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['break_end']): ?>
                    <div>
                        <strong>Break End</strong>
                        <div><?php echo formatTime($today_timelog['break_end']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['clock_out']): ?>
                    <div>
                        <strong>Clock Out</strong>
                        <div><?php echo formatTime($today_timelog['clock_out']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['total_hours'] > 0): ?>
                    <div>
                        <strong>Total Hours</strong>
                        <div><?php echo number_format($today_timelog['total_hours'], 2); ?>h</div>
                    </div>
                <?php endif; ?>
                <?php if ($today_timelog['total_break_minutes'] > 0): ?>
                    <div>
                        <strong>Break Duration</strong>
                        <div><?php echo $today_timelog['total_break_minutes']; ?> min</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No activity recorded today.</p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
            <a href="/interntrack/intern/timelog.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if ($recent_logs): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Hours</th>
                            <th>Status</th>
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
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No activity recorded yet.</p>
        <?php endif; ?>
    </div>
    
    <!-- Active Goals -->
    <?php if ($active_goals): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Active Goals</h3>
                <a href="/interntrack/intern/goals.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <?php foreach ($active_goals as $goal): ?>
                <div style="padding: 12px; border-bottom: 1px solid var(--primary-gray-dark);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                            <div style="font-size: 13px; color: var(--secondary-text);">
                                Due: <?php echo date('M d, Y', strtotime($goal['end_date'])); ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $goal['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $goal['status'])); ?>
                        </span>
                    </div>
                    <div style="margin-top: 8px;">
                        <div style="background: var(--primary-gray); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: var(--primary-red); height: 100%; width: <?php echo $goal['progress']; ?>%;"></div>
                        </div>
                        <div style="font-size: 12px; color: var(--secondary-text); margin-top: 4px;">
                            Progress: <?php echo $goal['progress']; ?>%
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
                <h3 class="card-title">Pending Leave Requests</h3>
                <a href="/interntrack/intern/leave.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <?php foreach ($pending_leave as $leave): ?>
                <div style="padding: 12px; border-bottom: 1px solid var(--primary-gray-dark);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo ucfirst($leave['type']); ?> Leave</strong>
                            <div style="font-size: 13px; color: var(--secondary-text);">
                                <?php echo date('M d, Y', strtotime($leave['leave_date'])); ?>
                            </div>
                            <?php if ($leave['reason']): ?>
                                <div style="font-size: 13px;"><?php echo htmlspecialchars($leave['reason']); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge pending">Pending</span>
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
    if (!confirm('Are you sure you want to clock out?')) return;
    
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
</script>

<?php include_once '../includes/footer.php'; ?>