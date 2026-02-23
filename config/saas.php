<?php
/**
 * SaaS Platform Configuration
 * Used by admin-platform and partners
 */

// Load environment variables
require_once __DIR__ . '/../includes/env.php';

// Database settings - use env() for credentials (same as config/database.php)
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', ''));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', ''));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Base path for SaaS platform (partyparart.dk)
if (!defined('BASE_PATH')) define('BASE_PATH', '');

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
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                die('Database connection failed. Please check your configuration.');
            }
        }

        return $pdo;
    }
}
