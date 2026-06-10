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
     * Initialize cache invalidation hooks.
     */
    public static function init()
    {
        add_action('save_post_page', array(__CLASS__, 'maybe_invalidate_on_save'), 10, 3);
        add_action('delete_post', array(__CLASS__, 'maybe_invalidate_on_delete'), 10, 2);
        add_action('trashed_post', array(__CLASS__, 'maybe_invalidate_on_delete'), 10, 1);
    }

    /**
     * Known shortcode to option key mappings.
     *
     * @return array<string, string>
     */
    public static function get_option_map()
    {
        return array(
            'mjb_jobs' => 'mjb_jobs_page_id',
            'mjb_dashboard' => 'mjb_employer_dashboard_page_id',
            'mjb_job_form' => 'mjb_job_form_page_id',
            'mjb_candidate_dashboard' => 'mjb_candidate_dashboard_page_id',
            'mjb_candidate_registration' => 'mjb_candidate_registration_page_id',
            'mjb_employer_registration' => 'mjb_employer_registration_page_id',
        );
    }

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
     * Prefer the current referer, then a shortcode-backed page URL.
     *
     * @param string $shortcode
     * @param string $option_key
     * @param string $fallback_path
     * @return string
     */
    public static function get_request_fallback_url($shortcode, $option_key, $fallback_path = '/')
    {
        $referer = wp_get_referer();
        if ($referer) {
            return $referer;
        }

        return self::get_page_url($shortcode, $option_key, array(), $fallback_path);
    }

    /**
     * Resolve the public jobs listing page URL.
     *
     * @param array $query_args
     * @return string
     */
    public static function get_jobs_page_url($query_args = array())
    {
        return self::get_page_url('mjb_jobs', 'mjb_jobs_page_id', $query_args, '/jobs/');
    }

    /**
     * Base URL for front-end actions that accept query arguments.
     *
     * @return string
     */
    public static function get_front_action_base_url()
    {
        $jobs_page = self::resolve_page_id('mjb_jobs', 'mjb_jobs_page_id');
        if ($jobs_page) {
            return get_permalink($jobs_page);
        }

        return home_url('/');
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

    /**
     * Clear cached page ID for an option key.
     *
     * @param string $option_key
     */
    public static function clear_cached_page_id($option_key)
    {
        delete_option($option_key);
    }

    /**
     * Invalidate cache when a page is saved.
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     */
    public static function maybe_invalidate_on_save($post_id, $post, $update)
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        self::invalidate_if_cached_page($post_id);
    }

    /**
     * Invalidate cache when a page is deleted or trashed.
     *
     * @param int $post_id
     */
    public static function maybe_invalidate_on_delete($post_id)
    {
        if (get_post_type($post_id) !== 'page') {
            return;
        }

        self::invalidate_if_cached_page($post_id);
    }

    /**
     * Delete option entries that point at the given page.
     *
     * @param int $page_id
     */
    public static function invalidate_if_cached_page($page_id)
    {
        $page_id = intval($page_id);

        foreach (self::get_option_map() as $option_key) {
            if (intval(get_option($option_key)) === $page_id) {
                self::clear_cached_page_id($option_key);
            }
        }
    }
}