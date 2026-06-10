<?php
/**
 * Modern Job Board reCAPTCHA Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Recaptcha
{
    const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Whether reCAPTCHA is enabled and configured.
     *
     * @return bool
     */
    public static function is_enabled()
    {
        return (bool) get_option('mjb_recaptcha_enabled')
            && self::get_site_key() !== ''
            && self::get_secret_key() !== '';
    }

    /**
     * Get the configured site key.
     *
     * @return string
     */
    public static function get_site_key()
    {
        return sanitize_text_field(get_option('mjb_recaptcha_site_key', ''));
    }

    /**
     * Get the configured secret key.
     *
     * @return string
     */
    public static function get_secret_key()
    {
        return sanitize_text_field(get_option('mjb_recaptcha_secret_key', ''));
    }

    /**
     * Verify a reCAPTCHA response token.
     *
     * @param string|null $response
     * @return bool
     */
    public static function verify($response = null)
    {
        if (!self::is_enabled()) {
            return true;
        }

        if ($response === null) {
            $response = isset($_POST['g-recaptcha-response'])
                ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response']))
                : '';
        }

        if ($response === '') {
            return false;
        }

        $result = wp_remote_post(self::VERIFY_URL, array(
            'body' => array(
                'secret' => self::get_secret_key(),
                'response' => $response,
                'remoteip' => MJB_Application_Guard::get_client_ip(),
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($result)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($result), true);

        return !empty($body['success']);
    }
}