<?php
/**
 * Jobs map shortcode (WPJB Tier B1).
 * Uses the Google Maps API key already stored in settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Jobs_Map
{
    const OPTION_HEIGHT = 'mjb_map_height';
    const OPTION_LIMIT  = 'mjb_map_job_limit';
    const OPTION_ZOOM   = 'mjb_map_default_zoom';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_shortcode('mjb_map', array(__CLASS__, 'render_shortcode'));
        add_shortcode('mjb_jobs_map', array(__CLASS__, 'render_shortcode'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    /**
     * Settings under listing section.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_HEIGHT, array(
            'type' => 'integer',
            'default' => 420,
            'sanitize_callback' => function ($v) {
                return max(200, min(900, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_LIMIT, array(
            'type' => 'integer',
            'default' => 50,
            'sanitize_callback' => function ($v) {
                return max(1, min(200, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_ZOOM, array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => function ($v) {
                return max(1, min(18, (int) $v));
            },
        ));

        add_settings_field(
            self::OPTION_HEIGHT,
            __('Jobs map height (px)', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_HEIGHT, 420);
                echo '<input type="number" min="200" max="900" name="' . esc_attr(self::OPTION_HEIGHT) . '" value="' . esc_attr((string) $v) . '">';
            },
            'mjb-settings',
            'mjb_listing_section'
        );
        add_settings_field(
            self::OPTION_LIMIT,
            __('Jobs map max markers', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_LIMIT, 50);
                echo '<input type="number" min="1" max="200" name="' . esc_attr(self::OPTION_LIMIT) . '" value="' . esc_attr((string) $v) . '">';
                echo '<p class="description">' . esc_html__('Used by [mjb_map] / [mjb_jobs_map]. Requires Google Maps API key.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_listing_section'
        );
    }

    /**
     * Register front-end script handle (enqueued only when shortcode renders).
     */
    public static function register_assets()
    {
        wp_register_script(
            'mjb-jobs-map',
            MJB_URL . 'assets/js/mjb-jobs-map.js',
            array(),
            MJB_VERSION,
            true
        );
        wp_register_style(
            'mjb-jobs-map',
            MJB_URL . 'assets/css/mjb-jobs-map.css',
            array('mjb-style'),
            MJB_VERSION
        );
    }

    /**
     * Collect map markers for published jobs.
     *
     * @param array $atts Shortcode attributes.
     * @return array<int, array{id:int,title:string,url:string,location:string,company:string}>
     */
    public static function collect_markers(array $atts = array())
    {
        $limit = isset($atts['limit']) ? max(1, min(200, (int) $atts['limit'])) : (int) get_option(self::OPTION_LIMIT, 50);
        $category = isset($atts['category']) ? sanitize_title($atts['category']) : '';
        $location = isset($atts['location']) ? sanitize_title($atts['location']) : '';

        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
        );

        $tax_query = array();
        if ($category !== '') {
            $tax_query[] = array(
                'taxonomy' => 'job_category',
                'field' => 'slug',
                'terms' => $category,
            );
        }
        if ($location !== '') {
            $tax_query[] = array(
                'taxonomy' => 'job_location',
                'field' => 'slug',
                'terms' => $location,
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        if (class_exists('MJB_Job_Ops') && get_option(MJB_Job_Ops::OPTION_HIDE_FILLED, '1') === '1') {
            $args = MJB_Job_Ops::filter_listing_query($args, array());
        }

        $ids = get_posts($args);
        $markers = array();

        foreach ((array) $ids as $job_id) {
            $job_id = (int) $job_id;
            $loc = class_exists('MJB_Location')
                ? MJB_Location::format_job_location($job_id)
                : '';
            if ($loc === '') {
                continue;
            }
            $markers[] = array(
                'id' => $job_id,
                'title' => get_the_title($job_id),
                'url' => get_permalink($job_id),
                'location' => $loc,
                'company' => (string) get_post_meta($job_id, '_company_name', true),
            );
        }

        /**
         * Filter map markers before render.
         *
         * @param array $markers
         * @param array $atts
         */
        return apply_filters('mjb_jobs_map_markers', $markers, $atts);
    }

    /**
     * Shortcode callback.
     *
     * @param array|string $atts
     * @return string
     */
    public static function render_shortcode($atts = array())
    {
        $atts = shortcode_atts(array(
            'limit' => (int) get_option(self::OPTION_LIMIT, 50),
            'height' => (int) get_option(self::OPTION_HEIGHT, 420),
            'zoom' => (int) get_option(self::OPTION_ZOOM, 5),
            'category' => '',
            'location' => '',
            'class' => '',
        ), $atts, 'mjb_map');

        $api_key = get_option('mjb_google_maps_api_key');
        $markers = self::collect_markers($atts);
        $height = max(200, min(900, (int) $atts['height']));
        $zoom = max(1, min(18, (int) $atts['zoom']));

        if (empty($api_key)) {
            return '<div class="mjb-map mjb-map--no-key"><p>' . esc_html__('Configure a Google Maps API key under Modern Job Board → Settings to show the jobs map.', 'modern-job-board') . '</p>'
                . self::render_marker_list($markers) . '</div>';
        }

        if (empty($markers)) {
            return '<div class="mjb-map mjb-map--empty"><p>' . esc_html__('No jobs with locations to map.', 'modern-job-board') . '</p></div>';
        }

        wp_enqueue_style('mjb-jobs-map');
        wp_enqueue_script('mjb-jobs-map');

        $map_id = 'mjb-map-' . (function_exists('wp_unique_id') ? wp_unique_id() : uniqid());
        $payload = wp_json_encode(array(
            'markers' => $markers,
            'zoom' => $zoom,
            'apiKey' => $api_key,
        ));

        // Load Maps JS with callback once.
        $maps_src = add_query_arg(array(
            'key' => $api_key,
            'callback' => 'mjbInitJobsMaps',
            'v' => 'weekly',
        ), 'https://maps.googleapis.com/maps/api/js');

        if (!wp_script_is('google-maps-api', 'registered')) {
            wp_register_script('google-maps-api', $maps_src, array('mjb-jobs-map'), null, true);
        }
        wp_enqueue_script('google-maps-api');

        $class = 'mjb-map mjb-jobs-map';
        if (!empty($atts['class'])) {
            $class .= ' ' . sanitize_html_class($atts['class']);
        }

        ob_start();
        echo '<div class="' . esc_attr($class) . '">';
        echo '<div id="' . esc_attr($map_id) . '" class="mjb-jobs-map__canvas" style="height:' . esc_attr((string) $height) . 'px" data-mjb-jobs-map="' . esc_attr($payload) . '"></div>';
        echo wp_kses_post(self::render_marker_list($markers));
        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * Accessible fallback list under the map.
     *
     * @param array $markers
     * @return string
     */
    public static function render_marker_list(array $markers)
    {
        if (empty($markers)) {
            return '';
        }
        $html = '<ul class="mjb-jobs-map__list">';
        foreach ($markers as $m) {
            $title = isset($m['title']) ? $m['title'] : '';
            $url = isset($m['url']) ? $m['url'] : '';
            $loc = isset($m['location']) ? $m['location'] : '';
            $company = isset($m['company']) ? $m['company'] : '';
            $html .= '<li>';
            if ($url) {
                $html .= '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
            } else {
                $html .= esc_html($title);
            }
            if ($company !== '') {
                $html .= ' <span class="mjb-jobs-map__company">' . esc_html($company) . '</span>';
            }
            if ($loc !== '') {
                $html .= ' <span class="mjb-jobs-map__loc">' . esc_html($loc) . '</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
