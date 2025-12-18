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
        // No hooks needed for now as we call methods directly, 
        // but keeping init for consistency and future hook-based emails (e.g. status transitions).
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

        $employer_id = $job->post_author;
        $employer = get_userdata($employer_id);

        if (!$employer) {
            return;
        }

        $candidate_name = get_post_meta($application_id, '_candidate_name', true);

        $to = $employer->user_email;
        $subject = sprintf(__('New Application for %s', 'modern-job-board'), $job->post_title);

        $message = sprintf(__('You have received a new application for "%s".', 'modern-job-board'), $job->post_title) . "\n\n";
        $message .= sprintf(__('Candidate Name: %s', 'modern-job-board'), $candidate_name) . "\n";
        $message .= sprintf(__('Candidate Email: %s', 'modern-job-board'), get_post_meta($application_id, '_candidate_email', true)) . "\n";

        $resume_url = get_post_meta($application_id, '_candidate_resume', true);
        if ($resume_url) {
            $message .= sprintf(__('Resume: %s', 'modern-job-board'), $resume_url) . "\n";
        }

        $message .= "\n" . sprintf(__('Message:', 'modern-job-board')) . "\n";
        $message .= get_post_field('post_content', $application_id) . "\n";

        wp_mail($to, $subject, $message);
    }
}
