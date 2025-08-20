<?php
// includes/mail_templates.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

if (!function_exists('base_url')) {
  // just in case this file is used standalone
  function base_url(string $path = ''): string {
    $base = defined('BASE_URL') ? BASE_URL : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
  }
}

/**
 * Return [html, alt] for the welcome email.
 */
function jhema_welcome_email(string $firstName = '', string $ctaUrl = ''): array
{
    $safeName = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $logoUrl  = base_url('public/images/logo.jpg'); // change to your actual logo name/path
    $ctaUrl   = $ctaUrl ?: base_url();

    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Welcome to Jhema</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* Basic reset + luxury-ish palette */
    body{margin:0;background:#f6f3ee;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans",sans-serif;color:#0f0f0f}
    .wrapper{padding:24px}
    .container{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e7e1d8;border-radius:16px;overflow:hidden;box-shadow:0 2px 24px rgba(0,0,0,.04)}
    .header{padding:28px 28px 12px;text-align:center}
    .brand{display:inline-flex;align-items:center;gap:12px;text-decoration:none;color:#0f0f0f}
    .brand img{height:44px;width:auto;display:block}
    .brand-name{font-weight:800;letter-spacing:.04em;text-transform:uppercase}
    .hero{padding:8px 28px 0 28px;text-align:center}
    .title{font-size:28px;line-height:1.2;margin:16px 0 4px}
    .subtitle{color:#5b5b5b;margin:0 0 20px;font-size:15px}
    .card{margin:0 28px 28px;border:1px solid #e7e1d8;border-radius:14px;padding:20px;background:#faf7f2}
    .btn{display:inline-block;background:#0f0f0f;color:#fff;text-decoration:none;padding:12px 20px;border-radius:9999px;font-weight:600}
    .btn:hover{opacity:.92}
    .divider{height:1px;background:#e7e1d8;margin:22px 28px}
    .list{margin:0;padding-left:18px;color:#333}
    .footer{color:#7a736a;font-size:12px;padding:18px 28px 28px;text-align:center}
    @media (prefers-color-scheme: dark){
      body{background:#111}
      .container{background:#161616;border-color:#2b2b2b}
      .card{background:#1e1e1e;border-color:#2b2b2b}
      .subtitle,.footer{color:#b7b7b7}
      .divider{background:#2b2b2b}
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="container">
      <div class="header">
        <a class="brand" href="{$ctaUrl}">
          <img src="https://jhema.org/logo.jpg" alt="Jhema">
          <span class="brand-name">Jhema</span>
        </a>
      </div>

      <div class="hero">
        <h1 class="title">Welcome to Jhema, {$safeName} </h1>
        <p class="subtitle">We are delighted to have you. Your new account unlocks curated looks, effortless checkout and personalized recommendations.</p>
        <p style="margin:22px 0 28px;">
          <a href="{$ctaUrl}" class="btn">Explore the Boutique</a>
        </p>
      </div>

      <div class="card">
        <p style="margin-top:0;margin-bottom:12px;"><strong>Here is what you can do next:</strong></p>
        <ul class="list">
          <li>Browse new arrivals and limited editions</li>
          <li>Save favorites to your wishlist</li>
          <li>Enjoy localized pricing and seamless checkout</li>
        </ul>
      </div>

      <div class="divider"></div>

      <div class="footer">
        You are receiving this because you created an account at Jhema.<br>
        If this was not you, please reply to this email.
      </div>
    </div>
  </div>
</body>
</html>
HTML;

    $alt = "Welcome to Jhema, {$firstName}!\n\n"
         . "Explore the boutique: {$ctaUrl}\n\n"
         . "• Browse new arrivals\n"
         . "• Save favorites\n"
         . "• Seamless checkout\n\n"
         . "If this was not you, please reply to this email.";

    return [$html, $alt];
}
