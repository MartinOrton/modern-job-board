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
        MJB_Admin_Tabs::init();
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

        // Jobs add/edit: Settings-style chrome (classic editor + MJB shell).
        add_filter('use_block_editor_for_post_type', array($this, 'disable_block_editor_for_jobs'), 10, 2);
        add_filter('admin_body_class', array($this, 'job_editor_body_class'));
        add_action('all_admin_notices', array($this, 'job_editor_chrome_open'), 5);
        add_action('admin_footer-post.php', array($this, 'job_editor_chrome_close'));
        add_action('admin_footer-post-new.php', array($this, 'job_editor_chrome_close'));
        // Keep main-column meta boxes out of the side column (2-col screen + order arrows).
        add_action('add_meta_boxes_job_listing', array($this, 'relocate_job_main_meta_boxes'), 99);
        add_filter('get_user_option_meta-box-order_job_listing', array($this, 'force_job_main_column_meta_boxes'));
    }

    /**
     * Meta box IDs that must stay in the main (normal) column on job_listing screens.
     *
     * @return string[]
     */
    private function get_job_main_column_meta_box_ids()
    {
        return array(
            'mjb_job_data',
            'job_locationdiv',
            'postimagediv',
            'postcustom',
            'mjb_job_media',
        );
    }

    /**
     * Register main-column meta boxes in `normal` context (not side).
     *
     * @return void
     */
    public function relocate_job_main_meta_boxes()
    {
        // Featured image: default is side → normal.
        remove_meta_box('postimagediv', 'job_listing', 'side');
        remove_meta_box('postimagediv', 'job_listing', 'normal');
        add_meta_box(
            'postimagediv',
            esc_html__('Featured image', 'modern-job-board'),
            'post_thumbnail_meta_box',
            'job_listing',
            'normal',
            'low'
        );

        // Locations taxonomy: default hierarchical placement is side → normal.
        remove_meta_box('job_locationdiv', 'job_listing', 'side');
        remove_meta_box('job_locationdiv', 'job_listing', 'normal');
        if (taxonomy_exists('job_location')) {
            $tax = get_taxonomy('job_location');
            add_meta_box(
                'job_locationdiv',
                $tax ? $tax->labels->name : __('Locations', 'modern-job-board'),
                'post_categories_meta_box',
                'job_listing',
                'normal',
                'default',
                array('taxonomy' => 'job_location')
            );
        }

        // Custom Fields: keep in normal (re-register if WP put it elsewhere).
        remove_meta_box('postcustom', 'job_listing', 'side');
        remove_meta_box('postcustom', 'job_listing', 'normal');
        add_meta_box(
            'postcustom',
            __('Custom Fields'),
            'post_custom_meta_box',
            'job_listing',
            'normal',
            'low'
        );

        // Listing details + Job media are registered in normal already; strip any side copies.
        remove_meta_box('mjb_job_data', 'job_listing', 'side');
        remove_meta_box('mjb_job_media', 'job_listing', 'side');
    }

    /**
     * If a user previously ordered locked boxes into the side column, pull them back.
     *
     * @param mixed $order
     * @return mixed
     */
    public function force_job_main_column_meta_boxes($order)
    {
        if (!is_array($order)) {
            return $order;
        }

        $locked = $this->get_job_main_column_meta_box_ids();
        $moved = array();

        foreach (array('side', 'advanced') as $context) {
            if (empty($order[$context]) || !is_string($order[$context])) {
                continue;
            }
            $ids = array_filter(array_map('trim', explode(',', $order[$context])));
            $keep = array();
            foreach ($ids as $id) {
                if (in_array($id, $locked, true)) {
                    $moved[] = $id;
                } else {
                    $keep[] = $id;
                }
            }
            $order[$context] = implode(',', $keep);
        }

        if (empty($moved)) {
            return $order;
        }

        $normal = array();
        if (!empty($order['normal']) && is_string($order['normal'])) {
            $normal = array_filter(array_map('trim', explode(',', $order['normal'])));
        }
        foreach ($moved as $id) {
            if (!in_array($id, $normal, true)) {
                $normal[] = $id;
            }
        }
        $order['normal'] = implode(',', $normal);

        return $order;
    }

    /**
     * Whether the current screen is the job_listing classic editor.
     *
     * @return bool
     */
    private function is_job_editor_screen()
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'job_listing') {
            return false;
        }

        return in_array($screen->base, array('post', 'post-new'), true);
    }

    /**
     * Keep job add/edit on classic editor so we can match Settings UI chrome.
     *
     * @param bool   $use
     * @param string $post_type
     * @return bool
     */
    public function disable_block_editor_for_jobs($use, $post_type)
    {
        if ($post_type === 'job_listing') {
            return false;
        }

        return $use;
    }

    /**
     * Body class for job editor styling.
     *
     * @param string $classes
     * @return string
     */
    public function job_editor_body_class($classes)
    {
        if ($this->is_job_editor_screen()) {
            $classes .= ' mjb-job-editor-screen';
        }

        return $classes;
    }

    /**
     * Open MJB shell around the WP job editor (matches Settings look).
     *
     * @return void
     */
    public function job_editor_chrome_open()
    {
        if (!$this->is_job_editor_screen()) {
            return;
        }

        $is_new = (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'post-new.php');
        $heading = $is_new
            ? __('Add New Job', 'modern-job-board')
            : __('Edit Job', 'modern-job-board');
        ?>
        <div class="mjb-dashboard-page mjb-admin-page mjb-job-editor">
            <div class="mjb-dashboard-wrap mjb-admin-shell">
                <header class="mjb-dashboard-header">
                    <h1><?php esc_html_e('Modern Job Board', 'modern-job-board'); ?> <span class="mjb-badge">v<?php echo esc_html(MJB_VERSION); ?></span></h1>
                    <p class="subtitle"><?php esc_html_e('Manage your job board settings, listings, and tools.', 'modern-job-board'); ?></p>
                </header>
                <?php MJB_Admin_Tabs::render_tab_nav('jobs', true); ?>
                <div class="mjb-admin-panel mjb-job-editor-panel">
                    <div class="mjb-tab-panel mjb-tab-panel--job-editor">
                        <div class="mjb-tab-panel__header">
                            <h2 class="mjb-section-title"><?php echo esc_html($heading); ?></h2>
                            <div class="mjb-tab-panel__actions">
                                <a href="<?php echo esc_url(MJB_Admin_Tabs::get_tab_url('jobs')); ?>" class="mjb-btn mjb-btn-outline">
                                    <?php esc_html_e('Back to Jobs', 'modern-job-board'); ?>
                                </a>
                            </div>
                        </div>
        <?php
    }

    /**
     * Close MJB shell opened around the job editor.
     *
     * @return void
     */
    public function job_editor_chrome_close()
    {
        if (!$this->is_job_editor_screen()) {
            return;
        }

        echo '</div></div></div></div>';

        // Keep locked main-column boxes out of #side-sortables when reordered with arrows/drag.
        $locked = wp_json_encode($this->get_job_main_column_meta_box_ids());
        ?>
        <script>
        jQuery(function ($) {
            var locked = <?php echo $locked; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode. ?>;
            if (!locked || !locked.length || !$.fn.sortable) {
                return;
            }

            function isLocked($item) {
                var id = $item.attr('id') || '';
                return locked.indexOf(id) !== -1;
            }

            function returnToNormal($item) {
                var $normal = $('#normal-sortables');
                if ($normal.length) {
                    $normal.append($item);
                }
            }

            $('#side-sortables').on('sortreceive', function (event, ui) {
                if (ui && ui.item && isLocked(ui.item)) {
                    returnToNormal(ui.item);
                    if (typeof postboxes !== 'undefined' && postboxes.save_order) {
                        postboxes.save_order(pagenow);
                    }
                }
            });
        });
        </script>
        <?php
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
            $base_url = plugin_dir_url(dirname(__FILE__));

            wp_enqueue_style(
                'mjb-admin-fonts',
                'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap',
                array(),
                null
            );

            wp_enqueue_style(
                'mjb-shared',
                $base_url . 'assets/css/mjb-shared.css',
                array(),
                MJB_VERSION
            );
            wp_enqueue_style(
                'mjb-admin-css',
                $base_url . 'assets/css/mjb-admin.css',
                array('mjb-shared', 'mjb-admin-fonts'),
                MJB_VERSION
            );
            wp_enqueue_style(
                'mjb-charts-css',
                $base_url . 'assets/css/mjb-charts.css',
                array('mjb-admin-css'),
                MJB_VERSION
            );
        }

        if ($screen && $screen->id === 'toplevel_page_modern-job-board') {
            wp_enqueue_script(
                'mjb-admin-tabs',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/mjb-admin-tabs.js',
                array('jquery'),
                MJB_VERSION,
                true
            );
            wp_localize_script('mjb-admin-tabs', 'mjb_admin_tabs', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mjb_admin_tabs'),
                'default_tab' => MJB_Admin_Tabs::get_default_tab(),
            ));
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
            'dashicons-portfolio',
            56
        );

        add_submenu_page(
            'modern-job-board',
            __('Dashboard', 'modern-job-board'),
            __('Dashboard', 'modern-job-board'),
            'manage_options',
            'modern-job-board',
            array($this, 'admin_dashboard_html')
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

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $active_tab = MJB_Admin_Tabs::sanitize_tab(sanitize_key(wp_unslash($_GET['tab'] ?? MJB_Admin_Tabs::get_default_tab())));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $active_page = max(1, intval($_GET['mjb_page'] ?? 1));

        ?>
        <div class="wrap mjb-dashboard-page mjb-admin-page">
            <div class="mjb-dashboard-wrap mjb-admin-shell">
                <header class="mjb-dashboard-header">
                    <h1><?php esc_html_e('Modern Job Board', 'modern-job-board'); ?> <span class="mjb-badge">v<?php echo esc_html(MJB_VERSION); ?></span></h1>
                    <p class="subtitle"><?php esc_html_e('Manage your job board settings, listings, and tools.', 'modern-job-board'); ?></p>
                </header>

                <?php MJB_Admin_Tabs::render_tab_nav($active_tab); ?>

                <div
                    id="mjb-admin-panel"
                    class="mjb-admin-panel"
                    role="tabpanel"
                    aria-labelledby="mjb-tab-<?php echo esc_attr($active_tab); ?>"
                    data-active-tab="<?php echo esc_attr($active_tab); ?>"
                    data-active-page="<?php echo esc_attr((string) $active_page); ?>"
                >
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tab HTML is escaped by renderers.
                    echo MJB_Admin_Tabs::render_tab($active_tab, $active_page);
                    ?>
                </div>

                <div id="mjb-admin-loader" class="mjb-admin-loader" aria-hidden="true" role="status">
                    <span class="mjb-spinner" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e('Loading section…', 'modern-job-board'); ?></span>
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
        register_setting('mjb_settings_group', 'mjb_currency', array(
            'type' => 'string',
            'default' => 'USD',
            'sanitize_callback' => array($this, 'sanitize_currency'),
        ));
        register_setting('mjb_settings_group', 'mjb_listing_duration', array(
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => array($this, 'sanitize_listing_duration'),
        ));
        register_setting('mjb_settings_group', 'mjb_google_maps_api_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('mjb_settings_group', 'mjb_license_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_license_key'),
        ));
        register_setting('mjb_settings_group', MJB_License_Commerce::OPTION_URL_PRO, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array('MJB_License_Commerce', 'sanitize_purchase_url'),
        ));
        register_setting('mjb_settings_group', MJB_License_Commerce::OPTION_URL_BUSINESS, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array('MJB_License_Commerce', 'sanitize_purchase_url'),
        ));
        register_setting('mjb_settings_group', MJB_License_Commerce::OPTION_URL_COMPLETE, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array('MJB_License_Commerce', 'sanitize_purchase_url'),
        ));
        register_setting('mjb_settings_group', MJB_License_Commerce::OPTION_SALES_EMAIL, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_sales_email'),
        ));

        add_settings_section(
            'mjb_license_section',
            __('License & plan', 'modern-job-board'),
            array($this, 'license_section_callback'),
            'mjb-settings'
        );
        add_settings_field(
            'mjb_license_key',
            __('License key', 'modern-job-board'),
            array($this, 'license_key_callback'),
            'mjb-settings',
            'mjb_license_section'
        );
        add_settings_field(
            'mjb_license_plan_display',
            __('Current plan', 'modern-job-board'),
            array($this, 'license_plan_display_callback'),
            'mjb-settings',
            'mjb_license_section'
        );
        add_settings_field(
            'mjb_purchase_url_pro',
            __('Pro checkout URL', 'modern-job-board'),
            array($this, 'purchase_url_pro_callback'),
            'mjb-settings',
            'mjb_license_section'
        );
        add_settings_field(
            'mjb_purchase_url_business',
            __('Business checkout URL', 'modern-job-board'),
            array($this, 'purchase_url_business_callback'),
            'mjb-settings',
            'mjb_license_section'
        );
        add_settings_field(
            'mjb_purchase_url_complete',
            __('Complete Site checkout URL', 'modern-job-board'),
            array($this, 'purchase_url_complete_callback'),
            'mjb-settings',
            'mjb_license_section'
        );
        add_settings_field(
            'mjb_sales_email',
            __('Sales email (mailto fallback)', 'modern-job-board'),
            array($this, 'sales_email_callback'),
            'mjb-settings',
            'mjb_license_section'
        );

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

        add_settings_section(
            'mjb_payment_section',
            __('Monetization Settings', 'modern-job-board'),
            null,
            'mjb-settings'
        );

        register_setting('mjb_settings_group', 'mjb_payment_required', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        add_settings_field('mjb_payment_required', __('Require Payment', 'modern-job-board'), array($this, 'payment_required_callback'), 'mjb-settings', 'mjb_payment_section');

        register_setting('mjb_settings_group', 'mjb_submission_product_id', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => array($this, 'sanitize_product_id'),
        ));
        add_settings_field('mjb_submission_product_id', __('Submission Product ID', 'modern-job-board'), array($this, 'submission_product_id_callback'), 'mjb-settings', 'mjb_payment_section');

        register_setting('mjb_settings_group', 'mjb_cv_unlock_product_id', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => array($this, 'sanitize_product_id'),
        ));
        add_settings_field('mjb_cv_unlock_product_id', __('CV Unlock Product ID', 'modern-job-board'), array($this, 'cv_unlock_product_id_callback'), 'mjb-settings', 'mjb_payment_section');

        register_setting('mjb_settings_group', 'mjb_paid_cv_access', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        add_settings_field('mjb_paid_cv_access', __('Paid CV Access', 'modern-job-board'), array($this, 'paid_cv_access_callback'), 'mjb-settings', 'mjb_payment_section');

        add_settings_section(
            'mjb_integrations_section',
            __('Integrations', 'modern-job-board'),
            array($this, 'integrations_section_callback'),
            'mjb-settings'
        );

        register_setting('mjb_settings_group', 'mjb_webhook_urls', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_webhook_urls'),
        ));
        add_settings_field(
            'mjb_webhook_urls',
            __('Webhook URLs', 'modern-job-board'),
            array($this, 'webhook_urls_callback'),
            'mjb-settings',
            'mjb_integrations_section'
        );

        register_setting('mjb_settings_group', 'mjb_webhook_secret', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
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

        register_setting('mjb_settings_group', 'mjb_recaptcha_enabled', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        add_settings_field('mjb_recaptcha_enabled', __('Enable reCAPTCHA', 'modern-job-board'), array($this, 'recaptcha_enabled_callback'), 'mjb-settings', 'mjb_security_section');

        register_setting('mjb_settings_group', 'mjb_recaptcha_site_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_recaptcha_site_key'),
        ));
        add_settings_field('mjb_recaptcha_site_key', __('reCAPTCHA Site Key', 'modern-job-board'), array($this, 'recaptcha_site_key_callback'), 'mjb-settings', 'mjb_security_section');

        register_setting('mjb_settings_group', 'mjb_recaptcha_secret_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_recaptcha_secret_key'),
        ));
        add_settings_field('mjb_recaptcha_secret_key', __('reCAPTCHA Secret Key', 'modern-job-board'), array($this, 'recaptcha_secret_key_callback'), 'mjb-settings', 'mjb_security_section');

        add_settings_section(
            'mjb_registration_section',
            __('Registration', 'modern-job-board'),
            array($this, 'registration_section_callback'),
            'mjb-settings'
        );

        register_setting('mjb_settings_group', 'mjb_require_employer_approval', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        add_settings_field(
            'mjb_require_employer_approval',
            __('Recruiter approval', 'modern-job-board'),
            array($this, 'require_employer_approval_callback'),
            'mjb-settings',
            'mjb_registration_section'
        );

        register_setting('mjb_settings_group', 'mjb_require_candidate_approval', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        add_settings_field(
            'mjb_require_candidate_approval',
            __('Candidate approval', 'modern-job-board'),
            array($this, 'require_candidate_approval_callback'),
            'mjb-settings',
            'mjb_registration_section'
        );
    }

    /**
     * Sanitize checkbox option (0/1).
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_checkbox($value)
    {
        return empty($value) ? 0 : 1;
    }

    /**
     * Activate or clear offline license key; always returns stored key.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize_license_key($value)
    {
        $result = MJB_License::activate_key(is_string($value) ? $value : '');
        if (is_wp_error($result)) {
            add_settings_error(
                'mjb_settings_group',
                'mjb_license_key',
                $result->get_error_message(),
                'error'
            );
            return MJB_License::get_key();
        }

        $key = MJB_License::get_key();
        if ($key === '') {
            add_settings_error(
                'mjb_settings_group',
                'mjb_license_cleared',
                __('License cleared. You are on the Free plan.', 'modern-job-board'),
                'updated'
            );
        } else {
            add_settings_error(
                'mjb_settings_group',
                'mjb_license_activated',
                sprintf(
                    /* translators: %s: plan label */
                    __('License activated. Current plan: %s.', 'modern-job-board'),
                    MJB_License::get_plan_label()
                ),
                'updated'
            );
        }

        return $key;
    }

    /**
     * License settings section intro.
     */
    public function license_section_callback()
    {
        echo '<div id="mjb-license"></div>';
        echo '<p>' . esc_html__('Enter a license key to unlock Pro or Business features. Leave blank for Free (10 active jobs).', 'modern-job-board') . '</p>';
        echo '<p class="description">' . esc_html__('Checkout URLs power “Buy Pro/Business” buttons. Use any https product or payment page. With WooCommerce, any gateway that supports WooCommerce works (e.g. PayPal, Stripe, Square, Mollie, Razorpay, or regional gateways where available). Leave blank to fall back to a sales email.', 'modern-job-board') . '</p>';
        if (defined('MJB_LICENSE_PLAN') && MJB_License::is_valid_plan(MJB_LICENSE_PLAN)) {
            echo '<p class="description"><strong>' . esc_html__('Note:', 'modern-job-board') . '</strong> ';
            echo esc_html(sprintf(
                /* translators: %s: plan label */
                __('Plan is forced to %s via MJB_LICENSE_PLAN in wp-config.php.', 'modern-job-board'),
                MJB_License::get_plan_label(MJB_LICENSE_PLAN)
            ));
            echo '</p>';
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function sanitize_sales_email($value)
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $email = sanitize_email($value);
        return is_email($email) ? $email : '';
    }

    public function purchase_url_pro_callback()
    {
        $value = get_option(MJB_License_Commerce::OPTION_URL_PRO, '');
        echo '<input type="url" name="' . esc_attr(MJB_License_Commerce::OPTION_URL_PRO) . '" id="mjb_purchase_url_pro" value="' . esc_attr($value) . '" class="large-text" placeholder="https://…">';
        echo '<p class="description">' . esc_html__('https product or payment page for Pro (e.g. WooCommerce product URL). Payment is handled by whatever WooCommerce gateway you configure.', 'modern-job-board') . '</p>';
    }

    public function purchase_url_business_callback()
    {
        $value = get_option(MJB_License_Commerce::OPTION_URL_BUSINESS, '');
        echo '<input type="url" name="' . esc_attr(MJB_License_Commerce::OPTION_URL_BUSINESS) . '" id="mjb_purchase_url_business" value="' . esc_attr($value) . '" class="large-text" placeholder="https://…">';
    }

    public function purchase_url_complete_callback()
    {
        $value = get_option(MJB_License_Commerce::OPTION_URL_COMPLETE, '');
        echo '<input type="url" name="' . esc_attr(MJB_License_Commerce::OPTION_URL_COMPLETE) . '" id="mjb_purchase_url_complete" value="' . esc_attr($value) . '" class="large-text" placeholder="https://…">';
    }

    public function sales_email_callback()
    {
        $value = get_option(MJB_License_Commerce::OPTION_SALES_EMAIL, '');
        echo '<input type="email" name="' . esc_attr(MJB_License_Commerce::OPTION_SALES_EMAIL) . '" id="mjb_sales_email" value="' . esc_attr($value) . '" class="regular-text" placeholder="hello@martinorton.com">';
        echo '<p class="description">' . esc_html__('Used when a checkout URL is empty (mailto fallback). Defaults to the site admin email.', 'modern-job-board') . '</p>';
    }

    /**
     * License key input.
     */
    public function license_key_callback()
    {
        $key = MJB_License::get_key();
        echo '<input type="text" name="mjb_license_key" id="mjb_license_key" value="' . esc_attr($key) . '" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="MJB-PRO-00000000-XXXXXXXX">';
        echo '<p class="description">' . esc_html__('Format: MJB-{PLAN}-{YYYYMMDD|00000000}-{checksum}. Clear the field and save to revert to Free.', 'modern-job-board') . '</p>';
    }

    /**
     * Read-only plan summary.
     */
    public function license_plan_display_callback()
    {
        $plan = MJB_License::get_plan();
        $label = MJB_License::get_plan_label($plan);
        echo '<p><strong>' . esc_html($label) . '</strong></p>';

        if (!MJB_License::can('unlimited_jobs')) {
            $count = MJB_License::count_active_jobs();
            $limit = MJB_License::get_free_job_limit();
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: 1: published job count, 2: free plan limit */
                __('Active published jobs: %1$d / %2$d. Upgrade to Pro for unlimited listings.', 'modern-job-board'),
                $count,
                $limit
            )) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__('Unlimited published job listings.', 'modern-job-board') . '</p>';
        }

        $features = array(
            'woocommerce'   => __('WooCommerce monetization', 'modern-job-board'),
            'custom_fields' => __('Custom fields', 'modern-job-board'),
            'tools'         => __('Import / export tools', 'modern-job-board'),
            'rest_api'      => __('REST API', 'modern-job-board'),
            'xml_feed'      => __('XML job feed', 'modern-job-board'),
            'webhooks'      => __('Webhooks', 'modern-job-board'),
        );
        echo '<ul class="ul-disc" style="margin-left:1.2em">';
        foreach ($features as $slug => $name) {
            $ok = MJB_License::can($slug);
            echo '<li>' . esc_html($name) . ': ';
            echo $ok
                ? '<span style="color:#008a20">' . esc_html__('Enabled', 'modern-job-board') . '</span>'
                : '<span style="color:#b32d2e">' . esc_html__('Locked', 'modern-job-board') . '</span>';
            echo '</li>';
        }
        echo '</ul>';

        if (!MJB_License::is_pro()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- purchase_cta_html is escaped.
            echo MJB_License_Commerce::purchase_cta_html(MJB_License::PLAN_PRO);
        } elseif (!MJB_License::is_business()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- purchase_cta_html is escaped.
            echo MJB_License_Commerce::purchase_cta_html(MJB_License::PLAN_BUSINESS);
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- form is escaped.
        echo MJB_License_Commerce::render_generate_key_form();
    }

    /**
     * Sanitize listing duration days.
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_listing_duration($value)
    {
        $value = absint($value);
        if ($value < 1) {
            add_settings_error(
                'mjb_settings_group',
                'mjb_listing_duration_min',
                __('Listing duration must be at least 1 day.', 'modern-job-board'),
                'error'
            );
            return (int) get_option('mjb_listing_duration', 30);
        }
        if ($value > 3650) {
            add_settings_error(
                'mjb_settings_group',
                'mjb_listing_duration_max',
                __('Listing duration cannot exceed 3650 days.', 'modern-job-board'),
                'error'
            );
            return 3650;
        }
        return $value;
    }

    /**
     * Sanitize currency code.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize_currency($value)
    {
        $value = strtoupper(sanitize_text_field((string) $value));
        if ($value === '' || !preg_match('/^[A-Z]{3}$/', $value)) {
            add_settings_error(
                'mjb_settings_group',
                'mjb_currency_invalid',
                __('Currency must be a 3-letter ISO code (e.g. USD).', 'modern-job-board'),
                'error'
            );
            return (string) get_option('mjb_currency', 'USD');
        }
        return $value;
    }

    /**
     * Sanitize WooCommerce product ID.
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_product_id($value)
    {
        $value = absint($value);
        if ($value < 0) {
            return 0;
        }
        return $value;
    }

    /**
     * Sanitize webhook URL list (one valid URL per line).
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize_webhook_urls($value)
    {
        $raw = is_string($value) ? $value : '';
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $valid = array();
        $invalid = 0;

        foreach ((array) $lines as $line) {
            $line = trim(sanitize_text_field($line));
            if ($line === '') {
                continue;
            }
            if (wp_http_validate_url($line)) {
                $valid[] = esc_url_raw($line);
            } else {
                $invalid++;
            }
        }

        if ($invalid > 0) {
            add_settings_error(
                'mjb_settings_group',
                'mjb_webhook_urls_invalid',
                sprintf(
                    /* translators: %d: number of invalid webhook URLs */
                    _n(
                        '%d webhook URL was invalid and was removed. Use full http(s) URLs, one per line.',
                        '%d webhook URLs were invalid and were removed. Use full http(s) URLs, one per line.',
                        $invalid,
                        'modern-job-board'
                    ),
                    $invalid
                ),
                'error'
            );
        }

        return implode("\n", $valid);
    }

    /**
     * Sanitize reCAPTCHA site key; require when enabled.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize_recaptcha_site_key($value)
    {
        $value = sanitize_text_field((string) $value);
        $enabled = !empty($_POST['mjb_recaptcha_enabled']); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Settings API nonce already verified.
        if ($enabled && $value === '') {
            add_settings_error(
                'mjb_settings_group',
                'mjb_recaptcha_site_key_required',
                __('reCAPTCHA Site Key is required when reCAPTCHA is enabled.', 'modern-job-board'),
                'error'
            );
            return (string) get_option('mjb_recaptcha_site_key', '');
        }
        return $value;
    }

    /**
     * Sanitize reCAPTCHA secret key; require when enabled.
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize_recaptcha_secret_key($value)
    {
        $value = sanitize_text_field((string) $value);
        $enabled = !empty($_POST['mjb_recaptcha_enabled']); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Settings API nonce already verified.
        if ($enabled && $value === '') {
            add_settings_error(
                'mjb_settings_group',
                'mjb_recaptcha_secret_key_required',
                __('reCAPTCHA Secret Key is required when reCAPTCHA is enabled.', 'modern-job-board'),
                'error'
            );
            return (string) get_option('mjb_recaptcha_secret_key', '');
        }
        return $value;
    }

    /**
     * Callbacks.
     */
    public function listing_duration_callback()
    {
        $value = get_option('mjb_listing_duration', 30);
        echo '<input type="number" name="mjb_listing_duration" id="mjb_listing_duration" value="' . esc_attr($value) . '" class="small-text" min="1" max="3650" step="1" required> ';
        echo esc_html__('days', 'modern-job-board');
        echo '<p class="description">' . esc_html__('How long new job listings stay active (1–3650 days).', 'modern-job-board') . '</p>';
    }

    public function google_maps_api_key_callback()
    {
        $api_key = get_option('mjb_google_maps_api_key');
        echo '<input type="text" name="mjb_google_maps_api_key" id="mjb_google_maps_api_key" value="' . esc_attr($api_key) . '" class="regular-text" autocomplete="off">';
        echo '<p class="description">' . esc_html__('Optional. Used for map embeds on job pages.', 'modern-job-board') . '</p>';
    }

    public function payment_required_callback()
    {
        if (!MJB_License::can('woocommerce')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- upgrade_notice_html is escaped.
            echo MJB_License::upgrade_notice_html('woocommerce');
            return;
        }

        $required = get_option('mjb_payment_required');
        echo '<input type="hidden" name="mjb_payment_required" value="0">';
        echo '<label><input type="checkbox" name="mjb_payment_required" value="1" ' . checked(1, $required, false) . '> ';
        echo esc_html__('Enable Pay-Per-Post', 'modern-job-board') . '</label>';
    }

    public function submission_product_id_callback()
    {
        $id = get_option('mjb_submission_product_id');
        echo '<input type="number" name="mjb_submission_product_id" id="mjb_submission_product_id" value="' . esc_attr($id) . '" class="small-text" min="0" step="1">';
        echo '<p class="description">' . esc_html__('WooCommerce Product ID for the job listing fee (required when Pay-Per-Post is enabled).', 'modern-job-board') . '</p>';
    }

    public function cv_unlock_product_id_callback()
    {
        $id = get_option('mjb_cv_unlock_product_id');
        echo '<input type="number" name="mjb_cv_unlock_product_id" id="mjb_cv_unlock_product_id" value="' . esc_attr($id) . '" class="small-text" min="0" step="1">';
        echo '<p class="description">' . esc_html__('WooCommerce Product ID for unlocking a single application.', 'modern-job-board') . '</p>';
    }

    public function paid_cv_access_callback()
    {
        $enabled = get_option('mjb_paid_cv_access');
        echo '<input type="hidden" name="mjb_paid_cv_access" value="0">';
        echo '<label><input type="checkbox" name="mjb_paid_cv_access" value="1" ' . checked(1, $enabled, false) . '> ';
        echo esc_html__('Require payment before recruiters can view candidate details and resumes.', 'modern-job-board') . '</label>';
    }

    public function integrations_section_callback()
    {
        echo '<p>' . esc_html__('Send JSON webhook payloads when applications are submitted, statuses change, or jobs are submitted. One URL per line.', 'modern-job-board') . '</p>';
        if (!MJB_License::can('webhooks')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- upgrade_notice_html is escaped.
            echo MJB_License::upgrade_notice_html('webhooks');
        }
    }

    public function webhook_urls_callback()
    {
        if (!MJB_License::can('webhooks')) {
            echo '<p class="description">' . esc_html__('Webhooks require the Business plan.', 'modern-job-board') . '</p>';
            return;
        }

        $value = get_option('mjb_webhook_urls', '');
        echo '<textarea name="mjb_webhook_urls" id="mjb_webhook_urls" rows="4" class="large-text code" placeholder="https://example.com/hooks/mjb">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Full https:// URLs only. Events: application.submitted, application.status_updated, job.submitted', 'modern-job-board') . '</p>';
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
        if (!MJB_License::can('webhooks')) {
            echo '<p class="description">' . esc_html__('—', 'modern-job-board') . '</p>';
            return;
        }

        $value = get_option('mjb_webhook_secret', '');
        echo '<input type="password" name="mjb_webhook_secret" id="mjb_webhook_secret" value="' . esc_attr($value) . '" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">' . esc_html__('Optional HMAC secret sent as the X-MJB-Signature header (SHA-256).', 'modern-job-board') . '</p>';
    }

    public function security_section_callback()
    {
        echo '<p>' . esc_html__('Protect the job application form from automated spam. A honeypot field is always active; reCAPTCHA v2 is optional.', 'modern-job-board') . '</p>';
    }

    public function registration_section_callback()
    {
        echo '<p>' . esc_html__('Control recruiter and job seeker self-registration. Pending accounts cannot log in until approved (Users → Approve board account).', 'modern-job-board') . '</p>';
    }

    public function require_employer_approval_callback()
    {
        $enabled = get_option('mjb_require_employer_approval', 0);
        echo '<input type="hidden" name="mjb_require_employer_approval" value="0">';
        echo '<label><input type="checkbox" name="mjb_require_employer_approval" value="1" ' . checked(1, $enabled, false) . '> ';
        echo esc_html__('Require admin approval for new recruiter accounts', 'modern-job-board') . '</label>';
    }

    public function require_candidate_approval_callback()
    {
        $enabled = get_option('mjb_require_candidate_approval', 0);
        echo '<input type="hidden" name="mjb_require_candidate_approval" value="0">';
        echo '<label><input type="checkbox" name="mjb_require_candidate_approval" value="1" ' . checked(1, $enabled, false) . '> ';
        echo esc_html__('Require admin approval for new candidate accounts', 'modern-job-board') . '</label>';
    }

    public function recaptcha_enabled_callback()
    {
        $enabled = get_option('mjb_recaptcha_enabled');
        echo '<input type="hidden" name="mjb_recaptcha_enabled" value="0">';
        echo '<label><input type="checkbox" name="mjb_recaptcha_enabled" id="mjb_recaptcha_enabled" value="1" ' . checked(1, $enabled, false) . '> ';
        echo esc_html__('Require Google reCAPTCHA v2 on internal application forms.', 'modern-job-board') . '</label>';
    }

    public function recaptcha_site_key_callback()
    {
        $value = get_option('mjb_recaptcha_site_key', '');
        echo '<input type="text" name="mjb_recaptcha_site_key" id="mjb_recaptcha_site_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off">';
        echo '<p class="description">' . esc_html__('Required when reCAPTCHA is enabled.', 'modern-job-board') . '</p>';
    }

    public function recaptcha_secret_key_callback()
    {
        $value = get_option('mjb_recaptcha_secret_key', '');
        echo '<input type="password" name="mjb_recaptcha_secret_key" id="mjb_recaptcha_secret_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">' . esc_html__('Create keys at google.com/recaptcha/admin (reCAPTCHA v2, "I\'m not a robot" checkbox). Required when reCAPTCHA is enabled.', 'modern-job-board') . '</p>';
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
            __('Listing details', 'modern-job-board'),
            array($this, 'render_job_meta_box'),
            'job_listing',
            'normal',
            'high'
        );
    }

    /**
     * Render Job Meta Box (Settings-style form table).
     */
    public function render_job_meta_box($post)
    {
        wp_nonce_field('mjb_save_job_data', 'mjb_job_data_nonce');
        $expires = get_post_meta($post->ID, '_job_expires', true);
        $featured = get_post_meta($post->ID, '_featured', true);
        $method = get_post_meta($post->ID, '_application_method', true);
        $app_email = get_post_meta($post->ID, '_application_email', true);
        $app_url = get_post_meta($post->ID, '_application_url', true);
        $whatsapp = get_post_meta($post->ID, '_application_whatsapp', true);
        $filled = get_post_meta($post->ID, '_job_filled', true);
        $publish_at = get_post_meta($post->ID, '_job_publish_at', true);
        if ($method === '') {
            $method = 'internal';
        }
        $publish_at_local = $publish_at ? str_replace(' ', 'T', substr($publish_at, 0, 16)) : '';
        ?>
        <div class="mjb-job-meta mjb-settings-form">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mjb_job_expires"><?php esc_html_e('Expiration date', 'modern-job-board'); ?></label></th>
                    <td>
                        <input type="date" name="mjb_job_expires" id="mjb_job_expires" value="<?php echo esc_attr($expires); ?>">
                        <p class="description"><?php esc_html_e('Leave empty to use the default listing duration from Settings.', 'modern-job-board'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mjb_job_publish_at"><?php esc_html_e('Schedule go-live', 'modern-job-board'); ?></label></th>
                    <td>
                        <input type="datetime-local" name="mjb_job_publish_at" id="mjb_job_publish_at" value="<?php echo esc_attr($publish_at_local); ?>">
                        <p class="description"><?php esc_html_e('Optional. When set on a draft/pending job, MJB publishes it after this date/time.', 'modern-job-board'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Featured', 'modern-job-board'); ?></th>
                    <td>
                        <label for="mjb_featured">
                            <input type="checkbox" name="mjb_featured" id="mjb_featured" value="1" <?php checked($featured, 1); ?>>
                            <?php esc_html_e('Feature this job on the board', 'modern-job-board'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Filled', 'modern-job-board'); ?></th>
                    <td>
                        <label for="mjb_job_filled">
                            <input type="checkbox" name="mjb_job_filled" id="mjb_job_filled" value="1" <?php checked($filled, '1'); ?>>
                            <?php esc_html_e('Mark as filled (can be hidden from public lists)', 'modern-job-board'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Application method', 'modern-job-board'); ?></th>
                    <td>
                        <label class="mjb-job-meta__radio">
                            <input type="radio" name="mjb_application_method" value="internal" <?php checked($method, 'internal'); ?>>
                            <?php esc_html_e('Internal (email notification)', 'modern-job-board'); ?>
                        </label>
                        <label class="mjb-job-meta__radio">
                            <input type="radio" name="mjb_application_method" value="external" <?php checked($method, 'external'); ?>>
                            <?php esc_html_e('External URL', 'modern-job-board'); ?>
                        </label>
                        <label class="mjb-job-meta__radio">
                            <input type="radio" name="mjb_application_method" value="whatsapp" <?php checked($method, 'whatsapp'); ?>>
                            <?php esc_html_e('WhatsApp', 'modern-job-board'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mjb_application_email"><?php esc_html_e('Notification email(s)', 'modern-job-board'); ?></label></th>
                    <td>
                        <input type="text" name="mjb_application_email" id="mjb_application_email" value="<?php echo esc_attr($app_email); ?>" class="regular-text" placeholder="hr@example.com, hiring@example.com">
                        <p class="description"><?php esc_html_e('Comma-separated list for internal application notices.', 'modern-job-board'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mjb_application_url"><?php esc_html_e('External application URL', 'modern-job-board'); ?></label></th>
                    <td>
                        <input type="url" name="mjb_application_url" id="mjb_application_url" value="<?php echo esc_attr($app_url); ?>" class="regular-text" placeholder="https://">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mjb_application_whatsapp"><?php esc_html_e('WhatsApp number', 'modern-job-board'); ?></label></th>
                    <td>
                        <input type="text" name="mjb_application_whatsapp" id="mjb_application_whatsapp" value="<?php echo esc_attr($whatsapp); ?>" class="regular-text" placeholder="+27123456789">
                        <p class="description"><?php esc_html_e('International format; used when application method is WhatsApp.', 'modern-job-board'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
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
            update_post_meta($post_id, '_job_expires', sanitize_text_field(wp_unslash($_POST['mjb_job_expires'])));
        }

        // Schedule go-live
        if (isset($_POST['mjb_job_publish_at'])) {
            $raw = sanitize_text_field(wp_unslash($_POST['mjb_job_publish_at']));
            if ($raw === '') {
                delete_post_meta($post_id, '_job_publish_at');
            } else {
                $raw = str_replace('T', ' ', $raw);
                if (strlen($raw) === 16) {
                    $raw .= ':00';
                }
                update_post_meta($post_id, '_job_publish_at', $raw);
            }
        }

        // Save Featured
        $featured = isset($_POST['mjb_featured']) ? 1 : 0;
        update_post_meta($post_id, '_featured', $featured);

        // Filled
        $filled = isset($_POST['mjb_job_filled']) ? '1' : '0';
        update_post_meta($post_id, '_job_filled', $filled);

        // Save Application Method
        if (isset($_POST['mjb_application_method'])) {
            $method = sanitize_key(wp_unslash($_POST['mjb_application_method']));
            if (!in_array($method, array('internal', 'external', 'whatsapp'), true)) {
                $method = 'internal';
            }
            update_post_meta($post_id, '_application_method', $method);
        }
        if (isset($_POST['mjb_application_email'])) {
            $emails_raw = sanitize_text_field(wp_unslash($_POST['mjb_application_email']));
            if (class_exists('MJB_Job_Ops')) {
                $emails = MJB_Job_Ops::parse_emails($emails_raw);
                update_post_meta($post_id, '_application_email', implode(', ', $emails));
            } else {
                update_post_meta($post_id, '_application_email', sanitize_email($emails_raw));
            }
        }
        if (isset($_POST['mjb_application_url'])) {
            update_post_meta($post_id, '_application_url', esc_url_raw(wp_unslash($_POST['mjb_application_url'])));
        }
        if (isset($_POST['mjb_application_whatsapp'])) {
            update_post_meta($post_id, '_application_whatsapp', sanitize_text_field(wp_unslash($_POST['mjb_application_whatsapp'])));
        }
    }
}
