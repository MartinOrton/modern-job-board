<?php
/**
 * Modern Job Board Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Dashboard
{
    const PAGE_OPTION = 'mjb_employer_dashboard_page_id';

    /**
     * Initialize Dashboard.
     */
    public function init()
    {
        add_shortcode('mjb_dashboard', array($this, 'output_dashboard'));
        add_action('init', array($this, 'handle_post_actions'));
        add_action('wp_ajax_mjb_recruiter_load_tab', array($this, 'ajax_load_tab'));
    }

    /**
     * Handle dashboard POST actions (delete job, update application status).
     */
    public function handle_post_actions()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
        if ($method !== 'POST' || empty($_POST['mjb_dashboard_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['mjb_dashboard_action']));

        if ($action === 'delete_job') {
            $this->handle_delete_job_post();
            return;
        }

        if ($action === 'update_application_status') {
            $this->handle_update_application_status_post();
            return;
        }

        if ($action === 'bulk_update_application_status') {
            $this->handle_bulk_update_application_status_post();
            return;
        }

        if ($action === 'toggle_job_filled') {
            $this->handle_toggle_job_filled();
            return;
        }

        if ($action === 'republish_job') {
            $this->handle_republish_job();
        }
    }

    /**
     * Mark job filled / open from employer dashboard.
     */
    private function handle_toggle_job_filled()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        if (!$job_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mjb_toggle_filled_' . $job_id)) {
            return;
        }
        $job = get_post($job_id);
        $user_id = get_current_user_id();
        if (!$job || $job->post_type !== 'job_listing') {
            return;
        }
        if ((int) $job->post_author !== $user_id && !user_can($user_id, 'manage_options')) {
            return;
        }
        $filled = !empty($_POST['filled']);
        if (class_exists('MJB_Job_Ops')) {
            MJB_Job_Ops::set_filled($job_id, $filled);
        } else {
            update_post_meta($job_id, '_job_filled', $filled ? '1' : '0');
        }
        wp_safe_redirect(self::get_tab_url('jobs'));
        exit;
    }

    /**
     * Republish an expired/draft job (extend expiration).
     */
    private function handle_republish_job()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        if (!$job_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mjb_republish_job_' . $job_id)) {
            return;
        }
        $job = get_post($job_id);
        $user_id = get_current_user_id();
        if (!$job || $job->post_type !== 'job_listing') {
            return;
        }
        if ((int) $job->post_author !== $user_id && !user_can($user_id, 'manage_options')) {
            return;
        }
        if (class_exists('MJB_License') && !MJB_License::can_publish_job($job_id)) {
            wp_safe_redirect(self::get_tab_url('jobs', array('mjb_notice' => 'error_job_cap')));
            exit;
        }
        $duration = (int) get_option('mjb_listing_duration', 30);
        if ($duration < 1) {
            $duration = 30;
        }
        wp_update_post(array(
            'ID' => $job_id,
            'post_status' => 'publish',
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', true),
        ));
        update_post_meta($job_id, '_job_expires', gmdate('Y-m-d', time() + ($duration * DAY_IN_SECONDS)));
        if (class_exists('MJB_Job_Ops')) {
            MJB_Job_Ops::set_filled($job_id, false);
        }
        delete_post_meta($job_id, '_job_publish_at');
        wp_safe_redirect(self::get_tab_url('jobs', array('mjb_notice' => 'success_job_republished')));
        exit;
    }

    /**
     * Trash a job via POST with nonce verification.
     */
    private function handle_delete_job_post()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        if (!$job_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mjb_delete_job_' . $job_id)) {
            return;
        }

        $job = get_post($job_id);
        $user_id = get_current_user_id();

        if ($job && $job->post_type === 'job_listing' && (intval($job->post_author) === $user_id || user_can($user_id, 'manage_options'))) {
            do_action('mjb_before_delete_job', $job_id, $user_id);
            wp_trash_post($job_id);
            do_action('mjb_job_deleted', $job_id, $user_id);
            wp_safe_redirect(self::get_tab_url('jobs'));
            exit;
        }
    }

    /**
     * Update an application workflow status via POST.
     */
    private function handle_update_application_status_post()
    {
        $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
        $status = isset($_POST['application_status']) ? sanitize_key(wp_unslash($_POST['application_status'])) : '';
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

        if (!$application_id || !$job_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mjb_update_application_' . $application_id)) {
            return;
        }

        if (!MJB_Application_Status::user_can_manage($application_id) || !MJB_Application_Status::is_valid($status)) {
            return;
        }

        MJB_Application_Status::update_status($application_id, $status);

        if (class_exists('MJB_Pretty_Urls')) {
            wp_safe_redirect(MJB_Pretty_Urls::dashboard_applications_url($job_id, array(
                'mjb_notice' => 'success_application_status',
            )));
        } else {
            wp_safe_redirect(self::get_page_url(array(
                'action' => 'view_applications',
                'job_id' => $job_id,
                'mjb_notice' => 'success_application_status',
            )));
        }
        exit;
    }

    /**
     * Bulk-update application statuses (#21).
     */
    private function handle_bulk_update_application_status_post()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $status = isset($_POST['bulk_status']) ? sanitize_key(wp_unslash($_POST['bulk_status'])) : '';
        $ids = isset($_POST['application_ids']) && is_array($_POST['application_ids'])
            ? array_map('intval', wp_unslash($_POST['application_ids']))
            : array();

        if (!$job_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mjb_bulk_applications_' . $job_id)) {
            return;
        }
        if (!MJB_Application_Status::is_valid($status) || empty($ids)) {
            return;
        }

        foreach ($ids as $application_id) {
            if ($application_id > 0 && MJB_Application_Status::user_can_manage($application_id)) {
                MJB_Application_Status::update_status($application_id, $status);
            }
        }

        if (class_exists('MJB_Pretty_Urls')) {
            wp_safe_redirect(MJB_Pretty_Urls::dashboard_applications_url($job_id, array(
                'mjb_notice' => 'success_application_status',
            )));
        } else {
            wp_safe_redirect(self::get_page_url(array(
                'action' => 'view_applications',
                'job_id' => $job_id,
                'mjb_notice' => 'success_application_status',
            )));
        }
        exit;
    }

    /**
     * Recruiter portal tabs (separate pretty URLs).
     *
     * @return array<string, array{label:string,icon:string}>
     */
    public static function get_tabs()
    {
        return array(
            'overview' => array(
                'label' => __('Overview', 'modern-job-board'),
                'icon' => 'layout-grid',
            ),
            'jobs' => array(
                'label' => __('Jobs', 'modern-job-board'),
                'icon' => 'briefcase',
            ),
            'applications' => array(
                'label' => __('Applications', 'modern-job-board'),
                'icon' => 'inbox',
            ),
            'packages' => array(
                'label' => __('Packages', 'modern-job-board'),
                'icon' => 'tag',
            ),
        );
    }

    /**
     * Current recruiter dashboard tab.
     *
     * @return string
     */
    public static function get_current_tab()
    {
        if (class_exists('MJB_Pretty_Urls')) {
            $tab = MJB_Pretty_Urls::request_dashboard_tab();
            if ($tab === 'job-applications') {
                return 'applications';
            }
            return $tab;
        }
        if (!empty($_GET['mjb_tab'])) {
            $tab = sanitize_key(wp_unslash($_GET['mjb_tab']));
            if (isset(self::get_tabs()[$tab])) {
                return $tab;
            }
        }
        return 'overview';
    }

    /**
     * URL for a recruiter dashboard tab.
     *
     * @param string $tab
     * @param array  $query_args
     * @return string
     */
    public static function get_tab_url($tab, $query_args = array())
    {
        if (class_exists('MJB_Pretty_Urls') && method_exists('MJB_Pretty_Urls', 'dashboard_tab_url')) {
            return MJB_Pretty_Urls::dashboard_tab_url($tab, $query_args);
        }
        $tab = sanitize_key((string) $tab);
        if ($tab === '' || $tab === 'overview') {
            return self::get_page_url($query_args);
        }
        return self::get_page_url(array_merge(array('mjb_tab' => $tab), $query_args));
    }

    /**
     * Shared post-a-job / packages storefront URLs.
     *
     * @return array{post_job:string,packages:string}
     */
    private static function get_action_urls()
    {
        $post_job_url = class_exists('MJB_Shortcodes')
            ? MJB_Shortcodes::get_job_form_page_url()
            : home_url('/jobs/post-a-job/');
        $packages_url = MJB_Page_Resolver::get_page_url(
            'mjb_packages',
            'mjb_packages_page_id',
            array(),
            '/jobs/packages/'
        );
        if (!$packages_url) {
            $packages_url = home_url('/jobs/packages/');
        }
        return array(
            'post_job' => $post_job_url,
            'packages' => $packages_url,
        );
    }

    /**
     * Output Dashboard (tabbed portal).
     */
    public function output_dashboard($atts)
    {
        unset($atts);

        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to view the dashboard.', 'modern-job-board') . '</p>';
        }

        $user = wp_get_current_user();
        if (!in_array('employer', (array) $user->roles, true) && !user_can($user, 'manage_options')) {
            return '<p>' . __('This dashboard is for recruiter accounts only.', 'modern-job-board') . '</p>';
        }

        ob_start();

        do_action('mjb_before_employer_dashboard', get_current_user_id());

        // Job-specific applications (pretty path or legacy query).
        $apps_job_id = class_exists('MJB_Pretty_Urls')
            ? MJB_Pretty_Urls::request_dashboard_applications_job_id()
            : 0;
        if ($apps_job_id <= 0 && isset($_GET['action']) && $_GET['action'] === 'view_applications' && isset($_GET['job_id'])) {
            $apps_job_id = (int) $_GET['job_id'];
        }
        if ($apps_job_id > 0) {
            $this->output_applications_view($apps_job_id);
            return ob_get_clean();
        }

        $tab = self::get_current_tab();
        $jobs_page = isset($_GET['jobs_page']) ? max(1, (int) $_GET['jobs_page']) : 1;

        self::render_portal_shell_open($tab);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in tab renderers.
        echo $this->render_tab_content($tab, $jobs_page);
        do_action('mjb_after_employer_dashboard', get_current_user_id());
        self::render_portal_shell_close();

        return ob_get_clean();
    }

    /**
     * Open portal shell: container, notices, tab nav, AJAX panel shell.
     * (Page title comes from the theme hero — do not repeat it here.)
     *
     * @param string $active_tab
     */
    private static function render_portal_shell_open($active_tab)
    {
        $active_tab = sanitize_key((string) $active_tab);
        if (!isset(self::get_tabs()[$active_tab])) {
            $active_tab = 'overview';
        }

        // No .mjb-container here — parent theme .container already matches the main menu column.
        echo '<div class="mjb-portal-dashboard mjb-employer-dashboard" data-active-tab="' . esc_attr($active_tab) . '">';

        if (class_exists('MJB_Notices')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MJB_Notices::render() is escaped internally.
            echo MJB_Notices::render();
        }

        self::render_tab_nav($active_tab);

        echo '<div class="mjb-portal-dashboard__panel-wrap">';
        echo '<div class="mjb-portal-loader" id="mjb-recruiter-loader" aria-hidden="true" aria-live="polite">';
        echo '<span class="mjb-spinner" role="status"><span class="screen-reader-text">' . esc_html__('Loading', 'modern-job-board') . '</span></span>';
        echo '</div>';
        echo '<div class="mjb-portal-dashboard__panel" id="mjb-recruiter-panel" role="tabpanel" data-active-tab="' . esc_attr($active_tab) . '" aria-labelledby="mjb-rtab-' . esc_attr($active_tab) . '">';
    }

    /**
     * Close portal shell.
     */
    private static function render_portal_shell_close()
    {
        echo '</div>'; // .mjb-portal-dashboard__panel
        echo '</div>'; // .mjb-portal-dashboard__panel-wrap
        echo '</div>'; // .mjb-portal-dashboard
    }

    /**
     * Admin-style tab navigation (pretty URLs + AJAX data-tab).
     *
     * @param string $active_tab
     */
    private static function render_tab_nav($active_tab)
    {
        $active_tab = sanitize_key((string) $active_tab);
        echo '<nav class="mjb-portal-tabs" aria-label="' . esc_attr__('Recruiter dashboard sections', 'modern-job-board') . '">';
        echo '<ul class="mjb-portal-tabs__list" role="tablist">';

        foreach (self::get_tabs() as $tab_id => $tab) {
            $is_active = ($tab_id === $active_tab);
            $url = self::get_tab_url($tab_id);
            $icon = class_exists('MJB_Icons') ? MJB_Icons::render($tab['icon'], 18) : '';
            echo '<li class="mjb-portal-tabs__item" role="presentation">';
            echo '<a href="' . esc_url($url) . '" class="mjb-portal-tabs__btn' . ($is_active ? ' is-active' : '') . '" role="tab" id="mjb-rtab-' . esc_attr($tab_id) . '" data-tab="' . esc_attr($tab_id) . '" aria-selected="' . ($is_active ? 'true' : 'false') . '" aria-controls="mjb-recruiter-panel">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from MJB_Icons::render().
            echo $icon;
            echo '<span class="mjb-portal-tabs__label">' . esc_html($tab['label']) . '</span>';
            echo '</a></li>';
        }

        echo '</ul></nav>';
    }

    /**
     * Render a single tab’s inner HTML (for full page + AJAX).
     *
     * @param string $tab
     * @param int    $page Jobs pagination page.
     * @return string
     */
    public function render_tab_content($tab, $page = 1)
    {
        $tab = sanitize_key((string) $tab);
        if (!isset(self::get_tabs()[$tab])) {
            $tab = 'overview';
        }
        $page = max(1, (int) $page);
        if ($tab === 'jobs') {
            $_GET['jobs_page'] = $page;
        }

        $urls = self::get_action_urls();
        ob_start();
        switch ($tab) {
            case 'jobs':
                $this->output_jobs_tab($urls);
                break;
            case 'applications':
                $this->output_applications_tab($urls);
                break;
            case 'packages':
                $this->output_packages_tab($urls);
                break;
            case 'overview':
            default:
                $this->output_overview_tab($urls);
                break;
        }
        return (string) ob_get_clean();
    }

    /**
     * AJAX: load a recruiter dashboard tab panel (admin parity).
     */
    public function ajax_load_tab()
    {
        check_ajax_referer('mjb_recruiter_dashboard', 'security');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'modern-job-board')), 403);
        }

        $user = wp_get_current_user();
        if (!in_array('employer', (array) $user->roles, true) && !user_can($user, 'manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }

        $tab = sanitize_key(wp_unslash($_POST['tab'] ?? 'overview'));
        if (!isset(self::get_tabs()[$tab])) {
            $tab = 'overview';
        }
        $page = max(1, (int) ($_POST['page'] ?? 1));

        wp_send_json_success(array(
            'tab' => $tab,
            'page' => $page,
            'url' => self::get_tab_url($tab, $tab === 'jobs' && $page > 1 ? array('jobs_page' => $page) : array()),
            'html' => $this->render_tab_content($tab, $page),
        ));
    }

    /**
     * Overview tab: stats, charts, quick actions only.
     *
     * @param array{post_job:string,packages:string} $urls
     */
    private function output_overview_tab($urls)
    {
        $user_id = get_current_user_id();
        $analytics = MJB_Analytics::get_employer_job_stats($user_id);
        $totals = MJB_Analytics::summarize_job_stats($analytics);
        $credits = (int) get_user_meta($user_id, '_mjb_job_credits', true);
        $active_jobs = (int) count(get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
        )));
        $filled_jobs = 0;
        if (class_exists('MJB_Job_Ops')) {
            $filled_jobs = (int) count(get_posts(array(
                'post_type' => 'job_listing',
                'post_status' => array('publish', 'expired', 'draft', 'pending'),
                'author' => $user_id,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => MJB_Job_Ops::META_FILLED,
                'meta_value' => '1',
                'suppress_filters' => true,
            )));
        }

        $chart_jobs = $analytics;
        usort($chart_jobs, static function ($left, $right) {
            return (int) $right['views'] <=> (int) $left['views'];
        });
        $chart_jobs = array_slice($chart_jobs, 0, 5);

        echo '<div class="mjb-stats-grid" role="group" aria-label="' . esc_attr__('Performance overview', 'modern-job-board') . '">';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $active_jobs) . '</div><div class="mjb-stat-lbl">' . esc_html__('Active Jobs', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $totals['applications']) . '</div><div class="mjb-stat-lbl">' . esc_html__('Applications', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $totals['views']) . '</div><div class="mjb-stat-lbl">' . esc_html__('Job Views', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html($totals['conversion_rate'] . '%') . '</div><div class="mjb-stat-lbl">' . esc_html__('Conversion', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $credits) . '</div><div class="mjb-stat-lbl">' . esc_html__('Credits Left', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $filled_jobs) . '</div><div class="mjb-stat-lbl">' . esc_html__('Filled Jobs', 'modern-job-board') . '</div></div>';
        echo '</div>';

        echo '<h2 class="mjb-section-title">' . esc_html__('Performance Charts', 'modern-job-board') . '</h2>';
        if (class_exists('MJB_Analytics') && method_exists('MJB_Analytics', 'render_admin_charts_html')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo MJB_Analytics::render_admin_charts_html($chart_jobs);
        }

        echo '<h2 class="mjb-section-title">' . esc_html__('Quick Actions', 'modern-job-board') . '</h2>';
        echo '<div class="mjb-features-grid mjb-portal-dashboard__actions">';
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Card HTML is escaped in render_quick_action_card().
        echo self::render_quick_action_card(
            $urls['post_job'],
            'briefcase',
            __('Post a Job', 'modern-job-board'),
            __('Create a new listing and publish it to your board.', 'modern-job-board')
        );
        echo self::render_quick_action_card(
            self::get_tab_url('jobs'),
            'layout-grid',
            __('Manage Jobs', 'modern-job-board'),
            __('Edit listings, mark filled, feature, or remove expired posts.', 'modern-job-board'),
            'jobs'
        );
        echo self::render_quick_action_card(
            self::get_tab_url('applications'),
            'inbox',
            __('Applications', 'modern-job-board'),
            __('Review candidates who applied to your jobs.', 'modern-job-board'),
            'applications'
        );
        echo self::render_quick_action_card(
            self::get_tab_url('packages'),
            'tag',
            __('Packages & Credits', 'modern-job-board'),
            __('Check usage and buy credits or resume access.', 'modern-job-board'),
            'packages'
        );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    /**
     * Jobs workbench tab.
     *
     * @param array{post_job:string,packages:string} $urls
     */
    private function output_jobs_tab($urls)
    {
        $jobs_per_page = 10;
        $jobs_page = isset($_GET['jobs_page']) ? max(1, (int) $_GET['jobs_page']) : 1;

        $args = array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired'),
            'posts_per_page' => $jobs_per_page,
            'paged' => $jobs_page,
            'author' => get_current_user_id(),
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $jobs = new WP_Query($args);
        $job_ids = wp_list_pluck($jobs->posts, 'ID');
        $app_counts = self::get_application_counts_for_jobs($job_ids);

        echo '<div class="mjb-portal-dashboard__section-head">';
        echo '<h2 class="mjb-section-title">' . esc_html__('Your jobs', 'modern-job-board') . '</h2>';
        echo '<div class="mjb-portal-dashboard__toolbar">';
        echo '<a class="btn btn-outline btn-sm" href="' . esc_url(self::get_tab_url('packages')) . '">' . esc_html__('Buy credits', 'modern-job-board') . '</a>';
        echo '<a class="btn btn-primary btn-sm" href="' . esc_url($urls['post_job']) . '">' . esc_html__('Post a job', 'modern-job-board') . '</a>';
        echo '</div>';
        echo '</div>';

        if ($jobs->have_posts()) {
            $this->render_jobs_table($jobs, $app_counts);
            self::render_jobs_pagination((int) $jobs->max_num_pages, $jobs_page);
            wp_reset_postdata();
        } else {
            echo '<div class="mjb-portal-empty">';
            echo '<p>' . esc_html__('You have not posted any jobs yet.', 'modern-job-board') . '</p>';
            echo '<p><a class="btn btn-primary" href="' . esc_url($urls['post_job']) . '">' . esc_html__('Post your first job', 'modern-job-board') . '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Applications hub: jobs with application counts.
     *
     * @param array{post_job:string,packages:string} $urls
     */
    private function output_applications_tab($urls)
    {
        unset($urls);
        $user_id = get_current_user_id();
        $job_ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired'),
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => true,
        ));
        $app_counts = self::get_application_counts_for_jobs($job_ids);

        echo '<div class="mjb-portal-dashboard__section-head">';
        echo '<h2 class="mjb-section-title">' . esc_html__('Applications', 'modern-job-board') . '</h2>';
        echo '</div>';
        echo '<p class="mjb-portal-dashboard__tab-intro">' . esc_html__('Open a job to review candidates, update status, and download resumes.', 'modern-job-board') . '</p>';

        if (empty($job_ids)) {
            echo '<div class="mjb-portal-empty"><p>' . esc_html__('No jobs yet — post a listing to start receiving applications.', 'modern-job-board') . '</p></div>';
            return;
        }

        $headers = array(
            __('Job', 'modern-job-board'),
            __('Status', 'modern-job-board'),
            __('Applications', 'modern-job-board'),
            __('Actions', 'modern-job-board'),
        );
        $grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard mjb-data-grid--apps-hub', count($headers));
        $grid->render_header($headers)->open_body();

        foreach ($job_ids as $job_id) {
            $job_id = (int) $job_id;
            $count = isset($app_counts[$job_id]) ? (int) $app_counts[$job_id] : 0;
            $view_apps = class_exists('MJB_Pretty_Urls')
                ? MJB_Pretty_Urls::dashboard_applications_url($job_id)
                : self::get_page_url(array('action' => 'view_applications', 'job_id' => $job_id));
            $status_obj = get_post_status_object(get_post_status($job_id));
            $status_label = $status_obj ? $status_obj->label : get_post_status($job_id);
            $title = get_the_title($job_id);
            $title_html = '<a class="mjb-data-grid__link" href="' . esc_url($view_apps) . '">' . esc_html($title) . '</a>';
            $actions = '<a class="btn btn-sm btn-outline" href="' . esc_url($view_apps) . '">' . esc_html__('View applications', 'modern-job-board') . '</a>';

            $grid->open_row()
                ->render_cell($title_html, $headers[0])
                ->render_cell('<span class="mjb-status-pill">' . esc_html((string) $status_label) . '</span>', $headers[1])
                ->render_cell('<span class="mjb-data-grid__num">' . esc_html((string) $count) . '</span>', $headers[2])
                ->render_cell($actions, $headers[3])
                ->close_row();
        }

        $grid->close_body()->end();
    }

    /**
     * Packages / credits tab.
     *
     * @param array{post_job:string,packages:string} $urls
     */
    private function output_packages_tab($urls)
    {
        echo '<div class="mjb-portal-dashboard__section-head">';
        echo '<h2 class="mjb-section-title">' . esc_html__('Packages & usage', 'modern-job-board') . '</h2>';
        echo '<div class="mjb-portal-dashboard__toolbar">';
        echo '<a class="btn btn-primary btn-sm" href="' . esc_url($urls['packages']) . '">' . esc_html__('Buy credits', 'modern-job-board') . '</a>';
        echo '</div>';
        echo '</div>';
        echo '<p class="mjb-portal-dashboard__tab-intro">' . esc_html__('See remaining job credits, filled listings, and CV access status.', 'modern-job-board') . '</p>';

        if (class_exists('MJB_Packages') && method_exists('MJB_Packages', 'render_employer_usage')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo MJB_Packages::render_employer_usage(get_current_user_id(), false);
        } else {
            echo '<div class="mjb-portal-empty"><p>' . esc_html__('Package tracking is not available.', 'modern-job-board') . '</p></div>';
        }

        echo '<p class="mjb-portal-dashboard__packages-cta">';
        echo '<a class="btn btn-primary" href="' . esc_url($urls['packages']) . '">' . esc_html__('Browse packages', 'modern-job-board') . '</a>';
        echo '</p>';
    }

    /**
     * Render the jobs data grid for the Jobs tab.
     *
     * @param WP_Query            $jobs
     * @param array<int,int>      $app_counts
     */
    private function render_jobs_table($jobs, $app_counts)
    {
        $job_headers = array(
            __('Title', 'modern-job-board'),
            __('Status', 'modern-job-board'),
            __('Views', 'modern-job-board'),
            __('Applications', 'modern-job-board'),
            __('Conversion', 'modern-job-board'),
            __('Date', 'modern-job-board'),
            __('Actions', 'modern-job-board'),
        );
        $jobs_grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard mjb-data-grid--jobs', count($job_headers));
        $jobs_grid->render_header($job_headers)->open_body();

        while ($jobs->have_posts()) {
            $jobs->the_post();
            $job_id = get_the_ID();
            $edit_link = class_exists('MJB_Pretty_Urls')
                ? MJB_Pretty_Urls::job_edit_url($job_id)
                : MJB_Shortcodes::get_job_form_page_url(array(
                    'action' => 'edit',
                    'job_id' => $job_id,
                ));
            $view_apps_link = class_exists('MJB_Pretty_Urls')
                ? MJB_Pretty_Urls::dashboard_applications_url($job_id)
                : self::get_page_url(array(
                    'action' => 'view_applications',
                    'job_id' => $job_id,
                ));

            $app_count = isset($app_counts[$job_id]) ? intval($app_counts[$job_id]) : 0;
            $view_count = intval(get_post_meta($job_id, MJB_Analytics::VIEW_COUNT_META, true));
            $conversion = $view_count > 0 ? round(($app_count / $view_count) * 100, 1) . '%' : '—';

            $is_filled = class_exists('MJB_Job_Ops') ? MJB_Job_Ops::is_filled($job_id) : false;
            $status_label = get_post_status_object(get_post_status()) ? get_post_status_object(get_post_status())->label : get_post_status();
            if ($is_filled) {
                $status_label .= ' · ' . __('Filled', 'modern-job-board');
            }

            $status_class = 'mjb-status-pill';
            if ($is_filled) {
                $status_class .= ' mjb-status-pill--filled';
            } elseif (get_post_status() === 'publish') {
                $status_class .= ' mjb-status-pill--live';
            } elseif (get_post_status() === 'expired') {
                $status_class .= ' mjb-status-pill--expired';
            }

            $actions_html = self::render_job_row_actions($job_id, $edit_link, $view_apps_link, $is_filled);

            $date_label = get_the_date('M j, Y');
            $job_title = get_the_title();
            /* translators: %s: job title */
            $edit_aria = sprintf(__('Edit %s', 'modern-job-board'), $job_title);
            $title_html = '<a class="mjb-data-grid__link mjb-job-title-link" href="' . esc_url($edit_link) . '" aria-label="' . esc_attr($edit_aria) . '">';
            $title_html .= '<span class="mjb-job-title-link__text">' . esc_html($job_title) . '</span>';
            $title_html .= '<span class="mjb-job-title-link__edit" aria-hidden="true">';
            if (class_exists('MJB_Icons')) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $title_html .= MJB_Icons::render('file-pen', 16);
            }
            $title_html .= '</span>';
            $title_html .= '</a>';

            $jobs_grid->open_row()
                ->render_cell($title_html, $job_headers[0])
                ->render_cell('<span class="' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>', $job_headers[1])
                ->render_cell('<span class="mjb-data-grid__num">' . esc_html((string) $view_count) . '</span>', $job_headers[2])
                ->render_cell('<span class="mjb-data-grid__num">' . esc_html((string) $app_count) . '</span>', $job_headers[3])
                ->render_cell('<span class="mjb-data-grid__num">' . esc_html((string) $conversion) . '</span>', $job_headers[4])
                ->render_cell('<span class="mjb-data-grid__date">' . esc_html($date_label) . '</span>', $job_headers[5])
                ->render_cell($actions_html, $job_headers[6])
                ->close_row();
        }

        $jobs_grid->close_body()->end();
    }

    /**
     * Output Applications View.
     */
    private function output_applications_view($job_id)
    {
        $job_id = intval($job_id);
        $job = get_post($job_id);

        $user_id = get_current_user_id();
        $can_view_job = $job
            && $job->post_type === 'job_listing'
            && (intval($job->post_author) === $user_id || user_can($user_id, 'manage_options'));

        if (!$can_view_job) {
            self::render_portal_shell_open('applications');
            echo '<p>' . esc_html__('Invalid job or permission denied.', 'modern-job-board') . '</p>';
            echo '<p><a class="btn btn-outline btn-sm" href="' . esc_url(self::get_tab_url('applications')) . '">' . esc_html__('&larr; Back to Applications', 'modern-job-board') . '</a></p>';
            self::render_portal_shell_close();
            return;
        }

        self::render_portal_shell_open('applications');
        echo '<div class="mjb-portal-dashboard__section-head">';
        echo '<h2 class="mjb-section-title">' . esc_html(sprintf(__('Applications for "%s"', 'modern-job-board'), get_the_title($job_id))) . '</h2>';
        echo '<a class="btn btn-outline btn-sm" href="' . esc_url(self::get_tab_url('applications')) . '">' . esc_html__('&larr; Back to Applications', 'modern-job-board') . '</a>';
        echo '</div>';

        if (!empty($_GET['mjb_notice']) && $_GET['mjb_notice'] === 'success_application_status') {
            echo '<div class="mjb-message success">' . esc_html__('Application status updated.', 'modern-job-board') . '</div>';
        }

        $args = array(
            'post_type' => 'job_application',
            'meta_key' => '_job_applied_for',
            'meta_value' => $job_id,
            'posts_per_page' => -1,
        );
        $applications = new WP_Query($args);

        global $mjb_custom_fields;
        $application_fields = array();
        if (isset($mjb_custom_fields)) {
            $application_fields = $mjb_custom_fields->get_fields('application');
        }

        if ($applications->have_posts()) {
            // Bulk status toolbar (#21).
            echo '<form method="post" action="" class="mjb-bulk-apps" id="mjb-bulk-apps">';
            wp_nonce_field('mjb_bulk_applications_' . $job_id);
            echo '<input type="hidden" name="mjb_dashboard_action" value="bulk_update_application_status">';
            echo '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '">';
            echo '<p class="mjb-bulk-apps__bar">';
            echo '<label class="screen-reader-text" for="mjb_bulk_status">' . esc_html__('Bulk status', 'modern-job-board') . '</label> ';
            echo '<select name="bulk_status" id="mjb_bulk_status" class="mjb-select">';
            foreach (MJB_Application_Status::get_statuses() as $status_key => $status_label) {
                echo '<option value="' . esc_attr($status_key) . '">' . esc_html($status_label) . '</option>';
            }
            echo '</select> ';
            echo '<button type="submit" class="btn btn-primary btn-sm">' . esc_html__('Apply to selected', 'modern-job-board') . '</button>';
            echo '</p>';

            $app_headers = array(
                '<input type="checkbox" id="mjb-select-all-apps" aria-label="' . esc_attr__('Select all', 'modern-job-board') . '">',
                __('Candidate', 'modern-job-board'),
                __('Email', 'modern-job-board'),
                __('Date', 'modern-job-board'),
                __('Resume', 'modern-job-board'),
            );
            foreach ($application_fields as $field) {
                $app_headers[] = $field['label'];
            }
            $app_headers[] = __('Status', 'modern-job-board');
            $app_headers[] = __('Message', 'modern-job-board');

            $apps_grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard', count($app_headers));
            $apps_grid->render_header($app_headers)->open_body();

            while ($applications->have_posts()) {
                $applications->the_post();
                $app_id = get_the_ID();
                $name = get_post_meta($app_id, '_candidate_name', true);
                $email = get_post_meta($app_id, '_candidate_email', true);
                $resume = MJB_Resumes::get_application_download_url($app_id);
                $current_status = MJB_Application_Status::get_status($app_id);

                $can_view = true;
                if (get_option('mjb_paid_cv_access')) {
                    $can_view = MJB_Resumes::employer_has_cv_access(get_current_user_id(), $app_id);
                }

                if ($can_view) {
                    $name_html = esc_html($name);
                } else {
                    $name_html = '<span class="mjb-blurred">' . esc_html__('Hidden', 'modern-job-board') . '</span>';
                }

                if ($can_view) {
                    $email_html = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    $email_html = '<span class="mjb-blurred">' . esc_html__('Hidden', 'modern-job-board') . '</span>';
                }

                if ($can_view) {
                    if ($resume) {
                        $resume_html = '<a href="' . esc_url($resume) . '" target="_blank" class="btn btn-outline btn-sm">' . esc_html__('Download', 'modern-job-board') . '</a>';
                    } else {
                        $resume_html = '-';
                    }
                } else {
                    $resume_html = '<span class="mjb-locked">' . esc_html__('Locked', 'modern-job-board') . '</span>';
                    $unlock_product_id = get_option('mjb_cv_unlock_product_id');
                    if ($unlock_product_id && function_exists('wc_get_cart_url')) {
                        $cart_url = wc_get_cart_url();
                        $unlock_link = add_query_arg(array(
                            'add-to-cart' => $unlock_product_id,
                            'mjb_unlock_application_id' => $app_id,
                        ), $cart_url);
                        $resume_html .= '<div class="mjb-unlock-wrap"><a href="' . esc_url($unlock_link) . '" class="btn btn-primary btn-sm mjb-unlock-btn">' . esc_html__('Unlock', 'modern-job-board') . '</a></div>';
                    }
                }

                $check_html = '<input type="checkbox" name="application_ids[]" value="' . esc_attr((string) $app_id) . '">';

                $apps_grid->open_row()
                    ->render_cell($check_html, __('Select', 'modern-job-board'))
                    ->render_cell($name_html, $app_headers[1])
                    ->render_cell($email_html, $app_headers[2])
                    ->render_cell(esc_html(get_the_date()), $app_headers[3])
                    ->render_cell($resume_html, $app_headers[4]);

                $column_index = 5;
                foreach ($application_fields as $field) {
                    $field_value = get_post_meta($app_id, '_mjb_' . $field['key'], true);
                    if ($can_view) {
                        $field_html = esc_html((string) $field_value);
                    } else {
                        $field_html = '<span class="mjb-blurred">' . esc_html__('Hidden', 'modern-job-board') . '</span>';
                    }
                    $apps_grid->render_cell($field_html, $app_headers[$column_index]);
                    $column_index++;
                }

                ob_start();
                echo '<form method="post" action="" class="mjb-status-form">';
                wp_nonce_field('mjb_update_application_' . $app_id);
                echo '<input type="hidden" name="mjb_dashboard_action" value="update_application_status">';
                echo '<input type="hidden" name="application_id" value="' . esc_attr((string) $app_id) . '">';
                echo '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '">';
                echo '<select name="application_status" class="mjb-select">';
                foreach (MJB_Application_Status::get_statuses() as $status_key => $status_label) {
                    echo '<option value="' . esc_attr($status_key) . '" ' . selected($current_status, $status_key, false) . '>' . esc_html($status_label) . '</option>';
                }
                echo '</select> ';
                echo '<button type="submit" class="btn btn-outline btn-sm">' . esc_html__('Update', 'modern-job-board') . '</button>';
                echo '</form>';
                $status_html = ob_get_clean();

                apply_filters('mjb_dashboard_application_row', array(
                    'id' => $app_id,
                    'name' => $name,
                    'email' => $email,
                    'status' => $current_status,
                ), $app_id, $job_id);

                $apps_grid->render_cell($status_html, $app_headers[$column_index])
                    ->render_cell(esc_html(wp_trim_words(get_the_content(), 10)), $app_headers[$column_index + 1])
                    ->close_row();
            }

            $apps_grid->close_body()->end();
            echo '</form>';
            echo '<script>document.getElementById("mjb-select-all-apps")&&document.getElementById("mjb-select-all-apps").addEventListener("change",function(e){document.querySelectorAll("#mjb-bulk-apps input[name=\'application_ids[]\']").forEach(function(c){c.checked=e.target.checked;});});</script>';
            wp_reset_postdata();
        } else {
            echo '<div class="mjb-portal-empty"><p>' . esc_html__('No applications found for this job.', 'modern-job-board') . '</p></div>';
        }

        self::render_portal_shell_close();
    }

    /**
     * Build a dashboard URL with optional query arguments.
     *
     * @param array $query_args
     * @return string
     */
    public static function get_page_url($query_args = array())
    {
        return MJB_Page_Resolver::get_page_url('mjb_dashboard', self::PAGE_OPTION, $query_args, '/jobs/recruiter-dashboard/');
    }

    /**
     * Resolve the page ID that contains the employer dashboard shortcode.
     *
     * @return int
     */
    public static function resolve_page_id()
    {
        return MJB_Page_Resolver::resolve_page_id('mjb_dashboard', self::PAGE_OPTION);
    }

    /**
     * Batch-fetch application counts for multiple job IDs in a single query.
     *
     * @param array $job_ids
     * @return array<int, int>
     */
    public static function get_application_counts_for_jobs($job_ids)
    {
        $job_ids = array_filter(array_map('intval', (array) $job_ids));
        if (empty($job_ids)) {
            return array();
        }

        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count($job_ids), '%d'));
        $sql = "
            SELECT pm.meta_value AS job_id, COUNT(*) AS app_count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_job_applied_for'
            AND p.post_type = 'job_application'
            AND p.post_status IN ('publish', 'pending', 'draft')
            AND pm.meta_value IN ($placeholders)
            GROUP BY pm.meta_value
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are built from intval IDs and passed to prepare().
        $rows = $wpdb->get_results($wpdb->prepare($sql, $job_ids), ARRAY_A);
        $counts = array();

        foreach ($rows as $row) {
            $counts[intval($row['job_id'])] = intval($row['app_count']);
        }

        return $counts;
    }

    /**
     * Quick-action feature card (admin dashboard parity).
     *
     * @param string $url
     * @param string $icon
     * @param string $title
     * @param string $description
     * @param string $ajax_tab Optional tab id for in-panel AJAX navigation.
     * @return string
     */
    private static function render_quick_action_card($url, $icon, $title, $description, $ajax_tab = '')
    {
        $ajax_tab = sanitize_key((string) $ajax_tab);
        $html = '<a class="mjb-feature-card' . ($ajax_tab !== '' ? ' mjb-feature-card--ajax' : '') . '" href="' . esc_url($url) . '"';
        if ($ajax_tab !== '') {
            $html .= ' data-tab="' . esc_attr($ajax_tab) . '"';
        }
        $html .= '>';
        $html .= '<div class="mjb-feature-icon">';
        if (class_exists('MJB_Icons')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            $html .= MJB_Icons::render($icon, 24);
        }
        $html .= '</div>';
        $html .= '<h3>' . esc_html($title) . '</h3>';
        $html .= '<p>' . esc_html($description) . '</p>';
        $html .= '</a>';
        return $html;
    }

    /**
     * Compact icon action cluster for a job row.
     *
     * @param int    $job_id
     * @param string $edit_link
     * @param string $view_apps_link
     * @param bool   $is_filled
     * @return string
     */
    private static function render_job_row_actions($job_id, $edit_link, $view_apps_link, $is_filled)
    {
        $job_id = (int) $job_id;
        // Frequent tasks stay visible; infrequent ones sit behind "More" (progressive disclosure).
        $html = '<div class="mjb-list-actions mjb-list-actions--icons" role="group" aria-label="' . esc_attr__('Job actions', 'modern-job-board') . '">';

        $html .= self::icon_action_link($edit_link, 'file-pen', __('Edit', 'modern-job-board'));
        $html .= self::icon_action_link($view_apps_link, 'inbox', __('Applications', 'modern-job-board'));

        $filled_label = $is_filled ? __('Reopen', 'modern-job-board') : __('Mark filled', 'modern-job-board');
        $filled_icon = $is_filled ? 'rotate-ccw' : 'circle-check';
        $more_id = 'mjb-job-more-' . $job_id;

        $html .= '<details class="mjb-action-more">';
        $html .= '<summary class="mjb-action-more__summary" aria-label="' . esc_attr__('More actions', 'modern-job-board') . '">';
        $html .= '<span class="mjb-icon-btn mjb-action-more__toggle" aria-hidden="true">';
        if (class_exists('MJB_Icons')) {
            // Closed: chevron; open: X (not a rotated “up arrow”).
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $html .= '<span class="mjb-action-more__icon mjb-action-more__icon--more">' . MJB_Icons::render('chevron-down', 16) . '</span>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $html .= '<span class="mjb-action-more__icon mjb-action-more__icon--close">' . MJB_Icons::render('x', 16) . '</span>';
        } else {
            $html .= '<span class="mjb-action-more__icon mjb-action-more__icon--more">&middot;&middot;&middot;</span>';
            $html .= '<span class="mjb-action-more__icon mjb-action-more__icon--close">&times;</span>';
        }
        $html .= '</span>';
        $html .= '<span class="mjb-icon-btn__tooltip" role="tooltip" aria-hidden="true">' . esc_html__('More', 'modern-job-board') . '</span>';
        $html .= '</summary>';
        $html .= '<div class="mjb-action-more__menu" id="' . esc_attr($more_id) . '" role="menu">';

        // Toggle filled / reopen.
        $html .= '<form method="post" action="" class="mjb-action-more__item-form" role="none">';
        ob_start();
        wp_nonce_field('mjb_toggle_filled_' . $job_id);
        $html .= ob_get_clean();
        $html .= '<input type="hidden" name="mjb_dashboard_action" value="toggle_job_filled">';
        $html .= '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '">';
        $html .= '<input type="hidden" name="filled" value="' . ($is_filled ? '0' : '1') . '">';
        $html .= self::more_menu_button('submit', $filled_icon, $filled_label);
        $html .= '</form>';

        // Republish.
        $html .= '<form method="post" action="" class="mjb-action-more__item-form" role="none">';
        ob_start();
        wp_nonce_field('mjb_republish_job_' . $job_id);
        $html .= ob_get_clean();
        $html .= '<input type="hidden" name="mjb_dashboard_action" value="republish_job">';
        $html .= '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '">';
        $html .= self::more_menu_button('submit', 'refresh-cw', __('Republish', 'modern-job-board'));
        $html .= '</form>';

        if (
            class_exists('MJB_Memberships')
            && MJB_Memberships::get_feature_product_id() > 0
            && get_post_status($job_id) === 'publish'
            && !get_post_meta($job_id, '_featured', true)
        ) {
            $html .= self::more_menu_button(
                'button',
                'star',
                __('Feature', 'modern-job-board'),
                'mjb-feature-job-btn',
                array(
                    'data-job-id' => (string) $job_id,
                    'data-security' => wp_create_nonce('mjb_feature_job'),
                )
            );
        }

        // Delete.
        $html .= '<form method="post" action="" class="mjb-action-more__item-form" role="none" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to delete this job?', 'modern-job-board')) . '\');">';
        ob_start();
        wp_nonce_field('mjb_delete_job_' . $job_id);
        $html .= ob_get_clean();
        $html .= '<input type="hidden" name="mjb_dashboard_action" value="delete_job">';
        $html .= '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '">';
        $html .= self::more_menu_button('submit', 'trash-2', __('Delete', 'modern-job-board'), 'mjb-action-more__item--danger');
        $html .= '</form>';

        $html .= '</div></details>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Labeled item inside the "More" actions menu.
     *
     * @param string               $type
     * @param string               $icon
     * @param string               $label
     * @param string               $extra_class
     * @param array<string,string> $attrs
     * @return string
     */
    private static function more_menu_button($type, $icon, $label, $extra_class = '', $attrs = array())
    {
        $type = $type === 'button' ? 'button' : 'submit';
        $classes = trim('mjb-action-more__item ' . $extra_class);
        $html = '<button type="' . esc_attr($type) . '" class="' . esc_attr($classes) . '" role="menuitem"';
        foreach ($attrs as $key => $val) {
            $html .= ' ' . esc_attr($key) . '="' . esc_attr((string) $val) . '"';
        }
        $html .= '>';
        if (class_exists('MJB_Icons')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $html .= '<span class="mjb-action-more__item-icon" aria-hidden="true">' . MJB_Icons::render($icon, 16) . '</span>';
        }
        $html .= '<span class="mjb-action-more__item-label">' . esc_html($label) . '</span>';
        $html .= '</button>';
        return $html;
    }

    /**
     * Icon button (submit/button) for job row actions.
     *
     * @param string               $type    submit|button
     * @param string               $icon
     * @param string               $label
     * @param string               $extra_class
     * @param array<string,string> $attrs
     * @return string
     */
    private static function icon_action_button($type, $icon, $label, $extra_class = '', $attrs = array())
    {
        $type = $type === 'button' ? 'button' : 'submit';
        $classes = trim('mjb-icon-btn ' . $extra_class);
        $html = '<span class="mjb-icon-btn-wrap">';
        $html .= '<button type="' . esc_attr($type) . '" class="' . esc_attr($classes) . '"';
        $html .= ' aria-label="' . esc_attr($label) . '"';
        foreach ($attrs as $key => $val) {
            $html .= ' ' . esc_attr($key) . '="' . esc_attr((string) $val) . '"';
        }
        $html .= '>';
        if (class_exists('MJB_Icons')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            $html .= MJB_Icons::render($icon, 16);
        } else {
            $html .= esc_html($label);
        }
        $html .= '</button>';
        $html .= '<span class="mjb-icon-btn__tooltip" role="tooltip" aria-hidden="true">' . esc_html($label) . '</span>';
        $html .= '</span>';
        return $html;
    }

    /**
     * Icon link for job row actions.
     *
     * @param string $url
     * @param string $icon
     * @param string $label
     * @return string
     */
    private static function icon_action_link($url, $icon, $label)
    {
        $html = '<span class="mjb-icon-btn-wrap">';
        $html .= '<a href="' . esc_url($url) . '" class="mjb-icon-btn" aria-label="' . esc_attr($label) . '">';
        if (class_exists('MJB_Icons')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $html .= MJB_Icons::render($icon, 16);
        } else {
            $html .= esc_html($label);
        }
        $html .= '</a>';
        $html .= '<span class="mjb-icon-btn__tooltip" role="tooltip" aria-hidden="true">' . esc_html($label) . '</span>';
        $html .= '</span>';
        return $html;
    }

    /**
     * Pagination for the recruiter jobs table.
     *
     * @param int $total_pages
     * @param int $current_page
     */
    private static function render_jobs_pagination($total_pages, $current_page)
    {
        $total_pages = (int) $total_pages;
        $current_page = max(1, (int) $current_page);
        if ($total_pages <= 1) {
            return;
        }

        $base = self::get_tab_url('jobs');
        echo '<nav class="mjb-pagination mjb-pagination--dashboard" aria-label="' . esc_attr__('Jobs pagination', 'modern-job-board') . '">';

        // Previous
        if ($current_page > 1) {
            $prev_url = $current_page - 1 > 1
                ? add_query_arg('jobs_page', $current_page - 1, $base)
                : remove_query_arg('jobs_page', $base);
            echo '<a class="btn btn-sm btn-outline mjb-page-link mjb-page-prev" href="' . esc_url($prev_url) . '" aria-label="' . esc_attr__('Previous page', 'modern-job-board') . '">';
            if (class_exists('MJB_Icons')) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span class="mjb-page-link__icon" aria-hidden="true">' . MJB_Icons::render('chevron-left', 16) . '</span>';
            } else {
                echo '&lsaquo;';
            }
            echo '</a>';
        }

        for ($page = 1; $page <= $total_pages; $page++) {
            $is_active = ($page === $current_page);
            $url = $page > 1 ? add_query_arg('jobs_page', $page, $base) : remove_query_arg('jobs_page', $base);
            $classes = 'btn btn-sm mjb-page-link mjb-page-number ' . ($is_active ? 'btn-primary is-active' : 'btn-outline');
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '"';
            if ($is_active) {
                echo ' aria-current="page"';
            }
            echo '>';
            echo '<span>' . esc_html((string) $page) . '</span>';
            echo '</a>';
        }

        // Next
        if ($current_page < $total_pages) {
            $next_url = add_query_arg('jobs_page', $current_page + 1, $base);
            echo '<a class="btn btn-sm btn-outline mjb-page-link mjb-page-next" href="' . esc_url($next_url) . '" aria-label="' . esc_attr__('Next page', 'modern-job-board') . '">';
            if (class_exists('MJB_Icons')) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span class="mjb-page-link__icon" aria-hidden="true">' . MJB_Icons::render('chevron-right', 16) . '</span>';
            } else {
                echo '&rsaquo;';
            }
            echo '</a>';
        }

        echo '</nav>';
    }
}
