<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'joudarzakaria60@gmail.com';
    $mail->Password = 'ykllohegksfjqumd';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom('joudarzakaria60@gmail.com', 'SpiderNetOS');
    $mail->addAddress('azarjoudar@gmail.com');
    $mail->Subject = 'Test Email from SpiderNetOS';
    $mail->Body = 'This is a test email sent at ' . date('Y-m-d H:i:s');
    
    $mail->send();
    echo "✅ Email sent successfully!\n";
} catch (Exception $e) {
    echo "❌ Email failed: " . $mail->ErrorInfo . "\n";
}