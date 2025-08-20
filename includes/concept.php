<?php
declare(strict_types=1);
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

/**
 * Global error visibility (dev-friendly; turn off display_errors in production)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== Centralized error logging bootstrap =====
(function () {
    // Toggle display in dev vs prod via APP_ENV
    $env = getenv('APP_ENV') ?: 'prod';
    $displayErrors = in_array(strtolower($env), ['dev','local','development'], true) ? '1' : '0';

    // Prefer project root /error.log (config.php lives in /includes)
    $rootDir = dirname(__DIR__);
    $logFile = $rootDir . DIRECTORY_SEPARATOR . 'error.log';

    // Ensure we can write; otherwise fall back to system temp
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    if (!file_exists($logFile)) {
        @touch($logFile);
        @chmod($logFile, 0664);
    }
    if (!is_writable($logFile)) {
        $logFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jhema_error.log';
        if (!file_exists($logFile)) {
            @touch($logFile);
            @chmod($logFile, 0666);
        }
    }

    // PHP ini flags
    ini_set('display_errors', $displayErrors);  // show in dev only
    ini_set('log_errors', '1');
    ini_set('error_log', $logFile);
    ini_set('ignore_repeated_errors', '1');
    ini_set('ignore_repeated_source', '1');

    // Simple structured logger
    if (!function_exists('app_log')) {
        function app_log(string $level, string $message, array $context = []): void {
            $ctx = [
                'time'   => date('c'),
                'level'  => $level,
                'uri'    => $_SERVER['REQUEST_URI'] ?? '',
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'uid'    => $_SESSION['user_id'] ?? null,
            ] + $context;

            // Flatten to one line JSON for easy tailing
            @error_log(json_encode($ctx, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        }
    }

    // Error handler: convert all PHP errors to logged warnings (and keep normal behavior)
    set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0) {
        // Respect @-operator
        if (!(error_reporting() & $errno)) { return false; }
        app_log('php_error', $errstr, [
            'errno' => $errno,
            'file'  => $errfile,
            'line'  => $errline,
        ]);
        // Let PHP proceed (so fatals etc. still behave)
        return false;
    });

    // Exception handler
    set_exception_handler(function (Throwable $e) {
        app_log('exception', $e->getMessage(), [
            'type'  => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        if (ini_get('display_errors') === '1') {
            // Developer-friendly output
            echo '<pre style="white-space:pre-wrap;">' .
                 htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') .
                 '</pre>';
        } else {
            http_response_code(500);
            echo 'An unexpected error occurred. Please try again later.';
        }
        exit;
    });

    // Fatal error catcher (shutdown)
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            app_log('fatal', $err['message'], [
                'type' => $err['type'],
                'file' => $err['file'] ?? '',
                'line' => $err['line'] ?? 0,
            ]);
            if (ini_get('display_errors') !== '1') {
                http_response_code(500);
                echo 'A fatal error occurred. Please try again later.';
            }
        }
    });
})();

/**
 * Composer autoload / dotenv (optional)
 */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;

    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }
}

/* ---------------------------------------
 * BASE URL
 * ------------------------------------- */
$baseUrl = getenv('BASE_URL') ?: 'http://localhost/jhema/';
$baseUrl = rtrim($baseUrl, '/') . '/';

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $base = defined('BASE_URL') ? BASE_URL : ($GLOBALS['baseUrl'] ?? '');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

/* ---------------------------------------
 * DATABASE (MySQL)
 * ------------------------------------- */
$DB_HOST = getenv('DB_HOST') ?: 'srv2057.hstgr.io';
$DB_NAME = getenv('DB_NAME') ?: 'u848848112_jhemamain';
$DB_USER = getenv('DB_USER') ?: 'u848848112_jhemamain';
$DB_PASS = getenv('DB_PASS') ?: '@@Uyioobong155@@'; // move to .env in production

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

/* ---------------------------------------
 * PRODUCT MEDIA: paths & helpers
 * ------------------------------------- */
/**
 * If your web server's document root is the project root (so assets are served as /public/…),
 * keep the default below: 'public/images/products'.
 *
 * If your web server's document root is the /public folder itself (common in production),
 * change to: 'images/products'
 */
if (!defined('PRODUCT_UPLOAD_BASE_REL')) {
    define('PRODUCT_UPLOAD_BASE_REL', getenv('PRODUCT_UPLOAD_BASE_REL') ?: 'public/images/products');
}

if (!defined('PRODUCT_UPLOAD_DIR')) {
    // __DIR__ points to /includes. Build absolute filesystem path for uploads.
    $abs = __DIR__ . '/../' . PRODUCT_UPLOAD_BASE_REL;
    if (!is_dir($abs)) { @mkdir($abs, 0775, true); }
    define('PRODUCT_UPLOAD_DIR', realpath($abs) ?: $abs);
}

/**
 * Normalize legacy DB paths into PRODUCT_UPLOAD_BASE_REL/…
 * Handles:
 *  - "uploads/xxx.jpg"                    (very old)
 *  - "admin/products/uploads/xxx.jpg"     (old)
 *  - "admin/product/uploads/xxx.jpg"      (previous)
 *  - "images/products/xxx.jpg"            (older app return value)
 *  - already-canonical "public/images/products/xxx.jpg"
 *  - absolute http(s) URLs (returned as-is)
 */
if (!function_exists('normalize_upload_path')) {
    function normalize_upload_path(?string $path): ?string {
        if (!$path) return null;

        $p = str_replace('\\', '/', $path);

        // Absolute URL? Return as-is.
        if (preg_match('~^https?://~i', $p)) return $p;

        // Remove leading slashes to keep relative
        $p = ltrim($p, '/');

        // Already canonical?
        $canonical = PRODUCT_UPLOAD_BASE_REL . '/';
        if (stripos($p, $canonical) === 0) {
            return $p;
        }

        // Known legacy roots
        $legacyRoots = [
            'admin/products/uploads/',
            'admin/product/uploads/',
            'uploads/',
            'images/products/', // ← added to prevent double-prefixing on older rows
        ];

        foreach ($legacyRoots as $legacy) {
            if (stripos($p, $legacy) === 0) {
                $suffix = substr($p, strlen($legacy));
                return $canonical . $suffix;
            }
        }

        // Unknown relative path: tuck under canonical
        return $canonical . $p;
    }
}

/**
 * Build an absolute URL for a product image.
 * - Absolute URLs pass through.
 * - Relative/legacy paths are normalized and joined with BASE_URL.
 */
if (!function_exists('product_image_url')) {
    function product_image_url(?string $path): ?string {
        if (!$path) return null;
        if (preg_match('~^https?://~i', $path)) return $path;

        $norm = normalize_upload_path($path);
        if (!$norm) return null;

        return rtrim(BASE_URL, '/') . '/' . ltrim($norm, '/');
    }
}

/**
 * Convert a (possibly legacy) DB path to an absolute filesystem path.
 * Returns null if not resolvable.
 */
if (!function_exists('product_image_abs_path')) {
    function product_image_abs_path(?string $path): ?string {
        $norm = normalize_upload_path($path);
        if (!$norm) return null;

        $prefix = PRODUCT_UPLOAD_BASE_REL . '/';
        if (stripos($norm, $prefix) === 0) {
            $sub = substr($norm, strlen($prefix));
        } else {
            // Fallback: treat as relative under canonical dir
            $sub = ltrim($norm, '/');
        }

        return rtrim(PRODUCT_UPLOAD_DIR, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $sub);
    }
}

