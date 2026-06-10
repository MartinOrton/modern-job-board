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
        );
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

require_once dirname(__DIR__) . '/includes/class-mjb-search.php';
require_once dirname(__DIR__) . '/includes/class-mjb-application-guard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-page-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mjb-recaptcha.php';
require_once dirname(__DIR__) . '/includes/class-mjb-woocommerce.php';