<?php
declare(strict_types=1);

// includes/mail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Try Composer autoload (preferred). Fallback to manual include.
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

/**
 * Small helper to read env/const with fallback.
 */
if (!function_exists('envv')) {
    function envv(string $key, $default = null) {
        // Prefer $_ENV set by vlucas/phpdotenv (loaded in includes/config.php).
        if (array_key_exists($key, $_ENV)) return $_ENV[$key];
        $val = getenv($key);
        if ($val !== false && $val !== null) return $val;
        if (defined($key)) return constant($key);
        return $default;
    }
}

/**
 * Decide PHPMailer encryption constant from env values and/or port.
 */
function resolveEncryption(?string $enc, int $port): ?string {
    $e = strtolower(trim((string)$enc));
    if (in_array($e, ['ssl', 'smtps'], true)) {
        return PHPMailer::ENCRYPTION_SMTPS; // implicit SSL on connect (465)
    }
    if (in_array($e, ['tls', 'starttls'], true)) {
        return PHPMailer::ENCRYPTION_STARTTLS; // explicit STARTTLS (usually 587)
    }
    // If unset or "auto", infer from port
    if ($e === '' || $e === 'auto' || $enc === null) {
        if ($port === 465) return PHPMailer::ENCRYPTION_SMTPS;
        if ($port === 587) return PHPMailer::ENCRYPTION_STARTTLS;
        // leave null => no encryption (not recommended)
        return null;
    }
    // Fallback: null (no encryption)
    return null;
}

/**
 * Send an HTML email using SMTP settings from .env
 *
 * @param string      $to       Recipient email address
 * @param string      $subject  Email subject
 * @param string      $html     HTML body
 * @param string|null $alt      Alt/plain body (defaults to stripped HTML)
 * @param array       $opts     Options:
 *   - 'from' => 'address@domain' (override MAIL_FROM)
 *   - 'from_name' => 'Display Name' (override MAIL_FROM_NAME)
 *   - 'reply_to' => 'reply@domain'
 *   - 'reply_to_name' => 'Reply Name'
 *   - 'cc' => ['a@x.com','b@y.com'] or 'a@x.com'
 *   - 'bcc' => ['a@x.com','b@y.com'] or 'a@x.com'
 *   - 'attachments' => [['path'=>'/abs/file','name'=>'file.ext'], ...]
 *   - 'smtp_debug' => 0|1|2|3|4
 *   - 'smtp_timeout' => seconds (int)
 *   - 'headers' => [['name'=>'X-Header','value'=>'...'], ...]
 * @return bool
 */
function send_mail(string $to, string $subject, string $html, ?string $alt = null, array $opts = []): bool
{
    $mail = new PHPMailer(true);

    // Read settings from environment / constants
    $host        = (string) envv('MAIL_HOST', 'smtp.hostinger.com');
    $username    = (string) envv('MAIL_USERNAME', '');
    $password    = (string) envv('MAIL_PASSWORD', '');
    $port        = (int)    envv('MAIL_PORT', 587);
    $encRaw      = envv('MAIL_ENCRYPTION', 'auto'); // 'ssl', 'tls', 'auto'
    $fromAddr    = (string) ($opts['from'] ?? envv('MAIL_FROM', $username));
    $fromName    = (string) ($opts['from_name'] ?? envv('MAIL_FROM_NAME', 'Jhema'));
    $replyTo     = (string) ($opts['reply_to'] ?? '');
    $replyName   = (string) ($opts['reply_to_name'] ?? '');
    $smtpDebug   = (int)    ($opts['smtp_debug'] ?? 0);
    $smtpTimeout = (int)    ($opts['smtp_timeout'] ?? 30);

    $encryptionConst = resolveEncryption(is_string($encRaw) ? $encRaw : null, $port);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->Port       = $port;
        $mail->Timeout    = $smtpTimeout;
        $mail->SMTPDebug  = $smtpDebug;

        // Encryption
        if ($encryptionConst === PHPMailer::ENCRYPTION_SMTPS) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // implicit SSL (465)
        } elseif ($encryptionConst === PHPMailer::ENCRYPTION_STARTTLS) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS (587)
        } else {
            // No encryption; generally not recommended. Leave $mail->SMTPSecure unset.
        }

        // From / Reply-To
        $mail->setFrom($fromAddr, $fromName ?: '');
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo, $replyName ?: '');
        }

        // To / CC / BCC
        $mail->addAddress($to);

        if (!empty($opts['cc'])) {
            $ccs = is_array($opts['cc']) ? $opts['cc'] : [$opts['cc']];
            foreach ($ccs as $cc) {
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) $mail->addCC($cc);
            }
        }
        if (!empty($opts['bcc'])) {
            $bccs = is_array($opts['bcc']) ? $opts['bcc'] : [$opts['bcc']];
            foreach ($bccs as $bcc) {
                if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) $mail->addBCC($bcc);
            }
        }

        // Attachments
        if (!empty($opts['attachments']) && is_array($opts['attachments'])) {
            foreach ($opts['attachments'] as $att) {
                if (!empty($att['path']) && is_readable($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? '');
                }
            }
        }

        // Custom headers
        if (!empty($opts['headers']) && is_array($opts['headers'])) {
            foreach ($opts['headers'] as $h) {
                if (!empty($h['name'])) $mail->addCustomHeader($h['name'], $h['value'] ?? '');
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $alt ?: trim(strip_tags($html));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('[MAIL ERROR] ' . $e->getMessage());
        return false;
    }
}
