<?php
// includes/emailjs.php — send login OTP email through EmailJS REST API (server-side).
// Server-side so the code never reaches the browser; the private key lives in config.local.php (gitignored).
// EmailJS dashboard: Account → Security → enable "Allow EmailJS API for non-browser applications".

if (is_file(__DIR__ . '/../config.local.php')) require_once __DIR__ . '/../config.local.php';

// $templateId null = the OTP template (EMAILJS_TEMPLATE_ID); pass another id for other mails (e.g. invoice links)
function emailjs_send(array $params, ?string $templateId = null): bool {
    if (!defined('EMAILJS_PRIVATE_KEY') || EMAILJS_PRIVATE_KEY === '') {
        error_log('EmailJS: keys missing in system/config.local.php');
        return false;
    }
    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'service_id'      => EMAILJS_SERVICE_ID,
            'template_id'     => $templateId ?: EMAILJS_TEMPLATE_ID,
            'user_id'         => EMAILJS_PUBLIC_KEY,
            'accessToken'     => EMAILJS_PRIVATE_KEY,
            'template_params' => $params,
        ]),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) error_log("EmailJS send failed ($code): $body");
    return $code === 200;
}

// Template for emailed invoice links: EMAILJS_INVOICE_TEMPLATE_ID if the client defined it, else the shared template.
// Template variables: to_email, subject, message, link
function emailjs_invoice_template(): string {
    return defined('EMAILJS_INVOICE_TEMPLATE_ID') && EMAILJS_INVOICE_TEMPLATE_ID !== '' ? EMAILJS_INVOICE_TEMPLATE_ID : (defined('EMAILJS_TEMPLATE_ID') ? EMAILJS_TEMPLATE_ID : '');
}
