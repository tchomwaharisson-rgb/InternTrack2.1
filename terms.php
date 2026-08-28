<?php
// terms.php
require_once 'config/config.php';
require_once 'config/language.php';

if (!isLoggedIn()) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

include_once 'includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📜 <?php echo t('terms_of_service'); ?></h3>
        </div>
        
        <div style="padding: 20px;">
            <!-- Last Updated -->
            <div style="color: var(--gray-500); font-size: 13px; margin-bottom: 20px;">
                <?php echo t('last_updated'); ?>: <?php echo date('F d, Y'); ?>
            </div>
            
            <!-- Introduction -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">📋 <?php echo t('introduction'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8;">
                    <?php echo t('terms_introduction'); ?>
                </p>
            </div>
            
            <!-- Acceptance of Terms -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">✅ <?php echo t('acceptance_of_terms'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8;">
                    <?php echo t('terms_acceptance_description'); ?>
                </p>
            </div>
            
            <!-- User Accounts -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">👤 <?php echo t('user_accounts'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8; margin-bottom: 10px;">
                    <?php echo t('terms_accounts_description'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>🔑 <?php echo t('terms_accounts_security'); ?></li>
                    <li>📧 <?php echo t('terms_accounts_accuracy'); ?></li>
                    <li>🚫 <?php echo t('terms_accounts_unauthorized'); ?></li>
                </ul>
            </div>
            
            <!-- User Responsibilities -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">⚡ <?php echo t('user_responsibilities'); ?></h4>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>✅ <?php echo t('terms_responsibilities_accurate'); ?></li>
                    <li>🔒 <?php echo t('terms_responsibilities_password'); ?></li>
                    <li>📋 <?php echo t('terms_responsibilities_compliance'); ?></li>
                    <li>🚫 <?php echo t('terms_responsibilities_abuse'); ?></li>
                </ul>
            </div>
            
            <!-- Acceptable Use -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">📋 <?php echo t('acceptable_use'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8; margin-bottom: 10px;">
                    <?php echo t('terms_acceptable_use_description'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>📊 <?php echo t('terms_acceptable_use_tracking'); ?></li>
                    <li>💬 <?php echo t('terms_acceptable_use_communication'); ?></li>
                    <li>📋 <?php echo t('terms_acceptable_use_reporting'); ?></li>
                    <li>🤝 <?php echo t('terms_acceptable_use_collaboration'); ?></li>
                </ul>
            </div>
            
            <!-- Prohibited Activities -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">🚫 <?php echo t('prohibited_activities'); ?></h4>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>🔓 <?php echo t('terms_prohibited_hacking'); ?></li>
                    <li>👤 <?php echo t('terms_prohibited_impersonation'); ?></li>
                    <li>📧 <?php echo t('terms_prohibited_harassment'); ?></li>
                    <li>🗑️ <?php echo t('terms_prohibited_data_manipulation'); ?></li>
                </ul>
            </div>
            
            <!-- Termination -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">⛔ <?php echo t('termination'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8;">
                    <?php echo t('terms_termination_description'); ?>
                </p>
            </div>
            
            <!-- Changes to Terms -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">📝 <?php echo t('changes_to_terms'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8;">
                    <?php echo t('terms_changes_description'); ?>
                </p>
            </div>
            
            <!-- Contact -->
            <div style="background: var(--red-50); padding: 20px; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">📞 <?php echo t('contact_us_terms'); ?></h4>
                <p style="color: var(--gray-600); margin-bottom: 8px;">
                    <?php echo t('terms_contact_message'); ?>
                </p>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 12px;">
                    <div>
                        <strong style="color: var(--gray-700);">📧 <?php echo t('email'); ?>:</strong>
                        <a href="mailto:<?php echo ADMIN_EMAIL; ?>" style="color: var(--primary-color); text-decoration: none;">
                            <?php echo ADMIN_EMAIL; ?>
                        </a>
                    </div>
                    <div>
                        <strong style="color: var(--gray-700);">📞 <?php echo t('phone'); ?>:</strong>
                        <span style="color: var(--gray-600);">+237 123 456 789</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- <a href="/interntrack/" class="header-brand">
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
</a> -->