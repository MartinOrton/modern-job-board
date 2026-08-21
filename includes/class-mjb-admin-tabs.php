<?php
/**
 * Modern Job Board admin tab shell and AJAX loaders.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Tabs
{
    const PER_PAGE = 10;

    /**
     * Initialize tab hooks.
     */
    public static function init()
    {
        add_action('wp_ajax_mjb_admin_load_tab', array(__CLASS__, 'ajax_load_tab'));
        add_action('wp_ajax_mjb_admin_delete_job', array(__CLASS__, 'ajax_delete_job'));
        add_action('admin_menu', array(__CLASS__, 'reorder_menu'), 999);
        add_action('admin_init', array(__CLASS__, 'redirect_legacy_pages'));
        add_action('admin_head', array(__CLASS__, 'render_menu_icon_css'));
        add_filter('parent_file', array(__CLASS__, 'filter_parent_file'));
        add_filter('submenu_file', array(__CLASS__, 'filter_submenu_file'));
    }

    /**
     * Map MJB post types to tab ids.
     *
     * @return array<string, string>
     */
    public static function get_post_type_tab_map()
    {
        return array(
            'job_listing' => 'jobs',
            'job_application' => 'applications',
            'company' => 'companies',
            'mjb_resume' => 'resumes',
        );
    }

    /**
     * Build a tabbed admin shell URL.
     *
     * @param string $tab_id
     * @param array  $args
     * @return string
     */
    public static function get_tab_url($tab_id, $args = array())
    {
        $query = array_merge(
            array(
                'page' => 'modern-job-board',
                'tab' => self::sanitize_tab($tab_id),
            ),
            $args
        );

        return add_query_arg($query, admin_url('admin.php'));
    }

    /**
     * Submenu slug used by WordPress for a tab.
     *
     * @param string $tab_id
     * @return string
     */
    public static function get_tab_menu_slug($tab_id)
    {
        $tab_id = self::sanitize_tab($tab_id);

        if ($tab_id === self::get_default_tab()) {
            return 'modern-job-board';
        }

        return 'admin.php?page=modern-job-board&tab=' . $tab_id;
    }

    /**
     * Registered admin tabs.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_tabs()
    {
        $tabs = array(
            'dashboard' => array(
                'label' => __('Dashboard', 'modern-job-board'),
                'icon' => 'layout-grid',
                'paged' => false,
            ),
            'jobs' => array(
                'label' => __('Jobs', 'modern-job-board'),
                'icon' => 'briefcase',
                'paged' => true,
            ),
            'applications' => array(
                'label' => __('Applications', 'modern-job-board'),
                'icon' => 'inbox',
                'paged' => true,
            ),
            'companies' => array(
                'label' => __('Companies', 'modern-job-board'),
                'icon' => 'building',
                'paged' => true,
            ),
            'resumes' => array(
                'label' => __('Resumes', 'modern-job-board'),
                'icon' => 'file-text',
                'paged' => true,
            ),
            'settings' => array(
                'label' => __('Settings', 'modern-job-board'),
                'icon' => 'settings',
                'paged' => false,
            ),
            'setup' => array(
                'label' => __('Setup', 'modern-job-board'),
                'icon' => 'wand-sparkles',
                'paged' => false,
            ),
            'custom-fields' => array(
                'label' => __('Custom Fields', 'modern-job-board'),
                'icon' => 'list-plus',
                'paged' => false,
            ),
            'tools' => array(
                'label' => __('Tools', 'modern-job-board'),
                'icon' => 'wrench',
                'paged' => false,
            ),
        );

        return apply_filters('mjb_admin_tabs', $tabs);
    }

    /**
     * Whether a tab id is valid.
     *
     * @param string $tab_id
     * @return bool
     */
    public static function is_valid_tab($tab_id)
    {
        $tabs = self::get_tabs();

        return isset($tabs[$tab_id]);
    }

    /**
     * Default tab id.
     *
     * @return string
     */
    public static function get_default_tab()
    {
        return 'dashboard';
    }

    /**
     * Sanitize requested tab id.
     *
     * @param string $tab_id
     * @return string
     */
    public static function sanitize_tab($tab_id)
    {
        $tab_id = sanitize_key($tab_id);

        if (!self::is_valid_tab($tab_id)) {
            return self::get_default_tab();
        }

        return $tab_id;
    }

    /**
     * Render tab navigation.
     *
     * @param string $active_tab
     * @param bool   $as_links When true, tabs navigate via full page links (job editor screens).
     * @return void
     */
    public static function render_tab_nav($active_tab, $as_links = false)
    {
        $active_tab = self::sanitize_tab($active_tab);
        echo '<nav class="mjb-admin-tabs" aria-label="' . esc_attr__('Modern Job Board sections', 'modern-job-board') . '">';
        echo '<ul class="mjb-admin-tabs__list" role="tablist">';

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Tab icon SVG escaped in MJB_Icons::render(); other args escaped.
        foreach (self::get_tabs() as $tab_id => $tab) {
            $is_active = $tab_id === $active_tab;
            $icon = MJB_Icons::render($tab['icon'], 18);
            $label = esc_html($tab['label']);
            $active_class = $is_active ? ' is-active' : '';

            if ($as_links) {
                printf(
                    '<li class="mjb-admin-tabs__item" role="presentation"><a href="%1$s" class="mjb-admin-tabs__btn%2$s" role="tab" id="mjb-tab-%3$s" aria-selected="%4$s">%5$s<span>%6$s</span></a></li>',
                    esc_url(self::get_tab_url($tab_id)),
                    $active_class,
                    esc_attr($tab_id),
                    $is_active ? 'true' : 'false',
                    $icon,
                    $label
                );
            } else {
                printf(
                    '<li class="mjb-admin-tabs__item" role="presentation"><button type="button" class="mjb-admin-tabs__btn%1$s" role="tab" id="mjb-tab-%2$s" data-tab="%2$s" aria-selected="%3$s" aria-controls="mjb-admin-panel">%4$s<span>%5$s</span></button></li>',
                    $active_class,
                    esc_attr($tab_id),
                    $is_active ? 'true' : 'false',
                    $icon,
                    $label
                );
            }
        }
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</ul></nav>';
    }

    /**
     * Render tab panel content.
     *
     * @param string $tab_id
     * @param int    $page
     * @return string
     */
    public static function render_tab($tab_id, $page = 1)
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $tab_id = self::sanitize_tab($tab_id);
        $page = max(1, intval($page));

        ob_start();

        switch ($tab_id) {
            case 'dashboard':
                self::render_dashboard_tab();
                break;
            case 'jobs':
                self::render_post_type_tab('job_listing', $page);
                break;
            case 'applications':
                self::render_applications_tab($page);
                break;
            case 'companies':
                self::render_post_type_tab('company', $page);
                break;
            case 'resumes':
                self::render_post_type_tab('mjb_resume', $page);
                break;
            case 'settings':
                $settings_tab = isset($_REQUEST['settings_tab']) ? sanitize_key(wp_unslash($_REQUEST['settings_tab'])) : '';
                self::render_settings_tab($settings_tab);
                break;
            case 'setup':
                MJB_Page_Wizard::render_setup_content();
                break;
            case 'custom-fields':
                if (!MJB_License::can('custom_fields')) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- upgrade_notice_html is escaped.
                    echo MJB_License::upgrade_notice_html('custom_fields');
                    break;
                }
                global $mjb_custom_fields;
                if ($mjb_custom_fields instanceof MJB_Custom_Fields) {
                    $mjb_custom_fields->render_admin_content();
                }
                break;
            case 'tools':
                if (!MJB_License::can('tools')) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- upgrade_notice_html is escaped.
                    echo MJB_License::upgrade_notice_html('tools');
                    break;
                }
                global $mjb_tools;
                if ($mjb_tools instanceof MJB_Tools) {
                    $tools_tab = isset($_REQUEST['tools_tab']) ? sanitize_key(wp_unslash($_REQUEST['tools_tab'])) : 'export';
                    if (!in_array($tools_tab, array('export', 'import', 'schedules'), true)) {
                        $tools_tab = 'export';
                    }
                    $mjb_tools->render_tools_content($tools_tab);
                }
                break;
        }

        return (string) ob_get_clean();
    }

    /**
     * AJAX tab loader.
     *
     * @return void
     */
    public static function ajax_load_tab()
    {
        check_ajax_referer('mjb_admin_tabs', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }

        $tab_id = self::sanitize_tab(sanitize_key(wp_unslash($_POST['tab'] ?? self::get_default_tab())));
        $page = max(1, intval($_POST['page'] ?? 1));

        if ($tab_id === 'tools' && isset($_POST['tools_tab'])) {
            $_REQUEST['tools_tab'] = sanitize_key(wp_unslash($_POST['tools_tab']));
        }

        if ($tab_id === 'settings' && isset($_POST['settings_tab'])) {
            $_REQUEST['settings_tab'] = sanitize_key(wp_unslash($_POST['settings_tab']));
        }

        wp_send_json_success(array(
            'tab' => $tab_id,
            'page' => $page,
            'html' => self::render_tab($tab_id, $page),
        ));
    }

    /**
     * AJAX: trash a job listing from the Jobs tab.
     *
     * @return void
     */
    public static function ajax_delete_job()
    {
        check_ajax_referer('mjb_admin_tabs', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }

        $job_id = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;
        if ($job_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid job.', 'modern-job-board')), 400);
        }

        $post = get_post($job_id);
        if (!$post || $post->post_type !== 'job_listing') {
            wp_send_json_error(array('message' => __('Job not found.', 'modern-job-board')), 404);
        }

        if (!current_user_can('delete_post', $job_id)) {
            wp_send_json_error(array('message' => __('You cannot delete this job.', 'modern-job-board')), 403);
        }

        // Prefer trash (recoverable); fall back to permanent delete if trash is unavailable.
        $trashed = wp_trash_post($job_id);
        if (!$trashed) {
            $deleted = wp_delete_post($job_id, true);
            if (!$deleted) {
                wp_send_json_error(array('message' => __('Could not delete the job.', 'modern-job-board')), 500);
            }
        }

        wp_send_json_success(array(
            'job_id' => $job_id,
            'message' => __('Job moved to trash.', 'modern-job-board'),
        ));
    }

    /**
     * Rebuild the MJB submenu so every item opens the tabbed admin shell.
     *
     * @return void
     */
    public static function reorder_menu()
    {
        global $submenu;

        if (!current_user_can('manage_options')) {
            return;
        }

        $capability = 'manage_options';
        $tabs = self::get_tabs();
        $menu = array(
            array(
                __('Dashboard', 'modern-job-board'),
                $capability,
                'modern-job-board',
                __('Dashboard', 'modern-job-board'),
            ),
        );

        foreach (self::get_post_type_tab_map() as $post_type => $tab_id) {
            $post_type_object = get_post_type_object($post_type);
            if (!$post_type_object || empty($post_type_object->show_ui)) {
                continue;
            }

            $menu[] = array(
                $post_type_object->labels->menu_name,
                $capability,
                self::get_tab_menu_slug($tab_id),
                $post_type_object->labels->all_items,
            );
        }

        foreach (array('settings', 'setup', 'custom-fields', 'tools') as $tab_id) {
            if (!isset($tabs[$tab_id])) {
                continue;
            }

            $menu[] = array(
                $tabs[$tab_id]['label'],
                $capability,
                self::get_tab_menu_slug($tab_id),
                $tabs[$tab_id]['label'],
            );
        }

        $submenu['modern-job-board'] = $menu;
    }

    /**
     * Keep the Modern Job Board menu highlighted on shell + CPT edit screens.
     *
     * @param string $parent_file
     * @return string
     */
    public static function filter_parent_file($parent_file)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'modern-job-board') {
            return 'modern-job-board';
        }

        $post_type = self::get_current_admin_post_type();
        if ($post_type && isset(self::get_post_type_tab_map()[$post_type])) {
            return 'modern-job-board';
        }

        return $parent_file;
    }

    /**
     * Highlight the active tab submenu item.
     *
     * @param string $submenu_file
     * @return string
     */
    public static function filter_submenu_file($submenu_file)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'modern-job-board') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $tab = self::sanitize_tab(sanitize_key(wp_unslash($_GET['tab'] ?? self::get_default_tab())));

            return self::get_tab_menu_slug($tab);
        }

        $post_type = self::get_current_admin_post_type();
        if ($post_type && isset(self::get_post_type_tab_map()[$post_type])) {
            return self::get_tab_menu_slug(self::get_post_type_tab_map()[$post_type]);
        }

        return $submenu_file;
    }

    /**
     * Resolve the post type for the current admin edit screen.
     *
     * @return string
     */
    private static function get_current_admin_post_type()
    {
        global $pagenow, $post;

        if (!in_array($pagenow, array('edit.php', 'post.php', 'post-new.php'), true)) {
            return '';
        }

        if ($post instanceof WP_Post) {
            return $post->post_type;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['post_type'])) {
            return sanitize_key(wp_unslash($_GET['post_type']));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($pagenow === 'post.php' && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            if ($post_id > 0) {
                $resolved = get_post_type($post_id);
                return is_string($resolved) ? $resolved : '';
            }
        }

        return '';
    }

    /**
     * Redirect legacy standalone admin pages to the tabbed shell.
     *
     * @return void
     */
    public static function redirect_legacy_pages()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        global $pagenow;

        if ($pagenow === 'edit.php') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'post';
            $tab_map = self::get_post_type_tab_map();

            if (isset($tab_map[$post_type])) {
                // Allow the real WP list table when explicitly requested (Open Full List).
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (!empty($_GET['mjb_full_list'])) {
                    return;
                }

                wp_safe_redirect(self::get_tab_url($tab_map[$post_type]));
                exit;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        $redirects = array(
            'mjb-settings' => 'settings',
            'mjb-setup' => 'setup',
            'mjb-custom-fields' => 'custom-fields',
            'mjb-tools' => 'tools',
        );

        if (!isset($redirects[$page])) {
            return;
        }

        $args = array();

        if ($redirects[$page] === 'tools' && isset($_GET['tab'])) {
            $args['tools_tab'] = sanitize_key(wp_unslash($_GET['tab']));
        }

        if (isset($_GET['message'])) {
            $args['message'] = sanitize_key(wp_unslash($_GET['message']));
        }

        foreach (array('imported', 'xml_imported', 'xml_skipped', 'xml_error', 'mjb_pages_created', 'mjb_pages_existing') as $key) {
            if (isset($_GET[$key])) {
                $args[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }

        wp_safe_redirect(self::get_tab_url($redirects[$page], $args));
        exit;
    }

    /**
     * Inject custom SVG icon for the top-level admin menu item.
     *
     * @return void
     */
    public static function render_menu_icon_css()
    {
        $icon = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>');
        echo '<style>#adminmenu #toplevel_page_modern-job-board .wp-menu-image::before{content:"";background-image:url("data:image/svg+xml,' . esc_attr($icon) . '");background-repeat:no-repeat;background-position:center;background-size:18px 18px;width:20px;height:20px;display:inline-block;opacity:.75;}#adminmenu #toplevel_page_modern-job-board:hover .wp-menu-image::before,#adminmenu #toplevel_page_modern-job-board.wp-has-current-submenu .wp-menu-image::before{opacity:1;}</style>';
    }

    /**
     * Render dashboard tab content.
     *
     * @return void
     */
    private static function render_dashboard_tab()
    {
        $job_count = wp_count_posts('job_listing')->publish;
        $app_count = wp_count_posts('job_application')->publish;
        $company_count = wp_count_posts('company')->publish;
        $resume_count = wp_count_posts('mjb_resume')->publish;
        $performance = MJB_Analytics::summarize_job_stats(MJB_Analytics::get_admin_job_stats());
        $top_jobs = MJB_Analytics::get_top_jobs_for_charts(5);
        $pending_webhooks = MJB_Webhook_Queue::get_pending_count();
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--dashboard">
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
                <button type="button" class="mjb-feature-card mjb-feature-card--action" data-tab="jobs">
                    <div class="mjb-feature-icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('briefcase', 24);
                    ?></div>
                    <h3><?php esc_html_e('Manage Jobs', 'modern-job-board'); ?></h3>
                    <p><?php esc_html_e('View, edit, and moderate job listings. Manage expiration dates and featured status.', 'modern-job-board'); ?></p>
                </button>
                <button type="button" class="mjb-feature-card mjb-feature-card--action" data-tab="applications">
                    <div class="mjb-feature-icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('inbox', 24);
                    ?></div>
                    <h3><?php esc_html_e('Applications', 'modern-job-board'); ?></h3>
                    <p><?php esc_html_e('Review candidate applications and download resumes.', 'modern-job-board'); ?></p>
                </button>
                <button type="button" class="mjb-feature-card mjb-feature-card--action" data-tab="settings">
                    <div class="mjb-feature-icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('settings', 24);
                    ?></div>
                    <h3><?php esc_html_e('Settings', 'modern-job-board'); ?></h3>
                    <p><?php esc_html_e('Configure listings, Google Maps API, and monetization options.', 'modern-job-board'); ?></p>
                </button>
                <button type="button" class="mjb-feature-card mjb-feature-card--action" data-tab="setup">
                    <div class="mjb-feature-icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('wand-sparkles', 24);
                    ?></div>
                    <h3><?php esc_html_e('Setup', 'modern-job-board'); ?></h3>
                    <p><?php esc_html_e('Create frontend pages for job search, dashboards, and registration shortcodes.', 'modern-job-board'); ?></p>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Convert a Settings API section id into a URL-safe subtab slug.
     *
     * @param string $section_id e.g. mjb_license_section
     * @return string e.g. license
     */
    public static function settings_section_slug($section_id)
    {
        $slug = (string) $section_id;
        if (strpos($slug, 'mjb_') === 0) {
            $slug = substr($slug, 4);
        }
        if (substr($slug, -8) === '_section') {
            $slug = substr($slug, 0, -8);
        }

        return sanitize_key($slug);
    }

    /**
     * Registered settings sections for the Settings tab, keyed by subtab slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_settings_sections()
    {
        global $wp_settings_sections;

        $out = array();
        $page = 'mjb-settings';
        if (empty($wp_settings_sections[$page]) || !is_array($wp_settings_sections[$page])) {
            return $out;
        }

        foreach ($wp_settings_sections[$page] as $section) {
            if (empty($section['id'])) {
                continue;
            }
            $slug = self::settings_section_slug($section['id']);
            if ($slug === '') {
                continue;
            }
            $out[$slug] = $section;
        }

        return $out;
    }

    /**
     * Render settings tab content with a subtab per settings section.
     *
     * @param string $active_subtab Subtab slug (section id without mjb_ / _section).
     * @return void
     */
    private static function render_settings_tab($active_subtab = '')
    {
        $sections = self::get_settings_sections();
        $active_subtab = sanitize_key((string) $active_subtab);
        if ($active_subtab === '' || !isset($sections[$active_subtab])) {
            $keys = array_keys($sections);
            $active_subtab = !empty($keys) ? $keys[0] : '';
        }

        // When settings are loaded via admin-ajax, settings_fields() stamps _wp_http_referer
        // as admin-ajax.php. options.php then redirects there and the browser shows "0".
        $return_args = array();
        if ($active_subtab !== '') {
            $return_args['settings_tab'] = $active_subtab;
        }
        $settings_return = self::get_tab_url('settings', $return_args);
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--settings" data-active-settings-tab="<?php echo esc_attr($active_subtab); ?>">
            <h2 class="mjb-section-title"><?php esc_html_e('Settings', 'modern-job-board'); ?></h2>
            <?php
            // Surfaces Settings API success/error notices after options.php redirect.
            settings_errors();

            if (!empty($sections)) :
                ?>
            <div class="mjb-settings-subtabs mjb-admin-subtabs" role="tablist" aria-label="<?php esc_attr_e('Settings sections', 'modern-job-board'); ?>">
                <?php foreach ($sections as $slug => $section) :
                    $is_active = ($slug === $active_subtab);
                    $label = !empty($section['title']) ? $section['title'] : $slug;
                    ?>
                <button
                    type="button"
                    class="mjb-settings-subtab mjb-admin-subtab<?php echo $is_active ? ' is-active' : ''; ?>"
                    data-settings-tab="<?php echo esc_attr($slug); ?>"
                    role="tab"
                    id="mjb-settings-tab-<?php echo esc_attr($slug); ?>"
                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                    aria-controls="mjb-settings-panel-<?php echo esc_attr($slug); ?>"
                ><?php echo esc_html($label); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="post" class="mjb-settings-form">
                <?php
                settings_fields('mjb_settings_group');
                // Override AJAX referer so save redirects back to this Settings subtab.
                echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(wp_unslash($settings_return)) . '" />';

                if (empty($sections)) {
                    do_settings_sections('mjb-settings');
                } else {
                    foreach ($sections as $slug => $section) {
                        $is_active = ($slug === $active_subtab);
                        $panel_id = 'mjb-settings-panel-' . $slug;
                        echo '<div class="mjb-settings-section' . ($is_active ? ' is-active' : '') . '" id="' . esc_attr($panel_id) . '" data-settings-tab="' . esc_attr($slug) . '" role="tabpanel" aria-labelledby="mjb-settings-tab-' . esc_attr($slug) . '"' . ($is_active ? '' : ' hidden') . '>';

                        if (!empty($section['title'])) {
                            echo '<h2 class="mjb-settings-section__title">' . esc_html($section['title']) . '</h2>';
                        }

                        if (!empty($section['callback']) && is_callable($section['callback'])) {
                            call_user_func($section['callback'], $section);
                        }

                        echo '<table class="form-table" role="presentation">';
                        do_settings_fields('mjb-settings', $section['id']);
                        echo '</table>';
                        echo '</div>';
                    }
                }

                submit_button(__('Save Settings', 'modern-job-board'), 'primary mjb-btn mjb-btn-primary');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render a paginated post-type list tab.
     *
     * @param string $post_type
     * @param int    $page
     * @return void
     */
    private static function render_post_type_tab($post_type, $page)
    {
        $post_type_object = get_post_type_object($post_type);
        $title = $post_type_object ? $post_type_object->labels->name : ucfirst(str_replace('_', ' ', $post_type));
        // Prefer create_posts; fall back to edit_posts when a CPT still blocks create.
        $create_cap = ($post_type_object && !empty($post_type_object->cap->create_posts))
            ? $post_type_object->cap->create_posts
            : '';
        $can_create = $create_cap
            && $create_cap !== 'do_not_allow'
            && current_user_can($create_cap);
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => self::PER_PAGE,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $headers = array(
            __('Title', 'modern-job-board'),
            __('Status', 'modern-job-board'),
            __('Date', 'modern-job-board'),
            __('Actions', 'modern-job-board'),
        );

        ?>
        <div class="mjb-tab-panel mjb-tab-panel--list" data-tab-panel="<?php echo esc_attr(str_replace('_', '-', $post_type)); ?>">
            <div class="mjb-tab-panel__header">
                <h2 class="mjb-section-title"><?php echo esc_html($title); ?></h2>
                <div class="mjb-tab-panel__actions">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type . '&mjb_full_list=1')); ?>" class="mjb-btn mjb-btn-outline">
                        <?php esc_html_e('Open Full List', 'modern-job-board'); ?>
                    </a>
                    <?php if ($can_create) : ?>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . rawurlencode($post_type))); ?>" class="mjb-btn mjb-btn-primary">
                        <span class="mjb-btn__icon" aria-hidden="true"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('plus', 16);
                        ?></span>
                        <span class="mjb-btn__label"><?php esc_html_e('Add New', 'modern-job-board'); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--admin', count($headers));
            $grid->render_header($headers)->open_body();

            if (!$query->have_posts()) {
                $grid->render_empty_row(__('No records found.', 'modern-job-board'));
            } else {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $edit_url = get_edit_post_link($post_id, 'raw');
                    $view_url = get_permalink($post_id);
                    $status = get_post_status($post_id);
                    $actions = '<div class="mjb-list-actions">';
                    $actions .= '<a class="mjb-btn mjb-btn-outline mjb-btn--sm" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'modern-job-board') . '</a>';
                    if ($view_url) {
                        $actions .= '<a class="mjb-btn mjb-btn-outline mjb-btn--sm" href="' . esc_url($view_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View', 'modern-job-board') . '</a>';
                    }

                    if ($post_type === 'job_listing' && current_user_can('delete_post', $post_id)) {
                        $actions .= sprintf(
                            '<button type="button" class="mjb-btn mjb-btn-outline mjb-btn--sm mjb-btn--delete mjb-job-delete" data-job-id="%1$d" data-job-title="%2$s">%3$s</button>',
                            (int) $post_id,
                            esc_attr(get_the_title($post_id)),
                            esc_html__('Delete', 'modern-job-board')
                        );
                    }
                    $actions .= '</div>';

                    $grid->open_row()
                        ->render_cell(esc_html(get_the_title()), $headers[0])
                        ->render_cell(esc_html(ucfirst($status)), $headers[1])
                        ->render_cell(esc_html(get_the_date()), $headers[2])
                        ->render_cell($actions, $headers[3])
                        ->close_row();
                }
                wp_reset_postdata();
            }

            $grid->close_body()->end();
            self::render_pagination($query->max_num_pages, $page);

            if ($post_type === 'job_listing') {
                self::render_delete_job_modal();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Confirmation modal for deleting a job from the Jobs list.
     *
     * @return void
     */
    private static function render_delete_job_modal()
    {
        ?>
        <div class="mjb-modal" id="mjb-delete-job-modal" hidden data-mjb-modal>
            <div class="mjb-modal__backdrop" data-mjb-modal-dismiss tabindex="-1"></div>
            <div class="mjb-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mjb-delete-job-title" aria-describedby="mjb-delete-job-desc">
                <header class="mjb-modal__header">
                    <h3 id="mjb-delete-job-title" class="mjb-modal__title"><?php esc_html_e('Delete job?', 'modern-job-board'); ?></h3>
                    <button type="button" class="mjb-modal__close" data-mjb-modal-dismiss aria-label="<?php esc_attr_e('Close', 'modern-job-board'); ?>">&times;</button>
                </header>
                <div class="mjb-modal__body">
                    <p id="mjb-delete-job-desc">
                        <?php esc_html_e('This will move the job to the trash. You can restore it later from the WordPress trash if needed.', 'modern-job-board'); ?>
                    </p>
                    <p class="mjb-modal__emphasis">
                        <strong class="mjb-modal__job-title"></strong>
                    </p>
                </div>
                <footer class="mjb-modal__footer">
                    <button type="button" class="mjb-btn mjb-btn-outline" data-mjb-modal-dismiss>
                        <?php esc_html_e('Cancel', 'modern-job-board'); ?>
                    </button>
                    <button type="button" class="mjb-btn mjb-btn--danger" id="mjb-delete-job-confirm">
                        <?php esc_html_e('Delete job', 'modern-job-board'); ?>
                    </button>
                </footer>
            </div>
        </div>
        <?php
    }

    /**
     * Render applications tab with custom columns.
     *
     * @param int $page
     * @return void
     */
    private static function render_applications_tab($page)
    {
        $query = new WP_Query(array(
            'post_type' => 'job_application',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => self::PER_PAGE,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $headers = array(
            __('Application', 'modern-job-board'),
            __('Candidate', 'modern-job-board'),
            __('Applied For', 'modern-job-board'),
            __('Resume', 'modern-job-board'),
            __('Date', 'modern-job-board'),
        );

        ?>
        <div class="mjb-tab-panel mjb-tab-panel--list" data-tab-panel="applications">
            <div class="mjb-tab-panel__header">
                <h2 class="mjb-section-title"><?php esc_html_e('Applications', 'modern-job-board'); ?></h2>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_application&mjb_full_list=1')); ?>" class="mjb-btn mjb-btn-outline">
                    <?php esc_html_e('Open Full List', 'modern-job-board'); ?>
                </a>
            </div>

            <?php
            $grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--admin', count($headers));
            $grid->render_header($headers)->open_body();

            if (!$query->have_posts()) {
                $grid->render_empty_row(__('No applications found.', 'modern-job-board'));
            } else {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $candidate = get_post_meta($post_id, '_candidate_name', true);
                    $job_id = get_post_meta($post_id, '_job_applied_for', true);
                    $job_title = $job_id ? get_the_title($job_id) : '-';
                    $job_link = $job_id ? '<a href="' . esc_url(get_edit_post_link($job_id)) . '">' . esc_html($job_title) . '</a>' : '-';
                    $resume_url = MJB_Resumes::get_application_download_url($post_id);
                    $resume_link = $resume_url
                        ? '<a href="' . esc_url($resume_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Download', 'modern-job-board') . '</a>'
                        : '&mdash;';

                    $grid->open_row()
                        ->render_cell('<a href="' . esc_url(get_edit_post_link($post_id, 'raw')) . '">' . esc_html(get_the_title()) . '</a>', $headers[0])
                        ->render_cell(esc_html($candidate ?: '-'), $headers[1])
                        ->render_cell($job_link, $headers[2])
                        ->render_cell($resume_link, $headers[3])
                        ->render_cell(esc_html(get_the_date()), $headers[4])
                        ->close_row();
                }
                wp_reset_postdata();
            }

            $grid->close_body()->end();
            self::render_pagination($query->max_num_pages, $page);
            ?>
        </div>
        <?php
    }

    /**
     * Render pagination controls for list tabs.
     *
     * @param int $total_pages
     * @param int $current_page
     * @return void
     */
    private static function render_pagination($total_pages, $current_page)
    {
        $total_pages = max(1, intval($total_pages));
        $current_page = max(1, min($current_page, $total_pages));

        if ($total_pages <= 1) {
            return;
        }

        echo '<nav class="mjb-admin-pagination" aria-label="' . esc_attr__('Records pagination', 'modern-job-board') . '">';
        echo '<div class="mjb-admin-pagination__info">' . esc_html(sprintf(
            __('Page %1$d of %2$d', 'modern-job-board'),
            $current_page,
            $total_pages
        )) . '</div>';
        echo '<div class="mjb-admin-pagination__controls">';

        $prev_page = max(1, $current_page - 1);
        $next_page = min($total_pages, $current_page + 1);
        $prev_disabled = $current_page <= 1 ? ' disabled' : '';
        $next_disabled = $current_page >= $total_pages ? ' disabled' : '';

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons escaped in MJB_Icons::render(); page nums cast; disabled attr is controlled.
        printf(
            '<button type="button" class="mjb-btn mjb-btn-outline mjb-btn--sm mjb-admin-pagination__btn" data-page="%1$s"%2$s>%3$s %4$s</button>',
            esc_attr((string) $prev_page),
            $prev_disabled,
            MJB_Icons::render('chevron-left', 16),
            esc_html__('Previous', 'modern-job-board')
        );

        printf(
            '<button type="button" class="mjb-btn mjb-btn-outline mjb-btn--sm mjb-admin-pagination__btn" data-page="%1$s"%2$s>%3$s %4$s</button>',
            esc_attr((string) $next_page),
            $next_disabled,
            esc_html__('Next', 'modern-job-board'),
            MJB_Icons::render('chevron-right', 16)
        );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</div></nav>';
    }
}