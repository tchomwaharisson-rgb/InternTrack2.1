<?php
// includes/header.php
global $conn; // Make sure $conn is available

$user = getUserData($_SESSION['user_id']);
$unread_notifications = getUnreadNotifications($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);
$profile_picture = $user['profile_picture'] ?? null;
$default_avatar = '/interntrack/assets/images/default-avatar.png';
$avatar_url = $profile_picture ? '/interntrack/uploads/profiles/' . $profile_picture : $default_avatar;
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>" data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('app_name'); ?> - <?php echo ucfirst($_SESSION['user_role'] ?? 'Dashboard'); ?></title>
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
    <link rel="stylesheet" href="/interntrack/assets/css/dark-mode.css" disabled>
    <link rel="icon" href="/interntrack/assets/images/logo-icon.png">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div style="display: flex; align-items: center; gap: 16px;">
            <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn" title="<?php echo t('toggle_menu'); ?>">
            ☰
        </button>
        
        <button class="sidebar-toggle" style="background: none; border: none; font-size: 24px; cursor: pointer; display: none;" onclick="document.querySelector('.sidebar').classList.toggle('open')">
            ☰
        </button>
        <a href="/interntrack/<?php echo $_SESSION['user_role'] ?? ''; ?>/dashboard.php" class="header-brand">
            <a href="/interntrack/<?php echo $_SESSION['user_role'] ?? ''; ?>/dashboard.php" class="header-brand">
                <!DOCTYPE html>
                <html>
                <head>
                <style>
                .logo-option1 {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    text-decoration: none;
                }
                .logo-option1 .logo-icon {
                    position: relative;
                    width: 48px;
                    height: 48px;
                    background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 100%);
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 15px rgba(211, 47, 47, 0.3);
                    transition: transform 0.3s ease;
                }
                .logo-option1 .logo-icon:hover {
                    transform: scale(1.05);
                }
                .logo-option1 .logo-icon .letter {
                    color: white;
                    font-size: 24px;
                    font-weight: 800;
                    letter-spacing: -1px;
                    position: relative;
                    z-index: 2;
                }
                .logo-option1 .logo-icon .track-line {
                    position: absolute;
                    bottom: 8px;
                    left: 8px;
                    right: 8px;
                    height: 3px;
                    background: rgba(255, 255, 255, 0.4);
                    border-radius: 2px;
                    overflow: hidden;
                }
                .logo-option1 .logo-icon .track-line::after {
                    content: '';
                    position: absolute;
                    left: -100%;
                    width: 50%;
                    height: 100%;
                    background: white;
                    border-radius: 2px;
                    animation: trackMove 3s ease-in-out infinite;
                }
                @keyframes trackMove {
                    0% { left: -100%; }
                    100% { left: 200%; }
                }
                .logo-option1 .logo-text {
                    display: flex;
                    flex-direction: column;
                }
                .logo-option1 .logo-text .main-text {
                    font-size: 28px;
                    font-weight: 800;
                    color: #1A1A1A;
                    line-height: 1;
                    letter-spacing: -0.5px;
                }
                .logo-option1 .logo-text .main-text .red {
                    color: #D32F2F;
                }
                .logo-option1 .logo-text .sub-text {
                    font-size: 10px;
                    font-weight: 600;
                    color: #666;
                    letter-spacing: 2.5px;
                    text-transform: uppercase;
                    margin-top: 2px;
                }
                </style>
                </head>
                <body>
                <a href="#" class="logo-option1">
                    <div class="logo-icon">
                        <span class="letter">IT</span>
                        <div class="track-line"></div>
                    </div>
                    <div class="logo-text">
                        <span class="main-text">Intern<span class="red">Track</span></span>
                        <span class="sub-text">Management System</span>
                    </div>
                </a>
                </body>
                </html>
            </a>
        </div>
        <div class="header-actions">
            <div class="language-switcher">
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" onclick="switchLanguage('en')">EN</button>
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>" onclick="switchLanguage('fr')">FR</button>
            </div>
            
            <button class="theme-toggle" title="<?php echo t('theme'); ?>" onclick="toggleTheme()">
                <?php echo ($_SESSION['theme'] ?? 'light') === 'light' ? '🌙' : '☀️'; ?>
            </button>
            
            <button class="notification-btn" title="<?php echo t('notifications'); ?>" onclick="toggleNotifications()">
                🔔
                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                <?php endif; ?>
                <div class="dropdown-menu" id="notificationDropdown" style="display: none;">
                    <div style="padding: 8px 16px; font-weight: 600; border-bottom: 1px solid var(--gray-200);">
                        <?php echo t('notifications'); ?>
                    </div>
                    <div style="padding: 8px 16px; border-top: 1px solid var(--gray-200); text-align: center;">
                        <a href="/interntrack/notifications.php" style="font-size: 13px; color: var(--primary-color);"><?php echo t('view_all'); ?></a>
                    </div>
                    <?php 
                    try {
                        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                        $stmt->execute([$_SESSION['user_id']]);
                        $notifications = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        $notifications = [];
                    }
                    ?>
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $notif): ?>
                            <a href="<?php echo $notif['link'] ?? '#'; ?>" style="display: block; padding: 8px 16px; border-bottom: 1px solid var(--gray-200); font-size: 13px; <?php echo $notif['is_read'] ? '' : 'background: var(--red-50);'; ?>">
                                <?php echo htmlspecialchars($notif['message']); ?>
                                <div style="font-size: 11px; color: var(--gray-500);">
                                    <?php echo timeAgo($notif['created_at']); ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 16px; text-align: center; color: var(--gray-500);">
                            <?php echo t('no_notifications'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </button>
            
            <div class="user-menu" onclick="toggleUserMenu()">
                <div class="user-avatar" id="headerAvatar">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo $avatar_url; ?>" 
                             alt="Profile" 
                             style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: 
                        $name = $user['first_name'] . ' ' . $user['last_name'] ?? 'User';
                        $parts = explode(' ', $name);
                        echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                    endif; ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?></span>
                <span style="font-size: 12px; color: var(--gray-500);">▼</span>
                
                <?php if ($role === 'intern'): ?>
                    <div class="dropdown-menu" id="userDropdown" style="display: none;">
                        <a href="/interntrack/intern/profile.php">
                            <span>👤</span> <?php echo t('profile'); ?>
                        </a>
                        <div class="divider"></div>
                        <a href="/interntrack/auth/logout.php" style="color: var(--primary-color);">
                            <span>🚪</span> <?php echo t('logout'); ?>
                        </a>
                    </div>
                <?php elseif ($role === 'supervisor'): ?>
                    <div class="dropdown-menu" id="userDropdown" style="display: none;">
                        <a href="/interntrack/supervisor/profile.php">
                            <span>👤</span> <?php echo t('profile'); ?>
                        </a>
                        <div class="divider"></div>
                        <a href="/interntrack/auth/logout.php" style="color: var(--primary-color);">
                            <span>🚪</span> <?php echo t('logout'); ?>
                        </a>
                    </div>

                <?php elseif ($role === 'admin'): ?>
                    <div class="dropdown-menu" id="userDropdown" style="display: none;">
                        <a href="/interntrack/admin/profile.php">
                            <span>👤</span> <?php echo t('profile'); ?>
                        </a>
                        <div class="divider"></div>
                        <a href="/interntrack/auth/logout.php" style="color: var(--primary-color);">
                            <span>🚪</span> <?php echo t('logout'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Include Sidebar -->
    <?php include_once 'sidebar.php'; ?>
    
    <script>
    function switchLanguage(lang) {
        fetch('/interntrack/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'language', language: lang })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
    
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        fetch('/interntrack/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'theme', theme: newTheme })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.documentElement.setAttribute('data-theme', newTheme);
                const darkModeCss = document.querySelector('link[href*="dark-mode.css"]');
                if (darkModeCss) {
                    darkModeCss.disabled = newTheme !== 'dark';
                }
                document.querySelector('.theme-toggle').textContent = newTheme === 'light' ? '🌙' : '☀️';
                document.body.classList.toggle('dark-mode', newTheme === 'dark');
            }
        });
    }
    
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        if (dropdown.style.display === 'block') {
            fetch('/interntrack/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ action: 'mark_read' })
            });
        }
    }
    
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notification-btn')) {
            document.getElementById('notificationDropdown').style.display = 'none';
        }
        if (!e.target.closest('.user-menu')) {
            document.getElementById('userDropdown').style.display = 'none';
        }
    });
    
    // Initialize dark mode
    document.addEventListener('DOMContentLoaded', function() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            const darkModeCss = document.querySelector('link[href*="dark-mode.css"]');
            if (darkModeCss) {
                darkModeCss.disabled = false;
            }
        }
    });
    </script>