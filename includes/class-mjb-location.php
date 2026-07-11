<?php
/**
 * Job location display formatting.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Location
{
    /**
     * Country slug to display label.
     *
     * @var array<string, string>
     */
    private static $country_labels = array(
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'ca' => 'Canada',
        'au' => 'Australia',
        'de' => 'Germany',
        'ie' => 'Ireland',
    );

    /**
     * State/province slug to display label.
     *
     * @var array<string, string>
     */
    private static $state_labels = array(
        'california' => 'California',
        'england' => 'England',
        'texas' => 'Texas',
        'ny' => 'New York',
        'ontario' => 'Ontario',
        'nsw' => 'New South Wales',
        'oregon' => 'Oregon',
        'leinster' => 'Leinster',
        'germany' => 'Germany',
    );

    /**
     * Geo segments that should not appear in user-facing labels.
     *
     * @var array<string, array<int, string>>
     */
    private static $placeholder_segments = array(
        'city' => array('worldwide'),
        'state' => array('region', 'remote'),
        'country' => array('global'),
    );

    /**
     * Format a job listing location for display.
     *
     * @param int $post_id
     * @return string
     */
    public static function format_job_location($post_id)
    {
        $term = self::get_primary_location_term($post_id);

        if (!$term) {
            return '';
        }

        return self::format_location_term($term);
    }

    /**
     * Return linked location markup for job meta pills.
     *
     * @param int    $post_id
     * @param string $separator
     * @return string
     */
    public static function render_job_location_term_list($post_id, $separator = ', ')
    {
        $terms = wp_get_post_terms($post_id, 'job_location');

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $links = array();

        foreach ($terms as $term) {
            $term = self::normalize_term($term);
            $link = get_term_link($term, 'job_location');

            if (is_wp_error($link)) {
                continue;
            }

            $links[] = '<a href="' . esc_url($link) . '" rel="tag">' . esc_html(self::format_location_term($term)) . '</a>';
        }

        return implode($separator, $links);
    }

    /**
     * Format a location taxonomy term.
     *
     * @param object|array|string $term
     * @return string
     */
    public static function format_location_term($term)
    {
        $term = self::normalize_term($term);

        if (!$term) {
            return '';
        }

        return self::format_location_slug($term->slug, $term->name);
    }

    /**
     * Format a location slug into city, region, country copy.
     *
     * @param string $slug
     * @param string $fallback_name
     * @return string
     */
    public static function format_location_slug($slug, $fallback_name = '')
    {
        $slug = MJB_Search::normalize_slug($slug);

        if ($slug === '') {
            return '';
        }

        if ($slug === 'remote') {
            return $fallback_name !== '' ? $fallback_name : __('Remote', 'modern-job-board');
        }

        $geo = MJB_Job_Permalinks::map_location_slug_to_geo($slug);

        $city = self::resolve_city_label($geo['city'] ?? '', $fallback_name, $slug);
        $state = self::resolve_state_label($geo['state'] ?? '');
        $country = self::resolve_country_label($geo['country'] ?? '');

        return self::join_parts($city, $state, $country);
    }

    /**
     * Resolve structured schema address parts for a job listing.
     *
     * @param int $post_id
     * @return array{locality:string,region:string,country:string,formatted:string}
     */
    public static function get_job_schema_address($post_id)
    {
        $term = self::get_primary_location_term($post_id);

        if (!$term) {
            return array(
                'locality' => '',
                'region' => '',
                'country' => '',
                'formatted' => '',
            );
        }

        $slug = MJB_Search::normalize_slug($term->slug);

        if ($slug === 'remote') {
            $formatted = $term->name !== '' ? $term->name : __('Remote', 'modern-job-board');

            return array(
                'locality' => $formatted,
                'region' => '',
                'country' => '',
                'formatted' => $formatted,
            );
        }

        $geo = MJB_Job_Permalinks::map_location_slug_to_geo($slug);

        $city = self::resolve_city_label($geo['city'] ?? '', $term->name, $slug);
        $state = self::resolve_state_label($geo['state'] ?? '');
        $country = self::resolve_country_label($geo['country'] ?? '');

        return array(
            'locality' => $city,
            'region' => $state,
            'country' => $country,
            'formatted' => self::join_parts($city, $state, $country),
        );
    }

    /**
     * Get the first location term attached to a job listing.
     *
     * @param int $post_id
     * @return object|null
     */
    public static function get_primary_location_term($post_id)
    {
        $terms = wp_get_post_terms($post_id, 'job_location');

        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        return self::normalize_term($terms[0]);
    }

    /**
     * Normalize term-like values from WP or test stubs.
     *
     * @param object|array|string|null $term
     * @return object|null
     */
    private static function normalize_term($term)
    {
        if ($term === null || $term === '') {
            return null;
        }

        if (is_string($term)) {
            return (object) array(
                'name' => $term,
                'slug' => sanitize_title($term),
            );
        }

        if (is_array($term)) {
            return (object) array(
                'name' => $term['name'] ?? '',
                'slug' => $term['slug'] ?? sanitize_title($term['name'] ?? ''),
            );
        }

        if (!isset($term->slug) && isset($term->name)) {
            $term->slug = sanitize_title($term->name);
        }

        return $term;
    }

    /**
     * Join available location parts.
     *
     * @param string $city
     * @param string $state
     * @param string $country
     * @return string
     */
    private static function join_parts($city, $state, $country)
    {
        $city = trim((string) $city);
        $state = trim((string) $state);
        $country = trim((string) $country);

        if ($state !== '' && strcasecmp($state, $country) === 0) {
            $state = '';
        }

        $parts = array();

        foreach (array($city, $state, $country) as $part) {
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Resolve a city label.
     *
     * @param string $city_slug
     * @param string $fallback_name
     * @param string $location_slug
     * @return string
     */
    private static function resolve_city_label($city_slug, $fallback_name, $location_slug)
    {
        $city_slug = MJB_Search::normalize_slug($city_slug);

        if (self::is_placeholder_segment('city', $city_slug)) {
            return '';
        }

        if ($fallback_name !== '' && MJB_Search::normalize_slug($fallback_name) === $location_slug) {
            return $fallback_name;
        }

        if ($fallback_name !== '' && MJB_Search::normalize_slug($fallback_name) === $city_slug) {
            return $fallback_name;
        }

        return self::humanize_slug($city_slug);
    }

    /**
     * Resolve a state/province label.
     *
     * @param string $state_slug
     * @return string
     */
    private static function resolve_state_label($state_slug)
    {
        $state_slug = MJB_Search::normalize_slug($state_slug);

        if ($state_slug === '' || self::is_placeholder_segment('state', $state_slug)) {
            return '';
        }

        if (isset(self::$state_labels[$state_slug])) {
            return self::$state_labels[$state_slug];
        }

        return self::humanize_slug($state_slug);
    }

    /**
     * Resolve a country label.
     *
     * @param string $country_slug
     * @return string
     */
    private static function resolve_country_label($country_slug)
    {
        $country_slug = MJB_Search::normalize_slug($country_slug);

        if ($country_slug === '' || self::is_placeholder_segment('country', $country_slug)) {
            return '';
        }

        if (isset(self::$country_labels[$country_slug])) {
            return self::$country_labels[$country_slug];
        }

        return self::humanize_slug($country_slug);
    }

    /**
     * Whether a geo segment is an internal placeholder.
     *
     * @param string $type
     * @param string $value
     * @return bool
     */
    private static function is_placeholder_segment($type, $value)
    {
        return in_array($value, self::$placeholder_segments[$type] ?? array(), true);
    }

    /**
     * Convert a slug segment to title case words.
     *
     * @param string $slug
     * @return string
     */
    private static function humanize_slug($slug)
    {
        $slug = MJB_Search::normalize_slug($slug);

        if ($slug === '') {
            return '';
        }

        return ucwords(str_replace('-', ' ', $slug));
    }
}