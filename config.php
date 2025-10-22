<?php
// Load env
require_once __DIR__ . '/bootstrap.php';

// Expose critical settings via constants for legacy code
define('PAYMONGO_SECRET_KEY', envv('PAYMONGO_SECRET_KEY', '')); // sk_live_xxx or sk_test_xxx
define('PAYMONGO_PUBLIC_KEY', envv('PAYMONGO_PUBLIC_KEY', ''));
define('PAYMONGO_WEBHOOK_SECRET', envv('PAYMONGO_WEBHOOK_SECRET', ''));

define('DB_HOST', envv('DB_HOST', 'localhost'));
define('DB_USER', envv('DB_USER', ''));
define('DB_PASS', envv('DB_PASS', ''));
define('DB_NAME', envv('DB_NAME', ''));

define('SMTP_HOST', envv('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_USER', envv('SMTP_USER', ''));
define('SMTP_PASS', envv('SMTP_PASS', ''));
define('SMTP_PORT', (int) envv('SMTP_PORT', 587));

define('GOOGLE_CLIENT_ID', envv('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', envv('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', envv('GOOGLE_REDIRECT_URI', ''));

define('GEMINI_API_KEY', envv('GEMINI_API_KEY', ''));
?>
