<?php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Load Composer autoload (optional). If installed, we use vlucas/phpdotenv.
 */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;

    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }
}

/** -------------------------------
 *  Base URL
 * ------------------------------- */
$baseUrl = getenv('BASE_URL') ?: 'http://localhost/jhema/';
$baseUrl = rtrim($baseUrl, '/') . '/';

// Make it available in three ways:
// - global variable $baseUrl
// - constant BASE_URL
// - helper function base_url()
if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $base = defined('BASE_URL') ? BASE_URL : $GLOBALS['baseUrl'] ?? '';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

/** -------------------------------
 *  Database
 * ------------------------------- */
$DB_HOST = getenv('DB_HOST') ?: 'srv2057.hstgr.io';
$DB_NAME = getenv('DB_NAME') ?: 'u848848112_jhemamain';
$DB_USER = getenv('DB_USER') ?: 'u848848112_jhemamain';
$DB_PASS = getenv('DB_PASS') ?: '@@Uyioobong155@@';


try {
    if ($DB_NAME === '') {
        throw new RuntimeException('DB_NAME is not set.');
    }
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    exit('DB connection failed: ' . $e->getMessage());
}

/** -------------------------------
 *  Mail (Hostinger SMTP)
 * ------------------------------- */
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.hostinger.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 465));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Jhema');

// *** DO NOT CLOSE PHP TAG ***
