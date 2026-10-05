<?php
/**
 * Modern Job Board Candidate Registration (multi-step AJAX wizard).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Candidate_Registration
{

    /**
     * Initialize Registration.
     */
    public function init()
    {
        add_shortcode('mjb_candidate_registration', array($this, 'output_registration_form'));
        add_action('init', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_mjb_register_candidate', array($this, 'ajax_register'));
        add_action('wp_ajax_mjb_register_candidate', array($this, 'ajax_register'));
        add_action('wp_ajax_nopriv_mjb_check_registration_email', array($this, 'ajax_check_email'));
        add_action('wp_ajax_mjb_check_registration_email', array($this, 'ajax_check_email'));
    }

    /**
     * AJAX: create candidate account.
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
                'message' => isset($result['message']) ? $result['message'] : self::message_for_code('error_registration_failed'),
            ),
            400
        );
    }

    /**
     * AJAX: is this email free to register?
     */
    public function ajax_check_email()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mjb_registration_wizard')) {
            wp_send_json_error(array('ok' => false, 'message' => self::message_for_code('error_security')), 403);
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if ($email === '' || !is_email($email)) {
            wp_send_json_error(
                array(
                    'ok' => false,
                    'code' => 'error_invalid_email',
                    'message' => self::message_for_code('error_invalid_email'),
                ),
                400
            );
        }

        if (email_exists($email)) {
            wp_send_json_error(
                array(
                    'ok' => false,
                    'code' => 'error_email_exists',
                    'message' => self::message_for_code('error_email_exists'),
                ),
                400
            );
        }

        wp_send_json_success(array('ok' => true));
    }

    /**
     * Handle classic form POST (no-JS fallback).
     */
    public function handle_registration()
    {
        if (!isset($_POST['mjb_register_candidate']) || !isset($_POST['mjb_candidate_nonce'])) {
            return;
        }
        // AJAX uses admin-ajax.php; skip classic path when flagged.
        if (!empty($_POST['mjb_ajax'])) {
            return;
        }

        $result = $this->process_registration(false);
        if (!empty($result['ok']) && !empty($result['redirect'])) {
            wp_safe_redirect($result['redirect']);
            exit;
        }

        $redirect_url = MJB_Page_Resolver::get_request_fallback_url(
            'mjb_candidate_registration',
            'mjb_candidate_registration_page_id',
            '/jobs/candidate-registration/'
        );
        $code = isset($result['code']) ? $result['code'] : 'error_registration_failed';
        MJB_Notices::redirect($redirect_url, $code);
    }

    /**
     * Shared create path for classic POST and AJAX.
     *
     * @param bool $is_ajax
     * @return array{ok:bool,redirect?:string,code?:string,message?:string}
     */
    public function process_registration($is_ajax = false)
    {
        $redirect_url = MJB_Page_Resolver::get_request_fallback_url(
            'mjb_candidate_registration',
            'mjb_candidate_registration_page_id',
            '/jobs/candidate-registration/'
        );

        if (!wp_verify_nonce(
            isset($_POST['mjb_candidate_nonce']) ? sanitize_text_field(wp_unslash($_POST['mjb_candidate_nonce'])) : '',
            'mjb_candidate_action'
        )) {
            return self::fail('error_security');
        }

        $apply_token = class_exists('MJB_Applications') ? MJB_Applications::request_apply_token() : '';
        $dashboard_url = class_exists('MJB_Candidate_Dashboard')
            ? MJB_Candidate_Dashboard::get_page_url()
            : home_url('/jobs/candidate-dashboard/');
        $success_redirect = class_exists('MJB_Applications')
            ? MJB_Applications::resolve_auth_redirect($dashboard_url)
            : $dashboard_url;

        $spam_error = MJB_Application_Guard::validate_spam_protection('registration');
        if ($spam_error) {
            return self::fail($spam_error);
        }

        if (MJB_Application_Guard::is_registration_rate_limited()) {
            return self::fail('error_registration_rate_limited');
        }

        $first_name = isset($_POST['mjb_first_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_first_name'])) : '';
        $last_name = isset($_POST['mjb_last_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_last_name'])) : '';
        $phone_input = self::sanitize_phone_submission();
        $phone = $phone_input['phone'];
        $phone_country = $phone_input['country'];
        $city = isset($_POST['mjb_city']) ? sanitize_text_field(wp_unslash($_POST['mjb_city'])) : '';
        $email = isset($_POST['mjb_email']) ? sanitize_email(wp_unslash($_POST['mjb_email'])) : '';
        $password = isset($_POST['mjb_password']) ? (string) wp_unslash($_POST['mjb_password']) : '';
        $password_confirm = isset($_POST['mjb_password_confirm']) ? (string) wp_unslash($_POST['mjb_password_confirm']) : '';

        // Deferred profile fields: sensible defaults (editable later on the dashboard).
        $linkedin = '';
        $website = '';
        $title = '';
        $experience_company = '';
        $is_current_role = '0';
        $bio = '';
        $open_to_work = '1';
        $remote_ok = '0';
        $is_public = '1';

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
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

        if (empty($_FILES['mjb_resume']['name'])) {
            return self::fail('error_resume_required');
        }

        $resume_original = isset($_FILES['mjb_resume']['name']) ? sanitize_file_name(wp_unslash($_FILES['mjb_resume']['name'])) : '';
        $resume_upload = MJB_Resumes::upload_file($_FILES['mjb_resume'], 'candidate_registration');
        if (is_wp_error($resume_upload)) {
            $code = $resume_upload->get_error_code() === 'invalid_type' ? 'error_invalid_resume' : 'error_resume_upload';
            return self::fail($code);
        }

        $photo_path = '';
        $photo_relative = '';
        $photo_url = '';
        if (!empty($_FILES['mjb_photo']['name'])) {
            $photo_upload = MJB_Private_Uploads::upload($_FILES['mjb_photo'], MJB_Private_Uploads::TYPE_CANDIDATE_PHOTO);
            if (is_wp_error($photo_upload)) {
                MJB_Private_Uploads::delete($resume_upload['file']);
                $code = $photo_upload->get_error_code() === 'invalid_type' ? 'error_invalid_photo' : 'error_photo_upload';
                return self::fail($code);
            }
            $photo_path = $photo_upload['file'];
            $photo_relative = isset($photo_upload['relative']) ? $photo_upload['relative'] : '';
            $photo_url = isset($photo_upload['url']) ? $photo_upload['url'] : '';
        }

        $username = MJB_Employer_Registration::username_from_email($email);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            MJB_Private_Uploads::delete($resume_upload['file']);
            if ($photo_path) {
                MJB_Private_Uploads::delete($photo_path);
            }
            return self::fail('error_registration_failed');
        }

        $user_id = intval($user_id);
        $user = new WP_User($user_id);
        $user->set_role('candidate');

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
        ));

        update_user_meta($user_id, '_candidate_headline', $title);
        update_user_meta($user_id, '_candidate_linkedin', $linkedin);
        update_user_meta($user_id, '_candidate_website', $website);
        update_user_meta($user_id, '_candidate_phone', $phone);
        if ($phone_country !== '') {
            update_user_meta($user_id, '_candidate_phone_country', $phone_country);
        }
        update_user_meta($user_id, '_candidate_city', $city);
        update_user_meta($user_id, '_candidate_experience_company', $experience_company);
        update_user_meta($user_id, '_candidate_is_current_role', $is_current_role);
        update_user_meta($user_id, '_candidate_bio', $bio);
        update_user_meta($user_id, '_candidate_open_to_work', $open_to_work);
        update_user_meta($user_id, '_candidate_remote_ok', $remote_ok);
        update_user_meta($user_id, '_candidate_is_public', $is_public);

        if ($photo_path) {
            update_user_meta($user_id, '_candidate_photo_path', $photo_path);
            if ($photo_relative) {
                update_user_meta($user_id, '_candidate_photo_relative', $photo_relative);
            }
            if ($photo_url) {
                update_user_meta($user_id, '_candidate_photo_url', $photo_url);
            }
        }

        $resume_id = MJB_Resumes::create_resume_post($user_id, $resume_upload, $resume_original);
        if (is_wp_error($resume_id)) {
            MJB_Private_Uploads::delete($resume_upload['file']);
            if ($photo_path) {
                MJB_Private_Uploads::delete($photo_path);
            }
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            return self::fail('error_resume_upload');
        }

        $requires_approval = MJB_Account_Status::require_candidate_approval();
        $account_status = MJB_Account_Status::set_for_new_registration($user_id, $requires_approval);

        MJB_Application_Guard::record_registration();

        global $mjb_emails;
        if ($mjb_emails instanceof MJB_Emails) {
            $mjb_emails->send_candidate_signup_confirmation($user_id, $requires_approval);
        }

        /**
         * Fires after a successful candidate registration.
         *
         * @param int  $user_id
         * @param int  $resume_id
         * @param bool $requires_approval
         */
        do_action('mjb_candidate_registered', $user_id, intval($resume_id), $requires_approval);

        if ($requires_approval || $account_status === MJB_Account_Status::STATUS_PENDING) {
            return array(
                'ok' => true,
                'code' => 'success_registration_pending',
                'redirect' => add_query_arg(MJB_Notices::QUERY_KEY, 'success_registration_pending', $redirect_url),
            );
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Apply intent after signup.
        if ($apply_token !== '' && class_exists('MJB_Applications')) {
            $job_id = MJB_Applications::request_apply_job_id();
            if ($job_id > 0) {
                $job_url = get_permalink($job_id) ?: MJB_Page_Resolver::get_jobs_page_url();
                $app_result = MJB_Applications::create_application_from_profile($job_id, $user_id);
                $token = MJB_Applications::request_apply_token();
                if ($token !== '') {
                    delete_transient(MJB_Applications::APPLY_TOKEN_TRANSIENT_PREFIX . strtolower($token));
                }
                if (is_wp_error($app_result)) {
                    return array(
                        'ok' => true,
                        'code' => $app_result->get_error_code(),
                        'redirect' => add_query_arg(MJB_Notices::QUERY_KEY, $app_result->get_error_code(), $job_url),
                    );
                }
                return array(
                    'ok' => true,
                    'code' => 'success_application',
                    'redirect' => add_query_arg(MJB_Notices::QUERY_KEY, 'success_application', $job_url),
                );
            }
        }

        return array(
            'ok' => true,
            'code' => 'success_candidate_registered',
            'redirect' => add_query_arg(MJB_Notices::QUERY_KEY, 'success_candidate_registered', $success_redirect),
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
            'message' => self::message_for_code($code),
        );
    }

    /**
     * @param string $code
     * @return string
     */
    public static function message_for_code($code)
    {
        $messages = apply_filters('mjb_notice_messages', MJB_Notices::default_messages());
        return isset($messages[$code]) ? $messages[$code] : $code;
    }

    /**
     * Normalize the posted international phone into E.164 plus ISO2 country.
     *
     * @return array{phone:string,country:string}
     */
    public static function sanitize_phone_submission()
    {
        $phone = isset($_POST['mjb_phone']) ? sanitize_text_field(wp_unslash($_POST['mjb_phone'])) : '';
        $phone = preg_replace('/[^\d+]/', '', (string) $phone);
        if (!is_string($phone)) {
            $phone = '';
        }
        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . ltrim($phone, '+');
        }

        $phone_country = isset($_POST['mjb_phone_country']) ? strtoupper(sanitize_key(wp_unslash($_POST['mjb_phone_country']))) : '';
        if (strlen($phone_country) !== 2) {
            $phone_country = '';
        }

        return array(
            'phone' => $phone,
            'country' => $phone_country,
        );
    }

    /**
     * International phone field (country dial + national number).
     * Submits E.164-style value in $name and ISO2 country in $country_name.
     *
     * @param array $args
     * @return string
     */
    public static function render_phone_field($args = array())
    {
        $defaults = array(
            'id' => 'mjb_phone',
            'name' => 'mjb_phone',
            'country_name' => 'mjb_phone_country',
            'value' => '',
            // Empty / "auto" = detect from browser locale + timezone in JS.
            'country' => 'auto',
        );
        $args = wp_parse_args($args, $defaults);
        $id = sanitize_html_class($args['id']);
        $name = sanitize_key($args['name']);
        $country_name = sanitize_key($args['country_name']);
        $country_raw = strtoupper(sanitize_key($args['country']));
        $auto_country = ($country_raw === '' || $country_raw === 'AUTO');
        $country = $auto_country ? 'auto' : $country_raw;
        if (!$auto_country && strlen($country) !== 2) {
            $country = 'auto';
            $auto_country = true;
        }

        $national_id = $id . '_national';
        $list_id = $id . '_countries';

        ob_start();
        ?>
        <div
            class="mjb-phone-field"
            data-mjb-phone
            data-default-country="<?php echo esc_attr($country); ?>"
            data-auto-country="<?php echo $auto_country ? '1' : '0'; ?>"
            data-value="<?php echo esc_attr((string) $args['value']); ?>"
        >
            <div class="mjb-phone-field__row">
                <button
                    type="button"
                    class="mjb-phone-field__cc"
                    data-mjb-phone-cc
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="<?php echo esc_attr($list_id); ?>"
                >
                    <span class="mjb-phone-field__flag" data-mjb-phone-flag aria-hidden="true">🌐</span>
                    <span class="mjb-phone-field__dial" data-mjb-phone-dial>+</span>
                    <span class="mjb-phone-field__chevron" aria-hidden="true">▾</span>
                </button>
                <input
                    type="tel"
                    id="<?php echo esc_attr($national_id); ?>"
                    class="mjb-phone-field__national"
                    data-mjb-phone-national
                    inputmode="tel"
                    autocomplete="tel-national"
                    placeholder="<?php esc_attr_e('Phone number', 'modern-job-board'); ?>"
                >
            </div>
            <div
                id="<?php echo esc_attr($list_id); ?>"
                class="mjb-phone-field__menu mjb-ac__menu"
                data-mjb-phone-menu
                hidden
                role="listbox"
                aria-label="<?php esc_attr_e('Country calling code', 'modern-job-board'); ?>"
            ></div>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr((string) $args['value']); ?>" data-mjb-phone-e164>
            <input type="hidden" name="<?php echo esc_attr($country_name); ?>" value="<?php echo esc_attr($auto_country ? '' : $country); ?>" data-mjb-phone-iso>
        </div>
        <?php
        return (string) ob_get_clean();
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
            $apply_job_id = class_exists('MJB_Applications') ? MJB_Applications::request_apply_job_id() : 0;
            if ($apply_job_id > 0 && class_exists('MJB_Applications') && MJB_Applications::current_user_can_apply()) {
                wp_safe_redirect(MJB_Applications::get_apply_url($apply_job_id));
                exit;
            }
            return '<p>' . esc_html__('You are already logged in.', 'modern-job-board') . '</p>';
        }

        $apply_token = class_exists('MJB_Applications') ? MJB_Applications::request_apply_token() : '';
        $apply_job_id = class_exists('MJB_Applications') ? MJB_Applications::request_apply_job_id() : 0;
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        $login_url = ($apply_job_id > 0 && class_exists('MJB_Applications'))
            ? MJB_Applications::get_apply_login_url($apply_job_id)
            : MJB_Page_Resolver::get_page_url(
                'mjb_candidate_login',
                'mjb_candidate_login_page_id',
                array(),
                '/jobs/candidate-login/'
            );

        $steps = array(
            array(
                'label' => __('About you', 'modern-job-board'),
                'icon' => 'user',
            ),
            array(
                'label' => __('Resume', 'modern-job-board'),
                'icon' => 'file-text',
            ),
            array(
                'label' => __('Account', 'modern-job-board'),
                'icon' => 'mail',
            ),
        );

        $intro = $apply_job_id > 0
            ? __('Create a job seeker profile to finish applying. Your resume will be sent with the application.', 'modern-job-board')
            : __('Create a job seeker profile in three short steps.', 'modern-job-board');

        ob_start();
        ?>
        <div class="mjb-registration-form-container mjb-registration--candidate mjb-container--auth">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo MJB_Notices::render();
            ?>
            <article class="mjb-audience-card mjb-audience-card--auth mjb-audience-card--register mjb-audience-card--seekers mjb-registration-card">
                <header class="mjb-audience-card__header">
                    <span class="mjb-audience-card__icon" aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo MJB_Icons::render('user-plus', 22); /* sized via CSS to title line-height */
                        ?>
                    </span>
                    <h2 class="mjb-audience-card__title"><?php esc_html_e('Job Seeker Sign up', 'modern-job-board'); ?></h2>
                </header>
                <p class="mjb-audience-card__intro"><?php echo esc_html($intro); ?></p>
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
                    data-mjb-audience="candidate"
                    data-mjb-ajax-action="mjb_register_candidate"
                >
                    <?php wp_nonce_field('mjb_candidate_action', 'mjb_candidate_nonce'); ?>
                    <?php if ($redirect_to !== '') : ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                    <?php endif; ?>
                    <?php if ($apply_token !== '') : ?>
                        <input type="hidden" name="<?php echo esc_attr(MJB_Applications::APPLY_TOKEN_QUERY); ?>" value="<?php echo esc_attr($apply_token); ?>">
                    <?php endif; ?>
                    <div class="mjb-hp-field" aria-hidden="true">
                        <label for="mjb_hp_website_candidate"><?php esc_html_e('Website', 'modern-job-board'); ?></label>
                        <input type="text" name="<?php echo esc_attr(MJB_Application_Guard::HONEYPOT_FIELD); ?>" id="mjb_hp_website_candidate" tabindex="-1" autocomplete="off">
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

                    <div class="mjb-wizard-panel is-active" data-step="0" role="group" aria-labelledby="mjb-cand-step-0-title">
                        <h3 id="mjb-cand-step-0-title" class="mjb-wizard-panel__title"><?php esc_html_e('About you', 'modern-job-board'); ?></h3>
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
                            <p class="mjb-field-phone">
                                <label for="mjb_phone_national"><?php esc_html_e('Phone number', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
                                echo self::render_phone_field(array(
                                    'id' => 'mjb_phone',
                                    'name' => 'mjb_phone',
                                    'country_name' => 'mjb_phone_country',
                                    'value' => '',
                                    'country' => 'auto',
                                ));
                                ?>
                            </p>
                            <p class="mjb-field-city">
                                <label for="mjb_city"><?php esc_html_e('City', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <?php
                                if (class_exists('MJB_Search')) {
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped inside render_autocomplete_field().
                                    echo MJB_Search::render_autocomplete_field(array(
                                        'name' => 'mjb_city',
                                        'type' => 'geocity',
                                        'value' => '',
                                        'label' => '',
                                        'placeholder' => esc_attr__('Start typing a city…', 'modern-job-board'),
                                        'free_text' => true,
                                        'input_id' => 'mjb_city',
                                        // Ranking uses detected country in JS (locale/timezone + phone dial).
                                        'prefer_country' => '',
                                    ));
                                } else {
                                    ?>
                                    <input type="text" name="mjb_city" id="mjb_city" autocomplete="address-level2" placeholder="<?php esc_attr_e('Start typing a city…', 'modern-job-board'); ?>">
                                    <?php
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="mjb-wizard-panel" data-step="1" role="group" aria-labelledby="mjb-cand-step-1-title" hidden>
                        <h3 id="mjb-cand-step-1-title" class="mjb-wizard-panel__title"><?php esc_html_e('Resume', 'modern-job-board'); ?></h3>
                        <div class="mjb-auth-card-fields">
                            <p>
                                <label for="mjb_resume"><?php esc_html_e('Resume', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="file" name="mjb_resume" id="mjb_resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required aria-required="true">
                                <span class="mjb-field-hint"><?php esc_html_e('PDF, DOC or DOCX. Max 5 MB. Stored privately (not the Media Library).', 'modern-job-board'); ?></span>
                            </p>
                            <p>
                                <label for="mjb_photo"><?php esc_html_e('Photo', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="file" name="mjb_photo" id="mjb_photo" accept="image/jpeg,image/png,image/webp">
                                <span class="mjb-field-hint"><?php esc_html_e('JPEG, PNG or WebP. Max 2 MB.', 'modern-job-board'); ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="mjb-wizard-panel" data-step="2" role="group" aria-labelledby="mjb-cand-step-2-title" hidden>
                        <h3 id="mjb-cand-step-2-title" class="mjb-wizard-panel__title"><?php esc_html_e('Account', 'modern-job-board'); ?></h3>
                        <div class="mjb-auth-card-fields">
                            <p>
                                <label for="mjb_email"><?php esc_html_e('Email', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                                <input type="email" name="mjb_email" id="mjb_email" required aria-required="true" autocomplete="email" autocapitalize="off">
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
                             * Extra fields on the final candidate registration step.
                             */
                            do_action('mjb_candidate_registration_form_fields');
                            do_action('mjb_candidate_registration_form_end');
                            ?>
                        </div>
                    </div>

                    <div class="mjb-wizard-error mjb-message error" role="alert" hidden></div>
                    <p class="mjb-wizard-status" hidden aria-live="polite"></p>

                    <div class="mjb-wizard-actions mjb-audience-card__actions mjb-audience-card__actions--inline">
                        <button type="button" class="btn btn-outline btn-sm" data-mjb-wizard-back hidden><?php esc_html_e('Back', 'modern-job-board'); ?></button>
                        <button type="button" class="btn btn-primary btn-sm" data-mjb-wizard-next><?php esc_html_e('Continue', 'modern-job-board'); ?></button>
                        <button type="submit" name="mjb_register_candidate" value="1" class="btn btn-primary btn-sm" data-mjb-wizard-submit hidden>
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
