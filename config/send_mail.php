<?php
/**
 * ============================================================
 * FILE: /config/send_mail.php
 * ============================================================
 * Reusable email helper for OralSync.
 * All endpoints require this file and call the functions below.
 * Credentials come from Azure App Settings env vars —
 * no hardcoding needed here.
 * ============================================================
 */

require_once __DIR__ . '/mailer.php';

define('APP_BASE_URL', 'https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net');

// ─── Shared HTML wrapper ──────────────────────────────────────

function mailWrap(string $content): string {
    return "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f4f8; padding: 24px; }
        .card { max-width: 520px; margin: 0 auto; background: #fff;
                border-radius: 16px; padding: 36px 32px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
        .logo-box { background: #2563eb; border-radius: 12px; padding: 8px 14px;
                    color: #fff; font-size: 16px; font-weight: 800; }
        .logo-sub { font-size: 12px; color: #94a3b8; font-weight: 500; }
        h2 { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
        p { font-size: 14px; color: #475569; line-height: 1.7; margin-bottom: 14px; }
        .btn { display: block; background: #2563eb; color: #fff !important;
               text-decoration: none; text-align: center; border-radius: 12px;
               padding: 14px 24px; font-size: 15px; font-weight: 700;
               margin: 24px 0; }
        .info-box { background: #f8fafc; border-radius: 10px; padding: 14px 16px;
                    border: 1px solid #e2e8f0; margin: 16px 0; }
        .info-row { display: flex; justify-content: space-between;
                    font-size: 13px; margin-bottom: 6px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { color: #94a3b8; }
        .info-value { color: #1e293b; font-weight: 600; }
        .note { font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;
                padding-top: 14px; margin-top: 8px; line-height: 1.6; }
        .url { word-break: break-all; font-size: 11px; color: #94a3b8; margin-top: 8px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px;
                 font-size: 12px; font-weight: 600; margin-bottom: 16px; }
        .badge-blue   { background: #eff6ff; color: #2563eb; }
        .badge-green  { background: #dcfce7; color: #16a34a; }
        .badge-red    { background: #fef2f2; color: #dc2626; }
        .badge-amber  { background: #fef3c7; color: #d97706; }
      </style>
    </head>
    <body>
      <div class='card'>
        <div class='logo'>
          <div class='logo-box'>OralSync</div>
          <span class='logo-sub'>Dental Management System</span>
        </div>
        {$content}
        <div class='note'>
          This is an automated message from OralSync. Please do not reply to this email.
        </div>
      </div>
    </body>
    </html>";
}

// ─── 1. Email Verification ────────────────────────────────────

function sendVerificationEmail(string $to, string $firstName, string $token): bool {
    $link = APP_BASE_URL . '/api/verify_email.php?token=' . urlencode($token);
    $body = mailWrap("
        <h2>Verify Your Email</h2>
        <span class='badge badge-blue'>Action Required</span>
        <p>Hi {$firstName},</p>
        <p>Welcome to OralSync! Please verify your email address to activate your account and start booking appointments.</p>
        <a href='{$link}' class='btn'>Verify My Email</a>
        <p>This link expires in <strong>24 hours</strong>. If you did not create an account, you can safely ignore this email.</p>
        <div class='note'>
          If the button doesn't work, copy and paste this link into your browser:
          <div class='url'>{$link}</div>
        </div>
    ");
    $alt = "Hi {$firstName},\n\nVerify your OralSync email:\n{$link}\n\nThis link expires in 24 hours.";
    return dispatchMail($to, $firstName, 'Verify Your OralSync Email', $body, $alt);
}

// ─── 2. Booking Confirmation ──────────────────────────────────

function sendBookingConfirmationEmail(
    string $to,
    string $firstName,
    string $date,
    string $time,
    string $dentist,
    array  $services
): bool {
    $serviceRows = '';
    foreach ($services as $s) {
        $serviceRows .= "
          <div class='info-row'>
            <span class='info-label'>{$s['service_name']}</span>
            <span class='info-value'>{$s['duration_minutes']} mins</span>
          </div>";
    }

    $body = mailWrap("
        <h2>Appointment Submitted</h2>
        <span class='badge badge-amber'>Awaiting Approval</span>
        <p>Hi {$firstName},</p>
        <p>Your appointment request has been submitted successfully. The clinic will review and confirm it shortly — you'll receive another email once approved.</p>
        <div class='info-box'>
          <div class='info-row'>
            <span class='info-label'>Date</span>
            <span class='info-value'>{$date}</span>
          </div>
          <div class='info-row'>
            <span class='info-label'>Time</span>
            <span class='info-value'>{$time}</span>
          </div>
          <div class='info-row'>
            <span class='info-label'>Dentist</span>
            <span class='info-value'>{$dentist}</span>
          </div>
          {$serviceRows}
        </div>
        <p>You can view your appointment status anytime in the OralSync app under <strong>Appointments</strong>.</p>
        <p style='font-size:12px;color:#94a3b8;'>You may cancel or reschedule up to <strong>24 hours</strong> before your appointment through the app.</p>
    ");
    $alt = "Hi {$firstName},\n\nYour appointment on {$date} at {$time} with {$dentist} has been submitted and is awaiting clinic approval.";
    return dispatchMail($to, $firstName, 'Appointment Submitted — OralSync', $body, $alt);
}

// ─── 3. Cancellation Confirmation ────────────────────────────

function sendCancellationEmail(
    string $to,
    string $firstName,
    string $date,
    string $time,
    string $dentist
): bool {
    $body = mailWrap("
        <h2>Appointment Cancelled</h2>
        <span class='badge badge-red'>Cancelled</span>
        <p>Hi {$firstName},</p>
        <p>Your appointment has been successfully cancelled. No action is needed.</p>
        <div class='info-box'>
          <div class='info-row'>
            <span class='info-label'>Date</span>
            <span class='info-value'>{$date}</span>
          </div>
          <div class='info-row'>
            <span class='info-label'>Time</span>
            <span class='info-value'>{$time}</span>
          </div>
          <div class='info-row'>
            <span class='info-label'>Dentist</span>
            <span class='info-value'>{$dentist}</span>
          </div>
        </div>
        <p>If you'd like to book a new appointment, open the OralSync app and go to <strong>Book</strong>.</p>
    ");
    $alt = "Hi {$firstName},\n\nYour appointment on {$date} at {$time} with {$dentist} has been cancelled.";
    return dispatchMail($to, $firstName, 'Appointment Cancelled — OralSync', $body, $alt);
}

// ─── 4. Reschedule Request Confirmation ──────────────────────

function sendRescheduleRequestEmail(
    string $to,
    string $firstName,
    string $oldDate,
    string $oldTime,
    string $newDate,
    string $newTime,
    string $dentist
): bool {
    $body = mailWrap("
        <h2>Reschedule Requested</h2>
        <span class='badge badge-amber'>Awaiting Approval</span>
        <p>Hi {$firstName},</p>
        <p>Your reschedule request has been submitted. The clinic will review and confirm your new schedule shortly.</p>
        <div class='info-box'>
          <div class='info-row'>
            <span class='info-label'>Previous Date</span>
            <span class='info-value'>{$oldDate} at {$oldTime}</span>
          </div>
          <div class='info-row'>
            <span class='info-label'>Requested Date</span>
            <span class='info-value'>{$newDate} at {$newTime}</span>
          </div>
          <div class='info-row'>
            <span class='info-label'>Dentist</span>
            <span class='info-value'>{$dentist}</span>
          </div>
        </div>
        <p>Your original slot has been released. If the clinic declines the new time, please book again through the app.</p>
    ");
    $alt = "Hi {$firstName},\n\nYour reschedule request from {$oldDate} {$oldTime} to {$newDate} {$newTime} has been submitted.";
    return dispatchMail($to, $firstName, 'Reschedule Requested — OralSync', $body, $alt);
}

// ─── Internal dispatcher ──────────────────────────────────────

function dispatchMail(
    string $to,
    string $name,
    string $subject,
    string $htmlBody,
    string $altBody
): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($to, $name);
        $mail->Subject  = $subject;
        $mail->isHTML(true);
        $mail->Body     = $htmlBody;
        $mail->AltBody  = $altBody;
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[OralSync Mailer] Failed to send to ' . $to . ': ' . $e->getMessage());
        return false;
    }
}