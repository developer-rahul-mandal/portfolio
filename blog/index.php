<?php
/**
 * DEVZOOP Dynamic Public Blog Listing
 * Server-side search, category filtering, featured article, and pagination.
 */

declare(strict_types=1);

define('DEVZOOP_ROOT', dirname(__DIR__));
require_once DEVZOOP_ROOT . '/includes/config.php';
require_once DEVZOOP_ROOT . '/includes/database.php';
require_once DEVZOOP_ROOT . '/includes/functions.php';
require_once DEVZOOP_ROOT . '/includes/seo.php';

$dbAvailable = db_is_available();
$categories = [];
$featured = null;
$articles = [];
$totalMatching = 0;
$totalPages = 1;

$searchQuery = trim((string)($_GET['q'] ?? ''));
$categorySlug = trim((string)($_GET['category'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

if ($dbAvailable) {
    try {
        $db = get_db();

        // 1. Fetch categories for filter tabs
        $catStmt = $db->query('
            SELECT c.id, c.name, c.slug, COUNT(p.id) as post_count
            FROM categories c
            LEFT JOIN posts p ON c.id = p.category_id AND p.status = "published"
            GROUP BY c.id
            HAVING post_count > 0
            ORDER BY c.name ASC
        ');
        $categories = $catStmt->fetchAll();

        // 2. Fetch Featured article (only when not searching or filtering by category on page 1)
        if ($searchQuery === '' && $categorySlug === '' && $page === 1) {
            $featStmt = $db->query('
                SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM posts p
                INNER JOIN categories c ON p.category_id = c.id
                WHERE p.status = "published" AND p.is_featured = 1
                ORDER BY p.published_at DESC
                LIMIT 1
            ');
            $featured = $featStmt->fetch();
        }

        // 3. Build query for latest articles list
        $where = ['p.status = "published"'];
        $params = [];

        // Exclude featured article from the general list on page 1
        if ($featured) {
            $where[] = 'p.id != :featured_id';
            $params[':featured_id'] = (int)$featured['id'];
        }

        if ($categorySlug !== '') {
            $where[] = 'c.slug = :c_slug';
            $params[':c_slug'] = $categorySlug;
        }

        if ($searchQuery !== '') {
            $where[] = '(p.title LIKE :q OR p.excerpt LIKE :q OR p.content LIKE :q)';
            $params[':q'] = '%' . $searchQuery . '%';
        }

        $whereSql = implode(' AND ', $where);

        // Count matching articles
        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM posts p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalMatching = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalMatching / $perPage));

        // Fetch paginated articles
        $query = "
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM posts p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE {$whereSql}
            ORDER BY p.published_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();

        // Attach tags to articles
        if (!empty($articles)) {
            $postIds = array_column($articles, 'id');
            if ($featured) {
                $postIds[] = (int)$featured['id'];
            }
            $inClause = implode(',', array_map('intval', $postIds));
            $tagsStmt = $db->query("
                SELECT pt.post_id, t.name, t.slug
                FROM post_tags pt
                INNER JOIN tags t ON pt.tag_id = t.id
                WHERE pt.post_id IN ({$inClause})
            ");
            $tagsByPost = [];
            while ($row = $tagsStmt->fetch()) {
                $tagsByPost[$row['post_id']][] = $row;
            }

            foreach ($articles as &$art) {
                $art['tags'] = $tagsByPost[$art['id']] ?? [];
            }
            unset($art);

            if ($featured) {
                $featured['tags'] = $tagsByPost[$featured['id']] ?? [];
            }
        }

    } catch (Throwable $e) {
        error_log('[DEVZOOP PUBLIC BLOG ERROR] ' . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= render_seo_tags([
        'title'       => 'Blog — Notes from the Build',
        'description' => 'Developer learning journal by Rahul Mandal — real projects, architectural decisions, and debugging discoveries.',
        'canonical'   => BASE_URL . '/blog/'
    ]) ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <link href="../assets/remixIcon/fonts/remixicon.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="blog.css?v=2">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Progress Bar -->
    <div class="progress-container" aria-hidden="true">
        <div class="progress-bar" id="myProgressBar"></div>
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
                    <li><a href="./" class="nav-link active">Blog</a></li>
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

        <!-- ═══════════════════════════════════════════════ -->
        <!-- BLOG HERO                                       -->
        <!-- ═══════════════════════════════════════════════ -->
        <section class="blog-hero section">
            <div class="blog-hero-grid" aria-hidden="true"></div>
            <div class="blog-hero-content">
                <div class="blog-hero-text">
                    <span class="section-label">01 / Notes from the Build</span>
                    <h1 class="blog-hero-headline">
                        Things I learn<br>
                        <span class="headline-accent">while building.</span>
                    </h1>
                    <p class="blog-hero-description">
                        Experiments, debugging sessions, technical decisions, and lessons from building real projects on the web.
                    </p>
                </div>
                <div class="blog-hero-meta-panel">
                    <div class="meta-panel-item">
                        <span class="meta-panel-label">Author</span>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <img src="../assets/img/rahul-about.jpg" alt="Rahul Mandal" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1px solid var(--c-accent);">
                            <span class="meta-panel-value" style="font-size: 0.85rem;">Rahul Mandal</span>
                        </div>
                    </div>
                    <div class="meta-panel-divider"></div>
                    <div class="meta-panel-item">
                        <span class="meta-panel-label">Type</span>
                        <span class="meta-panel-value">Developer Journal</span>
                    </div>
                    <div class="meta-panel-divider"></div>
                    <div class="meta-panel-item">
                        <span class="meta-panel-label">Categories</span>
                        <div class="meta-panel-tags">
                            <span class="blog-category blog-category-build">BUILD</span>
                            <span class="blog-category blog-category-learn">LEARN</span>
                            <span class="blog-category blog-category-break">BREAK</span>
                        </div>
                    </div>
                    <div class="meta-panel-divider"></div>
                    <div class="meta-panel-item">
                        <span class="meta-panel-label">Format</span>
                        <span class="meta-panel-value">Field Notes</span>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!$dbAvailable): ?>
            <!-- Fallback Alert if MySQL is not configured -->
            <section class="section" style="padding-top: 0;">
                <div class="empty-state" style="border-color: rgba(255,92,31,0.4); background: rgba(255,92,31,0.04);">
                    <div class="empty-icon"><i class="ri-database-2-line"></i></div>
                    <h3 class="empty-title">Database Not Initialized</h3>
                    <p class="empty-description">The database is currently disconnected or unmigrated. Please configure your database in <code>.env</code> and run the installer to seed real articles.</p>
                    <a href="../install.php" class="btn btn-primary">
                        <i class="ri-flashlight-line"></i>
                        <span>Run Platform Installer</span>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════ -->
        <!-- FEATURED ARTICLE                                -->
        <!-- ═══════════════════════════════════════════════ -->
        <?php if ($featured): ?>
            <section class="blog-featured section">
                <div class="featured-card">
                    <div class="featured-content">
                        <div>
                            <span class="featured-badge">
                                <i class="ri-flashlight-line"></i> Featured Entry
                            </span>
                            <span class="article-category-badge article-category-<?= e(strtolower(str_replace([' ', '&'], ['-', ''], $featured['category_name']))) ?>" style="margin-left: 8px;">
                                <?= e($featured['category_name']) ?>
                            </span>
                            <h2 class="featured-title">
                                <a href="post.php?slug=<?= urlencode($featured['slug']) ?>">
                                    <?= e($featured['title']) ?>
                                </a>
                            </h2>
                            <p class="featured-excerpt"><?= e($featured['excerpt']) ?></p>
                            <div class="featured-tags">
                                <?php foreach ($featured['tags'] ?? [] as $t): ?>
                                    <span class="tag-pill">#<?= e($t['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="featured-footer">
                            <div class="featured-meta">
                                <span><?= calculate_reading_time($featured['content']) ?></span>
                                <span class="featured-meta-dot"></span>
                                <span class="demo-chip"><?= format_date($featured['published_at']) ?></span>
                            </div>
                            <a href="post.php?slug=<?= urlencode($featured['slug']) ?>" class="btn btn-primary">
                                <span>Read Article</span>
                                <i class="ri-arrow-right-up-line"></i>
                            </a>
                        </div>
                    </div>
                    <div class="featured-visual" aria-hidden="true">
                        <div class="workflow-panel">
                            <div class="workflow-header">
                                <div class="workflow-dots"><span></span><span></span><span></span></div>
                                <span>workflow://deploy-pipeline</span>
                            </div>
                            <div class="workflow-body">
                                <div class="workflow-step done">
                                    <i class="ri-git-pull-request-line"></i>
                                    <span class="workflow-step-name">PR #4 Closed (Merged)</span>
                                    <span class="workflow-step-status">success</span>
                                </div>
                                <div class="workflow-step done">
                                    <i class="ri-shield-check-line"></i>
                                    <span class="workflow-step-name">CI: Syntax &amp; Integrity</span>
                                    <span class="workflow-step-status">passed</span>
                                </div>
                                <div class="workflow-step active">
                                    <i class="ri-upload-cloud-2-line"></i>
                                    <span class="workflow-step-name">CD: FTPS Release Sync</span>
                                    <span class="workflow-step-status">live</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════ -->
        <!-- SEARCH & FILTERS (Server-Side)                  -->
        <!-- ═══════════════════════════════════════════════ -->
        <section class="blog-controls section">
            <form method="GET" action="index.php">
                <div class="blog-search-wrap">
                    <label for="blog-search" class="sr-only">Search articles</label>
                    <i class="ri-search-line blog-search-icon" aria-hidden="true"></i>
                    <input type="search" id="blog-search" name="q" value="<?= e($searchQuery) ?>" class="blog-search-input" placeholder="Search technical notes by keyword, code, topic..." autocomplete="off">
                    <?php if ($categorySlug !== ''): ?>
                        <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
                    <?php endif; ?>
                </div>
            </form>

            <div class="blog-filters" role="tablist" aria-label="Filter articles by category">
                <a href="index.php<?= $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : '' ?>" 
                   class="blog-filter <?= $categorySlug === '' ? 'active' : '' ?>" role="tab">
                    All Notes
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?= urlencode($cat['slug']) ?><?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>" 
                       class="blog-filter <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>" role="tab">
                        <?= e($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="blog-results-info">
                <?php if ($searchQuery !== '' || $categorySlug !== ''): ?>
                    Showing <?= count($articles) ?> of <?= $totalMatching ?> matching articles
                    <a href="index.php" style="color: var(--c-accent); margin-left: 8px;">[Clear filters]</a>
                <?php else: ?>
                    Latest published notes from the engineering studio
                <?php endif; ?>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════ -->
        <!-- ARTICLES GRID                                   -->
        <!-- ═══════════════════════════════════════════════ -->
        <section class="blog-listing section">
            <?php if (empty($articles)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="ri-search-eye-line"></i></div>
                    <h3 class="empty-title">No matching notes found</h3>
                    <p class="empty-description">Try a different search keyword or category filter.</p>
                    <a href="index.php" class="btn btn-outline">
                        <i class="ri-refresh-line"></i>
                        <span>Reset All Filters</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="articles-grid">
                    <?php foreach ($articles as $art): ?>
                        <article class="article-card">
                            <div class="article-card-tech-banner">
                                <span class="article-code-glyph" aria-hidden="true"><?= e(strtoupper(substr($art['category_name'], 0, 4))) ?>//</span>
                                <span class="article-category-badge article-category-<?= e(strtolower(str_replace([' ', '&'], ['-', ''], $art['category_name']))) ?>">
                                    <?= e($art['category_name']) ?>
                                </span>
                            </div>
                            <div class="article-card-body">
                                <div class="article-meta-line">
                                    <span><?= calculate_reading_time($art['content']) ?></span>
                                    <span class="demo-chip"><?= format_date($art['published_at']) ?></span>
                                </div>
                                <h3 class="article-card-title">
                                    <a href="post.php?slug=<?= urlencode($art['slug']) ?>">
                                        <?= e($art['title']) ?>
                                    </a>
                                </h3>
                                <p class="article-card-excerpt"><?= e($art['excerpt']) ?></p>
                                <div class="article-card-tags">
                                    <?php foreach ($art['tags'] ?? [] as $t): ?>
                                        <span class="tag-pill">#<?= e($t['name']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="article-card-footer">
                                <a href="post.php?slug=<?= urlencode($art['slug']) ?>" class="article-card-link">
                                    <span>Read Note</span>
                                    <i class="ri-arrow-right-line"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--c-border);">
                        <div style="font-family: var(--f-mono); font-size: 12px; color: var(--c-text-dim);">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <?php if ($page > 1): ?>
                                <a href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline" style="padding: 8px 14px; font-size: 12px;">
                                    <i class="ri-arrow-left-line"></i> Newer Notes
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline" style="padding: 8px 14px; font-size: 12px;">
                                    Older Notes <i class="ri-arrow-right-line"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ═══════════════════════════════════════════════ -->
        <!-- FOLLOW CTA                                      -->
        <!-- ═══════════════════════════════════════════════ -->
        <section class="blog-follow section">
            <div class="follow-panel">
                <div class="follow-text">
                    <h3 class="follow-title">Want to follow along?</h3>
                    <p class="follow-description">New notes drop as I ship projects and solve problems. Reach out or follow on GitHub to stay in the loop.</p>
                </div>
                <div class="follow-actions">
                    <a href="https://github.com/developer-rahul-mandal" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                        <i class="ri-github-fill"></i>
                        <span>Follow on GitHub</span>
                    </a>
                    <a href="../#contact" class="btn btn-secondary">
                        <span>Get in Touch</span>
                        <i class="ri-arrow-right-up-line"></i>
                    </a>
                </div>
            </div>
        </section>

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
                <a href="./">Blog</a>
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
