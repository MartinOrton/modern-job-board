<?php

define('ABSPATH', dirname(__DIR__) . '/');
define('HOUR_IN_SECONDS', 3600);

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

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
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
            'post_content' => $GLOBALS['mjb_test_post_content'][$post_id] ?? '',
            'post_type' => $GLOBALS['mjb_test_post_types'][$post_id] ?? 'page',
            'post_author' => $GLOBALS['mjb_test_post_authors'][$post_id] ?? 0,
        );
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post)
    {
        $post_id = is_object($post) ? $post->ID : intval($post);
        return $GLOBALS['mjb_test_post_types'][$post_id] ?? false;
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field($field, $post_id)
    {
        if ($field === 'post_author') {
            return $GLOBALS['mjb_test_post_authors'][intval($post_id)] ?? 0;
        }
        return '';
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
        return !empty($GLOBALS['mjb_test_user_caps'][$user_id][$capability]);
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
        if (!empty($args['fields']) && $args['fields'] === 'names') {
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

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return is_object($thing) && isset($thing->errors);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array())
    {
        if (!empty($GLOBALS['mjb_test_remote_responses'])) {
            return array_shift($GLOBALS['mjb_test_remote_responses']);
        }

        return array('body' => json_encode(array('success' => false)));
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
        return $GLOBALS['mjb_test_posts'] ?? array();
    }
}

if (!class_exists('MJB_Test_WPDB')) {
    class MJB_Test_WPDB
    {
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';

        public function prepare($query, ...$args)
        {
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
    }
}

$GLOBALS['wpdb'] = new MJB_Test_WPDB();

require_once dirname(__DIR__) . '/includes/class-mjb-search.php';
require_once dirname(__DIR__) . '/includes/class-mjb-resumes.php';
require_once dirname(__DIR__) . '/includes/class-mjb-application-guard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-page-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mjb-recaptcha.php';
require_once dirname(__DIR__) . '/includes/class-mjb-woocommerce.php';
require_once dirname(__DIR__) . '/includes/class-mjb-rest-api.php';
require_once dirname(__DIR__) . '/includes/class-mjb-feeds.php';