<?php
/**
 * DEVZOOP Dynamic XML Sitemap Generator
 * Generates valid XML sitemap of all published articles for search engine crawlers.
 */

declare(strict_types=1);

define('DEVZOOP_ROOT', dirname(__DIR__));
require_once DEVZOOP_ROOT . '/includes/config.php';
require_once DEVZOOP_ROOT . '/includes/database.php';
require_once DEVZOOP_ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');

$posts = [];
if (db_is_available()) {
    try {
        $db = get_db();
        $stmt = $db->query('
            SELECT slug, updated_at, published_at 
            FROM posts 
            WHERE status = "published" 
            ORDER BY published_at DESC
        ');
        $posts = $stmt->fetchAll();
    } catch (Throwable) {
        $posts = [];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Homepage -->
    <url>
        <loc><?= e(BASE_URL) ?>/</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <!-- Blog Listing -->
    <url>
        <loc><?= e(BASE_URL) ?>/blog/</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <!-- Published Articles -->
    <?php foreach ($posts as $p): ?>
        <?php 
            $lastmod = !empty($p['updated_at']) ? date('c', strtotime($p['updated_at'])) : date('c', strtotime($p['published_at'] ?? 'now'));
        ?>
        <url>
            <loc><?= e(BASE_URL) ?>/blog/<?= urlencode($p['slug']) ?></loc>
            <lastmod><?= e($lastmod) ?></lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>
</urlset>
