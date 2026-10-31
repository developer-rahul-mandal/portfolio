/* ═══════════════════════════════════════════════════════════
   DEVZOOP — Blog Data Store
   Structured article data for the DEVZOOP Developer Journal.
   Supports rich headings, code snippets with filenames,
   terminal commands, workflow step lists, and related posts.
   ═══════════════════════════════════════════════════════════ */

var DEVZOOP_BLOG = (function () {
  'use strict';

  var articles = [
    {
      id: 1,
      slug: 'how-i-built-my-first-ci-cd-pipeline',
      title: 'How I built my first CI/CD pipeline',
      category: 'GIT & CI/CD',
      excerpt: 'From manual uploads to automated deployments: understanding GitHub Actions, pull requests, and a safer path to production.',
      date: '2026-10-28',
      readingTime: '8 min read',
      tags: ['GitHub Actions', 'Automation', 'Deployment', 'Git', 'FTPS'],
      featured: true,
      demo: true,
      relatedSlugs: [
        'from-feature-branch-to-production-my-git-workflow',
        'what-i-learned-deploying-to-shared-hosting'
      ],
      content: [
        {
          type: 'paragraph',
          text: 'For a long time, deploying my portfolio meant opening an FTP client, dragging modified files over an FTPS connection, and hoping that nothing broke in transit. It felt direct and simple — until the day I accidentally uploaded a stale file over a freshly edited stylesheet and spent two hours wondering why the layout collapsed.'
        },
        {
          type: 'paragraph',
          text: 'That experience made me realize something fundamental: manual deployment is not a badge of simplicity; it is an invisible liability. By introducing an automated pipeline with GitHub Actions, every change now follows a repeatable, verifiable path to production.'
        },

        // Section 1
        {
          type: 'heading',
          id: 'why-manual-deployment-fails',
          text: '1. Why manual deployment becomes difficult'
        },
        {
          type: 'paragraph',
          text: 'Manual deployment suffers from four inherent failure modes that compound as a project grows:'
        },
        {
          type: 'list',
          items: [
            '<strong>Human oversight:</strong> Forgetting to transfer a helper script, config file, or new stylesheet.',
            '<strong>Lack of atomic releases:</strong> Files are uploaded one by one, creating a brief window where mismatched assets can break live user sessions.',
            '<strong>No automated sanity checks:</strong> Untested syntax errors or missing files go straight to production with zero warning.',
            '<strong>No historical record:</strong> No log exists of exactly who uploaded what file at what minute, eliminating accountability and easy rollbacks.'
          ]
        },
        {
          type: 'callout',
          title: 'The core lesson',
          text: 'Automation is not about saving thirty seconds of file-dragging; it is about eliminating human error from the release process.'
        },

        // Section 2
        {
          type: 'heading',
          id: 'git-branch-and-pr-workflow',
          text: '2. Understanding the Git branch and pull request workflow'
        },
        {
          type: 'paragraph',
          text: 'The foundation of safe continuous deployment is never committing directly to the production branch. In my workflow, the <code>main</code> branch represents what is currently live. All active development happens on isolated feature branches.'
        },
        {
          type: 'terminal',
          filename: 'terminal — git-workflow',
          commands: [
            { cmd: 'git checkout -b feature/redesign-article-view', output: "Switched to a new branch 'feature/redesign-article-view'" },
            { cmd: 'git status', output: 'On branch feature/redesign-article-view\nChanges to be committed: modified: blog/post.html, blog/blog.css' },
            { cmd: 'git commit -m "feat(blog): implement responsive code editor panels and TOC"', output: '[feature/redesign-article-view 4a19c2f] feat(blog): implement responsive code editor panels and TOC\n 2 files changed, 280 insertions(+)' },
            { cmd: 'git push origin feature/redesign-article-view', output: 'Total 4 (delta 2), reused 0 (delta 0)\nTo https://github.com/developer-rahul-mandal/portfolio.git\n * [new branch] feature/redesign-article-view -> feature/redesign-article-view' }
          ]
        },
        {
          type: 'paragraph',
          text: 'Once pushed, opening a Pull Request (PR) triggers automated status checks and allows me to inspect the unified diff before any code reaches the deployment stage.'
        },

        // Section 3
        {
          type: 'heading',
          id: 'running-ci-checks',
          text: '3. Running CI checks before merging'
        },
        {
          type: 'paragraph',
          text: 'Before any pull request can be merged into <code>main</code>, an automated Continuous Integration (CI) workflow runs in an ephemeral Ubuntu container. For a static portfolio that may incorporate PHP scripts, the CI check validates two things: project file integrity and PHP syntax.'
        },
        {
          type: 'code',
          filename: '.github/workflows/ci.yml',
          language: 'yaml',
          text: 'name: Portfolio CI\n\non:\n  pull_request:\n    branches:\n      - main\n\njobs:\n  validate:\n    name: Validate Portfolio\n    runs-on: ubuntu-latest\n\n    steps:\n      - name: Checkout code\n        uses: actions/checkout@v4\n\n      - name: Check PHP files\n        run: |\n          if find . -name "*.php" -print -quit | grep -q .; then\n            echo "PHP files found. Checking syntax..."\n            find . -name "*.php" -print0 | while IFS= read -r -d \'\' file; do\n              php -l "$file"\n            done\n          else\n            echo "No PHP files found. Skipping PHP syntax check."\n          fi\n\n      - name: Check HTML integrity\n        run: |\n          test -f index.html\n          echo "index.html exists."\n\n      - name: CI passed\n        run: echo "All validation checks passed successfully!"'
        },
        {
          type: 'paragraph',
          text: 'If a single PHP file contains a parse error, or if <code>index.html</code> was accidentally deleted or misnamed, the job fails immediately and blocks the pull request from merging.'
        },

        // Section 4
        {
          type: 'heading',
          id: 'deploying-after-pr-merge',
          text: '4. Deploying after a pull request is merged'
        },
        {
          type: 'paragraph',
          text: 'The CD (Continuous Deployment) workflow is triggered exclusively when a pull request targeting <code>main</code> is closed with a successful merge. Merely closing a PR without merging does not trigger deployment.'
        },
        {
          type: 'workflow-diagram',
          title: 'Release Pipeline Architecture',
          steps: [
            { id: 'step-1', icon: 'ri-git-branch-line', label: 'Feature Branch', desc: 'Local commits pushed', status: 'done' },
            { id: 'step-2', icon: 'ri-git-pull-request-line', label: 'Pull Request', desc: 'Targeting main branch', status: 'done' },
            { id: 'step-3', icon: 'ri-shield-check-line', label: 'CI Validation', desc: 'Syntax & file integrity checks', status: 'done' },
            { id: 'step-4', icon: 'ri-git-merge-line', label: 'PR Merged', desc: 'Trigger condition met', status: 'done' },
            { id: 'step-5', icon: 'ri-upload-cloud-2-line', label: 'FTPS Deploy', desc: 'Sync to production web root', status: 'active' }
          ]
        },

        // Section 5
        {
          type: 'heading',
          id: 'understanding-workflow-yaml',
          text: '5. Understanding GitHub Actions workflow YAML'
        },
        {
          type: 'paragraph',
          text: 'Here is the exact continuous deployment workflow that transfers files securely to production via encrypted FTPS. Notice the exclusion rules that prevent repository metadata and secrets from reaching the public web server.'
        },
        {
          type: 'code',
          filename: '.github/workflows/cd.yml',
          language: 'yaml',
          text: 'name: Portfolio CD\n\non:\n  pull_request:\n    branches:\n      - main\n    types:\n      - closed\n\njobs:\n  deploy:\n    name: Deploy to Production\n    if: github.event.pull_request.merged == true\n    runs-on: ubuntu-latest\n\n    steps:\n      - name: Checkout main\n        uses: actions/checkout@v4\n\n      - name: Deploy to cPanel via FTPS\n        uses: SamKirkland/FTP-Deploy-Action@v4.4.0\n        with:\n          server: ${{ secrets.FTP_SERVER }}\n          username: ${{ secrets.FTP_USERNAME }}\n          password: ${{ secrets.FTP_PASSWORD }}\n          port: ${{ secrets.FTP_PORT }}\n          protocol: ftps\n          server-dir: ./\n          exclude: |\n            **/.git*\n            **/.git*/**\n            **/.github/**\n            **/node_modules/**\n            .env\n            .env.*'
        },
        {
          type: 'paragraph',
          text: 'Key parts of this configuration include:'
        },
        {
          type: 'list',
          items: [
            '<code>github.event.pull_request.merged == true</code>: Guarantees that unmerged or rejected PRs do not trigger uploads.',
            '<code>protocol: ftps</code>: Enforces TLS encryption for credential and file transmission over FTP.',
            '<code>exclude</code>: Protects Git history (<code>.git</code>), workflow definitions (<code>.github</code>), and potential local environment configurations from leaking into the public web root.'
          ]
        },

        // Section 6
        {
          type: 'heading',
          id: 'configuring-secrets-safely',
          text: '6. Configuring deployment secrets safely'
        },
        {
          type: 'paragraph',
          text: 'Credentials such as your FTP host, username, password, and port must never be committed to Git. Instead, they are stored in GitHub Repository Secrets under <em>Settings > Secrets and variables > Actions</em>.'
        },
        {
          type: 'code',
          filename: 'secrets-reference.txt',
          language: 'bash',
          text: '# Configured in GitHub Settings -> Secrets -> Actions\nFTP_SERVER    # Hostname (e.g. ftp.devzoop.in or cPanel host)\nFTP_USERNAME  # Dedicated FTPS deployment account\nFTP_PASSWORD  # High-entropy random deployment password\nFTP_PORT      # 21 (explicit FTPS) or 990 (implicit FTPS)'
        },
        {
          type: 'callout',
          title: 'Security best practice',
          text: 'Create a dedicated FTP user in cPanel whose root directory is restricted strictly to the project document root, rather than using your master hosting account credentials.'
        },

        // Section 7
        {
          type: 'heading',
          id: 'testing-and-troubleshooting',
          text: '7. Testing the deployment and troubleshooting failures'
        },
        {
          type: 'paragraph',
          text: 'When setting up this pipeline for the first time, errors are almost guaranteed. Here are the three most common failures I encountered and how to debug them from the GitHub Actions log console:'
        },
        {
          type: 'list',
          items: [
            '<strong>TLS certificate mismatches:</strong> Shared hosting often presents the host server\'s wildcard SSL rather than your custom domain. If FTPS fails with a certificate verification error, ensure the <code>FTP_SERVER</code> secret matches the hosting provider\'s canonical server name.',
            '<strong>Passive mode port blocking:</strong> Some shared hosts block the passive port range (e.g., 30000–50000). Verify passive mode settings in your cPanel FTP account.',
            '<strong>Overwritten permissions:</strong> FTPS uploads sometimes default to <code>0600</code> permissions, rendering assets unreadable by the Apache web server. Ensure default file permissions on the target directory are configured as <code>0644</code> for files and <code>0755</code> for directories.'
          ]
        },

        // Section 8
        {
          type: 'heading',
          id: 'lessons-learned',
          text: '8. Lessons learned and next improvements'
        },
        {
          type: 'paragraph',
          text: 'Implementing this pipeline took an afternoon of trial and error, but the peace of mind it provides is invaluable. Every change is tracked, syntax-checked, and deployed with zero manual intervention.'
        },
        {
          type: 'paragraph',
          text: 'My next planned improvements include adding automated HTML and CSS linting, asset minification before upload, and an automated health check ping that verifies HTTP 200 on the live domain immediately following deployment.'
        }
      ]
    },
    {
      id: 2,
      slug: 'what-finally-made-javascript-arrays-click',
      title: 'What finally made JavaScript arrays click for me',
      category: 'LEARN',
      excerpt: 'Understanding map, filter, and reduce by building real features instead of reading documentation in circles.',
      date: '2026-10-21',
      readingTime: '5 min read',
      tags: ['JavaScript', 'Arrays', 'Fundamentals', 'Functional'],
      featured: false,
      demo: true,
      relatedSlugs: [
        'how-i-built-my-first-ci-cd-pipeline',
        'building-a-real-project-with-core-php-and-mysql'
      ],
      content: [
        {
          type: 'paragraph',
          text: 'I read about map, filter, and reduce dozens of times. I could recite the MDN descriptions. But I could not confidently use them in my own code until I stopped reading and started building features that genuinely required them.'
        },
        {
          type: 'heading',
          id: 'the-turning-point',
          text: '1. The turning point: solving real UI data challenges'
        },
        {
          type: 'paragraph',
          text: 'The moment arrays clicked was when I needed to filter a list of bus routes by status and transform the active records into dropdown options. In imperative code, that meant temporary arrays and loop indexing:'
        },
        {
          type: 'code',
          filename: 'routes-filter.js',
          language: 'javascript',
          text: '// Before: Imperative loop with mutable state\nvar activeRoutes = [];\nfor (var i = 0; i < routes.length; i++) {\n  if (routes[i].status === "active") {\n    activeRoutes.push({\n      label: routes[i].name + " (Bus #" + routes[i].busNumber + ")",\n      value: routes[i].id\n    });\n  }\n}\n\n// After: Declarative array methods\nvar activeRoutes = routes\n  .filter(function(r) { return r.status === "active"; })\n  .map(function(r) {\n    return {\n      label: r.name + " (Bus #" + r.busNumber + ")",\n      value: r.id\n    };\n  });'
        },
        {
          type: 'heading',
          id: 'understanding-reduce',
          text: '2. Demystifying reduce'
        },
        {
          type: 'paragraph',
          text: '<code>reduce</code> felt intimidating until I realized it is simply an accumulator. Whenever you want to turn an array into a single number, an object map, or a grouped structure, <code>reduce</code> is the cleanest choice:'
        },
        {
          type: 'code',
          filename: 'group-by-category.js',
          language: 'javascript',
          text: '// Grouping articles by category using reduce\nvar grouped = articles.reduce(function(acc, article) {\n  var cat = article.category;\n  if (!acc[cat]) {\n    acc[cat] = [];\n  }\n  acc[cat].push(article.title);\n  return acc;\n}, {});'
        },
        {
          type: 'heading',
          id: 'immutability-benefits',
          text: '3. Why immutability matters'
        },
        {
          type: 'paragraph',
          text: 'Array methods like <code>map</code> and <code>filter</code> return new arrays without mutating the source data. This eliminates hard-to-track bugs where one function accidentally modifies data used elsewhere.'
        }
      ]
    },
    {
      id: 3,
      slug: 'debugging-a-production-issue-without-guessing',
      title: 'Debugging a production issue without guessing',
      category: 'BREAK',
      excerpt: 'A methodical approach to reproducing errors, reading logs, and isolating causes when something breaks in production.',
      date: '2026-10-15',
      readingTime: '6 min read',
      tags: ['Debugging', 'PHP', 'Problem Solving', 'Logs'],
      featured: false,
      demo: true,
      relatedSlugs: [
        'how-i-built-my-first-ci-cd-pipeline',
        'building-a-real-project-with-core-php-and-mysql'
      ],
      content: [
        {
          type: 'paragraph',
          text: 'The order notification system stopped working on a Sunday evening. Customers placed orders, but confirmation emails failed to send. The instinct when production breaks is panic and random code editing — but guessing is the slowest way to fix a bug.'
        },
        {
          type: 'heading',
          id: 'reproduce-dont-guess',
          text: '1. Step 1: Reproduce before changing code'
        },
        {
          type: 'paragraph',
          text: 'Before modifying a single line, I submitted a controlled test order. The order database entry was created, but no email was dispatched. Now I had a confirmed, reproducible reproduction case.'
        },
        {
          type: 'heading',
          id: 'read-the-logs',
          text: '2. Step 2: Read the server error logs'
        },
        {
          type: 'code',
          filename: 'error.log',
          language: 'text',
          text: '[2026-10-15 21:43:12] mail.ERROR: Connection to smtp.provider.com:587\ntimed out after 30s. Retries exhausted.\n[2026-10-15 21:43:42] mail.CRITICAL: Unable to deliver notification for order #1084'
        },
        {
          type: 'paragraph',
          text: 'The log showed the exact point of failure: the external SMTP host was unreachable. The problem was not query logic, PHP syntax, or mail formatting — it was an upstream network timeout.'
        },
        {
          type: 'heading',
          id: 'building-resilience',
          text: '3. Step 3: Architecting resilience'
        },
        {
          type: 'paragraph',
          text: 'The real fix was decoupling email dispatch from the HTTP checkout response. By recording notifications to an internal database queue and processing them via a background runner with retry backoff, customer checkouts were never blocked by upstream provider downtime again.'
        }
      ]
    },
    {
      id: 4,
      slug: 'building-a-real-project-with-core-php-and-mysql',
      title: 'Building a real project with Core PHP and MySQL',
      category: 'BUILD',
      excerpt: 'Request handling, database access, validation, and project structure — lessons from shipping a school bus management system.',
      date: '2026-10-08',
      readingTime: '8 min read',
      tags: ['PHP', 'MySQL', 'Architecture', 'Backend'],
      featured: false,
      demo: true,
      relatedSlugs: [
        'how-i-built-my-first-ci-cd-pipeline',
        'what-i-learned-deploying-to-shared-hosting'
      ],
      content: [
        {
          type: 'paragraph',
          text: 'Building a school bus management system without heavy frameworks or bloated abstractions taught me more about web fundamentals than any pre-packaged solution. When you write core PHP and MySQL, you are responsible for every layer of the application lifecycle.'
        },
        {
          type: 'heading',
          id: 'clean-directory-structure',
          text: '1. Establishing an organized structure'
        },
        {
          type: 'code',
          filename: 'project-tree.txt',
          language: 'text',
          text: 'busattendance/\n├── config/\n│   └── database.php       # PDO connection factory\n├── controllers/           # HTTP action handlers\n│   ├── StudentController.php\n│   └── RouteController.php\n├── models/                # Prepared query data operations\n│   ├── Student.php\n│   └── Route.php\n├── views/                 # Presentation templates\n│   ├── layout/header.php\n│   ├── students/index.php\n│   └── routes/index.php\n└── index.php              # Front controller router'
        },
        {
          type: 'heading',
          id: 'secure-pdo-queries',
          text: '2. Prepared statements with PDO'
        },
        {
          type: 'code',
          filename: 'models/Student.php',
          language: 'php',
          text: '<?php\nclass Student {\n    private PDO $db;\n\n    public function __construct(PDO $db) {\n        $this->db = $db;\n    }\n\n    public function findByRoute(int $routeId): array {\n        $stmt = $this->db->prepare(\n            "SELECT id, first_name, last_name, stop_name, status \n             FROM students \n             WHERE route_id = :route_id AND active = 1 \n             ORDER BY stop_order ASC"\n        );\n        $stmt->execute([":route_id" => $routeId]);\n        return $stmt->fetchAll(PDO::FETCH_ASSOC);\n    }\n}'
        },
        {
          type: 'paragraph',
          text: 'By enforcing prepared statements consistently, SQL injection is completely prevented at the driver level.'
        }
      ]
    },
    {
      id: 5,
      slug: 'what-i-learned-deploying-to-shared-hosting',
      title: 'What I learned while deploying to shared hosting',
      category: 'BUILD',
      excerpt: 'Deployment constraints, FTP/FTPS, environment configuration, and the art of safe releases on shared infrastructure.',
      date: '2026-10-01',
      readingTime: '5 min read',
      tags: ['Deployment', 'Hosting', 'DevOps', 'cPanel'],
      featured: false,
      demo: true,
      relatedSlugs: [
        'how-i-built-my-first-ci-cd-pipeline',
        'from-feature-branch-to-production-my-git-workflow'
      ],
      content: [
        {
          type: 'paragraph',
          text: 'Shared hosting often receives criticism from modern developer communities, but for many independent projects and small client builds, it remains an economical and accessible reality. Operating within its boundaries forces you to understand web server mechanics.'
        },
        {
          type: 'heading',
          id: 'handling-environment-constraints',
          text: '1. Overcoming the lack of shell access'
        },
        {
          type: 'paragraph',
          text: 'Without SSH access, you cannot run Composer or restart Apache on demand. The solution is treating your local environment or CI runner as the compilation layer: compile dependencies and assets before shipping via FTPS.'
        },
        {
          type: 'heading',
          id: 'protecting-configuration-files',
          text: '2. Protecting configuration files'
        },
        {
          type: 'paragraph',
          text: 'On shared Apache hosts, store configuration files containing database credentials outside the <code>public_html</code> root directory whenever possible, or protect them with strict Apache directives:'
        },
        {
          type: 'code',
          filename: '.htaccess',
          language: 'bash',
          text: '<Files "config.php">\n    Order allow,deny\n    Deny from all\n</Files>'
        }
      ]
    },
    {
      id: 6,
      slug: 'from-feature-branch-to-production-my-git-workflow',
      title: 'From feature branch to production: my Git workflow',
      category: 'GIT & CI/CD',
      excerpt: 'Branches, pull requests, status checks, and merging — a simple Git workflow that keeps solo projects safe.',
      date: '2026-09-24',
      readingTime: '4 min read',
      tags: ['Git', 'Workflow', 'GitHub', 'Solo Dev'],
      featured: false,
      demo: true,
      relatedSlugs: [
        'how-i-built-my-first-ci-cd-pipeline',
        'debugging-a-production-issue-without-guessing'
      ],
      content: [
        {
          type: 'paragraph',
          text: 'Even when building solo, treating your Git repository like a team codebase provides an invaluable safety net. Using pull requests forces you to slow down, review unified diffs, and ensure CI status checks pass before code reaches production.'
        },
        {
          type: 'heading',
          id: 'the-solo-git-flow',
          text: '1. The four-step discipline'
        },
        {
          type: 'list',
          items: [
            '<strong>Never commit to main:</strong> Every change starts on a descriptive branch like <code>feat/</code>, <code>fix/</code>, or <code>docs/</code>.',
            '<strong>Self-review on PR creation:</strong> Reading the diff on GitHub with fresh eyes catches typos, leftover debug code, and forgotten files.',
            '<strong>Wait for green checks:</strong> If automated tests fail, fix them on the feature branch rather than in production.',
            '<strong>Squash or clean merge:</strong> Keeps the production history legible and easily bisectable.'
          ]
        }
      ]
    }
  ];

  /* ── Query API ───────────────────────────────────────── */
  function getAll() {
    return articles.slice();
  }

  function getFeatured() {
    for (var i = 0; i < articles.length; i++) {
      if (articles[i].featured) return articles[i];
    }
    return null;
  }

  function getByCategory(category) {
    if (!category || category === 'ALL NOTES') return articles.slice();
    return articles.filter(function (a) {
      return a.category === category;
    });
  }

  function search(query, category) {
    var pool = getByCategory(category);
    if (!query || !query.trim()) return pool;

    var q = query.toLowerCase().trim();
    return pool.filter(function (a) {
      return (
        a.title.toLowerCase().indexOf(q) !== -1 ||
        a.excerpt.toLowerCase().indexOf(q) !== -1 ||
        a.category.toLowerCase().indexOf(q) !== -1 ||
        a.tags.some(function (t) { return t.toLowerCase().indexOf(q) !== -1; })
      );
    });
  }

  function getBySlug(slug) {
    for (var i = 0; i < articles.length; i++) {
      if (articles[i].slug === slug) return articles[i];
    }
    return null;
  }

  function getAdjacent(slug) {
    for (var i = 0; i < articles.length; i++) {
      if (articles[i].slug === slug) {
        return {
          prev: i > 0 ? articles[i - 1] : null,
          next: i < articles.length - 1 ? articles[i + 1] : null
        };
      }
    }
    return { prev: null, next: null };
  }

  function getRelated(article) {
    if (!article) return [];
    var related = [];
    if (article.relatedSlugs && article.relatedSlugs.length > 0) {
      for (var i = 0; i < article.relatedSlugs.length; i++) {
        var match = getBySlug(article.relatedSlugs[i]);
        if (match) related.push(match);
      }
    }
    if (related.length === 0) {
      // Fallback: match by category
      related = articles.filter(function (a) {
        return a.id !== article.id && a.category === article.category;
      }).slice(0, 2);
    }
    return related;
  }

  function getCategories() {
    var cats = [];
    articles.forEach(function (a) {
      if (cats.indexOf(a.category) === -1) cats.push(a.category);
    });
    return cats;
  }

  return {
    getAll: getAll,
    getFeatured: getFeatured,
    getByCategory: getByCategory,
    search: search,
    getBySlug: getBySlug,
    getAdjacent: getAdjacent,
    getRelated: getRelated,
    getCategories: getCategories
  };
})();
