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

    /**
     * Redirect to SEO friendly URLs.
     */
    public function redirect_to_clean_url()
    {
        if (!is_post_type_archive('job_listing') && !is_home()) {
            // Only redirect from job archive (or home if used there)
            return;
        }

        // Check if Keywords are empty
        if (!empty($_GET['search_keywords'])) {
            return;
        }

        $location = !empty($_GET['search_location']) ? sanitize_text_field($_GET['search_location']) : '';
        $category = !empty($_GET['search_category']) ? sanitize_text_field($_GET['search_category']) : '';
        $type = !empty($_GET['search_type']) ? sanitize_text_field($_GET['search_type']) : '';

        // Count how many filters are active
        $active_filters = 0;
        if ($location)
            $active_filters++;
        if ($category)
            $active_filters++;
        if ($type)
            $active_filters++;

        // Only redirect if EXACTLY ONE filter is active
        if ($active_filters === 1) {
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
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
    }
}
