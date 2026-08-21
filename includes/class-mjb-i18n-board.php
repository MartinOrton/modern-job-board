<?php
/**
 * Multi-language product mode (#28) — simple board language switcher.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_I18n_Board
{
    const COOKIE = 'mjb_board_lang';
    const OPTION = 'mjb_board_languages';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'maybe_set_language'), 1);
        add_filter('locale', array(__CLASS__, 'filter_locale'), 20);
        add_shortcode('mjb_language_switcher', array(__CLASS__, 'render_switcher'));
        add_action('admin_init', array(__CLASS__, 'register_setting'));
    }

    /**
     * Available language codes (comma list in option).
     *
     * @return string[]
     */
    public static function available()
    {
        $raw = get_option(self::OPTION, 'en_US');
        $parts = array_filter(array_map('trim', explode(',', (string) $raw)));
        return !empty($parts) ? $parts : array('en_US');
    }

    /**
     * Capture ?mjb_lang=
     */
    public static function maybe_set_language()
    {
        if (empty($_GET['mjb_lang'])) {
            return;
        }
        $lang = sanitize_text_field(wp_unslash($_GET['mjb_lang']));
        if (!in_array($lang, self::available(), true)) {
            return;
        }
        setcookie(self::COOKIE, $lang, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::COOKIE] = $lang;
    }

    /**
     * @param string $locale
     * @return string
     */
    public static function filter_locale($locale)
    {
        if (is_admin() && !wp_doing_ajax()) {
            return $locale;
        }
        if (!empty($_COOKIE[self::COOKIE])) {
            $lang = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE]));
            if (in_array($lang, self::available(), true)) {
                return $lang;
            }
        }
        return $locale;
    }

    /**
     * Settings field.
     */
    public static function register_setting()
    {
        register_setting('mjb_settings_group', self::OPTION, array(
            'type' => 'string',
            'default' => 'en_US',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * Switcher shortcode.
     *
     * @return string
     */
    public static function render_switcher()
    {
        $langs = self::available();
        if (count($langs) < 2) {
            return '';
        }
        $current = !empty($_COOKIE[self::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])) : get_locale();
        ob_start();
        echo '<nav class="mjb-lang-switcher" aria-label="' . esc_attr__('Language', 'modern-job-board') . '"><ul>';
        foreach ($langs as $lang) {
            $url = add_query_arg('mjb_lang', $lang);
            $label = $lang;
            echo '<li' . ($lang === $current ? ' class="is-active"' : '') . '><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
        }
        echo '</ul></nav>';
        return (string) ob_get_clean();
    }
}
