<?php

define('ABSPATH', dirname(__DIR__) . '/');
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(__DIR__) . '/.test-wp-content');
}
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('YEAR_IN_SECONDS', 31536000);
define('MJB_VERSION', 'test');
define('OBJECT', 'OBJECT');
if (!defined('UPLOAD_ERR_OK')) {
    define('UPLOAD_ERR_OK', 0);
}

$GLOBALS['mjb_test_transients'] = array();
$GLOBALS['mjb_test_post_meta'] = array();
$GLOBALS['mjb_test_options'] = array();
$GLOBALS['mjb_test_posts'] = array();
$GLOBALS['mjb_test_post_content'] = array();
$GLOBALS['mjb_test_post_status'] = array();
$GLOBALS['mjb_test_permalinks'] = array();
$GLOBALS['mjb_test_remote_responses'] = array();
$GLOBALS['mjb_test_duplicate_exists'] = null;
$GLOBALS['mjb_test_post_types'] = array();
$GLOBALS['mjb_test_post_authors'] = array();
$GLOBALS['mjb_test_user_meta'] = array();
$GLOBALS['mjb_test_current_user_id'] = 0;
$GLOBALS['mjb_test_is_logged_in'] = false;
$GLOBALS['mjb_test_user_caps'] = array();
$GLOBALS['mjb_test_timestamp'] = 1700000000;
$GLOBALS['mjb_test_titles'] = array();
$GLOBALS['mjb_test_excerpts'] = array();
$GLOBALS['mjb_test_terms'] = array();
$GLOBALS['mjb_test_dates'] = array();
$GLOBALS['mjb_test_query_vars'] = array();
$GLOBALS['mjb_test_inserted_posts'] = array();
$GLOBALS['mjb_test_object_terms'] = array();
$GLOBALS['mjb_test_companies_by_title'] = array();
$GLOBALS['mjb_test_next_post_id'] = 1000;
$GLOBALS['mjb_test_user_roles'] = array();
$GLOBALS['mjb_test_user_emails'] = array();
$GLOBALS['mjb_test_status_updates'] = array();
$GLOBALS['mjb_test_referer'] = false;
$GLOBALS['mjb_test_remote_posts'] = array();
$GLOBALS['mjb_test_remote_response_code'] = 204;
$GLOBALS['mjb_test_mails'] = array();
$GLOBALS['mjb_test_cron_events'] = array();

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('_x')) {
    function _x($text, $context, $domain = null)
    {
        unset($context, $domain);

        return $text;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true)
    {
        $result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null)
    {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename)
    {
        $filename = (string) $filename;
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '', $filename);
        return $filename === '' ? 'file' : $filename;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
    {
        unset($status, $x_redirect_by);
        $GLOBALS['mjb_test_redirects'][] = (string) $location;
        return true;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        if ($component === -1) {
            return parse_url($url);
        }

        return parse_url($url, $component);
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = null)
    {
        return ((int) $number === 1) ? $single : $plural;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args)
    {
        if ($hook === 'mjb_application_status_updated' && count($args) === 3) {
            $GLOBALS['mjb_test_status_updates'][] = $args;
        }
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        unset($hook, $callback, $priority, $accepted_args);
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10)
    {
        unset($hook, $callback, $priority);
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        unset($hook, $callback, $priority, $accepted_args);
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10)
    {
        unset($hook, $callback, $priority);
        return true;
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash($value)
    {
        if (is_array($value)) {
            return array_map('wp_slash', $value);
        }

        return is_string($value) ? addslashes($value) : $value;
    }
}

if (!function_exists('clean_post_cache')) {
    function clean_post_cache($post_id)
    {
        unset($post_id);
    }
}

if (!function_exists('wp_get_referer')) {
    function wp_get_referer()
    {
        return $GLOBALS['mjb_test_referer'] ?? false;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data)
    {
        return json_encode($data);
    }
}

if (!function_exists('is_singular')) {
    function is_singular($post_type = '')
    {
        return false;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        return 0;
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null)
    {
        echo esc_html($text);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null)
    {
        unset($domain);
        return esc_attr($text);
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = null)
    {
        echo esc_attr__($text, $domain);
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        return is_scalar($str) ? trim((string) $str) : '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array())
    {
        if (!is_array($args)) {
            return $defaults;
        }
        return array_merge($defaults, $args);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key)
    {
        return $GLOBALS['mjb_test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0)
    {
        $GLOBALS['mjb_test_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key)
    {
        unset($GLOBALS['mjb_test_transients'][$key]);
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        $all = $GLOBALS['mjb_test_post_meta'][$post_id] ?? array();
        if ($key === '') {
            return $all;
        }
        if (!isset($all[$key])) {
            return $single ? '' : array();
        }
        return $single ? $all[$key] : array($all[$key]);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value)
    {
        $GLOBALS['mjb_test_post_meta'][$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false)
    {
        return array_key_exists($key, $GLOBALS['mjb_test_options'])
            ? $GLOBALS['mjb_test_options'][$key]
            : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null)
    {
        $GLOBALS['mjb_test_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key)
    {
        unset($GLOBALS['mjb_test_options'][$key]);
        return true;
    }
}

if (!function_exists('wp_count_posts')) {
    function wp_count_posts($type = 'post')
    {
        $counts = $GLOBALS['mjb_test_post_counts'][$type] ?? null;
        if (is_object($counts)) {
            return $counts;
        }
        if (is_array($counts)) {
            return (object) $counts;
        }

        return (object) array('publish' => 0, 'draft' => 0, 'pending' => 0, 'trash' => 0);
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($post)
    {
        $post_id = is_object($post) ? $post->ID : intval($post);
        return $GLOBALS['mjb_test_post_status'][$post_id] ?? false;
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id)
    {
        $post_id = intval($post_id);
        if (!isset($GLOBALS['mjb_test_post_status'][$post_id])) {
            return null;
        }

        return (object) array(
            'ID' => $post_id,
            'post_title' => $GLOBALS['mjb_test_titles'][$post_id] ?? '',
            'post_content' => $GLOBALS['mjb_test_post_content'][$post_id] ?? '',
            'post_type' => $GLOBALS['mjb_test_post_types'][$post_id] ?? 'page',
            'post_status' => $GLOBALS['mjb_test_post_status'][$post_id] ?? 'publish',
            'post_author' => $GLOBALS['mjb_test_post_authors'][$post_id] ?? 0,
        );
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wp_error = false)
    {
        $post_id = intval($postarr['ID'] ?? 0);
        if ($post_id < 1 || !isset($GLOBALS['mjb_test_post_status'][$post_id])) {
            return $wp_error ? new WP_Error('invalid_post', 'Invalid post') : 0;
        }

        if (isset($postarr['post_title'])) {
            $GLOBALS['mjb_test_titles'][$post_id] = $postarr['post_title'];
        }
        if (isset($postarr['post_content'])) {
            $GLOBALS['mjb_test_post_content'][$post_id] = $postarr['post_content'];
        }
        if (isset($postarr['post_status'])) {
            $GLOBALS['mjb_test_post_status'][$post_id] = $postarr['post_status'];
        }

        return $post_id;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, $force_delete = false)
    {
        unset($force_delete);
        $post_id = intval($post_id);
        unset(
            $GLOBALS['mjb_test_post_status'][$post_id],
            $GLOBALS['mjb_test_post_types'][$post_id],
            $GLOBALS['mjb_test_titles'][$post_id],
            $GLOBALS['mjb_test_post_content'][$post_id],
            $GLOBALS['mjb_test_post_authors'][$post_id],
            $GLOBALS['mjb_test_post_meta'][$post_id],
            $GLOBALS['mjb_test_inserted_posts'][$post_id]
        );

        foreach (($GLOBALS['mjb_test_companies_by_title'] ?? array()) as $key => $id) {
            if (intval($id) === $post_id) {
                unset($GLOBALS['mjb_test_companies_by_title'][$key]);
            }
        }

        $GLOBALS['mjb_test_posts'] = array_values(array_filter(
            $GLOBALS['mjb_test_posts'] ?? array(),
            static function ($id) use ($post_id) {
                return intval($id) !== $post_id;
            }
        ));

        return (object) array('ID' => $post_id);
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post)
    {
        $post_id = is_object($post) ? $post->ID : intval($post);
        if (!isset($GLOBALS['mjb_test_post_status'][$post_id])) {
            return false;
        }

        return $GLOBALS['mjb_test_post_types'][$post_id] ?? 'page';
    }
}

if (!function_exists('wp_is_post_autosave')) {
    function wp_is_post_autosave($post_id)
    {
        return false;
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision($post_id)
    {
        return false;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return (bool) $GLOBALS['mjb_test_is_logged_in'];
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return intval($GLOBALS['mjb_test_current_user_id']);
    }
}

if (!function_exists('user_can')) {
    function user_can($user_id, $capability)
    {
        if ($capability === 'manage_options') {
            return !empty($GLOBALS['mjb_test_user_caps'][$user_id]['manage_options']);
        }

        return !empty($GLOBALS['mjb_test_user_caps'][$user_id][$capability]);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability)
    {
        return user_can(get_current_user_id(), $capability);
    }
}

if (!function_exists('content_url')) {
    function content_url($path = '')
    {
        return 'http://example.test/wp-content/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('wp_check_filetype_and_ext')) {
    function wp_check_filetype_and_ext($file, $filename, $mimes = null)
    {
        unset($file, $mimes);
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return array('ext' => false, 'type' => false, 'proper_filename' => false);
        }
        return array(
            'ext' => $ext,
            'type' => 'application/octet-stream',
            'proper_filename' => $filename,
        );
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($filename, $mimes = null)
    {
        unset($mimes);
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        return array('ext' => $ext, 'type' => $ext ? 'application/octet-stream' : false);
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        $user_id = intval($GLOBALS['mjb_test_current_user_id']);
        return (object) array(
            'ID' => $user_id,
            'roles' => $GLOBALS['mjb_test_user_roles'][$user_id] ?? array(),
            'user_email' => $GLOBALS['mjb_test_user_emails'][$user_id] ?? 'user@example.test',
        );
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        $user_id = intval($user_id);
        if (!$user_id) {
            return false;
        }

        return (object) array(
            'ID' => $user_id,
            'user_email' => $GLOBALS['mjb_test_user_emails'][$user_id] ?? 'user@example.test',
            'display_name' => $GLOBALS['mjb_test_user_display'][$user_id] ?? 'Test User',
            'roles' => $GLOBALS['mjb_test_user_roles'][$user_id] ?? array(),
            'first_name' => $GLOBALS['mjb_test_user_first'][$user_id] ?? '',
            'last_name' => $GLOBALS['mjb_test_user_last'][$user_id] ?? '',
        );
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth')
    {
        return 'mjb-test-salt-' . $scheme;
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field($field, $post_id)
    {
        if ($field === 'post_author') {
            return $GLOBALS['mjb_test_post_authors'][intval($post_id)] ?? 0;
        }
        if ($field === 'post_content') {
            return $GLOBALS['mjb_test_post_content'][intval($post_id)] ?? '';
        }
        return '';
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string)
    {
        return strip_tags((string) $string);
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false)
    {
        $all = $GLOBALS['mjb_test_user_meta'][$user_id] ?? array();
        if ($key === '') {
            return $all;
        }
        if (!isset($all[$key])) {
            return $single ? '' : array();
        }
        return $single ? $all[$key] : array($all[$key]);
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '')
    {
        unset($prev_value);
        $user_id = intval($user_id);
        if (!isset($GLOBALS['mjb_test_user_meta'][$user_id])) {
            $GLOBALS['mjb_test_user_meta'][$user_id] = array();
        }
        $GLOBALS['mjb_test_user_meta'][$user_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key, $meta_value = '')
    {
        unset($meta_value);
        $user_id = intval($user_id);
        if (isset($GLOBALS['mjb_test_user_meta'][$user_id][$meta_key])) {
            unset($GLOBALS['mjb_test_user_meta'][$user_id][$meta_key]);
        }
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type)
    {
        return $type === 'timestamp' ? $GLOBALS['mjb_test_timestamp'] : (string) $GLOBALS['mjb_test_timestamp'];
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post_id = 0)
    {
        $post_id = $post_id ? intval($post_id) : 0;
        return $GLOBALS['mjb_test_titles'][$post_id] ?? 'Test Post';
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($post_id = 0)
    {
        return $GLOBALS['mjb_test_excerpts'][intval($post_id)] ?? '';
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post_id = null)
    {
        return $GLOBALS['mjb_test_dates'][intval($post_id)] ?? '2026-06-10 12:00:00';
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = array())
    {
        $terms = $GLOBALS['mjb_test_terms'][intval($post_id)][$taxonomy] ?? array();
        $fields = !empty($args['fields']) ? $args['fields'] : 'all';
        if ($fields === 'names' || $fields === 'slugs') {
            return $terms;
        }
        return $terms;
    }
}

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag)
    {
        return strpos((string) $content, '[' . $tag) !== false;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id = 0)
    {
        return $GLOBALS['mjb_test_permalinks'][intval($post_id)] ?? 'https://example.test/?p=' . intval($post_id);
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return rtrim($string, '/\\') . '/';
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '')
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($key, $default = '')
    {
        return $GLOBALS['mjb_test_query_vars'][$key] ?? $default;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title)
    {
        $title = strtolower(trim((string) $title));
        return preg_replace('/[^a-z0-9\\s-]/', '', str_replace(' ', '-', $title));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return strtolower(preg_replace('/[^a-z0-9_\\-]/', '', (string) $key));
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = false, $url = false)
    {
        if (is_array($key)) {
            $url = $value ?: '';
            $args = $key;
        } else {
            $args = array($key => $value);
        }

        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . http_build_query($args);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1)
    {
        return 'nonce_' . substr(md5((string) $action), 0, 10);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        return is_string($nonce) && $nonce !== '' && hash_equals(wp_create_nonce($action), (string) $nonce);
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce')
    {
        return add_query_arg($name, wp_create_nonce($action), $actionurl);
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        unset($referer);
        $html = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '">';
        if ($echo) {
            echo $html;
        }

        return $html;
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email)
    {
        $list = $GLOBALS['mjb_test_emails'] ?? array();
        return in_array((string) $email, $list, true) ? 1 : false;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return is_object($thing) && isset($thing->errors);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array())
    {
        $GLOBALS['mjb_test_remote_posts'][] = array('url' => $url, 'args' => $args);

        if (!empty($GLOBALS['mjb_test_remote_responses'])) {
            return array_shift($GLOBALS['mjb_test_remote_responses']);
        }

        return array(
            'response' => array('code' => $GLOBALS['mjb_test_remote_response_code'] ?? 204),
            'body' => '',
        );
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) && isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = array())
    {
        $post_type = $args['post_type'] ?? '';

        if ($post_type === 'company' || $post_type === 'job_application' || $post_type === 'mjb_resume') {
            $posts = array();
            foreach (($GLOBALS['mjb_test_post_types'] ?? array()) as $post_id => $type) {
                if ($type === $post_type) {
                    $posts[] = intval($post_id);
                }
            }
            if ($post_type === 'company') {
                foreach (array_values($GLOBALS['mjb_test_companies_by_title'] ?? array()) as $post_id) {
                    $posts[] = intval($post_id);
                }
            }
            $posts = array_values(array_unique($posts));
        } else {
            $posts = $GLOBALS['mjb_test_posts'] ?? array();
        }

        if (!empty($args['meta_key']) && array_key_exists('meta_value', $args)) {
            $posts = array_values(array_filter($posts, static function ($post_id) use ($args) {
                $meta_value = get_post_meta(intval($post_id), $args['meta_key'], true);
                return (string) $meta_value === (string) $args['meta_value'];
            }));
        }

        // Minimal meta_query support (OR / =) for resume-reference checks.
        if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
            $query = $args['meta_query'];
            $relation = strtoupper((string) ($query['relation'] ?? 'AND'));
            $clauses = array();
            foreach ($query as $key => $clause) {
                if ($key === 'relation' || !is_array($clause) || empty($clause['key'])) {
                    continue;
                }
                $clauses[] = $clause;
            }
            if (!empty($clauses)) {
                $posts = array_values(array_filter($posts, static function ($post_id) use ($clauses, $relation) {
                    $matches = array();
                    foreach ($clauses as $clause) {
                        $stored = get_post_meta(intval($post_id), $clause['key'], true);
                        $compare = $clause['compare'] ?? '=';
                        $wanted = $clause['value'] ?? '';
                        if ($compare === '=') {
                            $matches[] = (string) $stored === (string) $wanted;
                        } else {
                            $matches[] = false;
                        }
                    }
                    if ($relation === 'OR') {
                        return in_array(true, $matches, true);
                    }
                    return !in_array(false, $matches, true);
                }));
            }
        }

        if (!empty($args['post_type']) && $post_type !== 'company') {
            $posts = array_values(array_filter($posts, static function ($post_id) use ($args) {
                return get_post_type($post_id) === $args['post_type'];
            }));
        }

        if (!empty($args['post_status'])) {
            $statuses = (array) $args['post_status'];
            if (in_array('any', $statuses, true)) {
                // no filter
            } else {
                $posts = array_values(array_filter($posts, static function ($post_id) use ($statuses) {
                    $status = $GLOBALS['mjb_test_post_status'][intval($post_id)] ?? 'publish';
                    return in_array($status, $statuses, true);
                }));
            }
        }

        if (!empty($args['post__in']) && is_array($args['post__in'])) {
            $wanted = array_map('intval', $args['post__in']);
            $posts = array_values(array_filter($posts, static function ($post_id) use ($wanted) {
                return in_array(intval($post_id), $wanted, true);
            }));
            // Preserve post__in order when orderby is post__in.
            if (!empty($args['orderby']) && $args['orderby'] === 'post__in') {
                $ordered = array();
                foreach ($wanted as $id) {
                    if (in_array($id, $posts, true)) {
                        $ordered[] = $id;
                    }
                }
                $posts = $ordered;
            }
        }

        if (isset($args['author']) && $args['author'] !== '' && $args['author'] !== null) {
            $author_id = intval($args['author']);
            $posts = array_values(array_filter($posts, static function ($post_id) use ($author_id) {
                return intval($GLOBALS['mjb_test_post_authors'][intval($post_id)] ?? 0) === $author_id;
            }));
        }

        if (!empty($args['name'])) {
            $name = (string) $args['name'];
            $posts = array_values(array_filter($posts, static function ($post_id) use ($name) {
                $title = $GLOBALS['mjb_test_titles'][intval($post_id)] ?? '';
                return sanitize_title($title) === $name;
            }));
        }

        if (!empty($args['posts_per_page']) && intval($args['posts_per_page']) > 0) {
            $posts = array_slice($posts, 0, intval($args['posts_per_page']));
        }

        if (!empty($args['fields']) && $args['fields'] === 'ids') {
            return $posts;
        }

        return array_map(static function ($post_id) {
            $post_id = intval($post_id);
            return (object) array(
                'ID' => $post_id,
                'post_title' => $GLOBALS['mjb_test_titles'][$post_id] ?? '',
                'post_type' => $GLOBALS['mjb_test_post_types'][$post_id] ?? 'post',
                'post_status' => $GLOBALS['mjb_test_post_status'][$post_id] ?? 'publish',
            );
        }, $posts);
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false)
    {
        $post_id = $GLOBALS['mjb_test_next_post_id']++;
        $postarr = is_array($postarr) ? $postarr : array();

        $GLOBALS['mjb_test_inserted_posts'][$post_id] = $postarr;
        $GLOBALS['mjb_test_post_status'][$post_id] = $postarr['post_status'] ?? 'publish';
        $GLOBALS['mjb_test_post_types'][$post_id] = $postarr['post_type'] ?? 'post';
        $GLOBALS['mjb_test_post_content'][$post_id] = $postarr['post_content'] ?? '';
        $GLOBALS['mjb_test_post_authors'][$post_id] = $postarr['post_author'] ?? 0;
        $GLOBALS['mjb_test_titles'][$post_id] = $postarr['post_title'] ?? '';

        if (($postarr['post_type'] ?? '') === 'company' && !empty($postarr['post_title'])) {
            $GLOBALS['mjb_test_companies_by_title'][$postarr['post_title']] = $post_id;
            $normalized_key = function_exists('mb_strtolower')
                ? mb_strtolower(trim(html_entity_decode(wp_strip_all_tags($postarr['post_title']), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8')
                : strtolower(trim(html_entity_decode(wp_strip_all_tags($postarr['post_title']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($normalized_key !== '') {
                $GLOBALS['mjb_test_companies_by_title'][$normalized_key] = $post_id;
            }
        }

        if (($postarr['post_type'] ?? '') === 'job_listing') {
            $GLOBALS['mjb_test_posts'][] = $post_id;
        }

        return $post_id;
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($post_id, $terms, $taxonomy)
    {
        if (!is_array($terms)) {
            $terms = array($terms);
        }

        $GLOBALS['mjb_test_terms'][intval($post_id)][$taxonomy] = array_map('strval', $terms);
        return true;
    }
}

if (!function_exists('get_page_by_title')) {
    function get_page_by_title($title, $output = OBJECT, $post_type = 'page')
    {
        if ($post_type === 'company' && isset($GLOBALS['mjb_test_companies_by_title'][$title])) {
            $post_id = $GLOBALS['mjb_test_companies_by_title'][$title];
            return (object) array('ID' => $post_id);
        }

        return null;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data)
    {
        return (string) $data;
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($data, $allowed_html = array(), $allowed_protocols = array())
    {
        unset($allowed_html, $allowed_protocols);
        return (string) $data;
    }
}

if (!function_exists('wp_trash_post')) {
    function wp_trash_post($post_id)
    {
        $post_id = intval($post_id);
        if (!isset($GLOBALS['mjb_test_post_status'][$post_id])) {
            return false;
        }
        $GLOBALS['mjb_test_post_status'][$post_id] = 'trash';
        return get_post($post_id);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url, $protocols = null, $_context = 'display')
    {
        unset($protocols, $_context);
        return (string) $url;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        $url = (string) $url;
        if ($url === '') {
            return '';
        }
        if (stripos($url, 'mailto:') === 0) {
            return $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = array(), $attachments = array())
    {
        $GLOBALS['mjb_test_mails'][] = array(
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
        );

        return true;
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '')
    {
        if ($show === 'name') {
            return 'MJB Test Site';
        }

        return '';
    }
}

if (!function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode($string, $quote_style = ENT_QUOTES)
    {
        return html_entity_decode((string) $string, $quote_style, 'UTF-8');
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand($min = 0, $max = 0)
    {
        return mt_rand($min, $max);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array())
    {
        if (!empty($GLOBALS['mjb_test_remote_get_responses'][$url])) {
            return $GLOBALS['mjb_test_remote_get_responses'][$url];
        }

        return array('response' => array('code' => 404), 'body' => '');
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_array($response) && isset($response['response']['code']) ? $response['response']['code'] : 0;
    }
}

if (!function_exists('wp_http_validate_url')) {
    function wp_http_validate_url($url)
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($post_id, $context = 'display')
    {
        return 'https://example.test/wp-admin/post.php?post=' . intval($post_id) . '&action=edit';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $errors = array();

        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;
            }
        }

        public function get_error_code()
        {
            foreach ($this->errors as $code => $messages) {
                return (string) $code;
            }

            return '';
        }

        public function get_error_message()
        {
            foreach ($this->errors as $messages) {
                return $messages[0] ?? '';
            }

            return '';
        }
    }
}

if (!class_exists('MJB_Test_WPDB')) {
    class MJB_Test_WPDB
    {
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';

        public function prepare($query, ...$args)
        {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }

            if (empty($args)) {
                return $query;
            }

            $escaped = array_map(static function ($arg) {
                return is_numeric($arg) ? $arg : "'" . str_replace("'", "''", (string) $arg) . "'";
            }, $args);

            return vsprintf(str_replace('%s', '%s', $query), $escaped);
        }

        public function get_var($query)
        {
            return $GLOBALS['mjb_test_duplicate_exists'];
        }

        public function get_results($query, $output = OBJECT)
        {
            $rows = $GLOBALS['mjb_test_db_results'] ?? array();
            if ($output === ARRAY_A) {
                return $rows;
            }

            return array_map(static function ($row) {
                return (object) $row;
            }, $rows);
        }
    }
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['wpdb'] = new MJB_Test_WPDB();

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir()
    {
        $base = sys_get_temp_dir() . '/mjb-test-uploads';
        return array(
            'path' => $base,
            'url' => 'http://example.test/uploads',
            'subdir' => '',
            'basedir' => $base,
            'baseurl' => 'http://example.test/uploads',
            'error' => false,
        );
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path)
    {
        return str_replace('\\', '/', (string) $path);
    }
}

if (!function_exists('username_exists')) {
    function username_exists($username)
    {
        $list = $GLOBALS['mjb_test_usernames'] ?? array();
        return in_array((string) $username, $list, true) ? 1 : false;
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false)
    {
        $username = preg_replace('/[^a-zA-Z0-9_\-@\.]/', '', (string) $username);
        unset($strict);
        return $username;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target)
    {
        $target = (string) $target;
        if ($target === '' || is_dir($target)) {
            return true;
        }
        return mkdir($target, 0777, true);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        unset($special_chars, $extra_special_chars);
        return substr(bin2hex(random_bytes(16)), 0, max(1, (int) $length));
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0)
    {
        unset($decimals);
        return ((int) $bytes) . ' B';
    }
}

if (!defined('MJB_PATH')) {
    define('MJB_PATH', dirname(__DIR__) . '/');
}
if (!defined('MJB_URL')) {
    define('MJB_URL', 'http://example.test/wp-content/plugins/modern-job-board/');
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '')
    {
        unset($plugin);
        return rtrim(MJB_URL, '/') . '/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format((float) $number, (int) $decimals);
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false, $gmt = false)
    {
        unset($gmt);
        $timestamp = $timestamp === false ? time() : (int) $timestamp;
        return date($format, $timestamp);
    }
}


if (!function_exists('wp_hash_password')) {
    function wp_hash_password($password)
    {
        return 'hash:' . md5((string) $password);
    }
}
if (!function_exists('wp_check_password')) {
    function wp_check_password($password, $hash, $user_id = '')
    {
        unset($user_id);
        return $hash === ('hash:' . md5((string) $password));
    }
}
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }
}
if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        $color = trim((string) $color);
        if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color)) {
            return $color;
        }
        return '';
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url($url, $component);
    }
}
if (!function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_US';
    }
}
if (!function_exists('is_ssl')) {
    function is_ssl()
    {
        return false;
    }
}
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}

require_once dirname(__DIR__) . '/includes/class-mjb-license.php';
require_once dirname(__DIR__) . '/includes/class-mjb-license-commerce.php';
require_once dirname(__DIR__) . '/includes/class-mjb-private-uploads.php';
require_once dirname(__DIR__) . '/includes/class-mjb-account-status.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-bar.php';
require_once dirname(__DIR__) . '/includes/class-mjb-job-routes.php';
require_once dirname(__DIR__) . '/includes/class-mjb-job-permalinks.php';
require_once dirname(__DIR__) . '/includes/class-mjb-location.php';
require_once dirname(__DIR__) . '/includes/class-mjb-icons.php';
require_once dirname(__DIR__) . '/includes/class-mjb-shortcodes.php';
require_once dirname(__DIR__) . '/includes/class-mjb-search.php';
require_once dirname(__DIR__) . '/includes/class-mjb-resumes.php';
require_once dirname(__DIR__) . '/includes/class-mjb-notices.php';
require_once dirname(__DIR__) . '/includes/class-mjb-employer-registration.php';
require_once dirname(__DIR__) . '/includes/class-mjb-candidate-registration.php';
require_once dirname(__DIR__) . '/includes/class-mjb-application-guard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-page-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mjb-recaptcha.php';
require_once dirname(__DIR__) . '/includes/class-mjb-woocommerce.php';
require_once dirname(__DIR__) . '/includes/class-mjb-rest-api.php';
require_once dirname(__DIR__) . '/includes/class-mjb-feeds.php';
require_once dirname(__DIR__) . '/includes/class-mjb-job-importer.php';
require_once dirname(__DIR__) . '/includes/class-mjb-xml-importer.php';
require_once dirname(__DIR__) . '/includes/class-mjb-import-scheduler.php';
require_once dirname(__DIR__) . '/includes/class-mjb-page-wizard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-application-status.php';
require_once dirname(__DIR__) . '/includes/class-mjb-rest-api-v2.php';
require_once dirname(__DIR__) . '/includes/class-mjb-dashboard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-saved-jobs.php';
require_once dirname(__DIR__) . '/includes/class-mjb-data-grid.php';
require_once dirname(__DIR__) . '/includes/class-mjb-blocks.php';
require_once dirname(__DIR__) . '/includes/class-mjb-analytics.php';
require_once dirname(__DIR__) . '/includes/class-mjb-webhook-queue.php';
require_once dirname(__DIR__) . '/includes/class-mjb-webhooks.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-tabs.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-dashboard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-jobs.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-companies.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-applications.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin-resumes.php';
require_once dirname(__DIR__) . '/includes/class-mjb-document-label.php';
require_once dirname(__DIR__) . '/includes/class-mjb-admin.php';
require_once dirname(__DIR__) . '/includes/class-mjb-license-remote.php';
require_once dirname(__DIR__) . '/includes/class-mjb-legacy-redirects.php';
require_once dirname(__DIR__) . '/includes/class-mjb-private-board.php';
require_once dirname(__DIR__) . '/includes/class-mjb-job-alerts.php';
require_once dirname(__DIR__) . '/includes/class-mjb-candidate-account.php';
require_once dirname(__DIR__) . '/includes/class-mjb-talent-pool.php';
require_once dirname(__DIR__) . '/includes/class-mjb-collaborators.php';
require_once dirname(__DIR__) . '/includes/class-mjb-embed.php';
require_once dirname(__DIR__) . '/includes/class-mjb-brand.php';
require_once dirname(__DIR__) . '/includes/class-mjb-string-overrides.php';
require_once dirname(__DIR__) . '/includes/class-mjb-auto-approve.php';
require_once dirname(__DIR__) . '/includes/class-mjb-company-preview.php';
require_once dirname(__DIR__) . '/includes/class-mjb-messaging.php';
require_once dirname(__DIR__) . '/includes/class-mjb-analytics-export.php';
require_once dirname(__DIR__) . '/includes/class-mjb-api-keys.php';
require_once dirname(__DIR__) . '/includes/class-mjb-pwa.php';
require_once dirname(__DIR__) . '/includes/class-mjb-filter-settings.php';
require_once dirname(__DIR__) . '/includes/class-mjb-packages.php';
require_once dirname(__DIR__) . '/includes/class-mjb-partner-import.php';
require_once dirname(__DIR__) . '/includes/class-mjb-seo-landings.php';
require_once dirname(__DIR__) . '/includes/class-mjb-media-listings.php';
require_once dirname(__DIR__) . '/includes/class-mjb-sms.php';
require_once dirname(__DIR__) . '/includes/class-mjb-i18n-board.php';
require_once dirname(__DIR__) . '/includes/class-mjb-blog-package.php';
require_once dirname(__DIR__) . '/includes/class-mjb-google-jobs.php';
require_once dirname(__DIR__) . '/includes/class-mjb-job-ops.php';
require_once dirname(__DIR__) . '/includes/class-mjb-resume-privacy.php';
require_once dirname(__DIR__) . '/includes/class-mjb-jobs-map.php';
require_once dirname(__DIR__) . '/includes/class-mjb-promotions.php';
require_once dirname(__DIR__) . '/includes/class-mjb-ingestion.php';
require_once dirname(__DIR__) . '/includes/class-mjb-memberships.php';
require_once dirname(__DIR__) . '/includes/class-mjb-commerce-admin.php';
require_once dirname(__DIR__) . '/includes/class-mjb-email-templates.php';
require_once dirname(__DIR__) . '/includes/class-mjb-mailchimp.php';
require_once dirname(__DIR__) . '/includes/class-mjb-board-polish.php';
require_once dirname(__DIR__) . '/includes/class-mjb-applications.php';
