<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

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

<div class="main-content">
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-value"><?php echo $stats['total_interns']; ?></div>
            <div class="stat-label">Total Interns</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👔</div>
            <div class="stat-value"><?php echo $stats['total_supervisors']; ?></div>
            <div class="stat-label">Total Supervisors</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo $stats['clocked_in_today']; ?></div>
            <div class="stat-label">Clocked In Today</div>
        </div>
        <div class="stat-card" style="border-left-color: #FFC107;">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">System Status</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
            <div>
                <strong>Work Hours</strong>
                <div><?php echo $work_start; ?> - <?php echo $work_end; ?></div>
            </div>
            <div>
                <strong>Break Time</strong>
                <div><?php echo $break_start; ?> - <?php echo $break_end; ?></div>
            </div>
            <div>
                <strong>Database</strong>
                <div style="color: #4CAF50;">✓ Connected</div>
            </div>
            <div>
                <strong>System Status</strong>
                <div style="color: #4CAF50;">✓ Running</div>
            </div>
        </div>
    </div>
    
    <!-- Recent Time Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Time Logs</h3>
            <a href="/interntrack/admin/timelogs.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if ($recent_logs): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
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
                                <td>
                                    <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary-text);">
                                        <?php echo htmlspecialchars($log['email']); ?>
                                    </div>
                                </td>
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
            <p>No time logs recorded yet.</p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Registration Requests -->
    <?php if ($recent_requests): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Registration Requests</h3>
                <a href="/interntrack/admin/requests.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Date</th>
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
                                        <?php echo ucfirst($request['status']); ?>
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