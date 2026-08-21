<?php
/**
 * Embeddable job widget (#23).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Embed
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_rewrite'));
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'serve_embed'), 1);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 65);
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function query_vars($vars)
    {
        $vars[] = 'mjb_embed';
        return $vars;
    }

    /**
     * /jobs/embed.js and /jobs/embed/frame/
     */
    public static function register_rewrite()
    {
        add_rewrite_rule('^jobs/embed\\.js$', 'index.php?mjb_embed=js', 'top');
        add_rewrite_rule('^jobs/embed/frame/?$', 'index.php?mjb_embed=frame', 'top');
    }

    /**
     * Serve embed assets.
     */
    public static function serve_embed()
    {
        $mode = get_query_var('mjb_embed', '');
        if ($mode === '' || $mode === false || $mode === null) {
            return;
        }

        if ($mode === 'js') {
            self::output_loader_js();
            exit;
        }

        if ($mode === 'frame') {
            self::output_frame_html();
            exit;
        }
    }

    /**
     * External sites load this script once.
     */
    private static function output_loader_js()
    {
        nocache_headers();
        header('Content-Type: application/javascript; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        $frame = esc_url_raw(home_url('/jobs/embed/frame/'));
        echo "(function(){\n";
        echo "var s=document.currentScript;if(!s)return;\n";
        echo "var limit=s.getAttribute('data-limit')||'5';\n";
        echo "var cat=s.getAttribute('data-category')||'';\n";
        echo "var loc=s.getAttribute('data-location')||'';\n";
        echo "var f=document.createElement('iframe');\n";
        echo "f.src=" . wp_json_encode($frame) . "+'?limit='+encodeURIComponent(limit)+'&category='+encodeURIComponent(cat)+'&location='+encodeURIComponent(loc);\n";
        echo "f.style.cssText='width:100%;min-height:320px;border:0;border-radius:8px;';\n";
        echo "f.title='Job listings';\n";
        echo "f.loading='lazy';\n";
        echo "s.parentNode.insertBefore(f,s.nextSibling);\n";
        echo "})();\n";
    }

    /**
     * Lightweight iframe content listing recent jobs.
     */
    private static function output_frame_html()
    {
        $limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 5;
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        $tax = array();
        if (!empty($_GET['category'])) {
            $tax[] = array(
                'taxonomy' => 'job_listing_category',
                'field' => 'slug',
                'terms' => sanitize_title(wp_unslash($_GET['category'])),
            );
        }
        if (!empty($_GET['location'])) {
            $tax[] = array(
                'taxonomy' => 'job_listing_location',
                'field' => 'slug',
                'terms' => sanitize_title(wp_unslash($_GET['location'])),
            );
        }
        if (!empty($tax)) {
            $args['tax_query'] = $tax;
        }
        $jobs = get_posts($args);

        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        // Allow embedding on third-party sites.
        header_remove('X-Frame-Options');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:12px;color:#111;background:#fff}
            h1{font-size:1rem;margin:0 0 12px}
            ul{list-style:none;margin:0;padding:0}
            li{padding:10px 0;border-bottom:1px solid #e5e7eb}
            a{color:#0f766e;text-decoration:none;font-weight:600}
            a:hover{text-decoration:underline}
            .meta{font-size:.85rem;color:#6b7280;margin-top:2px}
            .powered{margin-top:12px;font-size:.75rem;color:#9ca3af}
        </style></head><body>';
        echo '<h1>' . esc_html__('Latest jobs', 'modern-job-board') . '</h1>';
        if (empty($jobs)) {
            echo '<p>' . esc_html__('No jobs available right now.', 'modern-job-board') . '</p>';
        } else {
            echo '<ul>';
            foreach ($jobs as $job) {
                $company = get_post_meta($job->ID, '_company_name', true);
                echo '<li><a href="' . esc_url(get_permalink($job)) . '" target="_blank" rel="noopener">' . esc_html($job->post_title) . '</a>';
                if ($company) {
                    echo '<div class="meta">' . esc_html($company) . '</div>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '<p class="powered"><a href="' . esc_url(home_url('/jobs/')) . '" target="_blank" rel="noopener">' . esc_html(get_bloginfo('name')) . '</a></p>';
        echo '</body></html>';
    }

    /**
     * Admin: copy-paste snippet.
     */
    public static function admin_menu()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Embed widget', 'modern-job-board'),
            __('Embed widget', 'modern-job-board'),
            'manage_options',
            'mjb-embed',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Admin page content.
     */
    public static function render_admin()
    {
        $src = esc_url(home_url('/jobs/embed.js'));
        $snippet = '<script src="' . $src . '" data-limit="5" async></script>';
        echo '<div class="wrap"><h1>' . esc_html__('Embeddable job widget', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Paste this snippet on any external site to show your latest jobs.', 'modern-job-board') . '</p>';
        echo '<textarea class="large-text code" rows="4" readonly onclick="this.select()">' . esc_textarea($snippet) . '</textarea>';
        echo '<p class="description">' . esc_html__('Optional attributes: data-limit, data-category, data-location (slugs).', 'modern-job-board') . '</p>';
        echo '</div>';
    }
}
