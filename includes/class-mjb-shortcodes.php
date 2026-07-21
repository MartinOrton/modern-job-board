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
     * Initialize Shortcodes.
     */
    public function init()
    {
        add_shortcode('mjb_jobs', array($this, 'output_jobs'));
        add_shortcode('mjb_job_form', array($this, 'output_job_form'));
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
            while ($jobs->have_posts()) {
                $jobs->the_post();

                $permalink = get_permalink();
                $job_title = get_the_title();
                $featured = get_post_meta(get_the_ID(), '_featured', true);
                $featured_class = $featured ? ' mjb-featured' : '';
                $excerpt = self::get_job_card_excerpt();

                echo '<article class="mjb-job-card mjb-job-item' . esc_attr($featured_class) . '">';
                echo '<a class="mjb-job-card__stretched-link" href="' . esc_url($permalink) . '" aria-label="' . esc_attr(sprintf(__('View job: %s', 'modern-job-board'), $job_title)) . '"></a>';
                if ($featured) {
                    echo '<span class="mjb-badge mjb-badge--featured">' . esc_html__('Featured', 'modern-job-board') . '</span>';
                }
                echo '<h3 class="mjb-job-card__title">' . esc_html($job_title) . '</h3>';
                self::render_job_card_company(get_the_ID());

                if ($excerpt !== '') {
                    echo '<p class="mjb-job-card__excerpt">' . esc_html($excerpt) . '</p>';
                }

                echo '<div class="mjb-job-meta mjb-job-card__meta">';

                $job_type = get_the_term_list(get_the_ID(), 'job_type', '', ', ');
                self::render_meta_pill('clock', $job_type);

                $job_location = MJB_Location::render_job_location_term_list(get_the_ID());
                self::render_meta_pill('map-pin', $job_location);

                $job_category = get_the_term_list(get_the_ID(), 'job_category', '', ', ');
                self::render_meta_pill('tag', $job_category);

                $expires = get_post_meta(get_the_ID(), '_job_expires', true);
                if ($expires) {
                    echo '<span class="mjb-meta-right mjb-meta-pill">';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('calendar');
                    echo esc_html(sprintf(__('Exp: %s', 'modern-job-board'), date_i18n(get_option('date_format'), strtotime($expires))));
                    echo '</span>';
                }

                echo '</div>';
                echo '</article>';
            }
            echo '</div>'; // .mjb-job-list
        } else {
            echo '<p>' . esc_html__('No jobs found.', 'modern-job-board') . '</p>';
        }
    }

    /**
     * Render the company name below a listing card title.
     *
     * @param int $post_id
     */
    public static function render_job_card_company($post_id)
    {
        $company_name = get_post_meta($post_id, '_company_name', true);
        if ($company_name === '') {
            return;
        }

        $company_url = MJB_Search::get_company_jobs_url_for_listing($post_id);

        if ($company_url !== '') {
            echo '<a class="mjb-job-card__company mjb-job-card__company--link" href="' . esc_url($company_url) . '">';
            echo esc_html($company_name);
            echo '</a>';
            return;
        }

        echo '<p class="mjb-job-card__company">' . esc_html($company_name) . '</p>';
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
        $excerpt = get_the_excerpt();
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags(get_the_content()), 30, '…');
        }

        return trim($excerpt);
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
                    <p class="mjb-content-hero__intro"><?php echo esc_html($intro); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
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
            '/candidate-dashboard/'
        );
        $employer_registration_url = MJB_Page_Resolver::get_page_url(
            'mjb_employer_registration',
            'mjb_employer_registration_page_id',
            '/employer-registration/'
        );
        $post_job_url = MJB_Page_Resolver::get_page_url(
            'mjb_job_form',
            'mjb_job_form_page_id',
            '/post-a-job/'
        );
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
                            <?php esc_html_e('Create a profile and get hired by top employers.', 'modern-job-board'); ?>
                        </p>
                        <div class="mjb-audience-card__actions">
                            <a href="<?php echo esc_url($candidate_dashboard_url); ?>" class="btn btn-primary btn-sm">
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
                            <h2 class="mjb-audience-card__title"><?php esc_html_e('Employers', 'modern-job-board'); ?></h2>
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
     * Render the AJAX loading overlay for job listings.
     */
    public static function render_jobs_loader()
    {
        echo '<div id="mjb-loader-overlay" class="mjb-loader-overlay" aria-hidden="true" role="status">';
        echo '<svg class="mjb-spinner" viewBox="0 0 48 48" aria-hidden="true" focusable="false">';
        echo '<circle class="mjb-spinner__track" cx="24" cy="24" r="20" fill="none" stroke-width="3"></circle>';
        echo '<circle class="mjb-spinner__arc" cx="24" cy="24" r="20" fill="none" stroke-width="3" stroke-linecap="round"></circle>';
        echo '</svg>';
        echo '<span class="screen-reader-text">' . esc_html__('Loading jobs', 'modern-job-board') . '</span>';
        echo '</div>';
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

        ob_start();
        ?>
        <form id="mjb-job-filter" class="mjb-job-filter mjb-search-panel" method="GET" action="<?php echo esc_url(MJB_Job_Routes::build_url()); ?>">
            <?php if ($show_heading) : ?>
                <h3><?php esc_html_e('Filter Jobs', 'modern-job-board'); ?></h3>
            <?php endif; ?>
            <div class="mjb-filter-row">
                <?php if (!empty($filter_params['search_company'])) : ?>
                    <input type="hidden" name="search_company" value="<?php echo esc_attr($filter_params['search_company']); ?>">
                <?php endif; ?>
                <input type="text" name="search_keywords" placeholder="<?php esc_attr_e('Keywords...', 'modern-job-board'); ?>" value="<?php echo esc_attr($filter_params['search_keywords']); ?>">
                <?php
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Dropdown helpers escape all HTML.
                echo MJB_Search::render_location_dropdown($filter_params['search_location']);
                echo MJB_Search::render_taxonomy_dropdown(
                    'job_category',
                    'search_category',
                    $filter_params['search_category'],
                    esc_html__('All Categories', 'modern-job-board')
                );
                echo MJB_Search::render_taxonomy_dropdown(
                    'job_type',
                    'search_type',
                    $filter_params['search_type'],
                    esc_html__('All Job Types', 'modern-job-board')
                );
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
                <button type="submit" class="btn btn-primary btn-sm mjb-button mjb-button--search"><?php esc_html_e('Search', 'modern-job-board'); ?></button>
            </div>
        </form>
        <?php

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
     *
     * @param array $query_args
     * @return string
     */
    public static function get_job_form_page_url($query_args = array())
    {
        return MJB_Page_Resolver::get_page_url('mjb_job_form', self::JOB_FORM_PAGE_OPTION, $query_args, '/post-job/');
    }

    /**
     * Output Job Submission Form.
     */
    public function output_job_form($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . sprintf(
                __('You must be <a href="%s">logged in as an employer</a> to post jobs.', 'modern-job-board'),
                esc_url(wp_login_url(get_permalink()))
            ) . '</p>';
        }

        $user = wp_get_current_user();
        if (!in_array('employer', (array) $user->roles, true) && !user_can($user, 'manage_options')) {
            return '<p>' . __('This form is for employer accounts only.', 'modern-job-board') . '</p>';
        }

        ob_start();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in MJB_Notices::render().
        echo MJB_Notices::render();

        // Check for Edit Actions
        $job_id = 0;
        $job_title = '';
        $job_description = '';
        $company_name = '';

        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['job_id'])) {
            if (!is_user_logged_in()) {
                echo '<p>' . esc_html__('You must be logged in to edit a job.', 'modern-job-board') . '</p>';
                return ob_get_clean();
            }

            $job_id = intval($_GET['job_id']);
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
                    $required_attr = $is_required ? 'required aria-required="true"' : '';
                    echo '<p>';
                    echo '<label for="mjb_field_' . esc_attr($field['key']) . '">' . esc_html($field['label']);
                    if ($is_required) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
                        echo self::required_mark();
                    }
                    echo '</label>';
                    
                    if ($field['type'] === 'text' || $field['type'] === 'number') {
                        echo '<input type="' . esc_attr($field['type']) . '" name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '" value="' . esc_attr($value) . '" ' . $required_attr . '>';
                    } elseif ($field['type'] === 'textarea') {
                        echo '<textarea name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '" ' . $required_attr . '>' . esc_textarea($value) . '</textarea>';
                    } elseif ($field['type'] === 'select') {
                        echo '<select name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '" ' . $required_attr . '>';
                        $options = explode(',', $field['options']);
                        foreach ($options as $opt) {
                            $opt = trim($opt);
                            echo '<option value="' . esc_attr($opt) . '" ' . selected($value, $opt, false) . '>' . esc_html($opt) . '</option>';
                        }
                        echo '</select>';
                    } elseif ($field['type'] === 'checkbox') {
                         echo '<input type="checkbox" name="mjb_field_' . esc_attr($field['key']) . '" id="mjb_field_' . esc_attr($field['key']) . '" value="1" ' . checked(1, $value, false) . ' ' . $required_attr . '>';
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
            '/post-a-job/'
        );

        if (!is_user_logged_in()) {
            MJB_Notices::redirect($redirect_url, 'error_login_required');
        }

        $title = isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : '';
        $description = isset($_POST['job_description']) ? wp_kses_post($_POST['job_description']) : '';

        if (empty($title) || empty($description)) {
            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
        }

        $company_selection = isset($_POST['company_selection']) ? sanitize_text_field($_POST['company_selection']) : '';
        $company_id = 0;
        $company_name_text = '';

        if ($company_selection === 'new') {
            $company_name_text = isset($_POST['new_company_name']) ? sanitize_text_field($_POST['new_company_name']) : '';
            if (empty($company_name_text)) {
                MJB_Notices::redirect($redirect_url, 'error_invalid_company');
            }

            // Reuse an existing company with the same name to prevent duplicates.
            $company_id = MJB_Job_Importer::find_or_create_company($company_name_text, array(
                'author_id' => get_current_user_id(),
            ));
            if (!$company_id) {
                MJB_Notices::redirect($redirect_url, 'error_invalid_company');
            }
            $company_name_text = MJB_Job_Importer::normalize_company_name($company_name_text);
        } else {
            $company_id = intval($company_selection);
            // Verify ownership
            $company_post = get_post($company_id);
            if (!$company_post || $company_post->post_type !== 'company' || intval($company_post->post_author) !== get_current_user_id()) {
                MJB_Notices::redirect($redirect_url, 'error_invalid_company');
            }
            $company_name_text = $company_post->post_title;
        }

        // Check if updating via HIDDEN input (overrides argument if present)
        if (isset($_POST['job_id']) && intval($_POST['job_id']) > 0) {
            $existing_job_id = intval($_POST['job_id']);
            // Re-verify ownership for security (double check)
            $job = get_post($existing_job_id);
            if (!$job || intval($job->post_author) !== get_current_user_id()) {
                MJB_Notices::redirect($redirect_url, 'error_permission');
            }
        }

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_type' => 'job_listing',
            'post_status' => 'pending', // Always reset to pending on edit? Or keep status? Let's keep status if edit.
        );
        
        $post_data = apply_filters('mjb_pre_job_submission_data', $post_data, $_POST);

        if ($existing_job_id) {
            $post_data['ID'] = $existing_job_id;
            // Don't change status if editing, unless we want to re-review. Let's keep current status for now for simplicity, or pending if logic requires.
            // Actually, usually edits require re-approval. Let's set to pending.
            $post_data['post_status'] = 'pending';
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
            
            // Payment Logic
            $payment_required = get_option('mjb_payment_required');
            $product_id = get_option('mjb_submission_product_id');
            
            if ($payment_required) {
                $user_id = get_current_user_id();
                $credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $credits = $credits ? intval($credits) : 0;

                // Check for Credits
                if ($credits > 0) {
                     // Use Credit
                     $new_credits = $credits - 1;
                     update_user_meta($user_id, '_mjb_job_credits', $new_credits);
                     
                     // Publish Job immediately (bypass pending_payment)
                     // If previously set to pending, update to publish.
                     wp_update_post(array(
                         'ID' => $post_id,
                         'post_status' => 'publish'
                     ));
                     
                     // Optional: Add note that credit was used
                     update_post_meta($post_id, '_mjb_credit_used', true);
                     
                     MJB_Notices::redirect($redirect_url, 'success_job_credit');
                }

                // If no credits, proceed to Pay-Per-Post
                if ($product_id && function_exists('wc_get_cart_url')) {
                    if ($post_data['post_status'] === 'pending') { // Only if we just set it to pending
                         // Update status to pending_payment
                         $update = array('ID' => $post_id, 'post_status' => 'pending_payment');
                         wp_update_post($update);
                         
                         $cart_url = wc_get_cart_url();
                         $redirect_url = add_query_arg(array(
                             'add-to-cart' => $product_id,
                             'mjb_job_id' => $post_id
                         ), $cart_url);
                         
                         wp_safe_redirect($redirect_url);
                         exit;
                    }
                }
            }

            // Hook for post-submission actions
            do_action('mjb_job_submitted', $post_id);

            MJB_Notices::redirect($redirect_url, $notice_code);
        }
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
