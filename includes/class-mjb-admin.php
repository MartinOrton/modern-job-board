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

        // Enqueue Admin Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue Admin Assets.
     */
    public function enqueue_admin_assets()
    {
        $screen = get_current_screen();
        
        // Only load on MJB pages
        if (
            strpos($screen->id, 'modern-job-board') !== false ||
            strpos($screen->id, 'job_listing') !== false ||
            strpos($screen->id, 'job_application') !== false ||
            strpos($screen->id, 'company') !== false ||
            strpos($screen->id, 'mjb_resume') !== false
        ) {
            wp_enqueue_style(
                'mjb-shared',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/mjb-shared.css',
                array(),
                MJB_VERSION
            );
            wp_enqueue_style(
                'mjb-admin-css',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/mjb-admin.css',
                array('mjb-shared'),
                MJB_VERSION
            );
        }
    }

    /**
     * Add Admin Menu.
     */
    /**
     * Add Admin Menu.
     */
    public function add_admin_menu()
    {
        // Main Menu Item
        add_menu_page(
            __('Modern Job Board', 'modern-job-board'),
            __('Modern Job Board', 'modern-job-board'),
            'manage_options',
            'modern-job-board',
            array($this, 'admin_dashboard_html'),
            'dashicons-businessman',
            56
        );

        // Dashboard Submenu (Default)
        add_submenu_page(
            'modern-job-board',
            __('Dashboard', 'modern-job-board'),
            __('Dashboard', 'modern-job-board'),
            'manage_options',
            'modern-job-board',
            array($this, 'admin_dashboard_html')
        );

        // Settings Submenu
        add_submenu_page(
            'modern-job-board',
            __('Settings', 'modern-job-board'),
            __('Settings', 'modern-job-board'),
            'manage_options',
            'mjb-settings',
            array($this, 'settings_page_html')
        );
    }

    /**
     * Admin Dashboard HTML.
     */
    public function admin_dashboard_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Basic Stats
        $job_count = wp_count_posts('job_listing')->publish;
        $app_count = wp_count_posts('job_application')->publish;
        $company_count = wp_count_posts('company')->publish;
        $resume_count = wp_count_posts('mjb_resume')->publish;
        $performance = MJB_Analytics::summarize_job_stats(MJB_Analytics::get_admin_job_stats());
        $top_jobs = MJB_Analytics::get_top_jobs_for_charts(5);
        $pending_webhooks = MJB_Webhook_Queue::get_pending_count();

        ?>
        <div class="wrap mjb-dashboard-page">
            <div class="mjb-dashboard-wrap">
                <header class="mjb-dashboard-header">
                    <h1><?php esc_html_e('Modern Job Board', 'modern-job-board'); ?> <span class="mjb-badge">v<?php echo esc_html(MJB_VERSION); ?></span></h1>
                    <p class="subtitle"><?php esc_html_e('Manage your job board with complete control.', 'modern-job-board'); ?></p>
                </header>

                <div class="mjb-stats-grid">
                    <div class="mjb-stat-card">
                        <div class="mjb-stat-val"><?php echo esc_html((string) intval($job_count)); ?></div>
                        <div class="mjb-stat-lbl"><?php esc_html_e('Active Jobs', 'modern-job-board'); ?></div>
                    </div>
                    <div class="mjb-stat-card">
                        <div class="mjb-stat-val"><?php echo esc_html((string) intval($app_count)); ?></div>
                        <div class="mjb-stat-lbl"><?php esc_html_e('Applications', 'modern-job-board'); ?></div>
                    </div>
                    <div class="mjb-stat-card">
                        <div class="mjb-stat-val"><?php echo esc_html((string) intval($performance['views'])); ?></div>
                        <div class="mjb-stat-lbl"><?php esc_html_e('Job Views', 'modern-job-board'); ?></div>
                    </div>
                    <div class="mjb-stat-card">
                        <div class="mjb-stat-val"><?php echo esc_html($performance['conversion_rate'] . '%'); ?></div>
                        <div class="mjb-stat-lbl"><?php esc_html_e('Conversion', 'modern-job-board'); ?></div>
                    </div>
                    <div class="mjb-stat-card">
                        <div class="mjb-stat-val"><?php echo esc_html((string) intval($company_count)); ?></div>
                        <div class="mjb-stat-lbl"><?php esc_html_e('Companies', 'modern-job-board'); ?></div>
                    </div>
                    <div class="mjb-stat-card">
                        <div class="mjb-stat-val"><?php echo esc_html((string) intval($resume_count)); ?></div>
                        <div class="mjb-stat-lbl"><?php esc_html_e('Resumes', 'modern-job-board'); ?></div>
                    </div>
                </div>

                <h2 class="mjb-section-title"><?php esc_html_e('Performance Charts', 'modern-job-board'); ?></h2>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in MJB_Analytics::render_admin_charts_html().
                echo MJB_Analytics::render_admin_charts_html($top_jobs);
                ?>

                <?php if ($pending_webhooks > 0) : ?>
                    <div class="notice notice-warning mjb-notice-spaced">
                        <p><?php echo esc_html(sprintf(
                            _n('%d webhook delivery is queued for retry.', '%d webhook deliveries are queued for retry.', $pending_webhooks, 'modern-job-board'),
                            $pending_webhooks
                        )); ?></p>
                    </div>
                <?php endif; ?>

                <h2 class="mjb-section-title"><?php esc_html_e('Quick Actions', 'modern-job-board'); ?></h2>

                <div class="mjb-features-grid">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing')); ?>" class="mjb-feature-card">
                        <div class="mjb-feature-icon">
                            <span class="dashicons dashicons-businessman"></span>
                        </div>
                        <h3><?php esc_html_e('Manage Jobs', 'modern-job-board'); ?></h3>
                        <p><?php esc_html_e('View, edit, and moderate job listings. Manage expiration dates and featured status.', 'modern-job-board'); ?></p>
                    </a>

                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_application')); ?>" class="mjb-feature-card">
                        <div class="mjb-feature-icon">
                            <span class="dashicons dashicons-email"></span>
                        </div>
                        <h3><?php esc_html_e('Applications', 'modern-job-board'); ?></h3>
                        <p><?php esc_html_e('Review candidate applications and download resumes.', 'modern-job-board'); ?></p>
                    </a>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=mjb-settings')); ?>" class="mjb-feature-card">
                        <div class="mjb-feature-icon">
                            <span class="dashicons dashicons-admin-settings"></span>
                        </div>
                        <h3><?php esc_html_e('Settings', 'modern-job-board'); ?></h3>
                        <p><?php esc_html_e('Configure listings, Google Maps API, and monetization options.', 'modern-job-board'); ?></p>
                    </a>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=mjb-setup')); ?>" class="mjb-feature-card">
                        <div class="mjb-feature-icon">
                            <span class="dashicons dashicons-admin-page"></span>
                        </div>
                        <h3><?php esc_html_e('Setup', 'modern-job-board'); ?></h3>
                        <p><?php esc_html_e('Create frontend pages for job search, dashboards, and registration shortcodes.', 'modern-job-board'); ?></p>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register Settings.
     */
    public function register_settings()
    {
        register_setting('mjb_settings_group', 'mjb_currency', array('default' => 'USD'));
        register_setting('mjb_settings_group', 'mjb_listing_duration', array('default' => 30));
        register_setting('mjb_settings_group', 'mjb_google_maps_api_key');

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
        
        // Payment Settings (WooCommerce only)
        // Re-using Payment Section but purely for Logic toggle?
        add_settings_section(
            'mjb_payment_section',
            __('Monetization Settings', 'modern-job-board'),
            null,
            'mjb-settings'
        );

        register_setting('mjb_settings_group', 'mjb_payment_required');
        add_settings_field('mjb_payment_required', __('Require Payment', 'modern-job-board'), array($this, 'payment_required_callback'), 'mjb-settings', 'mjb_payment_section');

        register_setting('mjb_settings_group', 'mjb_submission_product_id');
        add_settings_field('mjb_submission_product_id', __('Submission Product ID', 'modern-job-board'), array($this, 'submission_product_id_callback'), 'mjb-settings', 'mjb_payment_section');

        // CV Unlock settings (if they existed, keeping consistent)
        register_setting('mjb_settings_group', 'mjb_cv_unlock_product_id');
        add_settings_field('mjb_cv_unlock_product_id', __('CV Unlock Product ID', 'modern-job-board'), array($this, 'cv_unlock_product_id_callback'), 'mjb-settings', 'mjb_payment_section');

        register_setting('mjb_settings_group', 'mjb_paid_cv_access');
        add_settings_field('mjb_paid_cv_access', __('Paid CV Access', 'modern-job-board'), array($this, 'paid_cv_access_callback'), 'mjb-settings', 'mjb_payment_section');

        add_settings_section(
            'mjb_integrations_section',
            __('Integrations', 'modern-job-board'),
            array($this, 'integrations_section_callback'),
            'mjb-settings'
        );

        register_setting('mjb_settings_group', 'mjb_webhook_urls');
        add_settings_field(
            'mjb_webhook_urls',
            __('Webhook URLs', 'modern-job-board'),
            array($this, 'webhook_urls_callback'),
            'mjb-settings',
            'mjb_integrations_section'
        );

        register_setting('mjb_settings_group', 'mjb_webhook_secret');
        add_settings_field(
            'mjb_webhook_secret',
            __('Webhook Secret', 'modern-job-board'),
            array($this, 'webhook_secret_callback'),
            'mjb-settings',
            'mjb_integrations_section'
        );

        add_settings_section(
            'mjb_security_section',
            __('Application Security', 'modern-job-board'),
            array($this, 'security_section_callback'),
            'mjb-settings'
        );

        register_setting('mjb_settings_group', 'mjb_recaptcha_enabled');
        add_settings_field('mjb_recaptcha_enabled', __('Enable reCAPTCHA', 'modern-job-board'), array($this, 'recaptcha_enabled_callback'), 'mjb-settings', 'mjb_security_section');

        register_setting('mjb_settings_group', 'mjb_recaptcha_site_key');
        add_settings_field('mjb_recaptcha_site_key', __('reCAPTCHA Site Key', 'modern-job-board'), array($this, 'recaptcha_site_key_callback'), 'mjb-settings', 'mjb_security_section');

        register_setting('mjb_settings_group', 'mjb_recaptcha_secret_key');
        add_settings_field('mjb_recaptcha_secret_key', __('reCAPTCHA Secret Key', 'modern-job-board'), array($this, 'recaptcha_secret_key_callback'), 'mjb-settings', 'mjb_security_section');
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
    public function listing_duration_callback()
    {
        $value = get_option('mjb_listing_duration', 30);
        echo '<input type="number" name="mjb_listing_duration" value="' . esc_attr($value) . '" class="small-text"> ' . esc_html__('days', 'modern-job-board');
    }

    public function google_maps_api_key_callback()
    {
        $api_key = get_option('mjb_google_maps_api_key');
        echo '<input type="text" name="mjb_google_maps_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    }

    public function payment_required_callback()
    {
        $required = get_option('mjb_payment_required');
        echo '<input type="checkbox" name="mjb_payment_required" value="1" ' . checked(1, $required, false) . '> ' . esc_html__('Enable Pay-Per-Post', 'modern-job-board');
    }

    public function submission_product_id_callback()
    {
        $id = get_option('mjb_submission_product_id');
        echo '<input type="number" name="mjb_submission_product_id" value="' . esc_attr($id) . '" class="small-text">';
        echo '<p class="description">' . esc_html__('Enter the WooCommerce Product ID for the job listing fee.', 'modern-job-board') . '</p>';
    }

    public function cv_unlock_product_id_callback()
    {
        $id = get_option('mjb_cv_unlock_product_id');
        echo '<input type="number" name="mjb_cv_unlock_product_id" value="' . esc_attr($id) . '" class="small-text">';
        echo '<p class="description">' . esc_html__('Enter the WooCommerce Product ID for unlocking a single application.', 'modern-job-board') . '</p>';
    }

    public function paid_cv_access_callback()
    {
        $enabled = get_option('mjb_paid_cv_access');
        echo '<input type="checkbox" name="mjb_paid_cv_access" value="1" ' . checked(1, $enabled, false) . '> ';
        echo esc_html__('Require payment before employers can view candidate details and resumes.', 'modern-job-board');
    }

    public function integrations_section_callback()
    {
        echo '<p>' . esc_html__('Send JSON webhook payloads when applications are submitted, statuses change, or jobs are submitted. One URL per line.', 'modern-job-board') . '</p>';
    }

    public function webhook_urls_callback()
    {
        $value = get_option('mjb_webhook_urls', '');
        echo '<textarea name="mjb_webhook_urls" rows="4" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Events: application.submitted, application.status_updated, job.submitted', 'modern-job-board') . '</p>';
        $pending = MJB_Webhook_Queue::get_pending_count();
        if ($pending > 0) {
            echo '<p class="description">' . esc_html(sprintf(
                _n('%d delivery is currently queued for retry.', '%d deliveries are currently queued for retry.', $pending, 'modern-job-board'),
                $pending
            )) . '</p>';
        }
    }

    public function webhook_secret_callback()
    {
        $value = get_option('mjb_webhook_secret', '');
        echo '<input type="password" name="mjb_webhook_secret" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off">';
        echo '<p class="description">' . esc_html__('Optional HMAC secret sent as the X-MJB-Signature header (SHA-256).', 'modern-job-board') . '</p>';
    }

    public function security_section_callback()
    {
        echo '<p>' . esc_html__('Protect the job application form from automated spam. A honeypot field is always active; reCAPTCHA v2 is optional.', 'modern-job-board') . '</p>';
    }

    public function recaptcha_enabled_callback()
    {
        $enabled = get_option('mjb_recaptcha_enabled');
        echo '<input type="checkbox" name="mjb_recaptcha_enabled" value="1" ' . checked(1, $enabled, false) . '> ';
        echo esc_html__('Require Google reCAPTCHA v2 on internal application forms.', 'modern-job-board');
    }

    public function recaptcha_site_key_callback()
    {
        $value = get_option('mjb_recaptcha_site_key', '');
        echo '<input type="text" name="mjb_recaptcha_site_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function recaptcha_secret_key_callback()
    {
        $value = get_option('mjb_recaptcha_secret_key', '');
        echo '<input type="password" name="mjb_recaptcha_secret_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off">';
        echo '<p class="description">' . esc_html__('Create keys at google.com/recaptcha/admin (reCAPTCHA v2, "I\'m not a robot" checkbox).', 'modern-job-board') . '</p>';
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
                echo esc_html(get_post_meta($post_id, '_candidate_name', true));
                break;
            case 'job_applied_for':
                $job_id = get_post_meta($post_id, '_job_applied_for', true);
                if ($job_id) {
                    echo '<a href="' . esc_url(get_edit_post_link($job_id)) . '">' . esc_html(get_the_title($job_id)) . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'resume':
                $resume_url = MJB_Resumes::get_application_download_url($post_id);
                if ($resume_url) {
                    echo '<a href="' . esc_url($resume_url) . '" target="_blank">' . esc_html__('Download Resume', 'modern-job-board') . '</a>';
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
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($expires)));
                } else {
                    echo '-';
                }
                break;
            case 'job_featured':
                $featured = get_post_meta($post_id, '_featured', true);
                if ($featured) {
                    echo '<span class="dashicons dashicons-star-filled mjb-star-filled"></span>';
                } else {
                    echo '<span class="dashicons dashicons-star-empty mjb-star-empty"></span>';
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
            <label for="mjb_job_expires"><?php esc_html_e('Expiration Date:', 'modern-job-board'); ?></label>
            <input type="date" name="mjb_job_expires" id="mjb_job_expires" value="<?php echo esc_attr($expires); ?>"
                class="mjb-input-full">
        </p>
        <p>
            <label>
                <input type="checkbox" name="mjb_featured" id="mjb_featured" value="1" <?php checked($featured, 1); ?>>
                <?php esc_html_e('Featured Job', 'modern-job-board'); ?>
            </label>
        </p>
        <?php
        $method = get_post_meta($post->ID, '_application_method', true);
        $app_email = get_post_meta($post->ID, '_application_email', true);
        $app_url = get_post_meta($post->ID, '_application_url', true);
        ?>
        <p><strong><?php esc_html_e('Application Method', 'modern-job-board'); ?></strong></p>
        <p>
            <label>
                <input type="radio" name="mjb_application_method" value="internal" <?php checked($method, 'internal'); ?>         <?php checked($method, ''); ?>>
                <?php esc_html_e('Internal (Email)', 'modern-job-board'); ?>
            </label><br>
            <label>
                <input type="radio" name="mjb_application_method" value="external" <?php checked($method, 'external'); ?>>
                <?php esc_html_e('External URL', 'modern-job-board'); ?>
            </label>
        </p>
        <p>
            <label for="mjb_application_email"><?php esc_html_e('Notification Email', 'modern-job-board'); ?></label><br>
            <input type="email" name="mjb_application_email" id="mjb_application_email"
                value="<?php echo esc_attr($app_email); ?>" class="widefat">
        </p>
        <p>
            <label for="mjb_application_url"><?php esc_html_e('External URL', 'modern-job-board'); ?></label><br>
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
