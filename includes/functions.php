<?php
/**
 * DEVZOOP Blog Helper Functions
 * XSS escaping, safe markdown/content rendering, image upload security,
 * slug generation, reading time estimation, and pagination.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Escape string for safe HTML output (prevents XSS).
 *
 * @param mixed $str
 * @return string
 */
function e(mixed $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate a clean, URL-safe slug from a string.
 *
 * @param string $text
 * @return string
 */
function slugify(string $text): string {
    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', (string)$text);
    }
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', (string)$text);
    // Trim
    $text = trim((string)$text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', (string)$text);
    // Lowercase
    $text = strtolower((string)$text);

    return empty($text) ? 'post-' . bin2hex(random_bytes(3)) : $text;
}

/**
 * Calculate estimated reading time in minutes.
 *
 * @param string $content
 * @param int $wpm Words per minute (average reading speed: 200)
 * @return string e.g. "5 min read"
 */
function calculate_reading_time(string $content, int $wpm = 200): string {
    $cleanText = strip_tags($content);
    $wordCount = str_word_count($cleanText);
    $minutes = (int)ceil($wordCount / max(1, $wpm));
    $minutes = max(1, $minutes);

    return $minutes . ' min read';
}

/**
 * Format date for blog display.
 *
 * @param string|null $dateStr
 * @param string $format
 * @return string
 */
function format_date(?string $dateStr, string $format = 'M d, Y'): string {
    if (empty($dateStr)) {
        return 'Draft';
    }

    $timestamp = strtotime($dateStr);
    if ($timestamp === false) {
        return 'Invalid Date';
    }

    return date($format, $timestamp);
}

/**
 * Flash messages: Set a flash message for the next request.
 *
 * @param string $type success|error|warning|info
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    init_session();
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash messages.
 *
 * @return array<int, array{type: string, message: string}>
 */
function get_flash(): array {
    init_session();
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Extract H2 headings from content to build the Table of Contents.
 *
 * @param string $content
 * @return array<int, array{id: string, text: string}>
 */
function extract_headings(string $content): array {
    $headings = [];
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
            $title = trim($matches[1]);
            $id = slugify($title);
            $headings[] = [
                'id'   => $id,
                'text' => $title
            ];
        }
    }

    return $headings;
}

/**
 * Syntax highlighter helper for code blocks.
 *
 * @param string $code
 * @param string $lang
 * @return string Safe HTML with syntax tokens
 */
function highlight_code(string $code, string $lang): string {
    $safe = e($code);
    $l = strtolower($lang);

    if ($l === 'yaml' || $l === 'yml') {
        $safe = preg_replace('/(#[^\n]*)/', '<span class="tok-comment">$1</span>', $safe);
        $safe = preg_replace('/^(\s*)([a-zA-Z0-9_-]+)(:)/m', '$1<span class="tok-keyword">$2</span>$3', $safe);
        $safe = preg_replace('/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/', '<span class="tok-string">$1</span>', $safe);
        $safe = preg_replace('/\b(true|false|null|[0-9]+)\b/', '<span class="tok-number">$1</span>', $safe);
        return $safe;
    }

    if ($l === 'bash' || $l === 'sh' || $l === 'shell') {
        $safe = preg_replace('/(#[^\n]*)/', '<span class="tok-comment">$1</span>', $safe);
        $safe = preg_replace('/\b(git|checkout|status|commit|push|pull|run|test|find|echo|grep|cd|mkdir|chmod)\b/', '<span class="tok-keyword">$1</span>', $safe);
        $safe = preg_replace('/(\s)(-[a-zA-Z0-9_-]+)/', '$1<span class="tok-variable">$2</span>', $safe);
        $safe = preg_replace('/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/', '<span class="tok-string">$1</span>', $safe);
        return $safe;
    }

    if ($l === 'javascript' || $l === 'js') {
        $safe = preg_replace('/(\/\/[^\n]*)/', '<span class="tok-comment">$1</span>', $safe);
        $safe = preg_replace('/\b(var|let|const|function|return|if|else|for|while|import|export|class|new|async|await)\b/', '<span class="tok-keyword">$1</span>', $safe);
        $safe = preg_replace('/\b(map|filter|reduce|push|indexOf|forEach|log)\b/', '<span class="tok-property">$1</span>', $safe);
        $safe = preg_replace('/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/', '<span class="tok-string">$1</span>', $safe);
        $safe = preg_replace('/\b([0-9]+)\b/', '<span class="tok-number">$1</span>', $safe);
        return $safe;
    }

    if ($l === 'php') {
        $safe = preg_replace('/(&lt;\?php|\?&gt;)/', '<span class="tok-keyword">$1</span>', $safe);
        $safe = preg_replace('/(\/\/[^\n]*|#[^\n]*)/', '<span class="tok-comment">$1</span>', $safe);
        $safe = preg_replace('/(\$[a-zA-Z0-9_]+)/', '<span class="tok-variable">$1</span>', $safe);
        $safe = preg_replace('/\b(class|public|private|protected|function|return|new|array|int|string|bool|void|declare|static)\b/', '<span class="tok-keyword">$1</span>', $safe);
        $safe = preg_replace('/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/', '<span class="tok-string">$1</span>', $safe);
        return $safe;
    }

    if ($l === 'sql') {
        $safe = preg_replace('/\b(SELECT|FROM|WHERE|INSERT|INTO|UPDATE|DELETE|JOIN|LEFT|RIGHT|INNER|GROUP|BY|ORDER|ASC|DESC|LIMIT|TABLE|CREATE|DROP|ALTER|PRIMARY|KEY|INDEX|FOREIGN|REFERENCES|NOT|NULL|DEFAULT|AND|OR)\b/i', '<span class="tok-keyword">$1</span>', $safe);
        $safe = preg_replace('/(&quot;[^&]*&quot;|&#039;[^&]*&#039;)/', '<span class="tok-string">$1</span>', $safe);
        $safe = preg_replace('/(--[^\n]*)/', '<span class="tok-comment">$1</span>', $safe);
        return $safe;
    }

    return $safe;
}

/**
 * Render structured markdown and special technical components into clean, safe HTML.
 * Supports:
 * - ```lang:filename.ext ... ``` (Rich code editor panel with line numbers & copy button)
 * - ```terminal:title ... ``` (Terminal panel with $ prompts)
 * - ```workflow:title ... ``` (Visual pipeline diagrams)
 * - > **Callout Title:** text (Callout box)
 * - ## Headings with anchor IDs
 * - Paragraphs, lists, bold, italics, inline code
 *
 * @param string $content
 * @return string Safe rendered HTML
 */
function render_article_content(string $content): string {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    $html = '';
    $inCode = false;
    $codeLang = '';
    $codeFilename = '';
    $codeBuffer = [];
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Check code block fence start/end
        if (str_starts_with($trimmed, '```')) {
            if ($inCode) {
                // End of code block
                $codeContent = implode("\n", $codeBuffer);
                $html .= render_code_or_special_block($codeLang, $codeFilename, $codeContent);
                $inCode = false;
                $codeLang = '';
                $codeFilename = '';
                $codeBuffer = [];
            } else {
                // Start of code block
                if ($inList) {
                    $html .= "</ul>\n";
                    $inList = false;
                }
                $fenceInfo = substr($trimmed, 3);
                $parts = explode(':', $fenceInfo, 2);
                $codeLang = trim($parts[0] ?? '');
                $codeFilename = trim($parts[1] ?? '');
                $inCode = true;
                $codeBuffer = [];
            }
            continue;
        }

        if ($inCode) {
            $codeBuffer[] = $line;
            continue;
        }

        // List item (* or -)
        if (preg_match('/^[\*\-]\s+(.+)$/', $trimmed, $m)) {
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            $html .= "  <li>" . format_inline($m[1]) . "</li>\n";
            continue;
        }

        if ($inList && $trimmed === '') {
            $html .= "</ul>\n";
            $inList = false;
            continue;
        }

        // Heading 2: ## Heading
        if (preg_match('/^##\s+(.+)$/', $trimmed, $m)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $title = trim($m[1]);
            $hid = slugify($title);
            $html .= '<h2 id="' . e($hid) . '">' . e($title) . "</h2>\n";
            continue;
        }

        // Heading 3: ### Heading
        if (preg_match('/^###\s+(.+)$/', $trimmed, $m)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $title = trim($m[1]);
            $hid = slugify($title);
            $html .= '<h3 id="' . e($hid) . '">' . e($title) . "</h3>\n";
            continue;
        }

        // Callout: > **Title:** text or > text
        if (str_starts_with($trimmed, '>')) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $quoteText = trim(substr($trimmed, 1));
            $calloutTitle = 'Note';
            if (preg_match('/^\*\*(.+?):\*\*\s*(.+)$/', $quoteText, $qm)) {
                $calloutTitle = trim($qm[1]);
                $quoteText = trim($qm[2]);
            }

            $html .= '<div class="article-callout">' .
                        '<div class="article-callout-header">' .
                            '<i class="ri-lightbulb-line"></i>' .
                            '<span>' . e($calloutTitle) . '</span>' .
                        '</div>' .
                        '<p>' . format_inline($quoteText) . '</p>' .
                     '</div>' . "\n";
            continue;
        }

        // Empty line
        if ($trimmed === '') {
            continue;
        }

        // Regular paragraph
        $html .= '<p>' . format_inline($trimmed) . "</p>\n";
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    if ($inCode) {
        $codeContent = implode("\n", $codeBuffer);
        $html .= render_code_or_special_block($codeLang, $codeFilename, $codeContent);
    }

    return $html;
}

/**
 * Render special fenced code block types: terminal, workflow, or rich code editor.
 *
 * @param string $lang
 * @param string $filename
 * @param string $content
 * @return string
 */
function render_code_or_special_block(string $lang, string $filename, string $content): string {
    $l = strtolower($lang);

    // 1. Terminal Panel
    if ($l === 'terminal') {
        $title = !empty($filename) ? $filename : 'bash — terminal';
        $lines = explode("\n", $content);
        $rowsHtml = '';

        $currentCmd = '';
        $currentOutput = [];

        foreach ($lines as $line) {
            $t = trim($line);
            if (str_starts_with($t, '$ ')) {
                if ($currentCmd !== '') {
                    $rowsHtml .= '<div class="terminal-row">' .
                                    '<div class="terminal-cmd-line">' .
                                        '<span class="terminal-prompt-char">$</span>' .
                                        '<span>' . e($currentCmd) . '</span>' .
                                    '</div>' .
                                    (!empty($currentOutput) ? '<div class="terminal-output-text">' . e(implode("\n", $currentOutput)) . '</div>' : '') .
                                 '</div>';
                }
                $currentCmd = substr($t, 2);
                $currentOutput = [];
            } else {
                if ($line !== '' || !empty($currentOutput)) {
                    $currentOutput[] = $line;
                }
            }
        }

        if ($currentCmd !== '') {
            $rowsHtml .= '<div class="terminal-row">' .
                            '<div class="terminal-cmd-line">' .
                                '<span class="terminal-prompt-char">$</span>' .
                                '<span>' . e($currentCmd) . '</span>' .
                            '</div>' .
                            (!empty($currentOutput) ? '<div class="terminal-output-text">' . e(implode("\n", $currentOutput)) . '</div>' : '') .
                         '</div>';
        }

        return '<div class="terminal-block">' .
                 '<div class="terminal-header">' .
                   '<div class="terminal-controls"><span></span><span></span><span></span></div>' .
                   '<span class="terminal-label">' . e($title) . '</span>' .
                 '</div>' .
                 '<div class="terminal-content">' . $rowsHtml . '</div>' .
               '</div>' . "\n";
    }

    // 2. Workflow Pipeline Diagram
    if ($l === 'workflow') {
        $title = !empty($filename) ? $filename : 'Pipeline Architecture';
        $lines = explode("\n", $content);
        $stepsHtml = '';

        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            // Format: [status] Label | Description
            $status = 'pending';
            $stepIcon = 'ri-checkbox-circle-line';
            if (preg_match('/^\[(done|active|pending)\]\s*([^\|]+)(?:\|\s*(.+))?$/i', $t, $sm)) {
                $status = strtolower($sm[1]);
                $label = trim($sm[2]);
                $desc = trim($sm[3] ?? '');

                if ($status === 'done') $stepIcon = 'ri-check-line';
                if ($status === 'active') $stepIcon = 'ri-loader-4-line';

                $stepCls = $status === 'done' ? 'done' : ($status === 'active' ? 'active' : '');
                $stepsHtml .= '<div class="pipeline-step-item ' . $stepCls . '">' .
                                '<div class="pipeline-step-icon"><i class="' . $stepIcon . '"></i></div>' .
                                '<div class="pipeline-step-text">' .
                                    '<span class="pipeline-step-label">' . e($label) . '</span>' .
                                    (!empty($desc) ? '<span class="pipeline-step-desc">' . e($desc) . '</span>' : '') .
                                '</div>' .
                                '<span class="pipeline-step-state">' . e($status) . '</span>' .
                              '</div>';
            }
        }

        return '<div class="workflow-diagram-block">' .
                 '<div class="workflow-diagram-title">' . e($title) . '</div>' .
                 '<div class="workflow-pipeline-flow">' . $stepsHtml . '</div>' .
               '</div>' . "\n";
    }

    // 3. Rich Code Editor Panel
    $rawLines = explode("\n", $content);
    $gutterHtml = '';
    $lineCount = count($rawLines);
    for ($i = 1; $i <= $lineCount; $i++) {
        $gutterHtml .= '<span>' . $i . '</span>';
    }

    $highlighted = highlight_code($content, $lang);
    $displayFile = !empty($filename) ? $filename : (!empty($lang) ? 'snippet.' . $lang : 'code.txt');
    $displayLang = !empty($lang) ? $lang : 'code';

    return '<div class="code-editor-block">' .
             '<div class="code-editor-header">' .
               '<div class="code-editor-file">' .
                 '<i class="ri-file-code-line"></i>' .
                 '<span>' . e($displayFile) . '</span>' .
               '</div>' .
               '<div class="code-editor-actions">' .
                 '<span class="code-lang-badge">' . e($displayLang) . '</span>' .
                 '<button type="button" class="code-copy-btn" aria-label="Copy code to clipboard">' .
                   '<i class="ri-file-copy-line"></i>' .
                   '<span>Copy</span>' .
                 '</button>' .
               '</div>' .
             '</div>' .
             '<div class="code-editor-body">' .
               '<div class="code-gutter" aria-hidden="true">' . $gutterHtml . '</div>' .
               '<pre class="code-lines-content"><code>' . $highlighted . '</code></pre>' .
             '</div>' .
           '</div>' . "\n";
}

/**
 * Format inline markdown tokens: bold, italic, inline code, and links.
 *
 * @param string $text
 * @return string
 */
function format_inline(string $text): string {
    // Escape HTML first to prevent injection
    $text = e($text);

    // Inline code: `code`
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    // Bold: **text** or __text__
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

    // Italic: *text* or _text_
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/', '<em>$1</em>', $text);

    // Links: [Text](url)
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
        $label = $matches[1];
        $url = $matches[2];
        // Validate URL protocol
        if (preg_match('#^(https?://|/|\./|\.\./)#i', $url)) {
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        }
        return $label;
    }, $text);

    return (string)$text;
}

/**
 * Validate and securely save an uploaded cover image.
 *
 * @param array<string, mixed> $file $_FILES['cover_image']
 * @return array{success: bool, filename: ?string, error: ?string}
 */
function handle_image_upload(array $file): array {
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'File upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $maxMb = round(MAX_UPLOAD_SIZE / (1024 * 1024), 1);
        return ['success' => false, 'filename' => null, 'error' => "Image size exceeds {$maxMb}MB limit."];
    }

    // Inspect real MIME type using fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return ['success' => false, 'filename' => null, 'error' => 'Server fileinfo extension unavailable.'];
    }

    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid image format. Only JPEG, PNG, and WebP are supported.'];
    }

    // Verify image data integrity with getimagesize
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        return ['success' => false, 'filename' => null, 'error' => 'Corrupt or unreadable image file.'];
    }

    // Ensure target storage directory exists
    if (!is_dir(UPLOADS_PATH)) {
        if (!mkdir(UPLOADS_PATH, 0755, true) && !is_dir(UPLOADS_PATH)) {
            return ['success' => false, 'filename' => null, 'error' => 'Failed to create storage upload directory.'];
        }
    }

    // Generate cryptographically random filename
    $ext = $allowedMimes[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = UPLOADS_PATH . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to move uploaded image to storage.'];
    }

    chmod($targetPath, 0644);

    return ['success' => true, 'filename' => $filename, 'error' => null];
}

/**
 * Remove an uploaded cover image from storage.
 *
 * @param string|null $filename
 */
function delete_uploaded_image(?string $filename): void {
    if (empty($filename)) {
        return;
    }

    // Prevent directory traversal
    $clean = basename($filename);
    $path = UPLOADS_PATH . '/' . $clean;

    if (file_exists($path) && is_file($path)) {
        @unlink($path);
    }
}
