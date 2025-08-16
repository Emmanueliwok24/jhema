<?php
// includes/mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoload if present, else fallback to manual includes
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

/**
 * Send an HTML email using Hostinger SMTP
 */
function send_mail(string $to, string $subject, string $html, ?string $alt = null, array $opts = []): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (465)
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($opts['reply_to']);
        }

        $mail->addAddress($to);

        if (!empty($opts['attachments']) && is_array($opts['attachments'])) {
            foreach ($opts['attachments'] as $att) {
                if (!empty($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? '');
                }
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $alt ?: strip_tags($html);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[MAIL ERROR] ' . $e->getMessage());
        return false;
    }
}
