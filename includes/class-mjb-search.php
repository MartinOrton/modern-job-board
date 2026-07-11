<?php
/**
 * Modern Job Board Search & Filter
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Search
{

    /**
     * Initialize Search.
     */
    public function init()
    {
        add_action('pre_get_posts', array($this, 'filter_jobs_query'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'redirect_legacy_taxonomy_urls'), 1);
        add_action('template_redirect', array($this, 'redirect_to_clean_url'), 2);
        add_action('wp_ajax_mjb_filter_jobs', array($this, 'ajax_filter_jobs'));
        add_action('wp_ajax_nopriv_mjb_filter_jobs', array($this, 'ajax_filter_jobs'));
    }

    /**
     * Sanitize raw filter parameters.
     *
     * @param array $raw
     * @return array
     */
    public static function sanitize_filter_params($raw)
    {
        $page = 0;
        if (!empty($raw['mjb_page'])) {
            $page = intval($raw['mjb_page']);
        } elseif (!empty($raw['page'])) {
            $page = intval($raw['page']);
        } elseif (!empty($raw['paged'])) {
            $page = intval($raw['paged']);
        }
        if ($page < 1) {
            $page = 0;
        }

        return array(
            'search_keywords' => !empty($raw['search_keywords']) ? sanitize_text_field($raw['search_keywords']) : '',
            'search_location' => !empty($raw['search_location']) ? self::normalize_slug($raw['search_location']) : '',
            'search_category' => !empty($raw['search_category']) ? self::normalize_slug($raw['search_category']) : '',
            'search_type' => !empty($raw['search_type']) ? self::normalize_slug($raw['search_type']) : '',
            'search_company' => !empty($raw['search_company']) ? self::normalize_slug($raw['search_company']) : '',
            'page' => $page,
        );
    }

    /**
     * Normalize a URL slug segment to hyphenated form.
     *
     * @param string $value
     * @return string
     */
    public static function normalize_slug($value)
    {
        return sanitize_title(str_replace('_', '-', sanitize_text_field($value)));
    }

    /**
     * Read filter parameters from the current GET request.
     *
     * @return array
     */
    public static function get_request_filter_params()
    {
        $path = get_query_var(MJB_Job_Routes::QUERY_VAR);
        if (!empty($path)) {
            return MJB_Job_Routes::parse_path($path);
        }

        if (MJB_Job_Routes::has_legacy_query_filters()) {
            return self::sanitize_filter_params(MJB_Job_Routes::get_legacy_query_params());
        }

        return self::sanitize_filter_params(wp_unslash($_GET));
    }

    /**
     * Build a WP_Query args array from filter parameters.
     *
     * @param array $params
     * @param array $base_args
     * @return array
     */
    public static function build_query_args($params = array(), $base_args = array())
    {
        $defaults = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        );

        $args = wp_parse_args($base_args, $defaults);

        if (!empty($params['search_keywords'])) {
            $args['s'] = $params['search_keywords'];
        }

        $tax_map = array(
            'search_location' => 'job_location',
            'search_category' => 'job_category',
            'search_type' => 'job_type',
        );

        $tax_query = array();
        foreach ($tax_map as $param => $taxonomy) {
            if (!empty($params[$param])) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $params[$param],
                );
            }
        }

        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        $args['paged'] = !empty($params['page']) ? max(1, intval($params['page'])) : 1;

        if (!empty($params['search_company'])) {
            $company_id = self::resolve_company_id_from_slug($params['search_company']);
            if ($company_id) {
                $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
                $args['meta_query'][] = array(
                    'key' => '_company_id',
                    'value' => $company_id,
                );
            }
        }

        $args = self::apply_featured_ordering($args);

        return $args;
    }

    /**
     * Resolve a company post ID from a URL slug.
     *
     * @param string $slug
     * @return int
     */
    public static function resolve_company_id_from_slug($slug)
    {
        $slug = self::normalize_slug($slug);
        if ($slug === '') {
            return 0;
        }

        $companies = get_posts(array(
            'post_type' => 'company',
            'post_status' => 'publish',
            'name' => $slug,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));

        return !empty($companies) ? intval($companies[0]) : 0;
    }

    /**
     * Resolve the public URL for a company's job listings.
     *
     * @param int $post_id Job listing ID.
     * @return string
     */
    public static function get_company_jobs_url_for_listing($post_id)
    {
        $company_id = intval(get_post_meta($post_id, '_company_id', true));
        if ($company_id && get_post($company_id)) {
            return MJB_Job_Routes::build_url(array(
                'search_company' => get_post_field('post_name', $company_id),
            ));
        }

        $company_name = get_post_meta($post_id, '_company_name', true);
        if ($company_name === '') {
            return '';
        }

        $company_post = get_page_by_title($company_name, OBJECT, 'company');
        if ($company_post) {
            return MJB_Job_Routes::build_url(array(
                'search_company' => $company_post->post_name,
            ));
        }

        return MJB_Job_Routes::build_url(array(
            'search_company' => sanitize_title($company_name),
        ));
    }

    /**
     * Resolve a company display name from a URL slug.
     *
     * @param string $slug
     * @return string
     */
    public static function get_company_name_from_slug($slug)
    {
        $company_id = self::resolve_company_id_from_slug($slug);
        if ($company_id) {
            return get_the_title($company_id);
        }

        return ucwords(str_replace('-', ' ', self::normalize_slug($slug)));
    }

    /**
     * Build the heading copy for a job listing page.
     *
     * @param array|null $filter_params
     * @return array{title:string,intro:string}
     */
    public static function get_listing_page_heading($filter_params = null)
    {
        if ($filter_params === null) {
            $filter_params = self::get_request_filter_params();
        }

        if (!empty($filter_params['search_company'])) {
            $company_name = self::get_company_name_from_slug($filter_params['search_company']);

            return array(
                'title' => sprintf(
                    /* translators: %s: company name */
                    __('Jobs at %s', 'modern-job-board'),
                    $company_name
                ),
                'intro' => sprintf(
                    /* translators: %s: company name */
                    __('Browse open roles at %s.', 'modern-job-board'),
                    $company_name
                ),
            );
        }

        if (!empty($filter_params['search_location'])) {
            $location_label = MJB_Location::format_location_slug($filter_params['search_location']);

            return array(
                'title' => sprintf(
                    /* translators: %s: location label */
                    __('Jobs in %s', 'modern-job-board'),
                    $location_label
                ),
                'intro' => sprintf(
                    /* translators: %s: location label */
                    __('Browse open roles in %s.', 'modern-job-board'),
                    $location_label
                ),
            );
        }

        return array(
            'title' => __('Jobs', 'modern-job-board'),
            'intro' => __('Browse open roles and filter by location, category, or keywords.', 'modern-job-board'),
        );
    }

    /**
     * Sort featured listings ahead of standard listings.
     *
     * Includes jobs that have no `_featured` meta (treated as not featured)
     * so frontend submissions are not excluded from listings.
     *
     * @param array $args
     * @return array
     */
    public static function apply_featured_ordering($args)
    {
        $meta_query = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : array();

        // OR of EXISTS / NOT EXISTS keeps every job in the result set while still
        // exposing a named clause for orderby (featured first, missing meta last).
        $featured_group = array(
            'relation' => 'OR',
            'mjb_featured_clause' => array(
                'key' => '_featured',
                'type' => 'NUMERIC',
                'compare' => 'EXISTS',
            ),
            'mjb_featured_missing' => array(
                'key' => '_featured',
                'compare' => 'NOT EXISTS',
            ),
        );

        if (!empty($meta_query)) {
            $meta_query = array(
                'relation' => 'AND',
                $meta_query,
                $featured_group,
            );
        } else {
            $meta_query = $featured_group;
        }

        $args['meta_query'] = $meta_query;
        unset($args['meta_key']);

        $args['orderby'] = array(
            'mjb_featured_clause' => 'DESC',
            'date' => 'DESC',
            'ID' => 'DESC',
        );

        return $args;
    }

    /**
     * Map a job type slug/name to a Schema.org employmentType value.
     *
     * @param string $type
     * @return string
     */
    public static function map_employment_type_for_schema($type)
    {
        $normalized = strtolower(trim($type));
        $map = array(
            'full-time' => 'FULL_TIME',
            'full time' => 'FULL_TIME',
            'part-time' => 'PART_TIME',
            'part time' => 'PART_TIME',
            'contract' => 'CONTRACTOR',
            'contractor' => 'CONTRACTOR',
            'temporary' => 'TEMPORARY',
            'temp' => 'TEMPORARY',
            'internship' => 'INTERN',
            'intern' => 'INTERN',
            'volunteer' => 'VOLUNTEER',
            'per diem' => 'PER_DIEM',
        );

        return $map[$normalized] ?? strtoupper(str_replace(array(' ', '-'), '_', $normalized));
    }

    /**
     * Render a location taxonomy dropdown.
     *
     * @param string $selected_slug
     * @param array  $args
     * @return string
     */
    public static function render_location_dropdown($selected_slug = '', $args = array())
    {
        $defaults = array(
            'name' => 'search_location',
            'id' => 'search_location',
            'show_option_all' => __('All Locations', 'modern-job-board'),
        );
        $args = wp_parse_args($args, $defaults);

        $locations = get_terms(array(
            'taxonomy' => 'job_location',
            'hide_empty' => false,
        ));

        if (is_wp_error($locations)) {
            $locations = array();
        }

        $html = '<select name="' . esc_attr($args['name']) . '" id="' . esc_attr($args['id']) . '">';
        $html .= '<option value="">' . esc_html($args['show_option_all']) . '</option>';

        foreach ($locations as $location) {
            $slug = self::normalize_slug($location->slug);
            $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_slug, $slug, false) . '>';
            $html .= esc_html(MJB_Location::format_location_term($location));
            $html .= '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Render a taxonomy dropdown with hyphenated slug values.
     *
     * @param string $taxonomy
     * @param string $name
     * @param string $selected_slug
     * @param string $show_option_all
     * @return string
     */
    public static function render_taxonomy_dropdown($taxonomy, $name, $selected_slug = '', $show_option_all = '')
    {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            $terms = array();
        }

        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        $html .= '<option value="">' . esc_html($show_option_all) . '</option>';

        foreach ($terms as $term) {
            $slug = self::normalize_slug($term->slug);
            $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_slug, $slug, false) . '>';
            $html .= esc_html($term->name);
            $html .= '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * AJAX Filter Jobs.
     */
    public function ajax_filter_jobs()
    {
        check_ajax_referer('mjb_search_nonce', 'security');

        $params = self::sanitize_filter_params(wp_unslash($_POST));
        $per_page = isset($_POST['posts_per_page']) ? max(1, intval($_POST['posts_per_page'])) : 10;
        $args = self::build_query_args($params, array('posts_per_page' => $per_page));
        $query = new WP_Query($args);

        if (class_exists('MJB_Shortcodes')) {
            MJB_Shortcodes::render_job_loop($query);
            MJB_Shortcodes::render_pagination($query, $params);
        } else {
            echo esc_html__('Error: Shortcodes class not found.', 'modern-job-board');
        }

        wp_die();
    }

    /**
     * Register Query Vars.
     */
    public function register_query_vars($vars)
    {
        $vars[] = 'search_keywords';
        $vars[] = 'search_location';
        $vars[] = 'search_category';
        $vars[] = 'search_type';
        $vars[] = 'search_company';
        return $vars;
    }

    /**
     * Filter Main Query.
     */
    public function filter_jobs_query($query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'job_listing' && !is_post_type_archive('job_listing') && !is_tax(array('job_type', 'job_category', 'job_location'))) {
            return;
        }

        $this->apply_search_criteria($query);
    }

    /**
     * Apply Search Criteria to Query.
     *
     * @param WP_Query $query
     * @param array    $params Optional explicit params instead of $_GET.
     */
    public function apply_search_criteria($query, $params = null)
    {
        if ($params === null) {
            $params = self::get_request_filter_params();
        }

        $args = self::build_query_args($params);

        $query->set('s', isset($args['s']) ? $args['s'] : '');
        $query->set('tax_query', isset($args['tax_query']) ? $args['tax_query'] : array());
        $query->set('meta_query', isset($args['meta_query']) ? $args['meta_query'] : array());
        $query->set('meta_key', '');
        $query->set('paged', isset($args['paged']) ? max(1, intval($args['paged'])) : 1);
        $query->set('orderby', isset($args['orderby']) ? $args['orderby'] : array());

        if (isset($args['posts_per_page'])) {
            $query->set('posts_per_page', $args['posts_per_page']);
        }
    }

    /**
     * Redirect legacy taxonomy archive URLs to hyphenated /jobs/ paths.
     */
    public function redirect_legacy_taxonomy_urls()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (is_tax(array('job_type', 'job_category', 'job_location'))) {
            $term = get_queried_object();
            if (!$term || is_wp_error($term) || empty($term->taxonomy)) {
                return;
            }

            $params = array();
            if ($term->taxonomy === 'job_location') {
                $params['search_location'] = self::normalize_slug($term->slug);
            } elseif ($term->taxonomy === 'job_category') {
                $params['search_category'] = self::normalize_slug($term->slug);
            } elseif ($term->taxonomy === 'job_type') {
                $params['search_type'] = self::normalize_slug($term->slug);
            }

            wp_safe_redirect(MJB_Job_Routes::build_url($params), 301);
            exit;
        }

        $request_path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!is_string($request_path)) {
            return;
        }

        if (!preg_match('#/(job[-_]location|job[-_]type|job[-_]category)/([^/]+)/?#', $request_path, $matches)) {
            return;
        }

        $taxonomy_map = array(
            'job_location' => 'search_location',
            'job-location' => 'search_location',
            'job_category' => 'search_category',
            'job-category' => 'search_category',
            'job_type' => 'search_type',
            'job-type' => 'search_type',
        );

        $param_key = $taxonomy_map[$matches[1]] ?? '';
        if ($param_key === '') {
            return;
        }

        wp_safe_redirect(MJB_Job_Routes::build_url(array(
            $param_key => self::normalize_slug($matches[2]),
        )), 301);
        exit;
    }

    /**
     * Redirect archive query-string filters to pretty /jobs/ paths.
     */
    public function redirect_to_clean_url()
    {
        if (!is_post_type_archive('job_listing') && !is_home()) {
            return;
        }

        if (get_query_var(MJB_Job_Routes::QUERY_VAR)) {
            return;
        }

        if (MJB_Job_Routes::has_legacy_query_filters()) {
            return;
        }

        $params = self::get_request_filter_params();
        $active_filters = 0;

        foreach (array('search_location', 'search_category', 'search_type', 'search_company') as $key) {
            if (!empty($params[$key])) {
                $active_filters++;
            }
        }

        if ($active_filters < 1) {
            return;
        }

        $redirect_url = MJB_Job_Routes::build_url($params);
        $current = home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        if (trailingslashit($redirect_url) !== trailingslashit($current)) {
            wp_safe_redirect($redirect_url, 301);
            exit;
        }
    }
}