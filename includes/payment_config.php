<?php
// includes/payment_config.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
if (!defined('FLW_PUBLIC_KEY'))     define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-601eac30c26254f970f848607b81caf9-X');
if (!defined('FLW_SECRET_KEY'))     define('FLW_SECRET_KEY', 'FLWSECK_TEST-209f14da8f504891553f8b921fffc4dc-X');
if (!defined('FLW_ENCRYPTION_KEY')) define('FLW_ENCRYPTION_KEY', 'FLWSECK_TEST1879db53429f');
if (!defined('FLW_CURRENCY'))       define('FLW_CURRENCY', 'NGN');
if (!defined('FLW_CALLBACK_URL'))   define('FLW_CALLBACK_URL', BASE_URL . 'payment-callback.php');
