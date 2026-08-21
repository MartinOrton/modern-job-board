<?php
/**
 * Filter enable/disable UI (#24) + homepage building blocks toggle (#25).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Filter_Settings
{
    const OPTION_FILTERS = 'mjb_enabled_filters';
    const OPTION_HOME = 'mjb_home_blocks';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register'));
        add_filter('mjb_search_form_filters', array(__CLASS__, 'filter_visible_filters'));
    }

    /**
     * Default enabled filters.
     *
     * @return array<string, bool>
     */
    public static function default_filters()
    {
        return array(
            'keywords' => true,
            'location' => true,
            'category' => true,
            'type' => true,
            'company' => true,
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function get_filters()
    {
        $opt = get_option(self::OPTION_FILTERS, array());
        if (!is_array($opt)) {
            $opt = array();
        }
        return array_merge(self::default_filters(), $opt);
    }

    /**
     * Whether a filter key is enabled.
     *
     * @param string $key
     * @return bool
     */
    public static function is_filter_enabled($key)
    {
        $f = self::get_filters();
        return !empty($f[$key]);
    }

    /**
     * Homepage blocks.
     *
     * @return array<string, bool>
     */
    public static function get_home_blocks()
    {
        $defaults = array(
            'audience_cards' => true,
            'search_form' => true,
            'featured_jobs' => true,
            'categories' => false,
            'companies' => false,
        );
        $opt = get_option(self::OPTION_HOME, array());
        if (!is_array($opt)) {
            $opt = array();
        }
        return array_merge($defaults, $opt);
    }

    /**
     * @param string $block
     * @return bool
     */
    public static function home_block_enabled($block)
    {
        $b = self::get_home_blocks();
        return !empty($b[$block]);
    }

    /**
     * Register settings fields on main MJB settings page.
     */
    public static function register()
    {
        register_setting('mjb_settings_group', self::OPTION_FILTERS, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_filters'),
            'default' => self::default_filters(),
        ));
        register_setting('mjb_settings_group', self::OPTION_HOME, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_home'),
            'default' => array(),
        ));
        register_setting('mjb_settings_group', 'mjb_private_job_search', array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', 'mjb_auto_approve_trusted_employers', array(
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', 'mjb_pwa_enabled', array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', 'mjb_license_api_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
        ));

        add_settings_section(
            'mjb_filters_section',
            __('Search filters', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('Toggle which filters appear on the jobs search form.', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );

        foreach (self::default_filters() as $key => $default) {
            add_settings_field(
                'mjb_filter_' . $key,
                ucfirst($key),
                function () use ($key) {
                    $f = self::get_filters();
                    echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_FILTERS) . '[' . esc_attr($key) . ']" value="1" ' . checked(!empty($f[$key]), true, false) . '> ' . esc_html__('Enabled', 'modern-job-board') . '</label>';
                },
                'mjb-settings',
                'mjb_filters_section'
            );
        }

        add_settings_section(
            'mjb_board_mode_section',
            __('Board mode', 'modern-job-board'),
            null,
            'mjb-settings'
        );
        add_settings_field(
            'mjb_private_job_search',
            __('Private job search', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="mjb_private_job_search" value="1" ' . checked(get_option('mjb_private_job_search', '0'), '1', false) . '> ' . esc_html__('Require login to browse jobs', 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_board_mode_section'
        );
        add_settings_field(
            'mjb_auto_approve_trusted_employers',
            __('Auto-approve trusted recruiters', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="mjb_auto_approve_trusted_employers" value="1" ' . checked(get_option('mjb_auto_approve_trusted_employers', '1'), '1', false) . '> ' . esc_html__("Publish jobs immediately after a recruiter's first approved listing", 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_board_mode_section'
        );
        add_settings_field(
            'mjb_pwa_enabled',
            __('PWA mode', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="mjb_pwa_enabled" value="1" ' . checked(get_option('mjb_pwa_enabled', '0'), '1', false) . '> ' . esc_html__('Enable web app manifest + service worker', 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_board_mode_section'
        );
        add_settings_field(
            'mjb_license_api_url',
            __('Remote license API URL', 'modern-job-board'),
            function () {
                $v = get_option('mjb_license_api_url', '');
                echo '<input type="url" class="regular-text" name="mjb_license_api_url" value="' . esc_attr($v) . '" placeholder="https://license.example.com">';
                echo '<p class="description">' . esc_html__('Optional. Enables domain-bound activation and licensed updates (tasks #6–#7).', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_license_section'
        );
    }

    /**
     * @param mixed $input
     * @return array
     */
    public static function sanitize_filters($input)
    {
        $out = self::default_filters();
        foreach ($out as $k => $v) {
            $out[$k] = is_array($input) && !empty($input[$k]);
        }
        return $out;
    }

    /**
     * @param mixed $input
     * @return array
     */
    public static function sanitize_home($input)
    {
        $out = self::get_home_blocks();
        if (!is_array($input)) {
            return $out;
        }
        foreach ($out as $k => $v) {
            $out[$k] = !empty($input[$k]);
        }
        return $out;
    }

    /**
     * Filter which fields the search form should render.
     *
     * @param array $filters
     * @return array
     */
    public static function filter_visible_filters($filters)
    {
        if (!is_array($filters)) {
            return $filters;
        }
        $enabled = self::get_filters();
        foreach ($filters as $key => $val) {
            $map = array(
                'search_keywords' => 'keywords',
                'keywords' => 'keywords',
                'search_location' => 'location',
                'location' => 'location',
                'search_category' => 'category',
                'category' => 'category',
                'search_type' => 'type',
                'type' => 'type',
                'search_company' => 'company',
                'company' => 'company',
            );
            $flag = isset($map[$key]) ? $map[$key] : $key;
            if (isset($enabled[$flag]) && !$enabled[$flag]) {
                unset($filters[$key]);
            }
        }
        return $filters;
    }
}
