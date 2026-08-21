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
        require_once dirname(__FILE__) . '/class-mjb-private-uploads.php';
        require_once dirname(__FILE__) . '/class-mjb-resumes.php';

        self::register_roles();
        self::schedule_cron();
        self::schedule_import_cron();
        MJB_Private_Uploads::ensure_directories();
        MJB_Resumes::ensure_secure_directory();

        require_once dirname(__FILE__) . '/class-mjb-job-routes.php';
        require_once dirname(__FILE__) . '/class-mjb-job-permalinks.php';
        require_once dirname(__FILE__) . '/class-mjb-login.php';
        require_once dirname(__FILE__) . '/class-mjb-pretty-urls.php';
        MJB_Job_Routes::register_rewrites();
        MJB_Job_Permalinks::register_rewrites();
        MJB_Login::register_rewrites();
        MJB_Pretty_Urls::register_rewrites();

        require_once dirname(__FILE__) . '/class-mjb-page-resolver.php';
        require_once dirname(__FILE__) . '/class-mjb-page-wizard.php';
        MJB_Page_Wizard::create_missing_pages();
        MJB_Page_Wizard::ensure_jobs_page_hierarchy();

        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('mjb_daily_cron_event');
        require_once dirname(__FILE__) . '/class-mjb-import-scheduler.php';
        MJB_Import_Scheduler::clear_events();
        flush_rewrite_rules();
    }

    /**
     * Register recruiter and candidate roles.
     *
     * Safe to re-run: never wipes capabilities on existing roles; only repairs
     * missing caps and keeps the public display name as "Recruiter".
     */
    private static function register_roles()
    {
        $caps = array(
            'read' => true,
            'upload_files' => true,
        );

        self::ensure_role('employer', __('Recruiter', 'modern-job-board'), $caps);
        self::ensure_role('candidate', __('Candidate', 'modern-job-board'), $caps);
    }

    /**
     * Ensure a role exists with the given display name and capabilities.
     *
     * @param string               $slug
     * @param string               $display_name
     * @param array<string, bool>  $caps
     */
    private static function ensure_role($slug, $display_name, array $caps)
    {
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return;
        }

        $role = get_role($slug);
        if (!$role) {
            add_role($slug, $display_name, $caps);
            return;
        }

        foreach ($caps as $cap => $grant) {
            if ($grant && !$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }

        // Update display name without rewriting the whole roles option
        // (rewriting the option array can drop capabilities if incomplete).
        global $wp_roles;
        if (!isset($wp_roles) || !($wp_roles instanceof WP_Roles)) {
            $wp_roles = wp_roles();
        }
        if (isset($wp_roles->roles[$slug]) && is_array($wp_roles->roles[$slug])) {
            if (!isset($wp_roles->roles[$slug]['capabilities']) || !is_array($wp_roles->roles[$slug]['capabilities'])) {
                $wp_roles->roles[$slug]['capabilities'] = $caps;
            }
            $wp_roles->roles[$slug]['name'] = $display_name;
            $wp_roles->role_names[$slug] = $display_name;
            update_option($wp_roles->role_key, $wp_roles->roles);
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

    /**
     * Schedule hourly import backfill cron if not already scheduled.
     */
    private static function schedule_import_cron()
    {
        require_once dirname(__FILE__) . '/class-mjb-import-scheduler.php';
        MJB_Import_Scheduler::schedule_events();
    }
}