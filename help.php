<?php
// help.php
require_once 'config/config.php';
require_once 'config/language.php';

if (!isLoggedIn()) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
$user = getUserData($_SESSION['user_id'] ?? 0);

include_once 'includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">❓ <?php echo t('help'); ?></h3>
        </div>
        
        <div style="padding: 20px;">
            <!-- Getting Started -->
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--primary-color); margin-bottom: 12px;">🚀 <?php echo t('getting_started'); ?></h4>
                <p style="color: var(--gray-600); margin-bottom: 12px;">
                    <?php echo t('help_welcome_message'); ?>
                </p>
                <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                    <li>📝 <?php echo t('help_tip_complete_profile'); ?></li>
                    <li>📊 <?php echo t('help_tip_check_dashboard'); ?></li>
                    <li>🧭 <?php echo t('help_tip_use_navigation'); ?></li>
                    <li>🔔 <?php echo t('help_tip_check_notifications'); ?></li>
                </ul>
            </div>
            
            <!-- Role-Specific Help -->
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--primary-color); margin-bottom: 12px;">👤 <?php echo t('role_specific_help'); ?></h4>
                
                <?php if ($user_role === 'intern'): ?>
                    <div style="background: var(--red-50); padding: 16px; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                        <h5 style="color: var(--gray-800); margin-bottom: 8px;">🎯 <?php echo t('intern_help_title'); ?></h5>
                        <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                            <li>⏱️ <?php echo t('intern_help_timelog'); ?></li>
                            <li>🎯 <?php echo t('intern_help_goals'); ?></li>
                            <li>📅 <?php echo t('intern_help_leave'); ?></li>
                            <li>💬 <?php echo t('intern_help_chat'); ?></li>
                            <li>📊 <?php echo t('intern_help_reports'); ?></li>
                        </ul>
                    </div>
                    
                <?php elseif ($user_role === 'supervisor'): ?>
                    <div style="background: var(--red-50); padding: 16px; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                        <h5 style="color: var(--gray-800); margin-bottom: 8px;">👔 <?php echo t('supervisor_help_title'); ?></h5>
                        <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                            <li>👥 <?php echo t('supervisor_help_interns'); ?></li>
                            <li>⏱️ <?php echo t('supervisor_help_timelogs'); ?></li>
                            <li>🎯 <?php echo t('supervisor_help_goals'); ?></li>
                            <li>💬 <?php echo t('supervisor_help_chat'); ?></li>
                            <li>✅ <?php echo t('supervisor_help_approvals'); ?></li>
                        </ul>
                    </div>
                    
                <?php elseif ($user_role === 'admin'): ?>
                    <div style="background: var(--red-50); padding: 16px; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                        <h5 style="color: var(--gray-800); margin-bottom: 8px;">⚙️ <?php echo t('admin_help_title'); ?></h5>
                        <ul style="color: var(--gray-600); padding-left: 20px; line-height: 2;">
                            <li>👥 <?php echo t('admin_help_users'); ?></li>
                            <li>📝 <?php echo t('admin_help_requests'); ?></li>
                            <li>⏱️ <?php echo t('admin_help_timelogs'); ?></li>
                            <li>📊 <?php echo t('admin_help_reports'); ?></li>
                            <li>⚙️ <?php echo t('admin_help_settings'); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- FAQ Section -->
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--primary-color); margin-bottom: 12px;">❓ <?php echo t('faq'); ?></h4>
                
                <div style="margin-bottom: 16px;">
                    <h5 style="color: var(--gray-700);">🔐 <?php echo t('faq_reset_password'); ?></h5>
                    <p style="color: var(--gray-600);"><?php echo t('faq_reset_password_answer'); ?></p>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <h5 style="color: var(--gray-700);">📧 <?php echo t('faq_change_email'); ?></h5>
                    <p style="color: var(--gray-600);"><?php echo t('faq_change_email_answer'); ?></p>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <h5 style="color: var(--gray-700);">📊 <?php echo t('faq_export_reports'); ?></h5>
                    <p style="color: var(--gray-600);"><?php echo t('faq_export_reports_answer'); ?></p>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <h5 style="color: var(--gray-700);">💬 <?php echo t('faq_chat'); ?></h5>
                    <p style="color: var(--gray-600);"><?php echo t('faq_chat_answer'); ?></p>
                </div>
            </div>
            
            <!-- Contact Support -->
            <div style="background: var(--gray-50); padding: 20px; border-radius: 8px;">
                <h4 style="color: var(--primary-color); margin-bottom: 12px;">📞 <?php echo t('contact_support'); ?></h4>
                <p style="color: var(--gray-600); margin-bottom: 8px;">
                    <?php echo t('help_contact_message'); ?>
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