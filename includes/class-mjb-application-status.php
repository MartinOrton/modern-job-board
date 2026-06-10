<?php
/**
 * Modern Job Board Application Workflow Statuses
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Application_Status
{
    const META_KEY = '_mjb_application_status';
    const DEFAULT_STATUS = 'new';

    /**
     * Supported workflow statuses.
     *
     * @return array<string, string>
     */
    public static function get_statuses()
    {
        return array(
            'new' => __('New', 'modern-job-board'),
            'reviewed' => __('Reviewed', 'modern-job-board'),
            'shortlisted' => __('Shortlisted', 'modern-job-board'),
            'rejected' => __('Rejected', 'modern-job-board'),
            'hired' => __('Hired', 'modern-job-board'),
        );
    }

    /**
     * Whether a status slug is valid.
     *
     * @param string $status
     * @return bool
     */
    public static function is_valid($status)
    {
        return array_key_exists($status, self::get_statuses());
    }

    /**
     * Get the workflow status for an application.
     *
     * @param int $application_id
     * @return string
     */
    public static function get_status($application_id)
    {
        $status = sanitize_key(get_post_meta(intval($application_id), self::META_KEY, true));
        if ($status === '' || !self::is_valid($status)) {
            return self::DEFAULT_STATUS;
        }

        return $status;
    }

    /**
     * Get a human-readable label for a status slug.
     *
     * @param string $status
     * @return string
     */
    public static function get_label($status)
    {
        $statuses = self::get_statuses();
        $status = sanitize_key($status);

        return $statuses[$status] ?? $statuses[self::DEFAULT_STATUS];
    }

    /**
     * Update an application workflow status.
     *
     * @param int    $application_id
     * @param string $status
     * @return bool
     */
    public static function update_status($application_id, $status)
    {
        $application_id = intval($application_id);
        $status = sanitize_key($status);

        if (!$application_id || !self::is_valid($status)) {
            return false;
        }

        $old_status = self::get_status($application_id);
        update_post_meta($application_id, self::META_KEY, $status);

        if ($old_status !== $status) {
            do_action('mjb_application_status_updated', $application_id, $old_status, $status);
        }

        return true;
    }

    /**
     * Whether the current user may manage an application's status.
     *
     * @param int $application_id
     * @return bool
     */
    public static function user_can_manage($application_id)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $job_id = intval(get_post_meta(intval($application_id), '_job_applied_for', true));
        $job = $job_id ? get_post($job_id) : null;

        return $job && $job->post_type === 'job_listing' && intval($job->post_author) === $user_id;
    }
}