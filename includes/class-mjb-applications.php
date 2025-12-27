<?php
/**
 * Modern Job Board Applications
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Applications
{

    /**
     * Initialize Applications.
     */
    public function init()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'handle_form_submission'));
    }

    /**
     * Register Application CPT.
     */
    public function register_post_type()
    {
        $labels = array(
            'name' => _x('Applications', 'Post Type General Name', 'modern-job-board'),
            'singular_name' => _x('Application', 'Post Type Singular Name', 'modern-job-board'),
            'menu_name' => __('Applications', 'modern-job-board'),
            'name_admin_bar' => __('Application', 'modern-job-board'),
            'archives' => __('Application Archives', 'modern-job-board'),
            'attributes' => __('Application Attributes', 'modern-job-board'),
            'parent_item_colon' => __('Parent Application:', 'modern-job-board'),
            'all_items' => __('All Applications', 'modern-job-board'),
            'add_new_item' => __('Add New Application', 'modern-job-board'), // Usually disabled
            'add_new' => __('Add New', 'modern-job-board'),
            'new_item' => __('New Application', 'modern-job-board'),
            'edit_item' => __('Edit Application', 'modern-job-board'),
            'update_item' => __('Update Application', 'modern-job-board'),
            'view_item' => __('View Application', 'modern-job-board'),
            'view_items' => __('View Applications', 'modern-job-board'),
            'search_items' => __('Search Application', 'modern-job-board'),
            'not_found' => __('Not found', 'modern-job-board'),
            'not_found_in_trash' => __('Not found in Trash', 'modern-job-board'),
        );
        $args = array(
            'label' => __('Application', 'modern-job-board'),
            'description' => __('Job Applications', 'modern-job-board'),
            'labels' => $labels,
            'supports' => array('title', 'editor', 'custom-fields'),
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=job_listing',
            'menu_position' => 10,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow', // Prevent admin creation by default if desired, but good for testing
            ),
            'map_meta_cap' => true,
        );
        register_post_type('job_application', $args);
    }

    /**
     * Handle frontend form submission.
     */
    public function handle_form_submission()
    {
        if (!isset($_POST['mjb_submit_application']) || !isset($_POST['mjb_application_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['mjb_application_nonce'], 'mjb_submit_application')) {
            return;
        }

        $job_id = intval($_POST['job_id']);
        $candidate_name = sanitize_text_field($_POST['candidate_name']);
        $candidate_email = sanitize_email($_POST['candidate_email']);
        $candidate_message = sanitize_textarea_field($_POST['candidate_message']);

        // Basic Validation
        if (empty($candidate_name) || empty($candidate_email)) {
            // In a real app, we'd add error handling/flashing messages here.
            return;
        }

        // Handle File Upload or Logic
        $resume_url = '';

        // Check if using profile resume
        if (isset($_POST['mjb_use_profile_resume']) && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $resume_id = get_user_meta($user_id, '_candidate_resume_id', true);
            if ($resume_id) {
                // Check if it's an attachment (Legacy) or MJB Resume Post
                $post_type = get_post_type($resume_id);
                if ($post_type === 'mjb_resume') {
                    $resume_url = get_post_meta($resume_id, '_resume_file_url', true);
                } else {
                    $resume_url = wp_get_attachment_url($resume_id);
                }
            }
        }

        // Fallback to upload if not using profile resume or if it failed
        if (empty($resume_url) && isset($_FILES['candidate_resume']) && !empty($_FILES['candidate_resume']['name'])) {
            $resume_url = $this->handle_file_upload($_FILES['candidate_resume']);
            if (is_wp_error($resume_url)) {
                // Handle error
                return;
            }
        } elseif (empty($resume_url)) {
            // No resume found (neither profile nor upload)
            return;
        }

        $post_title = sprintf(__('Application for %s by %s', 'modern-job-board'), get_the_title($job_id), $candidate_name);

        $post_data = array(
            'post_title' => $post_title,
            'post_content' => $candidate_message,
            'post_type' => 'job_application',
            'post_status' => 'publish',
        );

        $post_data = apply_filters('mjb_pre_application_submission_data', $post_data, $_POST);

        $application_id = wp_insert_post($post_data);

        if ($application_id) {
            update_post_meta($application_id, '_job_applied_for', $job_id);
            update_post_meta($application_id, '_candidate_name', $candidate_name);
            update_post_meta($application_id, '_candidate_email', $candidate_email);
            if ($resume_url) {
                update_post_meta($application_id, '_candidate_resume', $resume_url);
            }

            // Save Custom Fields
            global $mjb_custom_fields;
            if (isset($mjb_custom_fields)) {
                $fields = $mjb_custom_fields->get_fields('application');
                foreach ($fields as $field) {
                    $key = 'mjb_app_field_' . $field['key'];
                    if (isset($_POST[$key])) {
                        $val = sanitize_text_field($_POST[$key]);
                        update_post_meta($application_id, '_mjb_' . $field['key'], $val);
                        // Append to content for easy viewing in Admin?
                        // Or just store as meta. Let's store as meta.
                        // Optionally append to body:
                        // $candidate_message .= "\n\n" . $field['label'] . ': ' . $val;
                    }
                }
            }

            // Send Notification
            global $mjb_emails;
            if (isset($mjb_emails)) {
                $mjb_emails->send_new_application_notification($application_id);
            }

            // Hook for post-submission actions
            do_action('mjb_application_submitted', $application_id);

            // Redirect to prevent resubmission
            wp_safe_redirect(add_query_arg('application_submitted', 'true', get_permalink($job_id)));
            exit;
        }
    }

    /**
     * Handle File Upload
     */
    private function handle_file_upload($file)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            return $movefile['url'];
        } else {
            return new WP_Error('upload_error', $movefile['error']);
        }
    }
}
