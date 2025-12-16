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

        // Custom Columns for Applications
        add_filter('manage_job_application_posts_columns', array($this, 'add_application_columns'));
        add_action('manage_job_application_posts_custom_column', array($this, 'render_application_columns'), 10, 2);
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

    /**
     * Add columns to Job Application list.
     */
    public function add_application_columns($columns)
    {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Application', 'modern-job-board');
        $new_columns['candidate_name'] = __('Candidate Name', 'modern-job-board');
        $new_columns['job_applied_for'] = __('Applied For', 'modern-job-board');
        $new_columns['resume'] = __('Resume', 'modern-job-board');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Render custom columns.
     */
    public function render_application_columns($column, $post_id)
    {
        switch ($column) {
            case 'candidate_name':
                echo get_post_meta($post_id, '_candidate_name', true);
                break;
            case 'job_applied_for':
                $job_id = get_post_meta($post_id, '_job_applied_for', true);
                if ($job_id) {
                    echo '<a href="' . get_edit_post_link($job_id) . '">' . get_the_title($job_id) . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'resume':
                $resume_url = get_post_meta($post_id, '_candidate_resume', true);
                if ($resume_url) {
                    echo '<a href="' . esc_url($resume_url) . '" target="_blank">' . __('Download Resume', 'modern-job-board') . '</a>';
                } else {
                    echo '-';
                }
                break;
        }
    }
}
