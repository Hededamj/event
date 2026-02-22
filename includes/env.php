<?php
/**
 * Environment Variable Loader
 * Loads variables from .env file if it exists
 */

/**
 * Load environment variables from .env file
 */
function loadEnv(?string $path = null): void {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $path = $path ?? dirname(__DIR__) . '/.env';

    if (!file_exists($path)) {
        return; // No .env file - use defaults or server environment
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=value
        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Only set if not already defined in environment
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    $loaded = true;
}

/**
 * Get environment variable with optional default
 */
function env(string $key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }

    if ($value === null) {
        return $default;
    }

    // Convert string booleans
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }

    return $value;
}

// Auto-load on include
loadEnv();
