<?php
/**
 * DEVZOOP SEO & Metadata Engine
 * Generates Open Graph tags, Twitter Cards, canonical links, and JSON-LD schema.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Render complete SEO HTML head tags.
 *
 * @param array{
 *   title?: string,
 *   description?: string,
 *   canonical?: string,
 *   type?: string,
 *   image?: string,
 *   article?: array<string, mixed>
 * } $params
 * @return string HTML metadata tags
 */
function render_seo_tags(array $params = []): string {
    $siteName = 'DEVZOOP';
    $defaultTitle = 'DEVZOOP — Rahul Mandal | Independent Web Developer';
    $defaultDesc = 'Developer by practice, not by title. Building practical web applications, client projects, and experiments with PHP, MySQL, and JavaScript.';
    $defaultImage = BASE_URL . '/assets/img/rahul-hero.jpg';

    $title = !empty($params['title']) ? $params['title'] . ' — ' . $siteName : $defaultTitle;
    $desc = !empty($params['description']) ? $params['description'] : $defaultDesc;
    $canonical = !empty($params['canonical']) ? $params['canonical'] : BASE_URL;
    $type = !empty($params['type']) ? $params['type'] : 'website';
    $image = !empty($params['image']) ? $params['image'] : $defaultImage;

    $html = '';
    $html .= '<title>' . e($title) . "</title>\n";
    $html .= '<meta name="description" content="' . e($desc) . "\">\n";
    $html .= '<link rel="canonical" href="' . e($canonical) . "\">\n";

    // Open Graph
    $html .= '<meta property="og:site_name" content="' . e($siteName) . "\">\n";
    $html .= '<meta property="og:type" content="' . e($type) . "\">\n";
    $html .= '<meta property="og:title" content="' . e($title) . "\">\n";
    $html .= '<meta property="og:description" content="' . e($desc) . "\">\n";
    $html .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
    $html .= '<meta property="og:image" content="' . e($image) . "\">\n";

    // Twitter Card
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '<meta name="twitter:title" content="' . e($title) . "\">\n";
    $html .= '<meta name="twitter:description" content="' . e($desc) . "\">\n";
    $html .= '<meta name="twitter:image" content="' . e($image) . "\">\n";

    // JSON-LD Structured Data for Articles
    if ($type === 'article') {
        $art = $params['article'] ?? [];
        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $art['title'] ?? $title,
            'description'      => $art['excerpt'] ?? $desc,
            'url'              => $canonical,
            'datePublished'    => $art['published_at'] ?? date('c'),
            'dateModified'     => $art['updated_at'] ?? ($art['published_at'] ?? date('c')),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $canonical
            ],
            'author'           => [
                '@type' => 'Person',
                'name'  => 'Rahul Mandal',
                'url'   => BASE_URL
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => 'DEVZOOP',
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => BASE_URL . '/assets/img/rahul-hero.jpg'
                ]
            ],
            'image'            => $image
        ];

        $html .= '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";

        // BreadcrumbList Structured Data for Articles
        $breadcrumbSchema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'Home',
                    'item'     => BASE_URL . '/'
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => 'Blog',
                    'item'     => BASE_URL . '/blog/'
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => (string)($art['title'] ?? $title),
                    'item'     => $canonical
                ]
            ]
        ];
        $html .= '<script type="application/ld+json">' . json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
    }

    return $html;
}
