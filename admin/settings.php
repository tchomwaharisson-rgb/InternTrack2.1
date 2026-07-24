<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'work_start' => $_POST['work_start'] ?? '08:00:00',
        'work_end' => $_POST['work_end'] ?? '18:00:00',
        'break_start' => $_POST['break_start'] ?? '12:00:00',
        'break_end' => $_POST['break_end'] ?? '14:00:00',
        'max_break_minutes' => $_POST['max_break_minutes'] ?? '120',
        'clock_in_reminder_time' => $_POST['clock_in_reminder_time'] ?? '08:00:00',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? 'true' : 'false',
        'allow_early_clock_in' => isset($_POST['allow_early_clock_in']) ? 'true' : 'false',
        'max_weekly_hours' => $_POST['max_weekly_hours'] ?? '40',
        'daily_work_hours' => $_POST['daily_work_hours'] ?? '8',
        'notification_email' => $_POST['notification_email'] ?? ADMIN_EMAIL,
    ];
    
    foreach ($settings as $key => $value) {
        updateSetting($key, $value);
    }
    
    $message = t('settings_updated');
    $message_type = 'success';
    logAudit($_SESSION['user_id'], 'update_settings', 'Updated system settings');
}

// Get current settings
$work_start = getSetting('work_start') ?? '08:00:00';
$work_end = getSetting('work_end') ?? '18:00:00';
$break_start = getSetting('break_start') ?? '12:00:00';
$break_end = getSetting('break_end') ?? '14:00:00';
$max_break_minutes = getSetting('max_break_minutes') ?? '120';
$clock_in_reminder_time = getSetting('clock_in_reminder_time') ?? '08:00:00';
$maintenance_mode = getSetting('maintenance_mode') === 'true';
$allow_early_clock_in = getSetting('allow_early_clock_in') === 'true';
$max_weekly_hours = getSetting('max_weekly_hours') ?? '40';
$daily_work_hours = getSetting('daily_work_hours') ?? '8';
$notification_email = getSetting('notification_email') ?? ADMIN_EMAIL;

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('system_settings'); ?></h3>
        </div>
        
        <form method="POST" action="">
            <!-- Work Hours -->
            <h4 style="margin-bottom: 16px;"><?php echo t('work_hours_settings'); ?></h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="work_start"><?php echo t('work_start'); ?></label>
                    <input type="time" id="work_start" name="work_start" class="form-control" 
                           value="<?php echo $work_start; ?>" required>
                </div>
                <div class="form-group">
                    <label for="work_end"><?php echo t('work_end'); ?></label>
                    <input type="time" id="work_end" name="work_end" class="form-control" 
                           value="<?php echo $work_end; ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="daily_work_hours"><?php echo t('daily_work_hours'); ?></label>
                    <input type="number" id="daily_work_hours" name="daily_work_hours" class="form-control" 
                           value="<?php echo $daily_work_hours; ?>" min="1" max="12" step="0.5" required>
                </div>
                <div class="form-group">
                    <label for="max_weekly_hours"><?php echo t('max_weekly_hours'); ?></label>
                    <input type="number" id="max_weekly_hours" name="max_weekly_hours" class="form-control" 
                           value="<?php echo $max_weekly_hours; ?>" min="1" max="80" required>
                </div>
            </div>
            
            <!-- Break Settings -->
            <h4 style="margin: 24px 0 16px;"><?php echo t('break_settings'); ?></h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="break_start"><?php echo t('break_start'); ?></label>
                    <input type="time" id="break_start" name="break_start" class="form-control" 
                           value="<?php echo $break_start; ?>" required>
                </div>
                <div class="form-group">
                    <label for="break_end"><?php echo t('break_end'); ?></label>
                    <input type="time" id="break_end" name="break_end" class="form-control" 
                           value="<?php echo $break_end; ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="max_break_minutes"><?php echo t('max_break_duration'); ?> (minutes)</label>
                <input type="number" id="max_break_minutes" name="max_break_minutes" class="form-control" 
                       value="<?php echo $max_break_minutes; ?>" min="1" max="240" required>
                <small style="color: var(--secondary-text);"><?php echo t('max_break_duration_help'); ?></small>
            </div>
            
            <!-- Reminder Settings -->
            <h4 style="margin: 24px 0 16px;"><?php echo t('reminder_settings'); ?></h4>
            <div class="form-group">
                <label for="clock_in_reminder_time"><?php echo t('clock_in_reminder_time'); ?></label>
                <input type="time" id="clock_in_reminder_time" name="clock_in_reminder_time" class="form-control" 
                       value="<?php echo $clock_in_reminder_time; ?>" required>
                <small style="color: var(--secondary-text);"><?php echo t('clock_in_reminder_time_help'); ?></small>
            </div>
            
            <!-- Notification Settings -->
            <h4 style="margin: 24px 0 16px;"><?php echo t('notification_settings'); ?></h4>
            <div class="form-group">
                <label for="notification_email"><?php echo t('notification_email'); ?></label>
                <input type="email" id="notification_email" name="notification_email" class="form-control" 
                       value="<?php echo $notification_email; ?>" required>
                <small style="color: var(--secondary-text);"><?php echo t('notification_email_help'); ?></small>
            </div>
            
            <!-- System Settings -->
            <h4 style="margin: 24px 0 16px;"><?php echo t('system_settings'); ?></h4>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="allow_early_clock_in" <?php echo $allow_early_clock_in ? 'checked' : ''; ?>>
                    <?php echo t('allow_early_clock_in'); ?>
                </label>
                <small style="color: var(--secondary-text);"><?php echo t('allow_early_clock_in_help'); ?></small>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                    <?php echo t('maintenance_mode'); ?>
                </label>
                <small style="color: var(--secondary-text);"><?php echo t('maintenance_mode_help'); ?></small>
            </div>
            
            <button type="submit" class="btn btn-primary"><?php echo t('save_settings'); ?></button>
        </form>
    </div>
    
    <!-- System Information -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('system_information'); ?></h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div>
                <strong><?php echo t('php_version'); ?></strong>
                <div><?php echo phpversion(); ?></div>
            </div>
            <div>
                <strong><?php echo t('mysql_version'); ?></strong>
                <div><?php echo $conn->getAttribute(PDO::ATTR_SERVER_VERSION); ?></div>
            </div>
            <div>
                <strong><?php echo t('server'); ?></strong>
                <div><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
            </div>
            <div>
                <strong><?php echo t('server_time'); ?></strong>
                <div><?php echo date('Y-m-d H:i:s'); ?></div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>