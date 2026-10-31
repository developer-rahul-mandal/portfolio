-- ═══════════════════════════════════════════════════════════
-- DEVZOOP Blog Database Migration 002: Seed Demo Data
-- Migrates categories, tags, and all 6 existing demo articles
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- 1. Seed Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`) VALUES
(1, 'GIT & CI/CD', 'git-ci-cd', 'Version control workflows, GitHub Actions pipelines, and continuous deployment.'),
(2, 'LEARN', 'learn', 'Deep dives into modern JavaScript, PHP, algorithms, and web fundamentals.'),
(3, 'BREAK', 'break', 'Production incidents, root-cause debugging sessions, and lessons from failures.'),
(4, 'BUILD', 'build', 'Full-stack application architecture, request lifecycles, and database engineering.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Seed Tags
INSERT INTO `tags` (`id`, `name`, `slug`) VALUES
(1, 'GitHub Actions', 'github-actions'),
(2, 'Automation', 'automation'),
(3, 'Deployment', 'deployment'),
(4, 'Git', 'git'),
(5, 'FTPS', 'ftps'),
(6, 'JavaScript', 'javascript'),
(7, 'Arrays', 'arrays'),
(8, 'Fundamentals', 'fundamentals'),
(9, 'Functional', 'functional'),
(10, 'Debugging', 'debugging'),
(11, 'PHP', 'php'),
(12, 'Problem Solving', 'problem-solving'),
(13, 'Logs', 'logs'),
(14, 'MySQL', 'mysql'),
(15, 'Architecture', 'architecture'),
(16, 'Backend', 'backend'),
(17, 'Hosting', 'hosting'),
(18, 'DevOps', 'devops'),
(19, 'cPanel', 'cpanel'),
(20, 'Workflow', 'workflow'),
(21, 'GitHub', 'github'),
(22, 'Solo Dev', 'solo-dev')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Seed Posts
-- Post 1: How I built my first CI/CD pipeline (Featured)
INSERT INTO `posts` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `status`, `is_featured`, `published_at`, `meta_title`, `meta_description`) VALUES
(1, 1, 
'How I built my first CI/CD pipeline', 
'how-i-built-my-first-ci-cd-pipeline', 
'From manual uploads to automated deployments: understanding GitHub Actions, pull requests, and a safer path to production.',
'For a long time, deploying my portfolio meant opening an FTP client, dragging modified files over an FTPS connection, and hoping that nothing broke in transit. It felt direct and simple — until the day I accidentally uploaded a stale file over a freshly edited stylesheet and spent two hours wondering why the layout collapsed.

That experience made me realize something fundamental: manual deployment is not a badge of simplicity; it is an invisible liability. By introducing an automated pipeline with GitHub Actions, every change now follows a repeatable, verifiable path to production.

## 1. Why manual deployment becomes difficult

Manual deployment suffers from four inherent failure modes that compound as a project grows:

* **Human oversight:** Forgetting to transfer a helper script, config file, or new stylesheet.
* **Lack of atomic releases:** Files are uploaded one by one, creating a brief window where mismatched assets can break live user sessions.
* **No automated sanity checks:** Untested syntax errors or missing files go straight to production with zero warning.
* **No historical record:** No log exists of exactly who uploaded what file at what minute, eliminating accountability and easy rollbacks.

> **The core lesson:** Automation is not about saving thirty seconds of file-dragging; it is about eliminating human error from the release process.

## 2. Understanding the Git branch and pull request workflow

The foundation of safe continuous deployment is never committing directly to the production branch. In my workflow, the `main` branch represents what is currently live. All active development happens on isolated feature branches.

```terminal:terminal — git-workflow
$ git checkout -b feature/redesign-article-view
Switched to a new branch ''feature/redesign-article-view''
$ git status
On branch feature/redesign-article-view
Changes to be committed: modified: blog/post.html, blog/blog.css
$ git commit -m "feat(blog): implement responsive code editor panels and TOC"
[feature/redesign-article-view 4a19c2f] feat(blog): implement responsive code editor panels and TOC
 2 files changed, 280 insertions(+)
$ git push origin feature/redesign-article-view
Total 4 (delta 2), reused 0 (delta 0)
To https://github.com/developer-rahul-mandal/portfolio.git
 * [new branch] feature/redesign-article-view -> feature/redesign-article-view
```

Once pushed, opening a Pull Request (PR) triggers automated status checks and allows me to inspect the unified diff before any code reaches the deployment stage.

## 3. Running CI checks before merging

Before any pull request can be merged into `main`, an automated Continuous Integration (CI) workflow runs in an ephemeral Ubuntu container. For a static portfolio that may incorporate PHP scripts, the CI check validates two things: project file integrity and PHP syntax.

```yaml:.github/workflows/ci.yml
name: Portfolio CI

on:
  pull_request:
    branches:
      - main

jobs:
  validate:
    name: Validate Portfolio
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Check PHP files
        run: |
          if find . -name "*.php" -print -quit | grep -q .; then
            echo "PHP files found. Checking syntax..."
            find . -name "*.php" -print0 | while IFS= read -r -d '''' file; do
              php -l "$file"
            done
          else
            echo "No PHP files found. Skipping PHP syntax check."
          fi

      - name: Check HTML integrity
        run: |
          test -f index.html
          echo "index.html exists."

      - name: CI passed
        run: echo "All validation checks passed successfully!"
```

If a single PHP file contains a parse error, or if `index.html` was accidentally deleted or misnamed, the job fails immediately and blocks the pull request from merging.

## 4. Deploying after a pull request is merged

The CD (Continuous Deployment) workflow is triggered exclusively when a pull request targeting `main` is closed with a successful merge. Merely closing a PR without merging does not trigger deployment.

```workflow:Release Pipeline Architecture
[done] Feature Branch | Local commits pushed
[done] Pull Request | Targeting main branch
[done] CI Validation | Syntax & file integrity checks
[done] PR Merged | Trigger condition met
[active] FTPS Deploy | Sync to production web root
```

## 5. Understanding GitHub Actions workflow YAML

Here is the exact continuous deployment workflow that transfers files securely to production via encrypted FTPS. Notice the exclusion rules that prevent repository metadata and secrets from reaching the public web server.

```yaml:.github/workflows/cd.yml
name: Portfolio CD

on:
  pull_request:
    branches:
      - main
    types:
      - closed

jobs:
  deploy:
    name: Deploy to Production
    if: github.event.pull_request.merged == true
    runs-on: ubuntu-latest

    steps:
      - name: Checkout main
        uses: actions/checkout@v4

      - name: Deploy to cPanel via FTPS
        uses: SamKirkland/FTP-Deploy-Action@v4.4.0
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          port: ${{ secrets.FTP_PORT }}
          protocol: ftps
          server-dir: ./
          exclude: |
            **/.git*
            **/.git*/**
            **/.github/**
            **/node_modules/**
            .env
            .env.*
```

Key parts of this configuration include:

* `github.event.pull_request.merged == true`: Guarantees that unmerged or rejected PRs do not trigger uploads.
* `protocol: ftps`: Enforces TLS encryption for credential and file transmission over FTP.
* `exclude`: Protects Git history (`.git`), workflow definitions (`.github`), and local environment configurations from leaking into the public web root.

## 6. Configuring deployment secrets safely

Credentials such as your FTP host, username, password, and port must never be committed to Git. Instead, they are stored in GitHub Repository Secrets under *Settings > Secrets and variables > Actions*.

```bash:secrets-reference.txt
# Configured in GitHub Settings -> Secrets -> Actions
FTP_SERVER    # Hostname (e.g. ftp.devzoop.in or cPanel host)
FTP_USERNAME  # Dedicated FTPS deployment account
FTP_PASSWORD  # High-entropy random deployment password
FTP_PORT      # 21 (explicit FTPS) or 990 (implicit FTPS)
```

> **Security best practice:** Create a dedicated FTP user in cPanel whose root directory is restricted strictly to the project document root, rather than using your master hosting account credentials.

## 7. Testing the deployment and troubleshooting failures

When setting up this pipeline for the first time, errors are almost guaranteed. Here are the three most common failures I encountered and how to debug them from the GitHub Actions log console:

* **TLS certificate mismatches:** Shared hosting often presents the host server''s wildcard SSL rather than your custom domain. If FTPS fails with a certificate verification error, ensure the `FTP_SERVER` secret matches the hosting provider''s canonical server name.
* **Passive mode port blocking:** Some shared hosts block the passive port range (e.g., 30000–50000). Verify passive mode settings in your cPanel FTP account.
* **Overwritten permissions:** FTPS uploads sometimes default to `0600` permissions, rendering assets unreadable by the Apache web server. Ensure default file permissions on the target directory are configured as `0644` for files and `0755` for directories.

## 8. Lessons learned and next improvements

Implementing this pipeline took an afternoon of trial and error, but the peace of mind it provides is invaluable. Every change is tracked, syntax-checked, and deployed with zero manual intervention.

My next planned improvements include adding automated HTML and CSS linting, asset minification before upload, and an automated health check ping that verifies HTTP 200 on the live domain immediately following deployment.',
NULL, 'published', 1, '2026-10-28 10:00:00',
'How I built my first CI/CD pipeline — DEVZOOP Developer Journal',
'From manual uploads to automated deployments: understanding GitHub Actions, pull requests, and a safer path to production.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Post 2: What finally made JavaScript arrays click for me
INSERT INTO `posts` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `status`, `is_featured`, `published_at`, `meta_title`, `meta_description`) VALUES
(2, 2,
'What finally made JavaScript arrays click for me',
'what-finally-made-javascript-arrays-click',
'Understanding map, filter, and reduce by building real features instead of reading documentation in circles.',
'I read about map, filter, and reduce dozens of times. I could recite the MDN descriptions. But I could not confidently use them in my own code until I stopped reading and started building features that genuinely required them.

## 1. The turning point: solving real UI data challenges

The moment arrays clicked was when I needed to filter a list of bus routes by status and transform the active records into dropdown options. In imperative code, that meant temporary arrays and loop indexing:

```javascript:routes-filter.js
// Before: Imperative loop with mutable state
var activeRoutes = [];
for (var i = 0; i < routes.length; i++) {
  if (routes[i].status === "active") {
    activeRoutes.push({
      label: routes[i].name + " (Bus #" + routes[i].busNumber + ")",
      value: routes[i].id
    });
  }
}

// After: Declarative array methods
var activeRoutes = routes
  .filter(function(r) { return r.status === "active"; })
  .map(function(r) {
    return {
      label: r.name + " (Bus #" + r.busNumber + ")",
      value: r.id
    };
  });
```

## 2. Demystifying reduce

`reduce` felt intimidating until I realized it is simply an accumulator. Whenever you want to turn an array into a single number, an object map, or a grouped structure, `reduce` is the cleanest choice:

```javascript:group-by-category.js
// Grouping articles by category using reduce
var grouped = articles.reduce(function(acc, article) {
  var cat = article.category;
  if (!acc[cat]) {
    acc[cat] = [];
  }
  acc[cat].push(article.title);
  return acc;
}, {});
```

## 3. Why immutability matters

Array methods like `map` and `filter` return new arrays without mutating the source data. This eliminates hard-to-track bugs where one function accidentally modifies data used elsewhere.',
NULL, 'published', 0, '2026-10-21 14:30:00',
'What finally made JavaScript arrays click for me — DEVZOOP',
'Understanding map, filter, and reduce by building real features instead of reading documentation in circles.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Post 3: Debugging a production issue without guessing
INSERT INTO `posts` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `status`, `is_featured`, `published_at`, `meta_title`, `meta_description`) VALUES
(3, 3,
'Debugging a production issue without guessing',
'debugging-a-production-issue-without-guessing',
'A methodical approach to reproducing errors, reading logs, and isolating causes when something breaks in production.',
'The order notification system stopped working on a Sunday evening. Customers placed orders, but confirmation emails failed to send. The instinct when production breaks is panic and random code editing — but guessing is the slowest way to fix a bug.

## 1. Step 1: Reproduce before changing code

Before modifying a single line, I submitted a controlled test order. The order database entry was created, but no email was dispatched. Now I had a confirmed, reproducible reproduction case.

## 2. Step 2: Read the server error logs

```text:error.log
[2026-10-15 21:43:12] mail.ERROR: Connection to smtp.provider.com:587
timed out after 30s. Retries exhausted.
[2026-10-15 21:43:42] mail.CRITICAL: Unable to deliver notification for order #1084
```

The log showed the exact point of failure: the external SMTP host was unreachable. The problem was not query logic, PHP syntax, or mail formatting — it was an upstream network timeout.

## 3. Step 3: Architecting resilience

The real fix was decoupling email dispatch from the HTTP checkout response. By recording notifications to an internal database queue and processing them via a background runner with retry backoff, customer checkouts were never blocked by upstream provider downtime again.',
NULL, 'published', 0, '2026-10-15 09:15:00',
'Debugging a production issue without guessing — DEVZOOP',
'A methodical approach to reproducing errors, reading logs, and isolating causes when something breaks in production.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Post 4: Building a real project with Core PHP and MySQL
INSERT INTO `posts` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `status`, `is_featured`, `published_at`, `meta_title`, `meta_description`) VALUES
(4, 4,
'Building a real project with Core PHP and MySQL',
'building-a-real-project-with-core-php-and-mysql',
'Request handling, database access, validation, and project structure — lessons from shipping a school bus management system.',
'Building a school bus management system without heavy frameworks or bloated abstractions taught me more about web fundamentals than any pre-packaged solution. When you write core PHP and MySQL, you are responsible for every layer of the application lifecycle.

## 1. Establishing an organized structure

```text:project-tree.txt
busattendance/
├── config/
│   └── database.php       # PDO connection factory
├── controllers/           # HTTP action handlers
│   ├── StudentController.php
│   └── RouteController.php
├── models/                # Prepared query data operations
│   ├── Student.php
│   └── Route.php
├── views/                 # Presentation templates
│   ├── layout/header.php
│   ├── students/index.php
│   └── routes/index.php
└── index.php              # Front controller router
```

## 2. Prepared statements with PDO

```php:models/Student.php
<?php
class Student {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function findByRoute(int $routeId): array {
        $stmt = $this->db->prepare(
            "SELECT id, first_name, last_name, stop_name, status 
             FROM students 
             WHERE route_id = :route_id AND active = 1 
             ORDER BY stop_order ASC"
        );
        $stmt->execute([":route_id" => $routeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

By enforcing prepared statements consistently, SQL injection is completely prevented at the driver level.',
NULL, 'published', 0, '2026-10-08 11:00:00',
'Building a real project with Core PHP and MySQL — DEVZOOP',
'Request handling, database access, validation, and project structure — lessons from shipping a school bus management system.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Post 5: What I learned while deploying to shared hosting
INSERT INTO `posts` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `status`, `is_featured`, `published_at`, `meta_title`, `meta_description`) VALUES
(5, 4,
'What I learned while deploying to shared hosting',
'what-i-learned-deploying-to-shared-hosting',
'Deployment constraints, FTP/FTPS, environment configuration, and the art of safe releases on shared infrastructure.',
'Shared hosting often receives criticism from modern developer communities, but for many independent projects and small client builds, it remains an economical and accessible reality. Operating within its boundaries forces you to understand web server mechanics.

## 1. Overcoming the lack of shell access

Without SSH access, you cannot run Composer or restart Apache on demand. The solution is treating your local environment or CI runner as the compilation layer: compile dependencies and assets before shipping via FTPS.

## 2. Protecting configuration files

On shared Apache hosts, store configuration files containing database credentials outside the `public_html` root directory whenever possible, or protect them with strict Apache directives:

```bash:.htaccess
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>
```',
NULL, 'published', 0, '2026-10-01 16:45:00',
'What I learned while deploying to shared hosting — DEVZOOP',
'Deployment constraints, FTP/FTPS, environment configuration, and the art of safe releases on shared infrastructure.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Post 6: From feature branch to production: my Git workflow
INSERT INTO `posts` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `status`, `is_featured`, `published_at`, `meta_title`, `meta_description`) VALUES
(6, 1,
'From feature branch to production: my Git workflow',
'from-feature-branch-to-production-my-git-workflow',
'Branches, pull requests, status checks, and merging — a simple Git workflow that keeps solo projects safe.',
'Even when building solo, treating your Git repository like a team codebase provides an invaluable safety net. Using pull requests forces you to slow down, review unified diffs, and ensure CI status checks pass before code reaches production.

## 1. The four-step discipline

* **Never commit to main:** Every change starts on a descriptive branch like `feat/`, `fix/`, or `docs/`.
* **Self-review on PR creation:** Reading the diff on GitHub with fresh eyes catches typos, leftover debug code, and forgotten files.
* **Wait for green checks:** If automated tests fail, fix them on the feature branch rather than in production.
* **Squash or clean merge:** Keeps the production history legible and easily bisectable.',
NULL, 'published', 0, '2026-09-24 13:20:00',
'From feature branch to production: my Git workflow — DEVZOOP',
'Branches, pull requests, status checks, and merging — a simple Git workflow that keeps solo projects safe.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 4. Map Post Tags
-- Post 1 tags
INSERT INTO `post_tags` (`post_id`, `tag_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
-- Post 2 tags
(2, 6), (2, 7), (2, 8), (2, 9),
-- Post 3 tags
(3, 10), (3, 11), (3, 12), (3, 13),
-- Post 4 tags
(4, 11), (4, 14), (4, 15), (4, 16),
-- Post 5 tags
(5, 3), (5, 17), (5, 18), (5, 19),
-- Post 6 tags
(6, 4), (6, 20), (6, 21), (6, 22)
ON DUPLICATE KEY UPDATE `post_id` = VALUES(`post_id`);
