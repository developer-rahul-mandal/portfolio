<?php
/**
 * DEVZOOP Database & Admin Installer
 * Sets up MySQL tables, seeds demo articles, and securely creates the first administrator.
 * Can be run via CLI (`php install.php`) or web browser (`install.php`).
 */

declare(strict_types=1);

define('DEVZOOP_ROOT', __DIR__);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

// Security check: Lock file prevents unauthorized execution
$lockFile = __DIR__ . '/storage/installed.lock';
if (file_exists($lockFile)) {
    if (php_sapi_name() === 'cli') {
        echo "Error: Installation is locked. To re-install, remove storage/installed.lock\n";
        exit(1);
    }
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title><style>body{background:#0a0a0b;color:#e8e8e8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center;"><h2>Installer Locked</h2><p style="color:#ff5c1f;">DEVZOOP has already been initialized. For security, install.php is locked.</p><p style="margin-top:20px;"><a href="admin/login.php" style="color:#b8ff2e; text-decoration:none;">Go to Admin Login &rarr;</a></p></div></body></html>');
}

// Helper to check if database already has an admin
function check_existing_admin(PDO $db): bool {
    try {
        $stmt = $db->query('SELECT COUNT(*) FROM admins');
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable) {
        return false;
    }
}

// ════════════════════════════════════════════════════════════
// CLI MODE
// ════════════════════════════════════════════════════════════
if (php_sapi_name() === 'cli') {
    echo "====================================================\n";
    echo "  DEVZOOP Blog — Database & Admin Installer (CLI)   \n";
    echo "====================================================\n\n";

    echo "Attempting database connection to " . DB_HOST . ":" . DB_PORT . " / " . DB_NAME . "...\n";

    try {
        // Direct database connection
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable) {
            // Fallback: create database if on local development environment
            $dsnWithoutDb = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
            $serverPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci");
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        echo "✓ Database `" . DB_NAME . "` verified.\n";

        // Run migrations
        echo "Running migration 001_initial_schema.sql...\n";
        $schemaSql = file_get_contents(__DIR__ . '/migrations/001_initial_schema.sql');
        $pdo->exec($schemaSql);
        echo "✓ Migration 001 completed.\n";

        echo "Running migration 002_seed_demo_data.sql...\n";
        $seedSql = file_get_contents(__DIR__ . '/migrations/002_seed_demo_data.sql');
        $pdo->exec($seedSql);
        echo "✓ Migration 002 completed (demo articles seeded).\n";

        if (check_existing_admin($pdo)) {
            echo "\nAn administrator account already exists. Setup is already complete.\n";
            echo "Access your admin panel at: " . BASE_URL . "/admin/\n";
            exit(0);
        }

        echo "\n--- Create Initial Administrator ---\n";
        echo "Username: ";
        $username = trim((string)fgets(STDIN));
        echo "Email: ";
        $email = trim((string)fgets(STDIN));
        echo "Password: ";
        $password = trim((string)fgets(STDIN));

        if (empty($username) || empty($email) || empty($password)) {
            echo "Error: All fields are required.\n";
            exit(1);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, email, password_hash) VALUES (:u, :e, :p)');
        $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);

        echo "\n✓ Administrator `{$username}` created successfully!\n";
        echo "You can now log in at: " . BASE_URL . "/admin/login.php\n";
        exit(0);

    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ════════════════════════════════════════════════════════════
// WEB MODE
// ════════════════════════════════════════════════════════════
$error = null;
$success = null;
$dbConnected = false;
$adminExists = false;

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable) {
        $dsnWithoutDb = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $serverPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci");
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    $dbConnected = true;
    $adminExists = check_existing_admin($pdo);
} catch (Throwable $e) {
    error_log('[DEVZOOP INSTALL ERROR] ' . $e->getMessage());
    $error = 'Database connection could not be established. Please check your .env configuration.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbConnected && !$adminExists) {
    require_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Run migrations
            $schemaSql = file_get_contents(__DIR__ . '/migrations/001_initial_schema.sql');
            $pdo->exec($schemaSql);

            $seedSql = file_get_contents(__DIR__ . '/migrations/002_seed_demo_data.sql');
            $pdo->exec($seedSql);

            // Create admin
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, email, password_hash) VALUES (:u, :e, :p)');
            $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);

            // Create installation lock file for security
            @file_put_contents($lockFile, 'Installed on ' . date('c'));

            $success = "Setup complete! Administrator account `{$username}` was created successfully.";
            $adminExists = true;
        } catch (Throwable $e) {
            error_log('[DEVZOOP INSTALL ERROR] ' . $e->getMessage());
            $error = 'An error occurred during installation. Please check server logs.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEVZOOP — Platform Installer</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link href="assets/remixIcon/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { background: #0a0a0b; color: #e8e8e8; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .install-box { background: #111113; border: 1px solid #2a2a2d; border-radius: 12px; padding: 32px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.5); }
        .install-title { font-size: 24px; font-weight: 800; margin-bottom: 8px; color: #fff; }
        .install-title span { color: #ff5c1f; }
        .install-desc { color: #8a8a8a; font-size: 13px; margin-bottom: 24px; line-height: 1.6; }
        .alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
        .alert-error { background: rgba(255,95,87,0.1); border: 1px solid rgba(255,95,87,0.3); color: #ff7b72; }
        .alert-success { background: rgba(184,255,46,0.1); border: 1px solid rgba(184,255,46,0.3); color: #b8ff2e; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-family: monospace; font-size: 11px; text-transform: uppercase; color: #8a8a8a; margin-bottom: 6px; }
        .form-input { width: 100%; background: #161618; border: 1px solid #2a2a2d; border-radius: 6px; padding: 10px 14px; color: #fff; font-family: inherit; font-size: 14px; outline: none; }
        .form-input:focus { border-color: #ff5c1f; }
        .btn-install { width: 100%; justify-content: center; margin-top: 8px; }
        .db-badge { display: inline-flex; align-items: center; gap: 6px; font-family: monospace; font-size: 11px; padding: 4px 10px; border-radius: 4px; margin-bottom: 20px; }
        .db-badge.ok { background: rgba(184,255,46,0.1); color: #b8ff2e; border: 1px solid rgba(184,255,46,0.2); }
        .db-badge.fail { background: rgba(255,95,87,0.1); color: #ff7b72; border: 1px solid rgba(255,95,87,0.2); }
    </style>
</head>
<body>
    <div class="install-box">
        <h1 class="install-title">DEV<span>ZOOP</span> // SETUP</h1>
        <p class="install-desc">Initialize your MySQL database, migrate schema, seed demo articles, and create your master administrator.</p>

        <?php if ($dbConnected): ?>
            <div class="db-badge ok"><i class="ri-database-2-line"></i> MySQL Connected: <?= e(DB_HOST) ?> / <?= e(DB_NAME) ?></div>
        <?php else: ?>
            <div class="db-badge fail"><i class="ri-error-warning-line"></i> MySQL Disconnected (Check .env or XAMPP)</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <a href="admin/login.php" class="btn btn-primary btn-install"><span>Go to Admin Login</span><i class="ri-arrow-right-line"></i></a>
        <?php elseif ($adminExists): ?>
            <div class="alert alert-success">An administrator is already configured. Setup is complete.</div>
            <a href="admin/login.php" class="btn btn-primary btn-install"><span>Go to Admin Login</span><i class="ri-arrow-right-line"></i></a>
        <?php elseif ($dbConnected): ?>
            <form method="POST" action="install.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="username">Admin Username</label>
                    <input class="form-input" type="text" id="username" name="username" required placeholder="e.g. rahul">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Admin Email</label>
                    <input class="form-input" type="email" id="email" name="email" required placeholder="me@devzoop.in">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Admin Password (min. 8 chars)</label>
                    <input class="form-input" type="password" id="password" name="password" required minlength="8" placeholder="••••••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-install">
                    <span>Initialize Platform</span>
                    <i class="ri-flashlight-line"></i>
                </button>
            </form>
        <?php else: ?>
            <p style="color:#8a8a8a; font-size:13px;">Please start your MySQL server in XAMPP or configure <code>.env</code> with your database credentials to continue.</p>
        <?php endif; ?>
    </div>
</body>
</html>
