<?php
/**
 * Modern Job Board Authenticated REST API (v2)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_REST_API_V2
{
    /**
     * Initialize v2 routes.
     */
    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register authenticated routes.
     */
    public function register_routes()
    {
        register_rest_route('mjb/v2', '/applications', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_applications'),
                'permission_callback' => array($this, 'employer_permissions_check'),
                'args' => array(
                    'job_id' => array(
                        'sanitize_callback' => 'absint',
                    ),
                    'status' => array(
                        'sanitize_callback' => 'sanitize_key',
                    ),
                    'page' => array(
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'default' => 20,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        ));

        register_rest_route('mjb/v2', '/applications/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_application'),
                'permission_callback' => array($this, 'can_manage_application'),
                'args' => array(
                    'id' => array(
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ),
                    'status' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            ),
        ));

        register_rest_route('mjb/v2', '/analytics', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_analytics'),
                'permission_callback' => array($this, 'employer_permissions_check'),
            ),
        ));

        register_rest_route('mjb/v2', '/candidate/profile', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_candidate_profile'),
                'permission_callback' => array($this, 'candidate_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_candidate_profile'),
                'permission_callback' => array($this, 'candidate_permissions_check'),
                'args' => array(
                    'first_name' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'last_name' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'headline' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
    }

    /**
     * Employer permission check.
     *
     * @return bool|WP_Error
     */
    public function employer_permissions_check()
    {
        return self::current_user_is_employer();
    }

    /**
     * Candidate permission check.
     *
     * @return bool|WP_Error
     */
    public function candidate_permissions_check()
    {
        return self::current_user_is_candidate();
    }

    /**
     * Permission check for a specific application.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function can_manage_application($request)
    {
        if (!self::current_user_is_employer()) {
            return false;
        }

        $application_id = intval($request->get_param('id'));
        return MJB_Application_Status::user_can_manage($application_id);
    }

    /**
     * Whether the current user is an employer or admin.
     *
     * @return bool|WP_Error
     */
    public static function current_user_is_employer()
    {
        if (!is_user_logged_in()) {
            return new WP_Error('mjb_rest_auth_required', __('Authentication required.', 'modern-job-board'), array('status' => 401));
        }

        $user = wp_get_current_user();
        if (in_array('employer', (array) $user->roles, true) || user_can($user, 'manage_options')) {
            return true;
        }

        return new WP_Error('mjb_rest_forbidden', __('Employer access required.', 'modern-job-board'), array('status' => 403));
    }

    /**
     * Whether the current user is a candidate.
     *
     * @return bool|WP_Error
     */
    public static function current_user_is_candidate()
    {
        if (!is_user_logged_in()) {
            return new WP_Error('mjb_rest_auth_required', __('Authentication required.', 'modern-job-board'), array('status' => 401));
        }

        $user = wp_get_current_user();
        if (in_array('candidate', (array) $user->roles, true) || user_can($user, 'manage_options')) {
            return true;
        }

        return new WP_Error('mjb_rest_forbidden', __('Candidate access required.', 'modern-job-board'), array('status' => 403));
    }

    /**
     * Build employer job IDs for the current user.
     *
     * @return array<int>
     */
    public static function get_current_employer_job_ids()
    {
        if (user_can(get_current_user_id(), 'manage_options')) {
            return array();
        }

        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'author' => get_current_user_id(),
        ));

        return array_map('intval', $jobs);
    }

    /**
     * Format an application for API output.
     *
     * @param int $application_id
     * @return array
     */
    public static function format_application_for_api($application_id)
    {
        $application_id = intval($application_id);
        $job_id = intval(get_post_meta($application_id, '_job_applied_for', true));
        $status = MJB_Application_Status::get_status($application_id);

        return array(
            'id' => $application_id,
            'job_id' => $job_id,
            'job_title' => $job_id ? get_the_title($job_id) : '',
            'candidate_name' => get_post_meta($application_id, '_candidate_name', true),
            'candidate_email' => get_post_meta($application_id, '_candidate_email', true),
            'status' => $status,
            'status_label' => MJB_Application_Status::get_label($status),
            'date' => get_the_date('Y-m-d H:i:s', $application_id),
            'message' => wp_strip_all_tags(get_post_field('post_content', $application_id)),
            'resume_url' => MJB_Resumes::get_application_download_url($application_id),
        );
    }

    /**
     * Format candidate profile for API output.
     *
     * @param int $user_id
     * @return array
     */
    public static function format_candidate_profile_for_api($user_id)
    {
        $user_id = intval($user_id);
        $user = get_userdata($user_id);
        $resume_id = intval(get_user_meta($user_id, '_candidate_resume_id', true));

        return array(
            'id' => $user_id,
            'email' => $user ? $user->user_email : '',
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'headline' => get_user_meta($user_id, '_candidate_headline', true),
            'resume_id' => $resume_id,
            'resume_url' => MJB_Resumes::get_resume_display_url($resume_id),
        );
    }

    /**
     * Get employer analytics summary and per-job stats.
     *
     * @return WP_REST_Response
     */
    public function get_analytics()
    {
        $stats = MJB_Analytics::get_employer_job_stats(get_current_user_id());

        return new WP_REST_Response(array(
            'totals' => MJB_Analytics::summarize_job_stats($stats),
            'jobs' => $stats,
        ), 200);
    }

    /**
     * List applications for the authenticated employer.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_applications($request)
    {
        $per_page = min(max(intval($request->get_param('per_page') ?: 20), 1), 100);
        $page = max(intval($request->get_param('page') ?: 1), 1);
        $job_id = intval($request->get_param('job_id'));
        $status = sanitize_key($request->get_param('status'));

        $meta_query = array();
        $employer_job_ids = self::get_current_employer_job_ids();

        if ($job_id) {
            if (!empty($employer_job_ids) && !in_array($job_id, $employer_job_ids, true)) {
                return new WP_Error('mjb_rest_forbidden', __('You do not have access to this job.', 'modern-job-board'), array('status' => 403));
            }

            $meta_query[] = array(
                'key' => '_job_applied_for',
                'value' => $job_id,
            );
        } elseif (!empty($employer_job_ids)) {
            $meta_query[] = array(
                'key' => '_job_applied_for',
                'value' => $employer_job_ids,
                'compare' => 'IN',
            );
        }

        if ($status && MJB_Application_Status::is_valid($status)) {
            $meta_query[] = array(
                'key' => MJB_Application_Status::META_KEY,
                'value' => $status,
            );
        }

        $args = array(
            'post_type' => 'job_application',
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $applications = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $applications[] = self::format_application_for_api(get_the_ID());
            }
        }
        wp_reset_postdata();

        $response = new WP_REST_Response($applications, 200);
        $response->header('X-WP-Total', (int) $query->found_posts);
        $response->header('X-WP-TotalPages', (int) $query->max_num_pages);

        return $response;
    }

    /**
     * Update an application workflow status.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_application($request)
    {
        $application_id = intval($request->get_param('id'));
        $status = sanitize_key($request->get_param('status'));

        if (!MJB_Application_Status::is_valid($status)) {
            return new WP_Error('mjb_invalid_status', __('Invalid application status.', 'modern-job-board'), array('status' => 400));
        }

        if (!MJB_Application_Status::update_status($application_id, $status)) {
            return new WP_Error('mjb_update_failed', __('Unable to update application status.', 'modern-job-board'), array('status' => 500));
        }

        return new WP_REST_Response(self::format_application_for_api($application_id), 200);
    }

    /**
     * Get the authenticated candidate profile.
     *
     * @return WP_REST_Response
     */
    public function get_candidate_profile()
    {
        return new WP_REST_Response(self::format_candidate_profile_for_api(get_current_user_id()), 200);
    }

    /**
     * Update the authenticated candidate profile.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_candidate_profile($request)
    {
        $user_id = get_current_user_id();
        $first_name = $request->get_param('first_name');
        $last_name = $request->get_param('last_name');
        $headline = $request->get_param('headline');

        if ($first_name === null && $last_name === null && $headline === null) {
            return new WP_Error('mjb_missing_fields', __('No profile fields provided.', 'modern-job-board'), array('status' => 400));
        }

        $update = array('ID' => $user_id);
        if ($first_name !== null) {
            $update['first_name'] = sanitize_text_field($first_name);
        }
        if ($last_name !== null) {
            $update['last_name'] = sanitize_text_field($last_name);
        }

        if (count($update) > 1) {
            $result = wp_update_user($update);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        if ($headline !== null) {
            update_user_meta($user_id, '_candidate_headline', sanitize_text_field($headline));
        }

        do_action('mjb_candidate_profile_updated', $user_id, $request->get_params());

        return new WP_REST_Response(self::format_candidate_profile_for_api($user_id), 200);
    }
}