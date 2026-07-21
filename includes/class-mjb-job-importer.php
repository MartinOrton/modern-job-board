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
     * Stable, case-insensitive company name key used to prevent duplicates.
     */
    const COMPANY_NAME_KEY_META = '_mjb_company_name_key';

    /**
     * Register hooks that keep company identity consistent.
     */
    public static function init()
    {
        add_action('save_post_company', array(__CLASS__, 'sync_company_name_key'), 10, 3);
    }

    /**
     * Normalize a company display name (decode entities, collapse whitespace).
     *
     * @param string $name
     * @return string
     */
    public static function normalize_company_name($name)
    {
        $name = wp_strip_all_tags((string) $name);
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = sanitize_text_field($name);
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim((string) $name);
    }

    /**
     * Build a case-insensitive lookup key for a company name.
     *
     * @param string $name
     * @return string
     */
    public static function company_name_key($name)
    {
        $normalized = self::normalize_company_name($name);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($normalized, 'UTF-8');
        }

        return strtolower($normalized);
    }

    /**
     * Find an existing company by normalized name (meta key first, then title match).
     *
     * @param string $company_name
     * @return int Company post ID or 0.
     */
    public static function find_company_by_name($company_name)
    {
        $key = self::company_name_key($company_name);
        if ($key === '') {
            return 0;
        }

        $by_meta = get_posts(array(
            'post_type' => 'company',
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_key' => self::COMPANY_NAME_KEY_META,
            'meta_value' => $key,
        ));

        if (!empty($by_meta)) {
            return intval($by_meta[0]);
        }

        $candidates = get_posts(array(
            'post_type' => 'company',
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        foreach ($candidates as $candidate) {
            $post = is_object($candidate) ? $candidate : get_post($candidate);
            if (!$post || empty($post->post_title)) {
                continue;
            }

            if (self::company_name_key($post->post_title) === $key) {
                update_post_meta($post->ID, self::COMPANY_NAME_KEY_META, $key);
                return intval($post->ID);
            }
        }

        return 0;
    }

    /**
     * Persist the stable name key (and cleaned title) for a company post.
     *
     * @param int          $post_id
     * @param WP_Post|null $post
     * @param bool         $update
     */
    public static function sync_company_name_key($post_id, $post = null, $update = false)
    {
        unset($update);

        $post_id = intval($post_id);
        if ($post_id < 1 || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!$post) {
            $post = get_post($post_id);
        }

        if (!$post || $post->post_type !== 'company') {
            return;
        }

        if (in_array($post->post_status, array('auto-draft', 'trash'), true)) {
            return;
        }

        $normalized = self::normalize_company_name($post->post_title);
        if ($normalized === '') {
            return;
        }

        $key = self::company_name_key($normalized);
        update_post_meta($post_id, self::COMPANY_NAME_KEY_META, $key);

        // Decode entity-encoded titles (e.g. "Pixel &amp; Ink") so future lookups match.
        if ($post->post_title !== $normalized) {
            self::update_company_title($post_id, $normalized);
        }
    }

    /**
     * Update a company title without re-encoding HTML entities.
     *
     * @param int    $post_id
     * @param string $title
     */
    private static function update_company_title($post_id, $title)
    {
        global $wpdb;

        $post_id = intval($post_id);
        $title = self::normalize_company_name($title);
        if ($post_id < 1 || $title === '') {
            return;
        }

        if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'update') && !empty($wpdb->posts)) {
            $wpdb->update(
                $wpdb->posts,
                array('post_title' => $title),
                array('ID' => $post_id),
                array('%s'),
                array('%d')
            );
        } else {
            remove_action('save_post_company', array(__CLASS__, 'sync_company_name_key'), 10);
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $title,
            ));
            add_action('save_post_company', array(__CLASS__, 'sync_company_name_key'), 10, 3);
        }

        if (function_exists('clean_post_cache')) {
            clean_post_cache($post_id);
        }
    }

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

        if (class_exists('MJB_Job_Permalinks')) {
            MJB_Job_Permalinks::sync_geo_meta($post_id);
        }

        $company_name = isset($data['company']) ? sanitize_text_field($data['company']) : '';
        if ($company_name !== '') {
            $company_id = self::find_or_create_company($company_name);
            if ($company_id) {
                update_post_meta($post_id, '_company_id', $company_id);
                update_post_meta($post_id, '_company_name', $company_name);
            }
        }

        update_post_meta($post_id, '_featured', !empty($data['featured']) ? '1' : '0');

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
     * Find or create a company post (never creates a second company for the same name).
     *
     * @param string $company_name
     * @param array  $args {
     *     @type int $author_id Optional post author for newly created companies.
     * }
     * @return int
     */
    public static function find_or_create_company($company_name, $args = array())
    {
        $args = wp_parse_args($args, array(
            'author_id' => 0,
        ));

        $company_name = self::normalize_company_name($company_name);
        if ($company_name === '') {
            return 0;
        }

        $existing_id = self::find_company_by_name($company_name);
        if ($existing_id > 0) {
            self::sync_company_name_key($existing_id);
            return $existing_id;
        }

        $postarr = array(
            'post_title' => $company_name,
            'post_type' => 'company',
            'post_status' => 'publish',
        );

        if (intval($args['author_id']) > 0) {
            $postarr['post_author'] = intval($args['author_id']);
        }

        $company_id = wp_insert_post($postarr, true);
        if (!$company_id || is_wp_error($company_id)) {
            return 0;
        }

        $company_id = intval($company_id);
        update_post_meta($company_id, self::COMPANY_NAME_KEY_META, self::company_name_key($company_name));

        return $company_id;
    }

    /**
     * Merge duplicate company posts that share the same normalized name.
     *
     * Keeps the company with the most linked jobs (lowest ID as tie-breaker),
     * reassigns job `_company_id` meta, then permanently deletes the extras.
     *
     * @param array $args {
     *     @type bool $delete Whether to permanently delete duplicate posts. Default true.
     * }
     * @return array{groups:int,merged:int,deleted:int,kept:int[]}
     */
    public static function dedupe_companies($args = array())
    {
        $args = wp_parse_args($args, array(
            'delete' => true,
        ));

        $companies = get_posts(array(
            'post_type' => 'company',
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        $groups = array();
        foreach ($companies as $company) {
            $post = is_object($company) ? $company : get_post($company);
            if (!$post) {
                continue;
            }

            $key = self::company_name_key($post->post_title);
            if ($key === '') {
                continue;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = array();
            }

            $groups[$key][] = $post;
        }

        $merged = 0;
        $deleted = 0;
        $kept = array();

        foreach ($groups as $key => $items) {
            if (count($items) === 1) {
                $only = $items[0];
                self::sync_company_name_key($only->ID, $only);
                $kept[] = intval($only->ID);
                continue;
            }

            usort($items, static function ($a, $b) {
                $jobs_a = self::count_jobs_for_company(intval($a->ID));
                $jobs_b = self::count_jobs_for_company(intval($b->ID));
                if ($jobs_a === $jobs_b) {
                    return intval($a->ID) <=> intval($b->ID);
                }
                return $jobs_b <=> $jobs_a;
            });

            $keeper = array_shift($items);
            $keeper_id = intval($keeper->ID);
            $clean_name = self::normalize_company_name($keeper->post_title);

            if ($keeper->post_title !== $clean_name) {
                self::update_company_title($keeper_id, $clean_name);
            }
            update_post_meta($keeper_id, self::COMPANY_NAME_KEY_META, $key);
            $kept[] = $keeper_id;

            foreach ($items as $duplicate) {
                $duplicate_id = intval($duplicate->ID);
                self::reassign_company_jobs($duplicate_id, $keeper_id, $clean_name);
                $merged++;

                if ($args['delete']) {
                    $result = wp_delete_post($duplicate_id, true);
                    if ($result) {
                        $deleted++;
                    }
                }
            }
        }

        return array(
            'groups' => count($groups),
            'merged' => $merged,
            'deleted' => $deleted,
            'kept' => $kept,
        );
    }

    /**
     * Count job listings linked to a company (any status except trash).
     *
     * @param int $company_id
     * @return int
     */
    public static function count_jobs_for_company($company_id)
    {
        $company_id = intval($company_id);
        if ($company_id < 1) {
            return 0;
        }

        $query = new WP_Query(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'private', 'expired'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                array(
                    'key' => '_company_id',
                    'value' => $company_id,
                ),
            ),
        ));

        return intval($query->found_posts);
    }

    /**
     * Point jobs at a different company and refresh legacy name meta.
     *
     * @param int    $from_company_id
     * @param int    $to_company_id
     * @param string $company_name
     */
    private static function reassign_company_jobs($from_company_id, $to_company_id, $company_name)
    {
        $from_company_id = intval($from_company_id);
        $to_company_id = intval($to_company_id);
        if ($from_company_id < 1 || $to_company_id < 1 || $from_company_id === $to_company_id) {
            return;
        }

        $job_ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'private', 'expired', 'future'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_company_id',
            'meta_value' => $from_company_id,
        ));

        foreach ($job_ids as $job_id) {
            $job_id = intval($job_id);
            update_post_meta($job_id, '_company_id', $to_company_id);
            if ($company_name !== '') {
                update_post_meta($job_id, '_company_name', $company_name);
            }
        }
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