<?php
/**
 * Modern Job Board Shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Shortcodes
{
    const JOB_FORM_PAGE_OPTION = 'mjb_job_form_page_id';

    /**
     * HTML mark for a required field label (Elementor-style asterisk).
     *
     * @return string
     */
    public static function required_mark()
    {
        return ' <span class="mjb-required-mark" aria-hidden="true">*</span>';
    }

    /**
     * Short note shown above forms that have required fields.
     *
     * @return string
     */
    public static function required_fields_note()
    {
        return '<p class="mjb-form-required-note">'
            . '<span class="mjb-required-mark" aria-hidden="true">*</span> '
            . esc_html(__('Required fields', 'modern-job-board'))
            . '</p>';
    }

    /**
     * Optional field badge (Niceboard-style).
     *
     * @return string
     */
    public static function optional_badge()
    {
        return ' <span class="mjb-optional-badge">' . esc_html__('Optional', 'modern-job-board') . '</span>';
    }

    /**
     * Initialize Shortcodes.
     */
    public function init()
    {
        add_shortcode('mjb_jobs', array($this, 'output_jobs'));
        add_shortcode('mjb_job_form', array($this, 'output_job_form'));
        add_action('wp_ajax_mjb_save_job', array($this, 'ajax_save_job'));
    }

    /**
     * Output Job Listings.
     */
    public function output_jobs($atts)
    {
        $atts = shortcode_atts(array(
            'posts_per_page' => 10,
        ), $atts, 'mjb_jobs');

        $per_page = max(1, intval($atts['posts_per_page']));
        ob_start();

        $filter_params = MJB_Search::get_request_filter_params();
        $heading = MJB_Search::get_listing_page_heading($filter_params);

        self::render_content_hero($heading['title'], $heading['intro']);

        echo '<div class="mjb-container mjb-container--listing mjb-container--shortcode">';
        self::render_audience_cards($filter_params);
        echo '<div id="mjb-jobs-board" class="mjb-jobs-board">';
        self::render_jobs_loader();
        echo '<div class="mjb-content-area">';
        echo '<aside class="mjb-sidebar">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_job_search_form().
        echo self::render_job_search_form($filter_params, true);
        echo '</aside>';
        echo '<main class="site-main">';

        $args = MJB_Search::build_query_args($filter_params, array('posts_per_page' => $per_page));
        $args = apply_filters('mjb_job_listing_query_args', $args, $atts);
        $jobs = new WP_Query($args);

        do_action('mjb_before_job_listings', $jobs);

        echo '<div id="mjb-jobs-list" class="mjb-jobs-list-wrap" data-posts-per-page="' . esc_attr($per_page) . '"';
        if (!empty($filter_params['search_company'])) {
            echo ' data-search-company="' . esc_attr($filter_params['search_company']) . '"';
        }
        echo '>';
        echo '<div id="mjb-jobs-results">';
        self::render_job_loop($jobs);
        self::render_pagination($jobs, $filter_params);
        echo '</div></div>';

        echo '</main></div></div></div>';

        do_action('mjb_after_job_listings', $jobs);

        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render Job Loop HTML.
     * Static helper for reuse in AJAX.
     */
    public static function render_job_loop($jobs)
    {
        if ($jobs->have_posts()) {
            echo '<div class="mjb-job-list">';
            $card_index = 0;
            while ($jobs->have_posts()) {
                $jobs->the_post();
                $card_index++;

                $permalink = get_permalink();
                $job_title = get_the_title();
                $featured = get_post_meta(get_the_ID(), '_featured', true);
                $featured_class = $featured ? ' mjb-featured' : '';

                $is_new = class_exists('MJB_Job_Ops') && MJB_Job_Ops::is_new(get_the_ID());
                $is_filled = class_exists('MJB_Job_Ops') && MJB_Job_Ops::is_filled(get_the_ID());
                if ($is_filled) {
                    $featured_class .= ' mjb-filled';
                }

                echo '<article class="mjb-job-card mjb-job-item' . esc_attr($featured_class) . '" data-job-id="' . esc_attr((string) get_the_ID()) . '">';
                echo '<a class="mjb-job-card__stretched-link" href="' . esc_url($permalink) . '" aria-label="' . esc_attr(sprintf(__('View job: %s', 'modern-job-board'), $job_title)) . '"></a>';
                echo '<div class="mjb-job-card__badges">';
                if ($featured) {
                    echo '<span class="mjb-badge mjb-badge--featured">' . esc_html__('Featured', 'modern-job-board') . '</span>';
                }
                if ($is_new) {
                    echo '<span class="mjb-badge mjb-badge--new">' . esc_html__('New', 'modern-job-board') . '</span>';
                }
                if ($is_filled) {
                    echo '<span class="mjb-badge mjb-badge--filled">' . esc_html__('Filled', 'modern-job-board') . '</span>';
                }
                echo '</div>';
                echo '<h3 class="mjb-job-card__title">' . esc_html($job_title) . '</h3>';
                self::render_job_card_company(get_the_ID());

                echo '<div class="mjb-job-meta mjb-job-card__meta">';

                $job_type = get_the_term_list(get_the_ID(), 'job_type', '', ', ');
                self::render_meta_pill('clock', $job_type);

                $job_location = MJB_Location::render_job_location_term_list(get_the_ID());
                self::render_meta_pill('map-pin', $job_location);

                $job_category = get_the_term_list(get_the_ID(), 'job_category', '', ', ');
                self::render_meta_pill('tag', $job_category);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_job_date_pills_html().
                echo self::get_job_date_pills_html(get_the_ID());

                echo '</div>';
                echo '</article>';

                /**
                 * After each job card in the public list (1-based index).
                 *
                 * @param int      $job_id
                 * @param int      $card_index
                 * @param WP_Query $jobs
                 */
                do_action('mjb_after_job_card', get_the_ID(), $card_index, $jobs);
            }
            echo '</div>'; // .mjb-job-list
        } else {
            $empty_html = '<p>' . esc_html__('No jobs found.', 'modern-job-board') . '</p>';
            /**
             * Filter empty job list HTML (e.g. backfill CTA).
             *
             * @param string   $empty_html
             * @param WP_Query $jobs
             */
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Callers escape; backfill returns escaped HTML.
            echo apply_filters('mjb_job_list_empty_html', $empty_html, $jobs);
        }
    }

    /**
     * Render the company name below a listing card title.
     *
     * @param int $post_id
     */
    public static function render_job_card_company($post_id)
    {
        $post_id = (int) $post_id;
        $company_name = get_post_meta($post_id, '_company_name', true);
        if ($company_name === '') {
            return;
        }

        $company_id = (int) get_post_meta($post_id, '_company_id', true);
        $company_url = class_exists('MJB_Search')
            ? MJB_Search::get_company_jobs_url_for_listing($post_id)
            : '';

        $classes = 'mjb-job-card__company';
        if ($company_url !== '') {
            $classes .= ' mjb-job-card__company--link';
        }

        if ($company_url !== '') {
            echo '<a class="' . esc_attr($classes) . '" data-job-id="' . esc_attr((string) $post_id) . '"';
        } else {
            echo '<span class="' . esc_attr($classes) . '" data-job-id="' . esc_attr((string) $post_id) . '"';
        }
        if ($company_id > 0) {
            echo ' data-mjb-company-id="' . esc_attr((string) $company_id) . '"';
        }
        if ($company_url !== '') {
            echo ' href="' . esc_url($company_url) . '" title="' . esc_attr__('Company details', 'modern-job-board') . '">';
        } else {
            echo '>';
        }

        echo '<span class="mjb-job-card__company-name">' . esc_html($company_name) . '</span>';
        echo '<span class="mjb-job-card__company-info" aria-hidden="true">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
        echo MJB_Icons::render('info', 14);
        echo '</span>';
        echo $company_url !== '' ? '</a>' : '</span>';
    }

    /**
     * Count published jobs linked to a company.
     *
     * @param int $company_id
     * @return int
     */
    public static function get_company_job_count($company_id)
    {
        $company_id = intval($company_id);
        if ($company_id < 1) {
            return 0;
        }

        $query = new WP_Query(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
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
     * Human-readable job count label for company cards/profiles.
     *
     * @param int $job_count
     * @return string
     */
    public static function format_company_job_count_label($job_count)
    {
        $job_count = max(0, intval($job_count));

        return sprintf(
            /* translators: %d: number of jobs */
            _n('%d job', '%d jobs', $job_count, 'modern-job-board'),
            $job_count
        );
    }

    /**
     * Build initials from a company name for avatar fallbacks.
     *
     * @param string $name
     * @return string
     */
    public static function get_company_initials($name)
    {
        $name = trim(wp_strip_all_tags((string) $name));
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name);
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Render a company logo or initials avatar.
     *
     * @param int    $company_id
     * @param string $size       Optional size modifier: md or lg.
     */
    public static function render_company_avatar($company_id, $size = 'md')
    {
        $company_id = intval($company_id);
        $title = get_the_title($company_id);
        $size_class = $size === 'lg' ? ' mjb-company-avatar--lg' : '';
        $has_logo = has_post_thumbnail($company_id);

        echo '<div class="mjb-company-avatar' . esc_attr($size_class) . '">';

        if ($has_logo) {
            echo get_the_post_thumbnail($company_id, 'thumbnail', array(
                'class' => 'mjb-company-avatar__image',
                'alt' => '',
            ));
        } else {
            echo '<span class="mjb-company-avatar__initials" aria-hidden="true">';
            echo esc_html(self::get_company_initials($title));
            echo '</span>';
        }

        echo '</div>';
    }

    /**
     * Build a trimmed excerpt for a company profile card.
     *
     * @param int $company_id
     * @return string
     */
    public static function get_company_card_excerpt($company_id)
    {
        $company_id = intval($company_id);
        if ($company_id < 1) {
            return '';
        }

        $excerpt = get_the_excerpt($company_id);
        if ($excerpt !== '') {
            return trim($excerpt);
        }

        $content = get_post_field('post_content', $company_id);
        if ($content === '') {
            return '';
        }

        return trim(wp_trim_words(wp_strip_all_tags($content), 24, '…'));
    }

    /**
     * Render company directory cards.
     *
     * @param WP_Query $companies
     */
    public static function render_company_loop($companies)
    {
        if (!$companies->have_posts()) {
            echo '<p class="mjb-empty-state">' . esc_html__('No companies found.', 'modern-job-board') . '</p>';
            return;
        }

        $rendered = 0;
        $buffer = '';

        while ($companies->have_posts()) {
            $companies->the_post();
            $company_id = get_the_ID();
            $job_count = self::get_company_job_count($company_id);

            // Hide employers with no published listings (also enforced at archive query level).
            if ($job_count < 1) {
                continue;
            }

            $permalink = get_permalink();
            $title = get_the_title();
            $excerpt = self::get_company_card_excerpt($company_id);
            $jobs_url = MJB_Job_Routes::build_url(array(
                'search_company' => get_post_field('post_name', $company_id),
            ));

            ob_start();

            // Feature-card style: icon/avatar on top, then title + body (matches homepage .feature-card).
            echo '<article class="mjb-company-card">';
            echo '<a class="mjb-company-card__stretched-link" href="' . esc_url($permalink) . '" aria-label="' . esc_attr(sprintf(__('View company: %s', 'modern-job-board'), $title)) . '"></a>';
            self::render_company_avatar($company_id);
            echo '<h2 class="mjb-company-card__title">' . esc_html($title) . '</h2>';
            echo '<p class="mjb-company-card__meta">';
            echo esc_html(self::format_company_job_count_label($job_count));
            echo '</p>';

            if ($excerpt !== '') {
                echo '<p class="mjb-company-card__excerpt">' . esc_html($excerpt) . '</p>';
            }

            echo '<a class="mjb-company-card__jobs-link" href="' . esc_url($jobs_url) . '">';
            echo esc_html__('View open jobs', 'modern-job-board');
            echo '</a>';

            echo '</article>';

            $buffer .= ob_get_clean();
            $rendered++;
        }

        if ($rendered < 1) {
            echo '<p class="mjb-empty-state">' . esc_html__('No companies found.', 'modern-job-board') . '</p>';
            return;
        }

        echo '<div class="mjb-company-list">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card HTML is escaped as it is built.
        echo $buffer;
        echo '</div>';
    }

    /**
     * Render pagination for a post-type archive query.
     *
     * Matches the Jobs board control styling (btn / btn-sm / mjb-page-link).
     *
     * @param WP_Query $query
     * @param string   $base_url
     */
    public static function render_archive_pagination($query, $base_url = '')
    {
        if ($query->max_num_pages <= 1) {
            return;
        }

        $current_page = max(1, intval(get_query_var('paged')));
        if ($current_page < 1) {
            $current_page = 1;
        }

        $base_url = $base_url !== '' ? $base_url : get_post_type_archive_link($query->get('post_type'));
        if (!$base_url) {
            return;
        }

        $base_url = trailingslashit($base_url);
        $total = intval($query->max_num_pages);

        echo '<nav class="mjb-pagination" aria-label="' . esc_attr__('Archive pagination', 'modern-job-board') . '" data-current-page="' . esc_attr($current_page) . '">';

        if ($current_page > 1) {
            self::render_archive_pagination_link(
                self::get_archive_page_url($base_url, $current_page - 1),
                '',
                'mjb-page-prev',
                false,
                __('Previous page', 'modern-job-board'),
                'chevron-left'
            );
        }

        for ($page = 1; $page <= $total; $page++) {
            self::render_archive_pagination_link(
                self::get_archive_page_url($base_url, $page),
                (string) $page,
                'mjb-page-number',
                $page === $current_page
            );
        }

        if ($current_page < $total) {
            self::render_archive_pagination_link(
                self::get_archive_page_url($base_url, $current_page + 1),
                '',
                'mjb-page-next',
                false,
                __('Next page', 'modern-job-board'),
                'chevron-right'
            );
        }

        echo '</nav>';
    }

    /**
     * Build a pretty archive page URL.
     *
     * @param string $base_url Trailing-slashed archive base.
     * @param int    $page     1-based page number.
     * @return string
     */
    private static function get_archive_page_url($base_url, $page)
    {
        $page = max(1, intval($page));
        if ($page <= 1) {
            return $base_url;
        }

        return $base_url . 'page/' . $page . '/';
    }

    /**
     * Render a single archive pagination control (link styled like Jobs buttons).
     *
     * @param string $url
     * @param string $label
     * @param string $modifier_class
     * @param bool   $active
     * @param string $aria_label
     * @param string $icon
     */
    private static function render_archive_pagination_link($url, $label, $modifier_class, $active = false, $aria_label = '', $icon = '')
    {
        $classes = array('btn', 'btn-sm', 'mjb-page-link', $modifier_class);
        $classes[] = $active ? 'btn-primary' : 'btn-outline';

        if ($active) {
            $classes[] = 'is-active';
        }

        $accessible_label = $aria_label !== '' ? $aria_label : $label;

        if ($active) {
            echo '<span class="' . esc_attr(implode(' ', $classes)) . '" aria-current="page" aria-label="' . esc_attr($accessible_label) . '">';
        } else {
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr(implode(' ', $classes)) . '" aria-label="' . esc_attr($accessible_label) . '">';
        }

        if ($icon !== '') {
            echo '<span class="mjb-page-link__icon" aria-hidden="true">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render($icon, 16);
            echo '</span>';
        } else {
            echo '<span aria-hidden="true">' . esc_html($label) . '</span>';
        }

        echo $active ? '</span>' : '</a>';
    }

    /**
     * Build a trimmed excerpt for job listing cards.
     *
     * @return string
     */
    public static function get_job_card_excerpt()
    {
        // Never call get_the_excerpt()/get_the_content() here: empty excerpts run
        // through wp_trim_excerpt → apply_filters('the_content'), which re-enters
        // single-job content filters (related jobs / map) and can OOM.
        $post = get_post();
        if (!$post) {
            return '';
        }

        $raw = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
        return trim(wp_trim_words(wp_strip_all_tags((string) $raw), 30, '…'));
    }

    /**
     * Escape descriptive text and wrap search keyword hits in pill-style marks.
     *
     * @param string $text     Plain text (already stripped of HTML).
     * @param string $keywords Free-text search keywords from the filter.
     * @return string Safe HTML.
     */
    public static function highlight_search_terms($text, $keywords)
    {
        $text = (string) $text;
        $keywords = trim((string) $keywords);

        if ($text === '') {
            return '';
        }

        if ($keywords === '') {
            return esc_html($text);
        }

        $parts = preg_split('/\s+/u', $keywords, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || empty($parts)) {
            return esc_html($text);
        }

        $terms = array();
        foreach ($parts as $part) {
            $part = trim($part);
            // Skip tiny noise tokens (e.g. "a", "of").
            $len = function_exists('mb_strlen') ? mb_strlen($part) : strlen($part);
            if ($part === '' || $len < 2) {
                continue;
            }
            $terms[$part] = true;
        }

        if (empty($terms)) {
            return esc_html($text);
        }

        $terms = array_keys($terms);
        usort(
            $terms,
            static function ($a, $b) {
                $la = function_exists('mb_strlen') ? mb_strlen($a) : strlen($a);
                $lb = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
                return $lb - $la;
            }
        );

        $pattern = implode('|', array_map(static function ($term) {
            return preg_quote($term, '/');
        }, $terms));

        $segments = preg_split('/(' . $pattern . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($segments)) {
            return esc_html($text);
        }

        $html = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (preg_match('/^(?:' . $pattern . ')$/iu', $segment)) {
                $html .= '<mark class="mjb-search-hit">' . esc_html($segment) . '</mark>';
            } else {
                $html .= esc_html($segment);
            }
        }

        return $html;
    }

    /**
     * Render a meta pill with a line icon.
     *
     * @param string $icon    Icon key.
     * @param string $content Pill content (may contain safe HTML from term lists).
     */
    public static function render_meta_pill($icon, $content)
    {
        if ($content === '' || $content === null) {
            return;
        }

        echo '<span class="mjb-meta-pill">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
        echo MJB_Icons::render($icon);
        echo wp_kses_post($content);
        echo '</span>';
    }

    /**
     * Render a clickable meta pill.
     *
     * @param string $icon    Icon key.
     * @param string $url     Destination URL.
     * @param string $content Pill label.
     */
    public static function render_meta_pill_link($icon, $url, $content)
    {
        if ($content === '' || $content === null || $url === '') {
            return;
        }

        echo '<a class="mjb-meta-pill mjb-meta-pill--link" href="' . esc_url($url) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
        echo MJB_Icons::render($icon);
        echo wp_kses_post($content);
        echo '</a>';
    }

    /**
     * Render a compact content hero for plugin pages.
     *
     * @param string $title
     * @param string $intro
     */
    public static function render_content_hero($title, $intro = '')
    {
        ?>
        <section class="mjb-content-hero alignfull">
            <div class="mjb-content-hero__inner mjb-container">
                <h1 class="mjb-content-hero__title"><?php echo esc_html($title); ?></h1>
                <?php if ($intro !== '') : ?>
                    <p class="mjb-content-hero__intro"><?php echo wp_kses($intro, array(
                        'a' => array(
                            'href' => true,
                            'class' => true,
                            'rel' => true,
                        ),
                    )); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    /**
     * Hero line: “Posted by {company}”, with company linked to its jobs list when possible.
     *
     * @param int $job_id Job listing ID.
     * @return string Escaped HTML, or empty string.
     */
    public static function get_job_posted_by_html($job_id)
    {
        $job_id = intval($job_id);
        $company_name = get_post_meta($job_id, '_company_name', true);
        if ($company_name === '') {
            $company_id = intval(get_post_meta($job_id, '_company_id', true));
            if ($company_id) {
                $company_name = get_the_title($company_id);
            }
        }
        if ($company_name === '') {
            return '';
        }

        $url = class_exists('MJB_Search') ? MJB_Search::get_company_jobs_url_for_listing($job_id) : '';
        $name_html = $url !== ''
            ? '<a class="mjb-content-hero__company" href="' . esc_url($url) . '">' . esc_html($company_name) . '</a>'
            : esc_html($company_name);

        return sprintf(
            /* translators: %s: company name (may include an HTML link) */
            __('Posted by %s', 'modern-job-board'),
            $name_html
        );
    }

    /**
     * Posted / expiry meta pills for job cards and the single-job header.
     *
     * @param int $job_id Job listing ID.
     * @return string HTML.
     */
    public static function get_job_date_pills_html($job_id)
    {
        $job_id = intval($job_id);
        $html = '<span class="mjb-meta-pill">';
        $html .= MJB_Icons::render('calendar-check-2');
        $html .= '<strong>' . esc_html__('Posted:', 'modern-job-board') . '</strong> ';
        $html .= esc_html(get_the_date('', $job_id));
        $html .= '</span>';

        $expires = get_post_meta($job_id, '_job_expires', true);
        if ($expires) {
            $expires_ts = strtotime((string) $expires);
            if ($expires_ts) {
                $format = get_option('date_format');
                if (!is_string($format) || $format === '') {
                    $format = 'F j, Y';
                }
                $expiry_label = function_exists('date_i18n')
                    ? date_i18n($format, $expires_ts)
                    : date($format, $expires_ts);
                $html .= '<span class="mjb-meta-pill">';
                $html .= MJB_Icons::render('calendar-x-2');
                $html .= '<strong>' . esc_html__('Expiry:', 'modern-job-board') . '</strong> ';
                $html .= esc_html($expiry_label);
                $html .= '</span>';
            }
        }

        return $html;
    }

    /**
     * Whether the jobs board home promo cards should render.
     *
     * @param array $filter_params
     * @return bool
     */
    public static function should_show_audience_cards($filter_params)
    {
        foreach (array('search_keywords', 'search_location', 'search_category', 'search_type', 'search_company') as $key) {
            if (!empty($filter_params[$key])) {
                return false;
            }
        }

        $page = isset($filter_params['page']) ? intval($filter_params['page']) : 0;

        return $page < 2;
    }

    /**
     * Render job seeker and employer promo cards below the jobs hero.
     *
     * @param array|null $filter_params
     */
    public static function render_audience_cards($filter_params = null)
    {
        if ($filter_params === null) {
            $filter_params = MJB_Search::get_request_filter_params();
        }

        if (!self::should_show_audience_cards($filter_params)) {
            return;
        }

        $candidate_dashboard_url = MJB_Page_Resolver::get_page_url(
            'mjb_candidate_dashboard',
            'mjb_candidate_dashboard_page_id',
            array(),
            '/jobs/candidate-dashboard/'
        );
        $candidate_login_url = MJB_Page_Resolver::get_page_url(
            'mjb_candidate_login',
            'mjb_candidate_login_page_id',
            array(),
            '/jobs/candidate-login/'
        );
        // #31: logged-out "Submit CV" goes to login (then dashboard).
        $submit_cv_url = $candidate_dashboard_url;
        if (!is_user_logged_in()) {
            $submit_cv_url = add_query_arg(
                'redirect_to',
                rawurlencode($candidate_dashboard_url),
                $candidate_login_url
            );
        }
        $employer_registration_url = MJB_Page_Resolver::get_page_url(
            'mjb_employer_registration',
            'mjb_employer_registration_page_id',
            array(),
            '/jobs/recruiter-registration/'
        );
        $post_job_url = MJB_Page_Resolver::get_page_url(
            'mjb_job_form',
            'mjb_job_form_page_id',
            array(),
            '/jobs/post-a-job/'
        );
        if (!is_user_logged_in()) {
            $employer_login = MJB_Page_Resolver::get_page_url(
                'mjb_employer_login',
                'mjb_employer_login_page_id',
                array(),
                '/jobs/recruiter-login/'
            );
            $post_job_url = add_query_arg('redirect_to', rawurlencode($post_job_url), $employer_login);
        }
        ?>
        <section class="mjb-audience-cards" aria-label="<?php esc_attr_e('Get started', 'modern-job-board'); ?>">
            <div class="mjb-audience-cards__grid">
                    <article class="mjb-audience-card mjb-audience-card--seekers">
                        <header class="mjb-audience-card__header">
                            <span class="mjb-audience-card__icon" aria-hidden="true">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                echo MJB_Icons::render('user', 24);
                                ?>
                            </span>
                            <h2 class="mjb-audience-card__title"><?php esc_html_e('Job seekers', 'modern-job-board'); ?></h2>
                        </header>
                        <p class="mjb-audience-card__intro">
                            <?php esc_html_e('Create a profile and get hired by top recruiters.', 'modern-job-board'); ?>
                        </p>
                        <div class="mjb-audience-card__actions">
                            <a href="<?php echo esc_url($submit_cv_url); ?>" class="btn btn-primary btn-sm">
                                <?php esc_html_e('Submit CV', 'modern-job-board'); ?>
                            </a>
                            <a href="#mjb-jobs-list" class="btn btn-outline btn-sm">
                                <?php esc_html_e('Find jobs', 'modern-job-board'); ?>
                            </a>
                        </div>
                    </article>

                    <article class="mjb-audience-card mjb-audience-card--employers">
                        <header class="mjb-audience-card__header">
                            <span class="mjb-audience-card__icon" aria-hidden="true">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                echo MJB_Icons::render('briefcase', 24);
                                ?>
                            </span>
                            <h2 class="mjb-audience-card__title"><?php esc_html_e('Recruiters', 'modern-job-board'); ?></h2>
                        </header>
                        <p class="mjb-audience-card__intro">
                            <?php esc_html_e('Post open jobs and hire top talent.', 'modern-job-board'); ?>
                        </p>
                        <div class="mjb-audience-card__actions">
                            <a href="<?php echo esc_url($post_job_url); ?>" class="btn btn-primary btn-sm">
                                <?php esc_html_e('Post a job', 'modern-job-board'); ?>
                            </a>
                            <a href="<?php echo esc_url($employer_registration_url); ?>" class="btn btn-outline btn-sm">
                                <?php esc_html_e('Sign up', 'modern-job-board'); ?>
                            </a>
                        </div>
                    </article>
            </div>
        </section>
        <?php
    }

    /**
     * One job-card skeleton matching listing card geometry.
     *
     * @return string
     */
    public static function get_jobs_skeleton_card_html()
    {
        return '<article class="mjb-job-card mjb-job-item mjb-skeleton-card">'
            . '<div class="mjb-job-card__badges"><span class="mjb-skeleton mjb-skeleton--badge"></span></div>'
            . '<div class="mjb-skeleton mjb-skeleton--title"></div>'
            . '<div class="mjb-skeleton mjb-skeleton--company"></div>'
            . '<div class="mjb-job-meta mjb-job-card__meta">'
            . '<span class="mjb-skeleton mjb-skeleton--pill"></span>'
            . '<span class="mjb-skeleton mjb-skeleton--pill"></span>'
            . '<span class="mjb-skeleton mjb-skeleton--pill"></span>'
            . '<span class="mjb-skeleton mjb-skeleton--pill"></span>'
            . '<span class="mjb-skeleton mjb-skeleton--pill"></span>'
            . '</div></article>';
    }

    /**
     * Skeleton job list used while AJAX filters run.
     *
     * @param int $count Card placeholders (clamped 1–8).
     * @return string
     */
    public static function get_jobs_skeleton_html($count = 6)
    {
        $count = max(1, min(8, intval($count)));
        $html = '<div class="mjb-job-list mjb-skeleton-list" aria-hidden="true">';
        for ($i = 0; $i < $count; $i++) {
            $html .= self::get_jobs_skeleton_card_html();
        }
        $html .= '</div>';
        $html .= '<nav class="mjb-pagination mjb-skeleton-pagination" aria-hidden="true">';
        $html .= '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        $html .= '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        $html .= '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        $html .= '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        $html .= '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        $html .= '</nav>';
        return $html;
    }

    /**
     * Accessible live region + card template for AJAX job loading.
     */
    public static function render_jobs_loader()
    {
        echo '<div id="mjb-jobs-live" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>';
        echo '<template id="mjb-jobs-skeleton-card">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static skeleton markup from get_jobs_skeleton_card_html().
        echo self::get_jobs_skeleton_card_html();
        echo '</template>';
        echo '<template id="mjb-jobs-skeleton-pagination">';
        echo '<nav class="mjb-pagination mjb-skeleton-pagination" aria-hidden="true">';
        echo '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        echo '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        echo '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        echo '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        echo '<span class="mjb-skeleton mjb-skeleton--page"></span>';
        echo '</nav>';
        echo '</template>';
    }

    /**
     * Render the shared job search form.
     *
     * @param array|null $filter_params
     * @param bool       $show_heading
     * @return string
     */
    public static function render_job_search_form($filter_params = null, $show_heading = false)
    {
        if ($filter_params === null) {
            $filter_params = MJB_Search::get_request_filter_params();
        }

        $total_jobs = MJB_Search::count_published_jobs();
        $total_label = number_format_i18n($total_jobs);
        $location_label = MJB_Search::get_term_label('job_location', $filter_params['search_location']);
        $category_label = MJB_Search::get_term_label('job_category', $filter_params['search_category']);
        $type_label = MJB_Search::get_term_label('job_type', $filter_params['search_type']);
        $company_locked = !empty($filter_params['search_company']);

        ob_start();
        ?>
        <form class="mjb-job-filter mjb-search-panel" method="GET" action="<?php echo esc_url(MJB_Job_Routes::build_url()); ?>" data-mjb-job-filter>
            <?php if ($show_heading) : ?>
                <h3 class="mjb-filter-heading">
                    <?php
                    if (function_exists('is_singular') && is_singular('job_listing')) {
                        esc_html_e('Find other jobs', 'modern-job-board');
                    } else {
                        echo esc_html(sprintf(
                            /* translators: %s: formatted job count, e.g. 4,793 */
                            __('Filter %s Jobs', 'modern-job-board'),
                            $total_label
                        ));
                    }
                    ?>
                </h3>
                <?php if (function_exists('is_singular') && is_singular('job_listing')) : ?>
                    <p class="mjb-filter-heading-hint"><?php esc_html_e('Search the full board — results open on the jobs page.', 'modern-job-board'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <div class="mjb-filter-row">
                <?php if ($company_locked) : ?>
                    <input type="hidden" name="search_company" value="<?php echo esc_attr($filter_params['search_company']); ?>">
                <?php endif; ?>
                <?php
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Autocomplete helper escapes all HTML.
                echo MJB_Search::render_autocomplete_field(array(
                    'name' => 'search_keywords',
                    'type' => 'keywords',
                    'value' => $filter_params['search_keywords'],
                    'label' => $filter_params['search_keywords'],
                    'placeholder' => __('Keywords...', 'modern-job-board'),
                    'free_text' => true,
                ));
                echo MJB_Search::render_autocomplete_field(array(
                    'name' => 'search_location',
                    'type' => 'location',
                    'value' => $filter_params['search_location'],
                    'label' => $location_label,
                    'placeholder' => __('All Locations', 'modern-job-board'),
                ));
                echo MJB_Search::render_autocomplete_field(array(
                    'name' => 'search_category',
                    'type' => 'category',
                    'value' => $filter_params['search_category'],
                    'label' => $category_label,
                    'placeholder' => __('All Categories', 'modern-job-board'),
                ));
                echo MJB_Search::render_autocomplete_field(array(
                    'name' => 'search_type',
                    'type' => 'type',
                    'value' => $filter_params['search_type'],
                    'label' => $type_label,
                    'placeholder' => __('All Job Types', 'modern-job-board'),
                ));
                if (!$company_locked) {
                    echo MJB_Search::render_autocomplete_field(array(
                        'name' => 'search_company',
                        'type' => 'company',
                        'value' => $filter_params['search_company'],
                        'label' => MJB_Search::get_company_label($filter_params['search_company']),
                        'placeholder' => __('All Companies', 'modern-job-board'),
                    ));
                }
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
                <div class="mjb-filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm mjb-button mjb-button--search"><?php esc_html_e('Search', 'modern-job-board'); ?></button>
                    <button type="button" class="btn btn-outline btn-sm mjb-button mjb-button--reset" data-mjb-filter-reset><?php esc_html_e('Reset', 'modern-job-board'); ?></button>
                </div>
            </div>
        </form>
        <?php
        /**
         * After jobs filter form (e.g. subscribe-to-search).
         *
         * @param array $filter_params
         */
        do_action('mjb_after_job_search_form', $filter_params);

        return ob_get_clean();
    }

    /**
     * Render pagination controls for a job query.
     *
     * @param WP_Query $query
     * @param array    $filter_params
     */
    public static function render_pagination($query, $filter_params = array())
    {
        if ($query->max_num_pages <= 1) {
            return;
        }

        $current_page = max(1, intval($filter_params['page'] ?? 0));
        if ($current_page < 1) {
            $current_page = max(1, intval($query->get('paged')));
        }

        echo '<nav class="mjb-pagination" aria-label="' . esc_attr__('Job listings pagination', 'modern-job-board') . '" data-current-page="' . esc_attr($current_page) . '">';

        if ($current_page > 1) {
            self::render_pagination_button(
                '',
                $current_page - 1,
                $filter_params,
                'mjb-page-prev',
                false,
                false,
                __('Previous page', 'modern-job-board'),
                'chevron-left'
            );
        }

        for ($page = 1; $page <= $query->max_num_pages; $page++) {
            self::render_pagination_button(
                (string) $page,
                $page,
                $filter_params,
                'mjb-page-number',
                false,
                $page === $current_page
            );
        }

        if ($current_page < $query->max_num_pages) {
            self::render_pagination_button(
                '',
                $current_page + 1,
                $filter_params,
                'mjb-page-next',
                false,
                false,
                __('Next page', 'modern-job-board'),
                'chevron-right'
            );
        }

        echo '</nav>';
    }

    /**
     * Render a single pagination button.
     *
     * @param string $label
     * @param int    $page
     * @param array  $filter_params
     * @param string $modifier_class
     * @param bool   $disabled
     * @param bool   $active
     * @param string $aria_label
     * @param string $icon
     */
    private static function render_pagination_button($label, $page, $filter_params, $modifier_class, $disabled = false, $active = false, $aria_label = '', $icon = '')
    {
        $classes = array('btn', 'btn-sm', 'mjb-page-link', $modifier_class);
        $classes[] = $active ? 'btn-primary' : 'btn-outline';

        if ($active) {
            $classes[] = 'is-active';
        }

        if ($disabled) {
            $classes[] = 'is-disabled';
        }

        $page_params = $filter_params;
        if ($page > 1) {
            $page_params['page'] = $page;
        } else {
            unset($page_params['page']);
        }

        $page_url = MJB_Job_Routes::build_url($page_params);
        $accessible_label = $aria_label !== '' ? $aria_label : $label;

        echo '<button type="button" class="' . esc_attr(implode(' ', $classes)) . '"';
        echo ' data-page="' . esc_attr($page) . '"';
        echo ' data-url="' . esc_url($page_url) . '"';
        echo ' aria-label="' . esc_attr($accessible_label) . '"';

        if ($disabled) {
            echo ' disabled';
        }

        if ($active) {
            echo ' aria-current="page"';
        }

        echo '>';

        if ($icon !== '') {
            echo '<span class="mjb-page-link__icon" aria-hidden="true">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render($icon, 16);
            echo '</span>';
        } else {
            echo '<span aria-hidden="true">' . esc_html($label) . '</span>';
        }

        echo '</button>';
    }

    /**
     * Build a job form URL with optional query arguments.
     * Prefer pretty /edit/{id}/ when callers pass action=edit + job_id.
     *
     * @param array $query_args
     * @return string
     */
    public static function get_job_form_page_url($query_args = array())
    {
        $query_args = is_array($query_args) ? $query_args : array();
        if (
            class_exists('MJB_Pretty_Urls')
            && isset($query_args['action'], $query_args['job_id'])
            && $query_args['action'] === 'edit'
        ) {
            return MJB_Pretty_Urls::job_edit_url((int) $query_args['job_id']);
        }

        return MJB_Page_Resolver::get_page_url('mjb_job_form', self::JOB_FORM_PAGE_OPTION, $query_args, '/jobs/post-a-job/');
    }

    /**
     * Output Job Submission Form.
     */
    public function output_job_form($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . sprintf(
                __('You must be <a href="%s">logged in as a recruiter</a> to post jobs.', 'modern-job-board'),
                esc_url(wp_login_url(get_permalink()))
            ) . '</p>';
        }

        $user = wp_get_current_user();
        if (class_exists('MJB_Board_Polish')) {
            $can_post = MJB_Board_Polish::user_can_post_job(get_current_user_id());
            if (!$can_post) {
                $mode = get_option(MJB_Board_Polish::OPTION_WHO_CAN_POST, 'employer');
                if ($mode === 'admin') {
                    return '<p>' . esc_html__('Only administrators can post jobs on this board.', 'modern-job-board') . '</p>';
                }
                return '<p>' . esc_html__('This form is for recruiter accounts only.', 'modern-job-board') . '</p>';
            }
        } elseif (!in_array('employer', (array) $user->roles, true) && !user_can($user, 'manage_options')) {
            return '<p>' . __('This form is for recruiter accounts only.', 'modern-job-board') . '</p>';
        }

        ob_start();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in MJB_Notices::render().
        echo MJB_Notices::render();

        // Check for Edit Actions
        $job_id = 0;
        $job_title = '';
        $job_description = '';
        $company_name = '';

        $edit_job_id = class_exists('MJB_Pretty_Urls')
            ? MJB_Pretty_Urls::request_edit_job_id()
            : 0;
        if ($edit_job_id <= 0 && isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['job_id'])) {
            $edit_job_id = (int) $_GET['job_id'];
        }

        if ($edit_job_id > 0) {
            if (!is_user_logged_in()) {
                echo '<p>' . esc_html__('You must be logged in to edit a job.', 'modern-job-board') . '</p>';
                return ob_get_clean();
            }

            $job_id = $edit_job_id;
            $job = get_post($job_id);

            // Verify ownership
            if (!$job || $job->post_type !== 'job_listing' || intval($job->post_author) !== get_current_user_id()) {
                echo '<p>' . esc_html__('Invalid job or permission denied.', 'modern-job-board') . '</p>';
                return ob_get_clean();
            }

            $job_title = $job->post_title;
            $job_description = $job->post_content;
            $selected_company_id = get_post_meta($job_id, '_company_id', true);
            // Fallback to text name if no ID
            $company_name = get_post_meta($job_id, '_company_name', true);
        }
        
        $user_companies = $this->get_user_companies(get_current_user_id());
        $selected_company_id = isset($selected_company_id) ? $selected_company_id : '';
        // If editing and we only have text name (legacy), we might not match a company ID. That's fine.



        if (isset($_POST['mjb_submit_job']) && isset($_POST['mjb_job_nonce']) && wp_verify_nonce($_POST['mjb_job_nonce'], 'mjb_submit_job')) {
            $this->handle_job_submission($job_id);
            // If submitted, get updated values? Or redirect? For simplicity, we handle submission logic below.
        }
        ?>
        <?php do_action('mjb_before_job_submission_form'); ?>
        <form method="post" class="mjb-job-form" enctype="multipart/form-data" novalidate>
            <?php do_action('mjb_job_submission_form_start'); ?>
            <?php wp_nonce_field('mjb_submit_job', 'mjb_job_nonce'); ?>
            <?php if ($job_id): ?>
                <input type="hidden" name="job_id" value="<?php echo esc_attr($job_id); ?>">
            <?php endif; ?>

            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
            echo self::required_fields_note();
            ?>

            <p>
                <label for="job_title"><?php esc_html_e('Job Title', 'modern-job-board'); ?><?php echo self::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <input type="text" name="job_title" id="job_title" value="<?php echo esc_attr($job_title); ?>" required aria-required="true">
            </p>
            <p>
                <label for="job_description"><?php esc_html_e('Description', 'modern-job-board'); ?><?php echo self::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <textarea name="job_description" id="job_description"
                    required aria-required="true"><?php echo esc_textarea($job_description); ?></textarea>
            </p>
            <p>
                <label for="company_selection"><?php esc_html_e('Company', 'modern-job-board'); ?><?php echo self::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <select name="company_selection" id="company_selection" required aria-required="true" onchange="toggleCompanyInput()">
                    <option value="new"><?php esc_html_e('Create New Company', 'modern-job-board'); ?></option>
                    <?php foreach ($user_companies as $company) : ?>
                        <option value="<?php echo esc_attr($company->ID); ?>" <?php selected($selected_company_id, $company->ID); ?>>
                            <?php echo esc_html($company->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p id="new-company-field" class="<?php echo $selected_company_id ? 'mjb-is-hidden' : ''; ?>">
                <label for="new_company_name"><?php esc_html_e('New Company Name', 'modern-job-board'); ?><?php echo self::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <input type="text" name="new_company_name" id="new_company_name" value="<?php echo empty($selected_company_id) ? esc_attr($company_name) : ''; ?>" <?php echo empty($selected_company_id) ? 'required aria-required="true"' : ''; ?>>
            </p>
            
            <!-- Application Method -->
            <p>
                <label><?php esc_html_e('Application Method', 'modern-job-board'); ?></label><br>
                <label>
                    <input type="radio" name="application_method" value="internal" checked onclick="toggleApplicationMethod()"> 
                    <?php esc_html_e('Email (Internal Form)', 'modern-job-board'); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="application_method" value="external" onclick="toggleApplicationMethod()"> 
                    <?php esc_html_e('External URL', 'modern-job-board'); ?>
                </label>
            </p>

            <p id="app-email-field">
                <label for="application_email"><?php esc_html_e('Notification Email', 'modern-job-board'); ?><?php echo self::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <input type="email" name="application_email" id="application_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required aria-required="true">
            </p>

            <p id="app-url-field" class="mjb-is-hidden">
                <label for="application_url"><?php esc_html_e('External Application URL', 'modern-job-board'); ?><?php echo self::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                <input type="url" name="application_url" id="application_url" placeholder="https://...">
            </p>

            <script>
                function toggleCompanyInput() {
                    var select = document.getElementById('company_selection');
                    var input = document.getElementById('new-company-field');
                    var nameInput = document.getElementById('new_company_name');
                    if (select.value === 'new') {
                        input.classList.remove('mjb-is-hidden');
                        nameInput.required = true;
                        nameInput.setAttribute('aria-required', 'true');
                    } else {
                        input.classList.add('mjb-is-hidden');
                        nameInput.required = false;
                        nameInput.removeAttribute('aria-required');
                    }
                }

                function toggleApplicationMethod() {
                    var method = document.querySelector('input[name="application_method"]:checked').value;
                    var emailField = document.getElementById('app-email-field');
                    var urlField = document.getElementById('app-url-field');
                    var emailInput = document.getElementById('application_email');
                    var urlInput = document.getElementById('application_url');
                    
                    if (method === 'internal') {
                        emailField.classList.remove('mjb-is-hidden');
                        urlField.classList.add('mjb-is-hidden');
                        emailInput.required = true;
                        emailInput.setAttribute('aria-required', 'true');
                        urlInput.required = false;
                        urlInput.removeAttribute('aria-required');
                    } else {
                        emailField.classList.add('mjb-is-hidden');
                        urlField.classList.remove('mjb-is-hidden');
                        emailInput.required = false;
                        emailInput.removeAttribute('aria-required');
                        urlInput.required = true;
                        urlInput.setAttribute('aria-required', 'true');
                    }
                }

                // Run on load
                window.onload = function() { 
                    toggleCompanyInput(); 
                    toggleApplicationMethod();
                };
            </script>
            
            <!-- Custom Fields -->
            <?php
            global $mjb_custom_fields;
            if (isset($mjb_custom_fields)) {
                $fields = $mjb_custom_fields->get_fields('job');
                foreach ($fields as $field) {
                    $value = $job_id ? get_post_meta($job_id, '_mjb_' . $field['key'], true) : '';
                    $is_required = !empty($field['required']);
                    echo '<p>';
                    echo '<label for="mjb_field_' . esc_attr($field['key']) . '">' . esc_html($field['label']);
                    if ($is_required) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
                        echo self::required_mark();
                    }
                    echo '</label>';
                    
                    if ($field['type'] === 'text' || $field['type'] === 'number') {
                        echo '<input type="' . esc_attr($field['type']) . '" name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '" value="' . esc_attr($value) . '"' . ($is_required ? ' required aria-required="true"' : '') . '>';
                    } elseif ($field['type'] === 'textarea') {
                        echo '<textarea name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '"' . ($is_required ? ' required aria-required="true"' : '') . '>' . esc_textarea($value) . '</textarea>';
                    } elseif ($field['type'] === 'select') {
                        echo '<select name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '"' . ($is_required ? ' required aria-required="true"' : '') . '>';
                        $options = explode(',', $field['options']);
                        foreach ($options as $opt) {
                            $opt = trim($opt);
                            echo '<option value="' . esc_attr($opt) . '" ' . selected($value, $opt, false) . '>' . esc_html($opt) . '</option>';
                        }
                        echo '</select>';
                    } elseif ($field['type'] === 'checkbox') {
                        echo '<input type="checkbox" name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '" value="1" ' . checked(1, $value, false) . ($is_required ? ' required aria-required="true"' : '') . '>';
                    }
                    echo '</p>';
                }
            }
            ?>

            <p>
                <input type="submit" name="mjb_submit_job"
                    value="<?php echo esc_attr($job_id ? __('Update Job', 'modern-job-board') : __('Submit Job', 'modern-job-board')); ?>">
            </p>
            <?php do_action('mjb_job_submission_form_end'); ?>
        </form>
        <?php do_action('mjb_after_job_submission_form'); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle Job Submission.
     */
    private function handle_job_submission($existing_job_id = 0)
    {
        $redirect_url = MJB_Page_Resolver::get_request_fallback_url(
            'mjb_job_form',
            self::JOB_FORM_PAGE_OPTION,
            '/jobs/post-a-job/'
        );

        if (!is_user_logged_in()) {
            $this->finish_job_request($redirect_url, 'error_login_required');
        }

        $title = isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : '';
        $description = isset($_POST['job_description']) ? wp_kses_post($_POST['job_description']) : '';

        if (empty($title) || empty($description)) {
            $this->finish_job_request($redirect_url, 'error_missing_fields');
        }

        $company_selection = isset($_POST['company_selection']) ? sanitize_text_field($_POST['company_selection']) : '';
        $company_id = 0;
        $company_name_text = '';

        if ($company_selection === 'new') {
            $company_name_text = isset($_POST['new_company_name']) ? sanitize_text_field($_POST['new_company_name']) : '';
            if (empty($company_name_text)) {
                $this->finish_job_request($redirect_url, 'error_invalid_company');
            }

            // Reuse only the current employer's company with the same name (never attach to someone else's).
            $company_id = MJB_Job_Importer::find_or_create_company($company_name_text, array(
                'author_id' => get_current_user_id(),
                'only_own'  => true,
            ));
            if (!$company_id) {
                $this->finish_job_request($redirect_url, 'error_invalid_company');
            }
            $company_name_text = MJB_Job_Importer::normalize_company_name($company_name_text);
        } else {
            $company_id = intval($company_selection);
            // Verify ownership
            $company_post = get_post($company_id);
            if (!$company_post || $company_post->post_type !== 'company' || intval($company_post->post_author) !== get_current_user_id()) {
                $this->finish_job_request($redirect_url, 'error_invalid_company');
            }
            $company_name_text = $company_post->post_title;
        }

        // Check if updating via HIDDEN input (overrides argument if present)
        if (isset($_POST['job_id']) && intval($_POST['job_id']) > 0) {
            $existing_job_id = intval($_POST['job_id']);
            // Re-verify ownership for security (double check)
            $job = get_post($existing_job_id);
            if (!$job || intval($job->post_author) !== get_current_user_id()) {
                $this->finish_job_request($redirect_url, 'error_permission');
            }
        }

        $is_edit = intval($existing_job_id) > 0;

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_type' => 'job_listing',
            'post_status' => 'pending',
        );

        $post_data = apply_filters('mjb_pre_job_submission_data', $post_data, $_POST);

        if ($is_edit) {
            $post_data['ID'] = $existing_job_id;
            // Preserve existing status on edit — do not demote published listings or re-trigger payment.
            $existing_job = get_post($existing_job_id);
            if ($existing_job && !empty($existing_job->post_status)) {
                $post_data['post_status'] = $existing_job->post_status;
            }
            $post_id = wp_update_post($post_data);
            $notice_code = 'success_job_updated';
        } else {
            $post_data['post_status'] = 'pending';
            $post_id = wp_insert_post($post_data);
            $notice_code = 'success_job_submitted';

            // Set Expiration Date
            $duration = get_option('mjb_listing_duration', 30);
            $expires = date('Y-m-d', strtotime("+$duration days"));
            update_post_meta($post_id, '_job_expires', $expires);
        }

        if ($post_id) {
            update_post_meta($post_id, '_company_name', $company_name_text); // Legacy/Fallback
            if ($company_id) {
                update_post_meta($post_id, '_company_id', $company_id);
            }

            // Ensure featured ordering can include frontend-submitted jobs.
            $featured_meta = get_post_meta($post_id, '_featured', true);
            if ($featured_meta === '' || $featured_meta === false) {
                update_post_meta($post_id, '_featured', 0);
            }

            if (class_exists('MJB_Job_Permalinks')) {
                MJB_Job_Permalinks::sync_geo_meta($post_id);
            }
            
            // Save Application Method
            if (isset($_POST['application_method'])) {
                update_post_meta($post_id, '_application_method', sanitize_text_field($_POST['application_method']));
            }
            if (isset($_POST['application_email'])) {
                update_post_meta($post_id, '_application_email', sanitize_email($_POST['application_email']));
            }
            if (isset($_POST['application_url'])) {
                update_post_meta($post_id, '_application_url', esc_url_raw($_POST['application_url']));
            }
            
            // Send Notification
            global $mjb_emails;
            if (isset($mjb_emails)) {
                $mjb_emails->send_new_job_notification($post_id);
            }

            // Save Custom Fields
            global $mjb_custom_fields;
            if (isset($mjb_custom_fields)) {
                $fields = $mjb_custom_fields->get_fields('job');
                foreach ($fields as $field) {
                    $key = 'mjb_field_' . $field['key'];
                    if (isset($_POST[$key])) {
                        $val = sanitize_text_field($_POST[$key]);
                        update_post_meta($post_id, '_mjb_' . $field['key'], $val);
                    } else {
                        // Checkbox unchecked
                        if ($field['type'] === 'checkbox') {
                             update_post_meta($post_id, '_mjb_' . $field['key'], 0);
                        }
                    }
                }
            }
            
            // Payment / credits only apply to new listings — never re-bill or re-credit on edit.
            $payment_required = get_option('mjb_payment_required');
            $product_id = get_option('mjb_submission_product_id');

            if (!$is_edit && $payment_required) {
                $user_id = get_current_user_id();
                $credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $credits = $credits ? intval($credits) : 0;

                // Check for Credits
                if ($credits > 0) {
                     if (!MJB_License::can_publish_job(0)) {
                         // Keep pending; do not consume credit when Free job cap blocks publish.
                         $this->finish_job_request($redirect_url, 'error_job_cap');
                     }

                     // Use Credit
                     $new_credits = $credits - 1;
                     update_user_meta($user_id, '_mjb_job_credits', $new_credits);

                     // Publish Job immediately (bypass pending_payment)
                     wp_update_post(array(
                         'ID' => $post_id,
                         'post_status' => 'publish'
                     ));

                     update_post_meta($post_id, '_mjb_credit_used', true);

                     $this->finish_job_request($redirect_url, 'success_job_credit', array(
                         'job_id' => (int) $post_id,
                     ));
                }

                // If no credits, proceed to Pay-Per-Post
                if ($product_id && function_exists('wc_get_cart_url')) {
                     $update = array('ID' => $post_id, 'post_status' => 'pending_payment');
                     wp_update_post($update);

                     $cart_url = wc_get_cart_url();
                     $redirect_url = add_query_arg(array(
                         'add-to-cart' => $product_id,
                         'mjb_job_id' => $post_id
                     ), $cart_url);

                     $this->finish_external_redirect($redirect_url);
                }
            }

            // Hook for post-submission actions
            do_action('mjb_job_submitted', $post_id);

            $this->finish_job_request($redirect_url, $notice_code, array(
                'job_id' => (int) $post_id,
            ));
        }
    }

    /**
     * Save the job form without leaving the page. Checkout still navigates.
     */
    public function ajax_save_job()
    {
        $job_id = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        $this->handle_job_submission($job_id);
    }

    /**
     * @param string               $url
     * @param string               $code
     * @param array<string, mixed> $extra
     */
    private function finish_job_request($url, $code, $extra = array())
    {
        if (wp_doing_ajax()) {
            $payload = array_merge(array(
                'code' => $code,
                'message' => class_exists('MJB_Notices') ? MJB_Notices::message($code) : '',
            ), $extra);
            if (strpos($code, 'error_') === 0) {
                wp_send_json_error($payload);
            }
            wp_send_json_success($payload);
        }
        MJB_Notices::redirect($url, $code);
    }

    /**
     * @param string $url
     */
    private function finish_external_redirect($url)
    {
        if (wp_doing_ajax()) {
            wp_send_json_success(array(
                'redirect' => $url,
            ));
        }
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Get User Companies.
     */
    private function get_user_companies($user_id)
    {
        $args = array(
            'post_type' => 'company',
            'post_status' => 'publish', // Companies should be published to be selected? Or pending allowed? let's say publish.
            'posts_per_page' => -1,
            'author' => $user_id,
        );
        $user_companies = get_posts($args);
        return $user_companies;
    }

    /**
     * Output Employer Dashboard.
     */
    /**
     * Output Employer Dashboard.
     * Note: This is now handled by MJB_Dashboard class, so this method is deprecated/removed or delegates.
     * But since we registered the shortcode in MJB_Shortcodes initially, we should remove it from here if we want MJB_Dashboard to handle it.
     * OR we update this method to delegate.
     * 
     * To avoid conflict, I will remove the registration from init() above and remove this method.
     */
    // Removing method logic as it is superseded.
}
