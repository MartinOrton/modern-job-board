<?php
/**
 * Modern Job Board REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_REST_API
{
    /**
     * Initialize REST API.
     */
    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_dispatch', array($this, 'redirect_legacy_query_urls'), 10, 3);
    }

    /**
     * Redirect legacy query-string API requests to path-based URLs.
     *
     * @param mixed           $result
     * @param WP_REST_Server  $server
     * @param WP_REST_Request $request
     * @return mixed
     */
    public function redirect_legacy_query_urls($result, $server, $request)
    {
        if ($request->get_route() !== '/mjb/v1/jobs') {
            return $result;
        }

        if (!MJB_Job_Routes::has_legacy_query_filters()) {
            return $result;
        }

        $params = MJB_Job_Routes::get_legacy_query_params();
        $per_page = isset($params['per_page']) ? intval($params['per_page']) : 10;
        unset($params['per_page']);

        $target = MJB_Job_Routes::build_url($params, array(
            'rest' => true,
            'per_page' => $per_page,
        ));

        wp_safe_redirect($target, 301);
        exit;
    }

    /**
     * Register Routes.
     */
    public function register_routes()
    {
        register_rest_route('mjb/v1', '/jobs/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_jobs'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('mjb/v1', '/jobs/search/(?P<search_path>[a-zA-Z0-9\\-\\/]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_jobs'),
            'permission_callback' => '__return_true',
            'args' => array(
                'search_path' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route('mjb/v1', '/jobs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_jobs'),
            'permission_callback' => '__return_true',
            'args' => array(
                'per_page' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ),
                'search_keywords' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'search_location' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'search_category' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'search_type' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Build query args from a REST request.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public static function build_query_args_from_request($request)
    {
        $search_path = $request->get_param('search_path');
        if ($search_path) {
            $parsed = MJB_Job_Routes::parse_path($search_path);
            $per_page = !empty($parsed['per_page']) ? intval($parsed['per_page']) : 10;
            unset($parsed['per_page']);
            $per_page = min(max($per_page, 1), 100);

            return MJB_Search::build_query_args($parsed, array(
                'posts_per_page' => $per_page,
            ));
        }

        $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;
        $per_page = min(max($per_page, 1), 100);

        $params = MJB_Search::sanitize_filter_params(array(
            'search_keywords' => $request->get_param('search_keywords'),
            'search_location' => $request->get_param('search_location'),
            'search_category' => $request->get_param('search_category'),
            'search_type' => $request->get_param('search_type'),
            'page' => $request->get_param('page'),
        ));

        return MJB_Search::build_query_args($params, array(
            'posts_per_page' => $per_page,
        ));
    }

    /**
     * Canonical pretty URL for a REST request.
     *
     * @param WP_REST_Request $request
     * @return string
     */
    public static function get_canonical_search_url($request)
    {
        $search_path = $request->get_param('search_path');
        if ($search_path) {
            return MJB_Job_Routes::build_url(MJB_Job_Routes::parse_path($search_path), array(
                'rest' => true,
                'per_page' => $request->get_param('per_page') ?: 10,
            ));
        }

        if (MJB_Job_Routes::has_legacy_query_filters()) {
            $params = MJB_Job_Routes::get_legacy_query_params();
            $per_page = isset($params['per_page']) ? intval($params['per_page']) : ($request->get_param('per_page') ?: 10);
            unset($params['per_page']);

            return MJB_Job_Routes::build_url($params, array(
                'rest' => true,
                'per_page' => $per_page,
            ));
        }

        return MJB_Job_Routes::build_url(array(
            'page' => $request->get_param('page'),
        ), array(
            'rest' => true,
            'per_page' => $request->get_param('per_page') ?: 10,
        ));
    }

    /**
     * Format a job post for API output.
     *
     * @param int $post_id
     * @return array
     */
    public static function format_job_for_api($post_id)
    {
        $post_id = intval($post_id);
        $company_name = get_post_meta($post_id, '_company_name', true);
        $locations = wp_get_post_terms($post_id, 'job_location', array('fields' => 'names'));
        $types = wp_get_post_terms($post_id, 'job_type', array('fields' => 'names'));
        $categories = wp_get_post_terms($post_id, 'job_category', array('fields' => 'names'));

        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'link' => get_permalink($post_id),
            'date' => get_the_date('Y-m-d H:i:s', $post_id),
            'featured' => (bool) get_post_meta($post_id, '_featured', true),
            'company' => $company_name,
            'location' => !empty($locations) ? $locations[0] : '',
            'type' => !empty($types) ? $types[0] : '',
            'category' => !empty($categories) ? $categories[0] : '',
            'excerpt' => get_the_excerpt($post_id),
        );
    }

    /**
     * Get Jobs for API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_jobs($request)
    {
        $args = self::build_query_args_from_request($request);
        $query = new WP_Query($args);
        $jobs = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = self::format_job_for_api(get_the_ID());
            }
        }
        wp_reset_postdata();

        $response = new WP_REST_Response($jobs, 200);
        $response->header('X-WP-Total', (int) $query->found_posts);
        $response->header('X-WP-TotalPages', (int) $query->max_num_pages);
        $response->header('Link', '<' . esc_url_raw(self::get_canonical_search_url($request)) . '>; rel="canonical"');

        return $response;
    }
}