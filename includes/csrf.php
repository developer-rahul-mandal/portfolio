<?php
/**
 * DEVZOOP Blog CSRF Protection
 * Provides cryptographic token generation and strict validation.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Generate or retrieve current CSRF token from session.
 *
 * @return string
 */
function csrf_token(): string {
    init_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

/**
 * Generate a hidden HTML input field with the CSRF token.
 *
 * @return string
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate CSRF token from POST request or custom header.
 *
 * @param string|null $token
 * @return bool
 */
function validate_csrf(?string $token = null): bool {
    init_session();

    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    if (!is_string($token) || empty($token)) {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || empty($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Enforce CSRF check or terminate with HTTP 403 Forbidden.
 */
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf()) {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title><style>body{background:#0a0a0b;color:#e8e8e8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center;"><h2>403 Forbidden</h2><p style="color:#ff5c1f;">Invalid or expired CSRF security token. Please go back, refresh the page, and try again.</p></div></body></html>');
        }
    }
}
