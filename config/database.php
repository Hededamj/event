<?php
/**
 * Database Configuration
 *
 * Update these settings to match your UnoEuro MySQL database
 */

if (!defined('DB_HOST')) define('DB_HOST', 'mysql71.unoeuro.com');
if (!defined('DB_NAME')) define('DB_NAME', 'hededam_dk_db_event');
if (!defined('DB_USER')) define('DB_USER', 'hededam_dk');
if (!defined('DB_PASS')) define('DB_PASS', 'Plantagevej12');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Base URL path - change this if site is in a subdirectory
 */
if (!defined('BASE_PATH')) define('BASE_PATH', '/sofie');

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
            // In production, log this error instead of displaying it
            die('Database connection failed. Please check your configuration.');
        }
    }

    return $pdo;
}
}
