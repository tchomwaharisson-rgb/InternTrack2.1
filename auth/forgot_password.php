<?php
// auth/forgot_password.php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /interntrack/dashboard.php');
    exit;
}

$message = '';
$message_type = '';
$success = false;
$step = isset($_GET['step']) ? $_GET['step'] : 'request'; // request | reset

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'send_reset_link') {
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = t('invalid_email_format');
            $message_type = 'error';
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate reset token
                $token = generateToken(64);
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save token to database
                $stmt = $conn->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
                ");
                $stmt->execute([$user['id'], $token, $expires, $token, $expires]);
                
                // Send reset email
                $reset_link = BASE_URL . 'auth/forgot_password.php?step=reset&token=' . $token;
                $subject = t('reset_password_email_subject');
                $message_body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                            .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; }
                            .button:hover { background: #b91c1c; }
                            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>" . t('app_name') . "</h2>
                                <p>" . t('reset_password_email_header') . "</p>
                            </div>
                            <div class='content'>
                                <p>" . t('reset_password_email_greeting') . " " . htmlspecialchars($user['first_name']) . ",</p>
                                <p>" . t('reset_password_email_body') . "</p>
                                <p style='text-align: center; margin: 30px 0;'>
                                    <a href='" . $reset_link . "' class='button'>" . t('reset_password') . "</a>
                                </p>
                                <p>" . t('reset_password_email_expiry') . "</p>
                                <p>" . t('reset_password_email_ignore') . "</p>
                                <p style='margin-top: 20px;'>" . t('reset_password_email_regards') . "<br>" . t('app_name') . " " . t('team') . "</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " " . t('app_name') . ". " . t('all_rights_reserved') . "</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                if (sendEmail($email, $subject, $message_body)) {
                    $message = t('reset_link_sent');
                    $message_type = 'success';
                    $success = true;
                    logAudit($user['id'], 'password_reset_request', 'Requested password reset');
                } else {
                    $message = t('email_send_error');
                    $message_type = 'error';
                }
            } else {
                // Don't reveal if email exists or not for security
                $message = t('reset_link_sent');
                $message_type = 'success';
                $success = true;
            }
        }
    } elseif ($action === 'reset_password') {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($token)) {
            $message = t('invalid_token');
            $message_type = 'error';
        } elseif (strlen($password) < 8) {
            $message = t('password_too_short');
            $message_type = 'error';
        } elseif ($password !== $confirm_password) {
            $message = t('password_mismatch');
            $message_type = 'error';
        } else {
            // Verify token
            $stmt = $conn->prepare("
                SELECT user_id, expires_at FROM password_resets 
                WHERE token = ? AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reset) {
                // Update password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$password_hash, $reset['user_id']])) {
                    // Delete used token
                    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->execute([$token]);
                    
                    $message = t('password_reset_success');
                    $message_type = 'success';
                    $success = true;
                    logAudit($reset['user_id'], 'password_reset', 'Reset password via forgot password');
                } else {
                    $message = t('error_occurred');
                    $message_type = 'error';
                }
            } else {
                $message = t('invalid_or_expired_token');
                $message_type = 'error';
            }
        }
    }
}

// If we're on the reset step, verify the token
$token_valid = false;
$token_error = '';
if ($step === 'reset' && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $conn->prepare("
        SELECT user_id, expires_at FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset) {
        $token_valid = true;
    } else {
        $token_error = t('invalid_or_expired_token');
    }
} elseif ($step === 'reset') {
    $token_error = t('token_required');
}

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
    <link rel="stylesheet" href="/interntrack/assets/css/dark-mode.css" disabled>
    <link rel="icon" href="/interntrack/assets/images/logo-icon.png">
    <style>
        /* Auth Pages - Red Theme (Matching Login Page) */
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--red-gradient-light);
            padding: 20px;
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

        .auth-form .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 15px;
            transition: border-color var(--transition-speed);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
            background: var(--red-gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
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

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: var(--red-100);
            color: var(--red-900);
            border: 1px solid var(--red-200);
        }

        /* Password Toggle */
        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: var(--gray-400);
            padding: 4px;
            transition: all var(--transition-speed);
            line-height: 1;
        }

        .password-toggle .toggle-btn:hover {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle .toggle-btn:focus {
            outline: none;
        }

        .password-toggle .form-control {
            padding-right: 44px;
        }

        .password-requirements {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 4px;
            line-height: 1.6;
        }

        .password-requirements .valid {
            color: #16a34a;
        }

        .password-requirements .invalid {
            color: #dc2626;
        }

        .language-switcher {
            display: flex;
            gap: 4px;
            justify-content: center;
            padding: 0 40px 20px;
        }

        .language-switcher button {
            background: none;
            border: 1px solid var(--gray-300);
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition-speed);
            font-weight: 500;
        }

        .language-switcher button.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .language-switcher button:hover:not(.active) {
            background: var(--gray-100);
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

        body.dark-mode .form-control {
            background-color: #1a1a1a;
            border-color: #4a1a1a;
            color: #f3f4f6;
        }

        body.dark-mode .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
        }

        body.dark-mode .auth-footer {
            border-top-color: #4a1a1a;
        }

        body.dark-mode .auth-footer a {
            color: #dc2626;
        }

        body.dark-mode .form-group label {
            color: #e5e7eb;
        }

        body.dark-mode .alert-success {
            background: #0d2d0d;
            color: #86efac;
            border-color: #1a4a1a;
        }

        body.dark-mode .alert-danger {
            background: #4a1515;
            color: #fca5a5;
            border-color: #7f1d1d;
        }

        body.dark-mode .password-toggle .toggle-btn {
            color: #9ca3af;
        }

        body.dark-mode .password-toggle .toggle-btn:hover {
            color: #dc2626;
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

        body.dark-mode .btn-secondary {
            background: #4a4a4a;
            color: #f3f4f6;
        }

        body.dark-mode .btn-secondary:hover {
            background: #5a5a5a;
        }

        body.dark-mode .auth-subtitle {
            color: #9ca3af;
        }

        body.dark-mode .password-requirements {
            color: #9ca3af;
        }

        /* Success page styling */
        .success-page {
            text-align: center;
            padding: 20px 0;
        }

        .success-page .success-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .success-page h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: var(--gray-800);
        }

        .success-page p {
            color: var(--gray-500);
            margin-top: 8px;
        }

        body.dark-mode .success-page h3 {
            color: #f3f4f6;
        }

        body.dark-mode .success-page p {
            color: #9ca3af;
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
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($token_error): ?>
                    <div class="alert alert-danger">
                        <?php echo $token_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success && $step === 'request'): ?>
                    <!-- Success message after sending reset link -->
                    <div class="success-page">
                        <div class="success-icon">📧</div>
                        <h3><?php echo t('check_your_email'); ?></h3>
                        <p><?php echo t('reset_link_sent_message'); ?></p>
                        <p style="color: var(--gray-400); font-size: 14px; margin-top: 8px;">
                            <?php echo t('check_spam_folder'); ?>
                        </p>
                        <div style="margin-top: 24px;">
                            <a href="/interntrack/auth/login.php" class="btn btn-primary"><?php echo t('back_to_login'); ?></a>
                        </div>
                    </div>
                <?php elseif ($success && $step === 'reset'): ?>
                    <!-- Success message after password reset -->
                    <div class="success-page">
                        <div class="success-icon">✅</div>
                        <h3><?php echo t('password_reset_success_title'); ?></h3>
                        <p><?php echo t('password_reset_success_message'); ?></p>
                        <div style="margin-top: 24px;">
                            <a href="/interntrack/auth/login.php" class="btn btn-primary"><?php echo t('login_now'); ?></a>
                        </div>
                    </div>
                <?php elseif ($step === 'reset' && $token_valid): ?>
                    <!-- Reset Password Form -->
                    <h2><?php echo t('reset_password'); ?></h2>
                    <p class="auth-subtitle"><?php echo t('reset_password_subtitle'); ?></p>
                    
                    <form method="POST" action="" id="resetForm" class="auth-form">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                        
                        <div class="form-group password-toggle">
                            <label for="password"><?php echo t('new_password'); ?></label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="8" 
                                   onkeyup="validatePassword(this.value)">
                            <button type="button" class="toggle-btn" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                                👁️
                            </button>
                            <div class="password-requirements" id="passwordRequirements">
                                <div id="reqLength" class="invalid">❌ <?php echo t('min_8_characters'); ?></div>
                                <div id="reqUppercase" class="invalid">❌ <?php echo t('at_least_one_uppercase'); ?></div>
                                <div id="reqLowercase" class="invalid">❌ <?php echo t('at_least_one_lowercase'); ?></div>
                                <div id="reqNumber" class="invalid">❌ <?php echo t('at_least_one_number'); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-group password-toggle">
                            <label for="confirm_password"><?php echo t('confirm_new_password'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">
                                👁️
                            </button>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block"><?php echo t('reset_password'); ?></button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 16px;">
                        <a href="/interntrack/auth/login.php" class="btn btn-secondary" style="width: 100%;"><?php echo t('back_to_login'); ?></a>
                    </div>
                    
                <?php elseif ($step === 'reset'): ?>
                    <!-- Invalid or expired token -->
                    <div class="success-page">
                        <div class="success-icon">🔒</div>
                        <h3><?php echo t('invalid_reset_link'); ?></h3>
                        <p><?php echo t('invalid_reset_link_message'); ?></p>
                        <div style="margin-top: 24px;">
                            <a href="/interntrack/auth/forgot_password.php" class="btn btn-primary"><?php echo t('request_new_reset_link'); ?></a>
                        </div>
                        <div style="margin-top: 12px;">
                            <a href="/interntrack/auth/login.php" class="btn btn-secondary"><?php echo t('back_to_login'); ?></a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Request Reset Form -->
                    <h2><?php echo t('forgot_password_title'); ?></h2>
                    <p class="auth-subtitle"><?php echo t('forgot_password_subtitle'); ?></p>
                    
                    <form method="POST" action="" class="auth-form">
                        <input type="hidden" name="action" value="send_reset_link">
                        <div class="form-group">
                            <label for="email"><?php echo t('email'); ?></label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   placeholder="<?php echo t('enter_your_email'); ?>" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><?php echo t('send_reset_link'); ?></button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 16px;">
                        <a href="/interntrack/auth/login.php"><?php echo t('back_to_login'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="auth-footer">
                <p><?php echo t('remember_password'); ?> <a href="/interntrack/auth/login.php"><?php echo t('login'); ?></a></p>
                <p style="margin-top: 4px;"><?php echo t('dont_have_account'); ?> <a href="/interntrack/auth/register.php"><?php echo t('register'); ?></a></p>
            </div>
            
            <div class="language-switcher">
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" data-lang="en">🇬🇧 EN</button>
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>" data-lang="fr">🇫🇷 FR</button>
            </div>
        </div>
    </div>

    <script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
        field.setAttribute('type', type);
        
        const btn = field.parentElement.querySelector('.toggle-btn');
        btn.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
    }

    // Password validation
    function validatePassword(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password)
        };
        
        document.getElementById('reqLength').className = requirements.length ? 'valid' : 'invalid';
        document.getElementById('reqLength').textContent = (requirements.length ? '✅' : '❌') + ' <?php echo t('min_8_characters'); ?>';
        
        document.getElementById('reqUppercase').className = requirements.uppercase ? 'valid' : 'invalid';
        document.getElementById('reqUppercase').textContent = (requirements.uppercase ? '✅' : '❌') + ' <?php echo t('at_least_one_uppercase'); ?>';
        
        document.getElementById('reqLowercase').className = requirements.lowercase ? 'valid' : 'invalid';
        document.getElementById('reqLowercase').textContent = (requirements.lowercase ? '✅' : '❌') + ' <?php echo t('at_least_one_lowercase'); ?>';
        
        document.getElementById('reqNumber').className = requirements.number ? 'valid' : 'invalid';
        document.getElementById('reqNumber').textContent = (requirements.number ? '✅' : '❌') + ' <?php echo t('at_least_one_number'); ?>';
    }

    // Password confirmation validation
    document.getElementById('confirm_password')?.addEventListener('input', function() {
        const password = document.getElementById('password');
        if (this.value !== password.value) {
            this.setCustomValidity('<?php echo t('password_mismatch'); ?>');
        } else {
            this.setCustomValidity('');
        }
    });

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

    // Form validation
    document.getElementById('resetForm')?.addEventListener('submit', function(e) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (password.value.length < 8) {
            e.preventDefault();
            alert('<?php echo t('password_too_short'); ?>');
            return false;
        }
        
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('<?php echo t('password_mismatch'); ?>');
            return false;
        }
    });

    // Initialize dark mode class on body
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