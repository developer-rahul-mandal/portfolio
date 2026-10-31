<?php
/**
 * DEVZOOP Admin — Category Management
 */

declare(strict_types=1);

$pageTitle = 'Categories';
require_once __DIR__ . '/header.php';

$db = get_db();
$error = null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $manualSlug = trim((string)($_POST['slug'] ?? ''));
        $slug = !empty($manualSlug) ? slugify($manualSlug) : slugify($name);
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            try {
                $stmt = $db->prepare('INSERT INTO categories (name, slug, description) VALUES (:n, :s, :d)');
                $stmt->execute([':n' => $name, ':s' => $slug, ':d' => $desc ?: null]);
                set_flash('success', "Category `{$name}` created successfully.");
                header('Location: categories.php');
                exit;
            } catch (Throwable $e) {
                $error = 'Error creating category: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        // Check if any posts use this category
        $checkStmt = $db->prepare('SELECT COUNT(*) FROM posts WHERE category_id = :id');
        $checkStmt->execute([':id' => $catId]);
        $count = (int)$checkStmt->fetchColumn();

        if ($count > 0) {
            $error = "Cannot delete category: {$count} article(s) are currently assigned to it.";
        } else {
            $stmt = $db->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute([':id' => $catId]);
            set_flash('success', 'Category deleted.');
            header('Location: categories.php');
            exit;
        }
    }
}

// Fetch categories with post count
$categories = $db->query('
    SELECT c.*, COUNT(p.id) as post_count
    FROM categories c
    LEFT JOIN posts p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.name ASC
')->fetchAll();
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;">Category Taxonomy</h1>
    <p style="color: var(--c-text-muted); font-size: 13px;">Organize developer articles into thematic tracks.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" style="background: rgba(255,95,87,0.1); color: #ff7b72; padding: 14px; border-radius: 6px; margin-bottom: 20px; border: 1px solid rgba(255,95,87,0.3);">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Add Category Form -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 16px;">New Category</h2>
        <form method="POST" action="categories.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="name">Category Name *</label>
                <input type="text" id="name" name="name" required class="form-control" placeholder="e.g. SECURITY">
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug (Optional)</label>
                <input type="text" id="slug" name="slug" class="form-control" placeholder="auto-generated">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" rows="3" class="form-control" placeholder="Brief summary of topics in this category."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                <i class="ri-add-line"></i>
                <span>Create Category</span>
            </button>
        </form>
    </div>

    <!-- Category List Table -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 16px;">Active Categories (<?= count($categories) ?>)</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Slug</th>
                        <th>Articles</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <strong style="color: #fff;"><?= e($cat['name']) ?></strong>
                                <?php if (!empty($cat['description'])): ?>
                                    <div style="font-size: 11px; color: var(--c-text-dim); margin-top: 2px;">
                                        <?= e($cat['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: var(--f-mono); font-size: 11px;">
                                <?= e($cat['slug']) ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--c-text);">
                                    <?= (int)$cat['post_count'] ?> posts
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ((int)$cat['post_count'] === 0): ?>
                                    <form method="POST" action="categories.php" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #ff7b72;" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--c-text-dim);" title="Cannot delete: posts attached">In Use</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
