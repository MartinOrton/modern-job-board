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
            'show_in_menu' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-businessman',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
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
            'show_in_menu' => true,
            'menu_position' => 6,
            'menu_icon' => 'dashicons-building',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
        );
        register_post_type('company', $args_company);
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
        );
        register_taxonomy('job_location', array('job_listing'), $args_loc);
    }
}
