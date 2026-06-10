<?php
/**
 * Modern Job Board Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Emails
{

    /**
     * Initialize Emails.
     */
    public function init()
    {
    }

    /**
     * Send New Job Notification to Admin.
     *
     * @param int $job_id
     */
    public function send_new_job_notification($job_id)
    {
        $job = get_post($job_id);
        if (!$job) {
            return;
        }

        $to = get_option('admin_email');
        $subject = sprintf(__('New Job Submitted: %s', 'modern-job-board'), $job->post_title);

        $message = sprintf(__('A new job has been submitted to your board.', 'modern-job-board')) . "\n\n";
        $message .= sprintf(__('Job Title: %s', 'modern-job-board'), $job->post_title) . "\n";
        $message .= sprintf(__('Company: %s', 'modern-job-board'), get_post_meta($job_id, '_company_name', true)) . "\n";
        $message .= sprintf(__('Edit Job: %s', 'modern-job-board'), get_edit_post_link($job_id)) . "\n";

        wp_mail($to, $subject, $message);
    }

    /**
     * Send New Application Notification to Employer.
     *
     * @param int $application_id
     */
    public function send_new_application_notification($application_id)
    {
        $job_id = get_post_meta($application_id, '_job_applied_for', true);
        $job = get_post($job_id);

        if (!$job) {
            return;
        }

        $application_email = get_post_meta($job_id, '_application_email', true);
        $employer = get_userdata($job->post_author);

        if ($application_email) {
            $to = $application_email;
        } elseif ($employer) {
            $to = $employer->user_email;
        } else {
            return;
        }

        $candidate_name = get_post_meta($application_id, '_candidate_name', true);

        $subject = sprintf(__('New Application for %s', 'modern-job-board'), $job->post_title);

        $message = sprintf(__('You have received a new application for "%s".', 'modern-job-board'), $job->post_title) . "\n\n";
        $message .= sprintf(__('Candidate Name: %s', 'modern-job-board'), $candidate_name) . "\n";
        $message .= sprintf(__('Candidate Email: %s', 'modern-job-board'), get_post_meta($application_id, '_candidate_email', true)) . "\n";

        $dashboard_url = MJB_Dashboard::get_page_url(array(
            'action' => 'view_applications',
            'job_id' => intval($job_id),
        ));
        $message .= sprintf(__('View applications: %s', 'modern-job-board'), $dashboard_url) . "\n";

        $message .= "\n" . sprintf(__('Message:', 'modern-job-board')) . "\n";
        $message .= get_post_field('post_content', $application_id) . "\n";

        wp_mail($to, $subject, $message);

        do_action('mjb_application_notification_sent', $application_id, $to);
    }

    /**
     * Send application confirmation to the candidate.
     *
     * @param int $application_id
     */
    public function send_application_confirmation_to_candidate($application_id)
    {
        $application_id = intval($application_id);
        $job_id = intval(get_post_meta($application_id, '_job_applied_for', true));
        $job = $job_id ? get_post($job_id) : null;

        if (!$job) {
            return;
        }

        $candidate_email = sanitize_email(get_post_meta($application_id, '_candidate_email', true));
        if (!$candidate_email) {
            return;
        }

        $candidate_name = get_post_meta($application_id, '_candidate_name', true);
        $subject = sprintf(__('Application received: %s', 'modern-job-board'), $job->post_title);

        $message = sprintf(__('Hi %s,', 'modern-job-board'), $candidate_name) . "\n\n";
        $message .= sprintf(
            __('Thanks for applying for "%s". Your application has been received and forwarded to the employer.', 'modern-job-board'),
            $job->post_title
        ) . "\n\n";
        $message .= sprintf(__('View the job: %s', 'modern-job-board'), get_permalink($job_id)) . "\n";

        if (class_exists('MJB_Candidate_Dashboard')) {
            $message .= sprintf(__('Your dashboard: %s', 'modern-job-board'), MJB_Candidate_Dashboard::get_page_url()) . "\n";
        }

        wp_mail($candidate_email, $subject, $message);

        do_action('mjb_candidate_application_confirmation_sent', $application_id, $candidate_email);
    }
}