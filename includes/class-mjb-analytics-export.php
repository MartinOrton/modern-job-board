<?php
/**
 * Board analytics export CSV (#40).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Analytics_Export
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 70);
        add_action('admin_post_mjb_export_analytics', array(__CLASS__, 'handle_export'));
    }

    /**
     * Admin page.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Analytics export', 'modern-job-board'),
            __('Analytics export', 'modern-job-board'),
            'manage_options',
            'mjb-analytics-export',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * UI.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=mjb_export_analytics'), 'mjb_export_analytics');
        echo '<div class="wrap"><h1>' . esc_html__('Analytics export', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Download CSV of job views, applications, and revenue meta (when available).', 'modern-job-board') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">' . esc_html__('Download CSV', 'modern-job-board') . '</a></p>';
        echo '</div>';
    }

    /**
     * Stream CSV.
     */
    public static function handle_export()
    {
        if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mjb_export_analytics')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }

        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'expired', 'pending', 'draft'),
            'posts_per_page' => 2000,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=mjb-analytics-' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('job_id', 'title', 'status', 'author', 'views', 'applications', 'created'));

        foreach ($jobs as $job) {
            $views = (int) get_post_meta($job->ID, '_mjb_views', true);
            if (!$views) {
                $views = (int) get_post_meta($job->ID, '_job_views', true);
            }
            $apps = get_posts(array(
                'post_type' => 'job_application',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array('key' => '_job_applied_for', 'value' => $job->ID),
                ),
            ));
            // Count accurately.
            $app_q = new WP_Query(array(
                'post_type' => 'job_application',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array('key' => '_job_applied_for', 'value' => (string) $job->ID),
                ),
            ));
            $app_count = (int) $app_q->found_posts;

            fputcsv($out, array(
                $job->ID,
                $job->post_title,
                $job->post_status,
                $job->post_author,
                $views,
                $app_count,
                $job->post_date,
            ));
        }
        fclose($out);
        exit;
    }
}
