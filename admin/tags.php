<?php
/**
 * DEVZOOP Admin — Tag Management
 */

declare(strict_types=1);

$pageTitle = 'Tags';
require_once __DIR__ . '/header.php';

$db = get_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $manualSlug = trim((string)($_POST['slug'] ?? ''));
        $slug = !empty($manualSlug) ? slugify($manualSlug) : slugify($name);

        if ($name === '') {
            $error = 'Tag name is required.';
        } else {
            try {
                $stmt = $db->prepare('INSERT INTO tags (name, slug) VALUES (:n, :s)');
                $stmt->execute([':n' => $name, ':s' => $slug]);
                set_flash('success', "Tag `{$name}` created successfully.");
                header('Location: tags.php');
                exit;
            } catch (Throwable $e) {
                $error = 'Error creating tag: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $tagId = (int)($_POST['tag_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM tags WHERE id = :id');
        $stmt->execute([':id' => $tagId]);
        set_flash('success', 'Tag deleted.');
        header('Location: tags.php');
        exit;
    }
}

// Fetch tags with post count
$tags = $db->query('
    SELECT t.*, COUNT(pt.post_id) as post_count
    FROM tags t
    LEFT JOIN post_tags pt ON t.id = pt.tag_id
    GROUP BY t.id
    ORDER BY t.name ASC
')->fetchAll();
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;">Tag Taxonomy</h1>
    <p style="color: var(--c-text-muted); font-size: 13px;">Manage technical labels and topics across all developer articles.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" style="background: rgba(255,95,87,0.1); color: #ff7b72; padding: 14px; border-radius: 6px; margin-bottom: 20px; border: 1px solid rgba(255,95,87,0.3);">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Add Tag Form -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 16px;">New Tag</h2>
        <form method="POST" action="tags.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="name">Tag Name *</label>
                <input type="text" id="name" name="name" required class="form-control" placeholder="e.g. Docker">
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug (Optional)</label>
                <input type="text" id="slug" name="slug" class="form-control" placeholder="auto-generated">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                <i class="ri-add-line"></i>
                <span>Create Tag</span>
            </button>
        </form>
    </div>

    <!-- Tags Table -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 16px;">Active Tags (<?= count($tags) ?>)</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Slug</th>
                        <th>Articles</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $t): ?>
                        <tr>
                            <td>
                                <strong style="color: #fff;">#<?= e($t['name']) ?></strong>
                            </td>
                            <td style="font-family: var(--f-mono); font-size: 11px;">
                                <?= e($t['slug']) ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--c-text);">
                                    <?= (int)$t['post_count'] ?> posts
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" action="tags.php" style="display: inline;" onsubmit="return confirm('Delete this tag?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="tag_id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #ff7b72;" title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
