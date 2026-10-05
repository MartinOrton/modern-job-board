<?php
/**
 * Hide the WordPress toolbar for candidate and recruiter accounts.
 *
 * Recruiter accounts use the `employer` role. Site staff who also hold one of
 * those roles (editors, administrators, collaborators) keep the toolbar.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Bar
{
    const ROLE_CANDIDATE = 'candidate';
    const ROLE_RECRUITER = 'employer';

    /**
     * Register hooks.
     */
    public static function init()
    {
        add_filter('show_admin_bar', array(__CLASS__, 'filter_show_admin_bar'), PHP_INT_MAX);
        add_filter('body_class', array(__CLASS__, 'strip_front_body_class'));
        add_filter('admin_body_class', array(__CLASS__, 'strip_admin_body_class'));
        add_action('wp_head', array(__CLASS__, 'print_hide_css'), 100);
        add_action('admin_head', array(__CLASS__, 'print_hide_css'), 100);
        add_action('admin_init', array(__CLASS__, 'suppress_in_admin'), 0);
    }

    /**
     * Whether this account should never see the toolbar.
     *
     * @param object|null $user User object with ID and roles. Null uses the current user.
     * @return bool
     */
    public static function hides_toolbar_for_user($user = null)
    {
        if ($user === null) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user = wp_get_current_user();
        }

        $user_id = (is_object($user) && isset($user->ID)) ? (int) $user->ID : 0;
        if ($user_id <= 0) {
            return false;
        }

        $roles = (is_object($user) && isset($user->roles)) ? (array) $user->roles : array();
        $is_board_account = in_array(self::ROLE_CANDIDATE, $roles, true) || in_array(self::ROLE_RECRUITER, $roles, true);
        if (!$is_board_account) {
            return false;
        }

        if (user_can($user_id, 'edit_posts') || user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce')) {
            return false;
        }

        return true;
    }

    /**
     * Force the toolbar off, including when the user preference is enabled.
     *
     * @param bool $show
     * @return bool
     */
    public static function filter_show_admin_bar($show)
    {
        if (self::hides_toolbar_for_user()) {
            return false;
        }

        return (bool) $show;
    }

    /**
     * Stop wp-admin from printing the toolbar. Core ignores show_admin_bar there.
     */
    public static function suppress_in_admin()
    {
        if (!self::hides_toolbar_for_user()) {
            return;
        }

        remove_action('admin_init', '_wp_admin_bar_init');
        remove_action('in_admin_header', 'wp_admin_bar_render', 0);
        remove_action('wp_body_open', 'wp_admin_bar_render', 0);
        remove_action('wp_footer', 'wp_admin_bar_render', 1000);
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public static function strip_front_body_class($classes)
    {
        if (!self::hides_toolbar_for_user() || !is_array($classes)) {
            return $classes;
        }

        return array_values(array_diff($classes, array('admin-bar')));
    }

    /**
     * @param string $classes
     * @return string
     */
    public static function strip_admin_body_class($classes)
    {
        if (!self::hides_toolbar_for_user()) {
            return $classes;
        }

        return trim((string) preg_replace('/\badmin-bar\b/', '', (string) $classes));
    }

    /**
     * Last-resort hide if another plugin prints the bar anyway.
     */
    public static function print_hide_css()
    {
        if (!self::hides_toolbar_for_user()) {
            return;
        }

        echo '<style id="mjb-hide-admin-bar">html{margin-top:0!important}#wpadminbar{display:none!important}</style>';
    }
}
