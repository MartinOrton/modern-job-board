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
     * Opaque apply intent query key (no public post IDs in the URL).
     * Value is a random server-side token; job ID lives only in a transient.
     */
    const APPLY_TOKEN_QUERY = 'mjb_a';

    /** Transient key prefix for apply tokens. */
    const APPLY_TOKEN_TRANSIENT_PREFIX = 'mjb_apply_';

    /** @deprecated Old public query keys — ignored so they no longer expose IDs. */
    const APPLY_JOB_QUERY = 'mjb_apply_job';
    const APPLY_FLAG_QUERY = 'mjb_apply';

    /**
     * Initialize Applications.
     */
    public function init()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'handle_form_submission'));
        add_action('admin_post_mjb_apply_to_job', array($this, 'handle_logged_in_apply'));
        add_action('admin_post_nopriv_mjb_apply_to_job', array($this, 'handle_guest_apply_redirect'));
    }

    /**
     * Create a fully opaque apply token for a job.
     * Job ID is stored server-side only (transient), never in the URL payload.
     *
     * @param int $job_id
     * @param int $ttl Seconds until expiry (default 24h).
     * @return string
     */
    public static function create_apply_token($job_id, $ttl = DAY_IN_SECONDS)
    {
        $job_id = (int) $job_id;
        if ($job_id <= 0) {
            return '';
        }

        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing' || $job->post_status !== 'publish') {
            return '';
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            $token = wp_hash(uniqid((string) $job_id, true) . wp_rand());
            $token = substr(preg_replace('/[^a-f0-9]/', '', $token . md5($token)), 0, 32);
        }

        $ttl = max(300, (int) $ttl);
        set_transient(self::APPLY_TOKEN_TRANSIENT_PREFIX . $token, $job_id, $ttl);

        return $token;
    }

    /**
     * Resolve an apply token to a published job_listing ID, or 0.
     *
     * @param string $token
     * @return int
     */
    public static function parse_apply_token($token)
    {
        $token = is_string($token) ? strtolower(trim($token)) : '';
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
            return 0;
        }

        $job_id = (int) get_transient(self::APPLY_TOKEN_TRANSIENT_PREFIX . $token);
        if ($job_id <= 0) {
            return 0;
        }

        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing' || $job->post_status !== 'publish') {
            delete_transient(self::APPLY_TOKEN_TRANSIENT_PREFIX . $token);
            return 0;
        }

        return $job_id;
    }

    /**
     * Current request apply token (pretty path query var, GET/POST), if any.
     *
     * @return string
     */
    public static function request_apply_token()
    {
        if (class_exists('MJB_Pretty_Urls')) {
            return MJB_Pretty_Urls::request_apply_token();
        }

        if (empty($_REQUEST[self::APPLY_TOKEN_QUERY])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_REQUEST[self::APPLY_TOKEN_QUERY]));
    }

    /**
     * Profile completeness for one-click apply messaging (#22).
     *
     * @param int $user_id
     * @return array{complete:bool,missing:string[],percent:int}
     */
    public static function profile_completeness($user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        $missing = array();
        if ($user_id <= 0) {
            return array('complete' => false, 'missing' => array('account'), 'percent' => 0);
        }
        $user = get_userdata($user_id);
        $first = ($user && isset($user->first_name)) ? (string) $user->first_name : '';
        $last = ($user && isset($user->last_name)) ? (string) $user->last_name : '';
        if ($first === '') {
            $missing[] = 'first_name';
        }
        if ($last === '') {
            $missing[] = 'last_name';
        }
        if (get_user_meta($user_id, '_candidate_phone', true) === '') {
            $missing[] = 'phone';
        }
        $resume = get_user_meta($user_id, '_candidate_resume_id', true);
        if (!$resume) {
            $resume = get_user_meta($user_id, '_candidate_resume_path', true);
        }
        if (!$resume) {
            $missing[] = 'resume';
        }
        $total = 4;
        $done = $total - count($missing);
        return array(
            'complete' => empty($missing),
            'missing' => $missing,
            'percent' => (int) round(100 * max(0, $done) / $total),
        );
    }

    /**
     * URL for the Apply CTA on a job listing.
     *
     * Guests → candidate login with opaque apply token.
     * Logged-in candidates → secure pretty apply action (token + nonce, no raw ID).
     *
     * @param int $job_id
     * @return string
     */
    public static function get_apply_url($job_id)
    {
        $job_id = (int) $job_id;
        if ($job_id <= 0) {
            return MJB_Page_Resolver::get_jobs_page_url();
        }

        $token = self::create_apply_token($job_id);

        if (is_user_logged_in() && self::current_user_can_apply()) {
            if (class_exists('MJB_Pretty_Urls')) {
                return MJB_Pretty_Urls::front_apply_url($token);
            }
            return wp_nonce_url(
                add_query_arg(
                    array(
                        'action' => 'mjb_apply_to_job',
                        self::APPLY_TOKEN_QUERY => $token,
                    ),
                    admin_url('admin-post.php')
                ),
                'mjb_apply_to_job'
            );
        }

        return self::get_apply_login_url($job_id);
    }

    /**
     * Candidate login URL with opaque apply intent (no public job IDs).
     * Pretty: /jobs/candidate-login/apply/{token}/
     *
     * @param int $job_id
     * @return string
     */
    public static function get_apply_login_url($job_id)
    {
        $job_id = (int) $job_id;
        $token = $job_id > 0 ? self::create_apply_token($job_id) : '';
        $base = MJB_Page_Resolver::get_page_url(
            'mjb_candidate_login',
            'mjb_candidate_login_page_id',
            array(),
            '/jobs/candidate-login/'
        );

        if ($token !== '' && class_exists('MJB_Pretty_Urls')) {
            return MJB_Pretty_Urls::apply_intent_url($base, $token);
        }

        if ($token !== '') {
            return add_query_arg(self::APPLY_TOKEN_QUERY, $token, $base);
        }

        return $base;
    }

    /**
     * Candidate registration URL with opaque apply intent.
     * Pretty: /jobs/candidate-registration/apply/{token}/
     *
     * @param int $job_id
     * @return string
     */
    public static function get_apply_register_url($job_id)
    {
        $job_id = (int) $job_id;
        $token = $job_id > 0 ? self::create_apply_token($job_id) : '';
        $base = MJB_Page_Resolver::get_page_url(
            'mjb_candidate_registration',
            'mjb_candidate_registration_page_id',
            array(),
            '/jobs/candidate-registration/'
        );

        if ($token !== '' && class_exists('MJB_Pretty_Urls')) {
            return MJB_Pretty_Urls::apply_intent_url($base, $token);
        }

        if ($token !== '') {
            return add_query_arg(self::APPLY_TOKEN_QUERY, $token, $base);
        }

        return $base;
    }

    /**
     * @return bool
     */
    public static function current_user_can_apply()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (user_can($user, 'manage_options')) {
            return true;
        }

        return in_array('candidate', (array) $user->roles, true);
    }

    /**
     * Resolve apply job id from the opaque request token (never from public raw IDs).
     *
     * @return int
     */
    public static function request_apply_job_id()
    {
        return self::parse_apply_token(self::request_apply_token());
    }

    /**
     * Job permalink for a resolved apply intent (clean URL, no IDs).
     *
     * @param string $fallback
     * @return string
     */
    public static function resolve_auth_redirect($fallback = '')
    {
        $fallback = $fallback !== '' ? $fallback : home_url('/');
        $job_id = self::request_apply_job_id();
        if ($job_id > 0) {
            $url = get_permalink($job_id);
            if ($url) {
                return $url;
            }
        }

        if (!empty($_REQUEST['redirect_to'])) {
            $candidate = esc_url_raw(wp_unslash($_REQUEST['redirect_to']));
            $validated = wp_validate_redirect($candidate, '');
            if ($validated) {
                return $validated;
            }
        }

        return $fallback;
    }

    /**
     * After successful auth: submit application from token and redirect to the job.
     * Returns true if an apply redirect was performed (caller should not continue).
     *
     * @param int $user_id
     * @return bool
     */
    public static function complete_apply_after_auth($user_id)
    {
        $user_id = (int) $user_id;
        $job_id = self::request_apply_job_id();
        if ($job_id <= 0 || $user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $is_candidate = in_array('candidate', (array) $user->roles, true);
        if (!$is_candidate && !user_can($user, 'manage_options')) {
            return false;
        }

        $redirect = get_permalink($job_id) ?: MJB_Page_Resolver::get_jobs_page_url();
        $result = self::create_application_from_profile($job_id, $user_id);

        // One-time use: drop the opaque token after a successful resolve attempt.
        $token = self::request_apply_token();
        if ($token !== '') {
            delete_transient(self::APPLY_TOKEN_TRANSIENT_PREFIX . strtolower($token));
        }

        if (is_wp_error($result)) {
            MJB_Notices::redirect($redirect, $result->get_error_code());
        }

        MJB_Notices::redirect($redirect, 'success_application');
        return true;
    }

    /**
     * Guest hit apply action → login with opaque token.
     */
    public function handle_guest_apply_redirect()
    {
        $job_id = self::request_apply_job_id();
        wp_safe_redirect(self::get_apply_login_url($job_id));
        exit;
    }

    /**
     * Logged-in apply via admin-post (token + nonce only).
     */
    public function handle_logged_in_apply()
    {
        $job_id = self::request_apply_job_id();
        $redirect = $job_id > 0 ? get_permalink($job_id) : MJB_Page_Resolver::get_jobs_page_url();

        if ($job_id <= 0 || !wp_verify_nonce(
            isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '',
            'mjb_apply_to_job'
        )) {
            MJB_Notices::redirect($redirect ?: home_url('/'), 'error_security');
        }

        if (!is_user_logged_in()) {
            $token = self::request_apply_token();
            $login = self::get_apply_login_url($job_id);
            // Preserve the same token when possible.
            if ($token !== '') {
                $login = add_query_arg(self::APPLY_TOKEN_QUERY, $token, remove_query_arg(self::APPLY_TOKEN_QUERY, $login));
            }
            wp_safe_redirect($login);
            exit;
        }

        if (!self::current_user_can_apply()) {
            MJB_Notices::redirect($redirect, 'error_login_not_candidate');
        }

        $result = self::create_application_from_profile($job_id, get_current_user_id());
        if (is_wp_error($result)) {
            MJB_Notices::redirect($redirect, $result->get_error_code());
        }

        MJB_Notices::redirect($redirect, 'success_application');
    }

    /**
     * Create a job application from the candidate profile (no public form).
     *
     * @param int $job_id
     * @param int $user_id
     * @return int|WP_Error Application post ID or error.
     */
    public static function create_application_from_profile($job_id, $user_id)
    {
        $job_id = (int) $job_id;
        $user_id = (int) $user_id;
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('error_permission', __('You do not have permission to perform this action.', 'modern-job-board'));
        }

        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing' || $job->post_status !== 'publish') {
            return new WP_Error('error_invalid_job', __('This job is no longer available.', 'modern-job-board'));
        }

        $candidate_name = trim($user->first_name . ' ' . $user->last_name);
        if ($candidate_name === '') {
            $candidate_name = $user->display_name;
        }
        $candidate_email = $user->user_email;

        if ($candidate_name === '' || !is_email($candidate_email)) {
            return new WP_Error('error_missing_fields', __('Please complete your candidate profile before applying.', 'modern-job-board'));
        }

        if (MJB_Application_Guard::has_duplicate_application($job_id, $candidate_email)) {
            return new WP_Error('error_duplicate_application', __('You have already applied for this job.', 'modern-job-board'));
        }

        if (MJB_Application_Guard::is_rate_limited()) {
            return new WP_Error('error_rate_limited', __('Too many applications submitted. Please try again later.', 'modern-job-board'));
        }

        $resume_post_id = (int) get_user_meta($user_id, '_candidate_resume_id', true);
        $resume_path = '';
        $resume_relative = '';

        if ($resume_post_id) {
            $copied = MJB_Resumes::copy_profile_resume_for_application($resume_post_id);
            if (is_wp_error($copied)) {
                return $copied;
            }
            $resume_path = $copied['path'];
            $resume_relative = $copied['relative'];
        }

        if ($resume_path === '') {
            return new WP_Error('error_resume_required', __('Upload a resume on your candidate profile before applying.', 'modern-job-board'));
        }

        $candidate_message = sprintf(
            /* translators: %s: job title */
            __('I am applying for %s via my Modern Job Board candidate profile.', 'modern-job-board'),
            get_the_title($job_id)
        );

        $post_title = sprintf(
            __('Application for %s by %s', 'modern-job-board'),
            get_the_title($job_id),
            $candidate_name
        );

        $application_id = wp_insert_post(array(
            'post_title' => $post_title,
            'post_content' => $candidate_message,
            'post_type' => 'job_application',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));

        if (!$application_id || is_wp_error($application_id)) {
            return new WP_Error('error_application_failed', __('Application could not be submitted. Please try again.', 'modern-job-board'));
        }

        update_post_meta($application_id, '_job_applied_for', $job_id);
        update_post_meta($application_id, '_candidate_name', $candidate_name);
        update_post_meta($application_id, '_candidate_email', $candidate_email);
        update_post_meta($application_id, '_candidate_resume_path', $resume_path);
        update_post_meta($application_id, '_candidate_user_id', $user_id);
        if ($resume_relative !== '') {
            update_post_meta($application_id, '_candidate_resume_relative', $resume_relative);
        }
        if ($resume_post_id) {
            update_post_meta($application_id, '_candidate_resume_id', $resume_post_id);
        }

        MJB_Application_Status::update_status($application_id, MJB_Application_Status::DEFAULT_STATUS);

        global $mjb_emails;
        if (isset($mjb_emails)) {
            $mjb_emails->send_new_application_notification($application_id);
            $mjb_emails->send_application_confirmation_to_candidate($application_id);
        }

        do_action('mjb_application_submitted', $application_id);
        MJB_Application_Guard::record_submission();

        return (int) $application_id;
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
        $redirect_url = $job_id ? get_permalink($job_id) : MJB_Page_Resolver::get_jobs_page_url();

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_application_nonce'])), 'mjb_submit_application')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing' || $job->post_status !== 'publish') {
            MJB_Notices::redirect($redirect_url, 'error_invalid_job');
        }

        $candidate_name = isset($_POST['candidate_name']) ? sanitize_text_field(wp_unslash($_POST['candidate_name'])) : '';
        $candidate_email = isset($_POST['candidate_email']) ? sanitize_email(wp_unslash($_POST['candidate_email'])) : '';
        $candidate_message = isset($_POST['candidate_message']) ? sanitize_textarea_field(wp_unslash($_POST['candidate_message'])) : '';

        if (empty($candidate_name) || empty($candidate_email) || empty($candidate_message)) {
            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
        }

        $spam_error = MJB_Application_Guard::validate_spam_protection('application');
        if ($spam_error) {
            MJB_Notices::redirect($redirect_url, $spam_error);
        }

        if (MJB_Application_Guard::is_rate_limited()) {
            MJB_Application_Guard::log_spam('rate', 'application');
            MJB_Notices::redirect($redirect_url, 'error_rate_limited');
        }

        if (MJB_Application_Guard::has_duplicate_application($job_id, $candidate_email)) {
            MJB_Notices::redirect($redirect_url, 'error_duplicate_application');
        }

        $resume_path = '';
        $resume_post_id = 0;
        $resume_relative = '';

        if (isset($_POST['mjb_use_profile_resume']) && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $resume_post_id = intval(get_user_meta($user_id, '_candidate_resume_id', true));

            if ($resume_post_id) {
                // Always copy — never store the live profile path on the application.
                $copied = MJB_Resumes::copy_profile_resume_for_application($resume_post_id);
                if (is_wp_error($copied)) {
                    $code = $copied->get_error_code();
                    if (!in_array($code, array('error_resume_required', 'error_resume_copy'), true)) {
                        $code = 'error_resume_copy';
                    }
                    MJB_Notices::redirect($redirect_url, $code);
                }
                $resume_path = $copied['path'];
                $resume_relative = $copied['relative'];
            }
        }

        if (empty($resume_path) && isset($_FILES['candidate_resume']) && !empty($_FILES['candidate_resume']['name'])) {
            $uploaded = MJB_Resumes::upload_file($_FILES['candidate_resume']);
            if (is_wp_error($uploaded)) {
                $code = $uploaded->get_error_code() === 'invalid_type' ? 'error_invalid_resume' : 'error_resume_upload';
                MJB_Notices::redirect($redirect_url, $code);
            }
            $resume_path = !empty($uploaded['relative']) ? $uploaded['relative'] : $uploaded['file'];
            $resume_relative = isset($uploaded['relative']) ? $uploaded['relative'] : '';
            $resume_post_id = 0;
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
            MJB_Notices::redirect($redirect_url, 'error_application_failed');
        }

        update_post_meta($application_id, '_job_applied_for', $job_id);
        update_post_meta($application_id, '_candidate_name', $candidate_name);
        update_post_meta($application_id, '_candidate_email', $candidate_email);
        update_post_meta($application_id, '_candidate_resume_path', $resume_path);
        if ($resume_relative !== '') {
            update_post_meta($application_id, '_candidate_resume_relative', $resume_relative);
        }
        MJB_Application_Status::update_status($application_id, MJB_Application_Status::DEFAULT_STATUS);

        // Profile resume post ID is informational only; file path is application-owned after copy.
        if ($resume_post_id) {
            update_post_meta($application_id, '_candidate_resume_id', $resume_post_id);
        }

        if (isset($mjb_custom_fields)) {
            $fields = $mjb_custom_fields->get_fields('application');
            foreach ($fields as $field) {
                $key = 'mjb_app_field_' . $field['key'];
                if (isset($_POST[$key])) {
                    $val = sanitize_text_field(wp_unslash($_POST[$key]));
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