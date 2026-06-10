<?php
/**
 * Modern Job Board User Notices
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Notices
{
    const QUERY_KEY = 'mjb_notice';

    /**
     * Redirect with a notice code appended to the URL.
     *
     * @param string $url
     * @param string $code
     */
    public static function redirect($url, $code)
    {
        wp_safe_redirect(add_query_arg(self::QUERY_KEY, sanitize_key($code), $url));
        exit;
    }

    /**
     * Render a notice from the current request query string.
     *
     * @return string
     */
    public static function render()
    {
        if (empty($_GET[self::QUERY_KEY])) {
            return '';
        }

        $code = sanitize_key(wp_unslash($_GET[self::QUERY_KEY]));
        $messages = apply_filters('mjb_notice_messages', self::default_messages());

        if (!isset($messages[$code])) {
            return '';
        }

        $type = (strpos($code, 'error_') === 0) ? 'error' : 'success';

        return '<div class="mjb-message ' . esc_attr($type) . '">' . esc_html($messages[$code]) . '</div>';
    }

    /**
     * Default notice messages.
     *
     * @return array
     */
    public static function default_messages()
    {
        return array(
            'success_application' => __('Application submitted successfully!', 'modern-job-board'),
            'success_profile' => __('Profile updated successfully.', 'modern-job-board'),
            'success_resume' => __('Resume uploaded successfully.', 'modern-job-board'),
            'success_job_submitted' => __('Job submitted successfully! It is pending review.', 'modern-job-board'),
            'success_job_updated' => __('Job updated successfully! It is pending review.', 'modern-job-board'),
            'success_job_credit' => __('Job submitted successfully using a job credit!', 'modern-job-board'),
            'success_employer_registered' => __('Registration successful! Welcome to your dashboard.', 'modern-job-board'),
            'success_candidate_registered' => __('Registration successful! Welcome.', 'modern-job-board'),
            'error_security' => __('Security check failed. Please try again.', 'modern-job-board'),
            'error_missing_fields' => __('Please fill in all required fields.', 'modern-job-board'),
            'error_invalid_job' => __('This job is no longer available.', 'modern-job-board'),
            'error_invalid_resume' => __('Please upload a valid resume (PDF, DOC, or DOCX).', 'modern-job-board'),
            'error_resume_required' => __('A resume is required to apply.', 'modern-job-board'),
            'error_resume_upload' => __('Resume upload failed. Please try again.', 'modern-job-board'),
            'error_username_exists' => __('That username is already taken.', 'modern-job-board'),
            'error_email_exists' => __('That email address is already registered.', 'modern-job-board'),
            'error_registration_failed' => __('Registration failed. Please try again.', 'modern-job-board'),
            'error_permission' => __('You do not have permission to perform this action.', 'modern-job-board'),
            'error_invalid_company' => __('Please select a valid company or enter a new company name.', 'modern-job-board'),
            'error_login_required' => __('You must be logged in as an employer to post jobs.', 'modern-job-board'),
            'error_duplicate_application' => __('You have already applied for this job.', 'modern-job-board'),
            'error_rate_limited' => __('Too many applications submitted. Please try again later.', 'modern-job-board'),
            'error_spam' => __('Your application could not be submitted. Please try again.', 'modern-job-board'),
            'error_recaptcha' => __('Please complete the reCAPTCHA verification.', 'modern-job-board'),
        );
    }
}