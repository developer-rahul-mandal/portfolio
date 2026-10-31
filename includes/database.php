<?php
/**
 * DEVZOOP Blog Database Layer
 * Provides singleton PDO connection with secure defaults.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Get the shared PDO database instance.
 *
 * @throws PDOException if connection fails and debug mode is enabled
 * @return PDO
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[DEVZOOP DB ERROR] ' . $e->getMessage());

        if (APP_DEBUG) {
            throw $e;
        }

        // Production-safe error output
        http_response_code(503);
        die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Service Unavailable</title><style>body{background:#0a0a0b;color:#e8e8e8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center;"><h2>Database Connection Error</h2><p style="color:#8a8a8a;">The service is temporarily unavailable. Please verify database configuration or run install.php.</p></div></body></html>');
    }
}

/**
 * Check if the database connection can be established without throwing fatal errors.
 * Useful for installer and graceful public fallbacks.
 *
 * @return bool
 */
function db_is_available(): bool {
    try {
        $db = get_db();
        $stmt = $db->query("SHOW TABLES LIKE 'posts'");
        return (bool)$stmt->fetch();
    } catch (Throwable) {
        return false;
    }
}
