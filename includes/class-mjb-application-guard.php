<?php
/**
 * Modern Job Board Application Abuse Prevention
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Application_Guard
{
    const RATE_LIMIT_MAX = 5;
    const RATE_LIMIT_WINDOW = 3600;
    const REG_RATE_LIMIT_MAX = 3;
    const REG_RATE_LIMIT_WINDOW = 3600;
    const HONEYPOT_FIELD = 'mjb_hp_website';

    /**
     * Determine whether the honeypot field was filled in by a bot.
     *
     * @param string|null $value
     * @return bool
     */
    public static function is_honeypot_triggered($value = null)
    {
        if ($value === null) {
            $value = isset($_POST[self::HONEYPOT_FIELD])
                ? wp_unslash($_POST[self::HONEYPOT_FIELD])
                : '';
        }

        return trim((string) $value) !== '';
    }

    /**
     * Determine whether the client IP is rate limited.
     *
     * @param string|null $ip
     * @return bool
     */
    public static function is_rate_limited($ip = null)
    {
        $count = self::get_rate_limit_count($ip);
        return $count >= self::RATE_LIMIT_MAX;
    }

    /**
     * Get the current submission count for an IP.
     *
     * @param string|null $ip
     * @return int
     */
    public static function get_rate_limit_count($ip = null)
    {
        return intval(get_transient(self::get_rate_limit_key($ip)));
    }

    /**
     * Record a successful application submission for rate limiting.
     *
     * @param string|null $ip
     */
    public static function record_submission($ip = null)
    {
        $key = self::get_rate_limit_key($ip);
        $count = intval(get_transient($key));
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    }

    /**
     * Build the transient key for an IP address.
     *
     * @param string|null $ip
     * @return string
     */
    public static function get_rate_limit_key($ip = null)
    {
        return 'mjb_app_rate_' . md5(self::get_client_ip($ip));
    }

    /**
     * Resolve the client IP address.
     *
     * @param string|null $ip
     * @return string
     */
    public static function get_client_ip($ip = null)
    {
        if ($ip !== null) {
            return sanitize_text_field($ip);
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '0.0.0.0';
    }

    /**
     * Determine whether registration attempts are rate limited.
     *
     * @param string|null $ip
     * @return bool
     */
    public static function is_registration_rate_limited($ip = null)
    {
        return self::get_registration_rate_limit_count($ip) >= self::REG_RATE_LIMIT_MAX;
    }

    /**
     * Get the current registration count for an IP.
     *
     * @param string|null $ip
     * @return int
     */
    public static function get_registration_rate_limit_count($ip = null)
    {
        return intval(get_transient(self::get_registration_rate_limit_key($ip)));
    }

    /**
     * Record a successful registration for rate limiting.
     *
     * @param string|null $ip
     */
    public static function record_registration($ip = null)
    {
        $key = self::get_registration_rate_limit_key($ip);
        $count = intval(get_transient($key));
        set_transient($key, $count + 1, self::REG_RATE_LIMIT_WINDOW);
    }

    /**
     * Build the transient key for registration rate limiting.
     *
     * @param string|null $ip
     * @return string
     */
    public static function get_registration_rate_limit_key($ip = null)
    {
        return 'mjb_reg_rate_' . md5(self::get_client_ip($ip));
    }

    /**
     * Validate shared spam-protection checks for public forms.
     *
     * @return string|null Notice code on failure, null when valid.
     */
    public static function validate_spam_protection()
    {
        if (self::is_honeypot_triggered()) {
            return 'error_spam';
        }

        if (!MJB_Recaptcha::verify()) {
            return 'error_recaptcha';
        }

        return null;
    }

    /**
     * Check whether an application already exists for this job and email.
     *
     * @param int    $job_id
     * @param string $email
     * @return bool
     */
    public static function has_duplicate_application($job_id, $email)
    {
        $job_id = intval($job_id);
        $email = sanitize_email($email);

        if (!$job_id || !$email) {
            return false;
        }

        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_job
                ON pm_job.post_id = p.ID
                AND pm_job.meta_key = '_job_applied_for'
                AND pm_job.meta_value = %s
             INNER JOIN {$wpdb->postmeta} pm_email
                ON pm_email.post_id = p.ID
                AND pm_email.meta_key = '_candidate_email'
                AND pm_email.meta_value = %s
             WHERE p.post_type = 'job_application'
             AND p.post_status IN ('publish', 'pending', 'draft')
             LIMIT 1",
            (string) $job_id,
            $email
        ));

        return !empty($existing);
    }
}