<?php
/**
 * DEVZOOP Dynamic Public Blog Article View
 * Renders database-driven article content, rich code editors,
 * table of contents, reading progress, and SEO JSON-LD schema.
 */

declare(strict_types=1);

define('DEVZOOP_ROOT', dirname(__DIR__));
require_once DEVZOOP_ROOT . '/includes/config.php';
require_once DEVZOOP_ROOT . '/includes/database.php';
require_once DEVZOOP_ROOT . '/includes/auth.php';
require_once DEVZOOP_ROOT . '/includes/functions.php';
require_once DEVZOOP_ROOT . '/includes/seo.php';

init_session();
$isAdmin = is_admin_logged_in();

$slug = trim((string)($_GET['slug'] ?? ''));

// Clean URL fallback if slug passed via path
if (empty($slug)) {
    $pathParts = explode('/', trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));
    $lastPart = end($pathParts);
    if ($lastPart && $lastPart !== 'post.php' && $lastPart !== 'blog' && $lastPart !== 'index.php') {
        $slug = $lastPart;
    }
}

$article = null;
$category = null;
$tags = [];
$prevArticle = null;
$nextArticle = null;
$relatedArticles = [];
$headings = [];

if (!empty($slug) && db_is_available()) {
    try {
        $db = get_db();

        // Allow logged-in admin to preview draft or archived articles
        $statusCondition = $isAdmin ? '1=1' : 'p.status = "published"';

        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM posts p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.slug = :slug AND {$statusCondition}
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
        $article = $stmt->fetch();

        if ($article) {
            // Fetch tags
            $tagsStmt = $db->prepare('
                SELECT t.id, t.name, t.slug
                FROM tags t
                INNER JOIN post_tags pt ON t.id = pt.tag_id
                WHERE pt.post_id = :post_id
            ');
            $tagsStmt->execute([':post_id' => $article['id']]);
            $tags = $tagsStmt->fetchAll();

            // Extract headings for Table of Contents
            $headings = extract_headings((string)$article['content']);

            // Fetch adjacent articles (published only)
            $prevStmt = $db->prepare('
                SELECT title, slug 
                FROM posts 
                WHERE status = "published" AND published_at < :pub_at 
                ORDER BY published_at DESC 
                LIMIT 1
            ');
            $prevStmt->execute([':pub_at' => $article['published_at'] ?? $article['created_at']]);
            $prevArticle = $prevStmt->fetch();

            $nextStmt = $db->prepare('
                SELECT title, slug 
                FROM posts 
                WHERE status = "published" AND published_at > :pub_at 
                ORDER BY published_at ASC 
                LIMIT 1
            ');
            $nextStmt->execute([':pub_at' => $article['published_at'] ?? $article['created_at']]);
            $nextArticle = $nextStmt->fetch();

            // Fetch related articles (same category, published only)
            $relStmt = $db->prepare('
                SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.published_at, c.name as category_name
                FROM posts p
                INNER JOIN categories c ON p.category_id = c.id
                WHERE p.status = "published" AND p.category_id = :cat_id AND p.id != :post_id
                ORDER BY p.published_at DESC
                LIMIT 2
            ');
            $relStmt->execute([
                ':cat_id'  => $article['category_id'],
                ':post_id' => $article['id']
            ]);
            $relatedArticles = $relStmt->fetchAll();
        }

    } catch (Throwable $e) {
        error_log('[DEVZOOP POST VIEW ERROR] ' . $e->getMessage());
    }
}

// 404 Handler if article does not exist or is unpublished
if (!$article) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($article): ?>
        <?= render_seo_tags([
            'title'       => !empty($article['meta_title']) ? $article['meta_title'] : $article['title'],
            'description' => !empty($article['meta_description']) ? $article['meta_description'] : $article['excerpt'],
            'canonical'   => BASE_URL . '/blog/' . urlencode($article['slug']),
            'type'        => 'article',
            'image'       => !empty($article['cover_image']) ? BASE_URL . '/storage/uploads/' . $article['cover_image'] : BASE_URL . '/assets/img/rahul-hero.jpg',
            'article'     => $article
        ]) ?>
    <?php else: ?>
        <title>Article Not Found — DEVZOOP Blog</title>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <link href="../assets/remixIcon/fonts/remixicon.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="blog.css?v=2">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Reading Progress Indicator -->
    <div class="article-reading-progress" aria-hidden="true">
        <div class="article-reading-progress-bar" id="articleReadingBar"></div>
    </div>

    <!-- Navigation -->
    <header id="site-header">
        <div class="header-inner">
            <a href="../" class="logo" aria-label="DEVZOOP Home">
                <span class="logo-text">DEV<span class="logo-accent">ZOOP</span></span>
                <span class="logo-dot"></span>
            </a>
            <nav id="main-nav" aria-label="Main navigation">
                <ul class="nav-links">
                    <li><a href="../#work" class="nav-link">Work</a></li>
                    <li><a href="../#about" class="nav-link">About</a></li>
                    <li><a href="index.php" class="nav-link active">Blog</a></li>
                    <li><a href="../#contact" class="nav-link">Contact</a></li>
                </ul>
                <a href="https://github.com/developer-rahul-mandal" target="_blank" rel="noopener noreferrer" class="nav-github" aria-label="GitHub Profile">
                    <i class="ri-github-fill"></i>
                    <span>GitHub</span>
                    <i class="ri-arrow-right-up-line"></i>
                </a>
            </nav>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </header>

    <main id="main-content">
        <?php if (!$article): ?>
            <!-- 404 Not Found -->
            <section class="blog-empty section" style="padding-top: calc(64px + var(--sp-2xl)); min-height: 70vh; display: flex; align-items: center; justify-content: center;">
                <div class="empty-state">
                    <div class="empty-icon"><i class="ri-file-unknow-line"></i></div>
                    <h1 class="empty-title" style="font-size: 1.8rem;">Article Not Found</h1>
                    <p class="empty-description">The requested article could not be located or may still be in draft mode.</p>
                    <a href="index.php" class="btn btn-outline">
                        <i class="ri-arrow-left-line"></i>
                        <span>Return to Blog</span>
                    </a>
                </div>
            </section>
        <?php else: ?>
            <div class="article-container">
                <!-- Breadcrumbs -->
                <nav class="article-breadcrumbs" aria-label="Breadcrumb">
                    <a href="../">DEVZOOP</a>
                    <span class="article-breadcrumbs-sep">/</span>
                    <a href="index.php">Blog</a>
                    <span class="article-breadcrumbs-sep">/</span>
                    <span class="article-breadcrumbs-current" aria-current="page"><?= e($article['title']) ?></span>
                </nav>

                <!-- Article Header -->
                <header class="article-header">
                    <div class="article-header-meta">
                        <span class="article-category-badge article-category-<?= e(strtolower(str_replace([' ', '&'], ['-', ''], $article['category_name']))) ?>">
                            <?= e($article['category_name']) ?>
                        </span>
                        <span><?= calculate_reading_time((string)$article['content']) ?></span>
                        <span>·</span>
                        <span class="demo-chip"><?= format_date($article['published_at']) ?></span>
                        <?php if ($article['status'] !== 'published'): ?>
                            <span class="badge badge-draft" style="margin-left: 6px;">Admin Preview (<?= e($article['status']) ?>)</span>
                        <?php endif; ?>
                    </div>

                    <h1 class="article-title"><?= e($article['title']) ?></h1>
                    <div class="article-lead"><?= e($article['excerpt']) ?></div>

                    <?php if (!empty($tags)): ?>
                        <div class="article-tags-row">
                            <?php foreach ($tags as $t): ?>
                                <span class="tag-pill">#<?= e($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($article['cover_image'])): ?>
                        <div style="margin-top: 24px; border-radius: var(--r-md); overflow: hidden; border: 1px solid var(--c-border); max-height: 400px;">
                            <img src="../storage/uploads/<?= e($article['cover_image']) ?>" alt="<?= e($article['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                </header>

                <!-- Two-Column Article Layout -->
                <div class="article-layout">
                    <!-- Main Reading Column -->
                    <div class="article-main-column">
                        <div class="article-body">
                            <?= render_article_content((string)$article['content']) ?>
                        </div>

                        <!-- Author Card -->
                        <div class="article-author-card">
                            <div class="author-avatar-wrap">
                                <img src="../assets/img/rahul-about.jpg" alt="Rahul Mandal" loading="lazy">
                            </div>
                            <div class="author-info">
                                <span class="author-name">Rahul Mandal</span>
                                <span class="author-role">Independent Web Developer</span>
                                <p class="author-bio">Notes from the build — documenting real-world web architecture, debugging discoveries, and deployment workflows as an independent developer.</p>
                            </div>
                        </div>

                        <!-- Prev / Next Navigation -->
                        <div class="article-nav-row">
                            <?php if ($prevArticle): ?>
                                <a href="post.php?slug=<?= urlencode($prevArticle['slug']) ?>" class="article-nav-card article-nav-prev">
                                    <span class="article-nav-direction"><i class="ri-arrow-left-line"></i> Previous Note</span>
                                    <span class="article-nav-title"><?= e($prevArticle['title']) ?></span>
                                </a>
                            <?php else: ?>
                                <div class="article-nav-card article-nav-prev disabled">
                                    <span class="article-nav-direction">Start of Journal</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($nextArticle): ?>
                                <a href="post.php?slug=<?= urlencode($nextArticle['slug']) ?>" class="article-nav-card article-nav-next">
                                    <span class="article-nav-direction">Next Note <i class="ri-arrow-right-line"></i></span>
                                    <span class="article-nav-title"><?= e($nextArticle['title']) ?></span>
                                </a>
                            <?php else: ?>
                                <div class="article-nav-card article-nav-next disabled">
                                    <span class="article-nav-direction">Latest Note</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Related Articles -->
                        <?php if (!empty($relatedArticles)): ?>
                            <div class="article-related-section">
                                <div class="related-section-header">
                                    <h3 class="related-title">Related Notes</h3>
                                    <a href="index.php" class="btn btn-outline">
                                        <span>Explore All Notes</span>
                                        <i class="ri-arrow-right-up-line"></i>
                                    </a>
                                </div>
                                <div class="related-grid">
                                    <?php foreach ($relatedArticles as $rel): ?>
                                        <article class="article-card">
                                            <div class="article-card-tech-banner">
                                                <span class="article-code-glyph" aria-hidden="true"><?= e(strtoupper(substr($rel['category_name'], 0, 4))) ?>//</span>
                                                <span class="article-category-badge article-category-<?= e(strtolower(str_replace([' ', '&'], ['-', ''], $rel['category_name']))) ?>">
                                                    <?= e($rel['category_name']) ?>
                                                </span>
                                            </div>
                                            <div class="article-card-body">
                                                <div class="article-meta-line">
                                                    <span><?= calculate_reading_time((string)$rel['content']) ?></span>
                                                    <span class="demo-chip"><?= format_date($rel['published_at']) ?></span>
                                                </div>
                                                <h4 class="article-card-title">
                                                    <a href="post.php?slug=<?= urlencode($rel['slug']) ?>">
                                                        <?= e($rel['title']) ?>
                                                    </a>
                                                </h4>
                                                <p class="article-card-excerpt"><?= e($rel['excerpt']) ?></p>
                                            </div>
                                            <div class="article-card-footer">
                                                <a href="post.php?slug=<?= urlencode($rel['slug']) ?>" class="article-card-link">
                                                    <span>Read Note</span>
                                                    <i class="ri-arrow-right-line"></i>
                                                </a>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sticky Table of Contents Sidebar -->
                    <?php if (!empty($headings)): ?>
                        <aside class="article-toc-sidebar" aria-label="Table of contents">
                            <div class="toc-title">
                                <i class="ri-list-check-2"></i>
                                <span>On This Page</span>
                            </div>
                            <nav class="toc-list">
                                <?php foreach ($headings as $h): ?>
                                    <a href="#<?= e($h['id']) ?>" class="toc-link"><?= e($h['text']) ?></a>
                                <?php endforeach; ?>
                            </nav>
                        </aside>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer id="site-footer">
        <div class="footer-top">
            <div class="footer-brand">
                <span class="logo-text">DEV<span class="logo-accent">ZOOP</span></span>
                <p class="footer-tagline">Building useful things for the web.</p>
            </div>
            <nav class="footer-nav" aria-label="Footer navigation">
                <a href="../#work">Work</a>
                <a href="../#about">About</a>
                <a href="index.php">Blog</a>
                <a href="../#contact">Contact</a>
            </nav>
            <div class="footer-socials">
                <a href="https://github.com/developer-rahul-mandal" target="_blank" rel="noopener noreferrer" aria-label="GitHub"><i class="ri-github-fill"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="ri-linkedin-box-fill"></i></a>
                <a href="#" aria-label="Reddit"><i class="ri-reddit-line"></i></a>
                <a href="#" aria-label="X"><i class="ri-twitter-x-line"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="footer-copy">DEVZOOP &copy; <?= date('Y') ?></p>
            <button class="back-to-top" id="backToTop" aria-label="Back to top">
                <i class="ri-arrow-up-line"></i>
                <span>Top</span>
            </button>
            <p class="footer-signoff">Built with code &amp; coffee. <i class="ri-cup-fill"></i></p>
        </div>
    </footer>

    <script src="blog.js?v=2"></script>
</body>
</html>
