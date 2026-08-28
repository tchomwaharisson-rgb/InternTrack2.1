<?php

/**
 * Welcome email template for new users
 */
function getWelcomeEmailTemplate($userName, $email, $password = null, $role = 'intern') {
    $app_name = SITE_NAME;
    $login_url = BASE_URL . 'auth/login.php';
    
    $role_display = ucfirst($role);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Welcome to $app_name</title>
        <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                padding: 30px 40px;
                text-align: center;
                color: white;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 800;
                letter-spacing: -0.5px;
            }
            .header p {
                margin: 8px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            .content {
                padding: 40px;
            }
            .content h2 {
                color: #1f2937;
                margin-top: 0;
                font-size: 22px;
            }
            .content p {
                color: #4b5563;
                margin: 0 0 16px;
            }
            .divider {
                border: none;
                border-top: 2px solid #f3f4f6;
                margin: 24px 0;
            }
            .info-box {
                background: #fef2f2;
                border-left: 4px solid #dc2626;
                padding: 16px 20px;
                border-radius: 6px;
                margin: 16px 0;
            }
            .info-box strong {
                color: #dc2626;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                color: white;
                padding: 14px 32px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin: 16px 0;
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
            }
            .credentials {
                background: #f9fafb;
                border-radius: 8px;
                padding: 16px 20px;
                margin: 16px 0;
                border: 1px solid #e5e7eb;
            }
            .credentials code {
                display: block;
                background: white;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #e5e7eb;
                font-family: monospace;
                font-size: 14px;
                margin: 4px 0;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 40px;
                text-align: center;
                border-top: 1px solid #e5e7eb;
                font-size: 12px;
                color: #9ca3af;
            }
            .footer a {
                color: #dc2626;
                text-decoration: none;
            }
            @media (max-width: 480px) {
                .header { padding: 20px; }
                .content { padding: 24px; }
                .footer { padding: 16px 20px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$app_name</h1>
                <p>Smart Internship Management System</p>
            </div>
            <div class='content'>
                <h2>Welcome to $app_name, $userName! 👋</h2>
                <p>We're excited to have you on board! Your account has been successfully created and you can now start using the system.</p>
                
                <div class='info-box'>
                    <strong>Account Information:</strong><br>
                    <strong>Role:</strong> $role_display<br>
                    <strong>Email:</strong> $email
                </div>";

    if ($password) {
        $html .= "
                <p><strong>Your temporary password is:</strong></p>
                <div class='credentials'>
                    <code>$password</code>
                </div>
                <p style='color: #ef4444; font-weight: 500;'>
                    ⚠️ Please change your password after your first login for security reasons.
                </p>";
    }

    $html .= "
                <p>You can now log in to the system using your email and password.</p>
                
                <div style='text-align: center;'>
                    <a href='$login_url' class='button'>Login to Your Account</a>
                </div>
                
                <hr class='divider'>
                
                <p style='font-size: 14px; color: #6b7280;'>
                    <strong>Getting Started:</strong><br>
                    • Complete your profile information<br>
                    • Check your dashboard for important updates<br>
                    • Contact your supervisor or admin for assistance
                </p>
                
                <p style='font-size: 14px; color: #6b7280;'>
                    If you have any questions or need assistance, please contact your system administrator.
                </p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $app_name. All rights reserved.</p>
                <p>
                    <a href='$login_url'>Login</a> | 
                    <a href='" . BASE_URL . "'>Visit Website</a> | 
                    <a href='mailto:" . MAIL_REPLY_TO . "'>Contact Support</a>
                </p>
                <p style='margin-top: 8px; color: #d1d5db;'>
                    This email was sent to you because your account was created on $app_name.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Registration approval email template
 */
function getApprovalEmailTemplate($userName, $email, $role = 'intern') {
    $app_name = SITE_NAME;
    $login_url = BASE_URL . 'auth/login.php';
    $role_display = ucfirst($role);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Registration Approved - $app_name</title>
        <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                padding: 30px 40px;
                text-align: center;
                color: white;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 800;
            }
            .header p {
                margin: 8px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            .content {
                padding: 40px;
            }
            .content h2 {
                color: #1f2937;
                margin-top: 0;
                font-size: 22px;
            }
            .content p {
                color: #4b5563;
                margin: 0 0 16px;
            }
            .success-icon {
                text-align: center;
                font-size: 64px;
                margin: 16px 0;
            }
            .info-box {
                background: #fef2f2;
                border-left: 4px solid #16a34a;
                padding: 16px 20px;
                border-radius: 6px;
                margin: 16px 0;
            }
            .info-box strong {
                color: #16a34a;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                color: white;
                padding: 14px 32px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin: 16px 0;
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
            }
            .footer {
                background: #f9fafb;
                padding: 20px 40px;
                text-align: center;
                border-top: 1px solid #e5e7eb;
                font-size: 12px;
                color: #9ca3af;
            }
            .footer a {
                color: #dc2626;
                text-decoration: none;
            }
            @media (max-width: 480px) {
                .header { padding: 20px; }
                .content { padding: 24px; }
                .footer { padding: 16px 20px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$app_name</h1>
                <p>Smart Internship Management System</p>
            </div>
            <div class='content'>
                <div class='success-icon'>✅</div>
                <h2>Your Registration Has Been Approved! 🎉</h2>
                <p>Dear $userName,</p>
                <p>We are pleased to inform you that your registration request for <strong>$app_name</strong> has been <strong>approved</strong>!</p>
                
                <div class='info-box'>
                    <strong>Account Details:</strong><br>
                    <strong>Role:</strong> $role_display<br>
                    <strong>Email:</strong> $email<br>
                    <strong>Status:</strong> Active
                </div>
                
                <p>You can now log in to the system and start using all the features available for your role.</p>
                
                <div style='text-align: center;'>
                    <a href='$login_url' class='button'>Login Now</a>
                </div>
                
                <hr style='border: none; border-top: 2px solid #f3f4f6; margin: 24px 0;'>
                
                <p style='font-size: 14px; color: #6b7280;'>
                    <strong>What you can do next:</strong><br>
                    • Complete your profile information<br>
                    • Explore your dashboard<br>
                    • Connect with your supervisor<br>
                    • Start tracking your internship activities
                </p>
                
                <p style='font-size: 14px; color: #6b7280;'>
                    If you have any questions, please don't hesitate to contact your administrator.
                </p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $app_name. All rights reserved.</p>
                <p>
                    <a href='$login_url'>Login</a> | 
                    <a href='" . BASE_URL . "'>Visit Website</a> | 
                    <a href='mailto:" . MAIL_REPLY_TO . "'>Contact Support</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Registration rejection email template
 */
function getRejectionEmailTemplate($userName, $email, $reason = '') {
    $app_name = SITE_NAME;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Registration Update - $app_name</title>
        <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                padding: 30px 40px;
                text-align: center;
                color: white;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 800;
            }
            .content {
                padding: 40px;
            }
            .content h2 {
                color: #1f2937;
                margin-top: 0;
            }
            .content p {
                color: #4b5563;
                margin: 0 0 16px;
            }
            .reason-box {
                background: #fef2f2;
                border-left: 4px solid #dc2626;
                padding: 16px 20px;
                border-radius: 6px;
                margin: 16px 0;
            }
            .reason-box strong {
                color: #dc2626;
            }
            .button {
                display: inline-block;
                background: #dc2626;
                color: white;
                padding: 12px 28px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin: 16px 0;
            }
            .button:hover {
                background: #991b1b;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 40px;
                text-align: center;
                border-top: 1px solid #e5e7eb;
                font-size: 12px;
                color: #9ca3af;
            }
            .footer a {
                color: #dc2626;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$app_name</h1>
                <p>Smart Internship Management System</p>
            </div>
            <div class='content'>
                <h2>Registration Update</h2>
                <p>Dear $userName,</p>
                <p>We regret to inform you that your registration request for <strong>$app_name</strong> has been <strong>rejected</strong>.</p>";
    
    if ($reason) {
        $html .= "
                <div class='reason-box'>
                    <strong>Reason for rejection:</strong><br>
                    " . htmlspecialchars($reason) . "
                </div>";
    }
    
    $html .= "
                <p>If you believe this is an error or would like more information, please contact the system administrator.</p>
                
                <div style='text-align: center;'>
                    <a href='" . BASE_URL . "' class='button'>Back to Home</a>
                </div>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $app_name. All rights reserved.</p>
                <p><a href='mailto:" . MAIL_REPLY_TO . "'>Contact Support</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}
?>