<?php
/**
 * Modern Job Board Activation / Deactivation
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Activator
{
    /**
     * Run on plugin activation.
     */
    public static function activate()
    {
        require_once dirname(__FILE__) . '/class-mjb-resumes.php';

        self::register_roles();
        self::schedule_cron();
        MJB_Resumes::ensure_secure_directory();

        require_once dirname(__FILE__) . '/class-mjb-job-routes.php';
        MJB_Job_Routes::register_rewrites();

        require_once dirname(__FILE__) . '/class-mjb-page-resolver.php';
        require_once dirname(__FILE__) . '/class-mjb-page-wizard.php';
        MJB_Page_Wizard::create_missing_pages();

        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('mjb_daily_cron_event');
        flush_rewrite_rules();
    }

    /**
     * Register employer and candidate roles.
     */
    private static function register_roles()
    {
        if (!get_role('employer')) {
            add_role(
                'employer',
                __('Employer', 'modern-job-board'),
                array(
                    'read' => true,
                    'upload_files' => true,
                )
            );
        }

        if (!get_role('candidate')) {
            add_role(
                'candidate',
                __('Candidate', 'modern-job-board'),
                array(
                    'read' => true,
                    'upload_files' => true,
                )
            );
        }
    }

    /**
     * Schedule daily cron if not already scheduled.
     */
    private static function schedule_cron()
    {
        if (!wp_next_scheduled('mjb_daily_cron_event')) {
            wp_schedule_event(time(), 'daily', 'mjb_daily_cron_event');
        }
    }
}