<?php
require_once 'config/config.php';
require_once 'config/language.php';

// // If user is logged in, redirect to appropriate dashboard
// if (isLoggedIn()) {
//     $role = $_SESSION['user_role'];
//     if ($role === 'admin') {
//         header('Location: /interntrack/admin/dashboard.php');
//     } elseif ($role === 'supervisor') {
//         header('Location: /interntrack/supervisor/dashboard.php');
//     } else {
//         header('Location: /interntrack/intern/dashboard.php');
//     }
//     exit;
// }

// Get system statistics for the landing page
$stats = [];
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'intern' AND is_active = TRUE");
$stats['active_interns'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'supervisor' AND is_active = TRUE");
$stats['active_supervisors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM time_logs WHERE date = CURDATE() AND clock_in IS NOT NULL");
$stats['today_clocked_in'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM registration_requests WHERE status = 'pending'");
$stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent testimonials or activities
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, tl.date, tl.clock_in 
    FROM time_logs tl 
    JOIN users u ON tl.intern_id = u.id 
    WHERE tl.date = CURDATE() AND tl.clock_in IS NOT NULL 
    ORDER BY tl.clock_in DESC 
    LIMIT 5
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>" data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('app_name'); ?> - Smart Internship Management</title>
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
    <link rel="stylesheet" href="/interntrack/assets/css/dark-mode.css" disabled>
    <link rel="icon" href="/interntrack/assets/images/logo-icon.png">
    <style>
        .hero {
            padding: 80px 0;
            text-align: center;
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--primary-red-dark) 100%);
            color: white;
            border-radius: var(--border-radius);
            margin-bottom: 40px;
        }
        .hero h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .hero h1 span {
            color: #FFD700;
        }
        .hero p {
            font-size: 20px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 32px;
        }
        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .hero-buttons .btn {
            padding: 14px 32px;
            font-size: 16px;
            font-weight: 600;
        }
        .hero-buttons .btn-outline-white {
            background: transparent;
            border: 2px solid white;
            color: white;
        }
        .hero-buttons .btn-outline-white:hover {
            background: white;
            color: var(--primary-red);
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin: 40px 0;
        }
        .feature-card {
            background: var(--primary-white);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .feature-card .feature-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .feature-card h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }
        .feature-card p {
            color: var(--secondary-text);
            font-size: 14px;
        }
        .stats-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 24px;
            background: var(--primary-white);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow);
            margin: 40px 0;
        }
        .stats-banner .stat-item {
            text-align: center;
        }
        .stats-banner .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-red);
        }
        .stats-banner .stat-label {
            font-size: 14px;
            color: var(--secondary-text);
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .header-actions .btn-nav {
            padding: 8px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        .header-actions .btn-login {
            background: var(--primary-red);
            color: white;
        }
        .header-actions .btn-login:hover {
            background: var(--primary-red-dark);
        }
        .header-actions .btn-register {
            background: transparent;
            color: var(--primary-text);
            border: 1px solid var(--primary-gray-dark);
        }
        .header-actions .btn-register:hover {
            background: var(--primary-gray);
        }
        .recent-activities {
            background: var(--primary-white);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            margin-top: 40px;
        }
        .recent-activities h3 {
            margin-bottom: 16px;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--primary-gray-dark);
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .activity-time {
            font-size: 12px;
            color: var(--secondary-text);
            min-width: 60px;
        }
        .activity-item .activity-text {
            font-size: 14px;
        }
        .footer-landing {
            text-align: center;
            padding: 40px 0 20px;
            border-top: 1px solid var(--primary-gray-dark);
            margin-top: 40px;
            color: var(--secondary-text);
        }
        .footer-landing a {
            color: var(--primary-red);
            text-decoration: none;
        }
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }
            .hero p {
                font-size: 16px;
            }
            .stats-banner {
                grid-template-columns: 1fr 1fr;
            }
            .header-actions .btn-nav {
                padding: 6px 12px;
                font-size: 13px;
            }
        }
        @media (max-width: 480px) {
            .hero {
                padding: 40px 0;
            }
            .hero h1 {
                font-size: 24px;
            }
            .stats-banner {
                grid-template-columns: 1fr;
            }
            .header-actions .btn-nav {
                padding: 4px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header" style="position: relative; margin-bottom: 0;">
        <a href="/interntrack/" class="header-brand">
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
        <div class="header-actions">
            <div class="header-actions">
            <!-- Language Switcher -->
            <div class="language-switcher">
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" 
                        data-lang="en" onclick="switchLanguage('en')">EN</button>
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>"
                        data-lang="fr" onclick="switchLanguage('fr')">FR</button>
            <button class="theme-toggle" title="<?php echo t('theme'); ?>">
                <?php echo ($_SESSION['theme'] ?? 'light') === 'light' ? '🌙' : '☀️'; ?>
            </button>
            
            <a href="/interntrack/auth/login.php" class="btn-nav btn-login"><?php echo t('login'); ?></a>
            <a href="/interntrack/auth/register.php" class="btn-nav btn-register"><?php echo t('register'); ?></a>
        </div>
    </header>

    <div class="main-content" style="margin-left: 0; margin-top: 0; padding: 24px;">
        <!-- Hero Section -->
        <section class="hero">
            <h1><?php echo t('app_name'); ?> <span>.</span></h1>
            <p><?php echo t('app_tagline'); ?></p>
            <div class="hero-buttons">
                <a href="/interntrack/auth/login.php" class="btn btn-primary"><?php echo t('login'); ?></a>
                <a href="/interntrack/auth/register.php" class="btn btn-outline-white"><?php echo t('register'); ?></a>
            </div>
        </section>

        <!-- Statistics Banner -->
        <div class="stats-banner">
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['active_interns']; ?></div>
                <div class="stat-label"><?php echo t('active_interns'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['active_supervisors']; ?></div>
                <div class="stat-label"><?php echo t('supervisors'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['today_clocked_in']; ?></div>
                <div class="stat-label"><?php echo t('clocked_in_today'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label"><?php echo t('pending_registrations'); ?></div>
            </div>
        </div>

        <!-- Features -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">⏱️</div>
                <h3><?php echo t('time_tracking'); ?></h3>
                <p><?php echo t('time_tracking_description'); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👔</div>
                <h3><?php echo t('supervision'); ?></h3>
                <p><?php echo t('supervision_description'); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3><?php echo t('real_time_chat'); ?></h3>
                <p><?php echo t('real_time_chat_description'); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3><?php echo t('goal_management'); ?></h3>
                <p><?php echo t('goal_management_description'); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3><?php echo t('reports_analytics'); ?></h3>
                <p><?php echo t('reports_analytics_description'); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3><?php echo t('secure_reliable'); ?></h3>
                <p><?php echo t('secure_reliable_description'); ?></p>
            </div>
        </div>

        <!-- Recent Activities -->
        <?php if ($recent_activities): ?>
        <div class="recent-activities">
            <h3>🕐 <?php echo t('today_s_activity'); ?></h3>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <span class="activity-time"><?php echo date('H:i', strtotime($activity['clock_in'])); ?></span>
                    <span class="activity-text">
                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong> 
                        clocked in
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-landing">
            <p>&copy; <?php echo date('Y'); ?> <?php echo t('app_name'); ?>. <?php echo t('all_rights_reserved'); ?></p>
            <p style="font-size: 13px; margin-top: 4px;">
                <?php echo t('built_with_heart'); ?>
            </p>
        </div>
    </div>

    <script>
        // Language switcher
        document.querySelectorAll('.language-switcher button').forEach(btn => {
            btn.addEventListener('click', function() {
                const lang = this.dataset.lang;
                document.querySelectorAll('.language-switcher button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
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
            });
        });

        // Theme toggle
        document.querySelector('.theme-toggle')?.addEventListener('click', function() {
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
                    this.textContent = newTheme === 'light' ? '🌙' : '☀️';
                }
            });
        });
    </script>
    <script src="/interntrack/assets/js/main.js"></script>
</body>
</html>