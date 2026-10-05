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
        add_action('template_redirect', array($this, 'redirect_legacy_taxonomy_urls'), 1);
        add_action('template_redirect', array($this, 'redirect_to_clean_url'), 2);
        add_action('wp_ajax_mjb_filter_jobs', array($this, 'ajax_filter_jobs'));
        add_action('wp_ajax_nopriv_mjb_filter_jobs', array($this, 'ajax_filter_jobs'));
        add_action('wp_ajax_mjb_filter_suggest', array($this, 'ajax_filter_suggest'));
        add_action('wp_ajax_nopriv_mjb_filter_suggest', array($this, 'ajax_filter_suggest'));
    }

    /**
     * Count published job listings (for Filter N Jobs heading).
     *
     * @return int
     */
    public static function count_published_jobs()
    {
        $counts = wp_count_posts('job_listing');
        if (!$counts || !isset($counts->publish)) {
            return 0;
        }

        return max(0, (int) $counts->publish);
    }

    /**
     * Resolve a taxonomy term label for a selected slug.
     *
     * @param string $taxonomy
     * @param string $slug
     * @return string
     */
    public static function get_term_label($taxonomy, $slug)
    {
        $slug = self::normalize_slug($slug);
        if ($slug === '') {
            return '';
        }

        $term = get_term_by('slug', $slug, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return $slug;
        }

        if ($taxonomy === 'job_location' && class_exists('MJB_Location')) {
            return MJB_Location::format_location_term($term);
        }

        return $term->name;
    }

    /**
     * Resolve a company label for a selected company slug.
     *
     * @param string $slug
     * @return string
     */
    public static function get_company_label($slug)
    {
        $slug = self::normalize_slug($slug);
        if ($slug === '') {
            return '';
        }

        $posts = get_posts(array(
            'post_type' => 'company',
            'name' => $slug,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));

        if (empty($posts)) {
            return $slug;
        }

        $title = get_the_title($posts[0]);
        return $title !== '' ? $title : $slug;
    }

    /**
     * Autocomplete field: text input + hidden value + popup menu.
     *
     * @param array $args {
     *     @type string $name           Form field name (hidden value / slug).
     *     @type string $type           Suggest type: keywords|location|category|type|company.
     *     @type string $value          Selected slug/value.
     *     @type string $label          Display label for selected value.
     *     @type string $placeholder    Input placeholder.
     *     @type bool   $free_text      When true, value is free text (keywords).
     *     @type string $prefer_country ISO2 country to rank first (geocity).
     * }
     * @return string
     */
    public static function render_autocomplete_field($args)
    {
        $defaults = array(
            'name' => '',
            'type' => 'keywords',
            'value' => '',
            'label' => '',
            'placeholder' => '',
            'free_text' => false,
            'input_id' => '',
            'prefer_country' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $name = sanitize_key($args['name']);
        $type = sanitize_key($args['type']);
        $value = (string) $args['value'];
        $label = (string) $args['label'];
        $placeholder = (string) $args['placeholder'];
        $free_text = !empty($args['free_text']);
        $input_id = $args['input_id'] !== '' ? sanitize_html_class($args['input_id']) : '';
        $prefer_country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $args['prefer_country']));
        if (strlen($prefer_country) !== 2) {
            $prefer_country = '';
        }

        if ($label === '' && $value !== '') {
            $label = $value;
        }

        $display = $free_text ? $value : $label;
        $uid = $name !== '' ? $name : $type;
        $list_id = 'mjb-ac-list-' . $uid . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8);

        ob_start();
        ?>
        <div class="mjb-ac" data-mjb-ac="<?php echo esc_attr($type); ?>" data-free-text="<?php echo $free_text ? '1' : '0'; ?>"<?php echo $prefer_country !== '' ? ' data-prefer-country="' . esc_attr($prefer_country) . '"' : ''; ?>>
            <?php if (!$free_text) : ?>
                <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="mjb-ac__value">
            <?php endif; ?>
            <input
                type="text"
                class="mjb-ac__input<?php echo $free_text ? ' mjb-ac__input--keywords' : ''; ?>"
                <?php if ($input_id !== '') : ?>
                    id="<?php echo esc_attr($input_id); ?>"
                <?php endif; ?>
                <?php if ($free_text) : ?>
                    name="<?php echo esc_attr($name); ?>"
                <?php endif; ?>
                value="<?php echo esc_attr($display); ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($list_id); ?>"
                aria-haspopup="listbox"
            >
            <div
                id="<?php echo esc_attr($list_id); ?>"
                class="mjb-ac__menu"
                data-mjb-ac-menu
                hidden
                role="listbox"
            ></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Sanitize raw filter parameters.
     *
     * @param array $raw
     * @return array
     */
    public static function sanitize_filter_params($raw)
    {
        $page = 0;
        if (!empty($raw['mjb_page'])) {
            $page = intval($raw['mjb_page']);
        } elseif (!empty($raw['page'])) {
            $page = intval($raw['page']);
        } elseif (!empty($raw['paged'])) {
            $page = intval($raw['paged']);
        }
        if ($page < 1) {
            $page = 0;
        }

        return array(
            'search_keywords' => !empty($raw['search_keywords']) ? sanitize_text_field($raw['search_keywords']) : '',
            'search_location' => !empty($raw['search_location']) ? self::normalize_slug($raw['search_location']) : '',
            'search_category' => !empty($raw['search_category']) ? self::normalize_slug($raw['search_category']) : '',
            'search_type' => !empty($raw['search_type']) ? self::normalize_slug($raw['search_type']) : '',
            'search_company' => !empty($raw['search_company']) ? self::normalize_slug($raw['search_company']) : '',
            'page' => $page,
        );
    }

    /**
     * Normalize a URL slug segment to hyphenated form.
     *
     * @param string $value
     * @return string
     */
    public static function normalize_slug($value)
    {
        return sanitize_title(str_replace('_', '-', sanitize_text_field($value)));
    }

    /**
     * Read filter parameters from the current GET request.
     *
     * @return array
     */
    public static function get_request_filter_params()
    {
        $path = get_query_var(MJB_Job_Routes::QUERY_VAR);
        if (!empty($path)) {
            return MJB_Job_Routes::parse_path($path);
        }

        if (MJB_Job_Routes::has_legacy_query_filters()) {
            return self::sanitize_filter_params(MJB_Job_Routes::get_legacy_query_params());
        }

        return self::sanitize_filter_params(wp_unslash($_GET));
    }

    /**
     * Build a WP_Query args array from filter parameters.
     *
     * @param array $params
     * @param array $base_args
     * @return array
     */
    public static function build_query_args($params = array(), $base_args = array())
    {
        $defaults = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        );

        $args = wp_parse_args($base_args, $defaults);

        if (!empty($params['search_keywords'])) {
            $args['s'] = $params['search_keywords'];
        }

        $tax_map = array(
            'search_location' => 'job_location',
            'search_category' => 'job_category',
            'search_type' => 'job_type',
        );

        $tax_query = array();
        foreach ($tax_map as $param => $taxonomy) {
            if (!empty($params[$param])) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $params[$param],
                );
            }
        }

        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        $args['paged'] = !empty($params['page']) ? max(1, intval($params['page'])) : 1;

        if (!empty($params['search_company'])) {
            $company_id = self::resolve_company_id_from_slug($params['search_company']);
            if ($company_id) {
                $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
                $args['meta_query'][] = array(
                    'key' => '_company_id',
                    'value' => $company_id,
                );
            }
        }

        $args = self::apply_featured_ordering($args);

        if (class_exists('MJB_Job_Ops')) {
            $args = MJB_Job_Ops::filter_listing_query($args, $params);
        }

        return $args;
    }

    /**
     * Resolve a company post ID from a URL slug.
     *
     * @param string $slug
     * @return int
     */
    public static function resolve_company_id_from_slug($slug)
    {
        $slug = self::normalize_slug($slug);
        if ($slug === '') {
            return 0;
        }

        $companies = get_posts(array(
            'post_type' => 'company',
            'post_status' => 'publish',
            'name' => $slug,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));

        return !empty($companies) ? intval($companies[0]) : 0;
    }

    /**
     * Resolve the public URL for a company's job listings.
     *
     * @param int $post_id Job listing ID.
     * @return string
     */
    public static function get_company_jobs_url_for_listing($post_id)
    {
        $company_id = intval(get_post_meta($post_id, '_company_id', true));
        if ($company_id && get_post($company_id)) {
            return MJB_Job_Routes::build_url(array(
                'search_company' => get_post_field('post_name', $company_id),
            ));
        }

        $company_name = get_post_meta($post_id, '_company_name', true);
        if ($company_name === '') {
            return '';
        }

        $company_post = get_page_by_title($company_name, OBJECT, 'company');
        if ($company_post) {
            return MJB_Job_Routes::build_url(array(
                'search_company' => $company_post->post_name,
            ));
        }

        return MJB_Job_Routes::build_url(array(
            'search_company' => sanitize_title($company_name),
        ));
    }

    /**
     * Resolve a company display name from a URL slug.
     *
     * @param string $slug
     * @return string
     */
    public static function get_company_name_from_slug($slug)
    {
        $company_id = self::resolve_company_id_from_slug($slug);
        if ($company_id) {
            return get_the_title($company_id);
        }

        return ucwords(str_replace('-', ' ', self::normalize_slug($slug)));
    }

    /**
     * Build the heading copy for a job listing page.
     *
     * @param array|null $filter_params
     * @return array{title:string,intro:string}
     */
    public static function get_listing_page_heading($filter_params = null)
    {
        if ($filter_params === null) {
            $filter_params = self::get_request_filter_params();
        }

        if (!empty($filter_params['search_company'])) {
            $company_name = self::get_company_name_from_slug($filter_params['search_company']);

            return array(
                'title' => sprintf(
                    /* translators: %s: company name */
                    __('Jobs at %s', 'modern-job-board'),
                    $company_name
                ),
                'intro' => '',
            );
        }

        if (!empty($filter_params['search_location'])) {
            $location_label = MJB_Location::format_location_slug($filter_params['search_location']);

            return array(
                'title' => sprintf(
                    /* translators: %s: location label */
                    __('Jobs in %s', 'modern-job-board'),
                    $location_label
                ),
                'intro' => '',
            );
        }

        return array(
            'title' => __('Jobs', 'modern-job-board'),
            'intro' => __('Browse open roles and filter by keywords, location, category, job type, or company.', 'modern-job-board'),
        );
    }

    /**
     * Sort featured listings ahead of standard listings.
     *
     * Includes jobs that have no `_featured` meta (treated as not featured)
     * so frontend submissions are not excluded from listings.
     *
     * @param array $args
     * @return array
     */
    public static function apply_featured_ordering($args)
    {
        $meta_query = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : array();

        // OR of EXISTS / NOT EXISTS keeps every job in the result set while still
        // exposing a named clause for orderby (featured first, missing meta last).
        $featured_group = array(
            'relation' => 'OR',
            'mjb_featured_clause' => array(
                'key' => '_featured',
                'type' => 'NUMERIC',
                'compare' => 'EXISTS',
            ),
            'mjb_featured_missing' => array(
                'key' => '_featured',
                'compare' => 'NOT EXISTS',
            ),
        );

        if (!empty($meta_query)) {
            $meta_query = array(
                'relation' => 'AND',
                $meta_query,
                $featured_group,
            );
        } else {
            $meta_query = $featured_group;
        }

        $args['meta_query'] = $meta_query;
        unset($args['meta_key']);

        $args['orderby'] = array(
            'mjb_featured_clause' => 'DESC',
            'date' => 'DESC',
            'ID' => 'DESC',
        );

        return $args;
    }

    /**
     * Map a job type slug/name to a Schema.org employmentType value.
     *
     * @param string $type
     * @return string
     */
    public static function map_employment_type_for_schema($type)
    {
        $normalized = strtolower(trim($type));
        $map = array(
            'full-time' => 'FULL_TIME',
            'full time' => 'FULL_TIME',
            'part-time' => 'PART_TIME',
            'part time' => 'PART_TIME',
            'contract' => 'CONTRACTOR',
            'contractor' => 'CONTRACTOR',
            'temporary' => 'TEMPORARY',
            'temp' => 'TEMPORARY',
            'internship' => 'INTERN',
            'intern' => 'INTERN',
            'volunteer' => 'VOLUNTEER',
            'per diem' => 'PER_DIEM',
        );

        return $map[$normalized] ?? strtoupper(str_replace(array(' ', '-'), '_', $normalized));
    }

    /**
     * Render a location taxonomy dropdown.
     *
     * @param string $selected_slug
     * @param array  $args
     * @return string
     */
    public static function render_location_dropdown($selected_slug = '', $args = array())
    {
        $defaults = array(
            'name' => 'search_location',
            'id' => 'search_location',
            'show_option_all' => __('All Locations', 'modern-job-board'),
        );
        $args = wp_parse_args($args, $defaults);

        $locations = get_terms(array(
            'taxonomy' => 'job_location',
            'hide_empty' => false,
        ));

        if (is_wp_error($locations)) {
            $locations = array();
        }

        $html = '<select name="' . esc_attr($args['name']) . '" id="' . esc_attr($args['id']) . '">';
        $html .= '<option value="">' . esc_html($args['show_option_all']) . '</option>';

        foreach ($locations as $location) {
            $slug = self::normalize_slug($location->slug);
            $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_slug, $slug, false) . '>';
            $html .= esc_html(MJB_Location::format_location_term($location));
            $html .= '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Render a taxonomy dropdown with hyphenated slug values.
     *
     * @param string $taxonomy
     * @param string $name
     * @param string $selected_slug
     * @param string $show_option_all
     * @return string
     */
    public static function render_taxonomy_dropdown($taxonomy, $name, $selected_slug = '', $show_option_all = '')
    {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            $terms = array();
        }

        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        $html .= '<option value="">' . esc_html($show_option_all) . '</option>';

        foreach ($terms as $term) {
            $slug = self::normalize_slug($term->slug);
            $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_slug, $slug, false) . '>';
            $html .= esc_html($term->name);
            $html .= '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * AJAX Filter Jobs.
     */
    public function ajax_filter_jobs()
    {
        check_ajax_referer('mjb_search_nonce', 'security');

        $params = self::sanitize_filter_params(wp_unslash($_POST));
        $per_page = isset($_POST['posts_per_page']) ? max(1, intval($_POST['posts_per_page'])) : 10;
        $args = self::build_query_args($params, array('posts_per_page' => $per_page));
        $query = new WP_Query($args);

        if (class_exists('MJB_Shortcodes')) {
            MJB_Shortcodes::render_job_loop($query);
            MJB_Shortcodes::render_pagination($query, $params);
        } else {
            echo esc_html__('Error: Shortcodes class not found.', 'modern-job-board');
        }

        wp_die();
    }

    /**
     * AJAX autocomplete suggestions for filter fields.
     */
    public function ajax_filter_suggest()
    {
        check_ajax_referer('mjb_search_nonce', 'security');

        $type = isset($_REQUEST['type']) ? sanitize_key(wp_unslash($_REQUEST['type'])) : '';
        $q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
        $limit = isset($_REQUEST['limit']) ? max(1, min(25, (int) $_REQUEST['limit'])) : 12;

        $items = array();

        switch ($type) {
            case 'location':
                $items = self::suggest_terms('job_location', $q, $limit, true);
                break;
            case 'category':
                $items = self::suggest_terms('job_category', $q, $limit, false);
                break;
            case 'type':
                $items = self::suggest_terms('job_type', $q, $limit, false);
                break;
            case 'company':
                $items = self::suggest_companies($q, $limit);
                break;
            case 'keywords':
                $items = self::suggest_keywords($q, $limit);
                break;
            case 'geocity':
                // Local hierarchical city index (Photon only if empty).
                $prefer = isset($_REQUEST['country']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['country']))) : '';
                if (strlen($prefer) !== 2) {
                    $prefer = '';
                }
                $items = self::suggest_geocities($q, $limit, $prefer);
                break;
            default:
                wp_send_json_error(array('message' => 'invalid_type'), 400);
        }

        wp_send_json_success(array(
            'items' => $items,
            'total' => self::count_published_jobs(),
        ));
    }

    /**
     * @param string $taxonomy
     * @param string $q
     * @param int    $limit
     * @param bool   $format_location
     * @return array<int, array{value:string,label:string}>
     */
    private static function suggest_terms($taxonomy, $q, $limit, $format_location = false)
    {
        $args = array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => $limit,
            'orderby' => 'count',
            'order' => 'DESC',
        );

        if ($q !== '') {
            $args['search'] = $q;
            $args['orderby'] = 'name';
            $args['order'] = 'ASC';
        }

        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $items = array();
        foreach ($terms as $term) {
            $label = $format_location && class_exists('MJB_Location')
                ? MJB_Location::format_location_term($term)
                : $term->name;
            $items[] = array(
                'value' => self::normalize_slug($term->slug),
                'label' => $label,
            );
        }

        return $items;
    }

    /**
     * City suggestions: local first-letter shards (instant), Photon only if empty.
     *
     * Dataset: assets/data/cities-by-letter/{letter}.ser.gz (~156k places total).
     * Typical load+search is ~1–2 ms — no network while the user types.
     *
     * @param string $q
     * @param int    $limit
     * @return array<int, array{value:string,label:string}>
     */
    /**
     * @param string $q
     * @param int    $limit
     * @param string $prefer_country Optional ISO2 — rank this country first (e.g. ZA).
     * @return array<int, array{value:string,label:string}>
     */
    public static function suggest_geocities($q, $limit = 12, $prefer_country = '')
    {
        $q = trim((string) $q);
        // 3-char minimum matches prefix shards (fast global index).
        if (strlen($q) < 3) {
            return array();
        }

        $limit = max(1, min(20, (int) $limit));
        $q_lower = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        $prefer_country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $prefer_country));
        if (strlen($prefer_country) !== 2) {
            $prefer_country = '';
        }

        // Result cache (same query / backspace / multi-tab).
        // v6 = global all-countries populated places + detected-country ranking
        $cache_key = 'mjb_geocity_v6_' . md5($q_lower . '|' . $limit . '|' . $prefer_country);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        // Object cache (Redis/Memcached/APCu-backed) — faster than options table.
        if (function_exists('wp_cache_get')) {
            $oc = wp_cache_get($cache_key, 'mjb_geocity');
            if (is_array($oc)) {
                return $oc;
            }
        }

        $items = self::suggest_geocities_local($q_lower, $limit, $prefer_country);

        // Photon only when the local index has nothing (rare places / odd spelling).
        // Never block common typing on a remote geocoder.
        if (empty($items) && strlen($q_lower) >= 3) {
            $remote = self::suggest_geocities_photon($q, $limit);
            if (!empty($remote)) {
                $items = $remote;
            }
        }

        // Cache matches for 7 days — city names rarely change.
        set_transient($cache_key, $items, 7 * DAY_IN_SECONDS);
        if (function_exists('wp_cache_set')) {
            wp_cache_set($cache_key, $items, 'mjb_geocity', 7 * DAY_IN_SECONDS);
        }

        return $items;
    }

    /**
     * Search local letter shards (no HTTP). Starts-with preferred, then contains.
     * Labels: City, [borough/county], province/state, Country
     *
     * Row format: [name, iso2, name_lower, admin2, admin1, pop]
     * Legacy rows may be [name, iso2, name_lower] only.
     *
     * @param string $q_lower Already lowercased query.
     * @param int    $limit
     * @param string $prefer_country ISO2 to rank first.
     * @return array<int, array{value:string,label:string}>
     */
    private static function suggest_geocities_local($q_lower, $limit, $prefer_country = '')
    {
        $rows = self::load_world_cities_prefix($q_lower);
        if (empty($rows)) {
            return array();
        }

        $countries = self::load_country_names();
        $starts = array();
        $contains = array();
        $prefer_country = strtoupper((string) $prefer_country);

        foreach ($rows as $row) {
            $name_l = isset($row[2]) ? $row[2] : '';
            $pos = function_exists('mb_strpos')
                ? mb_strpos($name_l, $q_lower, 0, 'UTF-8')
                : strpos($name_l, $q_lower);

            if ($pos === false) {
                continue;
            }

            $iso = isset($row[1]) ? strtoupper((string) $row[1]) : '';
            $label = self::format_geocity_label($row, $countries);
            $pop = isset($row[5]) ? (int) $row[5] : 0;
            $item = array(
                'value' => $label,
                'label' => $label,
                '_pop' => $pop,
                '_pref' => ($prefer_country !== '' && $iso === $prefer_country) ? 1 : 0,
            );

            if ($pos === 0) {
                $starts[] = $item;
            } else {
                $contains[] = $item;
            }
        }

        // Preferred country first (so ZA suburbs beat same-named US towns), then population.
        $rank = static function ($a, $b) {
            $pc = ($b['_pref'] ?? 0) <=> ($a['_pref'] ?? 0);
            if ($pc !== 0) {
                return $pc;
            }
            return ($b['_pop'] ?? 0) <=> ($a['_pop'] ?? 0);
        };
        // Soft typo rescue for preferred country only (e.g. "Bellvile" → "Bellville").
        // Only when exact starts-with found nothing in the preferred country and query is long enough.
        if ($prefer_country !== '' && strlen($q_lower) >= 5) {
            $pref_starts = 0;
            foreach ($starts as $item) {
                if (!empty($item['_pref'])) {
                    $pref_starts++;
                }
            }
            if ($pref_starts === 0) {
                $seen_labels = array();
                foreach ($starts as $item) {
                    $seen_labels[strtolower($item['label'])] = true;
                }
                foreach ($rows as $row) {
                    $iso = isset($row[1]) ? strtoupper((string) $row[1]) : '';
                    if ($iso !== $prefer_country) {
                        continue;
                    }
                    $name_l = isset($row[2]) ? $row[2] : '';
                    if ($name_l === '' || isset($seen_labels[strtolower(self::format_geocity_label($row, $countries))])) {
                        continue;
                    }
                    // Prefix-tolerant: first 4 chars match + edit distance ≤ 2 on whole name.
                    $prefix_ok = (function_exists('mb_substr')
                        ? mb_substr($name_l, 0, 4, 'UTF-8') === mb_substr($q_lower, 0, 4, 'UTF-8')
                        : substr($name_l, 0, 4) === substr($q_lower, 0, 4));
                    if (!$prefix_ok) {
                        continue;
                    }
                    $dist = levenshtein(
                        function_exists('mb_substr') ? mb_substr($q_lower, 0, 32, 'UTF-8') : substr($q_lower, 0, 32),
                        function_exists('mb_substr') ? mb_substr($name_l, 0, 32, 'UTF-8') : substr($name_l, 0, 32)
                    );
                    if ($dist > 2) {
                        continue;
                    }
                    $label = self::format_geocity_label($row, $countries);
                    $starts[] = array(
                        'value' => $label,
                        'label' => $label,
                        '_pop' => isset($row[5]) ? (int) $row[5] : 0,
                        '_pref' => 1,
                    );
                    $seen_labels[strtolower($label)] = true;
                }
                usort($starts, $rank);
            }
        }

        usort($starts, $rank);
        usort($contains, $rank);

        $items = array();
        foreach ($starts as $item) {
            unset($item['_pop'], $item['_pref']);
            $items[] = $item;
            if (count($items) >= $limit) {
                return $items;
            }
        }
        foreach ($contains as $item) {
            unset($item['_pop'], $item['_pref']);
            $items[] = $item;
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * Build hierarchical place label: City, [borough], province/state, Country.
     *
     * @param array<int, mixed> $row
     * @param array<string, string> $countries
     * @return string
     */
    private static function format_geocity_label(array $row, array $countries)
    {
        $name = isset($row[0]) ? trim((string) $row[0]) : '';
        $iso = isset($row[1]) ? (string) $row[1] : '';
        $admin2 = isset($row[3]) ? trim((string) $row[3]) : '';
        $admin1 = isset($row[4]) ? trim((string) $row[4]) : '';
        $country = isset($countries[$iso]) ? $countries[$iso] : $iso;

        $parts = array();
        if ($name !== '') {
            $parts[] = $name;
        }
        // Borough / county / district — only when distinct and present.
        if ($admin2 !== '' && strcasecmp($admin2, $name) !== 0 && strcasecmp($admin2, $admin1) !== 0) {
            $parts[] = $admin2;
        }
        if ($admin1 !== '' && strcasecmp($admin1, $name) !== 0) {
            $parts[] = $admin1;
        }
        if ($country !== '') {
            $parts[] = $country;
        }

        // Collapse accidental consecutive duplicates.
        $out = array();
        foreach ($parts as $part) {
            if ($out && strcasecmp(end($out), $part) === 0) {
                continue;
            }
            $out[] = $part;
        }

        return implode(', ', $out);
    }

    /**
     * Resolve 3-char prefix shard key for a lowercased query (matches build script).
     *
     * @param string $q_lower
     * @return string
     */
    private static function city_prefix_key($q_lower)
    {
        $len = function_exists('mb_strlen') ? mb_strlen($q_lower, 'UTF-8') : strlen($q_lower);
        if ($len < 1) {
            return 'zzz';
        }
        $p = function_exists('mb_substr') ? mb_substr($q_lower, 0, 3, 'UTF-8') : substr($q_lower, 0, 3);
        if ($len < 3) {
            $p = str_pad($p, 3, 'x');
        }
        $safe = strtolower(preg_replace('/[^a-z0-9]/u', '', $p));
        if ($safe === '' || strlen($safe) < 1) {
            $safe = 'u';
            $n = min(3, $len);
            for ($i = 0; $i < $n; $i++) {
                $ch = function_exists('mb_substr') ? mb_substr($q_lower, $i, 1, 'UTF-8') : substr($q_lower, $i, 1);
                $ord = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : ord($ch);
                $safe .= dechex((int) $ord);
            }
        }
        $reserved = array(
            'con' => 1, 'prn' => 1, 'aux' => 1, 'nul' => 1,
            'com1' => 1, 'com2' => 1, 'com3' => 1, 'com4' => 1, 'com5' => 1,
            'com6' => 1, 'com7' => 1, 'com8' => 1, 'com9' => 1,
            'lpt1' => 1, 'lpt2' => 1, 'lpt3' => 1, 'lpt4' => 1, 'lpt5' => 1,
            'lpt6' => 1, 'lpt7' => 1, 'lpt8' => 1, 'lpt9' => 1,
        );
        if (isset($reserved[$safe]) || isset($reserved[substr($safe, 0, 3)])) {
            $safe = 'x' . $safe;
        }
        if (strlen($safe) > 40) {
            $safe = substr($safe, 0, 40);
        }
        return $safe;
    }

    /**
     * Load one 3-char prefix city shard (all countries). Typical load a few ms.
     *
     * @param string $q_lower Lowercased query (first 3 chars select the shard).
     * @return array<int, array{0:string,1:string,2:string,3?:string,4?:string,5?:int}>
     */
    private static function load_world_cities_prefix($q_lower)
    {
        static $cache = array();

        $prefix = self::city_prefix_key($q_lower);
        if (isset($cache[$prefix]) && is_array($cache[$prefix])) {
            return $cache[$prefix];
        }

        $oc_key = 'mjb_cities_pref_v1_' . $prefix;
        if (function_exists('wp_cache_get')) {
            $oc = wp_cache_get($oc_key, 'mjb_geocity');
            if (is_array($oc)) {
                $cache[$prefix] = $oc;
                return $oc;
            }
        }
        if (function_exists('apcu_fetch')) {
            $ok = false;
            $oc = apcu_fetch($oc_key, $ok);
            if ($ok && is_array($oc)) {
                $cache[$prefix] = $oc;
                return $oc;
            }
        }

        $rows = array();
        $base = trailingslashit(dirname(dirname(__FILE__))) . 'assets/data/';
        if ((!is_dir($base) || !is_readable($base)) && defined('MJB_PATH')) {
            $base = MJB_PATH . 'assets/data/';
        }

        // Prefer 3-char prefix shards (global). Fall back to legacy letter shards.
        $shard = $base . 'cities-by-prefix/' . $prefix . '.ser.gz';
        if (!is_readable($shard)) {
            // Legacy letter-shard path (older installs).
            $letter = function_exists('mb_substr') ? mb_substr($q_lower, 0, 1, 'UTF-8') : substr($q_lower, 0, 1);
            if (preg_match('/^[a-z0-9]$/u', (string) $letter)) {
                $legacy = $base . 'cities-by-letter/' . $letter . '.ser.gz';
                if (is_readable($legacy)) {
                    $shard = $legacy;
                }
            }
        }

        if (is_readable($shard)) {
            $raw = @file_get_contents($shard);
            if ($raw !== false) {
                $decoded = function_exists('gzdecode') ? @gzdecode($raw) : false;
                if ($decoded !== false) {
                    $un = @unserialize($decoded);
                    if (is_array($un)) {
                        $rows = $un;
                    }
                }
            }
        }

        $cache[$prefix] = $rows;

        if (!empty($rows)) {
            if (function_exists('wp_cache_set')) {
                wp_cache_set($oc_key, $rows, 'mjb_geocity', DAY_IN_SECONDS);
            }
            if (function_exists('apcu_store')) {
                @apcu_store($oc_key, $rows, DAY_IN_SECONDS);
            }
        }

        return $rows;
    }

    /**
     * ISO2 → English country name map.
     *
     * @return array<string, string>
     */
    private static function load_country_names()
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $path = trailingslashit(dirname(dirname(__FILE__))) . 'assets/data/country-names.php';
        if (!is_readable($path) && defined('MJB_PATH')) {
            $path = MJB_PATH . 'assets/data/country-names.php';
        }
        if (is_readable($path)) {
            $loaded = include $path;
            $map = is_array($loaded) ? $loaded : array();
        } else {
            $map = array();
        }

        return $map;
    }

    /**
     * Photon API fallback for rare place names (network).
     *
     * @param string $q
     * @param int    $limit
     * @return array<int, array{value:string,label:string}>
     */
    private static function suggest_geocities_photon($q, $limit)
    {
        $url = add_query_arg(
            array(
                'q' => $q,
                'limit' => $limit,
                'lang' => 'en',
            ),
            'https://photon.komoot.io/api/'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 4,
                'redirection' => 2,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'ModernJobBoard/' . (defined('MJB_VERSION') ? MJB_VERSION : '1.0') . ' (WordPress; city autocomplete)',
                ),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300 || $body === '') {
            return array();
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['features']) || !is_array($data['features'])) {
            return array();
        }

        $items = array();
        $seen = array();

        foreach ($data['features'] as $feature) {
            if (!is_array($feature) || empty($feature['properties']) || !is_array($feature['properties'])) {
                continue;
            }
            $p = $feature['properties'];
            $name = isset($p['name']) ? trim((string) $p['name']) : '';
            if ($name === '') {
                continue;
            }

            $type = isset($p['type']) ? (string) $p['type'] : '';
            $osm_value = isset($p['osm_value']) ? (string) $p['osm_value'] : '';
            $place_types = array('city', 'town', 'village', 'hamlet', 'locality', 'suburb', 'municipality', 'county', 'state');
            $ok_type = ($type === '' || in_array($type, $place_types, true) || in_array($osm_value, $place_types, true));
            if (!$ok_type && empty($p['city']) && empty($p['country'])) {
                continue;
            }

            // City, [borough/district/county], [parent city if suburb], province/state, Country
            $ordered = array($name);
            foreach (array('district', 'county') as $key) {
                if (empty($p[$key])) {
                    continue;
                }
                $part = trim((string) $p[$key]);
                if ($part === '' || strcasecmp($part, $name) === 0) {
                    continue;
                }
                $ordered[] = $part;
                break; // one borough-level component
            }
            if (!empty($p['city'])) {
                $part = trim((string) $p['city']);
                if ($part !== '' && strcasecmp($part, $name) !== 0 && !in_array($part, $ordered, true)) {
                    $type_l = strtolower($type . ' ' . $osm_value);
                    if (
                        strpos($type_l, 'suburb') !== false
                        || strpos($type_l, 'neighbourhood') !== false
                        || strpos($type_l, 'neighborhood') !== false
                        || strpos($type_l, 'quarter') !== false
                    ) {
                        $ordered[] = $part;
                    }
                }
            }
            if (!empty($p['state'])) {
                $part = trim((string) $p['state']);
                if ($part !== '' && strcasecmp($part, $name) !== 0 && !in_array($part, $ordered, true)) {
                    $ordered[] = $part;
                }
            }
            if (!empty($p['country'])) {
                $part = trim((string) $p['country']);
                if ($part !== '' && !in_array($part, $ordered, true)) {
                    $ordered[] = $part;
                }
            }

            $label = implode(', ', $ordered);
            $key = strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = array(
                'value' => $label,
                'label' => $label,
            );

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param string $q
     * @param int    $limit
     * @return array<int, array{value:string,label:string}>
     */
    private static function suggest_companies($q, $limit)
    {
        $args = array(
            'post_type' => 'company',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        );

        if ($q !== '') {
            $args['s'] = $q;
        }

        $ids = get_posts($args);
        $items = array();
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post) {
                continue;
            }
            $items[] = array(
                'value' => self::normalize_slug($post->post_name),
                'label' => $post->post_title,
            );
        }

        return $items;
    }

    /**
     * Keyword suggestions from published job titles.
     *
     * @param string $q
     * @param int    $limit
     * @return array<int, array{value:string,label:string}>
     */
    private static function suggest_keywords($q, $limit)
    {
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        );

        if ($q !== '') {
            $args['s'] = $q;
        }

        $ids = get_posts($args);
        $items = array();
        $seen = array();

        foreach ($ids as $id) {
            $title = get_the_title($id);
            if ($title === '') {
                continue;
            }
            $key = strtolower($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = array(
                'value' => $title,
                'label' => $title,
            );
        }

        return $items;
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
        $vars[] = 'search_company';
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
     *
     * @param WP_Query $query
     * @param array    $params Optional explicit params instead of $_GET.
     */
    public function apply_search_criteria($query, $params = null)
    {
        if ($params === null) {
            $params = self::get_request_filter_params();
        }

        $args = self::build_query_args($params);

        $query->set('s', isset($args['s']) ? $args['s'] : '');
        $query->set('tax_query', isset($args['tax_query']) ? $args['tax_query'] : array());
        $query->set('meta_query', isset($args['meta_query']) ? $args['meta_query'] : array());
        $query->set('meta_key', '');
        $query->set('paged', isset($args['paged']) ? max(1, intval($args['paged'])) : 1);
        $query->set('orderby', isset($args['orderby']) ? $args['orderby'] : array());

        if (isset($args['posts_per_page'])) {
            $query->set('posts_per_page', $args['posts_per_page']);
        }
    }

    /**
     * Redirect legacy taxonomy archive URLs to hyphenated /jobs/ paths.
     */
    public function redirect_legacy_taxonomy_urls()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (is_tax(array('job_type', 'job_category', 'job_location'))) {
            $term = get_queried_object();
            if (!$term || is_wp_error($term) || empty($term->taxonomy)) {
                return;
            }

            $params = array();
            if ($term->taxonomy === 'job_location') {
                $params['search_location'] = self::normalize_slug($term->slug);
            } elseif ($term->taxonomy === 'job_category') {
                $params['search_category'] = self::normalize_slug($term->slug);
            } elseif ($term->taxonomy === 'job_type') {
                $params['search_type'] = self::normalize_slug($term->slug);
            }

            wp_safe_redirect(MJB_Job_Routes::build_url($params), 301);
            exit;
        }

        $request_path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!is_string($request_path)) {
            return;
        }

        if (!preg_match('#/(job[-_]location|job[-_]type|job[-_]category)/([^/]+)/?#', $request_path, $matches)) {
            return;
        }

        $taxonomy_map = array(
            'job_location' => 'search_location',
            'job-location' => 'search_location',
            'job_category' => 'search_category',
            'job-category' => 'search_category',
            'job_type' => 'search_type',
            'job-type' => 'search_type',
        );

        $param_key = $taxonomy_map[$matches[1]] ?? '';
        if ($param_key === '') {
            return;
        }

        wp_safe_redirect(MJB_Job_Routes::build_url(array(
            $param_key => self::normalize_slug($matches[2]),
        )), 301);
        exit;
    }

    /**
     * Redirect archive query-string filters to pretty /jobs/ paths.
     */
    public function redirect_to_clean_url()
    {
        if (!is_post_type_archive('job_listing') && !is_home()) {
            return;
        }

        if (get_query_var(MJB_Job_Routes::QUERY_VAR)) {
            return;
        }

        if (MJB_Job_Routes::has_legacy_query_filters()) {
            return;
        }

        $params = self::get_request_filter_params();
        $active_filters = 0;

        foreach (array('search_location', 'search_category', 'search_type', 'search_company') as $key) {
            if (!empty($params[$key])) {
                $active_filters++;
            }
        }

        if ($active_filters < 1) {
            return;
        }

        $redirect_url = MJB_Job_Routes::build_url($params);
        $current = home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        if (trailingslashit($redirect_url) !== trailingslashit($current)) {
            wp_safe_redirect($redirect_url, 301);
            exit;
        }
    }
}