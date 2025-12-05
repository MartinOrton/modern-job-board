<?php
/**
 * Modern Job Board Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin
{

    /**
     * Initialize Admin.
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add Admin Menu.
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Settings', 'modern-job-board'),
            __('Settings', 'modern-job-board'),
            'manage_options',
            'mjb-settings',
            array($this, 'settings_page_html')
        );
    }

    /**
     * Register Settings.
     */
    public function register_settings()
    {
        register_setting('mjb_settings_group', 'mjb_stripe_publishable_key');
        register_setting('mjb_settings_group', 'mjb_stripe_secret_key');
        register_setting('mjb_settings_group', 'mjb_currency', array('default' => 'USD'));

        add_settings_section(
            'mjb_payment_section',
            __('Payment Settings', 'modern-job-board'),
            null,
            'mjb-settings'
        );

        add_settings_field(
            'mjb_stripe_publishable_key',
            __('Stripe Publishable Key', 'modern-job-board'),
            array($this, 'stripe_pk_callback'),
            'mjb-settings',
            'mjb_payment_section'
        );

        add_settings_field(
            'mjb_stripe_secret_key',
            __('Stripe Secret Key', 'modern-job-board'),
            array($this, 'stripe_sk_callback'),
            'mjb-settings',
            'mjb_payment_section'
        );
    }

    /**
     * Settings Page HTML.
     */
    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mjb_settings_group');
                do_settings_sections('mjb-settings');
                submit_button(__('Save Settings', 'modern-job-board'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Callbacks.
     */
    public function stripe_pk_callback()
    {
        $value = get_option('mjb_stripe_publishable_key');
        echo '<input type="text" name="mjb_stripe_publishable_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function stripe_sk_callback()
    {
        $value = get_option('mjb_stripe_secret_key');
        echo '<input type="password" name="mjb_stripe_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
    }
}
