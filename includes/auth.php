<?php
/**
 * DEVZOOP Admin Authentication & Session Management
 * Secure session cookies, password verification, rate limiting, and access guards.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Initialize session with hardened security settings.
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        session_set_cookie_params([
            'lifetime' => 0,              // Session cookie until browser close
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,           // Prevent JavaScript access to session cookie
            'samesite' => 'Lax'           // Protect against CSRF
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

/**
 * Check if current session belongs to an authenticated administrator.
 *
 * @return bool
 */
function is_admin_logged_in(): bool {
    init_session();
    return !empty($_SESSION['admin_id']) && is_int($_SESSION['admin_id']);
}

/**
 * Get data for currently logged-in admin.
 *
 * @return array<string, mixed>|null
 */
function current_admin(): ?array {
    if (!is_admin_logged_in()) {
        return null;
    }

    return [
        'id'       => (int)$_SESSION['admin_id'],
        'username' => (string)($_SESSION['admin_username'] ?? ''),
        'email'    => (string)($_SESSION['admin_email'] ?? '')
    ];
}

/**
 * Enforce admin authentication on protected pages.
 * Redirects to admin login if not logged in.
 * Enforces a 2-hour idle session timeout.
 */
function require_admin(): void {
    if (!is_admin_logged_in()) {
        init_session();
        $target = $_SERVER['REQUEST_URI'] ?? '/admin';
        $_SESSION['intended_url'] = $target;

        $loginUrl = BASE_URL . '/admin/login.php';
        header('Location: ' . $loginUrl);
        exit;
    }

    // Session inactivity timeout (2 hours)
    $idleTimeout = 7200;
    $lastActivity = (int)($_SESSION['last_activity'] ?? time());
    if ((time() - $lastActivity) > $idleTimeout) {
        logout_admin();
        init_session();
        $_SESSION['flash_messages'][] = [
            'type'    => 'warning',
            'message' => 'Your session expired due to inactivity. Please sign in again.'
        ];
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Get server-side login rate limit status for given IP.
 *
 * @param string $ip
 * @return array{allowed: bool, remaining_min: int}
 */
function get_ip_rate_limit(string $ip): array {
    $dir = defined('STORAGE_PATH') ? STORAGE_PATH . '/cache/rate_limit' : dirname(__DIR__) . '/storage/cache/rate_limit';
    $file = $dir . '/attempt_' . md5($ip) . '.json';

    if (!file_exists($file)) {
        return ['allowed' => true, 'remaining_min' => 0];
    }

    $raw = @file_get_contents($file);
    $data = is_string($raw) ? @json_decode($raw, true) : null;
    if (!is_array($data) || empty($data['count']) || empty($data['last'])) {
        return ['allowed' => true, 'remaining_min' => 0];
    }

    $elapsed = time() - (int)$data['last'];
    $decayWindow = 900; // 15 minutes

    if ($elapsed > $decayWindow) {
        @unlink($file);
        return ['allowed' => true, 'remaining_min' => 0];
    }

    if ((int)$data['count'] >= 5) {
        $remainingMin = (int)ceil(($decayWindow - $elapsed) / 60);
        return ['allowed' => false, 'remaining_min' => max(1, $remainingMin)];
    }

    return ['allowed' => true, 'remaining_min' => 0];
}

/**
 * Record a failed login attempt for given IP.
 *
 * @param string $ip
 */
function record_ip_login_failure(string $ip): void {
    $dir = defined('STORAGE_PATH') ? STORAGE_PATH . '/cache/rate_limit' : dirname(__DIR__) . '/storage/cache/rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file = $dir . '/attempt_' . md5($ip) . '.json';
    $raw = file_exists($file) ? @file_get_contents($file) : false;
    $data = is_string($raw) ? @json_decode($raw, true) : null;
    $count = (is_array($data) && !empty($data['count'])) ? ((int)$data['count'] + 1) : 1;

    $record = [
        'ip'    => $ip,
        'count' => $count,
        'last'  => time()
    ];

    @file_put_contents($file, json_encode($record), LOCK_EX);
}

/**
 * Clear rate limit for given IP after successful authentication.
 *
 * @param string $ip
 */
function clear_ip_login_rate_limit(string $ip): void {
    $dir = defined('STORAGE_PATH') ? STORAGE_PATH . '/cache/rate_limit' : dirname(__DIR__) . '/storage/cache/rate_limit';
    $file = $dir . '/attempt_' . md5($ip) . '.json';
    if (file_exists($file)) {
        @unlink($file);
    }
}

/**
 * Attempt admin login with progressive throttling and password verification.
 *
 * @param string $usernameOrEmail
 * @param string $password
 * @return array{success: bool, error: ?string}
 */
function attempt_admin_login(string $usernameOrEmail, string $password): array {
    init_session();

    $identifier = trim($usernameOrEmail);
    if ($identifier === '' || $password === '') {
        return ['success' => false, 'error' => 'Please provide both username/email and password.'];
    }

    // IP-based server-side rate limiting: max 5 failed attempts per IP within 15 minutes
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateLimit = get_ip_rate_limit($clientIp);

    if (!$rateLimit['allowed']) {
        return [
            'success' => false, 
            'error' => "Too many failed login attempts. Please wait {$rateLimit['remaining_min']} minute(s) before trying again."
        ];
    }

    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT id, username, email, password_hash FROM admins WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute([':u' => $identifier, ':e' => $identifier]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, (string)$admin['password_hash'])) {
            // Password verified successfully: clear IP rate limiter
            clear_ip_login_rate_limit($clientIp);

            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = (string)$admin['username'];
            $_SESSION['admin_email'] = (string)$admin['email'];
            $_SESSION['admin_logged_at'] = time();
            $_SESSION['last_activity'] = time();

            // Update last_login_at in database
            $updateStmt = $db->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
            $updateStmt->execute([':id' => $admin['id']]);

            return ['success' => true, 'error' => null];
        }
    } catch (Throwable $e) {
        error_log('[DEVZOOP AUTH ERROR] ' . $e->getMessage());
        return ['success' => false, 'error' => 'An authentication error occurred. Please try again.'];
    }

    // Increment failed attempts for this client IP
    record_ip_login_failure($clientIp);

    return ['success' => false, 'error' => 'Invalid credentials provided.'];
}

/**
 * Log out current admin, clear session data, and destroy session cookie.
 */
function logout_admin(): void {
    init_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
