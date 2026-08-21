<?php
/**
 * Modern Job Board Employer Registration (multi-step AJAX wizard).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Employer_Registration
{

    /**
     * Initialize Registration.
     */
    public function init()
    {
        add_shortcode('mjb_employer_registration', array($this, 'output_registration_form'));
        add_action('init', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_mjb_register_employer', array($this, 'ajax_register'));
        add_action('wp_ajax_mjb_register_employer', array($this, 'ajax_register'));
    }

    /**
     * Build a unique WP username from an email address.
     *
     * @param string $email
     * @return string
     */
    public static function username_from_email($email)
    {
        $email = sanitize_email($email);

        // Prefer the full email as username when available (email-as-login, Niceboard-style).
        if ($email !== '' && !username_exists($email)) {
            return $email;
        }

        $local = strstr($email, '@', true);
        $base = sanitize_user($local !== false ? $local : $email, true);
        if ($base === '') {
            $base = 'employer';
        }

        $username = $base;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }

    /**
     * AJAX: create recruiter account.
     */
    public function ajax_register()
    {
        $result = $this->process_registration(true);
        if (!empty($result['ok'])) {
            wp_send_json_success(
                array(
                    'ok' => true,
                    'redirect' => $result['redirect'],
                    'code' => isset($result['code']) ? $result['code'] : '',
                )
            );
        }

        wp_send_json_error(
            array(
                'ok' => false,
                'code' => isset($result['code']) ? $result['code'] : 'error_registration_failed',
                'message' => isset($result['message'])
                    ? $result['message']
                    : MJB_Candidate_Registration::message_for_code('error_registration_failed'),
            ),
            400
        );
    }

    /**
     * Handle classic form POST (no-JS fallback).
     */
    public function handle_registration()
    {
        if (!isset($_POST['mjb_register_employer']) || !isset($_POST['mjb_registration_nonce'])) {
            return;
        }
        if (!empty($_POST['mjb_ajax'])) {
            return;
        }

        $result = $this->process_registration(false);
        if (!empty($result['ok']) && !empty($result['redirect'])) {
            wp_safe_redirect($result['redirect']);
            exit;
        }

        $redirect_url = MJB_Page_Resolver::get_request_fallback_url(
            'mjb_employer_registration',
            'mjb_employer_registration_page_id',
            '/jobs/recruiter-registration/'
        );
        $code = isset($result['code']) ? $result['code'] : 'error_registration_failed';
        MJB_Notices::redirect($redirect_url, $code);
    }

    /**
     * Shared create path.
     *
     * @param bool $is_ajax
     * @return array
     */
    public function process_registration($is_ajax = false)
    {
        unset($is_ajax);

        $redirect_url = MJB_Page_Resolver::get_request_fallback_url(
            'mjb_employer_registration',
            'mjb_employer_registration_page_id',
            '/jobs/recruiter-registration/'
        );

        if (!wp_verify_nonce(
            isset($_POST['mjb_registration_nonce']) ? sanitize_text_field(wp_unslash($_POST['mjb_registration_nonce'])) : '',
            'mjb_register_action'
        )) {
            return self::fail('error_security');
        }

        $spam_error = MJB_Application_Guard::validate_spam_protection('registration');
        if ($spam_error) {
            return self::fail($spam_error);
        }

        if (MJB_Application_Guard::is_registration_rate_limited()) {
            MJB_Application_Guard::log_spam('rate', 'registration');
            return self::fail('error_registration_rate_limited');
        }

        $company_name = isset($_POST['mjb_company_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_company_name'])) : '';
        // Deferred company extras (editable later).
        $company_tagline = '';
        $company_description = '';
        $company_website = '';
        $company_linkedin = '';
        $company_twitter = '';
        $company_facebook = '';
        $first_name = isset($_POST['mjb_first_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_first_name'])) : '';
        $last_name = isset($_POST['mjb_last_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_last_name'])) : '';
        // Legacy single-field contact name (older form / no-JS edge cases).
        if ($first_name === '' && $last_name === '' && !empty($_POST['mjb_contact_name'])) {
            $legacy = sanitize_text_field(wp_unslash($_POST['mjb_contact_name']));
            $parts = preg_split('/\s+/', $legacy, 2);
            $first_name = isset($parts[0]) ? $parts[0] : '';
            $last_name = isset($parts[1]) ? $parts[1] : '';
        }
        $contact_name = trim($first_name . ' ' . $last_name);
        $email = isset($_POST['mjb_email']) ? sanitize_email(wp_unslash($_POST['mjb_email'])) : '';
        $password = isset($_POST['mjb_password']) ? (string) wp_unslash($_POST['mjb_password']) : '';
        $password_confirm = isset($_POST['mjb_password_confirm']) ? (string) wp_unslash($_POST['mjb_password_confirm']) : '';

        if ($company_name === '' || $first_name === '' || $last_name === '' || $email === '' || $password === '') {
            return self::fail('error_missing_fields');
        }

        if (!is_email($email)) {
            return self::fail('error_invalid_email');
        }

        if ($password !== $password_confirm) {
            return self::fail('error_password_mismatch');
        }

        if (strlen($password) < 8) {
            return self::fail('error_password_weak');
        }

        if (email_exists($email)) {
            return self::fail('error_email_exists');
        }

        $logo_path = '';
        $logo_relative = '';
        $logo_url = '';
        if (!empty($_FILES['mjb_company_logo']['name'])) {
            $logo_upload = MJB_Private_Uploads::upload($_FILES['mjb_company_logo'], MJB_Private_Uploads::TYPE_COMPANY_LOGO);
            if (is_wp_error($logo_upload)) {
                $code = $logo_upload->get_error_code() === 'invalid_type' ? 'error_invalid_logo' : 'error_logo_upload';
                return self::fail($code);
            }
            $logo_path = $logo_upload['file'];
            $logo_relative = isset($logo_upload['relative']) ? $logo_upload['relative'] : '';
            $logo_url = isset($logo_upload['url']) ? $logo_upload['url'] : '';
        }

        $username = self::username_from_email($email);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            if ($logo_path) {
                MJB_Private_Uploads::delete($logo_path);
            }
            return self::fail('error_registration_failed');
        }

        $user_id = intval($user_id);
        $user = new WP_User($user_id);
        $user->set_role('employer');

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $contact_name,
        ));

        $requires_approval = MJB_Account_Status::require_employer_approval();
        $account_status = MJB_Account_Status::set_for_new_registration($user_id, $requires_approval);

        $company_status = $requires_approval ? 'pending' : 'publish';
        $company_id = wp_insert_post(array(
            'post_title'   => $company_name,
            'post_content' => $company_description,
            'post_type'    => 'company',
            'post_status'  => $company_status,
            'post_author'  => $user_id,
        ), true);

        if (!$company_id || is_wp_error($company_id)) {
            if ($logo_path) {
                MJB_Private_Uploads::delete($logo_path);
            }
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            return self::fail('error_registration_failed');
        }

        $company_id = intval($company_id);

        if (class_exists('MJB_Job_Importer')) {
            update_post_meta($company_id, MJB_Job_Importer::COMPANY_NAME_KEY_META, MJB_Job_Importer::company_name_key($company_name));
        }

        update_post_meta($company_id, '_company_tagline', $company_tagline);
        update_post_meta($company_id, '_company_website', $company_website);
        update_post_meta($company_id, '_company_linkedin', $company_linkedin);
        update_post_meta($company_id, '_company_twitter', $company_twitter);
        update_post_meta($company_id, '_company_facebook', $company_facebook);
        update_post_meta($company_id, '_company_contact_name', $contact_name);
        update_post_meta($company_id, '_company_email', $email);
        update_post_meta($company_id, '_employer_user_id', $user_id);

        if ($logo_path) {
            update_post_meta($company_id, '_company_logo_path', $logo_path);
            if ($logo_relative) {
                update_post_meta($company_id, '_company_logo_relative', $logo_relative);
            }
            if ($logo_url) {
                update_post_meta($company_id, '_company_logo_url', $logo_url);
            }
        }

        update_user_meta($user_id, '_company_name', $company_name);
        update_user_meta($user_id, '_employer_company_id', $company_id);

        MJB_Application_Guard::record_registration();

        global $mjb_emails;
        if ($mjb_emails instanceof MJB_Emails) {
            $mjb_emails->send_employer_signup_confirmation($user_id, $requires_approval);
        }

        /**
         * Fires after a successful employer registration.
         *
         * @param int  $user_id
         * @param int  $company_id
         * @param bool $requires_approval
         */
        do_action('mjb_employer_registered', $user_id, $company_id, $requires_approval);

        if ($requires_approval || $account_status === MJB_Account_Status::STATUS_PENDING) {
            return array(
                'ok' => true,
                'code' => 'success_registration_pending',
                'redirect' => add_query_arg(MJB_Notices::QUERY_KEY, 'success_registration_pending', $redirect_url),
            );
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        $dash = class_exists('MJB_Dashboard')
            ? MJB_Dashboard::get_page_url()
            : home_url('/jobs/recruiter-dashboard/');

        return array(
            'ok' => true,
            'code' => 'success_employer_registered',
            'redirect' => add_query_arg(MJB_Notices::QUERY_KEY, 'success_employer_registered', $dash),
        );
    }

    /**
     * @param string $code
     * @return array
     */
    private static function fail($code)
    {
        return array(
            'ok' => false,
            'code' => $code,
            'message' => MJB_Candidate_Registration::message_for_code($code),
        );
    }

    /**
     * Output multi-step registration form.
     *
     * @param array $atts
     * @return string
     */
    public function output_registration_form($atts)
    {
        unset($atts);

        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'modern-job-board') . '</p>';
        }

        $login_url = MJB_Page_Resolver::get_page_url(
            'mjb_employer_login',
            'mjb_employer_login_page_id',
            array(),
            '/jobs/recruiter-login/'
        );

        $steps = array(
            array(
                'label' => __('You', 'modern-job-board'),
                'icon' => 'user',
            ),
            array(
                'label' => __('Company', 'modern-job-board'),
                'icon' => 'briefcase',
            ),
            array(
                'label' => __('Account', 'modern-job-board'),
                'icon' => 'mail',
            ),
        );

        ob_start();
        ?>
        <div class="mjb-registration-form-container mjb-registration--employer mjb-container--auth">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo MJB_Notices::render();
            ?>
            <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--register mjb-audience-card--employers mjb-registration-card">
                <header class="mjb-audience-card__header">
                    <span class="mjb-audience-card__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo MJB_Icons::render('briefcase', 22);
                        ?>
                    </span>
                    <h2 class="mjb-audience-card__title"><?php esc_html_e('Recruiter Sign up', 'modern-job-board'); ?></h2>
                </header>
                <p class="mjb-audience-card__intro"><?php esc_html_e('Create a recruiter account in three short steps.', 'modern-job-board'); ?></p>
                <p class="mjb-registration-signin-note">
                    <?php
                    echo esc_html__('Already have an account?', 'modern-job-board') . ' ';
                    echo '<a class="mjb-auth-forgot-link" href="' . esc_url($login_url) . '">' . esc_html__('Sign in', 'modern-job-board') . '</a>';
                    ?>
                </p>

                <form
                    method="post"
                    action=""
                    class="mjb-auth-card-form mjb-registration-form mjb-registration-wizard"
                    enctype="multipart/form-data"
                    novalidate
                    data-mjb-audience="employer"
                    data-mjb-ajax-action="mjb_register_employer"
                >
                    <?php wp_nonce_field('mjb_register_action', 'mjb_registration_nonce'); ?>
                    <div class="mjb-hp-field" aria-hidden="true">
                        <label for="mjb_hp_website_employer"><?php esc_html_e('Website', 'modern-job-board'); ?></label>
                        <input type="text" name="<?php echo esc_attr(MJB_Application_Guard::HONEYPOT_FIELD); ?>" id="mjb_hp_website_employer" tabindex="-1" autocomplete="off">
                    </div>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in renderer.
                    echo MJB_Application_Guard::render_time_trap_field();
                    ?>

                    <ol class="mjb-wizard-progress" aria-label="<?php esc_attr_e('Registration steps', 'modern-job-board'); ?>">
                        <?php foreach ($steps as $i => $step) : ?>
                            <li class="mjb-wizard-progress__step<?php echo $i === 0 ? ' is-current' : ''; ?>" data-step="<?php echo esc_attr((string) $i); ?>"<?php echo $i === 0 ? ' aria-current="step"' : ''; ?>>
                                <span class="mjb-wizard-progress__icon" aria-hidden="true">
                                    <?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    echo MJB_Icons::render($step['icon'], 18);
                                    ?>
                                </span>
                                <span class="mjb-wizard-progress__label"><?php echo esc_html($step['label']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                    <div class="mjb-wizard-panel is-active" data-step="0" role="group" aria-labelledby="mjb-emp-step-0-title">
                        <h3 id="mjb-emp-step-0-title" class="mjb-wizard-panel__title"><?php esc_html_e('You', 'modern-job-board'); ?></h3>
                        <div class="mjb-auth-card-fields">
                            <div class="mjb-auth-card-fields__row">
                                <p>
                                    <label for="mjb_first_name"><?php esc_html_e('First name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                    <input type="text" name="mjb_first_name" id="mjb_first_name" required aria-required="true" autocomplete="given-name">
                                </p>
                                <p>
                                    <label for="mjb_last_name"><?php esc_html_e('Last name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                    <input type="text" name="mjb_last_name" id="mjb_last_name" required aria-required="true" autocomplete="family-name">
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mjb-wizard-panel" data-step="1" role="group" aria-labelledby="mjb-emp-step-1-title" hidden>
                        <h3 id="mjb-emp-step-1-title" class="mjb-wizard-panel__title"><?php esc_html_e('Company', 'modern-job-board'); ?></h3>
                        <div class="mjb-auth-card-fields">
                            <p>
                                <label for="mjb_company_name"><?php esc_html_e('Company name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="text" name="mjb_company_name" id="mjb_company_name" required aria-required="true" placeholder="<?php esc_attr_e('Acme', 'modern-job-board'); ?>" autocomplete="organization">
                            </p>
                            <p>
                                <label for="mjb_company_logo"><?php esc_html_e('Company logo', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="file" name="mjb_company_logo" id="mjb_company_logo" accept="image/jpeg,image/png,image/webp">
                                <span class="mjb-field-hint"><?php esc_html_e('JPEG, PNG or WebP. Max 2 MB. Optional — add more company details later.', 'modern-job-board'); ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="mjb-wizard-panel" data-step="2" role="group" aria-labelledby="mjb-emp-step-2-title" hidden>
                        <h3 id="mjb-emp-step-2-title" class="mjb-wizard-panel__title"><?php esc_html_e('Account', 'modern-job-board'); ?></h3>
                        <div class="mjb-auth-card-fields">
                            <p>
                                <label for="mjb_email"><?php esc_html_e('Company email', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="email" name="mjb_email" id="mjb_email" required aria-required="true" placeholder="contact@acme.com" autocomplete="email" autocapitalize="off">
                            </p>
                            <p>
                                <label for="mjb_password"><?php esc_html_e('Password', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="password" name="mjb_password" id="mjb_password" required aria-required="true" autocomplete="new-password" minlength="8">
                                <span class="mjb-field-hint"><?php esc_html_e('At least 8 characters.', 'modern-job-board'); ?></span>
                            </p>
                            <p>
                                <label for="mjb_password_confirm"><?php esc_html_e('Confirm password', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="password" name="mjb_password_confirm" id="mjb_password_confirm" required aria-required="true" autocomplete="new-password" minlength="8">
                            </p>
                            <?php if (MJB_Recaptcha::is_enabled()) : ?>
                                <p>
                                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(MJB_Recaptcha::get_site_key()); ?>"></div>
                                </p>
                            <?php endif; ?>
                            <?php
                            /**
                             * Extra fields on the final employer registration step.
                             */
                            do_action('mjb_employer_registration_form_fields');
                            do_action('mjb_employer_registration_form_end');
                            ?>
                        </div>
                    </div>

                    <div class="mjb-wizard-error mjb-message error" role="alert" hidden></div>
                    <p class="mjb-wizard-status" hidden aria-live="polite"></p>

                    <div class="mjb-wizard-actions mjb-audience-card__actions mjb-audience-card__actions--inline">
                        <button type="button" class="btn btn-outline btn-sm" data-mjb-wizard-back hidden><?php esc_html_e('Back', 'modern-job-board'); ?></button>
                        <button type="button" class="btn btn-primary btn-sm" data-mjb-wizard-next><?php esc_html_e('Continue', 'modern-job-board'); ?></button>
                        <button type="submit" name="mjb_register_employer" value="1" class="btn btn-primary btn-sm" data-mjb-wizard-submit hidden>
                            <?php esc_html_e('Create account', 'modern-job-board'); ?>
                        </button>
                    </div>
                </form>
            </article>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
