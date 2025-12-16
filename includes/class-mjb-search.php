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
     * Can be used by main query or shortcodes.
     *
     * @param WP_Query $query
     */
    public function apply_search_criteria($query)
    {
        // Keyword Search
        if (!empty($_GET['search_keywords'])) {
            $keywords = sanitize_text_field($_GET['search_keywords']);
            $query->set('s', $keywords);
        }

        $tax_query = array();

        // Location
        if (!empty($_GET['search_location'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_location',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_location']),
            );
        }

        // Category
        if (!empty($_GET['search_category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_category']),
            );
        }

        // Type
        if (!empty($_GET['search_type'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_type',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_type']),
            );
        }

        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $query->set('tax_query', $tax_query);
        }
    }
}
