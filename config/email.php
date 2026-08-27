<?php
// config/email.php
require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer

// If using manual installation
require_once __DIR__ . '/../includes/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email configuration - UPDATE THESE VALUES
define('MAIL_HOST', 'smtp.gmail.com');              // For Gmail: smtp.gmail.com
define('MAIL_PORT', 587);                           // 587 for TLS, 465 for SSL
define('MAIL_USERNAME', 'fossofrank77@gmail.com');    // Your FULL email address
define('MAIL_PASSWORD', 'vmuzjbspseodihwx');       // App Password (NOT your regular password)
define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS); // ENCRYPTION_SMTPS for SSL
define('MAIL_FROM_EMAIL', 'fossofrank77@gmail.com');
define('MAIL_FROM_NAME', 'InternTrack');
define('MAIL_REPLY_TO', 'noreply@interntrack.com');

/**
 * Send email using PHPMailer
 */
function sendEmailPHPMailer($to, $subject, $htmlBody, $textBody = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;          // Set to SMTP::DEBUG_SERVER for testing
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        
        // Additional settings for Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(MAIL_REPLY_TO, 'No Reply');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        
        $mail->send();
        return ['success' => true, 'message' => t('Email_sent _successfully')];
    } catch (Exception $e) {
        return ['success' => false, 'message' => t('Email_could_not_be_sent._Error: ') . $mail->ErrorInfo];
    }
}

/**
 * Main send email function
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    return sendEmailPHPMailer($to, $subject, $htmlBody, $textBody);
}
?>