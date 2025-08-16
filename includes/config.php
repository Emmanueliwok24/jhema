<?php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

// Strict error reporting for dev
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session early and only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Paths & URLs (guarded to avoid "already defined" warnings)
 * BASE_PATH = project root (parent of /includes)
 * BASE_URL  = your local URL base
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__)); // e.g., C:\laragon\www\jhema
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/jhema/');
}

// Database creds
$DB_HOST = 'srv2057.hstgr.io';
$DB_NAME = 'u848848112_jhemamain';
$DB_USER = 'u848848112_jhemamain';
$DB_PASS = '@@Uyioobong155@@';

// PDO (create only once if not already made)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        exit('DB connection failed: ' . $e->getMessage());
    }
}

// *** DO NOT CLOSE PHP TAG ***