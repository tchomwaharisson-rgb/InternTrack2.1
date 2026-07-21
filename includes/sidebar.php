<?php
// includes/sidebar.php
global $conn; // Make sure $conn is available

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
?>
<nav class="sidebar">
    <ul class="sidebar-nav">
        <li class="nav-section">
            <div class="nav-section-title"><?php echo t('menu'); ?></div>
        </li>
        <li class="nav-item">
            <a href="/interntrack/<?php echo $role; ?>/dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                <?php echo t('dashboard'); ?>
            </a>
        </li>
        
        <?php if ($role === 'intern'): ?>
            <li class="nav-item">
                <a href="/interntrack/intern/timelogs.php" class="<?php echo $current_page === 'timelog.php' ? 'active' : ''; ?>">
                    <span class="icon">⏱️</span>
                    <?php echo t('timelog'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/intern/goals.php" class="<?php echo $current_page === 'goals.php' ? 'active' : ''; ?>">
                    <span class="icon">🎯</span>
                    <?php echo t('goals'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/intern/leave.php" class="<?php echo $current_page === 'leave.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    <?php echo t('leave Request'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/intern/chat.php" class="<?php echo $current_page === 'chat.php' ? 'active' : ''; ?>">
                    <span class="icon">💬</span>
                    <?php echo t('chat'); ?>
                    <?php 
                        try {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
                            $stmt->execute([$_SESSION['user_id']]);
                            $unread_msgs = $stmt->fetchColumn();
                            if ($unread_msgs > 0): 
                        ?>
                            <span class="badge"><?php echo $unread_msgs; ?></span>
                        <?php 
                            endif;
                        } catch (PDOException $e) {
                            // Handle error silently
                        }
                    ?>
                </a>
            </li>
            
        <?php elseif ($role === 'supervisor'): ?>
            <li class="nav-item">
                <a href="/interntrack/supervisor/interns.php" class="<?php echo $current_page === 'interns.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    <?php echo t('assigned_interns'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/timelogs.php" class="<?php echo $current_page === 'timelogs.php' ? 'active' : ''; ?>">
                    <span class="icon">⏱️</span>
                    <?php echo t('timelog'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/leave.php" class="<?php echo $current_page === 'leave.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    <?php echo t('leave Request'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/goals.php" class="<?php echo $current_page === 'goals.php' ? 'active' : ''; ?>">
                    <span class="icon">🎯</span>
                    <?php echo t('goals'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/chat.php" class="<?php echo $current_page === 'chat.php' ? 'active' : ''; ?>">
                    <span class="icon">💬</span>
                    <?php echo t('chat'); ?>
                    <?php 
                        try {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
                            $stmt->execute([$_SESSION['user_id']]);
                            $unread_msgs = $stmt->fetchColumn();
                            if ($unread_msgs > 0): 
                        ?>
                            <span class="badge"><?php echo $unread_msgs; ?></span>
                        <?php 
                            endif;
                        } catch (PDOException $e) {
                            // Handle error silently
                        }
                    ?>
                </a>
            </li>
            
        <?php elseif ($role === 'admin'): ?>
            <li class="nav-item">
                <a href="/interntrack/admin/users.php" class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    <?php echo t('user_management'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/requests.php" class="<?php echo $current_page === 'requests.php' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <?php echo t('registration_requests'); ?>
                    <?php 
                        try {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM registration_requests WHERE status = 'pending'");
                            $stmt->execute();
                            $pending_reqs = $stmt->fetchColumn();
                            if ($pending_reqs > 0): 
                        ?>
                            <span class="badge"><?php echo $pending_reqs; ?></span>
                        <?php 
                            endif;
                        } catch (PDOException $e) {
                            // Handle error silently
                        }
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/timelogs.php" class="<?php echo $current_page === 'timelogs.php' ? 'active' : ''; ?>">
                    <span class="icon">⏱️</span>
                    <?php echo t('timelog'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/leave.php" class="<?php echo $current_page === 'leave.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    <?php echo t('leave Request'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span>
                    <?php echo t('system_settings'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <?php echo t('system_reports'); ?>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>