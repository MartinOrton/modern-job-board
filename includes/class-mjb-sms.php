<?php
/**
 * SMS notifications stub (#38).
 * Pluggable provider via filters; no vendor lock-in.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Sms
{
    const OPTION_ENABLED = 'mjb_sms_enabled';
    const OPTION_FROM = 'mjb_sms_from';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('mjb_application_status_updated', array(__CLASS__, 'on_application_status'), 20, 3);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * Settings.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_ENABLED, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_FROM, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * @param string $to_e164
     * @param string $message
     * @return true|WP_Error
     */
    public static function send($to_e164, $message)
    {
        if (get_option(self::OPTION_ENABLED, '0') !== '1') {
            return new WP_Error('mjb_sms_disabled', 'disabled');
        }
        $to_e164 = preg_replace('/[^\d+]/', '', (string) $to_e164);
        $message = wp_strip_all_tags((string) $message);
        if ($to_e164 === '' || $message === '') {
            return new WP_Error('mjb_sms_invalid', 'invalid');
        }

        /**
         * Providers hook here (Twilio, MessageBird, etc.).
         *
         * @param true|WP_Error $result
         * @param string        $to
         * @param string        $message
         * @param string        $from
         */
        $result = apply_filters('mjb_sms_send', null, $to_e164, $message, get_option(self::OPTION_FROM, ''));
        if ($result === null) {
            // Log for debugging when no provider connected.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MJB SMS] No provider. To=' . $to_e164 . ' Msg=' . $message);
            }
            return new WP_Error('mjb_sms_no_provider', __('No SMS provider configured. Hook mjb_sms_send.', 'modern-job-board'));
        }
        return $result;
    }

    /**
     * Notify candidate by SMS when status changes (if phone on file).
     *
     * @param int    $application_id
     * @param string $old
     * @param string $new
     */
    public static function on_application_status($application_id, $old, $new)
    {
        if ($old === $new) {
            return;
        }
        $phone = get_post_meta($application_id, '_candidate_phone', true);
        if ($phone === '') {
            $user_id = (int) get_post_meta($application_id, '_candidate_user_id', true);
            if ($user_id) {
                $phone = get_user_meta($user_id, '_mjb_phone', true);
            }
        }
        if ($phone === '') {
            return;
        }
        $label = class_exists('MJB_Application_Status') ? MJB_Application_Status::get_label($new) : $new;
        $msg = sprintf(
            /* translators: %s: status label */
            __('Your application status is now: %s', 'modern-job-board'),
            $label
        );
        self::send($phone, $msg);
    }
}
