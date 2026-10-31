<?php
/**
 * DEVZOOP Admin — Shared Header Template
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_admin();
$admin = current_admin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — DEVZOOP CMS</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <link href="../assets/remixIcon/fonts/remixicon.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --admin-sidebar-w: 240px;
        }
        body {
            background-color: var(--c-bg);
            color: var(--c-text);
            font-family: var(--f-sans);
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: var(--admin-sidebar-w);
            background: var(--c-bg-elevated);
            border-right: 1px solid var(--c-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
        }
        .admin-brand {
            padding: 24px 20px;
            border-bottom: 1px solid var(--c-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .admin-brand .logo-text { font-size: 1.15rem; }
        .admin-nav {
            padding: 20px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--f-mono);
            font-size: var(--fs-xs);
            color: var(--c-text-muted);
            padding: 10px 14px;
            border-radius: var(--r-sm);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: all var(--duration) var(--ease-out);
        }
        .admin-nav-item i { font-size: 1.1rem; }
        .admin-nav-item:hover {
            color: var(--c-text);
            background: var(--c-bg-surface);
        }
        .admin-nav-item.active {
            color: var(--c-bg);
            background: var(--c-accent);
            font-weight: 700;
        }
        .admin-sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--c-border);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--c-accent);
            color: var(--c-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }
        .admin-user-name {
            font-family: var(--f-mono);
            font-size: 12px;
            color: var(--c-text);
            font-weight: 600;
            line-height: 1.2;
        }
        .admin-user-role {
            font-family: var(--f-mono);
            font-size: 10px;
            color: var(--c-green);
            text-transform: uppercase;
        }
        .admin-main-wrap {
            margin-left: var(--admin-sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .admin-topbar {
            height: 64px;
            background: var(--c-bg);
            border-bottom: 1px solid var(--c-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-content {
            padding: 32px;
            flex: 1;
            max-width: 1400px;
            width: 100%;
        }
        /* Tables & Cards */
        .admin-card {
            background: var(--c-bg-surface);
            border: 1px solid var(--c-border);
            border-radius: var(--r-md);
            padding: 24px;
            margin-bottom: 24px;
        }
        .admin-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .admin-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
        }
        .admin-table-wrap {
            overflow-x: auto;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .admin-table th {
            text-align: left;
            padding: 12px 16px;
            font-family: var(--f-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-text-dim);
            border-bottom: 1px solid var(--c-border);
            background: var(--c-bg-elevated);
        }
        .admin-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--c-border-light);
            color: var(--c-text-muted);
        }
        .admin-table tr:hover td {
            background: rgba(255, 255, 255, 0.015);
            color: var(--c-text);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--f-mono);
            font-size: 10px;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: var(--r-sm);
            letter-spacing: 0.06em;
        }
        .badge-published { background: rgba(184, 255, 46, 0.1); color: var(--c-green); border: 1px solid rgba(184, 255, 46, 0.25); }
        .badge-draft { background: rgba(255, 189, 46, 0.1); color: #ffbd2e; border: 1px solid rgba(255, 189, 46, 0.25); }
        .badge-archived { background: rgba(138, 138, 138, 0.1); color: var(--c-silver); border: 1px solid rgba(138, 138, 138, 0.25); }
        /* Forms */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-family: var(--f-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-text-dim);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            background: var(--c-bg-elevated);
            border: 1px solid var(--c-border);
            border-radius: var(--r-sm);
            padding: 10px 14px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color var(--duration) var(--ease-out);
        }
        .form-control:focus {
            border-color: var(--c-accent);
        }
        textarea.form-control {
            line-height: 1.6;
        }
        /* Mobile Toggle */
        .admin-menu-toggle {
            display: none;
            font-size: 20px;
            color: #fff;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform var(--duration) var(--ease-out);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-main-wrap {
                margin-left: 0;
            }
            .admin-menu-toggle {
                display: block;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .admin-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
        <a href="index.php" class="logo">
            <span class="logo-text">DEV<span class="logo-accent">ZOOP</span></span>
            <span class="logo-dot"></span>
        </a>
    </div>
    <nav class="admin-nav">
        <a href="index.php" class="admin-nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="ri-dashboard-3-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="posts.php" class="admin-nav-item <?= ($currentPage === 'posts.php' || $currentPage === 'post-editor.php') ? 'active' : '' ?>">
            <i class="ri-article-line"></i>
            <span>Articles</span>
        </a>
        <a href="categories.php" class="admin-nav-item <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <i class="ri-folder-line"></i>
            <span>Categories</span>
        </a>
        <a href="tags.php" class="admin-nav-item <?= $currentPage === 'tags.php' ? 'active' : '' ?>">
            <i class="ri-price-tag-3-line"></i>
            <span>Tags</span>
        </a>
        <a href="../blog/index.php" target="_blank" class="admin-nav-item" style="margin-top: auto;">
            <i class="ri-external-link-line"></i>
            <span>View Public Blog</span>
        </a>
    </nav>
    <div class="admin-sidebar-footer">
        <div class="admin-user-info">
            <div class="admin-user-avatar" style="overflow: hidden; padding: 0;">
                <img src="../assets/img/rahul-about.jpg" alt="<?= e($admin['username'] ?? 'Admin') ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
            </div>
            <div>
                <div class="admin-user-name"><?= e($admin['username'] ?? 'Admin') ?></div>
                <div class="admin-user-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 11px; justify-content: center;">
            <i class="ri-logout-box-r-line"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- Main Wrap -->
<div class="admin-main-wrap">
    <header class="admin-topbar">
        <button type="button" class="admin-menu-toggle" id="adminMenuToggle" aria-label="Toggle navigation">
            <i class="ri-menu-line"></i>
        </button>
        <div style="font-family: var(--f-mono); font-size: 12px; color: var(--c-text-dim); text-transform: uppercase;">
            DEVZOOP // CMS CORE v2.0
        </div>
        <div>
            <a href="post-editor.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 12px;">
                <i class="ri-add-line"></i>
                <span>New Article</span>
            </a>
        </div>
    </header>

    <div class="admin-content">
        <?php foreach (get_flash() as $flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>" style="padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; <?= $flash['type'] === 'success' ? 'background: rgba(184,255,46,0.1); color: #b8ff2e; border: 1px solid rgba(184,255,46,0.3);' : 'background: rgba(255,95,87,0.1); color: #ff7b72; border: 1px solid rgba(255,95,87,0.3);' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endforeach; ?>
