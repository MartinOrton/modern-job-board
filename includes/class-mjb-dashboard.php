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
                $view_apps_link = add_query_arg(array('action' => 'view_applications', 'job_id' => $job_id));

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
                $resume = get_post_meta($app_id, '_candidate_resume', true);

                echo '<td>';

                // Permission Check
                $can_view = true; // Default
                if (get_option('mjb_paid_cv_access')) {
                    $can_view = false;
                    $user_id = get_current_user_id();

                    // 1. Check Global Access Pass
                    $expires = get_user_meta($user_id, '_mjb_cv_access_expires', true);
                    if ($expires && $expires > current_time('timestamp')) {
                        $can_view = true;
                    }

                    // 2. Check Single Unlock
                    if (!$can_view) {
                        $unlocked = get_user_meta($user_id, '_mjb_unlocked_applications', true);
                        if (is_array($unlocked) && in_array($app_id, $unlocked)) {
                            $can_view = true;
                        }
                    }
                }

                if ($can_view) {
                    // Visible
                    echo esc_html($name) . '<br>';
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                    if ($resume) {
                        echo '<br><a href="' . esc_url($resume) . '" target="_blank">' . __('Download Resume', 'modern-job-board') . '</a>';
                    }
                } else {
                    // Hidden
                    echo '<strong>' . __('Name Hidden', 'modern-job-board') . '</strong><br>';
                    echo '<em>' . __('Email Hidden', 'modern-job-board') . '</em>';

                    // Unlock Button
                    $unlock_product_id = get_option('mjb_cv_unlock_product_id');
                    if ($unlock_product_id && function_exists('wc_get_cart_url')) {
                        $cart_url = wc_get_cart_url();
                        $unlock_link = add_query_arg(array(
                            'add-to-cart' => $unlock_product_id,
                            'mjb_unlock_application_id' => $app_id
                        ), $cart_url);

                        echo '<br><a href="' . esc_url($unlock_link) . '" class="button mjb-unlock-btn" style="margin-top:5px;">' . __('Unlock Details', 'modern-job-board') . '</a>';
                    } else {
                        echo '<br><em>' . __('Access Restricted', 'modern-job-board') . '</em>';
                    }
                }
                echo '</td>';
                // echo '<td>' . esc_html($name) . '</td>'; // Removed original separate cells
                // echo '<td><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></td>'; // Merged into one cell for better UI control or keeps columns?
                // Wait, the table header has Name, Email, Date, Resume, Message.
                // Merging them complicates the table structure unless we replace columns. 
                // Let's keep columns but apply masking in each.

                // REVERTING MERGE to keep table structure:

                // Column: Candidate Name
                echo '<td>';
                if ($can_view) {
                    echo esc_html($name);
                } else {
                    echo __('Hidden', 'modern-job-board');
                }
                echo '</td>';

                // Column: Email
                echo '<td>';
                if ($can_view) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo __('Hidden', 'modern-job-board');
                }
                echo '</td>';

                // Column: Date (Always visible)
                echo '<td>' . get_the_date() . '</td>';

                // Column: Resume
                echo '<td>';
                if ($can_view) {
                    if ($resume) {
                        echo '<a href="' . esc_url($resume) . '" target="_blank">' . __('Download', 'modern-job-board') . '</a>';
                    } else {
                        echo '-';
                    }
                } else {
                    // Show Unlock here
                    $unlock_product_id = get_option('mjb_cv_unlock_product_id');
                    if ($unlock_product_id && function_exists('wc_get_cart_url')) {
                        $cart_url = wc_get_cart_url();
                        $unlock_link = add_query_arg(array(
                            'add-to-cart' => $unlock_product_id,
                            'mjb_unlock_application_id' => $app_id
                        ), $cart_url);
                        echo '<a href="' . esc_url($unlock_link) . '" class="button button-small">' . __('Unlock', 'modern-job-board') . '</a>';
                    } else {
                        echo __('Locked', 'modern-job-board');
                    }
                }
                echo '</td>';
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
}
