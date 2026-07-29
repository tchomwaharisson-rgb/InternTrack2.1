<?php
// auth/forgot_password.php
require_once '../config/config.php';
require_once '../config/language.php';

if (isLoggedIn()) {
    header('Location: /interntrack/dashboard.php');
    exit;
}

// Set default language and theme if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}

$current_theme = $_SESSION['theme'];
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('forgot_password_title'); ?> - <?php echo t('app_name'); ?></title>
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
    <link rel="stylesheet" href="/interntrack/assets/css/dark-mode.css" <?php echo $current_theme === 'dark' ? '' : 'disabled'; ?>>
    <link rel="icon" href="/interntrack/assets/images/logo-icon.png">
    <style>
        /* Auth Pages - Red Theme */
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--red-gradient-light);
            padding: 20px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-red);
            color: var(--gray-800);
        }

        .auth-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            border: 1px solid var(--red-200);
        }

        .auth-header {
            background: var(--red-gradient);
            padding: 32px 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .auth-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulseGlow 4s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.5); opacity: 0.8; }
        }

        .auth-logo h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 1;
        }

        .auth-logo p {
            font-size: 14px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .auth-body {
            padding: 32px 40px;
        }

        .auth-body h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--gray-800);
        }

        .auth-subtitle {
            color: var(--gray-500);
            margin-bottom: 24px;
        }

        .info-box {
            background: var(--red-50);
            border: 1px solid var(--red-200);
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-box .info-icon {
            font-size: 24px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .info-box .info-text {
            font-size: 14px;
            color: var(--gray-700);
            line-height: 1.6;
        }

        .info-box .info-text strong {
            color: var(--primary-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-decoration: none;
            gap: 8px;
        }

        .btn-block {
            width: 100%;
        }

        .btn-primary {
            background: var(--primary-red);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(253, 124, 124, 0.77);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .auth-footer {
            padding: 20px 40px 32px;
            text-align: center;
            border-top: 1px solid var(--gray-200);
        }

        .auth-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .language-switcher {
            display: flex;
            gap: 4px;
            justify-content: center;
            padding: 0 40px 20px;
        }

        .language-switcher button {
            background: none;
            border: 1px solid black;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition-speed);
            font-weight: 500;
        }

        .language-switcher button.active {
            background: var(--primary-red);
            color: white;
            border-color: var(--primary-red);
        }

        .language-switcher button:hover:not(.active) {
            background: var(--gray-100);
        }

        .contact-details {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 16px 20px;
            margin: 16px 0;
        }

        .contact-details .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .contact-details .contact-item:last-child {
            border-bottom: none;
        }

        .contact-details .contact-icon {
            font-size: 18px;
            width: 30px;
            text-align: center;
        }

        .contact-details .contact-text {
            font-size: 14px;
            color: var(--gray-700);
        }

        .contact-details .contact-text a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .contact-details .contact-text a:hover {
            text-decoration: underline;
        }

        /* Dark Mode Support */
        body.dark-mode .auth-container {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d0a0a 100%);
        }

        body.dark-mode .auth-card {
            background: #2d2d2d;
            border-color: #4a1a1a;
        }

        body.dark-mode .auth-body h2 {
            color: #f3f4f6;
        }

        body.dark-mode .auth-footer {
            border-top-color: #4a1a1a;
        }

        body.dark-mode .auth-footer a {
            color: #dc2626;
        }

        body.dark-mode .info-box {
            background: #2d1a1a;
            border-color: #4a1a1a;
        }

        body.dark-mode .info-box .info-text {
            color: #e5e7eb;
        }

        body.dark-mode .language-switcher button {
            border-color: #4a1a1a;
            color: #e5e7eb;
        }

        body.dark-mode .language-switcher button.active {
            background: #dc2626;
            border-color: #dc2626;
            color: white;
        }

        body.dark-mode .language-switcher button:hover:not(.active) {
            background: #3a3a3a;
        }

        body.dark-mode .auth-subtitle {
            color: #9ca3af;
        }

        body.dark-mode .contact-details {
            background: #2d2d2d;
            border-color: #4a1a1a;
        }

        body.dark-mode .contact-details .contact-item {
            border-bottom-color: #4a1a1a;
        }

        body.dark-mode .contact-details .contact-text {
            color: #e5e7eb;
        }

        body.dark-mode .btn-secondary {
            background: #4a4a4a;
            color: #f3f4f6;
        }

        body.dark-mode .btn-secondary:hover {
            background: #5a5a5a;
        }

        @media (max-width: 480px) {
            .auth-body {
                padding: 24px;
            }
            .auth-header {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
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
                </div>
            </div>
            
            <div class="auth-body">
                <h2><?php echo t('forgot_password_title'); ?></h2>
                <p class="auth-subtitle"><?php echo t('forgot_password_contact_admin_subtitle'); ?></p>
                
                <!-- Info Box -->
                <div class="info-box">
                    <span class="info-icon">ℹ️</span>
                    <div class="info-text">
                        <?php echo t('forgot_password_info_message'); ?>
                    </div>
                </div>

                <!-- Contact Details -->
                <div class="contact-details">
                    <div class="contact-item">
                        <span class="contact-icon">📧</span>
                        <span class="contact-text">
                            <strong><?php echo t('email'); ?>:</strong> 
                            <a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a>
                        </span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">📞</span>
                        <span class="contact-text">
                            <strong><?php echo t('phone'); ?>:</strong> 
                            <span>+237 123 456 789</span>
                        </span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">🕐</span>
                        <span class="contact-text">
                            <strong><?php echo t('working_hours'); ?>:</strong> 
                            <span><?php echo t('working_hours_value'); ?></span>
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 16px;">
                    <a href="mailto:<?php echo ADMIN_EMAIL; ?>?subject=<?php echo urlencode(t('password_reset_request_subject')); ?>&body=<?php echo urlencode(t('password_reset_request_body')); ?>" 
                       class="btn btn-primary btn-block">
                        📧 <?php echo t('send_email_to_admin'); ?>
                    </a>
                    <a href="/interntrack/auth/login.php" class="btn btn-secondary btn-block" style="color: var(--primary-red);">
                        ↩️ <?php echo t('back_to_login'); ?>
                    </a>
                </div>

                <!-- Additional Info -->
                <div style="margin-top: 16px; padding: 12px; background: var(--gray-50); border-radius: 8px;">
                    <p style="font-size: 13px; color: var(--gray-500); text-align: center; margin: 0;">
                        <?php echo t('forgot_password_additional_info'); ?>
                    </p>
                </div>
            </div>
            
            <div class="auth-footer">
                <p><?php echo t('remember_password'); ?> <a href="/interntrack/auth/login.php" style="color: var(--primary-red);"><?php echo t('login'); ?></a></p>
                <p><?php echo t('dont_have_account'); ?> <a href="/interntrack/auth/register.php" style="color: var(--primary-red);"><?php echo t('register'); ?></a></p>
            </div>
            
            <style>
                .flag-svg {
                    display: inline-block;
                    width: 24px;
                    height: 16px;
                    vertical-align: middle;
                    margin-right: 6px;
                    border-radius: 2px;
                    border: 1px solid rgba(0,0,0,0.1);
                }

                .language-switcher button {
                    display: inline-flex;
                    align-items: center;
                    padding: 6px 12px;
                    border: 1px solid var(--gray-300);
                    border-radius: 6px;
                    background: transparent;
                    cursor: pointer;
                    font-size: 13px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    color: var(--gray-700);
                    gap: 4px;
                }

                .language-switcher button.active {
                    background: var(--primary-color);
                    color: white;
                    border-color: var(--primary-color);
                }

                .language-switcher button:hover:not(.active) {
                    background: var(--gray-100);
                }
            </style>

                <div class="language-switcher" style="justify-content: center; margin-top: 20px;">
                    <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" data-lang="en" onclick="switchLanguage('en')">
                        <svg class="flag-svg" viewBox="0 0 60 30" xmlns="http://www.w3.org/2000/svg">
                            <rect width="60" height="30" fill="#FFFFFF"/>
                            <rect width="30" height="30" fill="#012169"/>
                            <line x1="0" y1="0" x2="60" y2="30" stroke="#FFFFFF" stroke-width="3"/>
                            <line x1="60" y1="0" x2="0" y2="30" stroke="#FFFFFF" stroke-width="3"/>
                            <line x1="0" y1="0" x2="60" y2="30" stroke="#C8102E" stroke-width="1.5"/>
                            <line x1="60" y1="0" x2="0" y2="30" stroke="#C8102E" stroke-width="1.5"/>
                            <rect x="0" y="14" width="60" height="2" fill="#FFFFFF"/>
                            <rect x="29" y="0" width="2" height="30" fill="#FFFFFF"/>
                            <rect x="0" y="15" width="60" height="1" fill="#C8102E"/>
                            <rect x="30" y="0" width="1" height="30" fill="#C8102E"/>
                        </svg>
                        EN
                    </button>
                    <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>" data-lang="fr" onclick="switchLanguage('fr')">
                        <svg class="flag-svg" viewBox="0 0 60 40" xmlns="http://www.w3.org/2000/svg">
                            <rect width="20" height="40" fill="#0055A4"/>
                            <rect x="20" width="20" height="40" fill="#FFFFFF"/>
                            <rect x="40" width="20" height="40" fill="#EF4135"/>
                        </svg>
                        FR
                    </button>
                </div>
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
            })
            .catch(error => console.error('Error switching language:', error));
        });
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
</body>
</html>