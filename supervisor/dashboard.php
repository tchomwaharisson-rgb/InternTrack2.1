<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
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
foreach ($interns as $intern) {
    $stmt = $conn->prepare("SELECT * FROM time_logs WHERE intern_id = ? AND date = ?");
    $stmt->execute([$intern['id'], $today]);
    $timelog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($timelog && $timelog['clock_in'] && !$timelog['clock_out']) {
        if ($timelog['break_start'] && !$timelog['break_end']) {
            $status = 'on_break';
        } else {
            $status = 'working';
        }
    } elseif ($timelog && $timelog['clock_in'] && $timelog['clock_out']) {
        $status = 'completed';
    } elseif ($timelog && !$timelog['clock_in']) {
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
$unread_messages = $stmt->fetchColumn();

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

include_once '../includes/header.php';
?>

<div class="main-content">
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo count($interns); ?></div>
            <div class="stat-label">Assigned Interns</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value">
                <?php 
                    $working = array_filter($intern_status, function($s) { 
                        return $s['status'] === 'working' || $s['status'] === 'on_break'; 
                    });
                    echo count($working);
                ?>
            </div>
            <div class="stat-label">Active Today</div>
        </div>
        <div class="stat-card" style="border-left-color: #FFC107;">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo count($pending_leave); ?></div>
            <div class="stat-label">Pending Leave</div>
        </div>
        <div class="stat-card" style="border-left-color: #2196F3;">
            <div class="stat-icon">💬</div>
            <div class="stat-value"><?php echo $unread_messages; ?></div>
            <div class="stat-label">Unread Messages</div>
        </div>
    </div>
    
    <!-- Intern Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Intern Status - <?php echo date('M d, Y'); ?></h3>
            <a href="/interntrack/supervisor/interns.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if ($interns): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>School</th>
                            <th>Status</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Hours</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($interns as $intern): ?>
                            <?php $status = $intern_status[$intern['id']] ?? ['status' => 'not_started', 'timelog' => null]; ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary-text);">
                                        <?php echo htmlspecialchars($intern['email']); ?>
                                    </div>
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
                                    <span class="status-badge <?php echo $status['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $status['timelog']['clock_in'] ? formatTime($status['timelog']['clock_in']) : '-'; ?></td>
                                <td><?php echo $status['timelog']['clock_out'] ? formatTime($status['timelog']['clock_out']) : '-'; ?></td>
                                <td><?php echo number_format($status['timelog']['total_hours'] ?? 0, 2); ?></td>
                                <td>
                                    <a href="/interntrack/supervisor/timelogs.php?intern_id=<?php echo $intern['id']; ?>" 
                                       class="btn btn-sm btn-secondary">View</a>
                                    <a href="/interntrack/supervisor/chat.php?user_id=<?php echo $intern['id']; ?>" 
                                       class="btn btn-sm btn-primary">Chat</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No interns assigned to you yet.</p>
        <?php endif; ?>
    </div>
    
    <!-- Weekly Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Weekly Summary (<?php echo date('M d', strtotime($week_start)); ?> - <?php echo date('M d'); ?>)</h3>
        </div>
        <?php if ($weekly_summary): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>Total Hours</th>
                            <th>Avg Hours/Day</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_summary as $summary): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($summary['first_name'] . ' ' . $summary['last_name']); ?></td>
                                <td><strong><?php echo number_format($summary['total_hours'], 2); ?></strong></td>
                                <td><?php echo number_format($summary['total_hours'] / 5, 1); ?></td>
                                <td>
                                    <?php if ($summary['total_hours'] >= 35): ?>
                                        <span style="color: #4CAF50;">✅ On Track</span>
                                    <?php elseif ($summary['total_hours'] >= 20): ?>
                                        <span style="color: #FFC107;">⚠️ Partial</span>
                                    <?php else: ?>
                                        <span style="color: #f44336;">❌ Below Target</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No data available for this week.</p>
        <?php endif; ?>
    </div>
    
    <!-- Pending Leave Requests -->
    <?php if ($pending_leave): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Leave Requests</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_leave as $leave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                <td><?php echo ucfirst($leave['type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($leave['leave_date'])); ?></td>
                                <td><?php echo htmlspecialchars($leave['reason'] ?? '-'); ?></td>
                                <td>
                                    <form method="POST" action="/interntrack/supervisor/leave.php" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <form method="POST" action="/interntrack/supervisor/leave.php" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
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