<?php
// test_email.php
require_once 'config/config.php';
require_once 'config/email.php';
require_once 'includes/email_templates.php';

$test_email = 'wultoffortune@gmail.com'; // CHANGE THIS TO YOUR EMAIL

echo "<h1>Testing Email Configuration</h1>";
echo "<p>Sending test email to: <strong>$test_email</strong></p>";

$subject = "Test Email from " . SITE_NAME;
$htmlBody = "<h1>Test Email</h1><p>If you received this, your PHPMailer is working!</p>";

$result = sendEmail($test_email, $subject, $htmlBody);

if ($result['success']) {
    echo "<div style='color: green; padding: 15px; border: 1px solid green; border-radius: 5px;'>";
    echo "✅ Email sent successfully!<br>";
    echo "📧 Check your inbox at: " . $test_email;
    echo "</div>";
} else {
    echo "<div style='color: red; padding: 15px; border: 1px solid red; border-radius: 5px;'>";
    echo "❌ Email failed: " . $result['message'];
    echo "</div>";
}
?>