<?php
/**
 * Display label for candidate documents: Resume vs CV.
 *
 * Storage, post types, meta keys, and PHP identifiers stay "resume".
 * This only changes user-facing copy.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Document_Label
{
    const OPTION = 'mjb_document_label';
    const USER_META = '_mjb_document_label';
    const RESUME = 'resume';
    const CV = 'cv';

    /**
     * Hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_setting'), 20);
        add_filter('gettext', array(__CLASS__, 'filter_gettext'), 25, 3);
        add_filter('gettext_with_context', array(__CLASS__, 'filter_gettext_context'), 25, 4);
        add_filter('ngettext', array(__CLASS__, 'filter_ngettext'), 25, 5);
        add_filter('ngettext_with_context', array(__CLASS__, 'filter_ngettext_context'), 25, 6);
        add_filter('mjb_notice_messages', array(__CLASS__, 'filter_notice_messages'));
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function sanitize($value)
    {
        if (is_array($value)) {
            $value = end($value);
        }
        $value = sanitize_key((string) $value);

        return $value === self::CV ? self::CV : self::RESUME;
    }

    /**
     * Site-wide label (candidates and the public board).
     *
     * @return string
     */
    public static function get_site()
    {
        return self::sanitize(get_option(self::OPTION, self::RESUME));
    }

    /**
     * Site-wide label. `$user_id` is ignored — only admins set this in Settings.
     *
     * @param int $user_id
     * @return string
     */
    public static function get($user_id = 0)
    {
        unset($user_id);

        return self::get_site();
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function is_cv($user_id = 0)
    {
        return self::get($user_id) === self::CV;
    }

    /**
     * Persist the site-wide choice. Admins only.
     *
     * @param string $label
     * @param int    $user_id
     * @return bool
     */
    public static function save($label, $user_id = 0)
    {
        $label = self::sanitize($label);
        $user_id = (int) $user_id;
        if ($user_id <= 0 && function_exists('get_current_user_id')) {
            $user_id = (int) get_current_user_id();
        }

        if ($user_id > 0 && function_exists('user_can') && user_can($user_id, 'manage_options')) {
            return (bool) update_option(self::OPTION, $label);
        }

        return false;
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function singular($user_id = 0)
    {
        return self::is_cv($user_id) ? 'CV' : 'Resume';
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function plural($user_id = 0)
    {
        return self::is_cv($user_id) ? 'CVs' : 'Resumes';
    }

    /**
     * Show only the selected noun in already-translated copy.
     *
     * @param string $text
     * @param int    $user_id
     * @return string
     */
    public static function apply_to($text, $user_id = 0)
    {
        $text = (string) $text;
        if (self::is_cv($user_id)) {
            return self::swap($text);
        }

        return self::swap_to_resume($text);
    }

    /**
     * Word-boundary swap of Resume/resume/Resumes/resumes → CV/CVs.
     *
     * @param string $text
     * @return string
     */
    public static function swap($text)
    {
        $text = (string) $text;
        if ($text === '' || stripos($text, 'resume') === false) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/\bResumes?\b|\bresumes?\b/',
            static function ($m) {
                $word = $m[0];
                $is_plural = (bool) preg_match('/s$/i', $word);

                return $is_plural ? 'CVs' : 'CV';
            },
            $text
        );
    }

    /**
     * Word-boundary swap of CV/CVs → Resume/Resumes.
     *
     * @param string $text
     * @return string
     */
    public static function swap_to_resume($text)
    {
        $text = (string) $text;
        if ($text === '' || strpos($text, 'CV') === false) {
            return $text;
        }

        $text = preg_replace('/\bCVs\b/', 'Resumes', $text);
        $text = preg_replace('/\bCV\b/', 'Resume', $text);

        return (string) $text;
    }

    /**
     * @param string $translation
     * @param string $text
     * @param string $domain
     * @return string
     */
    public static function filter_gettext($translation, $text, $domain)
    {
        unset($text);
        if ($domain !== 'modern-job-board') {
            return $translation;
        }

        return self::apply_to($translation);
    }

    /**
     * @param string $translation
     * @param string $text
     * @param string $context
     * @param string $domain
     * @return string
     */
    public static function filter_gettext_context($translation, $text, $context, $domain)
    {
        if ($domain === 'modern-job-board' && $context === 'document label choice') {
            return $translation;
        }

        return self::filter_gettext($translation, $text, $domain);
    }

    /**
     * @param string $translation
     * @param string $single
     * @param string $plural
     * @param int    $number
     * @param string $domain
     * @return string
     */
    public static function filter_ngettext($translation, $single, $plural, $number, $domain)
    {
        unset($single, $plural, $number);
        if ($domain !== 'modern-job-board') {
            return $translation;
        }

        return self::apply_to($translation);
    }

    /**
     * @param string $translation
     * @param string $single
     * @param string $plural
     * @param int    $number
     * @param string $context
     * @param string $domain
     * @return string
     */
    public static function filter_ngettext_context($translation, $single, $plural, $number, $context, $domain)
    {
        unset($context);

        return self::filter_ngettext($translation, $single, $plural, $number, $domain);
    }

    /**
     * @param array<string, string> $messages
     * @return array<string, string>
     */
    public static function filter_notice_messages($messages)
    {
        if (!is_array($messages)) {
            return $messages;
        }
        foreach ($messages as $code => $text) {
            $messages[$code] = self::apply_to((string) $text);
        }

        return $messages;
    }

    /**
     * Settings API: site-wide default on the Listing section.
     *
     * @return void
     */
    public static function register_setting()
    {
        register_setting('mjb_settings_group', self::OPTION, array(
            'type' => 'string',
            'default' => self::RESUME,
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
        ));
        add_settings_field(
            self::OPTION,
            __('Document label', 'modern-job-board'),
            array(__CLASS__, 'render_settings_field'),
            'mjb-settings',
            'mjb_listing_section'
        );
    }

    /**
     * @return void
     */
    public static function render_settings_field()
    {
        self::render_choice_fields(self::OPTION, self::get_site(), 'mjb_document_label');
        echo '<p class="description">' . esc_html__('Name used on buttons, tabs, and forms. Stored files and code are unchanged.', 'modern-job-board') . '</p>';
    }

    /**
     * Native checkboxes matching other Listing Settings fields. Exclusive via admin JS.
     *
     * @param string $name
     * @param string $current
     * @param string $id_prefix
     * @return void
     */
    public static function render_choice_fields($name, $current, $id_prefix = 'mjb_document_label')
    {
        $current = self::sanitize($current);
        $options = array(
            self::RESUME => _x('Resume', 'document label choice', 'modern-job-board'),
            self::CV => _x('CV', 'document label choice', 'modern-job-board'),
        );
        echo '<fieldset class="mjb-doc-label-choices">';
        echo '<legend class="mjb-sr-only">' . esc_html__('Document label', 'modern-job-board') . '</legend>';
        foreach ($options as $value => $label) {
            $id = $id_prefix . '_' . $value;
            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" class="mjb-doc-label-choice" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" ' . checked($current, $value, false) . '> ';
            echo esc_html($label);
            echo '</label><br>';
        }
        echo '</fieldset>';
    }

    /**
     * Recruiter dashboard no longer exposes this control.
     *
     * @return void
     */
    public static function render_recruiter_form()
    {
    }

    /**
     * Recruiter dashboard POST handler. No-op: only Settings (admins) may change the label.
     *
     * @return bool True when this request was a document-label post.
     */
    public static function handle_dashboard_post()
    {
        $action = isset($_POST['mjb_dashboard_action']) ? sanitize_key(wp_unslash($_POST['mjb_dashboard_action'])) : '';

        return $action === 'save_document_label';
    }
}
