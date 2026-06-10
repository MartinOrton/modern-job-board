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
        add_action('template_redirect', array($this, 'redirect_to_clean_url'));
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
        $page = !empty($raw['page']) ? intval($raw['page']) : 0;
        if ($page < 1 && !empty($raw['paged'])) {
            $page = intval($raw['paged']);
        }
        if ($page < 1) {
            $page = 0;
        }

        return array(
            'search_keywords' => !empty($raw['search_keywords']) ? sanitize_text_field($raw['search_keywords']) : '',
            'search_location' => !empty($raw['search_location']) ? sanitize_text_field($raw['search_location']) : '',
            'search_category' => !empty($raw['search_category']) ? sanitize_text_field($raw['search_category']) : '',
            'search_type' => !empty($raw['search_type']) ? sanitize_text_field($raw['search_type']) : '',
            'page' => $page,
        );
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

        if (!empty($params['page'])) {
            $args['paged'] = intval($params['page']);
        }

        $args = self::apply_featured_ordering($args);

        return $args;
    }

    /**
     * Sort featured listings ahead of standard listings.
     *
     * @param array $args
     * @return array
     */
    public static function apply_featured_ordering($args)
    {
        $args['meta_key'] = '_featured';
        $args['orderby'] = array(
            'meta_value_num' => 'DESC',
            'date' => 'DESC',
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
            $html .= '<option value="' . esc_attr($location->slug) . '" ' . selected($selected_slug, $location->slug, false) . '>';
            $html .= esc_html($location->name);
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

        foreach (array('s', 'tax_query', 'paged', 'meta_key', 'orderby') as $key) {
            if (isset($args[$key])) {
                $query->set($key, $args[$key]);
            }
        }
    }

    /**
     * Redirect to SEO friendly URLs when a single taxonomy filter is active.
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
        $location = $params['search_location'];
        $category = $params['search_category'];
        $type = $params['search_type'];

        $active_filters = 0;
        if ($location) {
            $active_filters++;
        }
        if ($category) {
            $active_filters++;
        }
        if ($type) {
            $active_filters++;
        }

        if ($active_filters !== 1) {
            return;
        }

        $redirect_url = '';
        if ($location) {
            $term = get_term_by('slug', $location, 'job_location');
            if ($term && !is_wp_error($term)) {
                $redirect_url = get_term_link($term);
            }
        } elseif ($category) {
            $term = get_term_by('slug', $category, 'job_category');
            if ($term && !is_wp_error($term)) {
                $redirect_url = get_term_link($term);
            }
        } elseif ($type) {
            $term = get_term_by('slug', $type, 'job_type');
            if ($term && !is_wp_error($term)) {
                $redirect_url = get_term_link($term);
            }
        }

        if ($redirect_url && !is_wp_error($redirect_url)) {
            wp_safe_redirect($redirect_url, 301);
            exit;
        }
    }
}