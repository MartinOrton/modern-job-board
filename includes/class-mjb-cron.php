<?php
/**
 * Modern Job Board Cron Jobs
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Cron
{

    /**
     * Initialize Cron.
     */
    public function init()
    {
        add_action('init', array($this, 'schedule_events'));
        add_action('mjb_daily_cron_event', array($this, 'check_for_expired_jobs'));
    }

    /**
     * Schedule Events.
     */
    public function schedule_events()
    {
        if (!wp_next_scheduled('mjb_daily_cron_event')) {
            wp_schedule_event(time(), 'daily', 'mjb_daily_cron_event');
        }
    }

    /**
     * Check for expired jobs.
     */
    public function check_for_expired_jobs()
    {
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_job_expires',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE',
                ),
            ),
            'fields' => 'ids',
        );

        $expired_jobs = get_posts($args);

        if (!empty($expired_jobs)) {
            foreach ($expired_jobs as $job_id) {
                $update_args = array(
                    'ID' => $job_id,
                    'post_status' => 'expired',
                );
                wp_update_post($update_args);
            }
        }
    }
}
