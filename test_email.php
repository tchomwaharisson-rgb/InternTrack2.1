<?php
// test_email.php - Run this to test email configuration
require_once 'config/config.php';
require_once 'config/email.php';
require_once 'includes/email_templates.php';

$test_email = 'fossofrank77@gmail.com'; // Change to your email
$userName = 'Fosso';
$password = 'Admin123!';
$role = 'intern';

$subject = "Test Email from " . SITE_NAME;
$htmlBody = getWelcomeEmailTemplate($userName, $test_email, $password, $role);

$result = sendEmail($test_email, $subject, $htmlBody);

if ($result['success']) {
    echo "✅ Email sent successfully!<br>";
    echo "📧 Check your inbox at: " . $test_email;
} else {
    echo "❌ Email failed: " . $result['message'];
}
?>