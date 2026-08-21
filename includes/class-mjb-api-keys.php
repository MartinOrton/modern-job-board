<?php
/**
 * Public API keys + simple rate limits (#41).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Api_Keys
{
    const OPTION = 'mjb_api_keys';
    const RATE_OPTION_PREFIX = 'mjb_api_rl_';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_filter('rest_authentication_errors', array(__CLASS__, 'authenticate'), 20);
        add_filter('rest_pre_dispatch', array(__CLASS__, 'rate_limit'), 10, 3);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 72);
        add_action('admin_post_mjb_create_api_key', array(__CLASS__, 'create_key'));
        add_action('admin_post_mjb_revoke_api_key', array(__CLASS__, 'revoke_key'));
    }

    /**
     * @return array<int, array{id:string,label:string,key_hash:string,created:int,revoked:int}>
     */
    public static function get_keys()
    {
        $keys = get_option(self::OPTION, array());
        return is_array($keys) ? $keys : array();
    }

    /**
     * Create a new API key; returns plaintext once.
     *
     * @param string $label
     * @return array{id:string,key:string}|WP_Error
     */
    public static function issue_key($label = '')
    {
        $plain = 'mjb_' . bin2hex(random_bytes(20));
        $id = wp_generate_uuid4();
        $keys = self::get_keys();
        $keys[] = array(
            'id' => $id,
            'label' => sanitize_text_field($label) ?: __('API key', 'modern-job-board'),
            'key_hash' => wp_hash_password($plain),
            'prefix' => substr($plain, 0, 10),
            'created' => time(),
            'revoked' => 0,
        );
        update_option(self::OPTION, $keys, false);
        return array('id' => $id, 'key' => $plain);
    }

    /**
     * @param string $provided
     * @return bool
     */
    public static function validate_key($provided)
    {
        $provided = trim((string) $provided);
        if ($provided === '') {
            return false;
        }
        foreach (self::get_keys() as $row) {
            if (!empty($row['revoked'])) {
                continue;
            }
            if (!empty($row['key_hash']) && wp_check_password($provided, $row['key_hash'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * REST auth via X-MJB-API-Key header.
     *
     * @param mixed $result
     * @return mixed
     */
    public static function authenticate($result)
    {
        if ($result !== null) {
            return $result;
        }
        $key = '';
        if (!empty($_SERVER['HTTP_X_MJB_API_KEY'])) {
            $key = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_MJB_API_KEY']));
        }
        if ($key === '' || strpos((string) (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''), '/wp-json/mjb/') === false) {
            return $result;
        }
        if (self::validate_key($key)) {
            // Mark as authenticated for public endpoints.
            return true;
        }
        return new WP_Error('mjb_api_key_invalid', __('Invalid API key.', 'modern-job-board'), array('status' => 401));
    }

    /**
     * Simple per-key rate limit (60/min).
     *
     * @param mixed           $result
     * @param WP_REST_Server  $server
     * @param WP_REST_Request $request
     * @return mixed
     */
    public static function rate_limit($result, $server, $request)
    {
        if (strpos($request->get_route(), '/mjb/') === false) {
            return $result;
        }
        $key = $request->get_header('x-mjb-api-key');
        if (!$key) {
            return $result;
        }
        $bucket = self::RATE_OPTION_PREFIX . md5($key . gmdate('YmdHi'));
        $count = (int) get_transient($bucket);
        if ($count >= 60) {
            return new WP_Error('mjb_rate_limited', __('API rate limit exceeded. Try again shortly.', 'modern-job-board'), array('status' => 429));
        }
        set_transient($bucket, $count + 1, 2 * MINUTE_IN_SECONDS);
        return $result;
    }

    /**
     * Admin UI.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('API keys', 'modern-job-board'),
            __('API keys', 'modern-job-board'),
            'manage_options',
            'mjb-api-keys',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Settings page.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $keys = self::get_keys();
        $new = isset($_GET['new_key']) ? sanitize_text_field(wp_unslash($_GET['new_key'])) : '';
        echo '<div class="wrap"><h1>' . esc_html__('API keys', 'modern-job-board') . '</h1>';
        if ($new !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Copy this key now; it will not be shown again:', 'modern-job-board') . ' <code>' . esc_html($new) . '</code></p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mjb_create_api_key">';
        wp_nonce_field('mjb_create_api_key');
        echo '<p><input type="text" name="label" class="regular-text" placeholder="' . esc_attr__('Label', 'modern-job-board') . '"> ';
        submit_button(__('Create key', 'modern-job-board'), 'primary', 'submit', false);
        echo '</p></form>';
        echo '<table class="widefat striped"><thead><tr><th>Label</th><th>Prefix</th><th>Created</th><th></th></tr></thead><tbody>';
        foreach ($keys as $row) {
            if (!empty($row['revoked'])) {
                continue;
            }
            echo '<tr>';
            echo '<td>' . esc_html($row['label']) . '</td>';
            echo '<td><code>' . esc_html(isset($row['prefix']) ? $row['prefix'] . '…' : '') . '</code></td>';
            echo '<td>' . esc_html(gmdate('Y-m-d', (int) $row['created'])) . '</td>';
            echo '<td><a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mjb_revoke_api_key&id=' . rawurlencode($row['id'])), 'mjb_revoke_api_key_' . $row['id'])) . '">' . esc_html__('Revoke', 'modern-job-board') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__('Send header X-MJB-API-Key on /wp-json/mjb/* requests. Limit: 60 requests/minute per key.', 'modern-job-board') . '</p>';
        echo '</div>';
    }

    /**
     * Create handler.
     */
    public static function create_key()
    {
        if (!current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mjb_create_api_key')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }
        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
        $issued = self::issue_key($label);
        $url = admin_url('edit.php?post_type=job_listing&page=mjb-api-keys');
        if (!is_wp_error($issued)) {
            $url = add_query_arg('new_key', rawurlencode($issued['key']), $url);
        }
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Revoke handler.
     */
    public static function revoke_key()
    {
        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        if (!current_user_can('manage_options') || !$id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mjb_revoke_api_key_' . $id)) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }
        $keys = self::get_keys();
        foreach ($keys as &$row) {
            if ($row['id'] === $id) {
                $row['revoked'] = time();
            }
        }
        unset($row);
        update_option(self::OPTION, $keys, false);
        wp_safe_redirect(admin_url('edit.php?post_type=job_listing&page=mjb-api-keys'));
        exit;
    }
}
