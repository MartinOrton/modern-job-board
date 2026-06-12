<?php
/**
 * Import the marketing landing page as valid, separate Gutenberg blocks.
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
 * @param string $content Raw content.
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
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @return array<int, array<string, mixed>>
 */
function mjb_prune_freeform_blocks(array $blocks) {
    $pruned = array();

    foreach ($blocks as $block) {
        if (null === $block['blockName']) {
            if ('' === trim($block['innerHTML'])) {
                continue;
            }
        } elseif (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = mjb_prune_freeform_blocks($block['innerBlocks']);
        }

        $pruned[] = $block;
    }

    return $pruned;
}

/**
 * @param string $markup Block markup.
 * @return string
 */
function mjb_serialize_parsed_blocks($markup) {
    return serialize_blocks(mjb_prune_freeform_blocks(parse_blocks($markup)));
}

/**
 * @param string $html        HTML fragment.
 * @param string $class_prefix Div class prefix (e.g. feature-card).
 * @return array<int, array<int, string>>
 */
function mjb_match_class_divs($html, $class_prefix) {
    $pattern = '/<div class="(' . preg_quote($class_prefix, '/') . '[^"]*)">(.*?)<\/div>\s*(?=(?:<!--.*?-->\s*)*(?:<div class="' . preg_quote($class_prefix, '/') . '|<\/div>\s*<\/div>))/is';

    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    return $matches;
}

/**
 * @param string $inner Inner block markup.
 * @return string
 */
function mjb_container_wrap($inner) {
    return mjb_group_block('container', $inner);
}

/**
 * @param string $html HTML fragment.
 * @return string
 */
function mjb_html_block($html) {
    $html = trim($html);

    return "<!-- wp:html -->\n{$html}\n<!-- /wp:html -->";
}

/**
 * @param int    $level     Heading level.
 * @param string $text      Heading text (may include inline HTML).
 * @param string $className Optional class.
 * @return string
 */
function mjb_heading_block($level, $text, $className = '') {
    $attrs = array('level' => (int) $level);
    if ($className) {
        $attrs['className'] = $className;
    }

    $json = wp_json_encode($attrs);
    $class_attr = $className ? ' class="wp-block-heading ' . esc_attr($className) . '"' : ' class="wp-block-heading"';

    return "<!-- wp:heading {$json} -->\n<h{$level}{$class_attr}>{$text}</h{$level}>\n<!-- /wp:heading -->";
}

/**
 * @param string $text      Paragraph text (may include inline HTML).
 * @param string $className Optional class.
 * @return string
 */
function mjb_paragraph_block($text, $className = '') {
    $attrs = $className ? array('className' => $className) : array();
    $json  = $attrs ? wp_json_encode($attrs) : '';
    $class_attr = $className ? ' class="' . esc_attr($className) . '"' : '';

    return '<!-- wp:paragraph' . ( $json ? ' ' . $json : '' ) . " -->\n<p{$class_attr}>{$text}</p>\n<!-- /wp:paragraph -->";
}

/**
 * @param string $className Group class names.
 * @param string $inner     Inner block markup.
 * @param string $tagName   Wrapper tag.
 * @param string $align     Optional alignment.
 * @param string $anchor    Optional HTML anchor/id.
 * @param string $layout    Group layout type (default avoids inner-container wrappers).
 * @return string
 */
function mjb_group_block($className, $inner, $tagName = 'div', $align = '', $anchor = '', $layout = 'default') {
    $attrs = array(
        'className' => $className,
        'layout'    => array('type' => $layout),
    );

    if ($tagName && 'div' !== $tagName) {
        $attrs['tagName'] = $tagName;
    }
    if ($align) {
        $attrs['align'] = $align;
    }
    if ($anchor) {
        $attrs['anchor'] = $anchor;
    }

    $json         = wp_json_encode($attrs);
    $wrapper      = $tagName && 'div' !== $tagName ? $tagName : 'div';
    $align_class  = $align ? ' align' . $align : '';
    $wrapper_class = trim('wp-block-group' . $align_class . ( $className ? ' ' . $className : '' ));
    $id_attr      = $anchor ? ' id="' . esc_attr($anchor) . '"' : '';

    return "<!-- wp:group {$json} -->\n<{$wrapper} class=\"{$wrapper_class}\"{$id_attr}>\n{$inner}\n</{$wrapper}>\n<!-- /wp:group -->";
}

/**
 * @param string $className Section classes.
 * @param string $inner     Inner block markup.
 * @param string $anchor    Optional section id.
 * @return string
 */
function mjb_section_block($className, $inner, $anchor = '') {
    return mjb_group_block($className, $inner, 'section', 'full', $anchor);
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
 * @param string $main_html Landing page main content.
 * @return array<int, array{attrs:string, inner:string}>
 */
function mjb_parse_sections($main_html) {
    preg_match_all('/<section([^>]*)>(.*?)<\/section>/is', $main_html, $matches, PREG_SET_ORDER);
    $sections = array();

    foreach ($matches as $match) {
        $sections[] = array(
            'attrs' => $match[1],
            'inner' => trim($match[2]),
        );
    }

    return $sections;
}

/**
 * @param string $attrs Section attribute string.
 * @return bool
 */
function mjb_section_has_class($attrs, $class_name) {
    return (bool) preg_match('/\b' . preg_quote($class_name, '/') . '\b/', $attrs);
}

/**
 * @param string $attrs Section attribute string.
 * @return string
 */
function mjb_section_id($attrs) {
    return preg_match('/\bid=["\']([^"\']+)["\']/', $attrs, $match) ? $match[1] : '';
}

/**
 * @param string $attrs Section attribute string.
 * @return string
 */
function mjb_section_classes($attrs) {
    if (!preg_match('/\bclass=["\']([^"\']+)["\']/', $attrs, $match)) {
        return 'section';
    }

    return trim($match[1]);
}

/**
 * @param string $inner Section inner HTML.
 * @return string
 */
function mjb_build_section_header_blocks($inner) {
    $eyebrow = '';
    $title   = '';
    $text    = '';

    if (preg_match('/<span class="eyebrow">(.*?)<\/span>/is', $inner, $match)) {
        $eyebrow = trim($match[1]);
    }
    if (preg_match('/<h2[^>]*>(.*?)<\/h2>/is', $inner, $match)) {
        $title = trim($match[1]);
    }
    if (preg_match('/<div class="section-header[^"]*">[\s\S]*?<p>(.*?)<\/p>/is', $inner, $match)) {
        $text = trim($match[1]);
    }

    $blocks = array();
    if ($eyebrow) {
        $blocks[] = mjb_paragraph_block($eyebrow, 'eyebrow');
    }
    if ($title) {
        $blocks[] = mjb_heading_block(2, $title);
    }
    if ($text) {
        $blocks[] = mjb_paragraph_block($text);
    }

    return mjb_group_block('section-header reveal', implode("\n\n", $blocks));
}

/**
 * @param string $inner Stat section inner HTML.
 * @return string
 */
function mjb_build_stats_blocks($inner) {
    preg_match_all('/<div class="(stat-block[^"]*)">.*?<h3>(.*?)<\/h3>.*?<p>(.*?)<\/p>.*?<\/div>/is', $inner, $matches, PREG_SET_ORDER);
    $children = array();

    foreach ($matches as $match) {
        $children[] = mjb_group_block(
            trim($match[1]),
            mjb_heading_block(3, trim($match[2])) . "\n\n" . mjb_paragraph_block(trim($match[3]))
        );
    }

    return mjb_group_block('container stats-grid', implode("\n\n", $children), 'div', '', '');
}

/**
 * @param string $inner Features section inner HTML.
 * @return string
 */
function mjb_build_features_blocks($inner) {
    $matches = mjb_match_class_divs($inner, 'feature-card');
    $cards    = array();

    foreach ($matches as $match) {
        $card_inner = $match[2];
        $icon       = '';
        $title      = '';
        $text       = '';

        if (preg_match('/<div class="feature-icon">(.*?)<\/div>/is', $card_inner, $icon_match)) {
            $icon = '<div class="feature-icon">' . trim($icon_match[1]) . '</div>';
        }
        if (preg_match('/<h3>(.*?)<\/h3>/is', $card_inner, $title_match)) {
            $title = trim($title_match[1]);
        }
        if (preg_match('/<p>(.*?)<\/p>/is', $card_inner, $text_match)) {
            $text = trim($text_match[1]);
        }

        $card_blocks = array();
        if ($icon) {
            $card_blocks[] = mjb_html_block($icon);
        }
        if ($title) {
            $card_blocks[] = mjb_heading_block(3, $title);
        }
        if ($text) {
            $card_blocks[] = mjb_paragraph_block($text);
        }

        $cards[] = mjb_group_block(trim($match[1]), implode("\n\n", $card_blocks));
    }

    return mjb_container_wrap(
        mjb_build_section_header_blocks($inner) . "\n\n" . mjb_group_block('features-grid', implode("\n\n", $cards), 'div', '', '')
    );
}

/**
 * @param string $inner Developers section inner HTML.
 * @return string
 */
function mjb_build_developers_blocks($inner) {
    preg_match_all('/<div class="(dev-item[^"]*)">.*?<h3>(.*?)<\/h3>.*?<p>(.*?)<\/p>.*?<\/div>/is', $inner, $matches, PREG_SET_ORDER);
    $items    = array();

    foreach ($matches as $match) {
        $items[] = mjb_group_block(
            trim($match[1]),
            mjb_heading_block(3, trim($match[2])) . "\n\n" . mjb_paragraph_block(trim($match[3]))
        );
    }

    $code = '';
    if (preg_match('/<div class="dev-code[^"]*">\s*(<div class="code-window">.*?<\/div>)\s*<\/div>/is', $inner, $match)) {
        $code = trim($match[1]);
    }

    $dev_content = mjb_group_block('dev-content', implode("\n\n", $items));
    $dev_code    = $code ? mjb_group_block('dev-code reveal delay-2', mjb_html_block($code)) : '';

    return mjb_container_wrap(
        mjb_build_section_header_blocks($inner) . "\n\n" . mjb_group_block(
            'dev-grid',
            $dev_content . ( $dev_code ? "\n\n" . $dev_code : '' )
        )
    );
}

/**
 * @param string $inner Pricing section inner HTML.
 * @return string
 */
function mjb_build_pricing_blocks($inner) {
    $matches = mjb_match_class_divs($inner, 'pricing-card');
    $cards = array();

    foreach ($matches as $match) {
        $card_inner = $match[2];
        $blocks     = array();

        if (preg_match('/<div class="recommended-badge">(.*?)<\/div>/is', $card_inner, $badge_match)) {
            $blocks[] = mjb_html_block('<div class="recommended-badge">' . trim($badge_match[1]) . '</div>');
        }
        if (preg_match('/<h3>(.*?)<\/h3>/is', $card_inner, $title_match)) {
            $blocks[] = mjb_heading_block(3, trim($title_match[1]));
        }
        if (preg_match('/<p class="price-sub">(.*?)<\/p>/is', $card_inner, $sub_match)) {
            $blocks[] = mjb_paragraph_block(trim($sub_match[1]), 'price-sub');
        }
        if (preg_match('/<div class="price">(.*?)<\/div>/is', $card_inner, $price_match)) {
            $blocks[] = mjb_html_block('<div class="price">' . trim($price_match[1]) . '</div>');
        }
        if (preg_match('/<ul class="price-features">(.*?)<\/ul>/is', $card_inner, $list_match)) {
            $blocks[] = mjb_html_block('<ul class="price-features">' . trim($list_match[1]) . '</ul>');
        }
        if (preg_match('/<a[^>]+class="btn[^"]*"[^>]*>.*?<\/a>/is', $card_inner, $link_match)) {
            $blocks[] = mjb_html_block(trim($link_match[0]));
        }

        $cards[] = mjb_group_block(trim($match[1]), implode("\n\n", $blocks));
    }

    return mjb_container_wrap(
        mjb_build_section_header_blocks($inner) . "\n\n" . mjb_group_block('pricing-grid', implode("\n\n", $cards))
    );
}

/**
 * @param string $inner CTA section inner HTML.
 * @return string
 */
function mjb_build_cta_blocks($inner) {
    $blocks = array();

    if (preg_match('/<h2>(.*?)<\/h2>/is', $inner, $match)) {
        $blocks[] = mjb_heading_block(2, trim($match[1]));
    }
    if (preg_match('/<p>(.*?)<\/p>/is', $inner, $match)) {
        $blocks[] = mjb_paragraph_block(trim($match[1]));
    }
    if (preg_match('/<a[^>]+class="btn btn-white[^"]*"[^>]*>.*?<\/a>/is', $inner, $match)) {
        $blocks[] = mjb_html_block(trim($match[0]));
    }

    return mjb_group_block('container reveal', implode("\n\n", $blocks));
}

/**
 * @param string $main_html Landing page main content.
 * @return string
 */
function mjb_build_home_block_markup($main_html) {
    $sections = mjb_parse_sections($main_html);
    $parts    = array();

    foreach ($sections as $section) {
        $classes = mjb_section_classes($section['attrs']);
        $anchor  = mjb_section_id($section['attrs']);
        $inner   = $section['inner'];

        if (mjb_section_has_class($section['attrs'], 'hero')) {
            $content = mjb_html_block($inner);
        } elseif (mjb_section_has_class($section['attrs'], 'stats-section')) {
            $content = mjb_build_stats_blocks($inner);
        } elseif ('features' === $anchor || mjb_section_has_class($section['attrs'], 'bg-neutral') && str_contains($inner, 'features-grid')) {
            $content = mjb_build_features_blocks($inner);
        } elseif ('comparison' === $anchor) {
            $table_html = '';
            if (preg_match('/<div class="comparison-table-wrapper[^"]*">\s*(<div class="comparison-table">.*?<\/div>)\s*<\/div>/is', $inner, $match)) {
                $table_html = trim($match[1]);
            } elseif (preg_match('/<div class="comparison-table">.*?<\/div>/is', $inner, $match)) {
                $table_html = trim($match[0]);
            }

            $content = mjb_container_wrap(
                mjb_build_section_header_blocks($inner) . "\n\n" . mjb_group_block(
                    'comparison-table-wrapper reveal delay-1',
                    mjb_html_block($table_html)
                )
            );
        } elseif ('developers' === $anchor) {
            $content = mjb_build_developers_blocks($inner);
        } elseif ('pricing' === $anchor) {
            $content = mjb_build_pricing_blocks($inner);
        } elseif (mjb_section_has_class($section['attrs'], 'cta-section')) {
            $content = mjb_build_cta_blocks($inner);
        } else {
            $content = mjb_html_block($inner);
        }

        $parts[] = mjb_section_block($classes, $content, $anchor);
    }

    return mjb_serialize_parsed_blocks(implode("\n", $parts));
}

/**
 * @param string $main_html Docs page main content.
 * @return string
 */
function mjb_build_docs_block_markup($main_html) {
    $markup = mjb_section_block('section docs-page', mjb_html_block($main_html), 'docs');

    return mjb_serialize_parsed_blocks($markup);
}

/**
 * @param int    $post_id Post ID.
 * @param string $content Raw post content.
 */
function mjb_save_unfiltered_content($post_id, $content) {
    global $wpdb;

    $wpdb->update(
        $wpdb->posts,
        array(
            'post_content' => $content,
        ),
        array(
            'ID' => (int) $post_id,
        ),
        array('%s'),
        array('%d')
    );

    clean_post_cache((int) $post_id);
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
                'ID'          => $existing->ID,
                'post_title'  => $title,
                'post_status' => 'publish',
            )
        );
        mjb_save_unfiltered_content((int) $existing->ID, $content);

        return (int) $existing->ID;
    }

    $post_id = (int) wp_insert_post(
        array(
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_status' => 'publish',
            'post_type'   => 'page',
        )
    );

    if ($post_id) {
        mjb_save_unfiltered_content($post_id, $content);
    }

    return $post_id;
}

$main_html    = mjb_extract_main_html((string) file_get_contents($index_file));
$home_content = mjb_build_home_block_markup($main_html);
$home_id      = mjb_upsert_page('home', 'Home', $home_content);

if (!$home_id) {
    fwrite(STDERR, "Failed to create or update the Home page.\n");
    exit(1);
}

update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

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

$parsed_blocks = parse_blocks($home_content);
echo 'Home page ID: ' . $home_id . PHP_EOL;
echo 'Top-level blocks: ' . count($parsed_blocks) . PHP_EOL;
echo 'Front page: ' . home_url('/') . PHP_EOL;
echo 'Active theme: ' . wp_get_theme()->get('Name') . PHP_EOL;