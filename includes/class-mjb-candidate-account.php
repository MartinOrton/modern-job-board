<?php
/**
 * Candidate account settings: email, notifications, password, sessions, export, deletion.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Candidate_Account
{
    const META_NOTIFY_APPLICATIONS = '_mjb_notify_applications';
    const META_NOTIFY_MESSAGES = '_mjb_notify_messages';
    const META_NOTIFY_NEWS = '_mjb_notify_news';
    const META_ALERTS_PAUSED = '_mjb_alerts_paused';
    const META_PASSWORD_CHANGED = '_mjb_password_changed';
    const META_NEW_EMAIL = '_new_email';
    const DELETE_WORD = 'delete';
    const PASSWORD_MIN = 12;

    /**
     * Register request handlers.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'handle_requests'));
        add_action('wp_ajax_mjb_candidate_password', array(__CLASS__, 'ajax_change_password'));
        add_action('wp_ajax_mjb_candidate_account', array(__CLASS__, 'ajax_save_account'));
        add_filter('mjb_notice_messages', array(__CLASS__, 'notice_messages'));
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function alerts_paused($user_id)
    {
        return get_user_meta((int) $user_id, self::META_ALERTS_PAUSED, true) === '1';
    }

    /**
     * Whether a candidate should receive this kind of email.
     *
     * Applications and messages are on until turned off. Tips stay off until turned on.
     *
     * @param string $kind applications|messages|news
     * @param int    $user_id
     * @return bool
     */
    public static function allows_email($kind, $user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return true;
        }
        if ($kind === 'news') {
            return get_user_meta($user_id, self::META_NOTIFY_NEWS, true) === '1';
        }
        $key = ($kind === 'messages') ? self::META_NOTIFY_MESSAGES : self::META_NOTIFY_APPLICATIONS;
        return get_user_meta($user_id, $key, true) !== '0';
    }

    /**
     * WordPress hashes the trimmed password. The field can also pick up a
     * trailing space or newline that is not part of the password.
     *
     * @param string     $password
     * @param string     $hash
     * @param int|string $user_id
     * @return bool
     */
    public static function current_password_matches($password, $hash, $user_id)
    {
        $password = trim((string) $password);
        $hash = (string) $hash;
        if ($password === '' || $hash === '') {
            return false;
        }
        return (bool) wp_check_password($password, $hash, $user_id);
    }

    /**
     * @param string $typed
     * @return bool
     */
    public static function deletion_confirmed($typed)
    {
        return strtolower(trim((string) $typed)) === self::DELETE_WORD;
    }

    /**
     * Browser and OS from a session user agent. Unknown clients stay unnamed.
     *
     * @param string $user_agent
     * @return string
     */
    public static function device_label($user_agent)
    {
        $ua = (string) $user_agent;
        $browser = '';
        if (stripos($ua, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        }

        $os = '';
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = stripos($ua, 'iPad') !== false ? 'iPad' : 'iPhone';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        if ($browser !== '' && $os !== '') {
            return $browser . ' ' . __('on', 'modern-job-board') . ' ' . $os;
        }
        if ($browser !== '') {
            return $browser;
        }
        if ($os !== '') {
            return $os;
        }
        return __('This browser', 'modern-job-board');
    }

    /**
     * @param string $user_agent
     * @return string monitor|smartphone
     */
    public static function device_icon($user_agent)
    {
        $ua = (string) $user_agent;
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'Android') !== false) {
            return 'smartphone';
        }
        return 'monitor';
    }

    /**
     * @param array<string, string> $messages
     * @return array<string, string>
     */
    public static function notice_messages($messages)
    {
        $messages['success_account'] = __('Account settings saved.', 'modern-job-board');
        $messages['success_email_pending'] = __('Check the new address for a confirmation link. You keep signing in with your current email until you confirm.', 'modern-job-board');
        $messages['success_email_confirmed'] = __('Your sign-in email is updated.', 'modern-job-board');
        $messages['success_email_resent'] = __('Confirmation link sent again.', 'modern-job-board');
        $messages['success_email_cancelled'] = __('Email change cancelled.', 'modern-job-board');
        $messages['success_password'] = __('Password updated. You are still signed in on this device.', 'modern-job-board');
        $messages['success_sessions'] = __('Signed out of every other device.', 'modern-job-board');
        $messages['success_account_deleted'] = __('Your account has been deleted.', 'modern-job-board');
        $messages['error_current_password'] = __('That does not match your current password.', 'modern-job-board');
        $messages['error_password_short'] = __('Use 12 or more characters for your new password.', 'modern-job-board');
        $messages['error_delete_confirm'] = __('Type DELETE to confirm account removal.', 'modern-job-board');
        $messages['error_email_pending'] = __('There is no email change waiting to be confirmed.', 'modern-job-board');
        return $messages;
    }

    /**
     * POST and email-confirmation handlers.
     */
    public static function handle_requests()
    {
        if (!empty($_GET['mjb_confirm_email'])) {
            self::confirm_email_change(sanitize_text_field(wp_unslash($_GET['mjb_confirm_email'])));
            return;
        }

        // admin-ajax.php still runs init. The password request includes this
        // form, and a redirect here would replace the JSON response.
        if (wp_doing_ajax()) {
            return;
        }

        if (!is_user_logged_in() || empty($_POST['mjb_account_nonce'])) {
            return;
        }

        $action = isset($_POST['mjb_account_action']) ? sanitize_key(wp_unslash($_POST['mjb_account_action'])) : '';
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_account_nonce'])), 'mjb_account_action')) {
            self::redirect('error_security');
        }

        $user = wp_get_current_user();
        if (!$user || !in_array('candidate', (array) $user->roles, true)) {
            self::redirect('error_permission');
        }

        switch ($action) {
            case 'save':
                self::redirect(self::save_settings($user));
                break;
            case 'resend_email':
                self::redirect(self::resend_email_change($user));
                break;
            case 'cancel_email':
                self::redirect(self::cancel_email_change($user));
                break;
            case 'password':
                $result = self::apply_password_change($user);
                self::redirect(is_wp_error($result) ? $result->get_error_code() : 'success_password');
                break;
            case 'sign_out_others':
                self::redirect(self::sign_out_other_sessions($user));
                break;
            case 'export':
                self::export_data($user);
                break;
            case 'delete':
                self::delete_account($user);
                break;
            default:
                self::redirect('error_security');
        }
    }

    /**
     * Account settings section.
     *
     * @param WP_User $user
     */
    public static function render($user)
    {
        $user_id = (int) $user->ID;
        $email = (string) $user->user_email;
        $pending = get_user_meta($user_id, self::META_NEW_EMAIL, true);
        $pending_email = (is_array($pending) && !empty($pending['newemail'])) ? (string) $pending['newemail'] : '';
        $notice = isset($_GET['mjb_notice']) ? sanitize_key(wp_unslash($_GET['mjb_notice'])) : '';
        $apps = self::allows_email('applications', $user_id);
        $messages = self::allows_email('messages', $user_id);
        $news = self::allows_email('news', $user_id);
        $frequency = class_exists('MJB_Job_Alerts') ? MJB_Job_Alerts::preference_for_user($user_id) : 'off';
        $changed = (int) get_user_meta($user_id, self::META_PASSWORD_CHANGED, true);
        $sessions = self::sessions_for_user($user_id);
        $others = 0;
        foreach ($sessions as $session) {
            if (empty($session['current'])) {
                $others++;
            }
        }
        ?>
        <div class="mjb-cd-sec" id="mjb-cd-account">
            <h2><?php echo self::icon('settings', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Account settings', 'modern-job-board'); ?></h2>
        </div>

        <div class="mjb-cd-cols mjb-cd-cols--main mjb-cd-account">
            <div class="mjb-cd-account-main">
                <form method="post" action="<?php echo esc_url(self::account_url()); ?>" id="mjb-cd-account-form" class="mjb-form mjb-cd-panel mjb-cd-form" novalidate>
                    <?php wp_nonce_field('mjb_account_action', 'mjb_account_nonce'); ?>
                    <button type="submit" name="mjb_account_action" value="save" hidden><?php esc_html_e('Save changes', 'modern-job-board'); ?></button>
                    <div class="mjb-cd-panel-head">
                        <h3><?php echo self::icon('mail', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Email & notifications', 'modern-job-board'); ?></h3>
                    </div>

                    <fieldset class="mjb-cd-group">
                        <legend><?php esc_html_e('Sign-in email', 'modern-job-board'); ?></legend>
                        <div class="mjb-field<?php echo $notice === 'error_invalid_email' || $notice === 'error_email_exists' ? ' is-invalid' : ''; ?>" id="mjb-cd-email-field">
                            <div class="mjb-cd-lbl-row">
                                <label for="mjb_account_email"><?php esc_html_e('Email address', 'modern-job-board'); ?></label>
                                <span class="mjb-cd-chip" id="mjb-cd-email-chip"<?php echo $pending_email !== '' ? ' hidden' : ''; ?>>
                                    <?php echo self::icon('check', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php esc_html_e('Verified', 'modern-job-board'); ?>
                                </span>
                            </div>
                            <input class="mjb-cd-input" type="email" name="mjb_account_email" id="mjb_account_email" value="<?php echo esc_attr($email); ?>" autocomplete="email" required data-current-email="<?php echo esc_attr($email); ?>">
                            <p class="mjb-cd-field-hint"><?php esc_html_e('You sign in with this address, and it’s where recruiters reply.', 'modern-job-board'); ?></p>
                            <div class="mjb-cd-pending" id="mjb-cd-email-pending" role="status"<?php echo $pending_email === '' ? ' hidden' : ''; ?>>
                                <?php echo self::icon('mail', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <div>
                                    <div id="mjb-cd-email-pending-text"><?php echo $pending_email !== '' ? self::pending_email_html($pending_email, $email) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                    <div class="mjb-cd-pending-acts">
                                        <button type="submit" class="mjb-cd-linkbtn" name="mjb_account_action" value="resend_email"><span class="mjb-cd-u"><?php esc_html_e('Resend link', 'modern-job-board'); ?></span></button>
                                        <button type="submit" class="mjb-cd-linkbtn" name="mjb_account_action" value="cancel_email"><span class="mjb-cd-u"><?php esc_html_e('Cancel change', 'modern-job-board'); ?></span></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mjb-cd-group">
                        <legend><?php esc_html_e('Email me about', 'modern-job-board'); ?></legend>
                        <?php self::render_switch('mjb_notify_applications', __('My applications', 'modern-job-board'), __('When a recruiter views, shortlists or closes an application you sent.', 'modern-job-board'), $apps); ?>
                        <?php self::render_switch('mjb_notify_messages', __('Messages from recruiters', 'modern-job-board'), __('A copy of every message, so you never miss one.', 'modern-job-board'), $messages); ?>
                        <?php self::render_switch('mjb_notify_news', __('Tips and news', 'modern-job-board'), __('Occasional advice on applying, and what’s new on the site.', 'modern-job-board'), $news); ?>
                    </fieldset>

                    <fieldset class="mjb-cd-group">
                        <legend><?php esc_html_e('Job alerts', 'modern-job-board'); ?></legend>
                        <div class="mjb-cd-alerts">
                            <div class="mjb-cd-seg" role="radiogroup" aria-label="<?php esc_attr_e('Job alert frequency', 'modern-job-board'); ?>">
                                <?php
                                foreach (array(
                                    'off' => __('Off', 'modern-job-board'),
                                    'daily' => __('Daily', 'modern-job-board'),
                                    'weekly' => __('Weekly', 'modern-job-board'),
                                ) as $value => $label) :
                                    ?>
                                    <input type="radio" name="mjb_alert_frequency" id="mjb-alert-<?php echo esc_attr($value); ?>" value="<?php echo esc_attr($value); ?>" <?php checked($frequency, $value); ?>>
                                    <label for="mjb-alert-<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="mjb-cd-field-hint" id="mjb-cd-alert-hint"><?php echo esc_html(self::alert_hint($frequency)); ?></p>
                        </div>
                    </fieldset>

                    <div class="mjb-cd-savebar" id="mjb-cd-account-savebar">
                        <span class="mjb-cd-save-status" aria-live="polite"><span class="mjb-cd-dot" aria-hidden="true"></span><span id="mjb-cd-account-save-text"><?php esc_html_e('All changes saved', 'modern-job-board'); ?></span></span>
                        <button class="btn btn-outline" type="reset" id="mjb-cd-account-discard" disabled><?php esc_html_e('Discard', 'modern-job-board'); ?></button>
                        <button class="btn btn-primary" type="submit" name="mjb_account_action" value="save" id="mjb-cd-account-save" disabled>
                            <span class="mjb-cd-btn-spin" hidden></span>
                            <span class="mjb-cd-save-label"><?php esc_html_e('Save changes', 'modern-job-board'); ?></span>
                        </button>
                    </div>
                </form>

                <form method="post" action="<?php echo esc_url(self::account_url()); ?>" id="mjb-cd-password-form" class="mjb-form mjb-cd-panel mjb-cd-form" novalidate autocomplete="on">
                    <?php wp_nonce_field('mjb_account_action', 'mjb_account_nonce'); ?>
                    <input type="hidden" name="mjb_account_action" value="password">
                    <input class="mjb-cd-sr" type="text" name="mjb_account_username" id="mjb_account_username" value="<?php echo esc_attr($email); ?>" autocomplete="username" readonly tabindex="-1">
                    <input type="hidden" name="mjb_current_password_typed" id="mjb_current_password_typed" value="">
                    <div class="mjb-cd-panel-head">
                        <h3><?php echo self::icon('lock', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Password', 'modern-job-board'); ?></h3>
                        <span class="mjb-cd-spacer"></span>
                        <span class="mjb-cd-meta" id="mjb-cd-password-changed"<?php echo $changed > 0 ? '' : ' hidden'; ?>><?php
                        if ($changed > 0) {
                            echo esc_html(self::password_changed_label($changed));
                        }
                        ?></span>
                    </div>
                    <fieldset class="mjb-cd-group" aria-label="<?php esc_attr_e('Change your password', 'modern-job-board'); ?>">
                        <div class="mjb-field mjb-field-full" id="mjb-cd-current-password-field">
                            <label for="mjb_current_password"><?php esc_html_e('Current password', 'modern-job-board'); ?></label>
                            <div class="mjb-cd-pw">
                                <input class="mjb-cd-input" type="password" name="mjb_current_password" id="mjb_current_password" autocomplete="current-password" aria-describedby="mjb-cd-current-password-err">
                                <?php self::render_reveal('mjb_current_password'); ?>
                            </div>
                            <p class="mjb-cd-err" id="mjb-cd-current-password-err" hidden></p>
                        </div>
                        <div class="mjb-field mjb-field-full" id="mjb-cd-new-password-field">
                            <label for="mjb_new_password"><?php esc_html_e('New password', 'modern-job-board'); ?></label>
                            <div class="mjb-cd-pw">
                                <input class="mjb-cd-input" type="password" name="mjb_new_password" id="mjb_new_password" autocomplete="new-password" aria-describedby="mjb-cd-pw-strength mjb-cd-new-password-err">
                                <?php self::render_reveal('mjb_new_password'); ?>
                            </div>
                            <div class="mjb-cd-meter" id="mjb-cd-meter" data-s="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            <p class="mjb-cd-strength" id="mjb-cd-pw-strength" aria-live="polite"><?php esc_html_e('Strength:', 'modern-job-board'); ?> <b id="mjb-cd-pw-word">—</b></p>
                            <p class="mjb-cd-err" id="mjb-cd-new-password-err" hidden></p>
                            <p class="mjb-cd-field-hint"><?php esc_html_e('Use 12 or more characters. A short phrase of unrelated words works well.', 'modern-job-board'); ?></p>
                        </div>
                    </fieldset>
                    <p class="mjb-cd-pw-status" id="mjb-cd-password-status" role="status" hidden></p>
                    <div class="mjb-cd-panel-foot">
                        <span class="mjb-cd-meta"><?php esc_html_e('You’ll stay signed in on this device.', 'modern-job-board'); ?></span>
                        <button class="btn btn-primary" type="submit" id="mjb-cd-password-save" disabled>
                            <span class="mjb-cd-btn-spin" hidden></span>
                            <span class="mjb-cd-password-label"><?php esc_html_e('Update password', 'modern-job-board'); ?></span>
                        </button>
                    </div>
                </form>
            </div>

            <aside class="mjb-cd-side mjb-cd-account-side">
                <div class="mjb-cd-panel">
                    <div class="mjb-cd-panel-head mjb-cd-panel-head--flush">
                        <h3><?php echo self::icon('shield-check', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Where you’re signed in', 'modern-job-board'); ?></h3>
                    </div>
                    <div class="mjb-cd-panel-body mjb-cd-panel-body--pad">
                        <div class="mjb-cd-sessions">
                            <?php foreach ($sessions as $session) : ?>
                                <div class="mjb-cd-file<?php echo empty($session['current']) ? ' is-other' : ''; ?>">
                                    <span class="mjb-cd-ficon" aria-hidden="true"><?php echo self::icon($session['icon'], 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <div class="mjb-cd-file-txt">
                                        <b><?php echo esc_html($session['label']); ?></b>
                                        <span><?php echo esc_html($session['detail']); ?></span>
                                    </div>
                                    <?php if (!empty($session['current'])) : ?>
                                        <span class="mjb-cd-chip"><?php esc_html_e('This device', 'modern-job-board'); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($others > 0) : ?>
                            <form method="post" action="<?php echo esc_url(self::account_url()); ?>" class="mjb-cd-inline-form">
                                <?php wp_nonce_field('mjb_account_action', 'mjb_account_nonce'); ?>
                                <button class="btn btn-outline" type="submit" name="mjb_account_action" value="sign_out_others">
                                    <?php echo self::icon('log-out', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php esc_html_e('Sign out other devices', 'modern-job-board'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mjb-cd-panel">
                    <div class="mjb-cd-panel-head mjb-cd-panel-head--flush">
                        <h3><?php echo self::icon('download', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Your data', 'modern-job-board'); ?></h3>
                    </div>
                    <div class="mjb-cd-panel-body mjb-cd-panel-body--pad">
                        <p><?php esc_html_e('Download a copy of your profile, CV details, applications and saved jobs.', 'modern-job-board'); ?></p>
                        <form method="post" action="<?php echo esc_url(self::account_url()); ?>" class="mjb-cd-inline-form">
                            <?php wp_nonce_field('mjb_account_action', 'mjb_account_nonce'); ?>
                            <button class="btn btn-outline" type="submit" name="mjb_account_action" value="export">
                                <?php echo self::icon('download', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php esc_html_e('Download my data', 'modern-job-board'); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mjb-cd-panel mjb-cd-danger">
                    <div class="mjb-cd-panel-head mjb-cd-panel-head--flush">
                        <h3><?php echo self::icon('trash-2', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Delete account', 'modern-job-board'); ?></h3>
                    </div>
                    <div class="mjb-cd-panel-body mjb-cd-panel-body--pad">
                        <p><?php esc_html_e('Removes your profile, CV and saved jobs for good. Recruiters keep copies of applications you’ve already sent.', 'modern-job-board'); ?></p>
                        <div class="mjb-cd-inline-form">
                            <button class="btn btn-outline mjb-cd-danger-btn" type="button" id="mjb-cd-delete-open">
                                <?php echo self::icon('trash-2', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php esc_html_e('Delete account', 'modern-job-board'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <dialog class="mjb-cd-dialog" id="mjb-cd-delete-dialog" aria-labelledby="mjb-cd-delete-title"<?php echo $notice === 'error_delete_confirm' ? ' data-open="1"' : ''; ?>>
            <button type="button" class="mjb-cd-sq mjb-cd-dialog-x" data-mjb-dialog-close aria-label="<?php esc_attr_e('Close', 'modern-job-board'); ?>">
                <?php echo self::icon('x', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
            <form method="post" action="<?php echo esc_url(self::account_url()); ?>" id="mjb-cd-delete-form">
                <?php wp_nonce_field('mjb_account_action', 'mjb_account_nonce'); ?>
                <input type="hidden" name="mjb_account_action" value="delete">
                <div class="mjb-cd-dialog-body">
                    <div class="mjb-cd-dialog-ic" aria-hidden="true"><?php echo self::icon('triangle-alert', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <h3 id="mjb-cd-delete-title"><?php esc_html_e('Delete your account?', 'modern-job-board'); ?></h3>
                    <p><?php echo wp_kses(
                        sprintf(
                            /* translators: %s: candidate email address */
                            __('This permanently deletes the account for <b>%s</b>.', 'modern-job-board'),
                            esc_html($email)
                        ),
                        array('b' => array())
                    ); ?></p>
                    <ul>
                        <li><?php esc_html_e('Your profile, CV and saved jobs are removed', 'modern-job-board'); ?></li>
                        <li><?php esc_html_e('You stop receiving job alerts and emails', 'modern-job-board'); ?></li>
                        <li><?php esc_html_e('Recruiters keep applications you’ve already sent', 'modern-job-board'); ?></li>
                    </ul>
                    <div class="mjb-field<?php echo $notice === 'error_delete_confirm' ? ' is-invalid' : ''; ?>">
                        <label for="mjb_delete_confirm"><?php echo wp_kses(__('Type <b>DELETE</b> to confirm', 'modern-job-board'), array('b' => array())); ?></label>
                        <input class="mjb-cd-input" type="text" name="mjb_delete_confirm" id="mjb_delete_confirm" autocomplete="off" autocapitalize="off" spellcheck="false" data-confirm-word="<?php echo esc_attr(self::DELETE_WORD); ?>">
                    </div>
                </div>
                <div class="mjb-cd-dialog-foot">
                    <button class="btn btn-outline" type="button" id="mjb-cd-delete-cancel"><?php esc_html_e('Keep my account', 'modern-job-board'); ?></button>
                    <button class="btn mjb-cd-btn-danger" type="submit" id="mjb-cd-delete-submit" disabled><?php esc_html_e('Delete account', 'modern-job-board'); ?></button>
                </div>
            </form>
        </dialog>
        <?php
    }

    /**
     * @param string $name
     * @param string $title
     * @param string $help
     * @param bool   $on
     */
    private static function render_switch($name, $title, $help, $on)
    {
        ?>
        <label class="mjb-cd-pref" for="<?php echo esc_attr($name); ?>">
            <span class="mjb-cd-pref-txt"><b><?php echo esc_html($title); ?></b><span><?php echo esc_html($help); ?></span></span>
            <input class="mjb-cd-switch" type="checkbox" role="switch" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="1" <?php checked($on); ?>>
        </label>
        <?php
    }

    /**
     * @param string $input_id
     */
    private static function render_reveal($input_id)
    {
        ?>
        <button class="mjb-cd-reveal" type="button" data-for="<?php echo esc_attr($input_id); ?>" aria-pressed="false" aria-label="<?php esc_attr_e('Show password', 'modern-job-board'); ?>">
            <span class="mjb-cd-reveal-show"><?php echo self::icon('eye', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="mjb-cd-reveal-hide" hidden><?php echo self::icon('eye-off', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        </button>
        <?php
    }

    /**
     * @param string $frequency
     * @return string
     */
    private static function alert_hint($frequency)
    {
        if ($frequency === 'daily') {
            return __('New jobs that match your saved searches, each day.', 'modern-job-board');
        }
        if ($frequency === 'weekly') {
            return __('New jobs that match your saved searches, once a week.', 'modern-job-board');
        }
        return __('You won’t get job alerts by email. You can still browse matches on the site.', 'modern-job-board');
    }

    /**
     * Confirmation sentence for an email address that is not signed in yet.
     *
     * @param string $pending_email
     * @param string $current_email
     * @return string
     */
    private static function pending_email_html($pending_email, $current_email)
    {
        return wp_kses(
            sprintf(
                /* translators: 1: pending email address, 2: current email address */
                __('Confirm <b>%1$s</b> to finish the change. Until then, you keep signing in with <b>%2$s</b>.', 'modern-job-board'),
                esc_html($pending_email),
                esc_html($current_email)
            ),
            array('b' => array())
        );
    }

    /**
     * @param WP_User $user
     * @return string Notice code.
     */
    private static function save_settings($user)
    {
        $user_id = (int) $user->ID;
        $email = isset($_POST['mjb_account_email']) ? sanitize_email(wp_unslash($_POST['mjb_account_email'])) : '';
        if ($email === '' || !is_email($email)) {
            return 'error_invalid_email';
        }
        $owner = email_exists($email);
        if ($owner && (int) $owner !== $user_id) {
            return 'error_email_exists';
        }

        update_user_meta($user_id, self::META_NOTIFY_APPLICATIONS, empty($_POST['mjb_notify_applications']) ? '0' : '1');
        update_user_meta($user_id, self::META_NOTIFY_MESSAGES, empty($_POST['mjb_notify_messages']) ? '0' : '1');
        update_user_meta($user_id, self::META_NOTIFY_NEWS, empty($_POST['mjb_notify_news']) ? '0' : '1');

        $frequency = isset($_POST['mjb_alert_frequency']) ? sanitize_key(wp_unslash($_POST['mjb_alert_frequency'])) : 'off';
        if (class_exists('MJB_Job_Alerts')) {
            MJB_Job_Alerts::set_preference_for_user($user_id, $frequency);
        }

        if (strtolower($email) === strtolower((string) $user->user_email)) {
            return 'success_account';
        }

        $hash = wp_hash($email . '|' . $user_id . '|' . wp_rand());
        update_user_meta($user_id, self::META_NEW_EMAIL, array(
            'hash' => $hash,
            'newemail' => $email,
        ));
        self::send_email_confirmation($user, $email, $hash);
        return 'success_email_pending';
    }

    /**
     * @param WP_User $user
     * @param string  $email
     * @param string  $hash
     */
    private static function send_email_confirmation($user, $email, $hash)
    {
        $link = add_query_arg('mjb_confirm_email', rawurlencode($hash), self::account_url());
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Confirm your new email', 'modern-job-board'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );
        $message = sprintf(
            /* translators: 1: new email, 2: confirmation URL, 3: current email */
            __("Confirm %1\$s as your sign-in email:\n\n%2\$s\n\nUntil you open that link, you keep signing in with %3\$s.", 'modern-job-board'),
            $email,
            $link,
            $user->user_email
        );
        wp_mail($email, $subject, $message);
    }

    /**
     * @param string $hash
     */
    private static function confirm_email_change($hash)
    {
        if ($hash === '') {
            return;
        }
        if (!is_user_logged_in()) {
            $return = add_query_arg('mjb_confirm_email', rawurlencode($hash), self::account_url());
            $login = class_exists('MJB_Login')
                ? MJB_Login::get_frontend_login_url('candidate', $return)
                : wp_login_url($return);
            wp_safe_redirect($login);
            exit;
        }
        $user = wp_get_current_user();
        $pending = get_user_meta($user->ID, self::META_NEW_EMAIL, true);
        if (!is_array($pending) || empty($pending['hash']) || empty($pending['newemail']) || !hash_equals((string) $pending['hash'], $hash)) {
            self::redirect('error_email_pending');
        }
        $email = sanitize_email((string) $pending['newemail']);
        $owner = email_exists($email);
        if ($email === '' || !is_email($email) || ($owner && (int) $owner !== (int) $user->ID)) {
            delete_user_meta($user->ID, self::META_NEW_EMAIL);
            self::redirect('error_email_exists');
        }
        $updated = wp_update_user(array(
            'ID' => (int) $user->ID,
            'user_email' => $email,
        ));
        if (is_wp_error($updated)) {
            self::redirect('error_email_exists');
        }
        delete_user_meta($user->ID, self::META_NEW_EMAIL);
        self::redirect('success_email_confirmed');
    }

    /**
     * @param WP_User $user
     * @return string
     */
    private static function resend_email_change($user)
    {
        $pending = get_user_meta($user->ID, self::META_NEW_EMAIL, true);
        if (!is_array($pending) || empty($pending['hash']) || empty($pending['newemail'])) {
            return 'error_email_pending';
        }
        self::send_email_confirmation($user, (string) $pending['newemail'], (string) $pending['hash']);
        return 'success_email_resent';
    }

    /**
     * @param WP_User $user
     * @return string
     */
    private static function cancel_email_change($user)
    {
        delete_user_meta($user->ID, self::META_NEW_EMAIL);
        return 'success_email_cancelled';
    }

    /**
     * AJAX password update. One request, so a second submit cannot fail after the first succeeds.
     */
    public static function ajax_change_password()
    {
        check_ajax_referer('mjb_account_action', 'mjb_account_nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'code' => 'error_permission',
                'message' => __('Please log in again.', 'modern-job-board'),
            ), 401);
        }
        $user = wp_get_current_user();
        if (!$user || !in_array('candidate', (array) $user->roles, true)) {
            wp_send_json_error(array(
                'code' => 'error_permission',
                'message' => __('This dashboard is for candidates only.', 'modern-job-board'),
            ), 403);
        }
        $result = self::apply_password_change($user);
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ));
        }
        wp_send_json_success($result);
    }

    /**
     * Save account settings without leaving the page.
     */
    public static function ajax_save_account()
    {
        check_ajax_referer('mjb_account_action', 'mjb_account_nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'code' => 'error_permission',
                'message' => MJB_Notices::message('error_permission'),
            ), 401);
        }
        $user = wp_get_current_user();
        if (!$user || !in_array('candidate', (array) $user->roles, true)) {
            wp_send_json_error(array(
                'code' => 'error_permission',
                'message' => __('This dashboard is for candidates only.', 'modern-job-board'),
            ), 403);
        }
        $posted = isset($_POST['mjb_account_action']) ? sanitize_key(wp_unslash($_POST['mjb_account_action'])) : 'save';
        if ($posted === 'resend_email') {
            $code = self::resend_email_change($user);
        } elseif ($posted === 'cancel_email') {
            $code = self::cancel_email_change($user);
        } elseif ($posted === 'sign_out_others') {
            $code = self::sign_out_other_sessions($user);
        } else {
            $code = self::save_settings($user);
        }
        $payload = array(
            'code' => $code,
            'message' => MJB_Notices::message($code),
        );
        if (strpos($code, 'error_') === 0) {
            wp_send_json_error($payload);
        }
        if ($code === 'success_email_pending') {
            $pending = get_user_meta((int) $user->ID, self::META_NEW_EMAIL, true);
            $pending_email = (is_array($pending) && !empty($pending['newemail'])) ? (string) $pending['newemail'] : '';
            $payload['pending_html'] = self::pending_email_html($pending_email, (string) $user->user_email);
        }
        wp_send_json_success($payload);
    }

    /**
     * @param WP_User $user
     * @return array|WP_Error
     */
    private static function apply_password_change($user)
    {
        $current = isset($_POST['mjb_current_password']) ? (string) wp_unslash($_POST['mjb_current_password']) : '';
        $typed = isset($_POST['mjb_current_password_typed']) ? (string) wp_unslash($_POST['mjb_current_password_typed']) : '';
        $new = isset($_POST['mjb_new_password']) ? trim((string) wp_unslash($_POST['mjb_new_password'])) : '';
        $stored = get_user_by('id', $user->ID);
        $hash = ($stored instanceof WP_User && $stored->user_pass !== '') ? (string) $stored->user_pass : (string) $user->user_pass;
        $current_ok = self::current_password_matches($current, $hash, $user->ID) || self::current_password_matches($typed, $hash, $user->ID);
        if (!$current_ok) {
            $changed_at = (int) get_user_meta($user->ID, self::META_PASSWORD_CHANGED, true);
            $just_changed = $changed_at > 0 && (time() - $changed_at) < 15;
            if ($just_changed && self::current_password_matches($new, $hash, $user->ID)) {
                return array(
                    'message' => __('Password updated. You are still signed in on this device.', 'modern-job-board'),
                    'changed' => self::password_changed_label($changed_at),
                );
            }
            return new WP_Error('error_current_password', __('That does not match your current password.', 'modern-job-board'));
        }
        if (strlen($new) < self::PASSWORD_MIN) {
            return new WP_Error('error_password_short', __('Use 12 or more characters for your new password.', 'modern-job-board'));
        }
        wp_set_password($new, $user->ID);
        $changed = time();
        update_user_meta($user->ID, self::META_PASSWORD_CHANGED, $changed);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        return array(
            'message' => __('Password updated. You are still signed in on this device.', 'modern-job-board'),
            'changed' => self::password_changed_label($changed),
        );
    }

    /**
     * @param int $timestamp
     * @return string
     */
    private static function password_changed_label($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            $timestamp = time();
        }
        return sprintf(
            /* translators: %s: date the password was last changed on this site */
            __('Changed %s', 'modern-job-board'),
            function_exists('wp_date') ? wp_date(get_option('date_format'), $timestamp) : gmdate('F j, Y', $timestamp)
        );
    }

    /**
     * @param WP_User $user
     * @return string
     */
    private static function sign_out_other_sessions($user)
    {
        if (class_exists('WP_Session_Tokens')) {
            $token = wp_get_session_token();
            WP_Session_Tokens::get_instance($user->ID)->destroy_others($token);
        }
        return 'success_sessions';
    }

    /**
     * @param WP_User $user
     */
    private static function export_data($user)
    {
        $payload = self::export_payload($user);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="mjb-candidate-data.json"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param WP_User $user
     * @return array<string, mixed>
     */
    public static function export_payload($user)
    {
        $user_id = (int) $user->ID;
        $meta_keys = array(
            'first_name',
            'last_name',
            '_candidate_headline',
            '_candidate_city',
            '_candidate_phone',
            '_candidate_linkedin',
            '_candidate_website',
            '_candidate_experience_company',
            '_candidate_bio',
            '_candidate_open_to_work',
            '_candidate_remote_ok',
            '_candidate_is_public',
        );
        $profile = array();
        foreach ($meta_keys as $key) {
            $profile[$key] = (string) get_user_meta($user_id, $key, true);
        }
        $resume_id = (int) get_user_meta($user_id, '_candidate_resume_id', true);
        $profile['resume'] = $resume_id > 0 ? (string) get_the_title($resume_id) : '';

        $applications = array();
        $posts = get_posts(array(
            'post_type' => 'job_application',
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_candidate_email',
                    'value' => $user->user_email,
                ),
            ),
        ));
        if (is_array($posts)) {
            foreach ($posts as $post) {
                $job_id = (int) get_post_meta($post->ID, '_job_applied_for', true);
                $applications[] = array(
                    'job' => $job_id > 0 ? (string) get_the_title($job_id) : (string) $post->post_title,
                    'status' => (string) get_post_meta($post->ID, '_mjb_application_status', true),
                    'date' => (string) $post->post_date,
                );
            }
        }

        $saved = array();
        if (class_exists('MJB_Saved_Jobs')) {
            $jobs = MJB_Saved_Jobs::get_jobs($user_id);
            if (is_array($jobs)) {
                foreach ($jobs as $job) {
                    if (is_object($job) && isset($job->post_title)) {
                        $saved[] = (string) $job->post_title;
                    }
                }
            }
        }

        $alerts = array();
        if (class_exists('MJB_Job_Alerts')) {
            foreach (MJB_Job_Alerts::get_user_alerts($user_id) as $alert) {
                $alerts[] = array(
                    'title' => (string) $alert->post_title,
                    'frequency' => (string) get_post_meta($alert->ID, '_mjb_alert_frequency', true),
                    'paused' => self::alerts_paused($user_id),
                );
            }
        }

        return array(
            'exported_at' => gmdate('c'),
            'account' => array(
                'email' => (string) $user->user_email,
                'registered' => (string) $user->user_registered,
            ),
            'profile' => $profile,
            'applications' => $applications,
            'saved_jobs' => $saved,
            'job_alerts' => $alerts,
        );
    }

    /**
     * @param WP_User $user
     */
    private static function delete_account($user)
    {
        $typed = isset($_POST['mjb_delete_confirm']) ? wp_unslash($_POST['mjb_delete_confirm']) : '';
        if (!self::deletion_confirmed($typed)) {
            self::redirect('error_delete_confirm');
        }
        if (user_can($user->ID, 'manage_options')) {
            self::redirect('error_permission');
        }

        $user_id = (int) $user->ID;
        $resume_id = (int) get_user_meta($user_id, '_candidate_resume_id', true);
        if ($resume_id > 0) {
            wp_delete_post($resume_id, true);
        }
        if (class_exists('MJB_Job_Alerts')) {
            foreach (MJB_Job_Alerts::get_user_alerts($user_id) as $alert) {
                wp_delete_post($alert->ID, true);
            }
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        wp_logout();

        $login = class_exists('MJB_Page_Resolver')
            ? MJB_Page_Resolver::get_page_url('mjb_candidate_login', 'mjb_candidate_login_page_id', array(), '/jobs/candidate-login/')
            : home_url('/jobs/candidate-login/');
        MJB_Notices::redirect($login, 'success_account_deleted');
    }

    /**
     * @param int $user_id
     * @return array<int, array<string, mixed>>
     */
    private static function sessions_for_user($user_id)
    {
        $rows = array();
        $current_session = null;
        if (class_exists('WP_Session_Tokens') && function_exists('wp_get_session_token')) {
            $manager = WP_Session_Tokens::get_instance($user_id);
            $token = (string) wp_get_session_token();
            if ($token !== '') {
                $current_session = $manager->get($token);
            }
            $all = $manager->get_all();
            if (is_array($all)) {
                foreach ($all as $session) {
                    $ua = isset($session['ua']) ? (string) $session['ua'] : '';
                    $login = isset($session['login']) ? (int) $session['login'] : 0;
                    $is_current = is_array($current_session)
                        && (int) ($current_session['expiration'] ?? 0) === (int) ($session['expiration'] ?? 0)
                        && (int) ($current_session['login'] ?? 0) === $login
                        && (string) ($current_session['ua'] ?? '') === $ua;
                    $rows[] = array(
                        'current' => $is_current,
                        'icon' => self::device_icon($ua),
                        'label' => self::device_label($ua),
                        'detail' => $is_current
                            ? __('Active now', 'modern-job-board')
                            : self::session_detail($session, $login),
                        'login' => $login,
                    );
                }
            }
        }
        if (empty($rows)) {
            $rows[] = array(
                'current' => true,
                'icon' => 'monitor',
                'label' => __('This browser', 'modern-job-board'),
                'detail' => __('Active now', 'modern-job-board'),
                'login' => time(),
            );
        }
        usort($rows, function ($a, $b) {
            if (!empty($a['current'])) {
                return -1;
            }
            if (!empty($b['current'])) {
                return 1;
            }
            return (int) $b['login'] - (int) $a['login'];
        });
        return $rows;
    }

    /**
     * @param array<string, mixed> $session
     * @param int                  $login
     * @return string
     */
    private static function session_detail($session, $login)
    {
        $when = $login > 0 ? wp_date(get_option('date_format'), $login) : '';
        $ip = isset($session['ip']) ? (string) $session['ip'] : '';
        if ($ip !== '' && $when !== '') {
            return $ip . ' · ' . $when;
        }
        return $when !== '' ? $when : $ip;
    }

    /**
     * @param string $code
     */
    private static function redirect($code)
    {
        $url = class_exists('MJB_Candidate_Dashboard')
            ? MJB_Candidate_Dashboard::get_page_url(array('mjb_panel' => 'account'))
            : home_url('/jobs/candidate-dashboard/');
        MJB_Notices::redirect($url, $code);
    }

    /**
     * @return string
     */
    private static function account_url()
    {
        if (class_exists('MJB_Candidate_Dashboard')) {
            return MJB_Candidate_Dashboard::get_page_url(array('mjb_panel' => 'account'));
        }
        return home_url('/jobs/candidate-dashboard/');
    }

    /**
     * @param string $name
     * @param int    $size
     * @return string
     */
    private static function icon($name, $size)
    {
        if (!class_exists('MJB_Icons')) {
            return '';
        }
        return MJB_Icons::render($name, $size);
    }
}
