<?php
/**
 * Modern Job Board Job Analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Analytics
{
    const VIEW_COUNT_META = '_mjb_view_count';
    const VIEW_TRANSIENT_PREFIX = 'mjb_viewed_';

    /**
     * Initialize analytics hooks.
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'maybe_record_job_view'), 20);
    }

    /**
     * Record a unique job view once per visitor per hour.
     */
    public static function maybe_record_job_view()
    {
        if (is_admin() || !is_singular('job_listing')) {
            return;
        }

        $job_id = get_queried_object_id();
        if (!$job_id || get_post_status($job_id) !== 'publish') {
            return;
        }

        self::record_job_view($job_id);
    }

    /**
     * Increment the view counter for a job.
     *
     * @param int $job_id
     */
    public static function record_job_view($job_id)
    {
        $job_id = intval($job_id);
        if (!$job_id) {
            return;
        }

        $visitor_key = self::get_visitor_key();
        $transient_key = self::VIEW_TRANSIENT_PREFIX . $job_id . '_' . $visitor_key;

        if (get_transient($transient_key)) {
            return;
        }

        set_transient($transient_key, 1, HOUR_IN_SECONDS);

        $views = intval(get_post_meta($job_id, self::VIEW_COUNT_META, true));
        update_post_meta($job_id, self::VIEW_COUNT_META, $views + 1);

        do_action('mjb_job_view_recorded', $job_id, $views + 1);
    }

    /**
     * Build a stable visitor key for deduplicating views.
     *
     * @return string
     */
    public static function get_visitor_key()
    {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        return 'ip_' . md5($ip);
    }

    /**
     * Get analytics stats for a single job.
     *
     * @param int $job_id
     * @return array<string, mixed>
     */
    public static function get_job_stats($job_id)
    {
        $job_id = intval($job_id);
        $views = intval(get_post_meta($job_id, self::VIEW_COUNT_META, true));
        $applications = 0;

        $counts = MJB_Dashboard::get_application_counts_for_jobs(array($job_id));
        if (isset($counts[$job_id])) {
            $applications = intval($counts[$job_id]);
        }

        $conversion_rate = $views > 0 ? round(($applications / $views) * 100, 2) : 0.0;

        return array(
            'job_id' => $job_id,
            'title' => get_the_title($job_id),
            'views' => $views,
            'applications' => $applications,
            'conversion_rate' => $conversion_rate,
        );
    }

    /**
     * Get analytics stats for all jobs owned by a user.
     *
     * @param int $user_id
     * @return array<int, array<string, mixed>>
     */
    public static function get_employer_job_stats($user_id)
    {
        $user_id = intval($user_id);
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'author' => $user_id,
        );

        if (user_can($user_id, 'manage_options')) {
            unset($args['author']);
        }

        $job_ids = get_posts($args);
        $app_counts = MJB_Dashboard::get_application_counts_for_jobs($job_ids);
        $stats = array();

        foreach ($job_ids as $job_id) {
            $job_id = intval($job_id);
            $views = intval(get_post_meta($job_id, self::VIEW_COUNT_META, true));
            $applications = isset($app_counts[$job_id]) ? intval($app_counts[$job_id]) : 0;
            $conversion_rate = $views > 0 ? round(($applications / $views) * 100, 2) : 0.0;

            $stats[] = array(
                'job_id' => $job_id,
                'title' => get_the_title($job_id),
                'status' => get_post_status($job_id),
                'views' => $views,
                'applications' => $applications,
                'conversion_rate' => $conversion_rate,
            );
        }

        return $stats;
    }

    /**
     * Summarize employer analytics totals.
     *
     * @param array<int, array<string, mixed>> $job_stats
     * @return array<string, int|float>
     */
    public static function summarize_job_stats($job_stats)
    {
        $totals = array(
            'jobs' => 0,
            'views' => 0,
            'applications' => 0,
            'conversion_rate' => 0.0,
        );

        foreach ($job_stats as $row) {
            $totals['jobs']++;
            $totals['views'] += intval($row['views']);
            $totals['applications'] += intval($row['applications']);
        }

        if ($totals['views'] > 0) {
            $totals['conversion_rate'] = round(($totals['applications'] / $totals['views']) * 100, 2);
        }

        return $totals;
    }
}