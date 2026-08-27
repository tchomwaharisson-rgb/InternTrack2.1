<?php
// admin/dashboard.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);

// Get statistics
$stats = [];

// Total users
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'intern'");
$stats['total_interns'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'supervisor'");
$stats['total_supervisors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE");
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM registration_requests WHERE status = 'pending'");
$stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's attendance
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM time_logs WHERE date = ? AND clock_in IS NOT NULL");
$stmt->execute([$today]);
$stats['clocked_in_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent time logs
$stmt = $conn->prepare("
    SELECT tl.*, u.first_name, u.last_name, u.email 
    FROM time_logs tl 
    JOIN users u ON tl.intern_id = u.id 
    ORDER BY tl.date DESC, tl.clock_in DESC 
    LIMIT 10
");
$stmt->execute();
$recent_logs = $stmt->fetchAll();

// Get recent registrations
$stmt = $conn->prepare("SELECT * FROM registration_requests ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_requests = $stmt->fetchAll();

// Get system status
$work_start = getSetting('work_start') ?? '08:00:00';
$work_end = getSetting('work_end') ?? '18:00:00';
$break_start = getSetting('break_start') ?? '12:00:00';
$break_end = getSetting('break_end') ?? '14:00:00';

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
        <p style="color: var(--gray-500);"><?php echo t('admin_dashboard_welcome'); ?></p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='/interntrack/admin/users.php'">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-value"><?php echo $stats['total_interns']; ?></div>
            <div class="stat-label"><?php echo t('total_interns'); ?></div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='/interntrack/admin/users.php'">
            <div class="stat-icon">👔</div>
            <div class="stat-value"><?php echo $stats['total_supervisors']; ?></div>
            <div class="stat-label"><?php echo t('total_supervisors'); ?></div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='/interntrack/admin/users.php'">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
            <div class="stat-label"><?php echo t('active_users'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b; cursor: pointer;" onclick="window.location.href='/interntrack/admin/requests.php'">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
            <div class="stat-label"><?php echo t('pending_requests'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #16a34a; cursor: pointer;" onclick="window.location.href='/interntrack/admin/timelogs.php'">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo $stats['clocked_in_today']; ?></div>
            <div class="stat-label"><?php echo t('clocked_in_today'); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3><?php echo t('quick_actions'); ?></h3>
        <div class="action-buttons">
            <a href="/interntrack/admin/users.php" class="btn btn-primary">
                <span>👥</span> <?php echo t('manage_users'); ?>
            </a>
            <a href="/interntrack/admin/requests.php" class="btn btn-warning">
                <span>📝</span> <?php echo t('registration_requests'); ?>
                <?php if ($stats['pending_requests'] > 0): ?>
                    <span class="badge badge-warning"><?php echo $stats['pending_requests']; ?></span>
                <?php endif; ?>
            </a>
            <a href="/interntrack/admin/timelogs.php" class="btn btn-secondary">
                <span>⏱️</span> <?php echo t('view_timelogs'); ?>
            </a>
            <a href="/interntrack/admin/settings.php" class="btn btn-secondary">
                <span>⚙️</span> <?php echo t('system_settings'); ?>
            </a>
            <a href="/interntrack/admin/reports.php" class="btn btn-secondary">
                <span>📊</span> <?php echo t('system_reports'); ?>
            </a>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('system_status'); ?></h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
            <div>
                <strong><?php echo t('work_hours'); ?></strong>
                <div><?php echo $work_start; ?> - <?php echo $work_end; ?></div>
            </div>
            <div>
                <strong><?php echo t('break_time'); ?></strong>
                <div><?php echo $break_start; ?> - <?php echo $break_end; ?></div>
            </div>
            <div>
                <strong><?php echo t('database'); ?></strong>
                <div style="color: #4CAF50;">✓ <?php echo t('connected'); ?></div>
            </div>
            <div>
                <strong><?php echo t('system_status'); ?></strong>
                <div style="color: #4CAF50;">✓ <?php echo t('running'); ?></div>
            </div>
            <div>
                <strong><?php echo t('timezone'); ?></strong>
                <div><?php echo date_default_timezone_get(); ?></div>
            </div>
            <div>
                <strong><?php echo t('server_time'); ?></strong>
                <div><?php echo date('H:i:s'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Recent Time Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('recent_timelogs'); ?></h3>
            <a href="/interntrack/admin/timelogs.php" class="btn btn-sm btn-secondary"><?php echo t('view_all'); ?></a>
        </div>
        <?php if ($recent_logs): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
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
                                <td>
                                    <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        <?php echo htmlspecialchars($log['email']); ?>
                                    </div>
                                </td>
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
            <p><?php echo t('no_timelogs_found'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Registration Requests -->
    <?php if ($recent_requests): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('recent_registrations'); ?></h3>
                <a href="/interntrack/admin/requests.php" class="btn btn-sm btn-secondary"><?php echo t('view_all'); ?></a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo t('name'); ?></th>
                            <th><?php echo t('email'); ?></th>
                            <th><?php echo t('role'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('date'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td><?php echo ucfirst($request['role']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $request['status']; ?>">
                                        <?php echo ucfirst(t($request['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>