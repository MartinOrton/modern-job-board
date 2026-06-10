<?php
/**
 * Modern Job Board Job Import Helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Job_Importer
{
    const EXTERNAL_ID_META = '_mjb_import_external_id';

    /**
     * Import a single job listing from normalized data.
     *
     * @param array $data
     * @param array $args
     * @return int Post ID on success, 0 on failure.
     */
    public static function import_job($data, $args = array())
    {
        $args = wp_parse_args($args, array(
            'author_id' => get_current_user_id(),
            'post_status' => 'publish',
            'skip_duplicates' => true,
        ));

        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        if ($title === '') {
            return 0;
        }

        $external_id = isset($data['external_id']) ? sanitize_text_field($data['external_id']) : '';
        if ($args['skip_duplicates'] && $external_id !== '') {
            $existing = self::find_existing_by_external_id($external_id);
            if ($existing) {
                return 0;
            }
        }

        $content = '';
        if (!empty($data['content'])) {
            $content = wp_kses_post($data['content']);
        } elseif (!empty($data['description'])) {
            $content = wp_kses_post($data['description']);
        }

        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'job_listing',
            'post_status' => $args['post_status'],
            'post_author' => intval($args['author_id']),
        ), true);

        if (!$post_id || is_wp_error($post_id)) {
            return 0;
        }

        if ($external_id !== '') {
            update_post_meta($post_id, self::EXTERNAL_ID_META, $external_id);
        }

        if (!empty($data['source_url'])) {
            update_post_meta($post_id, '_mjb_import_source_url', esc_url_raw($data['source_url']));
        }

        self::assign_taxonomy_terms($post_id, 'job_location', $data['location'] ?? '');
        self::assign_taxonomy_terms($post_id, 'job_type', $data['type'] ?? '');
        self::assign_taxonomy_terms($post_id, 'job_category', $data['category'] ?? '');

        $company_name = isset($data['company']) ? sanitize_text_field($data['company']) : '';
        if ($company_name !== '') {
            $company_id = self::find_or_create_company($company_name);
            if ($company_id) {
                update_post_meta($post_id, '_company_id', $company_id);
                update_post_meta($post_id, '_company_name', $company_name);
            }
        }

        if (!empty($data['featured'])) {
            update_post_meta($post_id, '_featured', '1');
        }

        return intval($post_id);
    }

    /**
     * Find an existing imported job by external identifier.
     *
     * @param string $external_id
     * @return int
     */
    public static function find_existing_by_external_id($external_id)
    {
        $posts = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::EXTERNAL_ID_META,
            'meta_value' => sanitize_text_field($external_id),
        ));

        return !empty($posts) ? intval($posts[0]) : 0;
    }

    /**
     * Find or create a company post.
     *
     * @param string $company_name
     * @return int
     */
    public static function find_or_create_company($company_name)
    {
        $company_name = sanitize_text_field($company_name);
        if ($company_name === '') {
            return 0;
        }

        $company_post = get_page_by_title($company_name, OBJECT, 'company');
        if ($company_post) {
            return intval($company_post->ID);
        }

        $company_id = wp_insert_post(array(
            'post_title' => $company_name,
            'post_type' => 'company',
            'post_status' => 'publish',
        ), true);

        return (!$company_id || is_wp_error($company_id)) ? 0 : intval($company_id);
    }

    /**
     * Assign taxonomy terms from a string (comma-separated supported).
     *
     * @param int    $post_id
     * @param string $taxonomy
     * @param string $value
     */
    public static function assign_taxonomy_terms($post_id, $taxonomy, $value)
    {
        $value = sanitize_text_field($value);
        if ($value === '') {
            return;
        }

        if (strpos($value, ',') !== false) {
            $terms = array_map('trim', explode(',', $value));
            wp_set_object_terms($post_id, $terms, $taxonomy);
            return;
        }

        wp_set_object_terms($post_id, $value, $taxonomy);
    }
}