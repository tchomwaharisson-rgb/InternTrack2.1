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
define('MAIL_USERNAME', 'your-email@gmail.com');    // Your FULL email address
define('MAIL_PASSWORD', 'your-app-password');       // App Password (NOT your regular password)
define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS); // ENCRYPTION_SMTPS for SSL
define('MAIL_FROM_EMAIL', 'your-email@gmail.com');
define('MAIL_FROM_NAME', 'InternTrack System');
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
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo];
    }
}

/**
 * Send email using PHP mail() function (fallback)
 */
function sendEmailPHP($to, $subject, $htmlBody) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . MAIL_REPLY_TO . "\r\n";
    
    // For Windows (WAMP), you might need to set the SMTP settings
    ini_set('SMTP', 'smtp.gmail.com');
    ini_set('smtp_port', 587);
    ini_set('sendmail_from', MAIL_FROM_EMAIL);
    
    return mail($to, $subject, $htmlBody, $headers);
}

/**
 * Main send email function
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    // First try PHPMailer
    $result = sendEmailPHPMailer($to, $subject, $htmlBody, $textBody);
    
    // If PHPMailer fails, try PHP mail() as fallback
    if (!$result['success']) {
        $resultPHP = sendEmailPHP($to, $subject, $htmlBody);
        if ($resultPHP) {
            return ['success' => true, 'message' => 'Email sent using PHP mail()'];
        } else {
            return ['success' => false, 'message' => 'Both PHPMailer and mail() failed'];
        }
    }
    
    return $result;
}
?>