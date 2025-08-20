<?php
// newsletter/subscribe.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php'; // must not echo anything

header_remove('X-Powered-By');
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok'=>false,'msg'=>'Method not allowed'], 405);
  }

  // Optional CSRF
  if (function_exists('csrf_check') && !csrf_check($_POST['csrf_token'] ?? '')) {
    respond(['ok'=>false,'msg'=>'Invalid session. Please refresh.'], 400);
  }

  $email = trim((string)($_POST['email'] ?? ''));
  $name  = trim((string)($_POST['name'] ?? ''));

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['ok'=>false,'msg'=>'Please enter a valid email address.'], 422);
  }

  // Create token for unsubscribe
  $token = md5(bin2hex(random_bytes(16)));

  $st = $pdo->prepare("
    INSERT INTO newsletter_subscribers (email, name, status, unsub_token)
    VALUES (:email, :name, 'active', :tok)
    ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      status = 'active',
      unsub_token = :tok2,
      unsubscribed_at = NULL
  ");
  $st->execute([
    ':email' => $email,
    ':name'  => ($name !== '' ? $name : null),
    ':tok'   => $token,
    ':tok2'  => $token,
  ]);

  // Build unsubscribe link
  $unsubUrl = rtrim(BASE_URL, '/') . "/newsletter/unsubscribe.php?token=" . urlencode($token);

  // Send welcome email
  $subject = "Welcome to our Newsletter!";
  $greet   = $name !== '' ? "Hello " . htmlspecialchars($name) . "," : "Hello,";
  $body    = '
    <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222;">
      <p>'.$greet.'</p>
      <p>Thanks for subscribing. We\'ll keep you posted with updates and offers.</p>
      <p>You can unsubscribe anytime using this link:</p>
      <p><a href="'.$unsubUrl.'" style="color:#c19a6b;">Unsubscribe</a></p>
    </div>';

  @send_mail($email, $subject, $body, strip_tags($body), [
    'headers' => [['name' => 'List-Unsubscribe', 'value' => '<'.$unsubUrl.'>']]
  ]);

  respond(['ok'=>true,'msg'=>'Subscribed! Check your inbox for updates.']);

} catch (Throwable $e) {
  error_log('[NEWSLETTER SUBSCRIBE] ' . $e->getMessage());
  respond(['ok'=>false,'msg'=>'Could not subscribe right now. Try again later.'], 500);
}
