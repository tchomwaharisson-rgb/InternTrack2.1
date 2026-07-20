<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$intern_id = $_GET['intern_id'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

// Get assigned interns
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users u 
                        JOIN interns i ON u.id = i.user_id 
                        WHERE i.supervisor_id = ?");
$stmt->execute([$user_id]);
$assigned_interns = $stmt->fetchAll();

// If no intern selected, show all assigned interns
$intern_ids = [];
if (empty($intern_id)) {
    $intern_ids = array_column($assigned_interns, 'id');
} else {
    // Verify the intern is assigned to this supervisor
    $stmt = $conn->prepare("SELECT user_id FROM interns WHERE user_id = ? AND supervisor_id = ?");
    $stmt->execute([$intern_id, $user_id]);
    if ($stmt->fetch()) {
        $intern_ids = [$intern_id];
    } else {
        $intern_ids = array_column($assigned_interns, 'id');
    }
}

if (empty($intern_ids)) {
    $intern_ids = [0]; // No interns assigned
}

// Get timelogs for selected interns
$placeholders = str_repeat('?,', count($intern_ids) - 1) . '?';
$stmt = $conn->prepare("
    SELECT tl.*, u.first_name, u.last_name, u.email 
    FROM time_logs tl
    JOIN users u ON tl.intern_id = u.id
    WHERE tl.intern_id IN ($placeholders)
    AND tl.date LIKE ?
    ORDER BY tl.date DESC, tl.clock_in DESC
");
$params = array_merge($intern_ids, [$month . '%']);
$stmt->execute($params);
$timelogs = $stmt->fetchAll();

include_once '../includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Intern Time Logs</h3>
        </div>
        
        <form method="GET" action="" style="margin-bottom: 16px;">
            <div class="form-row">
                <div class="form-group">
                    <label for="intern_id">Intern</label>
                    <select id="intern_id" name="intern_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Assigned Interns</option>
                        <?php foreach ($assigned_interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>" 
                                    <?php echo $intern_id == $intern['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="month">Month</label>
                    <input type="month" id="month" name="month" class="form-control" 
                           value="<?php echo $month; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
        
        <?php if ($timelogs): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
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
                        <?php foreach ($timelogs as $log): ?>
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
                </table>
            </div>
        <?php else: ?>
            <p>No time logs found for the selected period.</p>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>