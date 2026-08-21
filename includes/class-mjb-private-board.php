<?php
/**
 * Private job search mode (#16).
 *
 * When enabled, published listings require a logged-in member to view
 * job archives, search AJAX, and single job pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Private_Board
{
    const OPTION = 'mjb_private_job_search';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'guard_public_views'), 5);
        add_action('pre_get_posts', array(__CLASS__, 'hide_jobs_from_guests'), 20);
    }

    /**
     * @return bool
     */
    public static function is_enabled()
    {
        return (bool) apply_filters('mjb_private_job_search', get_option(self::OPTION, '0') === '1');
    }

    /**
     * Guests may not browse the board.
     */
    public static function guard_public_views()
    {
        if (!self::is_enabled() || is_user_logged_in() || is_admin()) {
            return;
        }

        $block = false;
        if (is_singular('job_listing') || is_post_type_archive('job_listing')) {
            $block = true;
        }
        if (is_tax(array('job_listing_category', 'job_listing_type', 'job_listing_location', 'job_listing_tag'))) {
            $block = true;
        }
        if (is_singular('page')) {
            $jobs_id = (int) get_option('mjb_jobs_page_id', 0);
            if ($jobs_id > 0 && (int) get_queried_object_id() === $jobs_id) {
                $block = true;
            }
        }

        if (!$block) {
            return;
        }

        $login = MJB_Page_Resolver::get_page_url(
            'mjb_candidate_login',
            'mjb_candidate_login_page_id',
            array(),
            '/jobs/candidate-login/'
        );
        $redirect_to = home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''));
        if (isset($_SERVER['REQUEST_URI'])) {
            $redirect_to = home_url(wp_unslash($_SERVER['REQUEST_URI']));
        }
        $login = add_query_arg('redirect_to', rawurlencode($redirect_to), $login);
        wp_safe_redirect($login);
        exit;
    }

    /**
     * Prevent guest main queries from listing jobs.
     *
     * @param WP_Query $query
     */
    public static function hide_jobs_from_guests($query)
    {
        if (!self::is_enabled() || is_user_logged_in() || is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') === 'job_listing' || $query->is_post_type_archive('job_listing')) {
            $query->set('post__in', array(0));
        }
    }
}
