<?php
/**
 * MailChimp opt-in on registration (WPJB Tier B9).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Mailchimp
{
    const OPTION_API_KEY = 'mjb_mailchimp_api_key';
    const OPTION_LIST_EMPLOYER = 'mjb_mailchimp_list_employer';
    const OPTION_LIST_CANDIDATE = 'mjb_mailchimp_list_candidate';
    const OPTION_ENABLED = 'mjb_mailchimp_enabled';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('mjb_employer_registration_form_fields', array(__CLASS__, 'render_employer_checkbox'), 20);
        add_action('mjb_candidate_registration_form_fields', array(__CLASS__, 'render_candidate_checkbox'), 20);
        add_action('mjb_employer_registered', array(__CLASS__, 'on_employer_registered'), 20, 3);
        add_action('mjb_candidate_registered', array(__CLASS__, 'on_candidate_registered'), 20, 3);
    }

    /**
     * Settings.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_ENABLED, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_API_KEY, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('mjb_settings_group', self::OPTION_LIST_EMPLOYER, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('mjb_settings_group', self::OPTION_LIST_CANDIDATE, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        add_settings_section(
            'mjb_mailchimp_section',
            __('Mailchimp', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('Optional marketing opt-in on recruiter and candidate registration.', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );

        add_settings_field(
            self::OPTION_ENABLED,
            __('Enable Mailchimp', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ENABLED) . '" value="1" ' . checked(get_option(self::OPTION_ENABLED, '0'), '1', false) . '> ';
                echo esc_html__('Show opt-in and sync when checked', 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_mailchimp_section'
        );
        add_settings_field(
            self::OPTION_API_KEY,
            __('API key', 'modern-job-board'),
            function () {
                $v = get_option(self::OPTION_API_KEY, '');
                echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_API_KEY) . '" value="' . esc_attr($v) . '" autocomplete="off">';
                echo '<p class="description">' . esc_html__('Format: xxxxx-usXX (datacenter suffix required).', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_mailchimp_section'
        );
        add_settings_field(
            self::OPTION_LIST_EMPLOYER,
            __('Recruiter audience ID', 'modern-job-board'),
            function () {
                echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_LIST_EMPLOYER) . '" value="' . esc_attr(get_option(self::OPTION_LIST_EMPLOYER, '')) . '">';
            },
            'mjb-settings',
            'mjb_mailchimp_section'
        );
        add_settings_field(
            self::OPTION_LIST_CANDIDATE,
            __('Candidate audience ID', 'modern-job-board'),
            function () {
                echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_LIST_CANDIDATE) . '" value="' . esc_attr(get_option(self::OPTION_LIST_CANDIDATE, '')) . '">';
                echo '<p class="description">' . esc_html__('Leave blank to use the recruiter list for both, or vice versa.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_mailchimp_section'
        );
    }

    /**
     * @return bool
     */
    public static function is_enabled()
    {
        return get_option(self::OPTION_ENABLED, '0') === '1'
            && get_option(self::OPTION_API_KEY, '') !== '';
    }

    /**
     * Opt-in checkbox HTML.
     *
     * @param string $context employer|candidate
     */
    public static function render_checkbox($context = 'employer')
    {
        if (!self::is_enabled()) {
            return;
        }
        $name = 'mjb_mailchimp_optin';
        echo '<p class="mjb-mailchimp-optin mjb-form-row">';
        echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1"> ';
        echo esc_html__('Send me occasional product and hiring tips by email', 'modern-job-board');
        echo '</label></p>';
        unset($context);
    }

    public static function render_employer_checkbox()
    {
        self::render_checkbox('employer');
    }

    public static function render_candidate_checkbox()
    {
        self::render_checkbox('candidate');
    }

    /**
     * @return bool
     */
    public static function request_opted_in()
    {
        return !empty($_POST['mjb_mailchimp_optin']);
    }

    /**
     * Parse datacenter from API key.
     *
     * @param string $api_key
     * @return string
     */
    public static function datacenter_from_key($api_key)
    {
        $api_key = (string) $api_key;
        if (strpos($api_key, '-') === false) {
            return '';
        }
        $parts = explode('-', $api_key);
        return (string) end($parts);
    }

    /**
     * Subscribe email to a list (PUT members — idempotent).
     *
     * @param string $email
     * @param string $list_id
     * @param array  $merge_fields
     * @return true|WP_Error
     */
    public static function subscribe($email, $list_id, array $merge_fields = array())
    {
        $email = sanitize_email($email);
        $list_id = sanitize_text_field($list_id);
        $api_key = get_option(self::OPTION_API_KEY, '');
        $dc = self::datacenter_from_key($api_key);

        if ($email === '' || !is_email($email)) {
            return new WP_Error('mjb_mc_email', __('Invalid email for Mailchimp.', 'modern-job-board'));
        }
        if ($list_id === '' || $api_key === '' || $dc === '') {
            return new WP_Error('mjb_mc_config', __('Mailchimp is not configured.', 'modern-job-board'));
        }

        $hash = md5(strtolower($email));
        $url = sprintf('https://%s.api.mailchimp.com/3.0/lists/%s/members/%s', $dc, rawurlencode($list_id), $hash);

        $body = array(
            'email_address' => $email,
            'status_if_new' => 'pending',
            'status' => 'subscribed',
        );
        if (!empty($merge_fields)) {
            $body['merge_fields'] = $merge_fields;
        }

        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'apikey ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        $raw = wp_remote_retrieve_body($response);
        return new WP_Error('mjb_mc_api', sprintf('Mailchimp API error HTTP %d: %s', $code, $raw));
    }

    /**
     * List ID for audience type.
     *
     * @param string $type employer|candidate
     * @return string
     */
    public static function list_for($type)
    {
        if ($type === 'candidate') {
            $id = get_option(self::OPTION_LIST_CANDIDATE, '');
            if ($id !== '') {
                return $id;
            }
        }
        if ($type === 'employer') {
            $id = get_option(self::OPTION_LIST_EMPLOYER, '');
            if ($id !== '') {
                return $id;
            }
        }
        // Fallback to the other list.
        $emp = get_option(self::OPTION_LIST_EMPLOYER, '');
        $cand = get_option(self::OPTION_LIST_CANDIDATE, '');
        return $type === 'candidate' ? ($cand !== '' ? $cand : $emp) : ($emp !== '' ? $emp : $cand);
    }

    /**
     * @param int  $user_id
     * @param int  $company_id
     * @param bool $requires_approval
     */
    public static function on_employer_registered($user_id, $company_id = 0, $requires_approval = false)
    {
        unset($company_id, $requires_approval);
        if (!self::is_enabled() || !self::request_opted_in()) {
            return;
        }
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return;
        }
        $list = self::list_for('employer');
        $result = self::subscribe($user->user_email, $list, array(
            'FNAME' => $user->first_name,
            'LNAME' => $user->last_name,
        ));
        update_user_meta($user_id, '_mjb_mailchimp_optin', 1);
        if (is_wp_error($result)) {
            do_action('mjb_mailchimp_error', $result, $user_id, 'employer');
        }
    }

    /**
     * @param int  $user_id
     * @param int  $resume_id
     * @param bool $requires_approval
     */
    public static function on_candidate_registered($user_id, $resume_id = 0, $requires_approval = false)
    {
        unset($resume_id, $requires_approval);
        if (!self::is_enabled() || !self::request_opted_in()) {
            return;
        }
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return;
        }
        $list = self::list_for('candidate');
        $result = self::subscribe($user->user_email, $list, array(
            'FNAME' => $user->first_name,
            'LNAME' => $user->last_name,
        ));
        update_user_meta($user_id, '_mjb_mailchimp_optin', 1);
        if (is_wp_error($result)) {
            do_action('mjb_mailchimp_error', $result, $user_id, 'candidate');
        }
    }
}
