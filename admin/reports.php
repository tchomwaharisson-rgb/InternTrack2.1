<?php
require_once '../config/config.php';
require_once '../config/language.php';
require_once './admin/export_functions.php';

global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$filter_period = $_GET['period'] ?? 'month';
$filter_intern = $_GET['intern_id'] ?? '';
$filter_supervisor = $_GET['supervisor_id'] ?? '';
$export_format = $_GET['export'] ?? '';

// Build date range
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

switch ($filter_period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'month':
        $start_date = date('Y-m-01');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        break;
    case 'custom':
        if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
            $start_date = $_GET['start_date'];
            $end_date = $_GET['end_date'];
        }
        break;
}

// Build query
$sql = "SELECT 
            u.id, u.first_name, u.last_name, u.email,
            i.school, i.field_of_study,
            COALESCE(SUM(tl.total_hours), 0) as total_hours,
            COUNT(DISTINCT tl.date) as days_worked,
            AVG(tl.total_hours) as avg_hours,
            COUNT(CASE WHEN tl.status = 'completed' THEN 1 END) as completed_days,
            COUNT(CASE WHEN tl.status = 'missed' THEN 1 END) as missed_days
        FROM users u
        LEFT JOIN interns i ON u.id = i.user_id
        LEFT JOIN time_logs tl ON u.id = tl.intern_id 
            AND tl.date BETWEEN ? AND ?
        WHERE u.role = 'intern'";

$params = [$start_date, $end_date];

if (!empty($filter_intern)) {
    $sql .= " AND u.id = ?";
    $params[] = $filter_intern;
}

if (!empty($filter_supervisor)) {
    $sql .= " AND i.supervisor_id = ?";
    $params[] = $filter_supervisor;
}

$sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, i.school, i.field_of_study
          ORDER BY total_hours DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$report_data = $stmt->fetchAll();

// Get totals
$total_hours = array_sum(array_column($report_data, 'total_hours'));
$avg_hours = count($report_data) > 0 ? $total_hours / count($report_data) : 0;

// Get all interns for filter
$stmt = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'intern' ORDER BY first_name");
$interns = $stmt->fetchAll();

// Get all supervisors for filter
$stmt = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'supervisor' ORDER BY first_name");
$supervisors = $stmt->fetchAll();

// Export functionality
if (! empty ($export_format)) {
    require_once '../config/export_functions.php';
    if ($export_format === 'pdf') {
        exportReportPDF($report_data, $start_date, $end_date, $total_hours, $avg_hours);
        exit;
        // ... PDF generation code
    }
} elseif ($export_format === 'excel') {
    exportReportExcel($report_data, $start_date, $end_date, $total_hours, $avg_hours);
    exit;
    // ... Excel generation code
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Reports</h3>
        </div>
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="period">Period</label>
                    <select id="period" name="period" class="form-control" onchange="toggleCustomDate()">
                        <option value="week" <?php echo $filter_period === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $filter_period === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="year" <?php echo $filter_period === 'year' ? 'selected' : ''; ?>>This Year</option>
                        <option value="custom" <?php echo $filter_period === 'custom' ? 'selected' : ''; ?>>Custom</option>
                    </select>
                </div>
                <div class="form-group" id="custom_date_range" style="<?php echo $filter_period === 'custom' ? '' : 'display: none;'; ?>">
                    <label>Date Range</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        <span style="align-self: center;">to</span>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="intern_id">Intern</label>
                    <select id="intern_id" name="intern_id" class="form-control">
                        <option value="">All Interns</option>
                        <?php foreach ($interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>" 
                                    <?php echo $filter_intern == $intern['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="supervisor_id">Supervisor</label>
                    <select id="supervisor_id" name="supervisor_id" class="form-control">
                        <option value="">All Supervisors</option>
                        <?php foreach ($supervisors as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo $filter_supervisor == $supervisor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <button type="submit" name="export" value="pdf" class="btn btn-secondary">Export PDF</button>
            <button type="submit" name="export" value="excel" class="btn btn-secondary">Export Excel</button>
        </form>
    </div>
    
    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-label">Total Hours</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($avg_hours, 1); ?></div>
            <div class="stat-label">Average Hours/Intern</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-value"><?php echo count($report_data); ?></div>
            <div class="stat-label">Total Interns</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d', strtotime($end_date)); ?></div>
            <div class="stat-label">Period</div>
        </div>
    </div>
    
    <!-- Detailed Report -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Intern Performance Report</h3>
        </div>
        <?php if ($report_data): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>School</th>
                            <th>Total Hours</th>
                            <th>Days Worked</th>
                            <th>Avg Hours/Day</th>
                            <th>Completed</th>
                            <th>Missed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $data): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary-text);">
                                        <?php echo htmlspecialchars($data['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($data['school'] ?? 'N/A'); ?>
                                    <?php if ($data['field_of_study']): ?>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo htmlspecialchars($data['field_of_study']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo number_format($data['total_hours'], 2); ?></strong></td>
                                <td><?php echo $data['days_worked']; ?></td>
                                <td><?php echo number_format($data['avg_hours'] ?? 0, 1); ?></td>
                                <td><?php echo $data['completed_days']; ?></td>
                                <td><?php echo $data['missed_days']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No data available for the selected period.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCustomDate() {
    const period = document.getElementById('period').value;
    const customRange = document.getElementById('custom_date_range');
    customRange.style.display = period === 'custom' ? '' : 'none';
}
</script>

<?php include_once '../includes/footer.php'; ?>