<?php
// supervisor/timelogs.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$intern_id = $_GET['intern_id'] ?? '';
$month = $_GET['month'] ?? date('Y-m');
$status = $_GET['status'] ?? '';

// Get assigned interns
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM users u 
                        JOIN interns i ON u.id = i.user_id 
                        WHERE i.supervisor_id = ? AND u.is_active = TRUE");
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

// Build query
$sql = "SELECT tl.*, u.first_name, u.last_name, u.email 
        FROM time_logs tl
        JOIN users u ON tl.intern_id = u.id
        WHERE tl.intern_id IN (" . str_repeat('?,', count($intern_ids) - 1) . '?)';
$params = $intern_ids;

if (!empty($month)) {
    $sql .= " AND tl.date LIKE ?";
    $params[] = $month . '%';
}

if (!empty($status)) {
    $sql .= " AND tl.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY tl.date DESC, tl.clock_in DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$timelogs = $stmt->fetchAll();

// Calculate statistics
$total_hours = array_sum(array_column($timelogs, 'total_hours'));
$total_days = count($timelogs);
$total_interns_logged = count(array_unique(array_column($timelogs, 'intern_id')));
$avg_hours = $total_days > 0 ? round($total_hours / $total_days, 2) : 0;

// Get status counts
$status_counts = [
    'active' => 0,
    'completed' => 0,
    'missed' => 0
];
foreach ($timelogs as $log) {
    if (isset($status_counts[$log['status']])) {
        $status_counts[$log['status']]++;
    }
}

include_once '../includes/header.php';
?>

<style>
    [data-theme="dark"] .stat-card .stat-value {
        color: white;
    }
</style>

<div class="main-content">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('total_hours'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo $total_days; ?></div>
            <div class="stat-label"><?php echo t('total_days'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-value"><?php echo $total_interns_logged; ?></div>
            <div class="stat-label"><?php echo t('interns_logged'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($avg_hours, 2); ?></div>
            <div class="stat-label"><?php echo t('avg_hours_per_day'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('filter_timelogs'); ?></h3>
        </div>
        <form method="GET" action="" id="filterForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="intern_id"><?php echo t('intern'); ?></label>
                    <select id="intern_id" name="intern_id" class="form-control" onchange="this.form.submit()">
                        <option value=""><?php echo t('all_interns'); ?></option>
                        <?php foreach ($assigned_interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>" 
                                    <?php echo $intern_id == $intern['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="month"><?php echo t('month'); ?></label>
                    <input type="month" id="month" name="month" class="form-control" 
                           value="<?php echo $month; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label for="status"><?php echo t('status'); ?></label>
                    <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                        <option value=""><?php echo t('all_statuses'); ?></option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>
                            <?php echo t('active'); ?>
                        </option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>
                            <?php echo t('completed'); ?>
                        </option>
                        <option value="missed" <?php echo $status === 'missed' ? 'selected' : ''; ?>>
                            <?php echo t('missed'); ?>
                        </option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Status Summary -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="status-badge active"><?php echo t('active'); ?></span>
            <span><?php echo $status_counts['active']; ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="status-badge completed"><?php echo t('completed'); ?></span>
            <span><?php echo $status_counts['completed']; ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="status-badge missed"><?php echo t('missed'); ?></span>
            <span><?php echo $status_counts['missed']; ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; margin-left: auto; color: var(--gray-500); font-size: 14px;">
            <?php echo t('showing'); ?> <?php echo count($timelogs); ?> <?php echo t('records'); ?>
        </div>
    </div>

    <!-- Time Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('time_logs'); ?></h3>
        </div>
        <?php if ($timelogs): ?>
            <div class="table-container">
                <table class="table" id="timelogTable">
                    <thead>
                        <tr>
                            <th><?php echo t('intern'); ?></th>
                            <th><?php echo t('date'); ?></th>
                            <th><?php echo t('clock_in'); ?></th>
                            <th><?php echo t('clock_out'); ?></th>
                            <th><?php echo t('break_start'); ?></th>
                            <th><?php echo t('break_end'); ?></th>
                            <th><?php echo t('break_duration'); ?></th>
                            <th><?php echo t('total_hours'); ?></th>
                            <th><?php echo t('status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timelogs as $log): ?>
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
                            <td colspan="7" style="text-align: right;"><?php echo t('total'); ?>:</td>
                            <td><?php echo number_format($total_hours, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                <h3><?php echo t('no_timelogs_found'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_timelogs_message_supervisor'); ?></p>
                <p style="color: var(--gray-400); font-size: 14px; margin-top: 8px;">
                    <?php echo t('try_adjusting_filters'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>