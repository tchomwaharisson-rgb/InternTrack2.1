<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('intern')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');
$view = $_GET['view'] ?? 'monthly';

// Get time logs for the month
$stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date LIKE ? ORDER BY date DESC");
$stmt->execute([$user_id, $month . '%']);
$logs = $stmt->fetchAll();

// Calculate statistics
$total_hours = array_sum(array_column($logs, 'total_hours'));
$days_worked = count(array_filter($logs, function($log) { return $log['clock_in'] !== null; }));
$avg_hours = $days_worked > 0 ? $total_hours / $days_worked : 0;
$total_break_minutes = array_sum(array_column($logs, 'total_break_minutes'));

// Get today's log
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = ?");
$stmt->execute([$user_id, $today]);
$today_log = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current status
$status = 'not_started';
if ($today_log) {
    if ($today_log['clock_in'] && !$today_log['clock_out']) {
        if ($today_log['break_start'] && !$today_log['break_end']) {
            $status = 'on_break';
        } else {
            $status = 'working';
        }
    } elseif ($today_log['clock_in'] && $today_log['clock_out']) {
        $status = 'completed';
    }
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <!-- Today's Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Today's Status - <?php echo date('M d, Y'); ?></h3>
            <span class="status-badge <?php echo $status; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
            </span>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?php if ($status === 'not_started'): ?>
                    <button class="btn btn-primary" onclick="clockIn()">✅ Clock In</button>
                <?php elseif ($status === 'working'): ?>
                    <button class="btn btn-warning" onclick="startBreak()">⏸️ Start Break</button>
                    <button class="btn btn-danger" onclick="clockOut()">🚪 Clock Out</button>
                <?php elseif ($status === 'on_break'): ?>
                    <button class="btn btn-success" onclick="endBreak()">▶️ End Break</button>
                    <button class="btn btn-danger" onclick="clockOut()">🚪 Clock Out</button>
                <?php endif; ?>
            </div>
            <?php if ($today_log): ?>
                <div style="margin-left: auto; display: flex; gap: 24px; font-size: 14px;">
                    <?php if ($today_log['clock_in']): ?>
                        <div><strong>Arrived:</strong> <?php echo formatTime($today_log['clock_in']); ?></div>
                    <?php endif; ?>
                    <?php if ($today_log['clock_out']): ?>
                        <div><strong>Departed:</strong> <?php echo formatTime($today_log['clock_out']); ?></div>
                    <?php endif; ?>
                    <?php if ($today_log['total_hours'] > 0): ?>
                        <div><strong>Hours:</strong> <?php echo number_format($today_log['total_hours'], 2); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-label">Total Hours (<?php echo date('M Y', strtotime($month)); ?>)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo $days_worked; ?></div>
            <div class="stat-label">Days Worked</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($avg_hours, 1); ?></div>
            <div class="stat-label">Avg Hours/Day</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo floor($total_break_minutes / 60); ?>h <?php echo $total_break_minutes % 60; ?>m</div>
            <div class="stat-label">Total Break Time</div>
        </div>
    </div>
    
    <!-- Time Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Time Logs</h3>
            <div style="display: flex; gap: 8px;">
                <form method="GET" action="" style="display: inline;">
                    <input type="month" name="month" class="form-control" style="width: 160px; display: inline-block;" 
                           value="<?php echo $month; ?>" onchange="this.form.submit()">
                </form>
            </div>
        </div>
        <?php if ($logs): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Break Start</th>
                            <th>Break End</th>
                            <th>Break (min)</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($log['date'])); ?></td>
                                <td><?php echo $log['clock_in'] ? formatTime($log['clock_in']) : '-'; ?></td>
                                <td><?php echo $log['clock_out'] ? formatTime($log['clock_out']) : '-'; ?></td>
                                <td><?php echo $log['break_start'] ? formatTime($log['break_start']) : '-'; ?></td>
                                <td><?php echo $log['break_end'] ? formatTime($log['break_end']) : '-'; ?></td>
                                <td><?php echo $log['total_break_minutes'] ?? 0; ?></td>
                                <td><strong><?php echo number_format($log['total_hours'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge <?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: 600; background: var(--primary-gray);">
                            <td colspan="6" style="text-align: right;">Total:</td>
                            <td><?php echo number_format($total_hours, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p>No time logs found for this month.</p>
        <?php endif; ?>
    </div>
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