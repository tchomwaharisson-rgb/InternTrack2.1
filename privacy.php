<?php
// privacy.php
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
            <h3 class="card-title">🔒 <?php echo t('privacy_policy'); ?></h3>
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
                    <?php echo t('privacy_introduction'); ?>
                </p>
            </div>
            
            <!-- Information We Collect -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">📊 <?php echo t('information_we_collect'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8; margin-bottom: 10px;">
                    <?php echo t('privacy_collect_description'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>👤 <?php echo t('privacy_collect_personal_info'); ?></li>
                    <li>📧 <?php echo t('privacy_collect_email'); ?></li>
                    <li>🏫 <?php echo t('privacy_collect_academic'); ?></li>
                    <li>⏱️ <?php echo t('privacy_collect_timelogs'); ?></li>
                    <li>💬 <?php echo t('privacy_collect_messages'); ?></li>
                </ul>
            </div>
            
            <!-- How We Use Your Information -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">⚡ <?php echo t('how_we_use_info'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8; margin-bottom: 10px;">
                    <?php echo t('privacy_use_description'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>📊 <?php echo t('privacy_use_management'); ?></li>
                    <li>📈 <?php echo t('privacy_use_tracking'); ?></li>
                    <li>💬 <?php echo t('privacy_use_communication'); ?></li>
                    <li>📋 <?php echo t('privacy_use_reports'); ?></li>
                </ul>
            </div>
            
            <!-- Data Protection -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">🔐 <?php echo t('data_protection'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8;">
                    <?php echo t('privacy_protection_description'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2; margin-top: 10px;">
                    <li>🔒 <?php echo t('privacy_protection_encryption'); ?></li>
                    <li>🛡️ <?php echo t('privacy_protection_access_control'); ?></li>
                    <li>📅 <?php echo t('privacy_protection_backup'); ?></li>
                    <li>👁️ <?php echo t('privacy_protection_audit'); ?></li>
                </ul>
            </div>
            
            <!-- Data Sharing -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">🤝 <?php echo t('data_sharing'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8;">
                    <?php echo t('privacy_sharing_description'); ?>
                </p>
            </div>
            
            <!-- Your Rights -->
            <div style="margin-bottom: 25px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">⚖️ <?php echo t('your_rights'); ?></h4>
                <p style="color: var(--gray-600); line-height: 1.8; margin-bottom: 10px;">
                    <?php echo t('privacy_rights_description'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>👁️ <?php echo t('privacy_rights_access'); ?></li>
                    <li>✏️ <?php echo t('privacy_rights_correct'); ?></li>
                    <li>🗑️ <?php echo t('privacy_rights_delete'); ?></li>
                    <li>📤 <?php echo t('privacy_rights_export'); ?></li>
                </ul>
            </div>
            
            <!-- Contact -->
            <div style="background: var(--red-50); padding: 20px; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">📞 <?php echo t('contact_us_privacy'); ?></h4>
                <p style="color: var(--gray-600); margin-bottom: 8px;">
                    <?php echo t('privacy_contact_message'); ?>
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