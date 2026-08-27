<?php
// admin/reports.php
require_once '../config/config.php';
require_once '../config/language.php';
require_once 'export_functions.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

// ============================================
// 1. GET ALL FILTERS FIRST (BEFORE QUERY)
// ============================================
$filter_period = $_GET['period'] ?? 'month';
$filter_intern = $_GET['intern_id'] ?? '';
$filter_supervisor = $_GET['supervisor_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'performance';  // <-- Moved HERE
$export_format = $_GET['export'] ?? '';

// ============================================
// 2. BUILD DATE RANGE
// ============================================
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

switch ($filter_period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'month':
        $start_date = date('Y-m-01');
        break;
    case 'quarter':
        $quarter = ceil(date('m') / 3);
        $start_date = date('Y-' . ($quarter * 3 - 2) . '-01');
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

// ============================================
// 3. BUILD QUERY BASED ON REPORT TYPE
// ============================================
if ($report_type === 'performance') {
    // Performance Report - Intern performance metrics
    $sql = "SELECT 
                u.id, u.first_name, u.last_name, u.email,
                i.school, i.field_of_study,
                i.start_date as intern_start_date,
                i.end_date as intern_end_date,
                s.first_name as supervisor_first_name,
                s.last_name as supervisor_last_name,
                COALESCE(SUM(tl.total_hours), 0) as total_hours,
                COUNT(DISTINCT tl.date) as days_worked,
                COALESCE(AVG(tl.total_hours), 0) as avg_hours_per_day,
                COUNT(CASE WHEN tl.status = 'completed' THEN 1 END) as completed_days,
                COUNT(CASE WHEN tl.status = 'missed' THEN 1 END) as missed_days,
                COUNT(CASE WHEN tl.status = 'active' THEN 1 END) as active_days,
                COUNT(CASE WHEN tl.clock_in IS NOT NULL THEN 1 END) as clocked_in_days,
                COALESCE(AVG(tl.total_break_minutes), 0) as avg_break_minutes
            FROM users u
            LEFT JOIN interns i ON u.id = i.user_id
            LEFT JOIN users s ON i.supervisor_id = s.id
            LEFT JOIN time_logs tl ON u.id = tl.intern_id 
                AND tl.date BETWEEN ? AND ?
            WHERE u.role = 'intern'";

    $daily_work_hours = getSetting('daily_work_hours') ?? 8;
    $params = [$start_date, $end_date];

    if (!empty($filter_intern)) {
        $sql .= " AND u.id = ?";
        $params[] = $filter_intern;
    }

    if (!empty($filter_supervisor)) {
        $sql .= " AND i.supervisor_id = ?";
        $params[] = $filter_supervisor;
    }

    $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, 
                     i.school, i.field_of_study, i.start_date, i.end_date,
                     s.first_name, s.last_name
              ORDER BY total_hours DESC";

} elseif ($report_type === 'attendance') {
    // Attendance Report - Daily attendance summary
    $sql = "SELECT 
                tl.date,
                COUNT(DISTINCT tl.intern_id) as total_interns,
                COUNT(DISTINCT CASE WHEN tl.clock_in IS NOT NULL THEN tl.intern_id END) as clocked_in_count,
                COUNT(DISTINCT CASE WHEN tl.clock_in IS NOT NULL AND tl.clock_out IS NULL THEN tl.intern_id END) as active_count,
                COUNT(DISTINCT CASE WHEN tl.clock_in IS NOT NULL AND tl.clock_out IS NOT NULL THEN tl.intern_id END) as completed_count,
                COALESCE(AVG(tl.total_hours), 0) as avg_hours,
                SUM(tl.total_hours) as total_hours
            FROM time_logs tl
            WHERE tl.date BETWEEN ? AND ?";
    
    $params = [$start_date, $end_date];
    
    if (!empty($filter_intern)) {
        $sql .= " AND tl.intern_id = ?";
        $params[] = $filter_intern;
    }
    
    $sql .= " GROUP BY tl.date
              ORDER BY tl.date DESC";

} else {
    // Default: Summary Report
    $sql = "SELECT 
                u.id, u.first_name, u.last_name, u.email,
                i.school, i.field_of_study,
                i.start_date as intern_start_date,
                i.end_date as intern_end_date,
                s.first_name as supervisor_first_name,
                s.last_name as supervisor_last_name,
                COUNT(tl.id) as total_entries,
                COALESCE(SUM(tl.total_hours), 0) as total_hours,
                COUNT(DISTINCT tl.date) as days_worked,
                COALESCE(AVG(tl.total_hours), 0) as avg_hours,
                COUNT(CASE WHEN tl.status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN tl.status = 'missed' THEN 1 END) as missed_count,
                COUNT(CASE WHEN tl.status = 'active' THEN 1 END) as active_count
            FROM users u
            LEFT JOIN interns i ON u.id = i.user_id
            LEFT JOIN users s ON i.supervisor_id = s.id
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
    
    $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, 
                     i.school, i.field_of_study, i.start_date, i.end_date,
                     s.first_name, s.last_name
              ORDER BY total_hours DESC";
}

// ============================================
// 4. EXECUTE QUERY
// ============================================
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$report_data = $stmt->fetchAll();

// ============================================
// 5. CALCULATE SUMMARY TOTALS
// ============================================
$total_hours = array_sum(array_column($report_data, 'total_hours'));
$total_days = array_sum(array_column($report_data, 'days_worked'));
$avg_hours = count($report_data) > 0 ? $total_hours / count($report_data) : 0;

// ============================================
// 6. GET FILTER OPTIONS
// ============================================
// Get all interns for filter
$stmt = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'intern' ORDER BY first_name");
$interns = $stmt->fetchAll();

// Get all supervisors for filter
$stmt = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'supervisor' ORDER BY first_name");
$supervisors = $stmt->fetchAll();

// ============================================
// 7. HANDLE EXPORT
// ============================================
if ($export_format === 'pdf') {
    exportReportPDF($report_data, $start_date, $end_date, $total_hours, $avg_hours, $report_type);
    exit;
} elseif ($export_format === 'csv') {
    exportReportCsv($report_data, $start_date, $end_date, $total_hours, $avg_hours, $report_type);
    exit;
} elseif ($export_format === 'excel') {
    exportReportExcel($report_data, $start_date, $end_date, $total_hours, $avg_hours, $report_type);
    exit;
}

include_once '../includes/header.php';
?>

<!-- HTML Content -->
<div class="main-content">
    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('system_reports'); ?></h3>
        </div>
        <form method="GET" action="" id="reportForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="report_type"><?php echo t('report_type'); ?></label>
                    <select id="report_type" name="report_type" class="form-control" onchange="this.form.submit()">
                        <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>
                            📊 <?php echo t('performance_report'); ?>
                        </option>
                        <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>
                            📋 <?php echo t('summary_report'); ?>
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="period"><?php echo t('period'); ?></label>
                    <select id="period" name="period" class="form-control" onchange="toggleCustomDate()">
                        <option value="week" <?php echo $filter_period === 'week' ? 'selected' : ''; ?>><?php echo t('this_week'); ?></option>
                        <option value="month" <?php echo $filter_period === 'month' ? 'selected' : ''; ?>><?php echo t('this_month'); ?></option>
                        <option value="quarter" <?php echo $filter_period === 'quarter' ? 'selected' : ''; ?>><?php echo t('this_quarter'); ?></option>
                        <option value="year" <?php echo $filter_period === 'year' ? 'selected' : ''; ?>><?php echo t('this_year'); ?></option>
                        <option value="custom" <?php echo $filter_period === 'custom' ? 'selected' : ''; ?>><?php echo t('custom'); ?></option>
                    </select>
                </div>
                <div class="form-group" id="custom_date_range" style="<?php echo $filter_period === 'custom' ? '' : 'display: none;'; ?>">
                    <label><?php echo t('date_range'); ?></label>
                    <div style="display: flex; gap: 8px;">
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        <span style="align-self: center;"><?php echo t('to'); ?></span>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="intern_id"><?php echo t('intern'); ?></label>
                    <select id="intern_id" name="intern_id" class="form-control">
                        <option value=""><?php echo t('all_interns'); ?></option>
                        <?php foreach ($interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>" 
                                    <?php echo $filter_intern == $intern['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="supervisor_id"><?php echo t('supervisor'); ?></label>
                    <select id="supervisor_id" name="supervisor_id" class="form-control">
                        <option value=""><?php echo t('all_supervisors'); ?></option>
                        <?php foreach ($supervisors as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo $filter_supervisor == $supervisor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" onclick="exportReport('pdf')">📄 <?php echo t('export_pdf'); ?></button>
                <button type="button" class="btn btn-success" onclick="exportReport('csv')"><?php echo t('export_csv'); ?></button>
                <button type="button" class="btn btn-success" onclick="exportReport('excel')">📊 <?php echo t('export_excel'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('total_hours'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?php echo number_format($avg_hours, 1); ?></div>
            <div class="stat-label"><?php echo t('avg_hours_per_intern'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-value"><?php echo count($report_data); ?></div>
            <div class="stat-label"><?php echo t('total_interns'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d', strtotime($end_date)); ?></div>
            <div class="stat-label"><?php echo t('period'); ?></div>
        </div>
    </div>
    
    <!-- Report Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                    if ($report_type === 'performance') echo t('performance_report');
                    elseif ($report_type === 'attendance') echo t('attendance_report');
                    else echo t('summary_report');
                ?>
            </h3>
            <span><?php echo count($report_data); ?> <?php echo t('records'); ?></span>
        </div>
        <?php if ($report_data): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'performance'): ?>
                                <th><?php echo t('intern'); ?></th>
                                <th><?php echo t('email'); ?></th>
                                <th><?php echo t('school'); ?></th>
                                <th><?php echo t('field_of_study'); ?></th>
                                <th><?php echo t('supervisor'); ?></th>
                                <th><?php echo t('start_date'); ?></th>
                                <th><?php echo t('end_date'); ?></th>
                                <th><?php echo t('total_hours'); ?></th>
                                <th><?php echo t('days_worked'); ?></th>
                                <th><?php echo t('avg_hours_per_day'); ?></th>
                                <th><?php echo t('completed'); ?></th>
                                <th><?php echo t('missed'); ?></th>
                                <th><?php echo t('active'); ?></th>
                            <?php elseif ($report_type === 'attendance'): ?>
                                <th><?php echo t('date'); ?></th>
                                <th><?php echo t('total_interns'); ?></th>
                                <th><?php echo t('clocked_in'); ?></th>
                                <th><?php echo t('active'); ?></th>
                                <th><?php echo t('completed'); ?></th>
                                <th><?php echo t('avg_hours'); ?></th>
                                <th><?php echo t('total_hours'); ?></th>
                            <?php else: ?>
                                <th><?php echo t('intern'); ?></th>
                                <th><?php echo t('email'); ?></th>
                                <th><?php echo t('school'); ?></th>
                                <th><?php echo t('supervisor'); ?></th>
                                <th><?php echo t('start_date'); ?></th>
                                <th><?php echo t('end_date'); ?></th>
                                <th><?php echo t('total_entries'); ?></th>
                                <th><?php echo t('total_hours'); ?></th>
                                <th><?php echo t('days_worked'); ?></th>
                                <th><?php echo t('avg_hours'); ?></th>
                                <th><?php echo t('completed'); ?></th>
                                <th><?php echo t('missed'); ?></th>
                                <th><?php echo t('active'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $data): ?>
                            <tr>
                                <?php if ($report_type === 'performance'): ?>
                                    <td><strong><?php echo htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($data['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($data['school'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($data['field_of_study'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($data['supervisor_first_name'] ?? '') . ' ' . ($data['supervisor_last_name'] ?? '')); ?></td>
                                    <td><?php echo isset($data['intern_start_date']) ? date('M d, Y', strtotime($data['intern_start_date'])) : 'N/A'; ?></td>
                                    <td><?php echo isset($data['intern_end_date']) ? date('M d, Y', strtotime($data['intern_end_date'])) : 'N/A'; ?></td>
                                    <td><strong><?php echo number_format($data['total_hours'], 2); ?></strong></td>
                                    <td><?php echo $data['days_worked'] ?? 0; ?></td>
                                    <td><?php echo number_format($data['avg_hours_per_day'] ?? 0, 1); ?></td>
                                    <td><?php echo $data['completed_days'] ?? 0; ?></td>
                                    <td><?php echo $data['missed_days'] ?? 0; ?></td>
                                    <td><?php echo $data['active_days'] ?? 0; ?></td>
                                <?php elseif ($report_type === 'attendance'): ?>
                                    <td><?php echo date('M d, Y', strtotime($data['date'])); ?></td>
                                    <td><?php echo $data['total_interns']; ?></td>
                                    <td><?php echo $data['clocked_in_count']; ?></td>
                                    <td><?php echo $data['active_count']; ?></td>
                                    <td><?php echo $data['completed_count']; ?></td>
                                    <td><?php echo number_format($data['avg_hours'], 2); ?></td>
                                    <td><?php echo number_format($data['total_hours'], 2); ?></td>
                                <?php else: ?>
                                    <td><strong><?php echo htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($data['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($data['school'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($data['supervisor_first_name'] ?? '') . ' ' . ($data['supervisor_last_name'] ?? '')); ?></td>
                                    <td><?php echo isset($data['intern_start_date']) ? date('M d, Y', strtotime($data['intern_start_date'])) : 'N/A'; ?></td>
                                    <td><?php echo isset($data['intern_end_date']) ? date('M d, Y', strtotime($data['intern_end_date'])) : 'N/A'; ?></td>
                                    <td><?php echo $data['total_entries']; ?></td>
                                    <td><strong><?php echo number_format($data['total_hours'], 2); ?></strong></td>
                                    <td><?php echo $data['days_worked']; ?></td>
                                    <td><?php echo number_format($data['avg_hours'], 2); ?></td>
                                    <td><?php echo $data['completed_count']; ?></td>
                                    <td><?php echo $data['missed_count']; ?></td>
                                    <td><?php echo $data['active_count']; ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: 600; background: var(--gray-50);">
                            <?php if ($report_type === 'performance'): ?>
                                <td colspan="7" style="text-align: right;"><?php echo t('total'); ?>:</td>
                                <td><?php echo number_format($total_hours, 2); ?></td>
                                <td colspan="5"></td>
                            <?php elseif ($report_type === 'attendance'): ?>
                                <td colspan="6" style="text-align: right;"><?php echo t('total'); ?>:</td>
                                <td><?php echo number_format(array_sum(array_column($report_data, 'total_hours')), 2); ?></td>
                            <?php else: ?>
                                <td colspan="6" style="text-align: right;"><?php echo t('total'); ?>:</td>
                                <td><?php echo array_sum(array_column($report_data, 'total_entries')); ?></td>
                                <td><?php echo number_format($total_hours, 2); ?></td>
                                <td><?php echo $total_days; ?></td>
                                <td colspan="4"></td>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">📊</div>
                <h3><?php echo t('no_data_available'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_report_data_message'); ?></p>
                <p style="color: var(--gray-400); font-size: 14px; margin-top: 8px;">
                    <?php echo t('try_adjusting_filters'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCustomDate() {
    const period = document.getElementById('period').value;
    const customRange = document.getElementById('custom_date_range');
    customRange.style.display = period === 'custom' ? '' : 'none';
}

function exportReport(format) {
    const form = document.getElementById('reportForm');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'export';
    input.value = format;
    form.appendChild(input);
    form.submit();
}
</script>

<?php include_once '../includes/footer.php'; ?>