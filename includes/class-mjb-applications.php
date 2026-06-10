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
            'add_new_item' => __('Add New Application', 'modern-job-board'),
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
            'show_in_menu' => 'modern-job-board',
            'menu_position' => 10,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
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

        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $redirect_url = $job_id ? get_permalink($job_id) : home_url('/');

        if (!wp_verify_nonce($_POST['mjb_application_nonce'], 'mjb_submit_application')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing' || $job->post_status !== 'publish') {
            MJB_Notices::redirect($redirect_url, 'error_invalid_job');
        }

        $candidate_name = isset($_POST['candidate_name']) ? sanitize_text_field($_POST['candidate_name']) : '';
        $candidate_email = isset($_POST['candidate_email']) ? sanitize_email($_POST['candidate_email']) : '';
        $candidate_message = isset($_POST['candidate_message']) ? sanitize_textarea_field($_POST['candidate_message']) : '';

        if (empty($candidate_name) || empty($candidate_email) || empty($candidate_message)) {
            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
        }

        $spam_error = MJB_Application_Guard::validate_spam_protection();
        if ($spam_error) {
            MJB_Notices::redirect($redirect_url, $spam_error);
        }

        if (MJB_Application_Guard::is_rate_limited()) {
            MJB_Notices::redirect($redirect_url, 'error_rate_limited');
        }

        if (MJB_Application_Guard::has_duplicate_application($job_id, $candidate_email)) {
            MJB_Notices::redirect($redirect_url, 'error_duplicate_application');
        }

        $resume_path = '';
        $resume_post_id = 0;

        if (isset($_POST['mjb_use_profile_resume']) && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $resume_post_id = intval(get_user_meta($user_id, '_candidate_resume_id', true));

            if ($resume_post_id) {
                $resume_path = MJB_Resumes::get_resume_post_file_path($resume_post_id);
            }
        }

        if (empty($resume_path) && isset($_FILES['candidate_resume']) && !empty($_FILES['candidate_resume']['name'])) {
            $uploaded = MJB_Resumes::upload_file($_FILES['candidate_resume']);
            if (is_wp_error($uploaded)) {
                $code = $uploaded->get_error_code() === 'invalid_type' ? 'error_invalid_resume' : 'error_resume_upload';
                MJB_Notices::redirect($redirect_url, $code);
            }
            $resume_path = $uploaded['file'];
        }

        if (empty($resume_path)) {
            MJB_Notices::redirect($redirect_url, 'error_resume_required');
        }

        global $mjb_custom_fields;
        if (isset($mjb_custom_fields)) {
            $fields = $mjb_custom_fields->get_fields('application');
            foreach ($fields as $field) {
                if (!empty($field['required'])) {
                    $key = 'mjb_app_field_' . $field['key'];
                    if ($field['type'] === 'checkbox') {
                        if (empty($_POST[$key])) {
                            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
                        }
                    } elseif (empty($_POST[$key])) {
                        MJB_Notices::redirect($redirect_url, 'error_missing_fields');
                    }
                }
            }
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

        if (!$application_id || is_wp_error($application_id)) {
            MJB_Notices::redirect($redirect_url, 'error_registration_failed');
        }

        update_post_meta($application_id, '_job_applied_for', $job_id);
        update_post_meta($application_id, '_candidate_name', $candidate_name);
        update_post_meta($application_id, '_candidate_email', $candidate_email);
        update_post_meta($application_id, '_candidate_resume_path', $resume_path);

        if ($resume_post_id) {
            update_post_meta($application_id, '_candidate_resume_id', $resume_post_id);
        }

        if (isset($mjb_custom_fields)) {
            $fields = $mjb_custom_fields->get_fields('application');
            foreach ($fields as $field) {
                $key = 'mjb_app_field_' . $field['key'];
                if (isset($_POST[$key])) {
                    $val = sanitize_text_field($_POST[$key]);
                    update_post_meta($application_id, '_mjb_' . $field['key'], $val);
                }
            }
        }

        global $mjb_emails;
        if (isset($mjb_emails)) {
            $mjb_emails->send_new_application_notification($application_id);
            $mjb_emails->send_application_confirmation_to_candidate($application_id);
        }

        do_action('mjb_application_submitted', $application_id);

        MJB_Application_Guard::record_submission();

        MJB_Notices::redirect($redirect_url, 'success_application');
    }
}