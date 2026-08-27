<?php
// includes/footer.php
$role = $_SESSION['user_role'] ?? '';
$user = getUserData($_SESSION['user_id'] ?? 0);
$current_year = date('Y');
?>
<footer class="footer" style="margin-left: <?php if (isLoggedIn()) { echo (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? '70px' : '260px'; } ?>; width: calc(100% - <?php if (isLoggedIn()) { echo (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? '70px' : '260px'; }?>);">
    <div class="footer-container">
        <!-- Footer Main Content -->
        <div class="footer-grid">
            <!-- Column 1: Brand & Description -->
            <div class="footer-col footer-brand">
                <div class="footer-logo">
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
                <p class="footer-description">
                    <?php echo t('app_description'); ?>
                </p>
                <!-- <div class="footer-social">
                    <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i>📘</a>
                    <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i>🐦</a>
                    <a href="#" class="social-link" title="LinkedIn"><i class="fab fa-linkedin-in"></i>💼</a>
                    <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i>▶️</a>
                </div> -->
            </div>
            
            <!-- Column 2: Quick Links -->
            <div class="footer-col">
                <h4 class="footer-title"><?php echo t('quick_links'); ?></h4>
                <ul class="footer-links">
                    <?php if ($role === 'intern'): ?>
                        <li><a href="/interntrack/intern/dashboard.php">📊 <?php echo t('dashboard'); ?></a></li>
                        <li><a href="/interntrack/intern/timelogs.php">⏱️ <?php echo t('timelog'); ?></a></li>
                        <li><a href="/interntrack/intern/goals.php">🎯 <?php echo t('goals'); ?></a></li>
                        <li><a href="/interntrack/intern/leave.php">📅 <?php echo t('leave'); ?></a></li>
                        <li><a href="/interntrack/intern/chat.php">💬 <?php echo t('chat'); ?></a></li>
                    <?php elseif ($role === 'supervisor'): ?>
                        <li><a href="/interntrack/supervisor/dashboard.php">📊 <?php echo t('dashboard'); ?></a></li>
                        <li><a href="/interntrack/supervisor/interns.php">👥 <?php echo t('assigned_interns'); ?></a></li>
                        <li><a href="/interntrack/supervisor/timelogs.php">⏱️ <?php echo t('timelog'); ?></a></li>
                        <li><a href="/interntrack/supervisor/goals.php">🎯 <?php echo t('goals'); ?></a></li>
                        <li><a href="/interntrack/supervisor/chat.php">💬 <?php echo t('chat'); ?></a></li>
                    <?php elseif ($role === 'admin'): ?>
                        <li><a href="/interntrack/admin/dashboard.php">📊 <?php echo t('dashboard'); ?></a></li>
                        <li><a href="/interntrack/admin/users.php">👥 <?php echo t('user_management'); ?></a></li>
                        <li><a href="/interntrack/admin/requests.php">📝 <?php echo t('registration_requests'); ?></a></li>
                        <li><a href="/interntrack/admin/timelogs.php">⏱️ <?php echo t('timelog'); ?></a></li>
                        <li><a href="/interntrack/admin/settings.php">⚙️ <?php echo t('system_settings'); ?></a></li>
                        <li><a href="/interntrack/admin/reports.php">📊 <?php echo t('system_reports'); ?></a></li>
                    <?php else: ?>
                        <li><a href="/interntrack/auth/login.php">🔑 <?php echo t('login'); ?></a></li>
                        <li><a href="/interntrack/auth/register.php">📝 <?php echo t('register'); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Column 3: Account Links -->
            <div class="footer-col">
                <ul class="footer-links">
                    <?php if ($role === 'admin'): ?>
                        <h4 class="footer-title"><?php echo t('account'); ?></h4>
                        <li><a href="/interntrack/admin/profile.php">👤 <?php echo t('profile'); ?></a></li>
                        <li><a href="/interntrack/notifications.php">🔔 <?php echo t('notifications'); ?></a></li>
                        <li><a href="/interntrack/auth/logout.php" class="logout-link">🚪 <?php echo t('logout'); ?></a></li>
                    <?php elseif ($role === 'supervisor'): ?>
                        <h4 class="footer-title"><?php echo t('account'); ?></h4>
                        <li><a href="/interntrack/supervisor/profile.php">👤 <?php echo t('profile'); ?></a></li>
                        <li><a href="/interntrack/notifications.php">🔔 <?php echo t('notifications'); ?></a></li>
                        <li><a href="/interntrack/auth/logout.php" class="logout-link">🚪 <?php echo t('logout'); ?></a></li>
                    <?php elseif ($role === 'intern'): ?>
                        <h4 class="footer-title"><?php echo t('account'); ?></h4>
                        <li><a href="/interntrack/intern/profile.php">👤 <?php echo t('profile'); ?></a></li>
                        <li><a href="/interntrack/notifications.php">🔔 <?php echo t('notifications'); ?></a></li>
                        <li><a href="/interntrack/auth/logout.php" class="logout-link">🚪 <?php echo t('logout'); ?></a></li>
                    <?php else: ?>
                    <?php endif; ?>
                    
                </ul>
            </div>
            
            <!-- Column 4: Contact & Info -->
            <div class="footer-col">
                <h4 class="footer-title"><?php echo t('contact_us'); ?></h4>
                <ul class="footer-contact">
                    <li>
                        <span class="contact-icon">📧</span>
                        <span><a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a></span>
                    </li>
                    <li>
                        <span class="contact-icon">📞</span>
                        <span><a href="tel:+237123456789">+237 123 456 789</a></span>
                    </li>
                    <li>
                        <span class="contact-icon">📍</span>
                        <span><?php echo t('location_address'); ?></span>
                    </li>
                    <li>
                        <span class="contact-icon">🕐</span>
                        <span><?php echo t('working_hours'); ?></span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <div class="footer-copyright">
                    &copy; <?php echo $current_year; ?> <strong><?php echo t('app_name'); ?></strong>. <?php echo t('footer_copyright'); ?>
                </div>
                <div class="footer-bottom-links">
                    <a href="/interntrack/privacy.php"><?php echo t('privacy_policy'); ?></a>
                    <span class="footer-divider">|</span>
                    <a href="/interntrack/terms.php"><?php echo t('terms_of_service'); ?></a>
                    <span class="footer-divider">|</span>
                    <a href="/interntrack/help.php"><?php echo t('help'); ?></a>
                    <span class="footer-divider">|</span>
                    <span class="footer-version"><?php echo t('version'); ?> 2.0.0</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
/* ============================================
   FOOTER STYLES
   ============================================ */
.footer {
    background: var(--primary-red);
    color: white;
    margin-top: 40px;
    padding: 0;
    border-top: 4px solid rgba(255, 255, 255, 0.1);
}

.footer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 24px 0;
}

/* Footer Grid */
.footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1.5fr;
    gap: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

/* Footer Columns */
.footer-col {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.footer-col .footer-title {
    font-size: 16px;
    font-weight: 700;
    color: white;
    margin: 0 0 8px 0;
    letter-spacing: 0.5px;
    position: relative;
    padding-bottom: 10px;
}

.footer-col .footer-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 3px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

/* Brand Column */
.footer-brand .footer-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.footer-brand .footer-logo img {
    height: 40px;
    width: 40px;
}

.footer-brand .footer-logo span {
    font-size: 24px;
    font-weight: 800;
    color: white;
    letter-spacing: -0.5px;
}

.footer-brand .footer-description {
    font-size: 14px;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.8);
    margin: 0 0 12px 0;
}

.footer-social {
    display: flex;
    gap: 10px;
}

.footer-social .social-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 16px;
}

.footer-social .social-link:hover {
    background: rgba(247, 111, 111, 0.25);
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Footer Links */
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 8px;
}

.footer-links li a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.footer-links li a:hover {
    color: white;
    transform: translateX(4px);
}

.footer-links li a.logout-link {
    color: rgba(255, 200, 200, 0.8);
}

.footer-links li a.logout-link:hover {
    color: #ff6b6b;
}

/* Footer Contact */
.footer-contact {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-contact li {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
}

.footer-contact li .contact-icon {
    width: 24px;
    text-align: center;
    font-size: 16px;
}

.footer-contact li a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: color 0.2s ease;
}

.footer-contact li a:hover {
    color: white;
    text-decoration: underline;
}

/* Footer Bottom */
.footer-bottom {
    padding: 20px 0;
}

.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.footer-copyright {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.7);
}

.footer-copyright strong {
    color: white;
}

.footer-bottom-links {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 13px;
}

.footer-bottom-links a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: color 0.2s ease;
}

.footer-bottom-links a:hover {
    color: white;
    text-decoration: underline;
}

.footer-divider {
    color: rgba(255, 255, 255, 0.2);
}

.footer-version {
    color: rgba(255, 255, 255, 0.4);
    font-size: 12px;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
    .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
}

@media (max-width: 768px) {
    .footer-container {
        padding: 30px 16px 0;
    }
    
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 24px;
        padding-bottom: 20px;
    }
    
    .footer-brand .footer-logo span {
        font-size: 20px;
    }
    
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-copyright {
        font-size: 12px;
    }
    
    .footer-bottom-links {
        justify-content: center;
        font-size: 12px;
    }
    
    .footer-social {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .footer-container {
        padding: 20px 12px 0;
    }
    
    .footer-grid {
        gap: 20px;
    }
    
    .footer-bottom-links {
        gap: 4px;
    }
    
    .footer-divider {
        display: none;
    }
    
    .footer-bottom-links a {
        padding: 4px 8px;
    }
}

/* ============================================
   DARK MODE SUPPORT
   ============================================ */
body.dark-mode .footer {
    background: linear-gradient(135deg, #7f1d1d 0%, #4a1515 100%);
}

body.dark-mode .footer-bottom {
    border-top-color: rgba(255, 255, 255, 0.05);
}

body.dark-mode .footer-social .social-link {
    background: rgba(255, 255, 255, 0.08);
}

body.dark-mode .footer-social .social-link:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* ============================================
   PRINT STYLES
   ============================================ */
@media print {
    .footer {
        display: none;
    }
}
</style>