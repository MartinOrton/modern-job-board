<?php
/**
 * Import the marketing landing page as the WordPress static home page.
 *
 * Usage: php bin/import-landing-home.php "C:\path\to\wordpress" ["C:\path\to\modern-job-board-website"]
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/import-landing-home.php /path/to/wordpress [/path/to/website]\n");
    exit(1);
}

$wp_root      = rtrim($argv[1], "\\/");
$website_root = isset($argv[2]) ? rtrim($argv[2], "\\/") : dirname(__DIR__) . '/../modern-job-board-website';
$index_file   = $website_root . '/index.html';
$docs_file    = $website_root . '/docs/index.html';

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

if (!is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Could not find wp-load.php in {$wp_root}\n");
    exit(1);
}

require $wp_root . '/wp-load.php';

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress failed to load.\n");
    exit(1);
}

if (!is_file($index_file)) {
    fwrite(STDERR, "Landing index not found: {$index_file}\n");
    exit(1);
}

/**
 * @param string $html Raw HTML file contents.
 * @return string
 */
function mjb_extract_main_html($html) {
    if (!preg_match('/<main[^>]*>(.*)<\/main>/is', $html, $matches)) {
        fwrite(STDERR, "Could not find <main> in landing HTML.\n");
        exit(1);
    }

    $main = $matches[1];

    $replacements = array(
        'href="docs/index.html"' => 'href="/docs/"',
        "href='docs/index.html'" => "href='/docs/'",
        'href="/jobs/" data-demo-path="/jobs/"' => 'href="/jobs/"',
        "href='/jobs/' data-demo-path='/jobs/'" => "href='/jobs/'",
    );

    return str_replace(array_keys($replacements), array_values($replacements), $main);
}

/**
 * @param string $html HTML fragment.
 * @return string
 */
function mjb_wrap_landing_page_content($html) {
    return "<!-- wp:html -->\n<main>{$html}</main>\n<!-- /wp:html -->";
}

/**
 * @param string $slug Page slug.
 * @param string $title Page title.
 * @param string $content Post content.
 * @return int
 */
function mjb_upsert_page($slug, $title, $content) {
    $existing = get_page_by_path($slug, OBJECT, 'page');

    if ($existing) {
        wp_update_post(
            array(
                'ID'           => $existing->ID,
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
            )
        );

        return (int) $existing->ID;
    }

    return (int) wp_insert_post(
        array(
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        )
    );
}

$home_content = mjb_wrap_landing_page_content(mjb_extract_main_html((string) file_get_contents($index_file)));
$home_id      = mjb_upsert_page('home', 'Home', $home_content);

if (!$home_id) {
    fwrite(STDERR, "Failed to create or update the Home page.\n");
    exit(1);
}

update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

if (is_file($docs_file)) {
    $docs_body    = mjb_extract_main_html((string) file_get_contents($docs_file));
    $docs_content = mjb_wrap_landing_page_content($docs_body);
    $docs_id      = mjb_upsert_page('docs', 'Documentation', $docs_content);
    echo 'Docs page ID: ' . intval($docs_id) . PHP_EOL;
}

$theme = 'modern-job-board-theme';
if (!wp_get_theme($theme)->exists()) {
    fwrite(STDERR, "Theme not found: {$theme}\n");
    exit(1);
}

switch_theme($theme);

echo 'Home page ID: ' . $home_id . PHP_EOL;
echo 'Front page: ' . home_url('/') . PHP_EOL;
echo 'Active theme: ' . wp_get_theme()->get('Name') . PHP_EOL;