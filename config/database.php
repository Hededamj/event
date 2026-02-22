<?php
/**
 * Database Configuration
 *
 * Credentials can be set via:
 * 1. Environment variables (recommended for production)
 * 2. .env file in project root
 * 3. Default values below (for development only)
 */

// Load environment variables
require_once __DIR__ . '/../includes/env.php';

// Database credentials - prefer environment variables
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', ''));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', ''));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Base URL path - change this if site is in a subdirectory
 */
if (!defined('BASE_PATH')) define('BASE_PATH', env('BASE_PATH', ''));

/**
 * Get full application URL
 */
if (!defined('APP_URL')) define('APP_URL', env('APP_URL', ''));

/**
 * Get base URL for links (with protocol and domain)
 */
function getBaseUrl(): string {
    $appUrl = APP_URL;
    if (!empty($appUrl)) {
        return rtrim($appUrl, '/') . BASE_PATH;
    }

    // Auto-detect from request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . BASE_PATH;
}

/**
 * Get PDO database connection (singleton pattern)
 */
if (!function_exists('getDB')) {
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                1002 => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            // In production, log this error instead of displaying it
            die('Database connection failed. Please check your configuration.');
        }
    }

    return $pdo;
}
}
