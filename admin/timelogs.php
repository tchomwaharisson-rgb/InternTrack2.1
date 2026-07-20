<?php
// admin/timelog.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$intern_id = $_GET['intern_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get all interns for filter dropdown
$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'intern' ORDER BY first_name");
$stmt->execute();
$interns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build the query
$sql = "SELECT tl.*, u.first_name, u.last_name, u.email, 
               i.school, i.field_of_study, i.supervisor_id,
               s.first_name as supervisor_first_name, s.last_name as supervisor_last_name
        FROM time_logs tl
        JOIN users u ON tl.intern_id = u.id
        LEFT JOIN interns i ON u.id = i.user_id
        LEFT JOIN users s ON i.supervisor_id = s.id
        WHERE tl.date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if (!empty($intern_id)) {
    $sql .= " AND tl.intern_id = ?";
    $params[] = $intern_id;
}

if (!empty($status)) {
    $sql .= " AND tl.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY tl.date DESC, tl.clock_in DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$timelogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get today's date for quick filters
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

include_once '../includes/header.php';
?>

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
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="btn btn-sm btn-secondary" onclick="setDateRange('today')"><?php echo t('today'); ?></button>
                <button class="btn btn-sm btn-secondary" onclick="setDateRange('week')"><?php echo t('this_week'); ?></button>
                <button class="btn btn-sm btn-secondary" onclick="setDateRange('month')"><?php echo t('this_month'); ?></button>
                <button class="btn btn-sm btn-secondary" onclick="resetFilters()"><?php echo t('reset'); ?></button>
            </div>
        </div>
        <form method="GET" action="" id="filterForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="intern_id"><?php echo t('intern'); ?></label>
                    <select id="intern_id" name="intern_id" class="form-control" onchange="this.form.submit()">
                        <option value=""><?php echo t('all_interns'); ?></option>
                        <?php foreach ($interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>" 
                                    <?php echo $intern_id == $intern['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                                (<?php echo htmlspecialchars($intern['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from"><?php echo t('date_from'); ?></label>
                    <input type="date" id="date_from" name="date_from" class="form-control" 
                           value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label for="date_to"><?php echo t('date_to'); ?></label>
                    <input type="date" id="date_to" name="date_to" class="form-control" 
                           value="<?php echo $date_to; ?>" onchange="this.form.submit()">
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
            <div class="form-row">
                <div class="form-group">
                    <label for="search"><?php echo t('search'); ?></label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="<?php echo t('search_interns'); ?>" 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><?php echo t('search'); ?></button>
                        <?php if ($search || $intern_id || $status): ?>
                            <a href="/interntrack/admin/timelog.php" class="btn btn-secondary"><?php echo t('clear'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                    <button type="button" class="btn btn-success" onclick="exportCSV()">
                        📊 <?php echo t('export_csv'); ?>
                    </button>
                    <button type="button" class="btn btn-danger" onclick="exportPDF()">
                        📄 <?php echo t('export_pdf'); ?>
                    </button>
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
                            <th><?php echo t('actions'); ?></th>
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
                                    <?php if ($log['school']): ?>
                                        <div style="font-size: 11px; color: var(--gray-400);">
                                            <?php echo htmlspecialchars($log['school']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($log['supervisor_first_name']): ?>
                                        <div style="font-size: 11px; color: var(--gray-400);">
                                            <?php echo t('supervisor'); ?>: <?php echo htmlspecialchars($log['supervisor_first_name'] . ' ' . $log['supervisor_last_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($log['date'])); ?></td>
                                <td><?php echo $log['clock_in'] ? formatTime($log['clock_in']) : '-'; ?></td>
                                <td><?php echo $log['clock_out'] ? formatTime($log['clock_out']) : '-'; ?></td>
                                <td><?php echo $log['break_start'] ? formatTime($log['break_start']) : '-'; ?></td>
                                <td><?php echo $log['break_end'] ? formatTime($log['break_end']) : '-'; ?></td>
                                <td><?php echo $log['total_break_minutes'] ?? 0; ?> min</td>
                                <td>
                                    <strong><?php echo number_format($log['total_hours'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="viewDetails(<?php echo $log['id']; ?>)">
                                        👁️ <?php echo t('view'); ?>
                                    </button>
                                    <?php if ($log['status'] === 'active'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="forceClockOut(<?php echo $log['id']; ?>, <?php echo $log['intern_id']; ?>)">
                                            ⏹️ <?php echo t('force_clock_out'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: 600; background: var(--gray-50);">
                            <td colspan="7" style="text-align: right;"><?php echo t('total'); ?>:</td>
                            <td><?php echo number_format($total_hours, 2); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                <h3><?php echo t('no_timelogs_found'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_timelogs_message'); ?></p>
                <p style="color: var(--gray-400); font-size: 14px; margin-top: 8px;">
                    <?php echo t('try_adjusting_filters'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('timelog_details'); ?></h3>
            <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent">
            <!-- Dynamic content -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')"><?php echo t('close'); ?></button>
        </div>
    </div>
</div>

<!-- Force Clock Out Modal -->
<div class="modal-overlay" id="forceClockOutModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo t('force_clock_out'); ?></h3>
            <button class="modal-close" onclick="closeModal('forceClockOutModal')">&times;</button>
        </div>
        <form method="POST" action="/interntrack/api/clock.php" id="forceClockOutForm">
            <input type="hidden" name="action" value="force_clock_out">
            <input type="hidden" name="timelog_id" id="force_timelog_id">
            <input type="hidden" name="intern_id" id="force_intern_id">
            <div class="modal-body">
                <p><?php echo t('force_clock_out_warning'); ?></p>
                <div class="form-group">
                    <label for="force_clock_out_time"><?php echo t('clock_out_time'); ?></label>
                    <input type="datetime-local" id="force_clock_out_time" name="clock_out_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="force_clock_out_reason"><?php echo t('reason'); ?></label>
                    <textarea id="force_clock_out_reason" name="reason" class="form-control" rows="2" 
                              placeholder="<?php echo t('reason_for_force_clock_out'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('forceClockOutModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-danger"><?php echo t('force_clock_out'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// Set date range shortcuts
function setDateRange(range) {
    const today = new Date();
    let from, to;
    
    switch(range) {
        case 'today':
            from = today.toISOString().split('T')[0];
            to = today.toISOString().split('T')[0];
            break;
        case 'week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay() + 1);
            from = weekStart.toISOString().split('T')[0];
            to = today.toISOString().split('T')[0];
            break;
        case 'month':
            from = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-01';
            to = today.toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('date_from').value = from;
    document.getElementById('date_to').value = to;
    document.getElementById('filterForm').submit();
}

// Reset filters
function resetFilters() {
    window.location.href = '/interntrack/admin/timelog.php';
}

// View details
function viewDetails(id) {
    // Find the log data from the table
    const row = document.querySelector(`tr:has(button[onclick*="${id}"])`);
    if (row) {
        const cells = row.querySelectorAll('td');
        const content = document.getElementById('detailsContent');
        content.innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div><strong><?php echo t('intern'); ?>:</strong> ${cells[0].textContent.trim()}</div>
                <div><strong><?php echo t('date'); ?>:</strong> ${cells[1].textContent.trim()}</div>
                <div><strong><?php echo t('clock_in'); ?>:</strong> ${cells[2].textContent.trim()}</div>
                <div><strong><?php echo t('clock_out'); ?>:</strong> ${cells[3].textContent.trim()}</div>
                <div><strong><?php echo t('break_start'); ?>:</strong> ${cells[4].textContent.trim()}</div>
                <div><strong><?php echo t('break_end'); ?>:</strong> ${cells[5].textContent.trim()}</div>
                <div><strong><?php echo t('break_duration'); ?>:</strong> ${cells[6].textContent.trim()}</div>
                <div><strong><?php echo t('total_hours'); ?>:</strong> ${cells[7].textContent.trim()}</div>
                <div><strong><?php echo t('status'); ?>:</strong> ${cells[8].textContent.trim()}</div>
            </div>
        `;
    }
    document.getElementById('detailsModal').classList.add('show');
}

// Force clock out
function forceClockOut(timelogId, internId) {
    document.getElementById('force_timelog_id').value = timelogId;
    document.getElementById('force_intern_id').value = internId;
    
    // Set default time to now
    const now = new Date();
    const formatted = now.toISOString().slice(0, 16);
    document.getElementById('force_clock_out_time').value = formatted;
    
    document.getElementById('forceClockOutModal').classList.add('show');
}

// Close modal
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close modal on outside click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

// Export CSV
function exportCSV() {
    const table = document.getElementById('timelogTable');
    if (!table) return;
    
    let csv = [];
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Data
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Clean up the text (remove extra whitespace, line breaks)
            let text = td.textContent.trim().replace(/\n/g, ' ').replace(/\s+/g, ' ');
            // If it contains a comma, wrap in quotes
            if (text.includes(',')) {
                text = `"${text}"`;
            }
            row.push(text);
        });
        csv.push(row.join(','));
    });
    
    // Download
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `timelogs_${document.getElementById('date_from').value}_to_${document.getElementById('date_to').value}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    
    showToast('<?php echo t('export_success'); ?>', 'success');
}

// Export PDF (simplified - prints the table)
function exportPDF() {
    window.print();
}

// Handle force clock out form submission
document.getElementById('forceClockOutForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/interntrack/api/clock.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('force_clock_out_success'); ?>', 'success');
            closeModal('forceClockOutModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || '<?php echo t('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('error_occurred'); ?>', 'error');
        console.error(error);
    });
});

// Auto-submit form on date change
document.querySelectorAll('#date_from, #date_to, #status, #intern_id').forEach(el => {
    el.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Toast notification function (if not already defined)
function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container');
    if (!container) {
        const newContainer = document.createElement('div');
        newContainer.className = 'toast-container';
        document.body.appendChild(newContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.querySelector('.toast-container').appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
}
</script>

<?php include_once '../includes/footer.php'; ?>