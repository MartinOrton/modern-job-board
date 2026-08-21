<?php
/**
 * Modern Job Board CPT Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_CPT
{

    /**
     * Initialize CPTs and Taxonomies.
     */
    public function init()
    {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('init', array($this, 'register_post_statuses'));
        add_filter('insert_term_data', array($this, 'normalize_term_slug'), 10, 3);
        add_filter('wp_update_term_data', array($this, 'normalize_term_slug'), 10, 3);
        add_filter('term_link', array($this, 'filter_term_link'), 10, 3);
        add_action('pre_get_posts', array($this, 'filter_company_archive_query'));

        // Frontend hooks
        add_filter('the_content', array($this, 'append_job_map'));
        add_action('wp_head', array($this, 'output_job_schema'));
    }

    /**
     * Hide companies with zero published jobs from the public Companies archive.
     *
     * @param WP_Query $query
     */
    public function filter_company_archive_query($query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$query->is_post_type_archive('company')) {
            return;
        }

        $company_ids = self::get_company_ids_with_published_jobs();
        if (empty($company_ids)) {
            // Force an empty result set (empty post__in returns all posts in WP_Query).
            $query->set('post__in', array(0));
            return;
        }

        $query->set('post__in', $company_ids);
        // Keep natural archive ordering rather than post__in order.
        if (!$query->get('orderby')) {
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
        }
    }

    /**
     * Company post IDs that have at least one published job listing.
     *
     * @return int[]
     */
    public static function get_company_ids_with_published_jobs()
    {
        global $wpdb;

        $ids = $wpdb->get_col(
            "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} jobs ON jobs.ID = pm.post_id
            INNER JOIN {$wpdb->posts} companies ON companies.ID = CAST(pm.meta_value AS UNSIGNED)
            WHERE pm.meta_key = '_company_id'
              AND pm.meta_value <> ''
              AND CAST(pm.meta_value AS UNSIGNED) > 0
              AND jobs.post_type = 'job_listing'
              AND jobs.post_status = 'publish'
              AND companies.post_type = 'company'
              AND companies.post_status = 'publish'"
        );

        if (empty($ids)) {
            return array();
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Register Custom Post Types.
     */
    public function register_post_types()
    {
        // Job Listing CPT
        $labels = array(
            'name' => _x('Jobs', 'Post Type General Name', 'modern-job-board'),
            'singular_name' => _x('Job', 'Post Type Singular Name', 'modern-job-board'),
            'menu_name' => __('Jobs', 'modern-job-board'),
            'name_admin_bar' => __('Job', 'modern-job-board'),
            'archives' => __('Job Archives', 'modern-job-board'),
            'attributes' => __('Job Attributes', 'modern-job-board'),
            'parent_item_colon' => __('Parent Job:', 'modern-job-board'),
            'all_items' => __('All Jobs', 'modern-job-board'),
            'add_new_item' => __('Add New Job', 'modern-job-board'),
            'add_new' => __('Add New', 'modern-job-board'),
            'new_item' => __('New Job', 'modern-job-board'),
            'edit_item' => __('Edit Job', 'modern-job-board'),
            'update_item' => __('Update Job', 'modern-job-board'),
            'view_item' => __('View Job', 'modern-job-board'),
            'view_items' => __('View Jobs', 'modern-job-board'),
            'search_items' => __('Search Job', 'modern-job-board'),
            'not_found' => __('Not found', 'modern-job-board'),
            'not_found_in_trash' => __('Not found in Trash', 'modern-job-board'),
            'featured_image' => __('Featured Image', 'modern-job-board'),
            'set_featured_image' => __('Set featured image', 'modern-job-board'),
            'remove_featured_image' => __('Remove featured image', 'modern-job-board'),
            'use_featured_image' => __('Use as featured image', 'modern-job-board'),
            'insert_into_item' => __('Insert into job', 'modern-job-board'),
            'uploaded_to_this_item' => __('Uploaded to this job', 'modern-job-board'),
            'items_list' => __('Jobs list', 'modern-job-board'),
            'items_list_navigation' => __('Jobs list navigation', 'modern-job-board'),
            'filter_items_list' => __('Filter jobs list', 'modern-job-board'),
        );
        $args = array(
            'label' => __('Job', 'modern-job-board'),
            'description' => __('Job Listings', 'modern-job-board'),
            'labels' => $labels,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'taxonomies' => array('job_type', 'job_category', 'job_location'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'modern-job-board',
            'menu_position' => 5,
            'menu_icon' => 'dashicons-businessman',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            'rewrite' => false,
        );
        register_post_type('job_listing', $args);

        // Company CPT
        $labels_company = array(
            'name' => _x('Companies', 'Post Type General Name', 'modern-job-board'),
            'singular_name' => _x('Company', 'Post Type Singular Name', 'modern-job-board'),
            'menu_name' => __('Companies', 'modern-job-board'),
            'all_items' => __('All Companies', 'modern-job-board'),
            'add_new_item' => __('Add New Company', 'modern-job-board'),
            'edit_item' => __('Edit Company', 'modern-job-board'),
        );
        $args_company = array(
            'label' => __('Company', 'modern-job-board'),
            'description' => __('Company Profiles', 'modern-job-board'),
            'labels' => $labels_company,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'modern-job-board',
            'menu_position' => 6,
            'menu_icon' => 'dashicons-building',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            // Nested under the demo jobs base so board URLs stay under /jobs/.
            'rewrite' => array(
                'slug' => 'jobs/companies',
                'with_front' => false,
            ),
        );
        register_post_type('company', $args_company);

        // Resume CPT
        $labels_resume = array(
            'name' => _x('Resumes', 'Post Type General Name', 'modern-job-board'),
            'singular_name' => _x('Resume', 'Post Type Singular Name', 'modern-job-board'),
            'menu_name' => __('Resumes', 'modern-job-board'),
            'all_items' => __('All Resumes', 'modern-job-board'),
            'add_new' => __('Add New', 'modern-job-board'),
            'add_new_item' => __('Add New Resume', 'modern-job-board'),
            'edit_item' => __('Edit Resume', 'modern-job-board'),
            'new_item' => __('New Resume', 'modern-job-board'),
            'view_item' => __('View Resume', 'modern-job-board'),
            'search_items' => __('Search Resumes', 'modern-job-board'),
            'not_found' => __('No resumes found', 'modern-job-board'),
            'not_found_in_trash' => __('No resumes found in Trash', 'modern-job-board'),
        );
        $args_resume = array(
            'label' => __('Resume', 'modern-job-board'),
            'description' => __('Candidate Resumes', 'modern-job-board'),
            'labels' => $labels_resume,
            'supports' => array('title', 'custom-fields'), // No editor needed really
            'hierarchical' => false,
            'public' => false, // Not publicly queryable via frontend URL
            'show_ui' => true,
            'show_in_menu' => 'modern-job-board',
            'menu_position' => 7,
            'menu_icon' => 'dashicons-media-document',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            // Standard post caps so Add New works for admins/editors (create was previously blocked).
            'capability_type' => 'post',
            'map_meta_cap' => true,
        );
        register_post_type('mjb_resume', $args_resume);
    }

    /**
     * Register Taxonomies.
     */
    public function register_taxonomies()
    {
        // Job Type
        $labels_type = array(
            'name' => _x('Job Types', 'Taxonomy General Name', 'modern-job-board'),
            'singular_name' => _x('Job Type', 'Taxonomy Singular Name', 'modern-job-board'),
            'menu_name' => __('Job Type', 'modern-job-board'),
        );
        $args_type = array(
            'labels' => $labels_type,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'rewrite' => array(
                'slug' => 'job-type',
                'with_front' => false,
            ),
        );
        register_taxonomy('job_type', array('job_listing'), $args_type);

        // Job Category
        $labels_cat = array(
            'name' => _x('Job Categories', 'Taxonomy General Name', 'modern-job-board'),
            'singular_name' => _x('Job Category', 'Taxonomy Singular Name', 'modern-job-board'),
            'menu_name' => __('Job Category', 'modern-job-board'),
        );
        $args_cat = array(
            'labels' => $labels_cat,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'rewrite' => array(
                'slug' => 'job-category',
                'with_front' => false,
            ),
        );
        register_taxonomy('job_category', array('job_listing'), $args_cat);

        // Job Location
        $labels_loc = array(
            'name' => _x('Locations', 'Taxonomy General Name', 'modern-job-board'),
            'singular_name' => _x('Location', 'Taxonomy Singular Name', 'modern-job-board'),
            'menu_name' => __('Location', 'modern-job-board'),
        );
        $args_loc = array(
            'labels' => $labels_loc,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'rewrite' => array(
                'slug' => 'job-location',
                'with_front' => false,
            ),
        );
        register_taxonomy('job_location', array('job_listing'), $args_loc);
    }

    /**
     * Normalize taxonomy term slugs to hyphenated form.
     *
     * @param array  $data
     * @param string $taxonomy
     * @param array  $args
     * @return array
     */
    public function normalize_term_slug($data, $taxonomy, $args)
    {
        if (!in_array($taxonomy, array('job_type', 'job_category', 'job_location'), true)) {
            return $data;
        }

        if (!empty($data['slug'])) {
            $data['slug'] = MJB_Search::normalize_slug($data['slug']);
        }

        return $data;
    }

    /**
     * Point taxonomy term links at pretty /jobs/ search URLs.
     *
     * @param string  $link
     * @param WP_Term $term
     * @param string  $taxonomy
     * @return string
     */
    public function filter_term_link($link, $term, $taxonomy)
    {
        if (is_wp_error($term) || !isset($term->slug)) {
            return $link;
        }

        $params = array();
        if ($taxonomy === 'job_location') {
            $params['search_location'] = MJB_Search::normalize_slug($term->slug);
        } elseif ($taxonomy === 'job_category') {
            $params['search_category'] = MJB_Search::normalize_slug($term->slug);
        } elseif ($taxonomy === 'job_type') {
            $params['search_type'] = MJB_Search::normalize_slug($term->slug);
        } else {
            return $link;
        }

        return MJB_Job_Routes::build_url($params);
    }

    /**
     * Register Custom Post Statuses.
     */
    public function register_post_statuses()
    {
        register_post_status('expired', array(
            'label' => _x('Expired', 'post status', 'modern-job-board'),
            'public' => true,
            'protected' => true,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'modern-job-board'),
        ));

        register_post_status('pending_payment', array(
            'label' => _x('Pending Payment', 'post status', 'modern-job-board'),
            'public' => false,
            'protected' => true,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Pending Payment <span class="count">(%s)</span>', 'Pending Payment <span class="count">(%s)</span>', 'modern-job-board'),
        ));
    }

    /**
     * Append Google Map to Job Content.
     */
    public function append_job_map($content)
    {
        // Skip while related-job cards render (their excerpt path can re-enter the_content).
        if (class_exists('MJB_Job_Ops') && method_exists('MJB_Job_Ops', 'is_rendering_related') && MJB_Job_Ops::is_rendering_related()) {
            return $content;
        }
        if (!is_singular('job_listing') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $api_key = get_option('mjb_google_maps_api_key');
        if (empty($api_key)) {
            return $content;
        }

        $terms = get_the_terms(get_the_ID(), 'job_location');
        if (empty($terms) || is_wp_error($terms)) {
            return $content;
        }

        $location = MJB_Location::format_location_term($terms[0]);
        $map_url = 'https://www.google.com/maps/embed/v1/place?key=' . esc_attr($api_key) . '&q=' . urlencode($location);

        $map_html = '<div class="mjb-map-container">';
        $map_html .= '<h3>' . __('Job Location', 'modern-job-board') . '</h3>';
        $map_html .= '<iframe class="mjb-map-iframe" frameborder="0" src="' . esc_url($map_url) . '" allowfullscreen></iframe>';
        $map_html .= '</div>';

        return $content . $map_html;
    }

    /**
     * Output Job Schema (JSON-LD).
     */
    public function output_job_schema()
    {
        if (!is_singular('job_listing')) {
            return;
        }

        global $post;

        $job_title = get_the_title();
        $job_description = wp_strip_all_tags($post->post_content);
        $date_posted = get_the_date('c');
        $expires = get_post_meta($post->ID, '_job_expires', true);

        // Company
        $company_name = get_post_meta($post->ID, '_company_name', true);
        if (!$company_name) {
            $company_name = get_bloginfo('name');
        }

        // Location
        $address = MJB_Location::get_job_schema_address($post->ID);
        $location_name = $address['formatted'];

        // Job Type
        $employment_type = '';
        $type_terms = get_the_terms($post->ID, 'job_type');
        if ($type_terms && !is_wp_error($type_terms)) {
            $employment_type = MJB_Search::map_employment_type_for_schema($type_terms[0]->name);
        }

        $postal_address = array(
            '@type' => 'PostalAddress',
            'addressLocality' => $address['locality'] !== '' ? $address['locality'] : $location_name,
        );

        if ($address['region'] !== '') {
            $postal_address['addressRegion'] = $address['region'];
        }

        if ($address['country'] !== '') {
            $postal_address['addressCountry'] = $address['country'];
        }

        $schema = array(
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'title' => $job_title,
            'description' => $job_description,
            'datePosted' => $date_posted,
            'identifier' => array(
                '@type' => 'PropertyValue',
                'name' => get_bloginfo('name'),
                'value' => (string) $post->ID,
            ),
            'hiringOrganization' => array(
                '@type' => 'Organization',
                'name' => $company_name,
                'sameAs' => get_bloginfo('url'),
            ),
            'jobLocation' => array(
                '@type' => 'Place',
                'address' => $postal_address,
            ),
            'directApply' => true,
            'url' => get_permalink($post->ID),
        );

        if ($expires) {
            $schema['validThrough'] = date('c', strtotime($expires));
        }

        if ($employment_type) {
            $schema['employmentType'] = $employment_type;
        }

        /**
         * Filter JobPosting JSON-LD before output (Google Jobs mapper applies here).
         *
         * @param array $schema
         * @param int   $post_id
         */
        $schema = apply_filters('mjb_job_schema', $schema, (int) $post->ID);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD from structured array.
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}
