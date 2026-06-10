<?php
/**
 * Modern Job Board Page Resolver
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Page_Resolver
{
    /**
     * Resolve a published page ID containing a shortcode.
     *
     * @param string $shortcode
     * @param string $option_key
     * @return int
     */
    public static function resolve_page_id($shortcode, $option_key)
    {
        $cached = intval(get_option($option_key));
        if ($cached && get_post_status($cached)) {
            return $cached;
        }

        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($pages as $page_id) {
            $post = get_post($page_id);
            if ($post && has_shortcode($post->post_content, $shortcode)) {
                update_option($option_key, $page_id, false);
                return intval($page_id);
            }
        }

        return 0;
    }

    /**
     * Build a permalink for a shortcode-backed page.
     *
     * @param string $shortcode
     * @param string $option_key
     * @param array  $query_args
     * @param string $fallback_path
     * @return string
     */
    public static function get_page_url($shortcode, $option_key, $query_args = array(), $fallback_path = '/')
    {
        $page_id = self::resolve_page_id($shortcode, $option_key);
        $url = $page_id ? get_permalink($page_id) : home_url($fallback_path);

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        return $url;
    }
}