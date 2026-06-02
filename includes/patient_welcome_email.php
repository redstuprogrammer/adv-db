<?php
/**
 * Sends a welcome email with a temporary password to a new patient.
 */
function sendPatientWelcomeEmail($email, $firstName, $lastName, $username, $tempPassword, $tenantName, $tenantSlug) {
    $smtpHost = getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?? $_SERVER['SMTP_HOST'] ?? null;
    $smtpPort = getenv('SMTP_PORT') ?: $_ENV['SMTP_PORT'] ?? $_SERVER['SMTP_PORT'] ?? null;
    $smtpUser = getenv('SMTP_USERNAME') ?: $_ENV['SMTP_USERNAME'] ?? $_SERVER['SMTP_USERNAME'] ?? null;
    $smtpPass = getenv('SMTP_PASSWORD') ?: $_ENV['SMTP_PASSWORD'] ?? $_SERVER['SMTP_PASSWORD'] ?? null;
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $_ENV['SMTP_FROM_EMAIL'] ?? $smtpUser;
    $fromName = 'OralSync';

    if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass) {
        error_log("SMTP settings missing. Could not send welcome email to $email");
        return false;
    }

    if (empty($email)) {
        return false;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, trim($firstName . ' ' . $lastName));

        $mail->isHTML(true);
        $mail->Subject = "Welcome to " . $tenantName . " | Your Patient Portal Account";

        $safeName = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
        $safeClinic = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
        $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safePass = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

        $mail->Body = <<<HTML
<div style="font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
    <div style="background: #0d3b66; color: white; padding: 24px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">Welcome to {$safeClinic}</h1>
        <p style="margin: 8px 0 0; opacity: 0.8;">Your patient portal account has been created</p>
    </div>
    <div style="padding: 24px; color: #334155; line-height: 1.6;">
        <p>Hello <strong>{$safeName}</strong>,</p>
        <p>We are pleased to welcome you to <strong>{$safeClinic}</strong>. You can now access our patient portal to manage your appointments and view your records.</p>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 20px 0;">
            <p style="margin: 0 0 8px;"><strong>Username:</strong> {$safeUser}</p>
            <p style="margin: 0;"><strong>Temporary Password:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">{$safePass}</code></p>
        </div>
        <p style="font-size: 14px; color: #64748b;">For your security, please change your password immediately after your first login.</p>
        <div style="text-align: center; margin-top: 32px;">
            <p>Download our mobile app or visit our website to login.</p>
        </div>
    </div>
    <div style="background: #f1f5f9; color: #94a3b8; padding: 16px; text-align: center; font-size: 12px;">
        &copy; OralSync - Advanced Dental Management System
    </div>
</div>
HTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}
