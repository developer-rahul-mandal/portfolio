<?php
/**
 * DEVZOOP Admin — Post Management
 * Search, filter, paginate, publish, unpublish, and delete articles.
 */

declare(strict_types=1);

$pageTitle = 'Articles';
require_once __DIR__ . '/header.php';

$db = get_db();

// Handle quick actions: publish, draft, archive, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);

    if ($postId > 0) {
        if ($action === 'delete') {
            // Delete post and its cover image
            $imgStmt = $db->prepare('SELECT cover_image FROM posts WHERE id = :id');
            $imgStmt->execute([':id' => $postId]);
            $cover = $imgStmt->fetchColumn();
            if ($cover) {
                delete_uploaded_image((string)$cover);
            }

            $delStmt = $db->prepare('DELETE FROM posts WHERE id = :id');
            $delStmt->execute([':id' => $postId]);
            set_flash('success', 'Article deleted successfully.');
        } elseif (in_array($action, ['publish', 'draft', 'archive'], true)) {
            $statusMap = [
                'publish' => 'published',
                'draft'   => 'draft',
                'archive' => 'archived'
            ];
            $newStatus = $statusMap[$action];
            $pubDateSql = ($newStatus === 'published') ? ', published_at = IFNULL(published_at, NOW())' : '';
            
            $stmt = $db->prepare("UPDATE posts SET status = :status {$pubDateSql} WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $postId]);
            set_flash('success', "Article status changed to {$newStatus}.");
        }
    }

    header('Location: posts.php');
    exit;
}

// Filters & Search
$search = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$catId = (int)($_GET['category'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.title LIKE :q OR p.excerpt LIKE :q OR p.slug LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

if ($status !== 'all' && in_array($status, ['published', 'draft', 'archived'], true)) {
    $where[] = 'p.status = :status';
    $params[':status'] = $status;
}

if ($catId > 0) {
    $where[] = 'p.category_id = :cat_id';
    $params[':cat_id'] = $catId;
}

$whereSql = implode(' AND ', $where);

// Count total matching
$countStmt = $db->prepare("SELECT COUNT(*) FROM posts p WHERE {$whereSql}");
$countStmt->execute($params);
$totalPosts = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalPosts / $perPage);

// Fetch posts
$query = "
    SELECT p.id, p.title, p.slug, p.status, p.is_featured, p.published_at, p.created_at, 
           c.name as category_name
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY p.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Categories for filter dropdown
$categories = $db->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
?>

<div class="admin-card-header">
    <div>
        <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;">Article Management</h1>
        <p style="color: var(--c-text-muted); font-size: 13px;">Create, update, organize, and publish developer notes.</p>
    </div>
    <a href="post-editor.php" class="btn btn-primary" style="padding: 10px 18px; font-size: 12px;">
        <i class="ri-add-line"></i>
        <span>Write New Note</span>
    </a>
</div>

<!-- Filters Bar -->
<div class="admin-card" style="padding: 16px; margin-bottom: 20px;">
    <form method="GET" action="posts.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 220px;">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search titles, excerpts..." class="form-control" style="padding: 8px 12px; font-size: 13px;">
        </div>
        <div style="min-width: 150px;">
            <select name="status" class="form-control" style="padding: 8px 12px; font-size: 13px;" onchange="this.form.submit()">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Drafts</option>
                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>
        <div style="min-width: 160px;">
            <select name="category" class="form-control" style="padding: 8px 12px; font-size: 13px;" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $catId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline" style="padding: 8px 14px; font-size: 12px;">
            <i class="ri-filter-line"></i> Filter
        </button>
        <?php if ($search !== '' || $status !== 'all' || $catId > 0): ?>
            <a href="posts.php" class="btn btn-outline" style="padding: 8px 12px; font-size: 12px;" title="Reset filters">
                <i class="ri-refresh-line"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Table Card -->
<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 45%;">Article</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--c-text-dim);">
                            No articles match your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $p): ?>
                        <tr>
                            <td>
                                <a href="post-editor.php?id=<?= (int)$p['id'] ?>" style="color: #fff; font-weight: 600; display: inline-block; margin-bottom: 4px;">
                                    <?= e($p['title']) ?>
                                </a>
                                <?php if (!empty($p['is_featured'])): ?>
                                    <span class="badge" style="background: rgba(255,92,31,0.15); color: var(--c-accent); border: 1px solid rgba(255,92,31,0.3); margin-left: 6px;">Featured</span>
                                <?php endif; ?>
                                <div style="font-family: var(--f-mono); font-size: 11px; color: var(--c-text-dim);">
                                    /blog/<?= e($p['slug']) ?>
                                </div>
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
                                    <a href="../blog/post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; margin-right: 4px;" title="View Live">
                                        <i class="ri-external-link-line"></i>
                                    </a>
                                    <form method="POST" action="posts.php" style="display: inline;" onsubmit="return confirm('Move this article back to drafts?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="draft">
                                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; margin-right: 4px;" title="Unpublish">
                                            <i class="ri-draft-line"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="posts.php" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; margin-right: 4px; color: var(--c-green);" title="Publish Now">
                                            <i class="ri-check-line"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" action="posts.php" style="display: inline;" onsubmit="return confirm('Permanently delete this article? This action cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #ff7b72;" title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--c-border-light);">
            <div style="font-family: var(--f-mono); font-size: 12px; color: var(--c-text-dim);">
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalPosts ?> articles total)
            </div>
            <div style="display: flex; gap: 8px;">
                <?php if ($page > 1): ?>
                    <a href="posts.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">
                        <i class="ri-arrow-left-line"></i> Previous
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="posts.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">
                        Next <i class="ri-arrow-right-line"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
