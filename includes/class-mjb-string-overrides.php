<?php
/**
 * Full text customization UI (#27).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_String_Overrides
{
    const OPTION = 'mjb_string_overrides';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_filter('gettext', array(__CLASS__, 'filter_gettext'), 20, 3);
        add_filter('gettext_with_context', array(__CLASS__, 'filter_gettext_context'), 20, 4);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 56);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * @return array<string, string>
     */
    public static function get_map()
    {
        $map = get_option(self::OPTION, array());
        return is_array($map) ? $map : array();
    }

    /**
     * @param string $translation
     * @param string $text
     * @param string $domain
     * @return string
     */
    public static function filter_gettext($translation, $text, $domain)
    {
        if ($domain !== 'modern-job-board') {
            return $translation;
        }
        $map = self::get_map();
        if (isset($map[$text]) && $map[$text] !== '') {
            return $map[$text];
        }
        return $translation;
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
        return self::filter_gettext($translation, $text, $domain);
    }

    /**
     * Register option.
     */
    public static function register_settings()
    {
        register_setting('mjb_strings_group', self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default' => array(),
        ));
    }

    /**
     * @param mixed $input
     * @return array
     */
    public static function sanitize($input)
    {
        $out = array();
        if (!is_array($input)) {
            return $out;
        }
        $originals = isset($input['original']) && is_array($input['original']) ? $input['original'] : array();
        $replacements = isset($input['replace']) && is_array($input['replace']) ? $input['replace'] : array();
        $n = max(count($originals), count($replacements));
        for ($i = 0; $i < $n; $i++) {
            $o = isset($originals[$i]) ? wp_kses_post(wp_unslash($originals[$i])) : '';
            $r = isset($replacements[$i]) ? wp_kses_post(wp_unslash($replacements[$i])) : '';
            $o = trim(wp_strip_all_tags($o));
            $r = trim($r);
            if ($o !== '' && $r !== '') {
                $out[$o] = $r;
            }
        }
        // Also accept associative form from single fields.
        foreach ($input as $k => $v) {
            if ($k === 'original' || $k === 'replace') {
                continue;
            }
            if (is_string($k) && is_string($v) && $k !== '' && $v !== '') {
                $out[wp_strip_all_tags($k)] = wp_kses_post($v);
            }
        }
        return $out;
    }

    /**
     * Admin menu.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Text overrides', 'modern-job-board'),
            __('Text overrides', 'modern-job-board'),
            'manage_options',
            'mjb-strings',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Settings UI.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $map = self::get_map();
        $pairs = array();
        foreach ($map as $o => $r) {
            $pairs[] = array($o, $r);
        }
        while (count($pairs) < 5) {
            $pairs[] = array('', '');
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('UI text overrides', 'modern-job-board'); ?></h1>
            <p><?php esc_html_e('Replace plugin UI strings without editing code. Match the original English string exactly.', 'modern-job-board'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('mjb_strings_group'); ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Original', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('Replacement', 'modern-job-board'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pairs as $i => $pair) : ?>
                            <tr>
                                <td><input type="text" class="large-text" name="<?php echo esc_attr(self::OPTION); ?>[original][<?php echo (int) $i; ?>]" value="<?php echo esc_attr($pair[0]); ?>"></td>
                                <td><input type="text" class="large-text" name="<?php echo esc_attr(self::OPTION); ?>[replace][<?php echo (int) $i; ?>]" value="<?php echo esc_attr($pair[1]); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
