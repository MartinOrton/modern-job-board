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
    const TIME_TRAP_FIELD = 'mjb_form_loaded_at';
    const OPTION_MIN_SECONDS = 'mjb_spam_min_seconds';
    const OPTION_BANNED_IPS = 'mjb_banned_ips';
    const OPTION_SPAM_LOG = 'mjb_spam_log';
    const SPAM_LOG_MAX = 100;

    /**
     * Bootstrap settings + admin spam panel hooks.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 63);
        add_action('admin_post_mjb_save_banned_ips', array(__CLASS__, 'save_banned_ips'));
        add_action('admin_post_mjb_clear_spam_log', array(__CLASS__, 'clear_spam_log'));
    }

    /**
     * Settings under security section.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_MIN_SECONDS, array(
            'type' => 'integer',
            'default' => 3,
            'sanitize_callback' => function ($v) {
                return max(0, min(60, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_BANNED_IPS, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array(__CLASS__, 'sanitize_ip_list'),
        ));

        add_settings_field(
            self::OPTION_MIN_SECONDS,
            __('Form time trap (seconds)', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_MIN_SECONDS, 3);
                echo '<input type="number" min="0" max="60" name="' . esc_attr(self::OPTION_MIN_SECONDS) . '" value="' . esc_attr((string) $v) . '"> ';
                echo '<p class="description">' . esc_html__('Reject submissions faster than this (0 disables). Applies to applications and registration.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_security_section'
        );
        add_settings_field(
            self::OPTION_BANNED_IPS,
            __('Banned IPs', 'modern-job-board'),
            function () {
                $v = get_option(self::OPTION_BANNED_IPS, '');
                echo '<textarea name="' . esc_attr(self::OPTION_BANNED_IPS) . '" class="large-text code" rows="4" placeholder="203.0.113.10">' . esc_textarea($v) . '</textarea>';
                echo '<p class="description">' . esc_html__('One IP per line. Blocked from applications and public registration.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_security_section'
        );
    }

    /**
     * @param string $raw
     * @return string
     */
    public static function sanitize_ip_list($raw)
    {
        $lines = preg_split('/[\r\n,]+/', (string) $raw);
        $out = array();
        foreach ((array) $lines as $line) {
            $ip = trim($line);
            if ($ip === '') {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $out[] = $ip;
            }
        }
        return implode("\n", array_unique($out));
    }

    /**
     * @return array<int, string>
     */
    public static function get_banned_ips()
    {
        $raw = get_option(self::OPTION_BANNED_IPS, '');
        if ($raw === '' || $raw === false) {
            return array();
        }
        $lines = preg_split('/[\r\n,]+/', (string) $raw);
        $out = array();
        foreach ((array) $lines as $line) {
            $ip = trim($line);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $out[] = $ip;
            }
        }
        return $out;
    }

    /**
     * @param string|null $ip
     * @return bool
     */
    public static function is_ip_banned($ip = null)
    {
        $ip = self::get_client_ip($ip);
        return in_array($ip, self::get_banned_ips(), true);
    }

    /**
     * Hidden time-trap field markup.
     *
     * @return string
     */
    public static function render_time_trap_field()
    {
        $min = (int) get_option(self::OPTION_MIN_SECONDS, 3);
        if ($min <= 0) {
            return '';
        }
        $ts = function_exists('time') ? time() : 0;
        return '<input type="hidden" name="' . esc_attr(self::TIME_TRAP_FIELD) . '" value="' . esc_attr((string) $ts) . '">';
    }

    /**
     * Whether the form was submitted too quickly.
     *
     * @param int|null $loaded_at Unix timestamp from POST.
     * @return bool True when spam (too fast).
     */
    public static function is_time_trap_triggered($loaded_at = null)
    {
        $min = (int) get_option(self::OPTION_MIN_SECONDS, 3);
        if ($min <= 0) {
            return false;
        }
        if ($loaded_at === null) {
            $loaded_at = isset($_POST[self::TIME_TRAP_FIELD])
                ? (int) wp_unslash($_POST[self::TIME_TRAP_FIELD])
                : 0;
        }
        $loaded_at = (int) $loaded_at;
        if ($loaded_at <= 0) {
            // Missing timestamp is treated as trap fail (bots often strip unknown fields).
            return true;
        }
        $elapsed = time() - $loaded_at;
        // Reject negative (future) or too-fast submissions; allow up to 24h form open.
        if ($elapsed < 0 || $elapsed > DAY_IN_SECONDS) {
            return true;
        }
        return $elapsed < $min;
    }

    /**
     * Append a spam log entry.
     *
     * @param string $reason honeypot|time_trap|banned_ip|recaptcha|rate
     * @param string $context application|registration|other
     * @param string|null $ip
     */
    public static function log_spam($reason, $context = 'other', $ip = null)
    {
        $ip = self::get_client_ip($ip);
        $log = get_option(self::OPTION_SPAM_LOG, array());
        if (!is_array($log)) {
            $log = array();
        }
        array_unshift($log, array(
            't' => time(),
            'ip' => $ip,
            'reason' => sanitize_key($reason),
            'context' => sanitize_key($context),
        ));
        $log = array_slice($log, 0, self::SPAM_LOG_MAX);
        update_option(self::OPTION_SPAM_LOG, $log, false);
    }

    /**
     * @return array<int, array{t:int,ip:string,reason:string,context:string}>
     */
    public static function get_spam_log()
    {
        $log = get_option(self::OPTION_SPAM_LOG, array());
        return is_array($log) ? $log : array();
    }

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
     * @param string $context application|registration|other
     * @return string|null Notice code on failure, null when valid.
     */
    public static function validate_spam_protection($context = 'other')
    {
        if (self::is_ip_banned()) {
            self::log_spam('banned_ip', $context);
            return 'error_spam';
        }

        if (self::is_honeypot_triggered()) {
            self::log_spam('honeypot', $context);
            return 'error_spam';
        }

        if (self::is_time_trap_triggered()) {
            self::log_spam('time_trap', $context);
            return 'error_spam';
        }

        if (!MJB_Recaptcha::verify()) {
            self::log_spam('recaptcha', $context);
            return 'error_recaptcha';
        }

        return null;
    }

    /**
     * Admin spam log page.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Spam log', 'modern-job-board'),
            __('Spam log', 'modern-job-board'),
            'manage_options',
            'mjb-spam-log',
            array(__CLASS__, 'render_spam_admin')
        );
    }

    /**
     * Save banned IPs from spam admin form.
     */
    public static function save_banned_ips()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'modern-job-board'));
        }
        check_admin_referer('mjb_save_banned_ips');
        $raw = isset($_POST['banned_ips']) ? wp_unslash($_POST['banned_ips']) : '';
        update_option(self::OPTION_BANNED_IPS, self::sanitize_ip_list($raw), false);
        wp_safe_redirect(add_query_arg(array('page' => 'mjb-spam-log', 'updated' => '1'), admin_url('edit.php?post_type=job_listing')));
        exit;
    }

    /**
     * Clear spam log.
     */
    public static function clear_spam_log()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'modern-job-board'));
        }
        check_admin_referer('mjb_clear_spam_log');
        update_option(self::OPTION_SPAM_LOG, array(), false);
        wp_safe_redirect(add_query_arg(array('page' => 'mjb-spam-log', 'cleared' => '1'), admin_url('edit.php?post_type=job_listing')));
        exit;
    }

    /**
     * Spam log + ban list UI.
     */
    public static function render_spam_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $log = self::get_spam_log();
        $banned = get_option(self::OPTION_BANNED_IPS, '');
        echo '<div class="wrap"><h1>' . esc_html__('Spam log & IP bans', 'modern-job-board') . '</h1>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Banned IPs saved.', 'modern-job-board') . '</p></div>';
        }
        if (!empty($_GET['cleared'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Spam log cleared.', 'modern-job-board') . '</p></div>';
        }
        echo '<h2>' . esc_html__('Banned IPs', 'modern-job-board') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mjb_save_banned_ips">';
        wp_nonce_field('mjb_save_banned_ips');
        echo '<textarea name="banned_ips" class="large-text code" rows="5">' . esc_textarea($banned) . '</textarea>';
        submit_button(__('Save banned IPs', 'modern-job-board'));
        echo '</form>';

        echo '<h2>' . esc_html__('Recent blocks', 'modern-job-board') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="mjb-mt-4">';
        echo '<input type="hidden" name="action" value="mjb_clear_spam_log">';
        wp_nonce_field('mjb_clear_spam_log');
        submit_button(__('Clear log', 'modern-job-board'), 'secondary', 'submit', false);
        echo '</form>';
        if (empty($log)) {
            echo '<p>' . esc_html__('No spam blocks recorded yet.', 'modern-job-board') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__('When', 'modern-job-board') . '</th>';
            echo '<th>' . esc_html__('IP', 'modern-job-board') . '</th>';
            echo '<th>' . esc_html__('Reason', 'modern-job-board') . '</th>';
            echo '<th>' . esc_html__('Context', 'modern-job-board') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($log as $row) {
                $when = !empty($row['t']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $row['t']) : '';
                echo '<tr>';
                echo '<td>' . esc_html($when) . '</td>';
                echo '<td><code>' . esc_html(isset($row['ip']) ? $row['ip'] : '') . '</code></td>';
                echo '<td>' . esc_html(isset($row['reason']) ? $row['reason'] : '') . '</td>';
                echo '<td>' . esc_html(isset($row['context']) ? $row['context'] : '') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
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