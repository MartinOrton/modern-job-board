<?php
/**
 * Import the marketing landing page as separate Gutenberg blocks on the static home page.
 *
 * Usage: php bin/import-landing-home.php "C:\path\to\wordpress" ["C:\path\to\modern-job-board-website"]
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/import-landing-home.php /path/to/wordpress [/path/to/website]\n");
    exit(1);
}

$wp_root      = rtrim($argv[1], "\\/");
$website_root = isset($argv[2]) ? rtrim($argv[2], "\\/") : dirname(__DIR__) . '/../modern-job-board-website';
$theme_root   = $wp_root . '/wp-content/themes/modern-job-board-theme';

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

/**
 * @param string $content Block markup.
 * @return string
 */
function mjb_normalize_landing_urls($content) {
    $replacements = array(
        'href="docs/index.html"' => 'href="/docs/"',
        "href='docs/index.html'" => "href='/docs/'",
        'href="../index.html"' => 'href="/"',
        'href="../index.html#features"' => 'href="/#features"',
        'href="index.html"' => 'href="/docs/"',
        'href="/jobs/" data-demo-path="/jobs/"' => 'href="/jobs/"',
        "href='/jobs/' data-demo-path='/jobs/'" => "href='/jobs/'",
    );

    return str_replace(array_keys($replacements), array_values($replacements), $content);
}

/**
 * @param string $website_root Website source directory.
 * @param string $theme_root   Active theme directory.
 * @return string
 */
function mjb_load_home_block_markup($website_root, $theme_root) {
    $candidates = array(
        $theme_root . '/gutenberg-blocks.html',
        $website_root . '/gutenberg-blocks.html',
    );

    foreach ($candidates as $file) {
        if (!is_file($file)) {
            continue;
        }

        $content = (string) file_get_contents($file);
        $content = preg_replace('/^(\s*<!--(?!\s*wp:).*?-->\s*)+/s', '', $content);

        return trim(mjb_normalize_landing_urls($content));
    }

    fwrite(STDERR, "Could not find gutenberg-blocks.html in the theme or website folder.\n");
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

    return mjb_normalize_landing_urls($matches[1]);
}

/**
 * Build docs page content from semantic blocks where possible.
 *
 * @param string $main_html Docs <main> HTML.
 * @return string
 */
function mjb_build_docs_block_markup($main_html) {
    $blocks  = '';
    $blocks .= "<!-- wp:group {\"tagName\":\"section\",\"align\":\"full\",\"className\":\"section docs-page\",\"layout\":{\"type\":\"constrained\"}} -->\n";
    $blocks .= "<section class=\"wp-block-group alignfull section docs-page\">\n";
    $blocks .= "<!-- wp:html -->\n{$main_html}\n<!-- /wp:html -->\n";
    $blocks .= "</section>\n";
    $blocks .= '<!-- /wp:group -->';

    return $blocks;
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

$home_content = mjb_load_home_block_markup($website_root, $theme_root);
$home_id      = mjb_upsert_page('home', 'Home', $home_content);

if (!$home_id) {
    fwrite(STDERR, "Failed to create or update the Home page.\n");
    exit(1);
}

update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

$docs_file = $website_root . '/docs/index.html';
if (is_file($docs_file)) {
    $docs_content = mjb_build_docs_block_markup(mjb_extract_main_html((string) file_get_contents($docs_file)));
    $docs_id      = mjb_upsert_page('docs', 'Documentation', $docs_content);
    echo 'Docs page ID: ' . intval($docs_id) . PHP_EOL;
}

$theme = 'modern-job-board-theme';
if (!wp_get_theme($theme)->exists()) {
    fwrite(STDERR, "Theme not found: {$theme}\n");
    exit(1);
}

switch_theme($theme);

$block_count = substr_count($home_content, '<!-- wp:');
echo 'Home page ID: ' . $home_id . PHP_EOL;
echo 'Home blocks: ' . $block_count . PHP_EOL;
echo 'Front page: ' . home_url('/') . PHP_EOL;
echo 'Active theme: ' . wp_get_theme()->get('Name') . PHP_EOL;