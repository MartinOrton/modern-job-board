<?php
/**
 * Pretty permalinks for single job listings.
 *
 * Structure: /job/{country}/{state}/{city}/{job-slug}/
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Job_Permalinks
{
    const COUNTRY_META = '_mjb_job_country';
    const STATE_META = '_mjb_job_state';
    const CITY_META = '_mjb_job_city';
    const LEGACY_QUERY_VAR = 'mjb_legacy_job';

    /**
     * Default geo segments keyed by job_location term slug.
     *
     * @var array<string, array<string, string>>
     */
    private static $location_geo_map = array(
        'remote' => array(
            'country' => 'global',
            'state' => 'remote',
            'city' => 'worldwide',
        ),
        'london' => array(
            'country' => 'uk',
            'state' => 'england',
            'city' => 'london',
        ),
        'san-francisco' => array(
            'country' => 'us',
            'state' => 'california',
            'city' => 'san-francisco',
        ),
        'new-york' => array(
            'country' => 'us',
            'state' => 'ny',
            'city' => 'new-york',
        ),
        'manchester' => array(
            'country' => 'uk',
            'state' => 'england',
            'city' => 'manchester',
        ),
        'austin' => array(
            'country' => 'us',
            'state' => 'texas',
            'city' => 'austin',
        ),
        'berlin' => array(
            'country' => 'de',
            'state' => 'germany',
            'city' => 'berlin',
        ),
        'toronto' => array(
            'country' => 'ca',
            'state' => 'ontario',
            'city' => 'toronto',
        ),
        'sydney' => array(
            'country' => 'au',
            'state' => 'nsw',
            'city' => 'sydney',
        ),
        'portland' => array(
            'country' => 'us',
            'state' => 'oregon',
            'city' => 'portland',
        ),
        'dublin' => array(
            'country' => 'ie',
            'state' => 'leinster',
            'city' => 'dublin',
        ),
    );

    /**
     * Initialize permalink hooks.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_rewrites'));
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_filter('post_type_link', array(__CLASS__, 'filter_post_type_link'), 10, 2);
        add_action('template_redirect', array(__CLASS__, 'redirect_legacy_single_urls'), 2);
        add_action('template_redirect', array(__CLASS__, 'redirect_canonical_single_urls'), 3);
        add_action('save_post_job_listing', array(__CLASS__, 'sync_geo_meta_on_save'), 20, 1);
        add_action('set_object_terms', array(__CLASS__, 'sync_geo_meta_on_terms'), 20, 4);
    }

    /**
     * Base slug for single job URLs.
     *
     * @return string
     */
    public static function get_base_slug()
    {
        return apply_filters('mjb_job_permalink_base', 'job');
    }

    /**
     * Register rewrite rules for pretty single job URLs.
     */
    public static function register_rewrites()
    {
        $slug = preg_quote(self::get_base_slug(), '/');

        add_rewrite_rule(
            '^' . $slug . '/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$',
            'index.php?post_type=job_listing&name=$matches[4]',
            'top'
        );

        add_rewrite_rule(
            '^job-listing/([^/]+)/?$',
            'index.php?post_type=job_listing&name=$matches[1]&' . self::LEGACY_QUERY_VAR . '=1',
            'top'
        );

        add_rewrite_rule(
            '^job_listing/([^/]+)/?$',
            'index.php?post_type=job_listing&name=$matches[1]&' . self::LEGACY_QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * Register custom query vars.
     *
     * @param array $vars
     * @return array
     */
    public static function register_query_vars($vars)
    {
        $vars[] = self::LEGACY_QUERY_VAR;
        return $vars;
    }

    /**
     * Map a location slug to country/state/city segments.
     *
     * @param string $location_slug
     * @return array<string, string>
     */
    public static function map_location_slug_to_geo($location_slug)
    {
        $location_slug = sanitize_title($location_slug);

        if ($location_slug === '') {
            $location_slug = 'remote';
        }

        $map = apply_filters('mjb_job_location_geo_map', self::$location_geo_map);

        if (isset($map[$location_slug])) {
            return $map[$location_slug];
        }

        return array(
            'country' => 'global',
            'state' => 'region',
            'city' => $location_slug,
        );
    }

    /**
     * Resolve geo segments for a job listing from the live location term.
     *
     * Meta is treated as a cache written by sync_geo_meta(); permalinks always
     * re-derive from the current job_location term so admin term changes stick.
     *
     * @param int $post_id
     * @return array<string, string>
     */
    public static function get_job_geo($post_id)
    {
        $terms = wp_get_post_terms($post_id, 'job_location', array('fields' => 'slugs'));
        $slug = (!empty($terms) && !is_wp_error($terms)) ? (string) $terms[0] : 'remote';

        return self::map_location_slug_to_geo($slug);
    }

    /**
     * Persist geo segments on a job listing.
     *
     * @param int $post_id
     * @return array<string, string>
     */
    public static function sync_geo_meta($post_id)
    {
        $geo = self::get_job_geo($post_id);

        update_post_meta($post_id, self::COUNTRY_META, $geo['country']);
        update_post_meta($post_id, self::STATE_META, $geo['state']);
        update_post_meta($post_id, self::CITY_META, $geo['city']);

        return $geo;
    }

    /**
     * Re-sync geo meta when a job listing is saved.
     *
     * @param int $post_id
     * @return void
     */
    public static function sync_geo_meta_on_save($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        self::sync_geo_meta($post_id);
    }

    /**
     * Re-sync geo meta when job_location terms change.
     *
     * @param int    $object_id  Post ID.
     * @param array  $terms      Term IDs/slugs.
     * @param array  $tt_ids     Term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @return void
     */
    public static function sync_geo_meta_on_terms($object_id, $terms, $tt_ids, $taxonomy)
    {
        unset($terms, $tt_ids);

        if ($taxonomy !== 'job_location') {
            return;
        }

        $post = get_post($object_id);
        if (!$post || $post->post_type !== 'job_listing') {
            return;
        }

        self::sync_geo_meta($object_id);
    }

    /**
     * Build a pretty single job URL.
     *
     * @param int|WP_Post $post
     * @return string
     */
    public static function build_job_url($post)
    {
        if (is_object($post) && isset($post->post_type, $post->post_name)) {
            $job_post = $post;
        } else {
            $job_post = get_post($post);
        }

        if (!$job_post || $job_post->post_type !== 'job_listing') {
            return '';
        }

        $geo = self::get_job_geo($job_post->ID);
        $path = implode('/', array(
            MJB_Search::normalize_slug($geo['country']),
            MJB_Search::normalize_slug($geo['state']),
            MJB_Search::normalize_slug($geo['city']),
            MJB_Search::normalize_slug($job_post->post_name),
        ));

        return home_url(trailingslashit('/' . self::get_base_slug() . '/' . $path));
    }

    /**
     * Filter job permalinks.
     *
     * @param string  $permalink
     * @param WP_Post $post
     * @return string
     */
    public static function filter_post_type_link($permalink, $post)
    {
        if ($post->post_type !== 'job_listing') {
            return $permalink;
        }

        $url = self::build_job_url($post);

        return $url !== '' ? $url : $permalink;
    }

    /**
     * Redirect legacy /job-listing/{slug}/ and /job_listing/{slug}/ URLs.
     */
    public static function redirect_legacy_single_urls()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!is_singular('job_listing')) {
            return;
        }

        if (!get_query_var(self::LEGACY_QUERY_VAR)) {
            $request_path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (!is_string($request_path)) {
                return;
            }

            $is_legacy = (strpos($request_path, '/job_listing/') !== false)
                || (strpos($request_path, '/job-listing/') !== false);

            if (!$is_legacy) {
                return;
            }
        }

        $target = get_permalink();
        $current = home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        if (trailingslashit($target) !== trailingslashit($current)) {
            wp_safe_redirect($target, 301);
            exit;
        }
    }

    /**
     * Redirect mismatched geo segments to the canonical job URL.
     */
    public static function redirect_canonical_single_urls()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!is_singular('job_listing') || get_query_var(self::LEGACY_QUERY_VAR)) {
            return;
        }

        $target = get_permalink();
        $current = home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        if (trailingslashit($target) !== trailingslashit($current)) {
            wp_safe_redirect($target, 301);
            exit;
        }
    }
}