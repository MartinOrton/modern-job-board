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
        // Custom Columns for Applications
        add_filter('manage_job_application_posts_columns', array($this, 'add_application_columns'));
        add_action('manage_job_application_posts_custom_column', array($this, 'render_application_columns'), 10, 2);

        // Custom Columns for Jobs
        add_filter('manage_job_listing_posts_columns', array($this, 'add_job_columns'));
        add_action('manage_job_listing_posts_custom_column', array($this, 'render_job_columns'), 10, 2);

        // Meta Box for Job Data
        add_action('add_meta_boxes', array($this, 'add_job_meta_boxes'));
        add_action('save_post', array($this, 'save_job_meta_data'));
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
        register_setting('mjb_settings_group', 'mjb_listing_duration', array('default' => 30));
        register_setting('mjb_settings_group', 'mjb_google_maps_api_key');

        // Payment Settings Section
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

        // Listing Settings Section
        add_settings_section(
            'mjb_listing_section',
            __('Listing Settings', 'modern-job-board'),
            null,
            'mjb-settings'
        );

        add_settings_field(
            'mjb_listing_duration',
            __('Listing Duration (Days)', 'modern-job-board'),
            array($this, 'listing_duration_callback'),
            'mjb-settings',
            'mjb_listing_section'
        );

        add_settings_field(
            'mjb_google_maps_api_key',
            __('Google Maps API Key', 'modern-job-board'),
            array($this, 'google_maps_api_key_callback'),
            'mjb-settings',
            'mjb_listing_section'
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

    public function listing_duration_callback()
    {
        $value = get_option('mjb_listing_duration', 30);
        echo '<input type="number" name="mjb_listing_duration" value="' . esc_attr($value) . '" class="small-text"> ' . __('days', 'modern-job-board');
    }

    public function google_maps_api_key_callback()
    {
        $value = get_option('mjb_google_maps_api_key');
        echo '<input type="text" name="mjb_google_maps_api_key" value="' . esc_attr($value) . '" class="regular-text">';
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


    /**
     * Add columns to Job Listing.
     */
    public function add_job_columns($columns)
    {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['job_expires'] = __('Expires', 'modern-job-board');
        $new_columns['job_featured'] = '<span class="dashicons dashicons-star-filled" title="' . __('Featured', 'modern-job-board') . '"></span>';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Render Job columns.
     */
    public function render_job_columns($column, $post_id)
    {
        switch ($column) {
            case 'job_expires':
                $expires = get_post_meta($post_id, '_job_expires', true);
                if ($expires) {
                    echo date_i18n(get_option('date_format'), strtotime($expires));
                } else {
                    echo '-';
                }
                break;
            case 'job_featured':
                $featured = get_post_meta($post_id, '_featured', true);
                if ($featured) {
                    echo '<span class="dashicons dashicons-star-filled" style="color:#f0ad4e;"></span>';
                } else {
                    echo '<span class="dashicons dashicons-star-empty" style="color:#ccc;"></span>';
                }
                break;
        }
    }

    /**
     * Add Job Meta Boxes.
     */
    public function add_job_meta_boxes()
    {
        add_meta_box(
            'mjb_job_data',
            __('Job Data', 'modern-job-board'),
            array($this, 'render_job_meta_box'),
            'job_listing',
            'side',
            'high'
        );
    }

    /**
     * Render Job Meta Box.
     */
    public function render_job_meta_box($post)
    {
        wp_nonce_field('mjb_save_job_data', 'mjb_job_data_nonce');
        $expires = get_post_meta($post->ID, '_job_expires', true);
        $featured = get_post_meta($post->ID, '_featured', true);
        ?>
        <p>
            <label for="mjb_job_expires"><?php _e('Expiration Date:', 'modern-job-board'); ?></label>
            <input type="date" name="mjb_job_expires" id="mjb_job_expires" value="<?php echo esc_attr($expires); ?>"
                style="width:100%;">
        </p>
        <p>
            <label>
                <input type="checkbox" name="mjb_featured" id="mjb_featured" value="1" <?php checked($featured, 1); ?>>
                <?php _e('Featured Job', 'modern-job-board'); ?>
            </label>
        </p>
        <?php
        $method = get_post_meta($post->ID, '_application_method', true);
        $app_email = get_post_meta($post->ID, '_application_email', true);
        $app_url = get_post_meta($post->ID, '_application_url', true);
        ?>
        <p><strong><?php _e('Application Method', 'modern-job-board'); ?></strong></p>
        <p>
            <label>
                <input type="radio" name="mjb_application_method" value="internal" <?php checked($method, 'internal'); ?>         <?php checked($method, ''); ?>>
                <?php _e('Internal (Email)', 'modern-job-board'); ?>
            </label><br>
            <label>
                <input type="radio" name="mjb_application_method" value="external" <?php checked($method, 'external'); ?>>
                <?php _e('External URL', 'modern-job-board'); ?>
            </label>
        </p>
        <p>
            <label for="mjb_application_email"><?php _e('Notification Email', 'modern-job-board'); ?></label><br>
            <input type="email" name="mjb_application_email" id="mjb_application_email"
                value="<?php echo esc_attr($app_email); ?>" class="widefat">
        </p>
        <p>
            <label for="mjb_application_url"><?php _e('External URL', 'modern-job-board'); ?></label><br>
            <input type="url" name="mjb_application_url" id="mjb_application_url" value="<?php echo esc_attr($app_url); ?>"
                class="widefat">
        </p>
        <?php
    }

    /**
     * Save Job Meta Data.
     */
    public function save_job_meta_data($post_id)
    {
        if (!isset($_POST['mjb_job_data_nonce']) || !wp_verify_nonce($_POST['mjb_job_data_nonce'], 'mjb_save_job_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save Expiration
        if (isset($_POST['mjb_job_expires'])) {
            update_post_meta($post_id, '_job_expires', sanitize_text_field($_POST['mjb_job_expires']));
        }

        // Save Featured
        $featured = isset($_POST['mjb_featured']) ? 1 : 0;
        update_post_meta($post_id, '_featured', $featured);

        // Save Application Method
        if (isset($_POST['mjb_application_method'])) {
            update_post_meta($post_id, '_application_method', sanitize_text_field($_POST['mjb_application_method']));
        }
        if (isset($_POST['mjb_application_email'])) {
            update_post_meta($post_id, '_application_email', sanitize_email($_POST['mjb_application_email']));
        }
        if (isset($_POST['mjb_application_url'])) {
            update_post_meta($post_id, '_application_url', esc_url_raw($_POST['mjb_application_url']));
        }
    }
}
