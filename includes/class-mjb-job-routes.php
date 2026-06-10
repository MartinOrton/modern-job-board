<?php
/**
 * Modern Job Board Pretty Search Routes
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Job_Routes
{
    const QUERY_VAR = 'mjb_search_path';
    const LEGACY_PARAMS = array(
        'search_keywords',
        'search_location',
        'search_category',
        'search_type',
        'page',
        'per_page',
    );

    /**
     * Initialize route hooks.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_rewrites'));
        add_action('init', array(__CLASS__, 'maybe_flush_rewrites'), 99);
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'redirect_legacy_query_urls'), 1);
    }

    /**
     * Flush rewrite rules after plugin upgrades.
     */
    public static function maybe_flush_rewrites()
    {
        if (get_option('mjb_routes_version') !== MJB_VERSION) {
            flush_rewrite_rules(false);
            update_option('mjb_routes_version', MJB_VERSION);
        }
    }

    /**
     * Base slug for public job search URLs.
     *
     * @return string
     */
    public static function get_base_slug()
    {
        return apply_filters('mjb_jobs_route_base', 'jobs');
    }

    /**
     * Register rewrite rules for path-based job searches.
     */
    public static function register_rewrites()
    {
        $slug = self::get_base_slug();

        add_rewrite_rule(
            '^' . preg_quote($slug, '/') . '/(.+?)/?$',
            'index.php?post_type=job_listing&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^' . preg_quote($slug, '/') . '/?$',
            'index.php?post_type=job_listing',
            'top'
        );
    }

    /**
     * Register the search path query var.
     *
     * @param array $vars
     * @return array
     */
    public static function register_query_vars($vars)
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Build a path segment string from filter params.
     *
     * @param array $params
     * @param array $options
     * @return string
     */
    public static function build_path($params, $options = array())
    {
        $params = MJB_Search::sanitize_filter_params($params);
        $segments = array();

        if (!empty($params['search_location'])) {
            $segments[] = 'in';
            $segments[] = $params['search_location'];
        }

        if (!empty($params['search_category'])) {
            $segments[] = 'category';
            $segments[] = $params['search_category'];
        }

        if (!empty($params['search_type'])) {
            $segments[] = 'type';
            $segments[] = $params['search_type'];
        }

        if (!empty($params['search_keywords'])) {
            $segments[] = 'keyword';
            $segments[] = sanitize_title($params['search_keywords']);
        }

        if (!empty($params['page']) && intval($params['page']) > 1) {
            $segments[] = 'page';
            $segments[] = (string) intval($params['page']);
        }

        if (!empty($options['rest']) && !empty($options['per_page'])) {
            $per_page = min(max(intval($options['per_page']), 1), 100);
            if ($per_page !== 10) {
                $segments[] = 'per-page';
                $segments[] = (string) $per_page;
            }
        }

        return implode('/', $segments);
    }

    /**
     * Parse a path segment string into filter params.
     *
     * @param string $path
     * @return array
     */
    public static function parse_path($path)
    {
        $path = trim((string) $path, '/');
        if ($path === '') {
            return MJB_Search::sanitize_filter_params(array());
        }

        $parts = explode('/', $path);
        $raw = array();
        $count = count($parts);

        for ($i = 0; $i < $count; $i++) {
            $marker = $parts[$i];
            if (!isset($parts[$i + 1])) {
                break;
            }

            $value = $parts[$i + 1];
            $i++;

            switch ($marker) {
                case 'in':
                    $raw['search_location'] = $value;
                    break;
                case 'category':
                    $raw['search_category'] = $value;
                    break;
                case 'type':
                    $raw['search_type'] = $value;
                    break;
                case 'keyword':
                    $raw['search_keywords'] = self::restore_keyword_from_slug($value);
                    break;
                case 'page':
                    $raw['page'] = $value;
                    break;
                case 'per-page':
                    $raw['per_page'] = $value;
                    break;
            }
        }

        $per_page = 0;
        if (isset($raw['per_page'])) {
            $per_page = intval($raw['per_page']);
            unset($raw['per_page']);
        }

        $params = MJB_Search::sanitize_filter_params($raw);

        if ($per_page > 0) {
            $params['per_page'] = $per_page;
        }

        return $params;
    }

    /**
     * Restore a human-readable keyword from its URL slug.
     *
     * @param string $slug
     * @return string
     */
    public static function restore_keyword_from_slug($slug)
    {
        $slug = sanitize_title($slug);
        return str_replace('-', ' ', $slug);
    }

    /**
     * Build a public SEO-friendly search URL.
     *
     * @param array $params
     * @param array $options
     * @return string
     */
    public static function build_url($params = array(), $options = array())
    {
        if (!empty($options['rest'])) {
            $base = trailingslashit(rest_url('mjb/v1/jobs/search'));
            $path = self::build_path($params, $options);
            return $path ? $base . trailingslashit($path) : $base;
        }

        $base = trailingslashit(home_url('/' . self::get_base_slug()));
        $path = self::build_path($params, $options);

        return $path ? $base . trailingslashit($path) : $base;
    }

    /**
     * Whether the current request uses legacy query-string filters.
     *
     * @return bool
     */
    public static function has_legacy_query_filters()
    {
        foreach (self::LEGACY_PARAMS as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect legacy query-string filters from the current request.
     *
     * @return array
     */
    public static function get_legacy_query_params()
    {
        $raw = array();

        foreach (self::LEGACY_PARAMS as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $raw[$param] = wp_unslash($_GET[$param]);
            }
        }

        return $raw;
    }

    /**
     * Redirect legacy query-string searches to pretty paths.
     */
    public static function redirect_legacy_query_urls()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!self::has_legacy_query_filters()) {
            return;
        }

        $target = self::build_url(self::get_legacy_query_params());
        $current = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        if (trailingslashit($target) === trailingslashit(strtok($current, '?'))) {
            return;
        }

        wp_safe_redirect($target, 301);
        exit;
    }
}