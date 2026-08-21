<?php
/**
 * Editable email subject/body templates with merge tags (WPJB Tier B8).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Email_Templates
{
    const OPTION = 'mjb_email_templates';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 62);
        add_action('admin_post_mjb_save_email_templates', array(__CLASS__, 'save'));
        add_filter('mjb_email_new_job_subject', array(__CLASS__, 'filter_subject'), 10, 2);
        add_filter('mjb_email_new_job_message', array(__CLASS__, 'filter_message'), 10, 2);
        add_filter('mjb_email_application_subject', array(__CLASS__, 'filter_application_subject'), 10, 2);
        add_filter('mjb_email_application_message', array(__CLASS__, 'filter_application_message'), 10, 2);
        add_filter('mjb_email_candidate_confirmation_subject', array(__CLASS__, 'filter_candidate_conf_subject'), 10, 2);
        add_filter('mjb_email_candidate_confirmation_message', array(__CLASS__, 'filter_candidate_conf_message'), 10, 2);
        add_filter('mjb_email_employer_signup_subject', array(__CLASS__, 'filter_employer_signup_subject'), 10, 3);
        add_filter('mjb_email_employer_signup_message', array(__CLASS__, 'filter_employer_signup_message'), 10, 3);
        add_filter('mjb_email_candidate_signup_subject', array(__CLASS__, 'filter_candidate_signup_subject'), 10, 3);
        add_filter('mjb_email_candidate_signup_message', array(__CLASS__, 'filter_candidate_signup_message'), 10, 3);
    }

    /**
     * Default templates (plain text; HTML allowed if admin pastes it).
     *
     * @return array<string, array{label:string,subject:string,body:string,tags:string}>
     */
    public static function defaults()
    {
        return array(
            'new_job' => array(
                'label' => __('Admin: new job submitted', 'modern-job-board'),
                'subject' => __('New Job Submitted: {job_title}', 'modern-job-board'),
                'body' => __("A new job has been submitted to your board.\n\nJob Title: {job_title}\nCompany: {company}\nEdit Job: {edit_link}\n", 'modern-job-board'),
                'tags' => '{job_title} {company} {edit_link} {site_name}',
            ),
            'application_employer' => array(
                'label' => __('Recruiter: new application', 'modern-job-board'),
                'subject' => __('New Application for {job_title}', 'modern-job-board'),
                'body' => __("You have received a new application for \"{job_title}\".\n\nCandidate Name: {candidate_name}\nCandidate Email: {candidate_email}\nView applications: {dashboard_url}\n\nMessage:\n{message}\n", 'modern-job-board'),
                'tags' => '{job_title} {candidate_name} {candidate_email} {dashboard_url} {message} {site_name}',
            ),
            'application_candidate' => array(
                'label' => __('Candidate: application received', 'modern-job-board'),
                'subject' => __('Application received: {job_title}', 'modern-job-board'),
                'body' => __("Hi {candidate_name},\n\nThanks for applying for \"{job_title}\". Your application has been received.\n\nView the job: {job_url}\n", 'modern-job-board'),
                'tags' => '{job_title} {candidate_name} {job_url} {site_name}',
            ),
            'employer_signup' => array(
                'label' => __('Recruiter: account ready', 'modern-job-board'),
                'subject' => __('Your recruiter account is ready', 'modern-job-board'),
                'body' => __("Hi {user_name},\n\nYour recruiter account has been created successfully.\n\nRecruiter dashboard: {dashboard_url}\n", 'modern-job-board'),
                'tags' => '{user_name} {dashboard_url} {site_name}',
            ),
            'employer_signup_pending' => array(
                'label' => __('Recruiter: pending approval', 'modern-job-board'),
                'subject' => __('Your recruiter account is pending approval', 'modern-job-board'),
                'body' => __("Hi {user_name},\n\nThanks for creating a recruiter account. Your account is pending approval.\n", 'modern-job-board'),
                'tags' => '{user_name} {site_name}',
            ),
            'candidate_signup' => array(
                'label' => __('Candidate: account ready', 'modern-job-board'),
                'subject' => __('Your job seeker account is ready', 'modern-job-board'),
                'body' => __("Hi {user_name},\n\nYour job seeker account has been created successfully.\n\nYour dashboard: {dashboard_url}\n", 'modern-job-board'),
                'tags' => '{user_name} {dashboard_url} {site_name}',
            ),
            'candidate_signup_pending' => array(
                'label' => __('Candidate: pending approval', 'modern-job-board'),
                'subject' => __('Your job seeker account is pending approval', 'modern-job-board'),
                'body' => __("Hi {user_name},\n\nThanks for creating a job seeker account. Your account is pending approval.\n", 'modern-job-board'),
                'tags' => '{user_name} {site_name}',
            ),
        );
    }

    /**
     * Stored + defaults merged.
     *
     * @return array
     */
    public static function get_all()
    {
        $stored = get_option(self::OPTION, array());
        if (!is_array($stored)) {
            $stored = array();
        }
        $out = self::defaults();
        foreach ($out as $key => $def) {
            if (!empty($stored[$key]['subject'])) {
                $out[$key]['subject'] = $stored[$key]['subject'];
            }
            if (!empty($stored[$key]['body'])) {
                $out[$key]['body'] = $stored[$key]['body'];
            }
        }
        return $out;
    }

    /**
     * Replace {tags} in a string.
     *
     * @param string               $text
     * @param array<string,string> $vars
     * @return string
     */
    public static function merge($text, array $vars)
    {
        $text = (string) $text;
        $vars = array_merge(
            array('site_name' => get_bloginfo('name')),
            $vars
        );
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        // Strip unused tags.
        $text = preg_replace('/\{[a-z0-9_]+\}/i', '', $text);
        return $text;
    }

    /**
     * @param string $key
     * @param string $field subject|body
     * @return string
     */
    public static function get_template_part($key, $field)
    {
        $all = self::get_all();
        if (!isset($all[$key][$field])) {
            return '';
        }
        return (string) $all[$key][$field];
    }

    /**
     * Whether a custom template overrides the built-in string.
     *
     * @param string $key
     * @return bool
     */
    public static function has_custom($key)
    {
        $stored = get_option(self::OPTION, array());
        return is_array($stored) && (!empty($stored[$key]['subject']) || !empty($stored[$key]['body']));
    }

    // --- Filters ---

    public static function filter_subject($subject, $job_id)
    {
        if (!self::has_custom('new_job')) {
            return $subject;
        }
        $tpl = self::get_template_part('new_job', 'subject');
        return self::merge($tpl, self::job_vars($job_id));
    }

    public static function filter_message($message, $job_id)
    {
        if (!self::has_custom('new_job')) {
            return $message;
        }
        $tpl = self::get_template_part('new_job', 'body');
        return self::merge($tpl, self::job_vars($job_id));
    }

    public static function filter_application_subject($subject, $application_id)
    {
        if (!self::has_custom('application_employer')) {
            return $subject;
        }
        return self::merge(self::get_template_part('application_employer', 'subject'), self::application_vars($application_id));
    }

    public static function filter_application_message($message, $application_id)
    {
        if (!self::has_custom('application_employer')) {
            return $message;
        }
        return self::merge(self::get_template_part('application_employer', 'body'), self::application_vars($application_id));
    }

    public static function filter_candidate_conf_subject($subject, $application_id)
    {
        if (!self::has_custom('application_candidate')) {
            return $subject;
        }
        return self::merge(self::get_template_part('application_candidate', 'subject'), self::application_vars($application_id));
    }

    public static function filter_candidate_conf_message($message, $application_id)
    {
        if (!self::has_custom('application_candidate')) {
            return $message;
        }
        return self::merge(self::get_template_part('application_candidate', 'body'), self::application_vars($application_id));
    }

    public static function filter_employer_signup_subject($subject, $user_id, $pending = false)
    {
        $key = $pending ? 'employer_signup_pending' : 'employer_signup';
        if (!self::has_custom($key)) {
            return $subject;
        }
        return self::merge(self::get_template_part($key, 'subject'), self::user_vars($user_id, true));
    }

    public static function filter_employer_signup_message($message, $user_id, $pending = false)
    {
        $key = $pending ? 'employer_signup_pending' : 'employer_signup';
        if (!self::has_custom($key)) {
            return $message;
        }
        return self::merge(self::get_template_part($key, 'body'), self::user_vars($user_id, true));
    }

    public static function filter_candidate_signup_subject($subject, $user_id, $pending = false)
    {
        $key = $pending ? 'candidate_signup_pending' : 'candidate_signup';
        if (!self::has_custom($key)) {
            return $subject;
        }
        return self::merge(self::get_template_part($key, 'subject'), self::user_vars($user_id, false));
    }

    public static function filter_candidate_signup_message($message, $user_id, $pending = false)
    {
        $key = $pending ? 'candidate_signup_pending' : 'candidate_signup';
        if (!self::has_custom($key)) {
            return $message;
        }
        return self::merge(self::get_template_part($key, 'body'), self::user_vars($user_id, false));
    }

    /**
     * @param int $job_id
     * @return array
     */
    private static function job_vars($job_id)
    {
        $job_id = (int) $job_id;
        return array(
            'job_title' => get_the_title($job_id),
            'company' => (string) get_post_meta($job_id, '_company_name', true),
            'edit_link' => get_edit_post_link($job_id, 'raw') ? get_edit_post_link($job_id, 'raw') : '',
            'job_url' => get_permalink($job_id) ? get_permalink($job_id) : '',
        );
    }

    /**
     * @param int $application_id
     * @return array
     */
    private static function application_vars($application_id)
    {
        $application_id = (int) $application_id;
        $job_id = (int) get_post_meta($application_id, '_job_applied_for', true);
        $candidate_name = (string) get_post_meta($application_id, '_candidate_name', true);
        if (class_exists('MJB_Resume_Privacy')) {
            $candidate_name = MJB_Resume_Privacy::anonymize_name($candidate_name);
        }
        $dashboard_url = '';
        if (class_exists('MJB_Dashboard') && $job_id) {
            $dashboard_url = MJB_Dashboard::get_page_url(array(
                'action' => 'view_applications',
                'job_id' => $job_id,
            ));
        }
        return array_merge(self::job_vars($job_id), array(
            'candidate_name' => $candidate_name,
            'candidate_email' => (string) get_post_meta($application_id, '_candidate_email', true),
            'dashboard_url' => $dashboard_url,
            'message' => (string) get_post_field('post_content', $application_id),
        ));
    }

    /**
     * @param int  $user_id
     * @param bool $employer
     * @return array
     */
    private static function user_vars($user_id, $employer)
    {
        $user = get_userdata((int) $user_id);
        $name = $user ? ($user->display_name ? $user->display_name : $user->user_login) : '';
        $dash = '';
        if ($employer && class_exists('MJB_Dashboard')) {
            $dash = MJB_Dashboard::get_page_url();
        } elseif (!$employer && class_exists('MJB_Candidate_Dashboard')) {
            $dash = MJB_Candidate_Dashboard::get_page_url();
        }
        return array(
            'user_name' => $name,
            'dashboard_url' => $dash,
        );
    }

    /**
     * Admin menu.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Email templates', 'modern-job-board'),
            __('Email templates', 'modern-job-board'),
            'manage_options',
            'mjb-email-templates',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Save templates.
     */
    public static function save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'modern-job-board'));
        }
        check_admin_referer('mjb_save_email_templates');

        $subjects = isset($_POST['tpl_subject']) ? (array) wp_unslash($_POST['tpl_subject']) : array();
        $bodies = isset($_POST['tpl_body']) ? (array) wp_unslash($_POST['tpl_body']) : array();
        $stored = array();
        foreach (array_keys(self::defaults()) as $key) {
            $stored[$key] = array(
                'subject' => isset($subjects[$key]) ? sanitize_text_field($subjects[$key]) : '',
                'body' => isset($bodies[$key]) ? wp_kses_post($bodies[$key]) : '',
            );
        }
        update_option(self::OPTION, $stored, false);
        wp_safe_redirect(add_query_arg(array('page' => 'mjb-email-templates', 'updated' => '1'), admin_url('edit.php?post_type=job_listing')));
        exit;
    }

    /**
     * Admin UI.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $templates = self::get_all();
        echo '<div class="wrap"><h1>' . esc_html__('Email templates', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Override notification subject and body. Leave blank fields to use built-in defaults. Merge tags are listed under each template. HTML is allowed in the body.', 'modern-job-board') . '</p>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Templates saved.', 'modern-job-board') . '</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mjb_save_email_templates">';
        wp_nonce_field('mjb_save_email_templates');
        foreach ($templates as $key => $tpl) {
            echo '<h2>' . esc_html($tpl['label']) . '</h2>';
            echo '<p class="description"><code>' . esc_html($tpl['tags']) . '</code></p>';
            echo '<p><label>' . esc_html__('Subject', 'modern-job-board') . '<br>';
            echo '<input type="text" class="large-text" name="tpl_subject[' . esc_attr($key) . ']" value="' . esc_attr($tpl['subject']) . '"></label></p>';
            echo '<p><label>' . esc_html__('Body', 'modern-job-board') . '<br>';
            echo '<textarea class="large-text code" rows="6" name="tpl_body[' . esc_attr($key) . ']">' . esc_textarea($tpl['body']) . '</textarea></label></p>';
            echo '<hr>';
        }
        submit_button(__('Save email templates', 'modern-job-board'));
        echo '</form></div>';
    }
}
