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

        $existing = get_posts(array(
            'post_type' => 'job_application',
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_job_applied_for',
                    'value' => $job_id,
                ),
                array(
                    'key' => '_candidate_email',
                    'value' => $email,
                ),
            ),
        ));

        return !empty($existing);
    }
}