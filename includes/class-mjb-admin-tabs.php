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
        if (class_exists('MJB_Admin_Jobs')) {
            MJB_Admin_Jobs::init();
        }
        if (class_exists('MJB_Admin_Companies')) {
            MJB_Admin_Companies::init();
        }
        if (class_exists('MJB_Admin_Applications')) {
            MJB_Admin_Applications::init();
        }
        if (class_exists('MJB_Admin_Resumes')) {
            MJB_Admin_Resumes::init();
        }
        add_action('admin_menu', array(__CLASS__, 'reorder_menu'), 999);
        add_action('admin_init', array(__CLASS__, 'redirect_legacy_pages'));
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
                'icon' => 'gauge',
                'paged' => false,
                'group' => 'primary',
            ),
            'jobs' => array(
                'label' => __('Jobs', 'modern-job-board'),
                'icon' => 'briefcase',
                'paged' => true,
                'group' => 'primary',
            ),
            'applications' => array(
                'label' => __('Applications', 'modern-job-board'),
                'icon' => 'inbox',
                'paged' => true,
                'group' => 'primary',
            ),
            'companies' => array(
                'label' => __('Companies', 'modern-job-board'),
                'icon' => 'building',
                'paged' => true,
                'group' => 'primary',
            ),
            'resumes' => array(
                'label' => __('Resumes', 'modern-job-board'),
                'icon' => 'file-text',
                'paged' => true,
                'group' => 'primary',
            ),
            'settings' => array(
                'label' => __('Settings', 'modern-job-board'),
                'icon' => 'settings',
                'paged' => false,
                'group' => 'config',
            ),
            'setup' => array(
                'label' => __('Setup', 'modern-job-board'),
                'icon' => 'wand-sparkles',
                'paged' => false,
                'group' => 'config',
            ),
            'custom-fields' => array(
                'label' => __('Custom Fields', 'modern-job-board'),
                'icon' => 'list-plus',
                'paged' => false,
                'group' => 'config',
            ),
            'tools' => array(
                'label' => __('Tools', 'modern-job-board'),
                'icon' => 'wrench',
                'paged' => false,
                'group' => 'config',
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
     * Skeleton HTML for an AJAX-loaded admin tab.
     * Reuses the recruiter dashboard placeholder blocks.
     *
     * @param string $tab Tab id.
     * @return string
     */
    public static function get_tab_skeleton_html($tab)
    {
        $tab = sanitize_key((string) $tab);
        if (!class_exists('MJB_Dashboard')) {
            return '';
        }

        if ($tab === 'dashboard') {
            return MJB_Dashboard::get_tab_skeleton_html('overview');
        }

        return MJB_Dashboard::get_tab_skeleton_html('jobs');
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
     * Shared admin shell header (logo, plan, primary actions).
     *
     * @return void
     */
    public static function render_shell_header()
    {
        $plan_label = MJB_License::get_plan_label();
        $unlimited = MJB_License::can('unlimited_jobs');
        $active_jobs = MJB_License::count_active_jobs();
        $limit = MJB_License::get_free_job_limit();
        $license_url = self::get_tab_url('settings', array('settings_tab' => 'license'));
        $import_url = self::get_tab_url('tools', array('tools_tab' => 'import'));
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=mjb_export_jobs'), 'mjb_export_jobs');
        $add_url = admin_url('post-new.php?post_type=job_listing');
        $chip_class = $unlimited ? 'mjb-plan-chip mjb-plan-chip--ok' : 'mjb-plan-chip';
        ?>
        <h1 class="mjb-sr-only"><?php esc_html_e('Modern Job Board', 'modern-job-board'); ?></h1>
        <hr class="wp-header-end">
        <header class="mjb-dashboard-header mjb-topbar">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled SVG from plugin assets.
            echo MJB_Admin_Dashboard::render_logo_html();
            ?>
            <span class="mjb-ver"><?php echo esc_html('v' . MJB_VERSION); ?></span>
            <span class="<?php echo esc_attr($chip_class); ?>">
                <span class="mjb-plan-chip__dot" aria-hidden="true"></span>
                <?php if ($unlimited) : ?>
                    <?php echo esc_html(sprintf(
                        /* translators: %s: plan name */
                        __('%s plan', 'modern-job-board'),
                        $plan_label
                    )); ?>
                <?php else : ?>
                    <?php echo esc_html(sprintf(
                        /* translators: 1: plan name, 2: published jobs, 3: free limit */
                        __('%1$s plan · %2$d of %3$d jobs used', 'modern-job-board'),
                        $plan_label,
                        $active_jobs,
                        $limit
                    )); ?>
                    · <a class="mjb-js-tab" href="<?php echo esc_url($license_url); ?>" data-tab="settings" data-settings-tab="license"><span class="mjb-u"><?php esc_html_e('Upgrade', 'modern-job-board'); ?></span></a>
                <?php endif; ?>
            </span>
            <span class="mjb-topbar__spacer"></span>
            <a id="mjb-export-jobs" class="mjb-btn mjb-btn-outline" href="<?php echo esc_url($export_url); ?>" aria-label="<?php esc_attr_e('Export Jobs', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Export jobs in CSV format', 'modern-job-board'); ?>">
                <span class="mjb-btn__icon" aria-hidden="true"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('download', 16);
                ?></span>
                <span class="mjb-btn__label"><?php esc_html_e('Export Jobs', 'modern-job-board'); ?></span>
            </a>
            <a class="mjb-btn mjb-btn-outline mjb-js-tab" href="<?php echo esc_url($import_url); ?>" data-tab="tools" data-tools-tab="import" aria-label="<?php esc_attr_e('Import jobs', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Import jobs in CSV format', 'modern-job-board'); ?>">
                <span class="mjb-btn__icon" aria-hidden="true"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('cloud-upload', 16);
                ?></span>
                <span class="mjb-btn__label"><?php esc_html_e('Import jobs', 'modern-job-board'); ?></span>
            </a>
            <a class="mjb-btn mjb-btn-primary" href="<?php echo esc_url($add_url); ?>">
                <span class="mjb-btn__icon" aria-hidden="true"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('plus', 14);
                ?></span>
                <span class="mjb-btn__label"><?php esc_html_e('Add new job', 'modern-job-board'); ?></span>
            </a>
        </header>
        <?php
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
        $counts = class_exists('MJB_Admin_Dashboard') ? MJB_Admin_Dashboard::get_tab_counts() : array();
        $tabs = self::get_tabs();
        $primary = array();
        $config = array();
        foreach ($tabs as $tab_id => $tab) {
            $group = isset($tab['group']) ? $tab['group'] : 'primary';
            if ($group === 'config') {
                $config[$tab_id] = $tab;
            } else {
                $primary[$tab_id] = $tab;
            }
        }

        echo '<div class="mjb-navbar">';
        echo '<button class="mjb-nav-burger" id="mjb-nav-toggle" type="button" aria-expanded="false" aria-controls="mjb-admin-nav">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
        echo MJB_Icons::render('menu', 18);
        echo '<span>' . esc_html__('Menu', 'modern-job-board') . '</span>';
        echo '<span class="mjb-nav-burger__chev" aria-hidden="true">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
        echo MJB_Icons::render('chevron-down', 16);
        echo '</span></button>';

        echo '<nav class="mjb-admin-tabs" id="mjb-admin-nav" aria-label="' . esc_attr__('Modern Job Board sections', 'modern-job-board') . '">';
        self::render_tab_group($primary, $active_tab, $as_links, $counts, 'mjb-admin-tabs__list');
        echo '<span class="mjb-admin-tabs__divider" aria-hidden="true"></span>';
        self::render_tab_group($config, $active_tab, $as_links, $counts, 'mjb-admin-tabs__list mjb-admin-tabs__list--config');
        echo '</nav></div>';
    }

    /**
     * Render one nav group.
     *
     * @param array<string, array<string, mixed>> $tabs
     * @param string                              $active_tab
     * @param bool                                $as_links
     * @param array<string, int>                  $counts
     * @param string                              $list_class
     * @return void
     */
    private static function render_tab_group($tabs, $active_tab, $as_links, $counts, $list_class)
    {
        echo '<ul class="' . esc_attr($list_class) . '" role="tablist">';

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Tab icon SVG escaped in MJB_Icons::render(); other args escaped.
        foreach ($tabs as $tab_id => $tab) {
            $is_active = $tab_id === $active_tab;
            $icon = MJB_Icons::render($tab['icon'], 18);
            $label = esc_html($tab['label']);
            $active_class = $is_active ? ' is-active' : '';
            $count_html = '';
            if (isset($counts[$tab_id])) {
                $count_html = ' <span class="mjb-admin-tabs__count">' . esc_html((string) intval($counts[$tab_id])) . '</span>';
            }
            $current = $is_active ? ' aria-current="page"' : '';

            if ($as_links && $is_active) {
                printf(
                    '<li class="mjb-admin-tabs__item" role="presentation"><span class="mjb-admin-tabs__btn%1$s" role="tab" id="mjb-tab-%2$s" aria-selected="true"%5$s>%3$s<span>%4$s</span>%6$s</span></li>',
                    $active_class,
                    esc_attr($tab_id),
                    $icon,
                    $label,
                    $current,
                    $count_html
                );
            } elseif ($as_links) {
                printf(
                    '<li class="mjb-admin-tabs__item" role="presentation"><a href="%1$s" class="mjb-admin-tabs__btn%2$s" role="tab" id="mjb-tab-%3$s" aria-selected="%4$s"%7$s>%5$s<span>%6$s</span>%8$s</a></li>',
                    esc_url(self::get_tab_url($tab_id)),
                    $active_class,
                    esc_attr($tab_id),
                    $is_active ? 'true' : 'false',
                    $icon,
                    $label,
                    $current,
                    $count_html
                );
            } else {
                printf(
                    '<li class="mjb-admin-tabs__item" role="presentation"><button type="button" class="mjb-admin-tabs__btn%1$s" role="tab" id="mjb-tab-%2$s" data-tab="%2$s" aria-selected="%3$s" aria-controls="mjb-admin-panel"%6$s>%4$s<span>%5$s</span>%7$s</button></li>',
                    $active_class,
                    esc_attr($tab_id),
                    $is_active ? 'true' : 'false',
                    $icon,
                    $label,
                    $current,
                    $count_html
                );
            }
        }
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</ul>';
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
                if (class_exists('MJB_Admin_Jobs')) {
                    MJB_Admin_Jobs::render($page);
                } else {
                    self::render_post_type_tab('job_listing', $page);
                }
                break;
            case 'applications':
                if (class_exists('MJB_Admin_Applications')) {
                    MJB_Admin_Applications::render($page);
                } else {
                    self::render_applications_tab($page);
                }
                break;
            case 'companies':
                if (class_exists('MJB_Admin_Companies')) {
                    MJB_Admin_Companies::render($page);
                } else {
                    self::render_post_type_tab('company', $page);
                }
                break;
            case 'resumes':
                if (class_exists('MJB_Admin_Resumes')) {
                    MJB_Admin_Resumes::render($page);
                } else {
                    self::render_post_type_tab('mjb_resume', $page);
                }
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

        if ($tab_id === 'dashboard' && isset($_POST['range'])) {
            $_REQUEST['range'] = absint($_POST['range']);
        }

        if (isset($_POST['mjb_list'])) {
            $_REQUEST['mjb_list'] = sanitize_key(wp_unslash($_POST['mjb_list']));
        }

        foreach (array('mjb_q', 'mjb_company', 'mjb_cat', 'mjb_type', 'mjb_orderby', 'mjb_order', 'mjb_per', 'mjb_loc', 'mjb_job', 'mjb_ext') as $jobs_arg) {
            if (isset($_POST[$jobs_arg])) {
                $_REQUEST[$jobs_arg] = wp_unslash($_POST[$jobs_arg]);
            }
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
     * Render dashboard tab content.
     *
     * @return void
     */
    private static function render_dashboard_tab()
    {
        MJB_Admin_Dashboard::render();
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
     * Render one Settings/Tools sub-tab.
     *
     * The active item is a span so the current destination is not a redundant hyperlink.
     *
     * @param array<string, mixed> $args {
     *     @type bool   $active
     *     @type string $label
     *     @type string $class
     *     @type string $id
     *     @type string $data_attr
     *     @type string $data_value
     *     @type string $aria_controls
     * }
     * @return void
     */
    public static function render_admin_subtab($args)
    {
        $args = is_array($args) ? $args : array();
        $active = !empty($args['active']);
        $label = isset($args['label']) ? (string) $args['label'] : '';
        $class = isset($args['class']) ? trim((string) $args['class']) : 'mjb-admin-subtab';
        if ($active && strpos(' ' . $class . ' ', ' is-active ') === false) {
            $class .= ' is-active';
        }
        $id = isset($args['id']) ? (string) $args['id'] : '';
        $data_attr = isset($args['data_attr']) ? (string) $args['data_attr'] : '';
        $data_value = isset($args['data_value']) ? (string) $args['data_value'] : '';
        $aria_controls = isset($args['aria_controls']) ? (string) $args['aria_controls'] : '';

        $allowed_data = array('data-settings-tab', 'data-tools-tab');
        if ($data_attr !== '' && !in_array($data_attr, $allowed_data, true)) {
            $data_attr = '';
        }

        $tag = $active ? 'span' : 'button';
        echo '<' . $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal span|button.
        if (!$active) {
            echo ' type="button"';
        }
        echo ' class="' . esc_attr($class) . '"';
        if ($data_attr !== '') {
            echo ' ' . $data_attr . '="' . esc_attr($data_value) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whitelisted attribute name.
        }
        echo ' role="tab"';
        if ($id !== '') {
            echo ' id="' . esc_attr($id) . '"';
        }
        echo ' aria-selected="' . ($active ? 'true' : 'false') . '"';
        if ($aria_controls !== '') {
            echo ' aria-controls="' . esc_attr($aria_controls) . '"';
        }
        if ($active) {
            echo ' tabindex="0"';
        }
        echo '>' . esc_html($label) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal span|button closer.
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
        <div class="mjb-tab-panel mjb-tab-panel--settings mjb-jobs mjb-settings" data-tab-panel="settings" data-active-settings-tab="<?php echo esc_attr($active_subtab); ?>">
            <div class="mjb-sec mjb-jobs__sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('settings', 22);
                    esc_html_e('Settings', 'modern-job-board');
                ?></h2>
            </div>
            <?php
            // Surfaces Settings API success/error notices after options.php redirect.
            settings_errors();

            if (!empty($sections)) :
                ?>
            <div class="mjb-jobs-views mjb-settings-subtabs" role="tablist" aria-label="<?php esc_attr_e('Settings sections', 'modern-job-board'); ?>">
                <?php foreach ($sections as $slug => $section) :
                    $is_active = ($slug === $active_subtab);
                    $label = !empty($section['title']) ? $section['title'] : $slug;
                    self::render_admin_subtab(
                        array(
                            'active' => $is_active,
                            'label' => $label,
                            'class' => 'mjb-view-pill mjb-settings-subtab',
                            'id' => 'mjb-settings-tab-' . $slug,
                            'data_attr' => 'data-settings-tab',
                            'data_value' => $slug,
                            'aria_controls' => 'mjb-settings-panel-' . $slug,
                        )
                    );
                endforeach; ?>
            </div>
            <?php endif; ?>
            <form id="mjb-generate-key-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" hidden></form>
            <form id="mjb-settings-form" action="<?php echo esc_url(admin_url('options.php')); ?>" method="post" class="mjb-settings-form">
                <?php
                settings_fields('mjb_settings_group');
                // Override AJAX referer so save redirects back to this Settings subtab.
                echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(wp_unslash($settings_return)) . '" />';
                ?>
                <div class="mjb-panel mjb-jobs-panel mjb-settings-panel">
                    <?php
                    if (empty($sections)) {
                        do_settings_sections('mjb-settings');
                    } else {
                        foreach ($sections as $slug => $section) {
                            $is_active = ($slug === $active_subtab);
                            $panel_id = 'mjb-settings-panel-' . $slug;
                            echo '<div class="mjb-settings-section' . ($is_active ? ' is-active' : '') . '" id="' . esc_attr($panel_id) . '" data-settings-tab="' . esc_attr($slug) . '" role="tabpanel" aria-labelledby="mjb-settings-tab-' . esc_attr($slug) . '"' . ($is_active ? '' : ' hidden') . '>';

                            if (!empty($section['title'])) {
                                echo '<div class="mjb-panel__head"><h3 class="mjb-settings-section__title">' . esc_html($section['title']) . '</h3></div>';
                            }

                            if (!empty($section['callback']) && is_callable($section['callback'])) {
                                echo '<div class="mjb-settings-section__intro">';
                                call_user_func($section['callback'], $section);
                                echo '</div>';
                            }

                            echo '<table class="form-table" role="presentation">';
                            do_settings_fields('mjb-settings', $section['id']);
                            echo '</table>';
                            echo '</div>';
                        }
                    }
                    ?>
                    <div class="mjb-jobs-foot">
                        <span class="mjb-topbar__spacer"></span>
                        <?php submit_button(__('Save Settings', 'modern-job-board'), 'primary mjb-btn mjb-btn-primary', 'submit', true, array('form' => 'mjb-settings-form')); ?>
                    </div>
                </div>
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
        $list_filter = '';
        if (class_exists('MJB_Admin_Dashboard') && isset($_REQUEST['mjb_list'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
            $list_filter = MJB_Admin_Dashboard::sanitize_list_filter(wp_unslash($_REQUEST['mjb_list']), $post_type);
        }

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => self::PER_PAGE,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if ($list_filter !== '' && class_exists('MJB_Admin_Dashboard')) {
            $filtered_ids = MJB_Admin_Dashboard::get_filtered_post_ids($list_filter, $post_type);
            if (is_array($filtered_ids)) {
                $query_args['post__in'] = !empty($filtered_ids) ? $filtered_ids : array(0);
                $query_args['orderby'] = 'post__in';
            }
        }

        $query = new WP_Query($query_args);

        $headers = array(
            __('Title', 'modern-job-board'),
            __('Status', 'modern-job-board'),
            __('Date', 'modern-job-board'),
            __('Actions', 'modern-job-board'),
        );

        ?>
        <div class="mjb-tab-panel mjb-tab-panel--list" data-tab-panel="<?php echo esc_attr(str_replace('_', '-', $post_type)); ?>"<?php echo $list_filter !== '' ? ' data-list-filter="' . esc_attr($list_filter) . '"' : ''; ?>>
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
            <?php if ($list_filter !== '') : ?>
            <p class="mjb-list-filter-note">
                <?php echo esc_html(self::list_filter_notice($list_filter)); ?>
                <a class="mjb-js-tab" href="<?php echo esc_url(self::get_tab_url($post_type === 'company' ? 'companies' : 'jobs')); ?>" data-tab="<?php echo esc_attr($post_type === 'company' ? 'companies' : 'jobs'); ?>" data-list-filter="">
                    <?php esc_html_e('Show all', 'modern-job-board'); ?>
                </a>
            </p>
            <?php endif; ?>

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

                    $title_html = $edit_url
                        ? '<a href="' . esc_url($edit_url) . '">' . esc_html(get_the_title()) . '</a>'
                        : esc_html(get_the_title());

                    $grid->open_row()
                        ->render_cell($title_html, $headers[0])
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
     * Human-readable notice for a dashboard-driven list filter.
     *
     * @param string $filter
     * @return string
     */
    private static function list_filter_notice($filter)
    {
        switch ($filter) {
            case 'zero_views':
                return __('Showing jobs with no views.', 'modern-job-board');
            case 'expiring':
                return __('Showing jobs that expire within 7 days.', 'modern-job-board');
            case 'no_apps':
                return __('Showing jobs with no applications.', 'modern-job-board');
            case 'pending':
                return __('Showing companies awaiting approval.', 'modern-job-board');
            default:
                return __('Filtered list.', 'modern-job-board');
        }
    }

    /**
     * Confirmation modal for deleting a job from the Jobs list.
     *
     * @return void
     */
    public static function render_delete_job_modal()
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
        $query_args = array(
            'post_type' => 'job_application',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => self::PER_PAGE,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        $job_id = 0;
        if (isset($_REQUEST['mjb_job'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only applications filter.
            $job_id = absint($_REQUEST['mjb_job']);
        }
        if ($job_id > 0) {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_job_applied_for',
                    'value' => (string) $job_id,
                ),
            );
        }

        $query = new WP_Query($query_args);

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