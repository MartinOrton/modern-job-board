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
        add_action('init', array($this, 'handle_delete_action'));
    }

    /**
     * Handle Delete Action.
     */
    public function handle_delete_action()
    {
        if (isset($_GET['action']) && $_GET['action'] == 'delete_job' && isset($_GET['job_id']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'mjb_delete_job_' . $_GET['job_id'])) {
                return;
            }

            $job_id = intval($_GET['job_id']);
            $job = get_post($job_id);

            if ($job && $job->post_type === 'job_listing' && intval($job->post_author) === get_current_user_id()) {
                wp_trash_post($job_id);
                wp_safe_redirect(remove_query_arg(array('action', 'job_id', '_wpnonce')));
                exit;
            }
        }
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

        $jobs = new WP_Query($args);

        echo '<h3>' . __('Your Jobs', 'modern-job-board') . '</h3>';

        if ($jobs->have_posts()) {
            echo '<table class="mjb-dashboard-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Title', 'modern-job-board') . '</th>';
            echo '<th>' . __('Status', 'modern-job-board') . '</th>';
            echo '<th>' . __('Applications', 'modern-job-board') . '</th>';
            echo '<th>' . __('Date', 'modern-job-board') . '</th>';
            echo '<th>' . __('Actions', 'modern-job-board') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            while ($jobs->have_posts()) {
                $jobs->the_post();
                $job_id = get_the_ID();
                $edit_link = add_query_arg(array('action' => 'edit', 'job_id' => $job_id), home_url('/post-job/'));
                $delete_link = wp_nonce_url(add_query_arg(array('action' => 'delete_job', 'job_id' => $job_id)), 'mjb_delete_job_' . $job_id);
                $view_apps_link = self::get_page_url(array(
                    'action' => 'view_applications',
                    'job_id' => $job_id,
                ));

                // Get Application Count
                $app_count = 0;
                $apps = get_posts(array(
                    'post_type' => 'job_application',
                    'meta_key' => '_job_applied_for',
                    'meta_value' => $job_id,
                    'posts_per_page' => -1,
                    'fields' => 'ids' // optimization
                ));
                $app_count = count($apps);

                echo '<tr>';
                echo '<td><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>';
                echo '<td>' . get_post_status_object(get_post_status())->label . '</td>';
                echo '<td>' . $app_count . '</td>';
                echo '<td>' . get_the_date() . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($edit_link) . '" class="button">' . __('Edit', 'modern-job-board') . '</a> ';
                echo '<a href="' . esc_url($view_apps_link) . '" class="button">' . __('Applications', 'modern-job-board') . '</a> ';
                echo '<a href="' . esc_url($delete_link) . '" class="button" onclick="return confirm(\'' . __('Are you sure?', 'modern-job-board') . '\');">' . __('Delete', 'modern-job-board') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            wp_reset_postdata();
        } else {
            echo '<p>' . __('You have not posted any jobs yet.', 'modern-job-board') . '</p>';
        }

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
            echo '<p>' . __('Invalid job or permission denied.', 'modern-job-board') . '</p>';
            echo '<p><a href="' . remove_query_arg(array('action', 'job_id')) . '">' . __('&larr; Back to Dashboard', 'modern-job-board') . '</a></p>';
            return;
        }

        echo '<h3>' . sprintf(__('Applications for "%s"', 'modern-job-board'), get_the_title($job_id)) . '</h3>';
        echo '<p><a href="' . remove_query_arg(array('action', 'job_id')) . '">' . __('&larr; Back to Dashboard', 'modern-job-board') . '</a></p>';

        $args = array(
            'post_type' => 'job_application',
            'meta_key' => '_job_applied_for',
            'meta_value' => $job_id,
            'posts_per_page' => -1,
        );
        $applications = new WP_Query($args);

        if ($applications->have_posts()) {
            echo '<table class="mjb-dashboard-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Candidate', 'modern-job-board') . '</th>';
            echo '<th>' . __('Email', 'modern-job-board') . '</th>';
            echo '<th>' . __('Date', 'modern-job-board') . '</th>';
            echo '<th>' . __('Resume', 'modern-job-board') . '</th>';
            echo '<th>' . __('Message', 'modern-job-board') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            while ($applications->have_posts()) {
                $applications->the_post();
                $app_id = get_the_ID();
                $name = get_post_meta($app_id, '_candidate_name', true);
                $email = get_post_meta($app_id, '_candidate_email', true);
                $resume = MJB_Resumes::get_application_download_url($app_id);

                $can_view = true;
                if (get_option('mjb_paid_cv_access')) {
                    $can_view = MJB_Resumes::employer_has_cv_access(get_current_user_id(), $app_id);
                }

                // Column: Candidate Name
                echo '<td>';
                if ($can_view) {
                    echo esc_html($name);
                } else {
                    echo '<span class="mjb-blurred">' . __('Hidden', 'modern-job-board') . '</span>';
                }
                echo '</td>';

                // Column: Email
                echo '<td>';
                if ($can_view) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '<span class="mjb-blurred">' . __('Hidden', 'modern-job-board') . '</span>';
                }
                echo '</td>';

                // Column: Date (Always visible)
                echo '<td>' . get_the_date() . '</td>';

                // Column: Resume / Unlock
                echo '<td>';
                if ($can_view) {
                    if ($resume) {
                        echo '<a href="' . esc_url($resume) . '" target="_blank" class="button button-small">' . __('Download', 'modern-job-board') . '</a>';
                    } else {
                        echo '-';
                    }
                } else {
                    echo '<span class="mjb-locked">' . __('Locked', 'modern-job-board') . '</span>';

                    // Unlock Button
                    $unlock_product_id = get_option('mjb_cv_unlock_product_id');
                    if ($unlock_product_id && function_exists('wc_get_cart_url')) {
                        $cart_url = wc_get_cart_url();
                        $unlock_link = add_query_arg(array(
                            'add-to-cart' => $unlock_product_id,
                            'mjb_unlock_application_id' => $app_id
                        ), $cart_url);

                        echo '<div style="margin-top:5px;"><a href="' . esc_url($unlock_link) . '" class="button button-small mjb-unlock-btn" style="background:#f0ad4e;border-color:#eea236;color:#fff;">' . __('Unlock', 'modern-job-board') . '</a></div>';
                    }
                }
                echo '</td>';

                // Column: Message
                echo '<td>' . wp_trim_words(get_the_content(), 10) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            wp_reset_postdata();
        } else {
            echo '<p>' . __('No applications found for this job.', 'modern-job-board') . '</p>';
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
        $page_id = self::resolve_page_id();
        $url = $page_id ? get_permalink($page_id) : home_url('/job-dashboard/');

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        return $url;
    }

    /**
     * Resolve the page ID that contains the employer dashboard shortcode.
     *
     * @return int
     */
    public static function resolve_page_id()
    {
        $cached = intval(get_option(self::PAGE_OPTION));
        if ($cached && get_post_status($cached)) {
            return $cached;
        }

        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($pages as $page_id) {
            $post = get_post($page_id);
            if ($post && has_shortcode($post->post_content, 'mjb_dashboard')) {
                update_option(self::PAGE_OPTION, $page_id, false);
                return intval($page_id);
            }
        }

        return 0;
    }
}
