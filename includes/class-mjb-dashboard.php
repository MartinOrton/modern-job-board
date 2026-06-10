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
    }

    /**
     * Handle dashboard POST actions (delete job, update application status).
     */
    public function handle_post_actions()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['mjb_dashboard_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['mjb_dashboard_action']));

        if ($action === 'delete_job') {
            $this->handle_delete_job_post();
            return;
        }

        if ($action === 'update_application_status') {
            $this->handle_update_application_status_post();
        }
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
            wp_safe_redirect(self::get_page_url());
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

        wp_safe_redirect(self::get_page_url(array(
            'action' => 'view_applications',
            'job_id' => $job_id,
            'mjb_notice' => 'success_application_status',
        )));
        exit;
    }

    /**
     * Output Dashboard.
     */
    public function output_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to view the dashboard.', 'modern-job-board') . '</p>';
        }

        $user = wp_get_current_user();
        if (!in_array('employer', (array) $user->roles, true) && !user_can($user, 'manage_options')) {
            return '<p>' . __('This dashboard is for employer accounts only.', 'modern-job-board') . '</p>';
        }

        ob_start();

        do_action('mjb_before_employer_dashboard', get_current_user_id());

        // Check for View Applications Action
        if (isset($_GET['action']) && $_GET['action'] == 'view_applications' && isset($_GET['job_id'])) {
            $this->output_applications_view($_GET['job_id']);
            return ob_get_clean();
        }

        $args = array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => -1,
            'author' => get_current_user_id(),
        );

        $credits = intval(get_user_meta(get_current_user_id(), '_mjb_job_credits', true));
        if ($credits > 0) {
            echo '<p class="mjb-dashboard-credits"><strong>' . esc_html__('Job credits:', 'modern-job-board') . '</strong> ' . esc_html((string) $credits) . '</p>';
        }

        $jobs = new WP_Query($args);
        $job_ids = wp_list_pluck($jobs->posts, 'ID');
        $app_counts = self::get_application_counts_for_jobs($job_ids);
        $analytics = MJB_Analytics::get_employer_job_stats(get_current_user_id());
        $totals = MJB_Analytics::summarize_job_stats($analytics);

        echo '<h3>' . esc_html__('Performance Overview', 'modern-job-board') . '</h3>';
        echo '<div class="mjb-stats-grid mjb-analytics-summary">';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $totals['jobs']) . '</div><div class="mjb-stat-lbl">' . esc_html__('Jobs', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $totals['views']) . '</div><div class="mjb-stat-lbl">' . esc_html__('Views', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $totals['applications']) . '</div><div class="mjb-stat-lbl">' . esc_html__('Applications', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html($totals['conversion_rate'] . '%') . '</div><div class="mjb-stat-lbl">' . esc_html__('Conversion', 'modern-job-board') . '</div></div>';
        echo '</div>';

        echo '<h3>' . esc_html__('Your Jobs', 'modern-job-board') . '</h3>';

        if ($jobs->have_posts()) {
            $job_headers = array(
                __('Title', 'modern-job-board'),
                __('Status', 'modern-job-board'),
                __('Views', 'modern-job-board'),
                __('Applications', 'modern-job-board'),
                __('Conversion', 'modern-job-board'),
                __('Date', 'modern-job-board'),
                __('Actions', 'modern-job-board'),
            );
            $jobs_grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard', count($job_headers));
            $jobs_grid->render_header($job_headers)->open_body();

            while ($jobs->have_posts()) {
                $jobs->the_post();
                $job_id = get_the_ID();
                $edit_link = MJB_Shortcodes::get_job_form_page_url(array(
                    'action' => 'edit',
                    'job_id' => $job_id,
                ));
                $view_apps_link = self::get_page_url(array(
                    'action' => 'view_applications',
                    'job_id' => $job_id,
                ));

                $app_count = isset($app_counts[$job_id]) ? intval($app_counts[$job_id]) : 0;
                $view_count = intval(get_post_meta($job_id, MJB_Analytics::VIEW_COUNT_META, true));
                $conversion = $view_count > 0 ? round(($app_count / $view_count) * 100, 1) . '%' : '—';

                $actions_html = '<a href="' . esc_url($edit_link) . '" class="button">' . esc_html__('Edit', 'modern-job-board') . '</a> ';
                $actions_html .= '<a href="' . esc_url($view_apps_link) . '" class="button">' . esc_html__('Applications', 'modern-job-board') . '</a> ';
                $actions_html .= '<form method="post" action="" class="mjb-inline-delete-form" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to delete this job?', 'modern-job-board')) . '\');">';
                ob_start();
                wp_nonce_field('mjb_delete_job_' . $job_id);
                $actions_html .= ob_get_clean();
                $actions_html .= '<input type="hidden" name="mjb_dashboard_action" value="delete_job">';
                $actions_html .= '<input type="hidden" name="job_id" value="' . esc_attr((string) $job_id) . '">';
                $actions_html .= '<button type="submit" class="button">' . esc_html__('Delete', 'modern-job-board') . '</button>';
                $actions_html .= '</form>';

                $jobs_grid->open_row()
                    ->render_cell('<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>', $job_headers[0])
                    ->render_cell(esc_html(get_post_status_object(get_post_status())->label), $job_headers[1])
                    ->render_cell(esc_html((string) $view_count), $job_headers[2])
                    ->render_cell(esc_html((string) $app_count), $job_headers[3])
                    ->render_cell(esc_html((string) $conversion), $job_headers[4])
                    ->render_cell(esc_html(get_the_date()), $job_headers[5])
                    ->render_cell($actions_html, $job_headers[6])
                    ->close_row();
            }

            $jobs_grid->close_body()->end();
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__('You have not posted any jobs yet.', 'modern-job-board') . '</p>';
        }

        do_action('mjb_after_employer_dashboard', get_current_user_id());

        return ob_get_clean();
    }

    /**
     * Output Applications View.
     */
    private function output_applications_view($job_id)
    {
        $job_id = intval($job_id);
        $job = get_post($job_id);

        if (!$job || $job->post_type !== 'job_listing' || intval($job->post_author) !== get_current_user_id()) {
            echo '<p>' . esc_html__('Invalid job or permission denied.', 'modern-job-board') . '</p>';
            echo '<p><a href="' . esc_url(remove_query_arg(array('action', 'job_id'))) . '">' . esc_html__('&larr; Back to Dashboard', 'modern-job-board') . '</a></p>';
            return;
        }

        echo '<h3>' . esc_html(sprintf(__('Applications for "%s"', 'modern-job-board'), get_the_title($job_id))) . '</h3>';
        echo '<p><a href="' . esc_url(remove_query_arg(array('action', 'job_id', 'mjb_notice'))) . '">' . esc_html__('&larr; Back to Dashboard', 'modern-job-board') . '</a></p>';

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
            $app_headers = array(
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
                        $resume_html = '<a href="' . esc_url($resume) . '" target="_blank" class="button button-small">' . esc_html__('Download', 'modern-job-board') . '</a>';
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
                        $resume_html .= '<div class="mjb-unlock-wrap"><a href="' . esc_url($unlock_link) . '" class="button button-small mjb-unlock-btn">' . esc_html__('Unlock', 'modern-job-board') . '</a></div>';
                    }
                }

                $apps_grid->open_row()
                    ->render_cell($name_html, $app_headers[0])
                    ->render_cell($email_html, $app_headers[1])
                    ->render_cell(esc_html(get_the_date()), $app_headers[2])
                    ->render_cell($resume_html, $app_headers[3]);

                $column_index = 4;
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
                echo '<select name="application_status">';
                foreach (MJB_Application_Status::get_statuses() as $status_key => $status_label) {
                    echo '<option value="' . esc_attr($status_key) . '" ' . selected($current_status, $status_key, false) . '>' . esc_html($status_label) . '</option>';
                }
                echo '</select> ';
                echo '<button type="submit" class="button button-small">' . esc_html__('Update', 'modern-job-board') . '</button>';
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
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__('No applications found for this job.', 'modern-job-board') . '</p>';
        }
    }

    /**
     * Build a dashboard URL with optional query arguments.
     *
     * @param array $query_args
     * @return string
     */
    public static function get_page_url($query_args = array())
    {
        return MJB_Page_Resolver::get_page_url('mjb_dashboard', self::PAGE_OPTION, $query_args, '/job-dashboard/');
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
}
