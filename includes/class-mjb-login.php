<?php
/**
 * Frontend candidate / recruiter login forms (with registration CTAs).
 *
 * Layout intentionally mirrors templates/archive-job.php + Filter Jobs panel.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Login
{
    const CANDIDATE_PAGE_OPTION = 'mjb_candidate_login_page_id';
    const EMPLOYER_PAGE_OPTION  = 'mjb_employer_login_page_id';

    /** Query var for lost / reset password screens (pretty path or legacy ?mjb_pw=). */
    const PW_QUERY_VAR = 'mjb_pw';

    /**
     * Initialize shortcodes and form handlers.
     */
    public function init()
    {
        add_shortcode('mjb_candidate_login', array($this, 'output_candidate_login'));
        add_shortcode('mjb_employer_login', array($this, 'output_employer_login'));
        add_action('init', array(__CLASS__, 'register_rewrites'));
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('template_redirect', array($this, 'redirect_legacy_pw_query'), 5);
        add_action('init', array($this, 'handle_login'));
        add_action('init', array($this, 'handle_lost_password'));
        add_action('init', array($this, 'handle_reset_password'));
        add_filter('retrieve_password_message', array($this, 'filter_reset_email_message'), 10, 4);
        add_filter('lostpassword_url', array($this, 'filter_lostpassword_url'), 10, 2);
        add_action('login_form_rp', array($this, 'redirect_core_reset_to_frontend'));
        add_action('login_form_resetpass', array($this, 'redirect_core_reset_to_frontend'));
    }

    /**
     * Pretty auth subpaths: /jobs/candidate-login/lost-password/, …/reset-password/.
     */
    public static function register_rewrites()
    {
        $pairs = array(
            array(
                'option'  => self::CANDIDATE_PAGE_OPTION,
                'fallback' => 'jobs/candidate-login',
            ),
            array(
                'option'  => self::EMPLOYER_PAGE_OPTION,
                'fallback' => 'jobs/recruiter-login',
            ),
        );

        foreach ($pairs as $pair) {
            $page_id = (int) get_option($pair['option'], 0);
            $uri     = '';
            if ($page_id > 0) {
                $uri = (string) get_page_uri($page_id);
            }
            if ($uri === '') {
                $uri = $pair['fallback'];
            }
            $uri = untrailingslashit(trim($uri, '/'));
            if ($uri === '') {
                continue;
            }

            $uri_q = preg_quote($uri, '/');
            $target = $page_id > 0
                ? 'index.php?page_id=' . $page_id . '&' . self::PW_QUERY_VAR . '='
                : 'index.php?pagename=' . $uri . '&' . self::PW_QUERY_VAR . '=';

            add_rewrite_rule(
                '^' . $uri_q . '/lost-password/?$',
                $target . 'lost',
                'top'
            );
            add_rewrite_rule(
                '^' . $uri_q . '/reset-password/?$',
                $target . 'reset',
                'top'
            );
        }
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function register_query_vars($vars)
    {
        $vars[] = self::PW_QUERY_VAR;
        return $vars;
    }

    /**
     * Resolve lost|reset from pretty path or legacy ?mjb_pw=.
     *
     * @return string ''|lost|reset
     */
    private function get_pw_mode()
    {
        $mode = get_query_var(self::PW_QUERY_VAR, '');
        if ($mode === '' || $mode === false || $mode === null) {
            $mode = isset($_GET[self::PW_QUERY_VAR])
                ? sanitize_key(wp_unslash($_GET[self::PW_QUERY_VAR]))
                : '';
        } else {
            $mode = sanitize_key((string) $mode);
        }

        // Path-friendly aliases (if ever used as query values).
        if ($mode === 'lost-password') {
            $mode = 'lost';
        }
        if ($mode === 'reset-password') {
            $mode = 'reset';
        }

        if ($mode !== 'lost' && $mode !== 'reset') {
            return '';
        }

        return $mode;
    }

    /**
     * 301 legacy ?mjb_pw=lost|reset on the base login URL to pretty subpaths.
     * Pretty paths may still set mjb_pw via rewrite → $_GET; skip those.
     */
    public function redirect_legacy_pw_query()
    {
        if (empty($_GET[self::PW_QUERY_VAR])) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path        = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        // Already on /lost-password/ or /reset-password/ (rewrite may inject mjb_pw into query).
        if (preg_match('#/(lost-password|reset-password)/?$#i', $path)) {
            return;
        }

        $mode = sanitize_key(wp_unslash($_GET[self::PW_QUERY_VAR]));
        if ($mode === 'lost-password') {
            $mode = 'lost';
        }
        if ($mode === 'reset-password') {
            $mode = 'reset';
        }
        if ($mode !== 'lost' && $mode !== 'reset') {
            return;
        }

        $role = $this->resolve_login_role_for_current_page();
        if ($role === '') {
            return;
        }

        if ($mode === 'lost') {
            $target = $this->get_lost_password_url($role);
        } else {
            $key    = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
            $login  = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login'])) : '';
            $target = $this->get_reset_password_url($role, $key, $login);
        }

        // Preserve notice / other args (not mjb_pw, key, login — those are already on $target).
        $keep = $_GET;
        unset($keep[self::PW_QUERY_VAR], $keep['key'], $keep['login']);
        if (!empty($keep)) {
            $clean = array();
            foreach ($keep as $k => $v) {
                if (!is_scalar($v)) {
                    continue;
                }
                $clean[sanitize_key((string) $k)] = sanitize_text_field(wp_unslash((string) $v));
            }
            if (!empty($clean)) {
                $target = add_query_arg($clean, $target);
            }
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    /**
     * Which login shortcode page is being viewed (candidate|employer|'').
     *
     * @return string
     */
    private function resolve_login_role_for_current_page()
    {
        if (!is_singular('page')) {
            return '';
        }
        $page_id = (int) get_queried_object_id();
        if ($page_id <= 0) {
            return '';
        }
        if ($page_id === (int) get_option(self::CANDIDATE_PAGE_OPTION, 0)) {
            return 'candidate';
        }
        if ($page_id === (int) get_option(self::EMPLOYER_PAGE_OPTION, 0)) {
            return 'employer';
        }

        // Fallback: detect by shortcode in content.
        $post = get_post($page_id);
        if (!$post) {
            return '';
        }
        if (has_shortcode($post->post_content, 'mjb_candidate_login')) {
            return 'candidate';
        }
        if (has_shortcode($post->post_content, 'mjb_employer_login')) {
            return 'employer';
        }

        return '';
    }

    /**
     * Handle frontend login POSTs.
     */
    public function handle_login()
    {
        if (empty($_POST['mjb_login_submit']) || empty($_POST['mjb_login_nonce'])) {
            return;
        }

        $role = isset($_POST['mjb_login_role']) ? sanitize_key(wp_unslash($_POST['mjb_login_role'])) : '';
        if ($role !== 'candidate' && $role !== 'employer') {
            return;
        }

        $login_page = $this->get_login_page_url($role);
        $success_redirect = $this->resolve_post_login_redirect($role);
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_login_nonce'])), 'mjb_login_' . $role)) {
            MJB_Notices::redirect($login_page, 'error_security');
        }

        if (is_user_logged_in()) {
            MJB_Notices::redirect($success_redirect, 'success_login');
        }

        $login = isset($_POST['mjb_login']) ? sanitize_text_field(wp_unslash($_POST['mjb_login'])) : '';
        $password = isset($_POST['mjb_password']) ? (string) wp_unslash($_POST['mjb_password']) : '';
        $remember = !empty($_POST['mjb_remember']);

        if ($login === '' || $password === '') {
            MJB_Notices::redirect($login_page, 'error_missing_fields');
        }

        $user_login = $login;
        if (is_email($login)) {
            $by_email = get_user_by('email', $login);
            if ($by_email) {
                $user_login = $by_email->user_login;
            }
        }

        $user = wp_signon(
            array(
                'user_login'    => $user_login,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            is_ssl()
        );

        if (is_wp_error($user)) {
            MJB_Notices::redirect($login_page, 'error_login_failed');
        }

        $roles = (array) $user->roles;
        $is_admin = user_can($user, 'manage_options');
        $has_role = in_array($role, $roles, true);

        if (!$has_role && !$is_admin) {
            wp_logout();
            MJB_Notices::redirect($login_page, $role === 'candidate' ? 'error_login_not_candidate' : 'error_login_not_employer');
        }

        // Opaque apply token: submit application then land on the job (no public IDs).
        if ($role === 'candidate' && class_exists('MJB_Applications')) {
            if (MJB_Applications::complete_apply_after_auth((int) $user->ID)) {
                return;
            }
        }

        MJB_Notices::redirect($success_redirect, 'success_login');
    }

    /**
     * Request a password reset email (username is included in the email).
     */
    public function handle_lost_password()
    {
        if (empty($_POST['mjb_lost_password_submit']) || empty($_POST['mjb_lost_password_nonce'])) {
            return;
        }

        $role = isset($_POST['mjb_login_role']) ? sanitize_key(wp_unslash($_POST['mjb_login_role'])) : 'candidate';
        if ($role !== 'candidate' && $role !== 'employer') {
            $role = 'candidate';
        }

        $login_page = $this->get_login_page_url($role);
        $lost_page = $this->get_lost_password_url($role);

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_lost_password_nonce'])), 'mjb_lost_password_' . $role)) {
            MJB_Notices::redirect($lost_page, 'error_security');
        }

        $login = isset($_POST['mjb_user_login']) ? sanitize_text_field(wp_unslash($_POST['mjb_user_login'])) : '';
        if ($login === '') {
            MJB_Notices::redirect($lost_page, 'error_missing_fields');
        }

        // Remember which login page should receive the reset link in the email.
        $GLOBALS['mjb_password_reset_role'] = $role;

        if (!function_exists('retrieve_password')) {
            require_once ABSPATH . WPINC . '/user.php';
        }

        // Always show the same success copy (avoids account enumeration).
        retrieve_password($login);
        unset($GLOBALS['mjb_password_reset_role']);

        MJB_Notices::redirect($login_page, 'success_password_reset_email');
    }

    /**
     * Set a new password from an emailed reset link.
     */
    public function handle_reset_password()
    {
        if (empty($_POST['mjb_reset_password_submit']) || empty($_POST['mjb_reset_password_nonce'])) {
            return;
        }

        $role = isset($_POST['mjb_login_role']) ? sanitize_key(wp_unslash($_POST['mjb_login_role'])) : 'candidate';
        if ($role !== 'candidate' && $role !== 'employer') {
            $role = 'candidate';
        }

        $login_page = $this->get_login_page_url($role);
        $key = isset($_POST['mjb_rp_key']) ? sanitize_text_field(wp_unslash($_POST['mjb_rp_key'])) : '';
        $user_login = isset($_POST['mjb_rp_login']) ? sanitize_user(wp_unslash($_POST['mjb_rp_login'])) : '';
        $reset_page = $this->get_reset_password_url($role, $key, $user_login);

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_reset_password_nonce'])), 'mjb_reset_password_' . $role)) {
            MJB_Notices::redirect($login_page, 'error_security');
        }

        $password = isset($_POST['mjb_password']) ? (string) wp_unslash($_POST['mjb_password']) : '';
        $password_confirm = isset($_POST['mjb_password_confirm']) ? (string) wp_unslash($_POST['mjb_password_confirm']) : '';

        if ($password === '' || $password_confirm === '') {
            MJB_Notices::redirect($reset_page, 'error_missing_fields');
        }

        if ($password !== $password_confirm) {
            MJB_Notices::redirect($reset_page, 'error_password_mismatch');
        }

        if (strlen($password) < 8) {
            MJB_Notices::redirect($reset_page, 'error_password_weak');
        }

        $user = check_password_reset_key($key, $user_login);
        if (is_wp_error($user)) {
            MJB_Notices::redirect($this->get_lost_password_url($role), 'error_password_reset_key');
        }

        reset_password($user, $password);
        MJB_Notices::redirect($login_page, 'success_password_reset');
    }

    /**
     * Point the reset email at the board login page (not wp-login.php).
     *
     * @param string  $message
     * @param string  $key
     * @param string  $user_login
     * @param WP_User $user_data
     * @return string
     */
    public function filter_reset_email_message($message, $key, $user_login, $user_data)
    {
        unset($user_data);

        $role = 'candidate';
        if (!empty($GLOBALS['mjb_password_reset_role']) && in_array($GLOBALS['mjb_password_reset_role'], array('candidate', 'employer'), true)) {
            $role = $GLOBALS['mjb_password_reset_role'];
        }

        $reset_url = $this->get_reset_password_url($role, $key, $user_login);

        $lines = array(
            __('Someone requested a password reset for the following account:', 'modern-job-board'),
            '',
            sprintf(
                /* translators: %s: site name */
                __('Site: %s', 'modern-job-board'),
                wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
            ),
            sprintf(
                /* translators: %s: user login */
                __('Username: %s', 'modern-job-board'),
                $user_login
            ),
            '',
            __('If this was a mistake, ignore this email and nothing will change.', 'modern-job-board'),
            '',
            __('To set a new password, visit:', 'modern-job-board'),
            $reset_url,
            '',
        );

        return implode("\r\n", $lines);
    }

    /**
     * Prefer board lost-password screen when core asks for lostpassword_url.
     *
     * @param string $url
     * @param string $redirect
     * @return string
     */
    public function filter_lostpassword_url($url, $redirect)
    {
        unset($redirect);
        return $this->get_lost_password_url('candidate');
    }

    /**
     * If a user hits wp-login.php?action=rp, send them to the board reset form.
     */
    public function redirect_core_reset_to_frontend()
    {
        $key = isset($_REQUEST['key']) ? sanitize_text_field(wp_unslash($_REQUEST['key'])) : '';
        $login = isset($_REQUEST['login']) ? sanitize_user(wp_unslash($_REQUEST['login'])) : '';
        if ($key === '' || $login === '') {
            return;
        }

        $role = isset($_REQUEST['role']) ? sanitize_key(wp_unslash($_REQUEST['role'])) : 'candidate';
        if ($role !== 'employer') {
            $role = 'candidate';
        }

        wp_safe_redirect($this->get_reset_password_url($role, $key, $login));
        exit;
    }

    /**
     * Prefer redirect_to / apply-job context, else dashboard.
     *
     * @param string $role
     * @return string
     */
    private function resolve_post_login_redirect($role)
    {
        $fallback = $this->get_dashboard_url($role);
        if ($role === 'candidate' && class_exists('MJB_Applications')) {
            return MJB_Applications::resolve_auth_redirect($fallback);
        }

        $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
        if ($redirect !== '') {
            $validated = wp_validate_redirect($redirect, false);
            if ($validated) {
                return $validated;
            }
        }

        return $fallback;
    }

    /**
     * @param array $atts
     * @return string
     */
    public function output_candidate_login($atts)
    {
        unset($atts);
        return $this->render_login_form('candidate');
    }

    /**
     * @param array $atts
     * @return string
     */
    public function output_employer_login($atts)
    {
        unset($atts);
        return $this->render_login_form('employer');
    }

    /**
     * Full jobs-board layout: teal hero + listing container + sidebar filter panel.
     *
     * @param string $role candidate|employer
     * @return string
     */
    private function render_login_form($role)
    {
        $role = $role === 'employer' ? 'employer' : 'candidate';
        $dashboard_url = $this->get_dashboard_url($role);
        $register_url  = $this->get_registration_url($role);
        $form_id       = 'mjb-login-' . $role;
        $apply_token   = class_exists('MJB_Applications') ? MJB_Applications::request_apply_token() : '';
        $apply_job_id  = class_exists('MJB_Applications') ? MJB_Applications::request_apply_job_id() : 0;
        $redirect_to   = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';

        if ($role === 'candidate' && $apply_job_id > 0 && class_exists('MJB_Applications')) {
            $register_url = MJB_Applications::get_apply_register_url($apply_job_id);
        }

        // Second column is always “Register” (role-specific copy stays in the intro).
        $register_title = __('Register', 'modern-job-board');
        $register_icon = 'user-plus';
        $register_label = __('Register', 'modern-job-board');

        if ($role === 'employer') {
            $hero_title = __('Recruiter login', 'modern-job-board');
            $hero_intro = __('Sign in to manage jobs and applications.', 'modern-job-board');
            $register_intro = __('Not registered yet? Post open jobs and hire top talent.', 'modern-job-board');
        } else {
            $hero_title = __('Job seeker login', 'modern-job-board');
            $hero_intro = __('Sign in to manage your profile and applications.', 'modern-job-board');
            $register_intro = __('Not registered yet? Create a profile and get hired by top recruiters.', 'modern-job-board');
        }

        $login_title = __('Sign in', 'modern-job-board');
        $login_intro = $role === 'employer'
            ? __('Already have a recruiter account? Continue below.', 'modern-job-board')
            : __('Already have a job seeker account? Continue below.', 'modern-job-board');

        $pw_mode = $this->get_pw_mode();
        $login_mode = class_exists('MJB_Pretty_Urls') ? MJB_Pretty_Urls::request_login_mode() : '';
        if ($pw_mode === 'reset') {
            $hero_title = __('Set a new password', 'modern-job-board');
            $hero_intro = __('Choose a new password for your account. Your username is shown in the reset email.', 'modern-job-board');
        } elseif ($pw_mode === 'lost') {
            $hero_title = __('Forgot username or password?', 'modern-job-board');
            $hero_intro = __('Enter the email or username for your account. We will email reset instructions and your username.', 'modern-job-board');
        } elseif ($login_mode === 'save' && $role === 'candidate') {
            $hero_title = __('Sign in to save jobs', 'modern-job-board');
            $hero_intro = __('Sign in as a job seeker to save jobs to your list.', 'modern-job-board');
        }

        $auth_area_class = 'mjb-content-area mjb-content-area--auth';
        if ($pw_mode === 'lost' || $pw_mode === 'reset') {
            // Single wider card (recover / set password), not the 1:2 sign-in + register pair.
            $auth_area_class .= ' mjb-content-area--auth-single';
        }

        ob_start();

        // Same hero helper the jobs archive uses (padding + teal surface).
        MJB_Shortcodes::render_content_hero($hero_title, $hero_intro);
        ?>
        <div class="mjb-container mjb-container--listing mjb-container--auth">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo MJB_Notices::render();
            ?>

            <div class="<?php echo esc_attr($auth_area_class); ?>">
                <?php if ($pw_mode === 'lost' && !is_user_logged_in()) : ?>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
                    echo $this->render_lost_password_card($role);
                    ?>
                <?php elseif ($pw_mode === 'reset' && !is_user_logged_in()) : ?>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
                    echo $this->render_reset_password_card($role);
                    ?>
                <?php elseif (is_user_logged_in()) : ?>
                    <?php
                    $user = wp_get_current_user();
                    $roles = (array) $user->roles;
                    $is_admin = user_can($user, 'manage_options');
                    $has_role = in_array($role, $roles, true);
                    ?>
                    <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--login mjb-audience-card--<?php echo esc_attr($role === 'employer' ? 'employers' : 'seekers'); ?>">
                        <header class="mjb-audience-card__header">
                            <span class="mjb-audience-card__icon" aria-hidden="true">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                echo MJB_Icons::render('log-in', 24);
                                ?>
                            </span>
                            <h2 class="mjb-audience-card__title">
                                <?php
                                echo $has_role || $is_admin
                                    ? esc_html__('You are signed in', 'modern-job-board')
                                    : esc_html__('Wrong account type', 'modern-job-board');
                                ?>
                            </h2>
                        </header>
                        <p class="mjb-audience-card__intro">
                            <?php
                            if ($has_role || $is_admin) {
                                echo $role === 'employer'
                                    ? esc_html__('Continue to your recruiter dashboard to manage jobs and applications.', 'modern-job-board')
                                    : esc_html__('Continue to your candidate dashboard to manage your profile and applications.', 'modern-job-board');
                            } else {
                                echo $role === 'employer'
                                    ? esc_html__('You are signed in, but this page is for recruiters.', 'modern-job-board')
                                    : esc_html__('You are signed in, but this page is for job seekers.', 'modern-job-board');
                            }
                            ?>
                        </p>
                        <div class="mjb-audience-card__actions mjb-audience-card__actions--inline">
                            <?php if ($has_role || $is_admin) : ?>
                                <?php
                                $continue_url = $dashboard_url;
                                if ($role === 'candidate' && $apply_job_id > 0 && class_exists('MJB_Applications')) {
                                    $continue_url = MJB_Applications::get_apply_url($apply_job_id);
                                } elseif ($redirect_to !== '') {
                                    $validated = wp_validate_redirect($redirect_to, false);
                                    if ($validated) {
                                        $continue_url = $validated;
                                    }
                                }
                                ?>
                                <a class="btn btn-primary btn-sm" href="<?php echo esc_url($continue_url); ?>">
                                    <?php
                                    echo $apply_job_id > 0
                                        ? esc_html__('Continue applying', 'modern-job-board')
                                        : esc_html__('Go to dashboard', 'modern-job-board');
                                    ?>
                                </a>
                            <?php else : ?>
                                <a class="btn btn-primary btn-sm" href="<?php echo esc_url(wp_logout_url($this->get_login_page_url($role))); ?>">
                                    <?php esc_html_e('Sign out and continue', 'modern-job-board'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php else : ?>
                    <?php /* Same shell as the register card: audience-card header + body + actions */ ?>
                    <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--login mjb-audience-card--<?php echo esc_attr($role === 'employer' ? 'employers' : 'seekers'); ?>">
                        <header class="mjb-audience-card__header">
                            <span class="mjb-audience-card__icon" aria-hidden="true">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                echo MJB_Icons::render('log-in', 24);
                                ?>
                            </span>
                            <h2 class="mjb-audience-card__title"><?php echo esc_html($login_title); ?></h2>
                        </header>
                        <p class="mjb-audience-card__intro"><?php echo esc_html($login_intro); ?></p>

                        <form
                            id="<?php echo esc_attr($form_id); ?>"
                            class="mjb-auth-card-form"
                            method="post"
                            action=""
                            novalidate
                        >
                            <?php wp_nonce_field('mjb_login_' . $role, 'mjb_login_nonce'); ?>
                            <input type="hidden" name="mjb_login_role" value="<?php echo esc_attr($role); ?>">
                            <?php if ($redirect_to !== '') : ?>
                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                            <?php endif; ?>
                            <?php if ($apply_token !== '') : ?>
                                <input type="hidden" name="<?php echo esc_attr(MJB_Applications::APPLY_TOKEN_QUERY); ?>" value="<?php echo esc_attr($apply_token); ?>">
                                <p class="mjb-auth-apply-note">
                                    <?php esc_html_e('Sign in to finish applying for this job. Your profile and resume will be used for the application.', 'modern-job-board'); ?>
                                </p>
                            <?php endif; ?>

                            <div class="mjb-auth-card-fields">
                                <div class="mjb-auth-card-fields__row">
                                    <p>
                                        <label for="<?php echo esc_attr($form_id); ?>-login"><?php esc_html_e('Email or username', 'modern-job-board'); ?></label>
                                        <input
                                            type="text"
                                            name="mjb_login"
                                            id="<?php echo esc_attr($form_id); ?>-login"
                                            placeholder="<?php esc_attr_e('Email or username', 'modern-job-board'); ?>"
                                            value=""
                                            required
                                            aria-required="true"
                                            autocomplete="username"
                                            autocapitalize="off"
                                        >
                                    </p>
                                    <p>
                                        <label for="<?php echo esc_attr($form_id); ?>-password"><?php esc_html_e('Password', 'modern-job-board'); ?></label>
                                        <input
                                            type="password"
                                            name="mjb_password"
                                            id="<?php echo esc_attr($form_id); ?>-password"
                                            placeholder="<?php esc_attr_e('Password', 'modern-job-board'); ?>"
                                            value=""
                                            required
                                            aria-required="true"
                                            autocomplete="current-password"
                                        >
                                    </p>
                                </div>
                                <p class="mjb-auth-card-check mjb-auth-card-meta">
                                    <label for="<?php echo esc_attr($form_id); ?>-remember">
                                        <input type="checkbox" name="mjb_remember" id="<?php echo esc_attr($form_id); ?>-remember" value="1">
                                        <?php esc_html_e('Remember me', 'modern-job-board'); ?>
                                    </label>
                                    <a class="mjb-auth-forgot-link" href="<?php echo esc_url($this->get_lost_password_url($role)); ?>">
                                        <?php esc_html_e('Forgot username or password?', 'modern-job-board'); ?>
                                    </a>
                                </p>
                            </div>

                            <div class="mjb-audience-card__actions mjb-audience-card__actions--inline">
                                <button type="submit" name="mjb_login_submit" value="1" class="btn btn-primary btn-sm">
                                    <?php esc_html_e('Continue', 'modern-job-board'); ?>
                                </button>
                            </div>
                        </form>
                    </article>

                    <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--register mjb-audience-card--<?php echo esc_attr($role === 'employer' ? 'employers' : 'seekers'); ?>">
                        <header class="mjb-audience-card__header">
                            <span class="mjb-audience-card__icon" aria-hidden="true">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                echo MJB_Icons::render($register_icon, 24);
                                ?>
                            </span>
                            <h2 class="mjb-audience-card__title"><?php echo esc_html($register_title); ?></h2>
                        </header>
                        <p class="mjb-audience-card__intro"><?php echo esc_html($register_intro); ?></p>
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
                        echo $this->render_register_benefits($role);
                        ?>
                        <div class="mjb-audience-card__actions mjb-audience-card__actions--inline">
                            <a href="<?php echo esc_url($register_url); ?>" class="btn btn-primary btn-sm">
                                <?php echo esc_html($register_label); ?>
                            </a>
                            <?php if ($role === 'candidate') : ?>
                                <a href="<?php echo esc_url(MJB_Page_Resolver::get_jobs_page_url()); ?>" class="btn btn-outline btn-sm">
                                    <?php esc_html_e('Find jobs', 'modern-job-board'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param string $role
     * @return string
     */
    public function get_login_page_url($role)
    {
        return self::get_frontend_login_url($role);
    }

    /**
     * Frontend login page URL (never wp-login.php).
     *
     * @param string $role        candidate|employer
     * @param string $redirect_to Optional return URL after successful login.
     * @return string
     */
    public static function get_frontend_login_url($role = 'candidate', $redirect_to = '')
    {
        $role = $role === 'employer' ? 'employer' : 'candidate';
        $query = array();
        $redirect_to = is_string($redirect_to) ? trim($redirect_to) : '';
        if ($redirect_to !== '') {
            $query['redirect_to'] = $redirect_to;
        }

        if ($role === 'employer') {
            return MJB_Page_Resolver::get_page_url(
                'mjb_employer_login',
                self::EMPLOYER_PAGE_OPTION,
                $query,
                '/jobs/recruiter-login/'
            );
        }

        return MJB_Page_Resolver::get_page_url(
            'mjb_candidate_login',
            self::CANDIDATE_PAGE_OPTION,
            $query,
            '/jobs/candidate-login/'
        );
    }

    /**
     * Role-specific “what you get” bullets for the Register column.
     *
     * @param string $role candidate|employer
     * @return string
     */
    private function render_register_benefits($role)
    {
        if ($role === 'employer') {
            $items = array(
                array(
                    'icon' => 'briefcase',
                    'text' => __('Post jobs and manage applicants in one place', 'modern-job-board'),
                ),
                array(
                    'icon' => 'building',
                    'text' => __('Build a company page candidates can browse', 'modern-job-board'),
                ),
                array(
                    'icon' => 'inbox',
                    'text' => __('Review applications and update hiring status', 'modern-job-board'),
                ),
                array(
                    'icon' => 'map-pin',
                    'text' => __('Get found in location, category, and type search', 'modern-job-board'),
                ),
                array(
                    'icon' => 'calendar',
                    'text' => __('Control listing duration and expiry dates', 'modern-job-board'),
                ),
                array(
                    'icon' => 'check',
                    'text' => __('Free to start - upgrade when you need more tools', 'modern-job-board'),
                ),
            );
        } else {
            $items = array(
                array(
                    'icon' => 'bookmark',
                    'text' => __('Save jobs and pick up where you left off', 'modern-job-board'),
                ),
                array(
                    'icon' => 'file-pen',
                    'text' => __('Apply with your profile and resume', 'modern-job-board'),
                ),
                array(
                    'icon' => 'inbox',
                    'text' => __('Track applications from your dashboard', 'modern-job-board'),
                ),
                array(
                    'icon' => 'map-pin',
                    'text' => __('Filter by keywords, location, category, type, or company', 'modern-job-board'),
                ),
                array(
                    'icon' => 'user',
                    'text' => __('Keep one job seeker profile ready for recruiters', 'modern-job-board'),
                ),
                array(
                    'icon' => 'check',
                    'text' => __('Free to join - no card required', 'modern-job-board'),
                ),
            );
        }

        ob_start();
        ?>
        <ul class="mjb-register-benefits" aria-label="<?php esc_attr_e('What you get when you register', 'modern-job-board'); ?>">
            <?php foreach ($items as $item) : ?>
                <li class="mjb-register-benefits__item">
                    <span class="mjb-register-benefits__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo MJB_Icons::render($item['icon'], 16);
                        ?>
                    </span>
                    <span class="mjb-register-benefits__text"><?php echo esc_html($item['text']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Lost-password form URL for a login role.
     * Pretty path: /jobs/candidate-login/lost-password/
     *
     * @param string $role
     * @return string
     */
    public function get_lost_password_url($role)
    {
        return trailingslashit($this->get_login_page_url($role)) . 'lost-password/';
    }

    /**
     * Reset-password form URL (from email link).
     * Pretty path: /jobs/candidate-login/reset-password/?key=…&login=…
     *
     * @param string $role
     * @param string $key
     * @param string $user_login
     * @return string
     */
    public function get_reset_password_url($role, $key, $user_login)
    {
        $base = trailingslashit($this->get_login_page_url($role)) . 'reset-password/';
        $args = array();
        if ($key !== '') {
            $args['key'] = $key;
        }
        if ($user_login !== '') {
            $args['login'] = $user_login;
        }
        return empty($args) ? $base : add_query_arg($args, $base);
    }

    /**
     * Request reset email form.
     *
     * @param string $role
     * @return string
     */
    private function render_lost_password_card($role)
    {
        $role = $role === 'employer' ? 'employer' : 'candidate';
        $form_id = 'mjb-lost-password-' . $role;
        $login_url = $this->get_login_page_url($role);

        ob_start();
        ?>
        <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--login mjb-audience-card--recover mjb-audience-card--<?php echo esc_attr($role === 'employer' ? 'employers' : 'seekers'); ?>">
            <header class="mjb-audience-card__header">
                <span class="mjb-audience-card__icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo MJB_Icons::render('mail', 24);
                    ?>
                </span>
                <h2 class="mjb-audience-card__title"><?php esc_html_e('Recover account', 'modern-job-board'); ?></h2>
            </header>
            <p class="mjb-audience-card__intro">
                <?php esc_html_e('Enter the email address or username you used to register. We will send a reset link and remind you of your username.', 'modern-job-board'); ?>
            </p>
            <form id="<?php echo esc_attr($form_id); ?>" class="mjb-auth-card-form" method="post" action="" novalidate>
                <?php wp_nonce_field('mjb_lost_password_' . $role, 'mjb_lost_password_nonce'); ?>
                <input type="hidden" name="mjb_login_role" value="<?php echo esc_attr($role); ?>">
                <div class="mjb-auth-card-fields">
                    <p>
                        <label for="<?php echo esc_attr($form_id); ?>-login"><?php esc_html_e('Email or username', 'modern-job-board'); ?></label>
                        <input
                            type="text"
                            name="mjb_user_login"
                            id="<?php echo esc_attr($form_id); ?>-login"
                            placeholder="<?php esc_attr_e('Email or username', 'modern-job-board'); ?>"
                            value=""
                            required
                            aria-required="true"
                            autocomplete="username"
                            autocapitalize="off"
                        >
                    </p>
                </div>
                <div class="mjb-audience-card__actions mjb-audience-card__actions--inline">
                    <button type="submit" name="mjb_lost_password_submit" value="1" class="btn btn-primary btn-sm">
                        <?php esc_html_e('Email reset link', 'modern-job-board'); ?>
                    </button>
                    <a href="<?php echo esc_url($login_url); ?>" class="btn btn-outline btn-sm">
                        <?php esc_html_e('Back to sign in', 'modern-job-board'); ?>
                    </a>
                </div>
            </form>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Choose a new password after clicking the email link.
     *
     * @param string $role
     * @return string
     */
    private function render_reset_password_card($role)
    {
        $role = $role === 'employer' ? 'employer' : 'candidate';
        $form_id = 'mjb-reset-password-' . $role;
        $login_url = $this->get_login_page_url($role);
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $user_login = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login'])) : '';

        $user = ($key !== '' && $user_login !== '') ? check_password_reset_key($key, $user_login) : new WP_Error('invalid_key');
        $key_ok = !is_wp_error($user);

        ob_start();
        ?>
        <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--login mjb-audience-card--recover mjb-audience-card--<?php echo esc_attr($role === 'employer' ? 'employers' : 'seekers'); ?>">
            <header class="mjb-audience-card__header">
                <span class="mjb-audience-card__icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo MJB_Icons::render('settings', 24);
                    ?>
                </span>
                <h2 class="mjb-audience-card__title"><?php esc_html_e('New password', 'modern-job-board'); ?></h2>
            </header>
            <?php if (!$key_ok) : ?>
                <p class="mjb-audience-card__intro">
                    <?php esc_html_e('This password reset link is invalid or has expired.', 'modern-job-board'); ?>
                </p>
                <div class="mjb-audience-card__actions mjb-audience-card__actions--inline">
                    <a href="<?php echo esc_url($this->get_lost_password_url($role)); ?>" class="btn btn-primary btn-sm">
                        <?php esc_html_e('Request a new link', 'modern-job-board'); ?>
                    </a>
                    <a href="<?php echo esc_url($login_url); ?>" class="btn btn-outline btn-sm">
                        <?php esc_html_e('Back to sign in', 'modern-job-board'); ?>
                    </a>
                </div>
            <?php else : ?>
                <p class="mjb-audience-card__intro">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: username */
                        __('Setting a new password for %s. Use at least 8 characters.', 'modern-job-board'),
                        $user_login
                    ));
                    ?>
                </p>
                <form id="<?php echo esc_attr($form_id); ?>" class="mjb-auth-card-form" method="post" action="" novalidate>
                    <?php wp_nonce_field('mjb_reset_password_' . $role, 'mjb_reset_password_nonce'); ?>
                    <input type="hidden" name="mjb_login_role" value="<?php echo esc_attr($role); ?>">
                    <input type="hidden" name="mjb_rp_key" value="<?php echo esc_attr($key); ?>">
                    <input type="hidden" name="mjb_rp_login" value="<?php echo esc_attr($user_login); ?>">
                    <div class="mjb-auth-card-fields">
                        <p>
                            <label for="<?php echo esc_attr($form_id); ?>-password"><?php esc_html_e('New password', 'modern-job-board'); ?></label>
                            <input
                                type="password"
                                name="mjb_password"
                                id="<?php echo esc_attr($form_id); ?>-password"
                                required
                                aria-required="true"
                                autocomplete="new-password"
                                minlength="8"
                            >
                        </p>
                        <p>
                            <label for="<?php echo esc_attr($form_id); ?>-password-confirm"><?php esc_html_e('Confirm new password', 'modern-job-board'); ?></label>
                            <input
                                type="password"
                                name="mjb_password_confirm"
                                id="<?php echo esc_attr($form_id); ?>-password-confirm"
                                required
                                aria-required="true"
                                autocomplete="new-password"
                                minlength="8"
                            >
                        </p>
                    </div>
                    <div class="mjb-audience-card__actions mjb-audience-card__actions--inline">
                        <button type="submit" name="mjb_reset_password_submit" value="1" class="btn btn-primary btn-sm">
                            <?php esc_html_e('Save password', 'modern-job-board'); ?>
                        </button>
                        <a href="<?php echo esc_url($login_url); ?>" class="btn btn-outline btn-sm">
                            <?php esc_html_e('Cancel', 'modern-job-board'); ?>
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param string $role
     * @return string
     */
    public function get_registration_url($role)
    {
        if ($role === 'employer') {
            return MJB_Page_Resolver::get_page_url(
                'mjb_employer_registration',
                'mjb_employer_registration_page_id',
                array(),
                '/jobs/recruiter-registration/'
            );
        }

        return MJB_Page_Resolver::get_page_url(
            'mjb_candidate_registration',
            'mjb_candidate_registration_page_id',
            array(),
            '/jobs/candidate-registration/'
        );
    }

    /**
     * @param string $role
     * @return string
     */
    public function get_dashboard_url($role)
    {
        if ($role === 'employer') {
            return MJB_Page_Resolver::get_page_url(
                'mjb_dashboard',
                'mjb_employer_dashboard_page_id',
                array(),
                '/jobs/recruiter-dashboard/'
            );
        }

        return MJB_Page_Resolver::get_page_url(
            'mjb_candidate_dashboard',
            'mjb_candidate_dashboard_page_id',
            array(),
            '/jobs/candidate-dashboard/'
        );
    }
}
