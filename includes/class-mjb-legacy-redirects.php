<?php
/**
 * Legacy path redirects (#32, #33).
 *
 * Maps old top-level board URLs to pretty /jobs/… paths.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Legacy_Redirects
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'maybe_redirect'), 0);
    }

    /**
     * 301 common legacy board paths.
     */
    public static function maybe_redirect()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_cron()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        if ($path === '') {
            return;
        }

        $path = untrailingslashit($path);
        $home_path = untrailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
        if ($home_path !== '' && $home_path !== '/' && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path));
            if ($path === false || $path === '') {
                $path = '/';
            }
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = untrailingslashit($path);
        }

        $map = array(
            '/candidate-registration' => '/jobs/candidate-registration/',
            '/employer-registration'  => '/jobs/recruiter-registration/',
            '/recruiter-registration' => '/jobs/recruiter-registration/',
            '/candidate-login'        => '/jobs/candidate-login/',
            '/employer-login'         => '/jobs/recruiter-login/',
            '/recruiter-login'        => '/jobs/recruiter-login/',
            '/candidate-dashboard'    => '/jobs/candidate-dashboard/',
            '/employer-dashboard'     => '/jobs/recruiter-dashboard/',
            '/recruiter-dashboard'    => '/jobs/recruiter-dashboard/',
            // Nested legacy employer-* under /jobs/ (pre-rename).
            '/jobs/employer-registration' => '/jobs/recruiter-registration/',
            '/jobs/employer-login'        => '/jobs/recruiter-login/',
            '/jobs/employer-dashboard'    => '/jobs/recruiter-dashboard/',
            '/post-a-job'             => '/jobs/post-a-job/',
            '/post-job'               => '/jobs/post-a-job/',
            '/company'                => '/jobs/companies/',
            '/companies'              => '/jobs/companies/',
        );

        $map = apply_filters('mjb_legacy_redirect_map', $map);

        if (!isset($map[$path])) {
            // /company/{slug}/ → /jobs/companies/{slug}/ when not already under /jobs/
            if (preg_match('#^/company/([^/]+)$#i', $path, $m)) {
                $target = home_url('/jobs/companies/' . rawurlencode($m[1]) . '/');
                wp_safe_redirect($target, 301);
                exit;
            }
            return;
        }

        $target = home_url($map[$path]);
        $query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
        if ($query !== '') {
            $target = $target . (strpos($target, '?') === false ? '?' : '&') . $query;
        }

        wp_safe_redirect($target, 301);
        exit;
    }
}
