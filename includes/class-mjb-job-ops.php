<?php
/**
 * Job ops parity (WPJB A2–A4): filled/schedule/republish, list badges, related jobs, WhatsApp apply.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Job_Ops
{
    const META_FILLED = '_job_filled';
    const META_WHATSAPP = '_application_whatsapp';
    const OPTION_NEW_DAYS = 'mjb_new_job_badge_days';
    const OPTION_HIDE_FILLED = 'mjb_hide_filled_jobs';
    const OPTION_RELATED = 'mjb_related_jobs_count';

    /** @var bool Re-entry guard for related block while rendering job cards. */
    private static $rendering_related = false;

    /** @var array<int, bool> Jobs that already rendered a related block this request. */
    private static $related_rendered_for = array();

    /**
     * Whether related jobs (or nested card content) are currently rendering.
     *
     * @return bool
     */
    public static function is_rendering_related()
    {
        return self::$rendering_related;
    }

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('init', array(__CLASS__, 'maybe_schedule_publish_cron'));
        add_action('mjb_publish_scheduled_jobs', array(__CLASS__, 'publish_due_jobs'));
        add_filter('mjb_job_listing_query_args', array(__CLASS__, 'filter_listing_query'), 15, 2);
        // Only after Apply/Save/Share — single-job.php fires this (avoids the_content duplicate).
        add_action('mjb_single_job_after_content', array(__CLASS__, 'render_related_jobs'), 20);
    }

    /**
     * Settings for list UX.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_NEW_DAYS, array(
            'type' => 'integer',
            'default' => 7,
            'sanitize_callback' => function ($v) {
                return max(0, min(90, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_HIDE_FILLED, array(
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_RELATED, array(
            'type' => 'integer',
            'default' => 3,
            'sanitize_callback' => function ($v) {
                return max(0, min(12, (int) $v));
            },
        ));

        add_settings_field(
            self::OPTION_NEW_DAYS,
            __('“New” badge (days)', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_NEW_DAYS, 7);
                echo '<input type="number" min="0" max="90" name="' . esc_attr(self::OPTION_NEW_DAYS) . '" value="' . esc_attr((string) $v) . '"> ';
                echo '<p class="description">' . esc_html__('Jobs posted within this many days show a New badge. 0 disables.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_listing_section'
        );
        add_settings_field(
            self::OPTION_HIDE_FILLED,
            __('Hide filled jobs', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_HIDE_FILLED) . '" value="1" ' . checked(get_option(self::OPTION_HIDE_FILLED, '1'), '1', false) . '> ' . esc_html__('Exclude filled jobs from public lists and search', 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_listing_section'
        );
        add_settings_field(
            self::OPTION_RELATED,
            __('Related jobs count', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_RELATED, 3);
                echo '<input type="number" min="0" max="12" name="' . esc_attr(self::OPTION_RELATED) . '" value="' . esc_attr((string) $v) . '"> ';
                echo '<p class="description">' . esc_html__('Related jobs on single job pages (same category/location). 0 disables.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_listing_section'
        );
    }

    /**
     * @param int $post_id
     * @return bool
     */
    public static function is_filled($post_id)
    {
        $v = get_post_meta((int) $post_id, self::META_FILLED, true);
        return $v === '1' || $v === 1 || $v === true || $v === 'yes';
    }

    /**
     * @param int  $post_id
     * @param bool $filled
     */
    public static function set_filled($post_id, $filled)
    {
        update_post_meta((int) $post_id, self::META_FILLED, $filled ? '1' : '0');
    }

    /**
     * @param int $post_id
     * @return bool
     */
    public static function is_new($post_id)
    {
        $days = (int) get_option(self::OPTION_NEW_DAYS, 7);
        if ($days <= 0) {
            return false;
        }
        $ts = get_post_time('U', true, $post_id);
        if (!$ts) {
            return false;
        }
        return $ts >= (time() - ($days * DAY_IN_SECONDS));
    }

    /**
     * Exclude filled jobs from public listing queries when enabled.
     *
     * Important: AND-merge so we never change an existing top-level `relation`
     * (e.g. featured EXISTS OR missing). Appending with `[]` onto a featured
     * OR-group made hide-filled OR with featured and broke featured ordering.
     *
     * @param array $args
     * @param array $params
     * @return array
     */
    public static function filter_listing_query($args, $params = array())
    {
        unset($params);
        if (get_option(self::OPTION_HIDE_FILLED, '1') !== '1') {
            return $args;
        }

        $filled_clause = array(
            'relation' => 'OR',
            array(
                'key' => self::META_FILLED,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => self::META_FILLED,
                'value' => '1',
                'compare' => '!=',
            ),
        );

        if (!isset($args['meta_query']) || !is_array($args['meta_query']) || $args['meta_query'] === array()) {
            $args['meta_query'] = array($filled_clause);
            return $args;
        }

        // Idempotent: already wrapped/added by build_query_args + mjb_job_listing_query_args.
        if (self::meta_query_has_filled_filter($args['meta_query'])) {
            return $args;
        }

        $args['meta_query'] = array(
            'relation' => 'AND',
            $args['meta_query'],
            $filled_clause,
        );

        return $args;
    }

    /**
     * Whether a meta_query tree already includes the hide-filled clause.
     *
     * @param array $meta_query
     * @return bool
     */
    private static function meta_query_has_filled_filter($meta_query)
    {
        if (!is_array($meta_query)) {
            return false;
        }

        foreach ($meta_query as $key => $clause) {
            if ($key === 'relation' || !is_array($clause)) {
                continue;
            }
            if (isset($clause['key']) && $clause['key'] === self::META_FILLED) {
                return true;
            }
            // Nested group (relation + children).
            if (isset($clause['relation']) || self::is_meta_query_list($clause)) {
                if (self::meta_query_has_filled_filter($clause)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $clause
     * @return bool
     */
    private static function is_meta_query_list($clause)
    {
        if (!is_array($clause) || isset($clause['key'])) {
            return false;
        }
        foreach ($clause as $k => $v) {
            if ($k === 'relation') {
                continue;
            }
            if (is_array($v)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Also apply hide-filled on core search builder.
     *
     * @param array $args
     * @return array
     */
    public static function filter_build_query_args($args)
    {
        return self::filter_listing_query($args, array());
    }

    /**
     * Cron for future-dated publishes (post_status future already handled by WP;
     * this republishes draft/pending with _job_publish_at meta).
     */
    public static function maybe_schedule_publish_cron()
    {
        if (!wp_next_scheduled('mjb_publish_scheduled_jobs')) {
            wp_schedule_event(time() + 300, 'hourly', 'mjb_publish_scheduled_jobs');
        }
    }

    /**
     * Publish jobs whose scheduled date has passed.
     */
    public static function publish_due_jobs()
    {
        $now = current_time('Y-m-d H:i:s');
        $q = new WP_Query(array(
            'post_type' => 'job_listing',
            'post_status' => array('draft', 'pending', 'future'),
            'posts_per_page' => 50,
            'meta_query' => array(
                array(
                    'key' => '_job_publish_at',
                    'value' => $now,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ),
            ),
            'fields' => 'ids',
        ));
        foreach ($q->posts as $job_id) {
            if (!MJB_License::can_publish_job((int) $job_id)) {
                continue;
            }
            wp_update_post(array(
                'ID' => (int) $job_id,
                'post_status' => 'publish',
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql', true),
            ));
            delete_post_meta((int) $job_id, '_job_publish_at');
        }
    }

    /**
     * Related jobs block.
     *
     * @param int $job_id
     */
    public static function render_related_jobs($job_id = 0)
    {
        if (self::$rendering_related) {
            return;
        }

        $job_id = (int) ($job_id ?: get_the_ID());
        $count = (int) get_option(self::OPTION_RELATED, 3);
        if ($count <= 0 || $job_id <= 0) {
            return;
        }
        // Once per job per request (prevents the_content + template action double render).
        if (!empty(self::$related_rendered_for[$job_id])) {
            return;
        }
        self::$related_rendered_for[$job_id] = true;

        $tax_query = array('relation' => 'OR');
        foreach (array('job_category', 'job_location', 'job_type') as $tax) {
            $terms = wp_get_post_terms($job_id, $tax, array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                $tax_query[] = array(
                    'taxonomy' => $tax,
                    'field' => 'term_id',
                    'terms' => $terms,
                );
            }
        }
        if (count($tax_query) < 2) {
            return;
        }

        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $count,
            'post__not_in' => array((int) $job_id),
            'tax_query' => $tax_query,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        );
        $args = apply_filters('mjb_job_listing_query_args', $args, array());

        self::$rendering_related = true;
        $q = new WP_Query($args);
        if ($q->have_posts()) {
            $browse_url = class_exists('MJB_Job_Routes') ? MJB_Job_Routes::build_url() : home_url('/');
            echo '<section class="mjb-related-jobs" aria-labelledby="mjb-related-jobs-title">';
            echo '<header class="mjb-related-jobs__header">';
            echo '<div class="mjb-related-jobs__heading">';
            echo '<h3 id="mjb-related-jobs-title" class="mjb-related-jobs__title">' . esc_html__('Similar jobs you might like', 'modern-job-board') . '</h3>';
            echo '<p class="mjb-related-jobs__hint">' . esc_html__('Based on this role\'s category, location, or job type.', 'modern-job-board') . '</p>';
            echo '</div>';
            echo '<a class="mjb-related-jobs__browse" href="' . esc_url($browse_url) . '">' . esc_html__('Browse all jobs', 'modern-job-board') . '</a>';
            echo '</header>';
            if (class_exists('MJB_Shortcodes')) {
                MJB_Shortcodes::render_job_loop($q);
            }
            echo '</section>';
        }
        wp_reset_postdata();
        self::$rendering_related = false;
    }

    /**
     * WhatsApp deep link from stored international number.
     *
     * @param string $number
     * @param string $prefill
     * @return string
     */
    public static function whatsapp_url($number, $prefill = '')
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if ($digits === '') {
            return '';
        }
        $url = 'https://wa.me/' . $digits;
        if ($prefill !== '') {
            $url = add_query_arg('text', rawurlencode($prefill), $url);
        }
        return $url;
    }

    /**
     * Parse multi email string (comma/semicolon/newline).
     *
     * @param string $raw
     * @return string[]
     */
    public static function parse_emails($raw)
    {
        $parts = preg_split('/[\s,;]+/', (string) $raw);
        $out = array();
        foreach ((array) $parts as $p) {
            $p = sanitize_email(trim($p));
            if ($p !== '' && is_email($p)) {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }
}
