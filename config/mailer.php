<?php
/**
 * PHPMailer Factory - Web-compatible
 */
require_once __DIR__ . '/../vendor/autoload.php'; // Assumes composer PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function createMailer($to = null, $subject = '', $body = '', $altBody = '') {
    $mail = new PHPMailer(true);
    
    // SMTP from env (matches web)
    $smtpHost = getenv('SMTP_HOST') ?: null;
    if ($smtpHost) {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER')   ?: '';
        $mail->Password   = getenv('SMTP_PASS')   ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->setFrom(getenv('SMTP_FROM') ?: 'no-reply@oralsync.com', 'OralSync');
    } else {
        $mail->isMail(); // Fallback
    }
    
    $mail->CharSet = 'UTF-8';
    
    if ($to) $mail->addAddress($to);
    if ($subject) $mail->Subject = $subject;
    if ($body) $mail->Body = $body;
    if ($altBody) $mail->AltBody = $altBody;
    
    return $mail;
}
?>
