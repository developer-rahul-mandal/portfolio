/* ═══════════════════════════════════════════════════════════
   DEVZOOP — Blog Controller
   Listing page filtering/search and comprehensive article renderer
   with syntax-highlighted code editors, table of contents,
   reading progress, and workflow visuals.
   ═══════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  /* ── Shared UI Setup ────────────────────────────────── */
  function setupSharedUI() {
    // 1. Sticky Header
    var header = document.getElementById('site-header');
    function updateHeader() {
      if (!header) return;
      if (window.scrollY > 60) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    }

    // 2. Mobile Navigation
    var navToggle = document.getElementById('navToggle');
    var mainNav = document.getElementById('main-nav');
    if (navToggle && mainNav) {
      var navOverlay = document.createElement('div');
      navOverlay.classList.add('nav-overlay');
      document.body.appendChild(navOverlay);

      function openNav() {
        navToggle.classList.add('active');
        navToggle.setAttribute('aria-expanded', 'true');
        mainNav.classList.add('active');
        navOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
      }

      function closeNav() {
        navToggle.classList.remove('active');
        navToggle.setAttribute('aria-expanded', 'false');
        mainNav.classList.remove('active');
        navOverlay.classList.remove('active');
        document.body.style.overflow = '';
      }

      navToggle.addEventListener('click', function () {
        if (mainNav.classList.contains('active')) {
          closeNav();
        } else {
          openNav();
        }
      });

      navOverlay.addEventListener('click', closeNav);

      mainNav.querySelectorAll('.nav-link').forEach(function (link) {
        link.addEventListener('click', closeNav);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && mainNav.classList.contains('active')) {
          closeNav();
        }
      });
    }

    // 3. Back to Top
    var backToTop = document.getElementById('backToTop');
    if (backToTop) {
      backToTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    // 4. Dynamic Footer Year
    var footerYear = document.getElementById('footer-year');
    if (footerYear) {
      footerYear.textContent = new Date().getFullYear();
    }

    // Scroll listener for sticky header
    window.addEventListener('scroll', updateHeader, { passive: true });
    updateHeader();
  }

  /* ── Safe HTML Escaper ──────────────────────────────── */
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /* ── Category Class Helper ──────────────────────────── */
  function getCategoryClass(category) {
    var c = (category || '').toUpperCase();
    if (c.indexOf('GIT') !== -1 || c.indexOf('CI') !== -1) return 'article-category-git';
    if (c === 'LEARN') return 'article-category-learn';
    if (c === 'BREAK') return 'article-category-break';
    return 'article-category-build';
  }

  /* ── Category Code Glyph Helper ─────────────────────── */
  function getCategoryGlyph(category) {
    var c = (category || '').toUpperCase();
    if (c.indexOf('GIT') !== -1 || c.indexOf('CI') !== -1) return 'git://';
    if (c === 'LEARN') return '[] => {}';
    if (c === 'BREAK') return '500 ERROR';
    return '<?php';
  }

  /* ── Lightweight Syntax Highlighting Tokenizer ──────── */
  function highlightCode(code, lang) {
    var safe = escapeHtml(code);
    var l = (lang || '').toLowerCase();

    if (l === 'yaml' || l === 'yml') {
      // Comments
      safe = safe.replace(/(#[^\n]*)/g, '<span class="tok-comment">$1</span>');
      // Keys before colon
      safe = safe.replace(/^(\s*)([a-zA-Z0-9_-]+)(:)/gm, '$1<span class="tok-keyword">$2</span>$3');
      // Strings in quotes
      safe = safe.replace(/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/g, '<span class="tok-string">$1</span>');
      // Boolean / numbers
      safe = safe.replace(/\b(true|false|null|[0-9]+)\b/g, '<span class="tok-number">$1</span>');
      return safe;
    }

    if (l === 'bash' || l === 'sh') {
      // Comments
      safe = safe.replace(/(#[^\n]*)/g, '<span class="tok-comment">$1</span>');
      // Command keywords
      safe = safe.replace(/\b(git|checkout|status|commit|push|pull|run|test|find|echo|grep)\b/g, '<span class="tok-keyword">$1</span>');
      // Flags
      safe = safe.replace(/(\s)(-[a-zA-Z0-9_-]+)/g, '$1<span class="tok-variable">$2</span>');
      // Strings
      safe = safe.replace(/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/g, '<span class="tok-string">$1</span>');
      return safe;
    }

    if (l === 'javascript' || l === 'js') {
      // Single line comments
      safe = safe.replace(/(\/\/[^\n]*)/g, '<span class="tok-comment">$1</span>');
      // Keywords
      safe = safe.replace(/\b(var|let|const|function|return|if|else|for|while|import|export|class|new)\b/g, '<span class="tok-keyword">$1</span>');
      // Builtins & methods
      safe = safe.replace(/\b(map|filter|reduce|push|indexOf|forEach|log)\b/g, '<span class="tok-property">$1</span>');
      // Strings
      safe = safe.replace(/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/g, '<span class="tok-string">$1</span>');
      // Numbers
      safe = safe.replace(/\b([0-9]+)\b/g, '<span class="tok-number">$1</span>');
      return safe;
    }

    if (l === 'php') {
      // PHP tags
      safe = safe.replace(/(&lt;\?php|\?&gt;)/g, '<span class="tok-keyword">$1</span>');
      // Comments
      safe = safe.replace(/(\/\/[^\n]*|#[^\n]*)/g, '<span class="tok-comment">$1</span>');
      // Variables ($var)
      safe = safe.replace(/(\$[a-zA-Z0-9_]+)/g, '<span class="tok-variable">$1</span>');
      // Keywords
      safe = safe.replace(/\b(class|public|private|protected|function|return|new|array|int|string|bool)\b/g, '<span class="tok-keyword">$1</span>');
      // Strings
      safe = safe.replace(/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/g, '<span class="tok-string">$1</span>');
      return safe;
    }

    return safe;
  }

  /* ── Code Block Generator with Line Numbers ─────────── */
  function buildCodeEditor(filename, language, code) {
    var rawLines = (code || '').split('\n');
    var gutterHtml = '';
    for (var i = 1; i <= rawLines.length; i++) {
      gutterHtml += '<span>' + i + '</span>';
    }

    var highlighted = highlightCode(code, language);
    var displayFile = filename || (language ? 'snippet.' + language : 'code-snippet.txt');
    var displayLang = language || 'code';

    return (
      '<div class="code-editor-block">' +
        '<div class="code-editor-header">' +
          '<div class="code-editor-file">' +
            '<i class="ri-file-code-line"></i>' +
            '<span>' + escapeHtml(displayFile) + '</span>' +
          '</div>' +
          '<div class="code-editor-actions">' +
            '<span class="code-lang-badge">' + escapeHtml(displayLang) + '</span>' +
            '<button type="button" class="code-copy-btn" aria-label="Copy code to clipboard">' +
              '<i class="ri-file-copy-line"></i>' +
              '<span>Copy</span>' +
            '</button>' +
          '</div>' +
        '</div>' +
        '<div class="code-editor-body">' +
          '<div class="code-gutter" aria-hidden="true">' + gutterHtml + '</div>' +
          '<pre class="code-lines-content"><code>' + highlighted + '</code></pre>' +
        '</div>' +
      '</div>'
    );
  }

  /* ── Copy Code to Clipboard ─────────────────────────── */
  function setupCodeCopyButtons() {
    document.querySelectorAll('.code-copy-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        var block = this.closest('.code-editor-block');
        if (!block) return;
        var codeEl = block.querySelector('.code-lines-content code');
        if (!codeEl) return;

        var text = codeEl.innerText || codeEl.textContent;

        function showSuccess() {
          button.classList.add('copied');
          button.innerHTML = '<i class="ri-check-line"></i><span>Copied!</span>';
          setTimeout(function () {
            button.classList.remove('copied');
            button.innerHTML = '<i class="ri-file-copy-line"></i><span>Copy</span>';
          }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(showSuccess).catch(function () {
            fallbackCopy(text, showSuccess);
          });
        } else {
          fallbackCopy(text, showSuccess);
        }
      });
    });
  }

  function fallbackCopy(text, callback) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      if (callback) callback();
    } catch (err) {
      console.warn('Clipboard copy fallback failed:', err);
    }
    document.body.removeChild(ta);
  }

  /* ═══════════════════════════════════════════════════════
     BLOG LISTING PAGE
     ═══════════════════════════════════════════════════════ */
  function initListingPage() {
    var listingContainer = document.getElementById('blog-listing');
    if (!listingContainer) return; // Not on listing page

    var featuredContainer = document.getElementById('blog-featured');
    var emptyContainer = document.getElementById('blog-empty');
    var resultsInfo = document.getElementById('blog-results-info');
    var searchInput = document.getElementById('blog-search');
    var searchClear = document.getElementById('blog-search-clear');
    var filterButtons = document.querySelectorAll('.blog-filter');
    var resetButton = document.getElementById('blog-reset');

    var currentCategory = 'ALL NOTES';
    var currentQuery = '';

    // Render Featured Article
    var featured = DEVZOOP_BLOG.getFeatured();
    if (featured && featuredContainer) {
      var tagsHtml = featured.tags.map(function (tag) {
        return '<span class="tag-pill">#' + escapeHtml(tag) + '</span>';
      }).join('');

      featuredContainer.innerHTML = 
        '<div class="featured-card">' +
          '<div class="featured-content">' +
            '<div>' +
              '<span class="featured-badge">' +
                '<i class="ri-flashlight-line"></i> Featured Entry' +
              '</span>' +
              '<span class="article-category-badge ' + getCategoryClass(featured.category) + '" style="margin-left: 8px;">' +
                escapeHtml(featured.category) +
              '</span>' +
              '<h2 class="featured-title">' +
                '<a href="post.html?slug=' + encodeURIComponent(featured.slug) + '">' +
                  escapeHtml(featured.title) +
                '</a>' +
              '</h2>' +
              '<p class="featured-excerpt">' + escapeHtml(featured.excerpt) + '</p>' +
              '<div class="featured-tags">' + tagsHtml + '</div>' +
            '</div>' +
            '<div class="featured-footer">' +
              '<div class="featured-meta">' +
                '<span>' + escapeHtml(featured.readingTime) + '</span>' +
                '<span class="featured-meta-dot"></span>' +
                '<span class="demo-chip">Sample Entry · Oct 2026</span>' +
              '</div>' +
              '<a href="post.html?slug=' + encodeURIComponent(featured.slug) + '" class="btn btn-primary">' +
                '<span>Read Article</span>' +
                '<i class="ri-arrow-right-up-line"></i>' +
              '</a>' +
            '</div>' +
          '</div>' +
          '<div class="featured-visual" aria-hidden="true">' +
            '<div class="workflow-panel">' +
              '<div class="workflow-header">' +
                '<div class="workflow-dots"><span></span><span></span><span></span></div>' +
                '<span>workflow://deploy-pipeline</span>' +
              '</div>' +
              '<div class="workflow-body">' +
                '<div class="workflow-step done">' +
                  '<i class="ri-git-pull-request-line"></i>' +
                  '<span class="workflow-step-name">PR #4 Closed (Merged)</span>' +
                  '<span class="workflow-step-status">success</span>' +
                '</div>' +
                '<div class="workflow-step done">' +
                  '<i class="ri-shield-check-line"></i>' +
                  '<span class="workflow-step-name">CI: Syntax & Integrity</span>' +
                  '<span class="workflow-step-status">passed</span>' +
                '</div>' +
                '<div class="workflow-step active">' +
                  '<i class="ri-upload-cloud-2-line"></i>' +
                  '<span class="workflow-step-name">CD: FTPS Release Sync</span>' +
                  '<span class="workflow-step-status">live</span>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    // Render Grid
    function renderArticles(articles) {
      if (!articles || articles.length === 0) {
        listingContainer.style.display = 'none';
        if (emptyContainer) emptyContainer.style.display = 'block';
        if (resultsInfo) resultsInfo.textContent = 'No matching notes found.';
        return;
      }

      if (emptyContainer) emptyContainer.style.display = 'none';
      listingContainer.style.display = 'block';

      var total = DEVZOOP_BLOG.getAll().length;
      if (resultsInfo) {
        resultsInfo.textContent = 'Showing ' + articles.length + ' of ' + total + ' developer notes';
      }

      var cardsHtml = articles.map(function (art) {
        var tagsHtml = art.tags.map(function (t) {
          return '<span class="tag-pill">#' + escapeHtml(t) + '</span>';
        }).join('');

        return (
          '<article class="article-card">' +
            '<div class="article-card-tech-banner">' +
              '<span class="article-code-glyph" aria-hidden="true">' + getCategoryGlyph(art.category) + '</span>' +
              '<span class="article-category-badge ' + getCategoryClass(art.category) + '">' +
                escapeHtml(art.category) +
              '</span>' +
            '</div>' +
            '<div class="article-card-body">' +
              '<div class="article-meta-line">' +
                '<span>' + escapeHtml(art.readingTime) + '</span>' +
                '<span class="demo-chip">Sample Note</span>' +
              '</div>' +
              '<h3 class="article-card-title">' +
                '<a href="post.html?slug=' + encodeURIComponent(art.slug) + '">' +
                  escapeHtml(art.title) +
                '</a>' +
              '</h3>' +
              '<p class="article-card-excerpt">' + escapeHtml(art.excerpt) + '</p>' +
              '<div class="article-card-tags">' + tagsHtml + '</div>' +
            '</div>' +
            '<div class="article-card-footer">' +
              '<a href="post.html?slug=' + encodeURIComponent(art.slug) + '" class="article-card-link">' +
                '<span>Read Note</span>' +
                '<i class="ri-arrow-right-line"></i>' +
              '</a>' +
            '</div>' +
          '</article>'
        );
      }).join('');

      listingContainer.innerHTML = '<div class="articles-grid">' + cardsHtml + '</div>';
    }

    function applyFilters() {
      var results = DEVZOOP_BLOG.search(currentQuery, currentCategory);
      renderArticles(results);
    }

    filterButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        filterButtons.forEach(function (b) {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        currentCategory = btn.getAttribute('data-category');
        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        currentQuery = this.value;
        if (searchClear) {
          searchClear.style.display = currentQuery.trim().length > 0 ? 'block' : 'none';
        }
        applyFilters();
      });
    }

    if (searchClear) {
      searchClear.addEventListener('click', function () {
        if (searchInput) {
          searchInput.value = '';
          currentQuery = '';
          searchClear.style.display = 'none';
          searchInput.focus();
          applyFilters();
        }
      });
    }

    if (resetButton) {
      resetButton.addEventListener('click', function () {
        currentCategory = 'ALL NOTES';
        currentQuery = '';
        if (searchInput) searchInput.value = '';
        if (searchClear) searchClear.style.display = 'none';
        filterButtons.forEach(function (b) {
          if (b.getAttribute('data-category') === 'ALL NOTES') {
            b.classList.add('active');
            b.setAttribute('aria-selected', 'true');
          } else {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
          }
        });
        applyFilters();
      });
    }

    applyFilters();
  }

  /* ═══════════════════════════════════════════════════════
     BLOG ARTICLE PAGE (blog/post.html)
     ═══════════════════════════════════════════════════════ */
  function initPostPage() {
    var articlePage = document.getElementById('article-page');
    var articleNotFound = document.getElementById('article-not-found');
    var articleBack = document.getElementById('article-back');

    if (!articlePage) return; // Not on post page

    // 1. Resolve Slug from Query Parameter or URL Path
    var slug = null;
    var params = new URLSearchParams(window.location.search);
    if (params.has('slug') && params.get('slug').trim()) {
      slug = params.get('slug').trim();
    } else {
      // Check clean URL fallback: /blog/{slug}/
      var pathParts = window.location.pathname.split('/').filter(Boolean);
      var lastPart = pathParts[pathParts.length - 1];
      if (lastPart && lastPart !== 'post.html' && lastPart !== 'blog' && lastPart !== 'index.html') {
        slug = lastPart;
      }
    }

    if (!slug) {
      showNotFound();
      return;
    }

    var article = DEVZOOP_BLOG.getBySlug(slug);
    if (!article) {
      showNotFound();
      return;
    }

    function showNotFound() {
      articlePage.style.display = 'none';
      if (articleBack) articleBack.style.display = 'none';
      if (articleNotFound) articleNotFound.style.display = 'block';
    }

    // 2. Update Page Head Metadata
    document.title = article.title + ' — DEVZOOP Developer Journal';
    var metaDesc = document.querySelector('meta[name="description"]');
    if (metaDesc) metaDesc.setAttribute('content', article.excerpt);
    var ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) ogTitle.setAttribute('content', article.title + ' — DEVZOOP');
    var ogDesc = document.querySelector('meta[property="og:description"]');
    if (ogDesc) ogDesc.setAttribute('content', article.excerpt);

    // 3. Generate Table of Contents items & Article Body
    var tocItems = [];
    var bodyHtml = '';

    (article.content || []).forEach(function (block) {
      if (block.type === 'heading') {
        var hid = block.id || block.text.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        tocItems.push({ id: hid, text: block.text });
        bodyHtml += '<h2 id="' + escapeHtml(hid) + '">' + escapeHtml(block.text) + '</h2>';
      } else if (block.type === 'paragraph') {
        bodyHtml += '<p>' + block.text + '</p>';
      } else if (block.type === 'list') {
        bodyHtml += '<ul>';
        (block.items || []).forEach(function (it) {
          bodyHtml += '<li>' + it + '</li>';
        });
        bodyHtml += '</ul>';
      } else if (block.type === 'callout') {
        bodyHtml += (
          '<div class="article-callout">' +
            '<div class="article-callout-header">' +
              '<i class="ri-lightbulb-line"></i>' +
              '<span>' + escapeHtml(block.title || 'Note') + '</span>' +
            '</div>' +
            '<p>' + escapeHtml(block.text) + '</p>' +
          '</div>'
        );
      } else if (block.type === 'code') {
        bodyHtml += buildCodeEditor(block.filename, block.language, block.text);
      } else if (block.type === 'terminal') {
        var cmdsHtml = (block.commands || []).map(function (c) {
          return (
            '<div class="terminal-row">' +
              '<div class="terminal-cmd-line">' +
                '<span class="terminal-prompt-char">$</span>' +
                '<span>' + escapeHtml(c.cmd) + '</span>' +
              '</div>' +
              (c.output ? '<div class="terminal-output-text">' + escapeHtml(c.output) + '</div>' : '') +
            '</div>'
          );
        }).join('');

        bodyHtml += (
          '<div class="terminal-block">' +
            '<div class="terminal-header">' +
              '<div class="terminal-controls"><span></span><span></span><span></span></div>' +
              '<span class="terminal-label">' + escapeHtml(block.filename || 'bash') + '</span>' +
            '</div>' +
            '<div class="terminal-content">' + cmdsHtml + '</div>' +
          '</div>'
        );
      } else if (block.type === 'workflow-diagram') {
        var stepsHtml = (block.steps || []).map(function (s) {
          var stepCls = s.status === 'done' ? 'done' : (s.status === 'active' ? 'active' : '');
          return (
            '<div class="pipeline-step-item ' + stepCls + '">' +
              '<div class="pipeline-step-icon"><i class="' + escapeHtml(s.icon || 'ri-checkbox-circle-line') + '"></i></div>' +
              '<div class="pipeline-step-text">' +
                '<span class="pipeline-step-label">' + escapeHtml(s.label) + '</span>' +
                '<span class="pipeline-step-desc">' + escapeHtml(s.desc) + '</span>' +
              '</div>' +
              '<span class="pipeline-step-state">' + escapeHtml(s.status) + '</span>' +
            '</div>'
          );
        }).join('');

        bodyHtml += (
          '<div class="workflow-diagram-block">' +
            '<div class="workflow-diagram-title">' + escapeHtml(block.title || 'Workflow Pipeline') + '</div>' +
            '<div class="workflow-pipeline-flow">' + stepsHtml + '</div>' +
          '</div>'
        );
      }
    });

    // 4. Build Table of Contents Sidebar HTML
    var tocHtml = '';
    if (tocItems.length > 0) {
      var linksHtml = tocItems.map(function (item) {
        return '<a href="#' + escapeHtml(item.id) + '" class="toc-link">' + escapeHtml(item.text) + '</a>';
      }).join('');

      tocHtml = (
        '<aside class="article-toc-sidebar" aria-label="Table of contents">' +
          '<div class="toc-title">' +
            '<i class="ri-list-check-2"></i>' +
            '<span>On This Page</span>' +
          '</div>' +
          '<nav class="toc-list">' + linksHtml + '</nav>' +
        '</aside>'
      );
    }

    // 5. Breadcrumb HTML
    var breadcrumbHtml = (
      '<nav class="article-breadcrumbs" aria-label="Breadcrumb">' +
        '<a href="../">DEVZOOP</a>' +
        '<span class="article-breadcrumbs-sep">/</span>' +
        '<a href="./">Blog</a>' +
        '<span class="article-breadcrumbs-sep">/</span>' +
        '<span class="article-breadcrumbs-current" aria-current="page">' + escapeHtml(article.title) + '</span>' +
      '</nav>'
    );

    // 6. Header Tags
    var tagsHtml = (article.tags || []).map(function (t) {
      return '<span class="tag-pill">#' + escapeHtml(t) + '</span>';
    }).join('');

    // 7. Author Bio Box
    var authorHtml = (
      '<div class="article-author-card">' +
        '<div class="author-avatar-wrap">' +
          '<img src="../assets/img/rahul-about.jpg" alt="Rahul Mandal" loading="lazy" />' +
        '</div>' +
        '<div class="author-info">' +
          '<span class="author-name">Rahul Mandal</span>' +
          '<span class="author-role">Independent Web Developer</span>' +
          '<p class="author-bio">Notes from the build — documenting real-world web architecture, debugging discoveries, and deployment workflows as an independent developer.</p>' +
        '</div>' +
      '</div>'
    );

    // 8. Adjacent Articles Navigation (Prev / Next)
    var adjacent = DEVZOOP_BLOG.getAdjacent(article.slug);
    var prevHtml = adjacent.prev
      ? '<a href="post.html?slug=' + encodeURIComponent(adjacent.prev.slug) + '" class="article-nav-card article-nav-prev">' +
          '<span class="article-nav-direction"><i class="ri-arrow-left-line"></i> Previous Note</span>' +
          '<span class="article-nav-title">' + escapeHtml(adjacent.prev.title) + '</span>' +
        '</a>'
      : '<div class="article-nav-card article-nav-prev disabled"><span class="article-nav-direction">Start of Journal</span></div>';

    var nextHtml = adjacent.next
      ? '<a href="post.html?slug=' + encodeURIComponent(adjacent.next.slug) + '" class="article-nav-card article-nav-next">' +
          '<span class="article-nav-direction">Next Note <i class="ri-arrow-right-line"></i></span>' +
          '<span class="article-nav-title">' + escapeHtml(adjacent.next.title) + '</span>' +
        '</a>'
      : '<div class="article-nav-card article-nav-next disabled"><span class="article-nav-direction">Latest Note</span></div>';

    var adjacentHtml = '<div class="article-nav-row">' + prevHtml + nextHtml + '</div>';

    // 9. Related Articles Grid
    var related = DEVZOOP_BLOG.getRelated(article);
    var relatedCardsHtml = related.map(function (rel) {
      return (
        '<article class="article-card">' +
          '<div class="article-card-tech-banner">' +
            '<span class="article-code-glyph" aria-hidden="true">' + getCategoryGlyph(rel.category) + '</span>' +
            '<span class="article-category-badge ' + getCategoryClass(rel.category) + '">' +
              escapeHtml(rel.category) +
            '</span>' +
          '</div>' +
          '<div class="article-card-body">' +
            '<div class="article-meta-line">' +
              '<span>' + escapeHtml(rel.readingTime) + '</span>' +
              '<span class="demo-chip">Sample Note</span>' +
            '</div>' +
            '<h4 class="article-card-title">' +
              '<a href="post.html?slug=' + encodeURIComponent(rel.slug) + '">' +
                escapeHtml(rel.title) +
              '</a>' +
            '</h4>' +
            '<p class="article-card-excerpt">' + escapeHtml(rel.excerpt) + '</p>' +
          '</div>' +
          '<div class="article-card-footer">' +
            '<a href="post.html?slug=' + encodeURIComponent(rel.slug) + '" class="article-card-link">' +
              '<span>Read Note</span>' +
              '<i class="ri-arrow-right-line"></i>' +
            '</a>' +
          '</div>' +
        '</article>'
      );
    }).join('');

    var relatedSectionHtml = related.length > 0 ? (
      '<div class="article-related-section">' +
        '<div class="related-section-header">' +
          '<h3 class="related-title">Related Notes</h3>' +
          '<a href="./" class="btn btn-outline">' +
            '<span>Explore All Notes</span>' +
            '<i class="ri-arrow-right-up-line"></i>' +
          '</a>' +
        '</div>' +
        '<div class="related-grid">' + relatedCardsHtml + '</div>' +
      '</div>'
    ) : '';

    // 10. Assemble Full Page
    articlePage.innerHTML = (
      '<div class="article-reading-progress" aria-hidden="true">' +
        '<div class="article-reading-progress-bar" id="articleReadingBar"></div>' +
      '</div>' +
      '<div class="article-container">' +
        breadcrumbHtml +
        '<header class="article-header">' +
          '<div class="article-header-meta">' +
            '<span class="article-category-badge ' + getCategoryClass(article.category) + '">' +
              escapeHtml(article.category) +
            '</span>' +
            '<span>' + escapeHtml(article.readingTime) + '</span>' +
            '<span>·</span>' +
            '<span class="demo-chip">Sample Entry · Oct 2026</span>' +
          '</div>' +
          '<h1 class="article-title">' + escapeHtml(article.title) + '</h1>' +
          '<div class="article-lead">' + escapeHtml(article.excerpt) + '</div>' +
          '<div class="article-tags-row">' + tagsHtml + '</div>' +
        '</header>' +
        '<div class="article-layout">' +
          '<div class="article-main-column">' +
            '<div class="article-body">' + bodyHtml + '</div>' +
            authorHtml +
            adjacentHtml +
            relatedSectionHtml +
          '</div>' +
          tocHtml +
        '</div>' +
      '</div>'
    );

    // 11. Setup Interactive Features
    setupCodeCopyButtons();
    setupReadingProgressBar();
    setupTableOfContents();
  }

  /* ── Reading Progress Bar ───────────────────────────── */
  function setupReadingProgressBar() {
    var bar = document.getElementById('articleReadingBar');
    var articleCol = document.querySelector('.article-main-column');
    if (!bar || !articleCol) return;

    function updateReading() {
      var rect = articleCol.getBoundingClientRect();
      var colTop = rect.top + window.scrollY;
      var colHeight = rect.height;
      var windowH = window.innerHeight;
      var scrollY = window.scrollY;

      var start = colTop - 100;
      var end = colTop + colHeight - windowH;

      if (scrollY <= start) {
        bar.style.width = '0%';
      } else if (scrollY >= end) {
        bar.style.width = '100%';
      } else {
        var progress = ((scrollY - start) / (end - start)) * 100;
        bar.style.width = Math.min(100, Math.max(0, progress)) + '%';
      }
    }

    window.addEventListener('scroll', updateReading, { passive: true });
    updateReading();
  }

  /* ── Table of Contents Scrollspy & Smooth Navigation ── */
  function setupTableOfContents() {
    var tocLinks = document.querySelectorAll('.toc-link');
    if (tocLinks.length === 0) return;

    // Smooth scroll on TOC click
    tocLinks.forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var targetId = this.getAttribute('href');
        var target = document.querySelector(targetId);
        if (target) {
          var topOffset = target.getBoundingClientRect().top + window.scrollY - 85;
          window.scrollTo({
            top: topOffset,
            behavior: 'smooth'
          });
          history.pushState(null, null, targetId);
        }
      });
    });

    // Scrollspy with IntersectionObserver
    var headings = document.querySelectorAll('.article-body h2[id]');
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            var id = entry.target.getAttribute('id');
            tocLinks.forEach(function (l) {
              if (l.getAttribute('href') === '#' + id) {
                l.classList.add('active');
              } else {
                l.classList.remove('active');
              }
            });
          }
        });
      }, {
        rootMargin: '-80px 0px -60% 0px',
        threshold: 0
      });

      headings.forEach(function (h) {
        observer.observe(h);
      });
    }
  }

  /* ── Initialize ─────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    setupSharedUI();
    initListingPage();
    initPostPage();
    setupCodeCopyButtons();
    setupReadingProgressBar();
    setupTableOfContents();
  });

})();
