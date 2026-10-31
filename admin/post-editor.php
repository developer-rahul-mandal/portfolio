<?php
/**
 * DEVZOOP Admin — Rich Article Editor
 * Create and edit technical articles with syntax blocks, cover upload, tags, and live preview.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_admin();
$db = get_db();

$postId = (int)($_GET['id'] ?? 0);
$isEditing = ($postId > 0);
$post = null;
$error = null;

// Fetch categories
$categories = $db->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

// Default values
$title = '';
$slug = '';
$excerpt = '';
$content = '';
$categoryId = !empty($categories) ? (int)$categories[0]['id'] : 1;
$status = 'draft';
$isFeatured = 0;
$metaTitle = '';
$metaDescription = '';
$coverImage = null;
$tagsString = '';

// If editing, load existing post
if ($isEditing) {
    $stmt = $db->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if (!$post) {
        set_flash('error', 'Article not found.');
        header('Location: posts.php');
        exit;
    }

    $title = (string)$post['title'];
    $slug = (string)$post['slug'];
    $excerpt = (string)$post['excerpt'];
    $content = (string)$post['content'];
    $categoryId = (int)$post['category_id'];
    $status = (string)$post['status'];
    $isFeatured = (int)$post['is_featured'];
    $metaTitle = (string)($post['meta_title'] ?? '');
    $metaDescription = (string)($post['meta_description'] ?? '');
    $coverImage = $post['cover_image'] ? (string)$post['cover_image'] : null;

    // Load existing tags
    $tagsStmt = $db->prepare('
        SELECT t.name 
        FROM tags t 
        INNER JOIN post_tags pt ON t.id = pt.tag_id 
        WHERE pt.post_id = :id
    ');
    $tagsStmt->execute([':id' => $postId]);
    $tagsList = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);
    $tagsString = implode(', ', $tagsList);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    $manualSlug = trim((string)($_POST['slug'] ?? ''));
    $slug = !empty($manualSlug) ? slugify($manualSlug) : slugify($title);
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 1);
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'draft';
    $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
    $metaTitle = trim((string)($_POST['meta_title'] ?? ''));
    $metaDescription = trim((string)($_POST['meta_description'] ?? ''));
    $rawTags = trim((string)($_POST['tags'] ?? ''));
    $removeCover = !empty($_POST['remove_cover']);

    // Validation
    if ($title === '') {
        $error = 'Article title is required.';
    } elseif ($excerpt === '') {
        $error = 'Article excerpt is required.';
    } elseif ($content === '') {
        $error = 'Article content is required.';
    } else {
        // Check slug uniqueness
        $slugCheckQuery = $isEditing 
            ? 'SELECT id FROM posts WHERE slug = :s AND id != :id LIMIT 1'
            : 'SELECT id FROM posts WHERE slug = :s LIMIT 1';
        $slugParams = [':s' => $slug];
        if ($isEditing) $slugParams[':id'] = $postId;

        $checkStmt = $db->prepare($slugCheckQuery);
        $checkStmt->execute($slugParams);
        if ($checkStmt->fetch()) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        // Handle image upload
        $uploadResult = handle_image_upload($_FILES['cover_image'] ?? []);
        if (!$uploadResult['success']) {
            $error = $uploadResult['error'];
        } else {
            if ($uploadResult['filename']) {
                if ($coverImage) {
                    delete_uploaded_image($coverImage);
                }
                $coverImage = $uploadResult['filename'];
            } elseif ($removeCover && $coverImage) {
                delete_uploaded_image($coverImage);
                $coverImage = null;
            }

            // Save post
            try {
                $db->beginTransaction();

                if ($isEditing) {
                    $updateStmt = $db->prepare('
                        UPDATE posts SET 
                            category_id = :cat,
                            title = :title,
                            slug = :slug,
                            excerpt = :excerpt,
                            content = :content,
                            cover_image = :cover,
                            status = :status,
                            is_featured = :feat,
                            published_at = IF(:status = "published", IFNULL(published_at, NOW()), published_at),
                            meta_title = :m_title,
                            meta_description = :m_desc
                        WHERE id = :id
                    ');
                    $updateStmt->execute([
                        ':cat'     => $categoryId,
                        ':title'   => $title,
                        ':slug'    => $slug,
                        ':excerpt' => $excerpt,
                        ':content' => $content,
                        ':cover'   => $coverImage,
                        ':status'  => $status,
                        ':feat'    => $isFeatured,
                        ':m_title' => $metaTitle,
                        ':m_desc'  => $metaDescription,
                        ':id'      => $postId
                    ]);
                } else {
                    $insertStmt = $db->prepare('
                        INSERT INTO posts 
                            (category_id, title, slug, excerpt, content, cover_image, status, is_featured, published_at, meta_title, meta_description)
                        VALUES 
                            (:cat, :title, :slug, :excerpt, :content, :cover, :status, :feat, IF(:status = "published", NOW(), NULL), :m_title, :m_desc)
                    ');
                    $insertStmt->execute([
                        ':cat'     => $categoryId,
                        ':title'   => $title,
                        ':slug'    => $slug,
                        ':excerpt' => $excerpt,
                        ':content' => $content,
                        ':cover'   => $coverImage,
                        ':status'  => $status,
                        ':feat'    => $isFeatured,
                        ':m_title' => $metaTitle,
                        ':m_desc'  => $metaDescription
                    ]);
                    $postId = (int)$db->lastInsertId();
                }

                // Sync tags
                $db->prepare('DELETE FROM post_tags WHERE post_id = :id')->execute([':id' => $postId]);
                $tagsArr = array_filter(array_map('trim', explode(',', $rawTags)));

                foreach ($tagsArr as $tagItem) {
                    if ($tagItem === '') continue;
                    $tagSlug = slugify($tagItem);

                    // Insert or select tag
                    $tagFind = $db->prepare('SELECT id FROM tags WHERE slug = :s LIMIT 1');
                    $tagFind->execute([':s' => $tagSlug]);
                    $tId = $tagFind->fetchColumn();

                    if (!$tId) {
                        $tInsert = $db->prepare('INSERT INTO tags (name, slug) VALUES (:n, :s)');
                        $tInsert->execute([':n' => $tagItem, ':s' => $tagSlug]);
                        $tId = (int)$db->lastInsertId();
                    }

                    // Attach to post
                    $db->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:p, :t)')->execute([
                        ':p' => $postId,
                        ':t' => $tId
                    ]);
                }

                $db->commit();
                set_flash('success', $isEditing ? 'Article updated successfully.' : 'Article created successfully.');
                header('Location: posts.php');
                exit;

            } catch (Throwable $e) {
                $db->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = $isEditing ? 'Edit Article' : 'New Article';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;">
            <?= $isEditing ? 'Edit Article' : 'Write New Article' ?>
        </h1>
        <p style="color: var(--c-text-muted); font-size: 13px;">Format your technical thoughts, add rich code editors, and publish to DEVZOOP.</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-outline" id="previewModalBtn" style="padding: 8px 14px; font-size: 12px;">
            <i class="ri-eye-line"></i> Live Preview
        </button>
        <a href="posts.php" class="btn btn-outline" style="padding: 8px 14px; font-size: 12px;">
            <i class="ri-close-line"></i> Cancel
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" style="background: rgba(255,95,87,0.1); color: #ff7b72; padding: 14px; border-radius: 6px; margin-bottom: 24px; border: 1px solid rgba(255,95,87,0.3);">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="POST" action="post-editor.php<?= $isEditing ? '?id=' . $postId : '' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Left Column: Main Editor -->
        <div>
            <!-- Title -->
            <div class="admin-card">
                <div class="form-group">
                    <label class="form-label" for="title">Article Title *</label>
                    <input type="text" id="title" name="title" value="<?= e($title) ?>" required class="form-control" style="font-size: 1.25rem; font-weight: 700; padding: 12px 16px;" placeholder="e.g. How I built my first CI/CD pipeline">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="slug">URL Slug</label>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-family: var(--f-mono); font-size: 12px; color: var(--c-text-dim);">/blog/</span>
                        <input type="text" id="slug" name="slug" value="<?= e($slug) ?>" class="form-control" style="font-family: var(--f-mono); font-size: 12px; padding: 8px 12px;" placeholder="auto-generated-from-title">
                    </div>
                </div>
            </div>

            <!-- Excerpt -->
            <div class="admin-card">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="excerpt">Article Excerpt (Lead Summary) *</label>
                    <textarea id="excerpt" name="excerpt" rows="3" required class="form-control" placeholder="A concise 2-3 sentence overview of this note. Appears in search cards and top of article."><?= e($excerpt) ?></textarea>
                </div>
            </div>

            <!-- Content with Helper Toolbar -->
            <div class="admin-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <label class="form-label" for="content" style="margin: 0;">Article Content (Markdown + Technical Blocks) *</label>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline snippet-btn" data-insert="## Heading Title" style="padding: 4px 8px; font-size: 11px;">H2</button>
                        <button type="button" class="btn btn-outline snippet-btn" data-insert="```yaml:.github/workflows/ci.yml&#10;name: CI&#10;on: [push]&#10;```" style="padding: 4px 8px; font-size: 11px;">Code Block</button>
                        <button type="button" class="btn btn-outline snippet-btn" data-insert="```terminal:git-terminal&#10;$ git status&#10;On branch main&#10;```" style="padding: 4px 8px; font-size: 11px;">Terminal</button>
                        <button type="button" class="btn btn-outline snippet-btn" data-insert="```workflow:Pipeline&#10;[done] Step 1 | Desc&#10;[active] Step 2 | In Progress&#10;```" style="padding: 4px 8px; font-size: 11px;">Workflow</button>
                        <button type="button" class="btn btn-outline snippet-btn" data-insert="> **Note:** Important takeaway" style="padding: 4px 8px; font-size: 11px;">Callout</button>
                    </div>
                </div>
                <textarea id="content" name="content" rows="22" required class="form-control" style="font-family: var(--f-mono); font-size: 13px; line-height: 1.6;" placeholder="Write your article in markdown. Use ```lang:filename.ext for rich code editor panels."><?= e($content) ?></textarea>
            </div>

            <!-- SEO Card -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="margin-bottom: 16px;">Search Engine Optimization (SEO)</h3>
                <div class="form-group">
                    <label class="form-label" for="meta_title">Custom Meta Title</label>
                    <input type="text" id="meta_title" name="meta_title" value="<?= e($metaTitle) ?>" class="form-control" placeholder="Defaults to Article Title">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="meta_description">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="2" class="form-control" placeholder="Defaults to Article Excerpt"><?= e($metaDescription) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right Column: Settings & Metadata -->
        <div>
            <!-- Publish Actions Card -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="margin-bottom: 16px;">Publish Status</h3>
                
                <div class="form-group">
                    <label class="form-label" for="status">Article Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft (Private)</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published (Live on /blog)</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_featured" value="1" <?= $isFeatured ? 'checked' : '' ?> style="accent-color: var(--c-accent); width: 16px; height: 16px;">
                        <span style="font-size: 13px; color: #fff;">Featured Article on Blog Hero</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                    <i class="ri-save-line"></i>
                    <span><?= $isEditing ? 'Save Changes' : 'Create & Save' ?></span>
                </button>
            </div>

            <!-- Category & Tags -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="margin-bottom: 16px;">Taxonomy</h3>

                <div class="form-group">
                    <label class="form-label" for="category_id">Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="tags">Tags (Comma Separated)</label>
                    <input type="text" id="tags" name="tags" value="<?= e($tagsString) ?>" class="form-control" placeholder="Git, CI/CD, Deployment">
                    <small style="color: var(--c-text-dim); font-size: 11px; margin-top: 4px; display: block;">New tags are created automatically.</small>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="margin-bottom: 16px;">Cover Image</h3>

                <?php if ($coverImage): ?>
                    <div style="margin-bottom: 16px; border-radius: var(--r-sm); overflow: hidden; border: 1px solid var(--c-border);">
                        <img src="../storage/uploads/<?= e($coverImage) ?>" alt="Cover" style="width: 100%; height: 140px; object-fit: cover;">
                        <label style="display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: var(--c-bg-elevated); cursor: pointer; font-size: 12px; color: #ff7b72;">
                            <input type="checkbox" name="remove_cover" value="1">
                            <span>Remove this image</span>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="cover_image">Upload New Cover</label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp" class="form-control" style="padding: 6px; font-size: 12px;">
                    <small style="color: var(--c-text-dim); font-size: 11px; margin-top: 4px; display: block;">Max 5MB (JPG, PNG, WebP).</small>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Live Preview Modal -->
<div id="previewModal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 10000; align-items: center; justify-content: center; padding: 24px;">
    <div style="background: var(--c-bg-surface); border: 1px solid var(--c-border); border-radius: 12px; width: 100%; max-width: 880px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 16px 48px rgba(0,0,0,0.8);">
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--c-border); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-family: var(--f-mono); font-size: 12px; color: var(--c-accent); text-transform: uppercase;">
                <i class="ri-eye-line"></i> Article Live Preview
            </div>
            <button type="button" id="closePreviewBtn" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">
                <i class="ri-close-line"></i> Close
            </button>
        </div>
        <div id="previewModalBody" style="padding: 32px; overflow-y: auto; color: #e8e8e8; line-height: 1.8;">
            <!-- Rendered preview output -->
        </div>
    </div>
</div>

<script>
(function() {
    // 1. Auto slug generator when creating a new post
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    var manualSlugEdited = <?= !empty($slug) ? 'true' : 'false' ?>;

    if (slugInput) {
        slugInput.addEventListener('input', function() {
            manualSlugEdited = true;
        });
    }

    if (titleInput && slugInput && !manualSlugEdited) {
        titleInput.addEventListener('input', function() {
            if (!manualSlugEdited) {
                var s = this.value.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .trim()
                    .replace(/[\s_-]+/g, '-');
                slugInput.value = s;
            }
        });
    }

    // 2. Toolbar snippet inserter
    var contentArea = document.getElementById('content');
    document.querySelectorAll('.snippet-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var snippet = this.getAttribute('data-insert');
            if (!contentArea) return;

            var start = contentArea.selectionStart;
            var end = contentArea.selectionEnd;
            var text = contentArea.value;

            contentArea.value = text.substring(0, start) + "\n\n" + snippet + "\n\n" + text.substring(end);
            contentArea.focus();
            contentArea.selectionStart = contentArea.selectionEnd = start + snippet.length + 4;
        });
    });

    // 3. Live Preview Modal
    var previewBtn = document.getElementById('previewModalBtn');
    var modal = document.getElementById('previewModal');
    var closeBtn = document.getElementById('closePreviewBtn');
    var modalBody = document.getElementById('previewModalBody');

    if (previewBtn && modal && modalBody) {
        previewBtn.addEventListener('click', function() {
            var t = document.getElementById('title').value || 'Untitled Note';
            var ex = document.getElementById('excerpt').value || '';
            var c = document.getElementById('content').value || '';

            // Simple preview transform
            var html = '<h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 12px; color: #fff;">' + escapeHtml(t) + '</h1>';
            if (ex) {
                html += '<div style="font-size: 16px; color: var(--c-text-muted); border-left: 2px solid var(--c-accent); padding-left: 14px; margin-bottom: 24px;">' + escapeHtml(ex) + '</div>';
            }
            html += '<hr style="border: 0; border-top: 1px solid var(--c-border); margin: 20px 0;">';
            html += '<div style="white-space: pre-wrap; font-family: sans-serif;">' + escapeHtml(c) + '</div>';

            modalBody.innerHTML = html;
            modal.style.display = 'flex';
        });

        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
