<?php
/**
 * DEVZOOP Admin Login
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';

init_session();

if (is_admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $identifier = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $result = attempt_admin_login($identifier, $password);
    if ($result['success']) {
        $rawDest = (string)($_SESSION['intended_url'] ?? 'index.php');
        unset($_SESSION['intended_url']);

        // Prevent open redirect: must be a local relative path, no scheme or host
        $parsed = parse_url($rawDest);
        $dest = 'index.php';
        if (empty($parsed['scheme']) && empty($parsed['host']) && !str_starts_with($rawDest, '//') && !str_starts_with($rawDest, '/\\')) {
            $dest = $rawDest;
        }

        header('Location: ' . $dest);
        exit;
    } else {
        $error = $result['error'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — DEVZOOP CMS</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <link href="../assets/remixIcon/fonts/remixicon.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            background: #0a0a0b;
            color: #e8e8e8;
            font-family: var(--f-sans);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: #111113;
            border: 1px solid #2a2a2d;
            border-radius: 12px;
            padding: 40px 32px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
        }
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-title {
            font-size: 1.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .login-title span { color: var(--c-accent); }
        .login-subtitle {
            font-family: var(--f-mono);
            font-size: 12px;
            color: var(--c-text-dim);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .alert-error {
            background: rgba(255, 95, 87, 0.1);
            border: 1px solid rgba(255, 95, 87, 0.3);
            color: #ff7b72;
            padding: 12px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-family: var(--f-mono);
            font-size: 11px;
            text-transform: uppercase;
            color: var(--c-text-muted);
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            background: #161618;
            border: 1px solid #2a2a2d;
            border-radius: 6px;
            padding: 12px 14px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            border-color: var(--c-accent);
        }
        .btn-submit {
            width: 100%;
            justify-content: center;
            padding: 12px;
            margin-top: 8px;
        }
        .back-link {
            display: block;
            text-align: center;
            font-family: var(--f-mono);
            font-size: 11px;
            color: var(--c-text-dim);
            margin-top: 24px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .back-link:hover {
            color: var(--c-accent);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-title">DEV<span>ZOOP</span></div>
            <div class="login-subtitle">System Administrator Gate</div>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <input class="form-input" type="text" id="username" name="username" required autocomplete="username" autofocus placeholder="admin username">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••••">
            </div>
            <button type="submit" class="btn btn-primary btn-submit">
                <span>Authenticate</span>
                <i class="ri-arrow-right-line"></i>
            </button>
        </form>

        <a href="../" class="back-link">
            <i class="ri-arrow-left-line"></i> Return to Public Site
        </a>
    </div>
</body>
</html>
