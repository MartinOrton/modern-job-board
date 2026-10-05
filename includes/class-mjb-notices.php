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

        $message = ($code === 'success_candidate_registered')
            ? self::candidate_registered_message()
            : $messages[$code];

        if (strpos($code, 'error_') === 0) {
            $type = 'error';
        } elseif (strpos($code, 'warning_') === 0) {
            $type = 'warning';
        } elseif (strpos($code, 'info_') === 0) {
            $type = 'info';
        } else {
            $type = 'success';
        }

        $role = ($type === 'error' || $type === 'warning') ? 'alert' : 'status';

        return '<div class="mjb-message ' . esc_attr($type) . '" role="' . esc_attr($role) . '">' . esc_html($message) . '</div>';
    }

    /**
     * Welcome line after a job seeker account is created.
     *
     * @param string|null $first_name Logged-in user's first name when null.
     * @return string
     */
    public static function candidate_registered_message($first_name = null)
    {
        if ($first_name === null) {
            $first_name = '';
            if (is_user_logged_in()) {
                $first_name = (string) get_user_meta(get_current_user_id(), 'first_name', true);
            }
        }

        $first_name = trim((string) $first_name);
        if ($first_name === '') {
            return __('Registration successful! Welcome.', 'modern-job-board');
        }

        return sprintf(
            /* translators: %s: candidate first name */
            __('Registration successful! Welcome %s.', 'modern-job-board'),
            $first_name
        );
    }

    /**
     * Message text for a notice code.
     *
     * @param string $code
     * @return string
     */
    public static function message($code)
    {
        $code = sanitize_key((string) $code);
        $messages = apply_filters('mjb_notice_messages', self::default_messages());
        return isset($messages[$code]) ? (string) $messages[$code] : '';
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
            'success_document_label' => __('Document label saved.', 'modern-job-board'),
            'success_resume' => __('Resume uploaded successfully.', 'modern-job-board'),
            'success_resume_deleted' => __('CV removed from your profile.', 'modern-job-board'),
            'success_photo' => __('Profile photo updated.', 'modern-job-board'),
            'success_photo_deleted' => __('Profile photo removed.', 'modern-job-board'),
            'success_job_republished' => __('Job republished successfully.', 'modern-job-board'),
            'success_job_filled' => __('Job marked as filled.', 'modern-job-board'),
            'success_job_reopened' => __('Job reopened.', 'modern-job-board'),
            'success_job_deleted' => __('Job deleted.', 'modern-job-board'),
            'success_application_status' => __('Application status updated.', 'modern-job-board'),
            'success_settings' => __('Settings saved.', 'modern-job-board'),
            'success_job_submitted' => __('Job submitted successfully! It is pending review.', 'modern-job-board'),
            'success_job_updated' => __('Job updated successfully! It is pending review.', 'modern-job-board'),
            'success_job_credit' => __('Job submitted successfully using a job credit!', 'modern-job-board'),
            'success_employer_registered' => __('Registration successful! Welcome to your dashboard.', 'modern-job-board'),
            'success_candidate_registered' => __('Registration successful! Welcome.', 'modern-job-board'),
            'success_registration_pending' => __('Your account is pending approval. You will receive an email once it is approved.', 'modern-job-board'),
            'success_login' => __('You are signed in.', 'modern-job-board'),
            'success_job_saved' => __('Job saved to your list.', 'modern-job-board'),
            'success_job_unsaved' => __('Job removed from your saved list.', 'modern-job-board'),
            'success_password_reset_email' => __('If an account matches that email or username, we sent password reset instructions. Check your inbox (and spam folder). The email includes your username.', 'modern-job-board'),
            'success_password_reset' => __('Your password was updated. Sign in with your new password.', 'modern-job-board'),
            'error_login_to_save' => __('Sign in as a job seeker to save jobs.', 'modern-job-board'),
            'error_login_failed' => __('Invalid email/username or password. Please try again.', 'modern-job-board'),
            'error_login_not_candidate' => __('That account is not a job seeker account. Please use recruiter login or register as a job seeker.', 'modern-job-board'),
            'error_login_not_employer' => __('That account is not a recruiter account. Please use job seeker login or register as a recruiter.', 'modern-job-board'),
            'error_password_reset_key' => __('This password reset link is invalid or has expired. Request a new one.', 'modern-job-board'),
            'error_password_reset_failed' => __('Could not reset the password. Please try again.', 'modern-job-board'),
            'error_security' => __('Security check failed. Please try again.', 'modern-job-board'),
            'error_missing_fields' => __('Please fill in all required fields.', 'modern-job-board'),
            'error_invalid_job' => __('This job is no longer available.', 'modern-job-board'),
            'error_invalid_resume' => __('Please upload a valid resume (PDF, DOC, or DOCX).', 'modern-job-board'),
            'error_resume_required' => __('A resume is required. Upload one on your candidate profile, then apply again.', 'modern-job-board'),
            'error_resume_copy' => __('Could not attach your resume to this application. Please try again.', 'modern-job-board'),
            'error_resume_upload' => __('Resume upload failed. Please try again.', 'modern-job-board'),
            'error_resume_delete_confirm' => __('Type DELETE to confirm removing your CV.', 'modern-job-board'),
            'error_invalid_logo' => __('Please upload a valid logo (JPEG, PNG, or WebP).', 'modern-job-board'),
            'error_logo_upload' => __('Logo upload failed. Please try again.', 'modern-job-board'),
            'error_invalid_photo' => __('Please upload a valid photo (JPEG, PNG, or WebP).', 'modern-job-board'),
            'error_photo_upload' => __('Photo upload failed. Please try again.', 'modern-job-board'),
            'error_username_exists' => __('That username is already taken.', 'modern-job-board'),
            'error_email_exists' => __('That email address is already registered.', 'modern-job-board'),
            'error_invalid_email' => __('Please enter a valid email address.', 'modern-job-board'),
            'error_password_mismatch' => __('Passwords do not match.', 'modern-job-board'),
            'error_password_weak' => __('Password must be at least 8 characters.', 'modern-job-board'),
            'error_registration_failed' => __('Registration failed. Please try again.', 'modern-job-board'),
            'error_application_failed' => __('Application could not be submitted. Please try again.', 'modern-job-board'),
            'error_permission' => __('You do not have permission to perform this action.', 'modern-job-board'),
            'error_invalid_company' => __('Please select a valid company or enter a new company name.', 'modern-job-board'),
            'error_login_required' => __('You must be logged in as a recruiter to post jobs.', 'modern-job-board'),
            'error_duplicate_application' => __('You have already applied for this job.', 'modern-job-board'),
            'error_rate_limited' => __('Too many applications submitted. Please try again later.', 'modern-job-board'),
            'error_registration_rate_limited' => __('Too many registration attempts. Please try again later.', 'modern-job-board'),
            'error_spam' => __('Your application could not be submitted. Please try again.', 'modern-job-board'),
            'error_recaptcha' => __('Please complete the reCAPTCHA verification.', 'modern-job-board'),
            'error_job_cap' => __('The Free plan allows a limited number of active published jobs. Upgrade to Pro for unlimited listings, or unpublish another job first.', 'modern-job-board'),
        );
    }
}