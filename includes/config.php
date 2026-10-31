<?php
/**
 * DEVZOOP Blog Configuration
 * Handles environment variable loading, base paths, and runtime constants.
 */

declare(strict_types=1);

// Prevent direct execution outside entry points if defined
if (!defined('DEVZOOP_ROOT')) {
    define('DEVZOOP_ROOT', dirname(__DIR__));
}

// 1. Load .env file if it exists
(function () {
    $envFile = DEVZOOP_ROOT . '/.env';
    if (!file_exists($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);

            // Strip surrounding quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
})();

// Helper to read env with default fallback
function env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val === false) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    if ($val === null || $val === false) {
        return $default;
    }

    // Convert string booleans
    $lower = strtolower((string)$val);
    if ($lower === 'true') return true;
    if ($lower === 'false') return false;
    if ($lower === 'null') return null;

    return $val;
}

// 2. Database Credentials
define('DB_HOST', (string)env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int)env('DB_PORT', 3306));
define('DB_NAME', (string)env('DB_NAME', 'devzoop_blog'));
define('DB_USER', (string)env('DB_USER', 'root'));
define('DB_PASS', (string)env('DB_PASS', ''));
define('DB_CHARSET', (string)env('DB_CHARSET', 'utf8mb4'));

// 3. Application Environment
define('APP_ENV', (string)env('APP_ENV', 'development'));
define('APP_DEBUG', (bool)env('APP_DEBUG', true));
define('APP_SECRET', (string)env('APP_SECRET', 'devzoop_super_secret_key_change_in_production_32char'));

// 4. File Storage Paths
define('STORAGE_PATH', DEVZOOP_ROOT . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('MAX_UPLOAD_SIZE', (int)env('MAX_UPLOAD_SIZE', 5 * 1024 * 1024)); // 5MB

// 5. Automatic Base URL Detection
if (!defined('BASE_URL')) {
    $configuredUrl = env('APP_URL');
    if (!empty($configuredUrl)) {
        define('BASE_URL', rtrim((string)$configuredUrl, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Derive subfolder path relative to document root
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        // Clean trailing slashes and admin/blog subfolders if in script name
        $subfolder = preg_replace('#/(admin|blog)(/.*)?$#', '', $scriptDir);
        $subfolder = rtrim($subfolder ?? '', '/\\');

        define('BASE_URL', $scheme . '://' . $host . $subfolder);
    }
}
