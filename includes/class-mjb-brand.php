<?php
/**
 * Brand panel (#26) — logo, colors, banner without theme CSS edits.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Brand
{
    const OPTION = 'mjb_brand';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_head', array(__CLASS__, 'output_css'), 40);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 55);
    }

    /**
     * @return array
     */
    public static function get()
    {
        $defaults = array(
            'primary' => '',
            'primary_hover' => '',
            'logo_url' => '',
            'banner_url' => '',
            'nav_style' => 'light',
        );
        $opt = get_option(self::OPTION, array());
        if (!is_array($opt)) {
            $opt = array();
        }
        return array_merge($defaults, $opt);
    }

    /**
     * Register option.
     */
    public static function register_settings()
    {
        register_setting('mjb_brand_group', self::OPTION, array(
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
        $out = self::get();
        if (!is_array($input)) {
            return $out;
        }
        if (isset($input['primary'])) {
            $out['primary'] = sanitize_hex_color($input['primary']) ?: '';
        }
        if (isset($input['primary_hover'])) {
            $out['primary_hover'] = sanitize_hex_color($input['primary_hover']) ?: '';
        }
        if (isset($input['logo_url'])) {
            $out['logo_url'] = esc_url_raw($input['logo_url']);
        }
        if (isset($input['banner_url'])) {
            $out['banner_url'] = esc_url_raw($input['banner_url']);
        }
        if (isset($input['nav_style'])) {
            $out['nav_style'] = in_array($input['nav_style'], array('light', 'dark'), true) ? $input['nav_style'] : 'light';
        }
        return $out;
    }

    /**
     * Inject CSS variables on front.
     */
    public static function output_css()
    {
        $b = self::get();
        $rules = array();
        if ($b['primary'] !== '') {
            $rules[] = '--primary: ' . $b['primary'] . ';';
            $rules[] = '--mjb-primary: ' . $b['primary'] . ';';
        }
        if ($b['primary_hover'] !== '') {
            $rules[] = '--primary-hover: ' . $b['primary_hover'] . ';';
        }
        if (empty($rules) && $b['banner_url'] === '' && $b['logo_url'] === '') {
            return;
        }
        echo '<style id="mjb-brand-css">';
        if (!empty($rules)) {
            echo ':root{' . esc_html(implode('', $rules)) . '}';
        }
        if ($b['banner_url'] !== '') {
            echo '.mjb-content-hero{background-image:url(' . esc_url($b['banner_url']) . ');background-size:cover;background-position:center;}';
        }
        if ($b['nav_style'] === 'dark') {
            echo '.site-header,.navbar{background:#0f172a;color:#f8fafc;}';
        }
        echo '</style>' . "\n";
    }

    /**
     * Admin submenu.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Brand', 'modern-job-board'),
            __('Brand', 'modern-job-board'),
            'manage_options',
            'mjb-brand',
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
        $b = self::get();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Board brand', 'modern-job-board'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('mjb_brand_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Primary color', 'modern-job-board'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr(self::OPTION); ?>[primary]" value="<?php echo esc_attr($b['primary']); ?>" class="regular-text" placeholder="#0f766e"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Primary hover', 'modern-job-board'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr(self::OPTION); ?>[primary_hover]" value="<?php echo esc_attr($b['primary_hover']); ?>" class="regular-text" placeholder="#0d9488"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Logo URL', 'modern-job-board'); ?></th>
                        <td><input type="url" name="<?php echo esc_attr(self::OPTION); ?>[logo_url]" value="<?php echo esc_attr($b['logo_url']); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Banner image URL', 'modern-job-board'); ?></th>
                        <td><input type="url" name="<?php echo esc_attr(self::OPTION); ?>[banner_url]" value="<?php echo esc_attr($b['banner_url']); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Nav style', 'modern-job-board'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION); ?>[nav_style]">
                                <option value="light" <?php selected($b['nav_style'], 'light'); ?>><?php esc_html_e('Light', 'modern-job-board'); ?></option>
                                <option value="dark" <?php selected($b['nav_style'], 'dark'); ?>><?php esc_html_e('Dark', 'modern-job-board'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
