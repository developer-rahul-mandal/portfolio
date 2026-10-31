<?php
/**
 * DEVZOOP Admin Dashboard
 * Displays real database metrics and recent articles.
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/header.php';

$stats = [
    'total_posts'     => 0,
    'published_posts' => 0,
    'draft_posts'     => 0,
    'categories'      => 0,
    'tags'            => 0
];
$recentPosts = [];
$dbError = null;

try {
    $db = get_db();

    // 1. Post statistics
    $stmt = $db->query('
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as drafts
        FROM posts
    ');
    $row = $stmt->fetch();
    if ($row) {
        $stats['total_posts'] = (int)($row['total'] ?? 0);
        $stats['published_posts'] = (int)($row['published'] ?? 0);
        $stats['draft_posts'] = (int)($row['drafts'] ?? 0);
    }

    // 2. Categories count
    $stats['categories'] = (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn();

    // 3. Tags count
    $stats['tags'] = (int)$db->query('SELECT COUNT(*) FROM tags')->fetchColumn();

    // 4. Recent Posts
    $recentStmt = $db->query('
        SELECT p.id, p.title, p.slug, p.status, p.is_featured, p.published_at, p.created_at, c.name as category_name
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ');
    $recentPosts = $recentStmt->fetchAll();

} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;">Dashboard Overview</h1>
    <p style="color: var(--c-text-muted); font-size: 13px;">Real-time publishing metrics and recent repository activity.</p>
</div>

<?php if ($dbError): ?>
    <div class="alert alert-error" style="background: rgba(255,95,87,0.1); color: #ff7b72; padding: 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(255,95,87,0.3);">
        <strong>Database Warning:</strong> <?= e($dbError) ?>
        <br><span style="font-size: 12px;">If you haven't run migrations yet, visit <a href="../install.php" style="color: #fff; text-decoration: underline;">install.php</a>.</span>
    </div>
<?php endif; ?>

<!-- Metrics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px;">
    <div class="admin-card" style="margin-bottom: 0;">
        <div style="font-family: var(--f-mono); font-size: 11px; text-transform: uppercase; color: var(--c-text-dim); margin-bottom: 8px;">Total Articles</div>
        <div style="font-size: 2.2rem; font-weight: 800; color: #fff;"><?= $stats['total_posts'] ?></div>
    </div>
    <div class="admin-card" style="margin-bottom: 0;">
        <div style="font-family: var(--f-mono); font-size: 11px; text-transform: uppercase; color: var(--c-text-dim); margin-bottom: 8px;">Published</div>
        <div style="font-size: 2.2rem; font-weight: 800; color: var(--c-green);"><?= $stats['published_posts'] ?></div>
    </div>
    <div class="admin-card" style="margin-bottom: 0;">
        <div style="font-family: var(--f-mono); font-size: 11px; text-transform: uppercase; color: var(--c-text-dim); margin-bottom: 8px;">Drafts</div>
        <div style="font-size: 2.2rem; font-weight: 800; color: #ffbd2e;"><?= $stats['draft_posts'] ?></div>
    </div>
    <div class="admin-card" style="margin-bottom: 0;">
        <div style="font-family: var(--f-mono); font-size: 11px; text-transform: uppercase; color: var(--c-text-dim); margin-bottom: 8px;">Categories</div>
        <div style="font-size: 2.2rem; font-weight: 800; color: var(--c-accent);"><?= $stats['categories'] ?></div>
    </div>
    <div class="admin-card" style="margin-bottom: 0;">
        <div style="font-family: var(--f-mono); font-size: 11px; text-transform: uppercase; color: var(--c-text-dim); margin-bottom: 8px;">Tags</div>
        <div style="font-size: 2.2rem; font-weight: 800; color: var(--c-silver);"><?= $stats['tags'] ?></div>
    </div>
</div>

<!-- Recent Posts -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">Recent Articles</h2>
        <a href="posts.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 11px;">
            <span>View All (<?= $stats['total_posts'] ?>)</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentPosts)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 32px; color: var(--c-text-dim);">
                            No articles created yet. <a href="post-editor.php" style="color: var(--c-accent);">Write your first article</a>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentPosts as $p): ?>
                        <tr>
                            <td>
                                <a href="post-editor.php?id=<?= (int)$p['id'] ?>" style="color: #fff; font-weight: 600;">
                                    <?= e($p['title']) ?>
                                </a>
                                <?php if (!empty($p['is_featured'])): ?>
                                    <span class="badge" style="background: rgba(255,92,31,0.15); color: var(--c-accent); border: 1px solid rgba(255,92,31,0.3); margin-left: 6px;">Featured</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['category_name'] ?? 'Uncategorized') ?></td>
                            <td>
                                <span class="badge badge-<?= e($p['status']) ?>">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td style="font-family: var(--f-mono); font-size: 11px;">
                                <?= format_date($p['published_at'] ?? $p['created_at']) ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="post-editor.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; margin-right: 4px;" title="Edit">
                                    <i class="ri-edit-line"></i>
                                </a>
                                <?php if ($p['status'] === 'published'): ?>
                                    <a href="../blog/post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" title="View Live">
                                        <i class="ri-external-link-line"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
