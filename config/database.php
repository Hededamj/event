<?php
/**
 * Database Configuration
 *
 * Update these settings to match your UnoEuro MySQL database
 */

define('DB_HOST', 'mysql71.unoeuro.com');
define('DB_NAME', 'hededam_dk_db');
define('DB_USER', 'hededam_dk');
define('DB_PASS', 'Plantagevej12');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection (singleton pattern)
 */
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
