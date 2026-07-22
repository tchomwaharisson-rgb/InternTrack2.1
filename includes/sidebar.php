<?php
// includes/sidebar.php
global $conn; // Make sure $conn is available

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
$user = getUserData($_SESSION['user_id']);
$profile_picture = $user['profile_picture'] ?? null;
$default_avatar = '/interntrack/assets/images/default-avatar.png';
$avatar_url = $profile_picture ? '/interntrack/uploads/profiles/' . $profile_picture : $default_avatar;
?>
<nav class="sidebar" id="mainSidebar">
    <!-- Sidebar Header with Toggle Button -->
    <br><br>
    <div class="sidebar-header">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="<?php echo t('toggle_sidebar'); ?>">
            <span class="toggle-icon">◀</span>
        </button>
    </div>
    
    <!-- User Profile Mini -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <?php if ($role === 'intern'): ?>
                <a class="profile-link" href="../intern/profile.php">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo $avatar_url; ?>" alt="Profile">
                    <?php else: 
                    $name = $user['first_name'] . ' ' . $user['last_name'] ?? 'User';
                    $parts = explode(' ', $name);
                    echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                    endif; ?>
                </a>
            <?php elseif ($role === 'supervisor'): ?>
                <a class="profile-link" href="../supervisor/profile.php">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo $avatar_url; ?>" alt="Profile">
                    <?php else: 
                    $name = $user['first_name'] . ' ' . $user['last_name'] ?? 'User';
                    $parts = explode(' ', $name);
                    echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                    endif; ?>
                </a>
            <?php elseif ($role === 'admin'): ?>
                <a class="profile-link" href="../admin/profile.php">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo $avatar_url; ?>" alt="Profile">
                    <?php else: 
                    $name = $user['first_name'] . ' ' . $user['last_name'] ?? 'User';
                    $parts = explode(' ', $name);
                    echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                    endif; ?>
                </a>
            <?php endif; ?>
            
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?></div>
            <div class="sidebar-user-role"><?php echo ucfirst($role); ?></div>
        </div>
    </div>
    
    <ul class="sidebar-nav">
        <li class="nav-section">
            <div class="nav-section-title"><?php echo t('menu'); ?></div>
        </li>
        <li class="nav-item">
            <a href="/interntrack/<?php echo $role; ?>/dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text"><?php echo t('Dashboard'); ?></span>
            </a>
        </li>
        
        <?php if ($role === 'intern'): ?>
            <li class="nav-item">
                <a href="/interntrack/intern/timelog.php" class="<?php echo $current_page === 'timelog.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">⏱️</span>
                    <span class="nav-text"><?php echo t('Timelog'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/intern/goals.php" class="<?php echo $current_page === 'goals.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🎯</span>
                    <span class="nav-text"><?php echo t('Goals'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/intern/leave.php" class="<?php echo $current_page === 'leave.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📅</span>
                    <span class="nav-text"><?php echo t('Leave'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/intern/chat.php" class="<?php echo $current_page === 'chat.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">💬</span>
                    <span class="nav-text"><?php echo t('Chat'); ?></span>
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
                    <span class="nav-icon">👥</span>
                    <span class="nav-text"><?php echo t('Assigned interns'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/timelogs.php" class="<?php echo $current_page === 'timelogs.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">⏱️</span>
                    <span class="nav-text"><?php echo t('Timelog'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/goals.php" class="<?php echo $current_page === 'goals.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🎯</span>
                    <span class="nav-text"><?php echo t('Goals'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/supervisor/chat.php" class="<?php echo $current_page === 'chat.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">💬</span>
                    <span class="nav-text"><?php echo t('Chat'); ?></span>
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
                    <span class="nav-icon">👥</span>
                    <span class="nav-text"><?php echo t('User management'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/requests.php" class="<?php echo $current_page === 'requests.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📝</span>
                    <span class="nav-text"><?php echo t('Registration requests'); ?></span>
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
                    <span class="nav-icon">⏱️</span>
                    <span class="nav-text"><?php echo t('Timelog'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/leave.php" class="<?php echo $current_page === 'leave.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📅</span>
                    <span class="nav-text"><?php echo t('Leave'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text"><?php echo t('System settings'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/interntrack/admin/reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text"><?php echo t('System reports'); ?></span>
                </a>
            </li>
        <?php endif; ?>
        <li class="nav-section">
            <div class="nav-section-title"><?php echo t('Account'); ?></div>
        </li>
        <li class="nav-item">
            <a href="/interntrack/notifications.php">
                <span class="nav-icon">🔔</span>
                <span class="nav-text"><?php echo t('Notifications'); ?></span>
                <?php 
                    $unread_count = getUnreadNotifications($_SESSION['user_id']);
                    if ($unread_count > 0): 
                ?>
                    <span class="badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="/interntrack/auth/logout.php" class="logout-link">
                <span class="nav-icon">🚪</span>
                <span class="nav-text"><?php echo t('Logout'); ?></span>
            </a>
        </li>
    </ul>
</nav>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
/* Sidebar Styles */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 260px;
    background: var(--red-gradient);
    padding: 0;
    overflow-y: auto;
    z-index: 1000;
    transition: transform 0.3s ease, width 0.3s ease;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

/* Sidebar Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
}

.sidebar-brand-link {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: white;
}

.sidebar-logo {
    width: 32px;
    height: 32px;
    border-radius: 8px;
}

.sidebar-brand-text {
    font-size: 20px;
    font-weight: 800;
    color: white;
    letter-spacing: -0.5px;
}

.sidebar-brand-text span {
    color: #FFD700;
}

/* Sidebar Toggle Button */
.sidebar-toggle-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: black;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 14px;
}

.sidebar-toggle-btn:hover {
    background: rgba(247, 83, 83, 0.25);
    transform: scale(1.05);
}

.profile-link {
    text-decoration: none;
    color: solid white;
}

.sidebar-toggle-btn .toggle-icon {
    transition: transform 0.3s ease;
    display: inline-block;
}

/* Sidebar Collapsed State */
.sidebar.collapsed {
    width: 70px;
}

.sidebar.collapsed .sidebar-brand-text,
.sidebar.collapsed .sidebar-user-info,
.sidebar.collapsed .nav-text,
.sidebar.collapsed .nav-section-title,
.sidebar.collapsed .badge {
    display: none;
}

.sidebar.collapsed .sidebar-brand-link {
    justify-content: center;
}

.sidebar.collapsed .sidebar-user {
    justify-content: center;
    padding: 12px 0;
}

.sidebar.collapsed .sidebar-user-avatar {
    width: 40px;
    height: 40px;
}

.sidebar.collapsed .sidebar-toggle-btn .toggle-icon {
    transform: rotate(180deg);
}

.sidebar.collapsed .nav-item a {
    justify-content: center;
    padding: 12px;
}

.sidebar.collapsed .nav-item a .nav-icon {
    margin-right: 0;
    font-size: 22px;
}

.sidebar.collapsed .nav-section {
    padding: 8px 0;
}

/* Sidebar User */
.sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background:black;
    display: flex;
    align-items: center;
    justify-content: center;
    color:  rgba(247, 47, 47, 0.1);
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
    overflow: hidden;
}

.sidebar-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar-user-info {
    flex: 1;
    min-width: 0;
}

.sidebar-user-name {
    font-size: 14px;
    font-weight: 600;
    color: solid black;
}

.sidebar-user-role {
    font-size: 11px;
    color: solid black;
}

/* Sidebar Navigation */
.sidebar-nav {
    list-style: none;
    padding: 12px 0;
    margin: 0;
}

.nav-section {
    padding: 8px 20px;
}

.nav-section-title {
    font-size: 11px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
    font-weight: 600;
    letter-spacing: 0.5px;
}

.nav-item {
    margin: 2px 0;
}

.nav-item a {
    display: flex;
    align-items: center;
    padding: 10px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.2s ease;
    border-radius: 8px;
    margin: 0 8px;
    gap: 12px;
    position: relative;
}

.nav-item a:hover {
    background: rgba(250, 110, 110, 0.15);
    color: black;
    transform: translateX(4px);
}

.nav-item a.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.nav-item a .nav-icon {
    font-size: 18px;
    flex-shrink: 0;
    width: 24px;
    text-align: center;
}

.nav-item a .nav-text {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
}

.nav-item a .badge {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    padding: 1px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.nav-item a.logout-link {
    color: black;
    margin-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 16px;
    border-radius: 0;
    margin: 8px 8px 0;
}

.nav-item a.logout-link:hover {
    color: #ff6b6b;
    background: rgba(255, 0, 0, 0.2);
}

/* Sidebar Overlay (for mobile) */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.sidebar-overlay.active {
    display: block;
}

/* Mobile Hamburger Button */
.mobile-menu-btn {
    display: none;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--gray-700);
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.3s;
}

.mobile-menu-btn:hover {
    background: var(--gray-100);
}

body.dark-mode .mobile-menu-btn {
    color: #e5e7eb;
}

body.dark-mode .mobile-menu-btn:hover {
    background: #3a3a3a;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .sidebar.collapsed {
        width: 70px;
    }
    
    .sidebar.collapsed.mobile-open {
        width: 70px;
    }
    
    .sidebar.collapsed .sidebar-brand-text,
    .sidebar.collapsed .sidebar-user-info,
    .sidebar.collapsed .nav-text,
    .sidebar.collapsed .nav-section-title,
    .sidebar.collapsed .badge {
        display: none;
    }
    
    .sidebar.collapsed .sidebar-brand-link {
        justify-content: center;
    }
    
    .sidebar.collapsed .sidebar-user {
        justify-content: center;
        padding: 12px 0;
    }
    
    .sidebar.collapsed .sidebar-user-avatar {
        width: 40px;
        height: 40px;
    }
    
    .sidebar.collapsed .nav-item a {
        justify-content: center;
        padding: 12px;
    }
    
    .sidebar.collapsed .nav-item a .nav-icon {
        margin-right: 0;
        font-size: 22px;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Collapsed state on mobile - still keep the toggle */
    .sidebar.collapsed:not(.mobile-open) {
        transform: translateX(-100%);
    }
    
    .sidebar.collapsed.mobile-open {
        transform: translateX(0);
        width: 70px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 280px;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
}

/* Scrollbar Styling */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>

<script>
    // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('mainSidebar');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                        
        // Load saved state from localStorage
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
            updateToggleIcon();
        }
                        
        // Toggle sidebar collapse (desktop)
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                    // On mobile, toggle the sidebar open/close
                    sidebar.classList.toggle('mobile-open');
                    overlay.classList.toggle('active');
                } else {
                    // On desktop, toggle collapsed state
                    sidebar.classList.toggle('collapsed');
                    // Save state
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                    updateToggleIcon();
                    // Adjust main content margin
                    adjustMainContent();
                }
            });
        }
                        
        // Update toggle icon
        function updateToggleIcon() {
            const icon = toggleBtn?.querySelector('.toggle-icon');
            if (icon) {
                if (sidebar.classList.contains('collapsed')) {
                    icon.textContent = '▶';
                } else {
                    icon.textContent = '◀';
                }
            }
        }
                        
        // Adjust main content margin when sidebar collapses/expands
        function adjustMainContent() {
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                if (sidebar.classList.contains('collapsed') && window.innerWidth > 768) {
                    mainContent.style.marginLeft = '70px';
                } else if (window.innerWidth > 768) {
                    mainContent.style.marginLeft = '260px';
                }
            }
        }
                        
        // Mobile sidebar toggle (hamburger menu)
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                // If sidebar was collapsed on desktop, ensure mobile shows full version
                if (sidebar.classList.contains('collapsed') && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('collapsed');
                }
            });
        }
                        
        // Close sidebar on overlay click (mobile)
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
        }
                        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (window.innerWidth > 768) {
                    // Desktop view
                    overlay.classList.remove('active');
                    sidebar.classList.remove('mobile-open');
                    adjustMainContent();
                } else {
                    // Mobile view
                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.style.marginLeft = '0';
                    }
                }
            }, 250);
        });
                        
        // Initial adjustment
        adjustMainContent();
    });

    // Close sidebar on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        }
    });
</script>