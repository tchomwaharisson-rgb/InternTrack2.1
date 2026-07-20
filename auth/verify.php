<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (empty($token)) {
    $error = 'Invalid verification token';
} else {
    // Verify the token
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_token = ? AND email_verified = FALSE");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $conn->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL WHERE id = ?");
        if ($stmt->execute([$user['id']])) {
            $success = 'Email verified successfully! You can now login.';
        } else {
            $error = 'Error verifying email';
        }
    } else {
        $error = 'Invalid or expired verification token';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo t('app_name'); ?></title>
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
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
            
            
            <h1 class="auth-subtitle"><?php echo t('email_verification'); ?></h1>
            
            <?php if ($error): ?>
                <div class="toast toast-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="toast toast-success"><?php echo $success; ?></div>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="/interntrack/auth/login.php" class="btn btn-primary"><?php echo t('login'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>