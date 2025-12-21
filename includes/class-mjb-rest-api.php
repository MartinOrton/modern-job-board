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
    }

    /**
     * Register Routes.
     */
    public function register_routes()
    {
        register_rest_route('mjb/v1', '/jobs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_jobs'),
            'permission_callback' => '__return_true', // Public
        ));
    }

    /**
     * Get Jobs for API.
     */
    public function get_jobs($request)
    {
        $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;

        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
        );

        $query = new WP_Query($args);
        $jobs = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $company_name = get_post_meta($post_id, '_company_name', true);
                $locations = wp_get_post_terms($post_id, 'job_location', array('fields' => 'names'));
                $types = wp_get_post_terms($post_id, 'job_type', array('fields' => 'names'));

                $jobs[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'link' => get_permalink(),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'company' => $company_name,
                    'location' => !empty($locations) ? $locations[0] : '',
                    'type' => !empty($types) ? $types[0] : '',
                    'excerpt' => get_the_excerpt(),
                );
            }
        }
        wp_reset_postdata();

        return new WP_REST_Response($jobs, 200);
    }
}
