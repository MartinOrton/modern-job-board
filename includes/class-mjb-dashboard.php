<?php
/**
 * Modern Job Board Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Dashboard
{

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
                wp_redirect(remove_query_arg(array('action', 'job_id', '_wpnonce')));
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

        ob_start();

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
            echo '<th>' . __('Date', 'modern-job-board') . '</th>';
            echo '<th>' . __('Actions', 'modern-job-board') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            while ($jobs->have_posts()) {
                $jobs->the_post();
                $job_id = get_the_ID();
                $edit_link = add_query_arg(array('action' => 'edit', 'job_id' => $job_id), home_url('/post-job/')); // Assuming /post-job/ contains [mjb_job_form]
                $delete_link = wp_nonce_url(add_query_arg(array('action' => 'delete_job', 'job_id' => $job_id)), 'mjb_delete_job_' . $job_id);

                echo '<tr>';
                echo '<td>' . get_the_title() . '</td>';
                echo '<td>' . get_post_status_object(get_post_status())->label . '</td>';
                echo '<td>' . get_the_date() . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($edit_link) . '" class="button">' . __('Edit', 'modern-job-board') . '</a> ';
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
}
