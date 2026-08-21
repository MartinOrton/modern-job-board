<?php
/**
 * Talent pool (#10) — employers browse public candidate profiles.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Talent_Pool
{
    const META_PUBLIC = '_candidate_is_public';
    const META_OPEN = '_candidate_open_to_work';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_shortcode('mjb_talent_pool', array(__CLASS__, 'render_shortcode'));
        add_action('init', array(__CLASS__, 'register_rewrite'));
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_profile'));
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function query_vars($vars)
    {
        $vars[] = 'mjb_talent_user';
        return $vars;
    }

    /**
     * Pretty candidate profile under /jobs/talent/{user_id}/
     */
    public static function register_rewrite()
    {
        add_rewrite_rule(
            '^jobs/talent/([0-9]+)/?$',
            'index.php?mjb_talent_user=$matches[1]',
            'top'
        );
    }

    /**
     * Whether a candidate opted into the public pool.
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_public($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        $public = get_user_meta($user_id, self::META_PUBLIC, true);
        // Default private unless explicitly public.
        return $public === '1' || $public === 1 || $public === true;
    }

    /**
     * @param int $user_id
     * @param bool $public
     */
    public static function set_public($user_id, $public)
    {
        update_user_meta((int) $user_id, self::META_PUBLIC, $public ? '1' : '0');
    }

    /**
     * Query public candidates.
     *
     * @param array $args
     * @return array<int, WP_User>
     */
    public static function query_candidates(array $args = array())
    {
        $defaults = array(
            'number' => 24,
            'search' => '',
            'paged' => 1,
        );
        $args = wp_parse_args($args, $defaults);

        $user_args = array(
            'role__in' => array('subscriber', 'candidate', 'mjb_candidate'),
            'number' => max(1, min(50, (int) $args['number'])),
            'paged' => max(1, (int) $args['paged']),
            'meta_query' => array(
                array(
                    'key' => self::META_PUBLIC,
                    'value' => '1',
                ),
            ),
            'orderby' => 'registered',
            'order' => 'DESC',
        );

        // Also include users with candidate meta even without custom role.
        $user_args = apply_filters('mjb_talent_pool_user_query', $user_args, $args);

        if ($args['search'] !== '') {
            $user_args['search'] = '*' . esc_attr($args['search']) . '*';
            $user_args['search_columns'] = array('user_login', 'user_nicename', 'display_name', 'user_email');
        }

        $q = new WP_User_Query($user_args);
        $users = $q->get_results();

        // Fallback: any user with public flag regardless of role.
        if (empty($users)) {
            $user_args['role__in'] = array();
            unset($user_args['role']);
            $q = new WP_User_Query($user_args);
            $users = $q->get_results();
        }

        return is_array($users) ? $users : array();
    }

    /**
     * Employers (or admins) only.
     *
     * @return bool
     */
    public static function current_user_can_browse()
    {
        if (class_exists('MJB_Resume_Privacy')) {
            return MJB_Resume_Privacy::current_user_can_browse_resumes();
        }
        if (!is_user_logged_in()) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        if (in_array('employer', $roles, true) || in_array('mjb_employer', $roles, true)) {
            return true;
        }
        // Authors of company posts count as employers.
        $companies = get_posts(array(
            'post_type' => 'company',
            'author' => get_current_user_id(),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => array('publish', 'pending', 'draft'),
        ));
        return !empty($companies);
    }

    /**
     * Shortcode: talent pool directory.
     *
     * @return string
     */
    public static function render_shortcode()
    {
        if (!self::current_user_can_browse()) {
            $login = MJB_Page_Resolver::get_page_url(
                'mjb_employer_login',
                'mjb_employer_login_page_id',
                array(),
                '/jobs/recruiter-login/'
            );
            return '<div class="mjb-talent-pool mjb-talent-pool--locked"><p>'
                . esc_html__('Sign in as a recruiter to browse the talent pool.', 'modern-job-board')
                . '</p><p><a class="btn btn-primary" href="' . esc_url($login) . '">'
                . esc_html__('Recruiter sign in', 'modern-job-board')
                . '</a></p></div>';
        }

        $search = isset($_GET['mjb_talent_q']) ? sanitize_text_field(wp_unslash($_GET['mjb_talent_q'])) : '';
        $candidates = self::query_candidates(array('search' => $search, 'number' => 24));

        ob_start();
        echo '<div class="mjb-talent-pool">';
        echo '<form class="mjb-talent-pool__search" method="get">';
        echo '<label class="screen-reader-text" for="mjb_talent_q">' . esc_html__('Search candidates', 'modern-job-board') . '</label>';
        echo '<input type="search" id="mjb_talent_q" name="mjb_talent_q" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search by name…', 'modern-job-board') . '">';
        echo '<button type="submit" class="btn btn-primary btn-sm">' . esc_html__('Search', 'modern-job-board') . '</button>';
        echo '</form>';

        if (empty($candidates)) {
            echo '<p class="mjb-talent-pool__empty">' . esc_html__('No public candidate profiles yet.', 'modern-job-board') . '</p>';
        } else {
            echo '<ul class="mjb-talent-pool__list">';
            foreach ($candidates as $user) {
                $name = $user->display_name ?: $user->user_login;
                $headline = get_user_meta($user->ID, '_candidate_headline', true);
                $city = get_user_meta($user->ID, '_candidate_city', true);
                $url = home_url('/jobs/talent/' . (int) $user->ID . '/');
                echo '<li class="mjb-talent-pool__card">';
                echo '<a href="' . esc_url($url) . '"><strong>' . esc_html($name) . '</strong></a>';
                if ($headline) {
                    echo '<div class="mjb-talent-pool__headline">' . esc_html($headline) . '</div>';
                }
                if ($city) {
                    echo '<div class="mjb-talent-pool__city">' . esc_html($city) . '</div>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * Single talent profile view.
     */
    public static function maybe_render_profile()
    {
        $user_id = (int) get_query_var('mjb_talent_user', 0);
        if ($user_id <= 0) {
            return;
        }

        if (!self::current_user_can_browse()) {
            wp_safe_redirect(MJB_Page_Resolver::get_page_url(
                'mjb_employer_login',
                'mjb_employer_login_page_id',
                array(),
                '/jobs/recruiter-login/'
            ));
            exit;
        }

        if (!self::is_public($user_id)) {
            status_header(404);
            nocache_headers();
            include get_query_template('404');
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            status_header(404);
            include get_query_template('404');
            exit;
        }

        status_header(200);
        nocache_headers();
        get_header();
        echo '<main class="mjb-container mjb-talent-profile">';
        echo '<h1>' . esc_html($user->display_name ?: $user->user_login) . '</h1>';
        $headline = get_user_meta($user_id, '_candidate_headline', true);
        $city = get_user_meta($user_id, '_candidate_city', true);
        $bio = get_user_meta($user_id, '_candidate_bio', true);
        if ($headline) {
            echo '<p class="mjb-talent-profile__headline">' . esc_html($headline) . '</p>';
        }
        if ($city) {
            echo '<p class="mjb-talent-profile__city">' . esc_html($city) . '</p>';
        }
        if ($bio) {
            echo '<div class="mjb-talent-profile__bio">' . wp_kses_post(wpautop($bio)) . '</div>';
        }
        $email = $user->user_email;
        // Only show email if open to work and public.
        if (get_user_meta($user_id, self::META_OPEN, true) === '1' && $email) {
            echo '<p><a class="btn btn-primary" href="mailto:' . esc_attr($email) . '">' . esc_html__('Contact candidate', 'modern-job-board') . '</a></p>';
        }
        echo '</main>';
        get_footer();
        exit;
    }
}
