<?php
/**
 * Remote license client (#6, #7).
 *
 * Domain-bound activation + check-in + licensed update filter.
 * Server URL via MJB_LICENSE_API_URL or option mjb_license_api_url.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_License_Remote
{
    const OPTION_TOKEN = 'mjb_license_site_token';
    const OPTION_VALID_UNTIL = 'mjb_license_valid_until';
    const OPTION_LAST_CHECK = 'mjb_license_last_check';
    const OPTION_API_URL = 'mjb_license_api_url';
    const OPTION_GRACE_UNTIL = 'mjb_license_grace_until';
    const CRON_HOOK = 'mjb_license_remote_check';
    const GRACE_DAYS = 7;

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'schedule'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'check_in'));
        add_filter('mjb_license_plan', array(__CLASS__, 'filter_plan'), 5);
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_action('update_option_mjb_license_key', array(__CLASS__, 'on_key_saved'), 10, 2);
    }

    /**
     * @return string
     */
    public static function api_base()
    {
        if (defined('MJB_LICENSE_API_URL') && MJB_LICENSE_API_URL) {
            return untrailingslashit((string) MJB_LICENSE_API_URL);
        }
        $opt = get_option(self::OPTION_API_URL, '');
        return $opt ? untrailingslashit((string) $opt) : '';
    }

    /**
     * Whether remote mode is configured.
     *
     * @return bool
     */
    public static function is_configured()
    {
        return self::api_base() !== '';
    }

    /**
     * Daily check-in cron.
     */
    public static function schedule()
    {
        if (!self::is_configured()) {
            return;
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Activate when license key option changes.
     *
     * @param mixed $old
     * @param mixed $new
     */
    public static function on_key_saved($old, $new)
    {
        $new = is_string($new) ? trim($new) : '';
        if ($new === '' || !self::is_configured()) {
            return;
        }
        self::activate($new);
    }

    /**
     * POST /v1/activate
     *
     * @param string $key
     * @return true|WP_Error
     */
    public static function activate($key)
    {
        $base = self::api_base();
        if ($base === '') {
            return new WP_Error('mjb_license_no_api', __('Remote license API URL is not configured.', 'modern-job-board'));
        }

        $body = array(
            'key' => $key,
            'domain' => self::site_domain(),
            'plugin_version' => defined('MJB_VERSION') ? MJB_VERSION : '0',
            'site_url' => home_url('/'),
        );

        $response = wp_remote_post($base . '/v1/activate', array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['ok'])) {
            $msg = is_array($data) && !empty($data['message']) ? $data['message'] : __('License activation failed.', 'modern-job-board');
            return new WP_Error('mjb_license_activate_failed', $msg, array('status' => $code));
        }

        if (!empty($data['site_token'])) {
            update_option(self::OPTION_TOKEN, sanitize_text_field($data['site_token']), false);
        }
        if (!empty($data['plan']) && method_exists('MJB_License', 'is_valid_plan') && MJB_License::is_valid_plan($data['plan'])) {
            update_option(MJB_License::OPTION_PLAN, $data['plan'], false);
        }
        if (!empty($data['expires'])) {
            update_option(self::OPTION_VALID_UNTIL, sanitize_text_field($data['expires']), false);
        }
        update_option(self::OPTION_LAST_CHECK, time(), false);
        delete_option(self::OPTION_GRACE_UNTIL);

        return true;
    }

    /**
     * POST /v1/check
     *
     * @return true|WP_Error
     */
    public static function check_in()
    {
        $base = self::api_base();
        $token = get_option(self::OPTION_TOKEN, '');
        if ($base === '' || $token === '') {
            return new WP_Error('mjb_license_skip', 'not_configured');
        }

        $response = wp_remote_post($base . '/v1/check', array(
            'timeout' => 12,
            'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
            'body' => wp_json_encode(array(
                'site_token' => $token,
                'domain' => self::site_domain(),
                'plugin_version' => defined('MJB_VERSION') ? MJB_VERSION : '0',
            )),
        ));

        if (is_wp_error($response)) {
            // Start/keep grace period.
            $grace = get_option(self::OPTION_GRACE_UNTIL, 0);
            if (!$grace) {
                update_option(self::OPTION_GRACE_UNTIL, time() + self::GRACE_DAYS * DAY_IN_SECONDS, false);
            }
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code === 403 || (is_array($data) && !empty($data['revoked']))) {
            update_option(MJB_License::OPTION_PLAN, MJB_License::PLAN_FREE, false);
            delete_option(self::OPTION_TOKEN);
            return new WP_Error('mjb_license_revoked', 'revoked');
        }

        if ($code >= 200 && $code < 300 && is_array($data) && !empty($data['ok'])) {
            if (!empty($data['plan']) && MJB_License::is_valid_plan($data['plan'])) {
                update_option(MJB_License::OPTION_PLAN, $data['plan'], false);
            }
            if (!empty($data['expires'])) {
                update_option(self::OPTION_VALID_UNTIL, sanitize_text_field($data['expires']), false);
            }
            update_option(self::OPTION_LAST_CHECK, time(), false);
            delete_option(self::OPTION_GRACE_UNTIL);
            return true;
        }

        return new WP_Error('mjb_license_check_failed', 'check_failed', array('status' => $code));
    }

    /**
     * Prefer remote plan while token valid / within grace.
     *
     * @param mixed $plan
     * @return mixed
     */
    public static function filter_plan($plan)
    {
        if ($plan !== null) {
            return $plan;
        }
        if (!self::is_configured()) {
            return $plan;
        }

        $grace = (int) get_option(self::OPTION_GRACE_UNTIL, 0);
        if ($grace > 0 && time() > $grace) {
            // Grace expired without check-in → free.
            return MJB_License::PLAN_FREE;
        }

        $stored = get_option(MJB_License::OPTION_PLAN, MJB_License::PLAN_FREE);
        return MJB_License::is_valid_plan($stored) ? $stored : $plan;
    }

    /**
     * Licensed updates: GET /v1/updates/check
     *
     * @param object $transient
     * @return object
     */
    public static function inject_update($transient)
    {
        if (!is_object($transient) || !self::is_configured()) {
            return $transient;
        }
        $token = get_option(self::OPTION_TOKEN, '');
        if ($token === '') {
            return $transient;
        }

        $base = self::api_base();
        $url = add_query_arg(array(
            'site_token' => $token,
            'domain' => self::site_domain(),
            'slug' => 'modern-job-board',
            'version' => defined('MJB_VERSION') ? MJB_VERSION : '0',
        ), $base . '/v1/updates/check');

        $response = wp_remote_get($url, array('timeout' => 10));
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return $transient;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['version']) || empty($data['package'])) {
            return $transient;
        }

        $plugin_file = plugin_basename(MJB_PATH . 'modern-job-board.php');
        if (version_compare($data['version'], defined('MJB_VERSION') ? MJB_VERSION : '0', '<=')) {
            return $transient;
        }

        $transient->response[$plugin_file] = (object) array(
            'slug' => 'modern-job-board',
            'plugin' => $plugin_file,
            'new_version' => sanitize_text_field($data['version']),
            'package' => esc_url_raw($data['package']),
            'url' => !empty($data['url']) ? esc_url_raw($data['url']) : 'https://martinorton.com/modern-job-board',
        );

        return $transient;
    }

    /**
     * @return string
     */
    public static function site_domain()
    {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }
}
