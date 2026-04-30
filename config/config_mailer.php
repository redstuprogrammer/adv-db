<?php
/**
 * PHPMailer Factory - Web-compatible (PHASE 1.3 FINAL)
 * Supports multiple environment variable names for maximum compatibility on Azure.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function createMailer($to = null, $subject = '', $body = '', $altBody = '') {
    $mail = new PHPMailer(true);
    
    // Check for various possible environment variable names
    $smtpHost = getenv('SMTP_HOST') ?: getenv('SMTP_HOSTNAME') ?: null;
    
    if ($smtpHost) {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        
        // Support both SMTP_USER and SMTP_USERNAME/SMTP_PASSWORD
        $mail->Username   = getenv('SMTP_USER') ?: getenv('SMTP_USERNAME') ?: '';
        $mail->Password   = getenv('SMTP_PASS') ?: getenv('SMTP_PASSWORD') ?: '';
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        
        $fromEmail = getenv('SMTP_FROM') ?: getenv('SMTP_FROM_EMAIL') ?: 'no-reply@oralsync.com';
        $fromName  = getenv('SMTP_FROM_NAME') ?: 'OralSync';
        $mail->setFrom($fromEmail, $fromName);
    } else {
        // Fallback to local mail function if SMTP not configured
        $mail->isMail();
    }
    
    $mail->CharSet = 'UTF-8';
    
    if ($to) $mail->addAddress($to);
    if ($subject) $mail->Subject = $subject;
    if ($body) $mail->Body = $body;
    if ($altBody) $mail->AltBody = $altBody;
    
    return $mail;
}
