<?php
/**
 * Blog integration package (#30) — guided setup for jobs + content marketing.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Blog_Package
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 74);
        add_shortcode('mjb_related_jobs', array(__CLASS__, 'related_jobs'));
        add_filter('the_content', array(__CLASS__, 'append_related_jobs'), 20);
    }

    /**
     * Admin guide.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Blog + jobs', 'modern-job-board'),
            __('Blog + jobs', 'modern-job-board'),
            'manage_options',
            'mjb-blog-package',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Setup checklist UI.
     */
    public static function render_admin()
    {
        echo '<div class="wrap"><h1>' . esc_html__('Jobs + content marketing setup', 'modern-job-board') . '</h1>';
        echo '<ol class="mjb-blog-setup">';
        echo '<li>' . esc_html__('Create categories that mirror job categories (e.g. Engineering, Design).', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('Write career guides and embed [mjb_related_jobs category="engineering"].', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('Related jobs auto-append to blog posts when a matching category slug exists.', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('Link from the jobs page footer to your career blog for SEO equity.', 'modern-job-board') . '</li>';
        echo '</ol></div>';
    }

    /**
     * Shortcode of related jobs by category slug.
     *
     * @param array $atts
     * @return string
     */
    public static function related_jobs($atts)
    {
        $atts = shortcode_atts(array(
            'category' => '',
            'limit' => 5,
        ), $atts, 'mjb_related_jobs');

        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(12, (int) $atts['limit'])),
        );
        if ($atts['category'] !== '') {
            $args['tax_query'] = array(array(
                'taxonomy' => 'job_listing_category',
                'field' => 'slug',
                'terms' => sanitize_title($atts['category']),
            ));
        }
        $jobs = get_posts($args);
        if (empty($jobs)) {
            return '';
        }
        ob_start();
        echo '<aside class="mjb-related-jobs"><h3>' . esc_html__('Related jobs', 'modern-job-board') . '</h3><ul>';
        foreach ($jobs as $job) {
            echo '<li><a href="' . esc_url(get_permalink($job)) . '">' . esc_html($job->post_title) . '</a></li>';
        }
        echo '</ul></aside>';
        return (string) ob_get_clean();
    }

    /**
     * Auto-append on posts when category slug matches a job category.
     *
     * @param string $content
     * @return string
     */
    public static function append_related_jobs($content)
    {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $cats = get_the_category();
        if (empty($cats)) {
            return $content;
        }
        $slug = $cats[0]->slug;
        $html = self::related_jobs(array('category' => $slug, 'limit' => 5));
        return $content . $html;
    }
}
