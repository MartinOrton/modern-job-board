<?php
/**
 * Resume access matrix + talent search + candidate anonymizer (WPJB A5–A6).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Resume_Privacy
{
    const OPTION_ACCESS = 'mjb_resume_access_tier';
    const OPTION_ON_APPLY = 'mjb_resume_access_on_application';
    const OPTION_ANON = 'mjb_candidate_anonymizer';
    const META_FEATURE_LEVEL = '_candidate_feature_level';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_filter('the_author', array(__CLASS__, 'filter_author_display'));
        add_filter('get_the_author_display_name', array(__CLASS__, 'filter_author_display'));
        add_action('wp_head', array(__CLASS__, 'maybe_noindex_talent'), 5);
        add_filter('mjb_talent_pool_user_query', array(__CLASS__, 'filter_talent_query'), 10, 2);
    }

    /**
     * Access tiers matching WPJB-style matrix.
     *
     * @return array<string, string>
     */
    public static function access_tiers()
    {
        return array(
            'all' => __('Everyone (public)', 'modern-job-board'),
            'registered' => __('Registered members', 'modern-job-board'),
            'employers' => __('Recruiters only', 'modern-job-board'),
            'verified_employers' => __('Verified recruiters (published a job)', 'modern-job-board'),
            'premium' => __('Premium / paid CV access', 'modern-job-board'),
        );
    }

    /**
     * Settings.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_ACCESS, array(
            'type' => 'string',
            'default' => 'employers',
            'sanitize_callback' => function ($v) {
                $v = sanitize_key((string) $v);
                return array_key_exists($v, self::access_tiers()) ? $v : 'employers';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_ON_APPLY, array(
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_ANON, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_anon'),
            'default' => array(),
        ));

        add_settings_section(
            'mjb_resume_privacy_section',
            __('Resume privacy & talent', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('Control who can browse the talent pool and how candidate names appear (GDPR-friendly anonymizer).', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );

        add_settings_field(
            self::OPTION_ACCESS,
            __('Grant resume / talent access', 'modern-job-board'),
            function () {
                $current = get_option(self::OPTION_ACCESS, 'employers');
                echo '<select name="' . esc_attr(self::OPTION_ACCESS) . '">';
                foreach (self::access_tiers() as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($current, $key, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            },
            'mjb-settings',
            'mjb_resume_privacy_section'
        );
        add_settings_field(
            self::OPTION_ON_APPLY,
            __('On application', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ON_APPLY) . '" value="1" ' . checked(get_option(self::OPTION_ON_APPLY, '1'), '1', false) . '> ';
                echo esc_html__('Recruiters may always view full resumes of candidates who applied to their jobs', 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_resume_privacy_section'
        );
        add_settings_field(
            self::OPTION_ANON,
            __('Candidate anonymizer', 'modern-job-board'),
            array(__CLASS__, 'render_anon_field'),
            'mjb-settings',
            'mjb_resume_privacy_section'
        );
    }

    /**
     * @param mixed $input
     * @return array
     */
    public static function sanitize_anon($input)
    {
        $out = array(
            'enabled' => '0',
            'hide_surname' => '0',
            'noindex' => '0',
        );
        if (!is_array($input)) {
            return $out;
        }
        $out['enabled'] = !empty($input['enabled']) ? '1' : '0';
        $out['hide_surname'] = !empty($input['hide_surname']) ? '1' : '0';
        $out['noindex'] = !empty($input['noindex']) ? '1' : '0';
        return $out;
    }

    /**
     * Anonymizer field UI.
     */
    public static function render_anon_field()
    {
        $a = self::get_anon();
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ANON) . '[enabled]" value="1" ' . checked($a['enabled'], '1', false) . '> ' . esc_html__('Enable anonymizer', 'modern-job-board') . '</label><br>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ANON) . '[hide_surname]" value="1" ' . checked($a['hide_surname'], '1', false) . '> ' . esc_html__('Hide surname (show “Jane D.”)', 'modern-job-board') . '</label><br>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ANON) . '[noindex]" value="1" ' . checked($a['noindex'], '1', false) . '> ' . esc_html__('Add noindex on public talent profiles', 'modern-job-board') . '</label>';
    }

    /**
     * @return array
     */
    public static function get_anon()
    {
        return self::sanitize_anon(get_option(self::OPTION_ANON, array()));
    }

    /**
     * Whether current user may browse the talent pool / resumes list.
     *
     * @return bool
     */
    public static function current_user_can_browse_resumes()
    {
        if (current_user_can('manage_options')) {
            return true;
        }
        $tier = get_option(self::OPTION_ACCESS, 'employers');
        switch ($tier) {
            case 'all':
                return true;
            case 'registered':
                return is_user_logged_in();
            case 'employers':
                return self::user_is_employer(get_current_user_id());
            case 'verified_employers':
                return self::user_is_verified_employer(get_current_user_id());
            case 'premium':
                return self::user_has_premium_cv_access(get_current_user_id());
            default:
                return false;
        }
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function user_is_employer($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        $roles = (array) $user->roles;
        return in_array('employer', $roles, true)
            || in_array('mjb_employer', $roles, true)
            || in_array('mjb_collaborator', $roles, true)
            || user_can($user_id, 'edit_posts');
    }

    /**
     * Employer who has at least one job listing.
     *
     * @param int $user_id
     * @return bool
     */
    public static function user_is_verified_employer($user_id)
    {
        if (!self::user_is_employer($user_id)) {
            return false;
        }
        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft'),
            'author' => (int) $user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));
        return !empty($jobs);
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function user_has_premium_cv_access($user_id)
    {
        if (class_exists('MJB_Resumes') && method_exists('MJB_Resumes', 'employer_has_cv_access')) {
            // Site-wide pass with application id 0 fallback: check meta expiry.
            $expires = (int) get_user_meta($user_id, '_mjb_cv_access_expires', true);
            if ($expires > time()) {
                return true;
            }
        }
        return self::user_is_verified_employer($user_id);
    }

    /**
     * Whether employer may view full name/resume for an application.
     *
     * @param int $employer_id
     * @param int $application_id
     * @return bool
     */
    public static function employer_can_view_application_resume($employer_id, $application_id)
    {
        if (user_can($employer_id, 'manage_options')) {
            return true;
        }
        if (get_option(self::OPTION_ON_APPLY, '1') === '1') {
            $job_id = (int) get_post_meta($application_id, '_job_applied_for', true);
            $job = get_post($job_id);
            if ($job && (int) $job->post_author === (int) $employer_id) {
                return true;
            }
        }
        if (class_exists('MJB_Resumes') && method_exists('MJB_Resumes', 'employer_has_cv_access')) {
            return MJB_Resumes::employer_has_cv_access($employer_id, $application_id);
        }
        return self::current_user_can_browse_resumes();
    }

    /**
     * Anonymize a display name.
     *
     * @param string $name
     * @return string
     */
    public static function anonymize_name($name)
    {
        $a = self::get_anon();
        if ($a['enabled'] !== '1' || $a['hide_surname'] !== '1') {
            return $name;
        }
        if (current_user_can('manage_options')) {
            return $name;
        }
        $name = trim((string) $name);
        if ($name === '') {
            return $name;
        }
        $parts = preg_split('/\s+/', $name);
        if (count($parts) < 2) {
            return $name;
        }
        $first = $parts[0];
        $last = end($parts);
        $initial = function_exists('mb_substr') ? mb_substr($last, 0, 1) : substr($last, 0, 1);
        return $first . ' ' . strtoupper($initial) . '.';
    }

    /**
     * @param string $name
     * @return string
     */
    public static function filter_author_display($name)
    {
        if (is_admin() && !wp_doing_ajax()) {
            return $name;
        }
        return self::anonymize_name($name);
    }

    /**
     * noindex talent profiles when configured.
     */
    public static function maybe_noindex_talent()
    {
        $a = self::get_anon();
        if ($a['enabled'] !== '1' || $a['noindex'] !== '1') {
            return;
        }
        if ((int) get_query_var('mjb_talent_user') > 0) {
            echo "<meta name=\"robots\" content=\"noindex,nofollow\" />\n";
        }
    }

    /**
     * Sort talent pool by feature level; enforce access in shortcode separately.
     *
     * @param array $user_args
     * @param array $request
     * @return array
     */
    public static function filter_talent_query($user_args, $request = array())
    {
        unset($request);
        $user_args['meta_key'] = self::META_FEATURE_LEVEL;
        $user_args['orderby'] = array(
            'meta_value_num' => 'DESC',
            'registered' => 'DESC',
        );
        return $user_args;
    }
}
