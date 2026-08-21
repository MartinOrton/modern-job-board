<?php
/**
 * SEO-friendly public URL builders, rewrites, and legacy redirects.
 *
 * Keeps ephemeral flash notices (?mjb_notice=) and security tokens
 * (reset key, nonces, redirect_to) as query args — those are not content URLs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Pretty_Urls
{
    const QV_APPLY_TOKEN = 'mjb_a';
    const QV_EDIT_JOB    = 'mjb_edit_job';
    const QV_DASH_VIEW   = 'mjb_dash_view';
    const QV_DASH_JOB    = 'mjb_dash_job';
    const QV_LOGIN_MODE  = 'mjb_login_mode';
    const QV_FRONT_APPLY = 'mjb_front_apply';
    const QV_FRONT_SAVE  = 'mjb_front_save';

    /**
     * Hook rewrites and redirects.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_rewrites'), 11);
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'redirect_legacy_query_urls'), 4);
        add_action('template_redirect', array(__CLASS__, 'handle_front_actions'), 6);
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function register_query_vars($vars)
    {
        $vars[] = self::QV_APPLY_TOKEN;
        $vars[] = self::QV_EDIT_JOB;
        $vars[] = self::QV_DASH_VIEW;
        $vars[] = self::QV_DASH_JOB;
        $vars[] = self::QV_LOGIN_MODE;
        $vars[] = self::QV_FRONT_APPLY;
        $vars[] = self::QV_FRONT_SAVE;
        return $vars;
    }

    /**
     * Resolve a board page path (no leading/trailing slash).
     *
     * @param string $option_key
     * @param string $fallback e.g. jobs/candidate-login
     * @return array{0:int,1:string} page_id, uri
     */
    public static function resolve_page_path($option_key, $fallback)
    {
        $page_id = (int) get_option($option_key, 0);
        $uri     = '';
        if ($page_id > 0) {
            $uri = (string) get_page_uri($page_id);
        }
        if ($uri === '') {
            $uri = $fallback;
            $page_id = 0;
        }
        $uri = untrailingslashit(trim($uri, '/'));
        return array($page_id, $uri);
    }

    /**
     * Register pretty rewrite rules for public board flows.
     */
    public static function register_rewrites()
    {
        $pages = array(
            'login' => array(
                'option'   => 'mjb_candidate_login_page_id',
                'fallback' => 'jobs/candidate-login',
            ),
            'register' => array(
                'option'   => 'mjb_candidate_registration_page_id',
                'fallback' => 'jobs/candidate-registration',
            ),
            'dashboard' => array(
                'option'   => 'mjb_employer_dashboard_page_id',
                'fallback' => 'jobs/recruiter-dashboard',
            ),
            'job_form' => array(
                'option'   => 'mjb_job_form_page_id',
                'fallback' => 'jobs/post-a-job',
            ),
        );

        foreach ($pages as $key => $cfg) {
            list($page_id, $uri) = self::resolve_page_path($cfg['option'], $cfg['fallback']);
            if ($uri === '') {
                continue;
            }
            $uri_q  = preg_quote($uri, '/');
            $target = $page_id > 0
                ? 'index.php?page_id=' . $page_id
                : 'index.php?pagename=' . $uri;

            if ($key === 'login' || $key === 'register') {
                // /…/apply/{token}/
                add_rewrite_rule(
                    '^' . $uri_q . '/apply/([a-f0-9]{16,64})/?$',
                    $target . '&' . self::QV_APPLY_TOKEN . '=$matches[1]',
                    'top'
                );
            }

            if ($key === 'login') {
                // /…/save/ — sign-in to save a job
                add_rewrite_rule(
                    '^' . $uri_q . '/save/?$',
                    $target . '&' . self::QV_LOGIN_MODE . '=save',
                    'top'
                );
            }

            if ($key === 'dashboard') {
                // /…/jobs/{id}/applications/ (job detail apps — more specific first)
                add_rewrite_rule(
                    '^' . $uri_q . '/jobs/([0-9]+)/applications/?$',
                    $target . '&' . self::QV_DASH_VIEW . '=applications&' . self::QV_DASH_JOB . '=$matches[1]',
                    'top'
                );
                // Tab pages: /…/jobs/, /…/applications/, /…/packages/
                add_rewrite_rule(
                    '^' . $uri_q . '/jobs/?$',
                    $target . '&' . self::QV_DASH_VIEW . '=jobs',
                    'top'
                );
                add_rewrite_rule(
                    '^' . $uri_q . '/applications/?$',
                    $target . '&' . self::QV_DASH_VIEW . '=applications',
                    'top'
                );
                add_rewrite_rule(
                    '^' . $uri_q . '/packages/?$',
                    $target . '&' . self::QV_DASH_VIEW . '=packages',
                    'top'
                );
            }

            if ($key === 'job_form') {
                // /…/edit/{id}/
                add_rewrite_rule(
                    '^' . $uri_q . '/edit/([0-9]+)/?$',
                    $target . '&' . self::QV_EDIT_JOB . '=$matches[1]',
                    'top'
                );
            }
        }

        $jobs_base = class_exists('MJB_Job_Routes')
            ? MJB_Job_Routes::get_base_slug()
            : 'jobs';
        $jobs_q = preg_quote($jobs_base, '/');

        // Front actions (replace admin-post.php for apply / save).
        add_rewrite_rule(
            '^' . $jobs_q . '/apply/([a-f0-9]{16,64})/?$',
            'index.php?' . self::QV_FRONT_APPLY . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^' . $jobs_q . '/save/([0-9]+)/?$',
            'index.php?' . self::QV_FRONT_SAVE . '=$matches[1]',
            'top'
        );
    }

    /**
     * Build URL: base page + path segments + optional query.
     *
     * @param string $base_url
     * @param array  $segments
     * @param array  $query
     * @return string
     */
    public static function with_path($base_url, $segments = array(), $query = array())
    {
        $url = trailingslashit($base_url);
        foreach ((array) $segments as $segment) {
            $segment = trim((string) $segment, '/');
            if ($segment === '') {
                continue;
            }
            // Tokens / numeric IDs / keywords stay readable in the path.
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $segment)) {
                $url .= $segment . '/';
            } else {
                $url .= rawurlencode($segment) . '/';
            }
        }

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        return $url;
    }

    /**
     * Candidate login / registration URL carrying an opaque apply token.
     *
     * @param string $base_url Login or registration page URL
     * @param string $token
     * @return string
     */
    public static function apply_intent_url($base_url, $token)
    {
        $token = is_string($token) ? strtolower(trim($token)) : '';
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
            return $base_url;
        }
        return self::with_path($base_url, array('apply', $token));
    }

    /**
     * Employer dashboard applications for a job.
     *
     * @param int $job_id
     * @param array $query Extra query (e.g. notice)
     * @return string
     */
    public static function dashboard_applications_url($job_id, $query = array())
    {
        $job_id = (int) $job_id;
        $base = class_exists('MJB_Dashboard')
            ? MJB_Dashboard::get_page_url()
            : home_url('/jobs/recruiter-dashboard/');
        if ($job_id <= 0) {
            return $base;
        }
        return self::with_path($base, array('jobs', (string) $job_id, 'applications'), $query);
    }

    /**
     * Job form edit URL.
     *
     * @param int $job_id
     * @return string
     */
    public static function job_edit_url($job_id)
    {
        $job_id = (int) $job_id;
        $base = class_exists('MJB_Shortcodes')
            ? MJB_Shortcodes::get_job_form_page_url()
            : home_url('/jobs/post-a-job/');
        if ($job_id <= 0) {
            return $base;
        }
        return self::with_path($base, array('edit', (string) $job_id));
    }

    /**
     * Logged-in apply action URL (pretty, not admin-post.php).
     *
     * @param string $token
     * @return string
     */
    public static function front_apply_url($token)
    {
        $token = is_string($token) ? strtolower(trim($token)) : '';
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
            return home_url('/jobs/');
        }
        $base = class_exists('MJB_Job_Routes')
            ? trailingslashit(home_url('/' . MJB_Job_Routes::get_base_slug()))
            : home_url('/jobs/');
        return wp_nonce_url($base . 'apply/' . $token . '/', 'mjb_apply_to_job');
    }

    /**
     * Save/unsave action URL (pretty).
     *
     * @param int         $job_id
     * @param string|null $redirect_to Optional return URL after toggle.
     * @return string
     */
    public static function front_save_url($job_id, $redirect_to = null)
    {
        $job_id = (int) $job_id;
        $base = class_exists('MJB_Job_Routes')
            ? trailingslashit(home_url('/' . MJB_Job_Routes::get_base_slug()))
            : home_url('/jobs/');
        $url = $base . 'save/' . $job_id . '/';
        if (is_string($redirect_to) && $redirect_to !== '') {
            $url = add_query_arg('redirect_to', $redirect_to, $url);
        }
        return wp_nonce_url($url, 'mjb_toggle_saved_job_' . $job_id);
    }

    /**
     * Login URL for saving a job (pretty /save/ + redirect_to).
     *
     * @param int $job_id
     * @return string
     */
    public static function login_to_save_url($job_id)
    {
        $job_id = (int) $job_id;
        $redirect = $job_id > 0 ? get_permalink($job_id) : home_url('/');
        $login = MJB_Page_Resolver::get_page_url(
            'mjb_candidate_login',
            'mjb_candidate_login_page_id',
            array(),
            '/jobs/candidate-login/'
        );
        return self::with_path(
            $login,
            array('save'),
            array(
                'redirect_to' => $redirect,
            )
        );
    }

    /**
     * Read apply token from pretty path query var or legacy ?mjb_a=.
     *
     * @return string
     */
    public static function request_apply_token()
    {
        $token = get_query_var(self::QV_APPLY_TOKEN, '');
        if ($token === '' || $token === false || $token === null) {
            if (!empty($_REQUEST[self::QV_APPLY_TOKEN])) {
                $token = sanitize_text_field(wp_unslash($_REQUEST[self::QV_APPLY_TOKEN]));
            } else {
                $token = '';
            }
        } else {
            $token = sanitize_text_field((string) $token);
        }
        return strtolower(trim($token));
    }

    /**
     * Job ID being edited on the post-a-job form (pretty or legacy).
     *
     * @return int
     */
    public static function request_edit_job_id()
    {
        $id = (int) get_query_var(self::QV_EDIT_JOB, 0);
        if ($id > 0) {
            return $id;
        }
        if (isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'edit' && isset($_GET['job_id'])) {
            return (int) $_GET['job_id'];
        }
        return 0;
    }

    /**
     * Dashboard applications job id (pretty or legacy).
     *
     * @return int 0 if not in applications view
     */
    public static function request_dashboard_applications_job_id()
    {
        $view = get_query_var(self::QV_DASH_VIEW, '');
        $job  = (int) get_query_var(self::QV_DASH_JOB, 0);
        if ($view === 'applications' && $job > 0) {
            return $job;
        }
        if (
            isset($_GET['action'])
            && sanitize_key(wp_unslash($_GET['action'])) === 'view_applications'
            && isset($_GET['job_id'])
        ) {
            return (int) $_GET['job_id'];
        }
        return 0;
    }

    /**
     * Recruiter dashboard tab slug (pretty path or ?mjb_tab=).
     *
     * @return string overview|jobs|applications|packages
     */
    public static function request_dashboard_tab()
    {
        $view = get_query_var(self::QV_DASH_VIEW, '');
        $view = is_string($view) ? sanitize_key($view) : '';
        $job  = (int) get_query_var(self::QV_DASH_JOB, 0);

        // Job-specific applications view is not a tab page.
        if ($view === 'applications' && $job > 0) {
            return 'job-applications';
        }

        if (in_array($view, array('jobs', 'applications', 'packages', 'overview'), true)) {
            return $view === 'overview' ? 'overview' : $view;
        }

        if (!empty($_GET['mjb_tab'])) {
            $tab = sanitize_key(wp_unslash($_GET['mjb_tab']));
            if (in_array($tab, array('jobs', 'applications', 'packages', 'overview'), true)) {
                return $tab;
            }
        }

        return 'overview';
    }

    /**
     * Build a recruiter dashboard tab URL.
     *
     * @param string $tab   overview|jobs|applications|packages
     * @param array  $query Extra query args
     * @return string
     */
    public static function dashboard_tab_url($tab, $query = array())
    {
        $tab = sanitize_key((string) $tab);
        $base = class_exists('MJB_Dashboard')
            ? MJB_Dashboard::get_page_url()
            : home_url('/jobs/recruiter-dashboard/');

        if ($tab === '' || $tab === 'overview' || $tab === 'dashboard') {
            return empty($query) ? $base : add_query_arg($query, $base);
        }

        if (!in_array($tab, array('jobs', 'applications', 'packages'), true)) {
            $tab = 'overview';
            return empty($query) ? $base : add_query_arg($query, $base);
        }

        return self::with_path($base, array($tab), $query);
    }

    /**
     * Login UI mode from pretty path (e.g. save).
     *
     * @return string
     */
    public static function request_login_mode()
    {
        $mode = get_query_var(self::QV_LOGIN_MODE, '');
        if ($mode === '' || $mode === false || $mode === null) {
            return '';
        }
        return sanitize_key((string) $mode);
    }

    /**
     * 301 legacy query-string board URLs to pretty paths.
     */
    public static function redirect_legacy_query_urls()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path        = (string) wp_parse_url($request_uri, PHP_URL_PATH);

        // Already on a pretty subpath — leave rewrite-injected query alone.
        if (preg_match('#/(apply|save|edit|applications|lost-password|reset-password)(/|$)#i', $path)) {
            // Still strip bare ?mjb_a= when path already has /apply/{token}/
            return;
        }

        // Apply token on login / registration pages.
        if (!empty($_GET[self::QV_APPLY_TOKEN]) && is_singular('page')) {
            $token = sanitize_text_field(wp_unslash($_GET[self::QV_APPLY_TOKEN]));
            if (preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
                $page_id = (int) get_queried_object_id();
                $login_id = (int) get_option('mjb_candidate_login_page_id', 0);
                $reg_id   = (int) get_option('mjb_candidate_registration_page_id', 0);
                if ($page_id > 0 && ($page_id === $login_id || $page_id === $reg_id)) {
                    $base = get_permalink($page_id);
                    $target = self::apply_intent_url($base, strtolower($token));
                    // Preserve other args except mjb_a.
                    $keep = $_GET;
                    unset($keep[self::QV_APPLY_TOKEN]);
                    if (!empty($keep)) {
                        $clean = array();
                        foreach ($keep as $k => $v) {
                            if (is_scalar($v)) {
                                $clean[sanitize_key((string) $k)] = sanitize_text_field(wp_unslash((string) $v));
                            }
                        }
                        if (!empty($clean)) {
                            $target = add_query_arg($clean, $target);
                        }
                    }
                    wp_safe_redirect($target, 301);
                    exit;
                }
            }
        }

        // Dashboard view applications.
        if (
            is_singular('page')
            && isset($_GET['action'])
            && sanitize_key(wp_unslash($_GET['action'])) === 'view_applications'
            && isset($_GET['job_id'])
        ) {
            $page_id = (int) get_queried_object_id();
            $dash_id = (int) get_option('mjb_employer_dashboard_page_id', 0);
            if ($page_id > 0 && $page_id === $dash_id) {
                $job_id = (int) $_GET['job_id'];
                $query  = array();
                if (!empty($_GET['mjb_notice'])) {
                    $query['mjb_notice'] = sanitize_key(wp_unslash($_GET['mjb_notice']));
                }
                wp_safe_redirect(self::dashboard_applications_url($job_id, $query), 301);
                exit;
            }
        }

        // Job form edit.
        if (
            is_singular('page')
            && isset($_GET['action'])
            && sanitize_key(wp_unslash($_GET['action'])) === 'edit'
            && isset($_GET['job_id'])
        ) {
            $page_id = (int) get_queried_object_id();
            $form_id = (int) get_option('mjb_job_form_page_id', 0);
            if ($page_id > 0 && $page_id === $form_id) {
                wp_safe_redirect(self::job_edit_url((int) $_GET['job_id']), 301);
                exit;
            }
        }
    }

    /**
     * Handle pretty /jobs/apply/{token}/ and /jobs/save/{id}/ actions.
     */
    public static function handle_front_actions()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $apply_token = get_query_var(self::QV_FRONT_APPLY, '');
        if ($apply_token !== '' && $apply_token !== false && $apply_token !== null) {
            self::run_front_apply(sanitize_text_field((string) $apply_token));
            return;
        }

        $save_id = get_query_var(self::QV_FRONT_SAVE, '');
        if ($save_id !== '' && $save_id !== false && $save_id !== null) {
            self::run_front_save((int) $save_id);
        }
    }

    /**
     * @param string $token
     */
    private static function run_front_apply($token)
    {
        $token = strtolower(trim($token));
        // Populate request so application helpers see the token.
        $_GET[self::QV_APPLY_TOKEN] = $token;
        $_REQUEST[self::QV_APPLY_TOKEN] = $token;

        if (!class_exists('MJB_Applications')) {
            wp_safe_redirect(home_url('/jobs/'));
            exit;
        }

        $job_id = MJB_Applications::parse_apply_token($token);
        $redirect = $job_id > 0 ? get_permalink($job_id) : MJB_Page_Resolver::get_jobs_page_url();

        if ($job_id <= 0 || !wp_verify_nonce(
            isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '',
            'mjb_apply_to_job'
        )) {
            MJB_Notices::redirect($redirect ?: home_url('/'), 'error_security');
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(MJB_Applications::get_apply_login_url($job_id));
            exit;
        }

        if (!MJB_Applications::current_user_can_apply()) {
            MJB_Notices::redirect($redirect, 'error_login_not_candidate');
        }

        $result = MJB_Applications::create_application_from_profile($job_id, get_current_user_id());
        if (is_wp_error($result)) {
            MJB_Notices::redirect($redirect, $result->get_error_code());
        }

        // One-time token.
        delete_transient(MJB_Applications::APPLY_TOKEN_TRANSIENT_PREFIX . $token);

        MJB_Notices::redirect($redirect, 'success_application');
    }

    /**
     * @param int $job_id
     */
    private static function run_front_save($job_id)
    {
        $job_id = (int) $job_id;

        if (!class_exists('MJB_Saved_Jobs')) {
            wp_safe_redirect(MJB_Page_Resolver::get_jobs_page_url());
            exit;
        }

        // Delegate to the same handler logic as admin-post (redirect_to aware).
        $_GET['job_id'] = $job_id;
        $_REQUEST['job_id'] = $job_id;
        MJB_Saved_Jobs::handle_toggle();
    }
}
