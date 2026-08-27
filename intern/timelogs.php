<?php
// intern/timelog.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
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
            <h3 class="card-title"><?php echo t('today_status'); ?></h3>
            <span class="status-badge <?php echo $status; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
            </span>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?php if ($status === 'not_started'): ?>
                    <button class="btn btn-primary" onclick="clockIn()">✅ <?php echo t('clock_in'); ?></button>
                <?php elseif ($status === t('working')): ?>
                    <button class="btn btn-warning" onclick="startBreak()">⏸️ <?php echo t('start_break'); ?></button>
                    <button class="btn btn-danger" onclick="clockOut()">🚪 <?php echo t('clock_out'); ?></button>
                <?php elseif ($status === t('on_break')): ?>
                    <button class="btn btn-success" onclick="endBreak()">▶️ <?php echo t('end_break'); ?></button>
                    <button class="btn btn-danger" onclick="clockOut()">🚪 <?php echo t('clock_out'); ?></button>
                <?php endif; ?>
            </div>
            <?php if ($today_log): ?>
                <div style="margin-left: auto; display: flex; gap: 24px; font-size: 14px; flex-wrap: wrap;">
                    <?php if ($today_log['clock_in']): ?>
                        <div><strong><?php echo t('clock_in'); ?>:</strong> <?php echo formatTime($today_log['clock_in']); ?></div>
                    <?php endif; ?>
                    <?php if ($today_log['clock_out']): ?>
                        <div><strong><?php echo t('clock_out'); ?>:</strong> <?php echo formatTime($today_log['clock_out']); ?></div>
                    <?php endif; ?>
                    <?php if ($today_log['total_hours'] > 0): ?>
                        <div><strong><?php echo t('hours'); ?>:</strong> <?php echo number_format($today_log['total_hours'], 2); ?></div>
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
            <div class="stat-label"><?php echo t('total_hours'); ?> (<?php echo date('M Y', strtotime($month)); ?>)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo $days_worked; ?></div>
            <div class="stat-label"><?php echo t('days_worked'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($avg_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('avg_hours_per_day'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo floor($total_break_minutes / 60); ?>h <?php echo $total_break_minutes % 60; ?>m</div>
            <div class="stat-label"><?php echo t('total_break'); ?></div>
        </div>
    </div>
    
    <!-- Time Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('time_logs'); ?></h3>
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
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('clock_in'); ?></th>
                            <th><?php echo t('clock_out'); ?></th>
                            <th><?php echo t('break_start'); ?></th>
                            <th><?php echo t('break_end'); ?></th>
                            <th><?php echo t('break_duration'); ?></th>
                            <th><?php echo t('hours'); ?></th>
                            <th><?php echo t('status'); ?></th>
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
                                <td><?php echo $log['total_break_minutes'] ?? 0; ?> min</td>
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
                        <tr style="font-weight: 600; background: var(--gray-50);">
                            <td colspan="6" style="text-align: right;"><?php echo t('total'); ?>:</td>
                            <td><?php echo number_format($total_hours, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">⏱️</div>
                <h3><?php echo t('no_timelogs_found'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_timelogs_message_intern'); ?></p>
            </div>
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