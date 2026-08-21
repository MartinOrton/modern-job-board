<?php
/**
 * Featured companies + ad banners (WPJB Tier B2).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Promotions
{
    const OPTION_BANNERS = 'mjb_ad_banners';
    const META_FEATURED_COMPANY = '_mjb_company_featured';
    const META_FEATURED_UNTIL = '_mjb_company_featured_until';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_shortcode('mjb_featured_companies', array(__CLASS__, 'render_featured_companies'));
        add_shortcode('mjb_ad_banner', array(__CLASS__, 'render_banner_shortcode'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 61);
        add_action('admin_post_mjb_save_banners', array(__CLASS__, 'save_banners'));
        add_action('add_meta_boxes', array(__CLASS__, 'company_meta_box'));
        add_action('save_post_company', array(__CLASS__, 'save_company_meta'), 10, 2);
        add_action('mjb_after_job_card', array(__CLASS__, 'maybe_inject_banner'), 10, 3);
        add_action('mjb_before_job_listings', array(__CLASS__, 'render_position_zero'), 10, 1);
    }

    /**
     * @return array<int, array{html:string,position:int,enabled:bool,label:string}>
     */
    public static function get_banners()
    {
        $banners = get_option(self::OPTION_BANNERS, array());
        return is_array($banners) ? $banners : array();
    }

    /**
     * Banners for a list index (1-based job position; 0 = above list).
     *
     * @param int $position
     * @return array
     */
    public static function banners_for_position($position)
    {
        $position = (int) $position;
        $out = array();
        foreach (self::get_banners() as $b) {
            if (empty($b['enabled'])) {
                continue;
            }
            if ((int) (isset($b['position']) ? $b['position'] : -1) === $position) {
                $out[] = $b;
            }
        }
        return $out;
    }

    /**
     * @param WP_Query|null $jobs
     */
    public static function render_position_zero($jobs = null)
    {
        unset($jobs);
        self::echo_banners(0);
    }

    /**
     * After each job card in the listing loop.
     *
     * @param int      $job_id
     * @param int      $index 1-based
     * @param WP_Query $jobs
     */
    public static function maybe_inject_banner($job_id, $index, $jobs = null)
    {
        unset($job_id, $jobs);
        self::echo_banners((int) $index);
    }

    /**
     * @param int $position
     */
    public static function echo_banners($position)
    {
        foreach (self::banners_for_position($position) as $b) {
            $html = isset($b['html']) ? $b['html'] : '';
            if ($html === '') {
                continue;
            }
            // Allow common ad markup (scripts for AdSense etc. when admin-authored).
            echo '<div class="mjb-ad-banner" data-position="' . esc_attr((string) $position) . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-configured ad HTML.
            echo $html;
            echo '</div>';
        }
    }

    /**
     * Shortcode: single banner by label or position.
     *
     * @param array|string $atts
     * @return string
     */
    public static function render_banner_shortcode($atts = array())
    {
        $atts = shortcode_atts(array(
            'position' => '',
            'label' => '',
        ), $atts, 'mjb_ad_banner');

        $html = '';
        foreach (self::get_banners() as $b) {
            if (empty($b['enabled'])) {
                continue;
            }
            if ($atts['label'] !== '' && isset($b['label']) && $b['label'] === $atts['label']) {
                $html = isset($b['html']) ? $b['html'] : '';
                break;
            }
            if ($atts['position'] !== '' && (int) $b['position'] === (int) $atts['position']) {
                $html = isset($b['html']) ? $b['html'] : '';
                break;
            }
        }
        if ($html === '') {
            return '';
        }
        return '<div class="mjb-ad-banner mjb-ad-banner--shortcode">' . $html . '</div>';
    }

    /**
     * Featured companies shortcode.
     *
     * @param array|string $atts
     * @return string
     */
    public static function render_featured_companies($atts = array())
    {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'columns' => 3,
        ), $atts, 'mjb_featured_companies');

        $limit = max(1, min(48, (int) $atts['limit']));
        $now = current_time('timestamp');

        $ids = get_posts(array(
            'post_type' => 'company',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => self::META_FEATURED_COMPANY,
                    'value' => '1',
                ),
            ),
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $companies = array();
        foreach ((array) $ids as $id) {
            $until = (int) get_post_meta($id, self::META_FEATURED_UNTIL, true);
            if ($until > 0 && $until < $now) {
                continue;
            }
            $companies[] = (int) $id;
        }

        if (empty($companies)) {
            return '<p class="mjb-featured-companies mjb-featured-companies--empty">' . esc_html__('No featured companies right now.', 'modern-job-board') . '</p>';
        }

        $cols = max(1, min(6, (int) $atts['columns']));
        ob_start();
        echo '<div class="mjb-featured-companies" style="--mjb-fc-cols:' . esc_attr((string) $cols) . '">';
        echo '<div class="mjb-featured-companies__grid">';
        foreach ($companies as $cid) {
            $name = get_the_title($cid);
            $url = get_permalink($cid);
            $logo = get_post_meta($cid, '_company_logo_url', true);
            $tagline = get_post_meta($cid, '_company_tagline', true);
            echo '<article class="mjb-featured-companies__card">';
            if ($logo) {
                echo '<a class="mjb-featured-companies__logo" href="' . esc_url($url) . '"><img src="' . esc_url($logo) . '" alt="" loading="lazy"></a>';
            }
            echo '<h3 class="mjb-featured-companies__name"><a href="' . esc_url($url) . '">' . esc_html($name) . '</a></h3>';
            if ($tagline) {
                echo '<p class="mjb-featured-companies__tagline">' . esc_html($tagline) . '</p>';
            }
            echo '</article>';
        }
        echo '</div></div>';
        return (string) ob_get_clean();
    }

    /**
     * Company meta: featured flag.
     */
    public static function company_meta_box()
    {
        add_meta_box(
            'mjb_company_featured',
            __('Featured company', 'modern-job-board'),
            array(__CLASS__, 'render_company_meta_box'),
            'company',
            'side',
            'default'
        );
    }

    /**
     * @param WP_Post $post
     */
    public static function render_company_meta_box($post)
    {
        wp_nonce_field('mjb_company_featured', 'mjb_company_featured_nonce');
        $featured = get_post_meta($post->ID, self::META_FEATURED_COMPANY, true);
        $until = (int) get_post_meta($post->ID, self::META_FEATURED_UNTIL, true);
        $until_str = $until > 0 ? gmdate('Y-m-d', $until) : '';
        echo '<p><label><input type="checkbox" name="mjb_company_featured" value="1" ' . checked($featured, '1', false) . '> ';
        echo esc_html__('Show in [mjb_featured_companies]', 'modern-job-board') . '</label></p>';
        echo '<p><label>' . esc_html__('Featured until (optional)', 'modern-job-board') . '<br>';
        echo '<input type="date" name="mjb_company_featured_until" value="' . esc_attr($until_str) . '"></label></p>';
    }

    /**
     * @param int     $post_id
     * @param WP_Post $post
     */
    public static function save_company_meta($post_id, $post = null)
    {
        unset($post);
        if (!isset($_POST['mjb_company_featured_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_company_featured_nonce'])), 'mjb_company_featured')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $featured = !empty($_POST['mjb_company_featured']) ? '1' : '0';
        update_post_meta($post_id, self::META_FEATURED_COMPANY, $featured);
        $until_raw = isset($_POST['mjb_company_featured_until']) ? sanitize_text_field(wp_unslash($_POST['mjb_company_featured_until'])) : '';
        if ($until_raw !== '') {
            $ts = strtotime($until_raw . ' 23:59:59');
            update_post_meta($post_id, self::META_FEATURED_UNTIL, $ts ? (int) $ts : 0);
        } else {
            delete_post_meta($post_id, self::META_FEATURED_UNTIL);
        }
    }

    /**
     * Admin page for ad slots.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Ad banners', 'modern-job-board'),
            __('Ad banners', 'modern-job-board'),
            'manage_options',
            'mjb-ad-banners',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Save banners form.
     */
    public static function save_banners()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'modern-job-board'));
        }
        check_admin_referer('mjb_save_banners');

        $labels = isset($_POST['banner_label']) ? (array) wp_unslash($_POST['banner_label']) : array();
        $positions = isset($_POST['banner_position']) ? (array) wp_unslash($_POST['banner_position']) : array();
        $htmls = isset($_POST['banner_html']) ? (array) wp_unslash($_POST['banner_html']) : array();
        $enableds = isset($_POST['banner_enabled']) ? (array) wp_unslash($_POST['banner_enabled']) : array();

        $banners = array();
        $count = max(count($labels), count($positions), count($htmls));
        $allow_raw = current_user_can('unfiltered_html');
        for ($i = 0; $i < $count; $i++) {
            $html = isset($htmls[$i]) ? trim((string) $htmls[$i]) : '';
            if ($html === '') {
                continue;
            }
            $banners[] = array(
                'label' => isset($labels[$i]) ? sanitize_text_field($labels[$i]) : '',
                'position' => isset($positions[$i]) ? max(0, min(50, (int) $positions[$i])) : 0,
                'html' => $allow_raw ? $html : wp_kses_post($html),
                'enabled' => isset($enableds[$i]) && (string) $enableds[$i] === '1',
            );
        }

        update_option(self::OPTION_BANNERS, $banners, false);
        wp_safe_redirect(add_query_arg(array('page' => 'mjb-ad-banners', 'updated' => '1'), admin_url('edit.php?post_type=job_listing')));
        exit;
    }

    /**
     * Admin UI.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $banners = self::get_banners();
        // Always show one empty row for add.
        $banners[] = array('label' => '', 'position' => 5, 'html' => '', 'enabled' => true);

        echo '<div class="wrap"><h1>' . esc_html__('Ad banners', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Inject HTML ad slots into the public job list. Position 0 = above the list; position N = after the Nth job card. Shortcode: [mjb_ad_banner position="0"] or [mjb_featured_companies].', 'modern-job-board') . '</p>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Banners saved.', 'modern-job-board') . '</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mjb_save_banners">';
        wp_nonce_field('mjb_save_banners');
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Label', 'modern-job-board') . '</th>';
        echo '<th>' . esc_html__('Position', 'modern-job-board') . '</th>';
        echo '<th>' . esc_html__('HTML', 'modern-job-board') . '</th>';
        echo '<th>' . esc_html__('On', 'modern-job-board') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($banners as $i => $b) {
            echo '<tr>';
            echo '<td><input type="text" name="banner_label[' . esc_attr((string) $i) . ']" value="' . esc_attr(isset($b['label']) ? $b['label'] : '') . '" class="regular-text"></td>';
            echo '<td><input type="number" min="0" max="50" name="banner_position[' . esc_attr((string) $i) . ']" value="' . esc_attr((string) (isset($b['position']) ? (int) $b['position'] : 0)) . '" style="width:5em"></td>';
            echo '<td><textarea name="banner_html[' . esc_attr((string) $i) . ']" rows="3" class="large-text code">' . esc_textarea(isset($b['html']) ? $b['html'] : '') . '</textarea></td>';
            echo '<td><input type="hidden" name="banner_enabled[' . esc_attr((string) $i) . ']" value="0">';
            echo '<input type="checkbox" name="banner_enabled[' . esc_attr((string) $i) . ']" value="1" ' . checked(!empty($b['enabled']), true, false) . '></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button(__('Save banners', 'modern-job-board'));
        echo '</form></div>';
    }
}
