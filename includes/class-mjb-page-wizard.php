<?php
/**
 * Modern Job Board Setup Page Wizard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Page_Wizard
{
    const NOTICE_DISMISS_OPTION = 'mjb_setup_notice_dismissed';

    /**
     * Initialize wizard hooks.
     */
    public static function init()
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', array(__CLASS__, 'register_admin_page'), 20);
        add_action('admin_init', array(__CLASS__, 'handle_create_pages'));
        add_action('wp_ajax_mjb_admin_setup', array(__CLASS__, 'ajax_create_pages'));
        add_action('admin_init', array(__CLASS__, 'handle_dismiss_notice'));
        add_action('admin_notices', array(__CLASS__, 'render_setup_notice'));
    }

    /**
     * Required frontend pages and shortcodes.
     *
     * @return array<int, array<string, string>>
     */
    public static function get_page_definitions()
    {
        return array(
            array(
                'slug' => 'jobs',
                'title' => __('Jobs', 'modern-job-board'),
                'shortcode' => 'mjb_jobs',
                'option_key' => 'mjb_jobs_page_id',
                'parent' => '',
            ),
            array(
                'slug' => 'post-a-job',
                'title' => __('Post a Job', 'modern-job-board'),
                'shortcode' => 'mjb_job_form',
                'option_key' => 'mjb_job_form_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'recruiter-dashboard',
                'title' => __('Recruiter Dashboard', 'modern-job-board'),
                'shortcode' => 'mjb_dashboard',
                'option_key' => 'mjb_employer_dashboard_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'candidate-dashboard',
                'title' => __('Candidate Dashboard', 'modern-job-board'),
                'shortcode' => 'mjb_candidate_dashboard',
                'option_key' => 'mjb_candidate_dashboard_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'recruiter-registration',
                'title' => __('Recruiter Registration', 'modern-job-board'),
                'shortcode' => 'mjb_employer_registration',
                'option_key' => 'mjb_employer_registration_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'candidate-registration',
                'title' => __('Candidate Registration', 'modern-job-board'),
                'shortcode' => 'mjb_candidate_registration',
                'option_key' => 'mjb_candidate_registration_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'recruiter-login',
                'title' => __('Recruiter Login', 'modern-job-board'),
                'shortcode' => 'mjb_employer_login',
                'option_key' => 'mjb_employer_login_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'candidate-login',
                'title' => __('Candidate Login', 'modern-job-board'),
                'shortcode' => 'mjb_candidate_login',
                'option_key' => 'mjb_candidate_login_page_id',
                'parent' => 'jobs',
            ),
            array(
                'slug' => 'blog',
                'title' => __('Blog', 'modern-job-board'),
                'shortcode' => '',
                'option_key' => 'mjb_blog_page_id',
                'parent' => 'jobs',
                'content' => "<!-- wp:latest-posts {\"postsToShow\":10,\"displayPostContent\":false} /-->",
            ),
        );
    }

    /**
     * Create any missing setup pages and nest demo pages under /jobs/.
     *
     * @return array{created:int, existing:int}
     */
    public static function create_missing_pages()
    {
        $created = 0;
        $existing = 0;

        foreach (self::get_page_definitions() as $definition) {
            $page_id = 0;
            if (!empty($definition['shortcode']) && !empty($definition['option_key'])) {
                $page_id = MJB_Page_Resolver::resolve_page_id($definition['shortcode'], $definition['option_key']);
            } elseif (!empty($definition['option_key'])) {
                $page_id = intval(get_option($definition['option_key']));
                if ($page_id && get_post_status($page_id) !== 'publish') {
                    $page_id = 0;
                }
            }

            if ($page_id) {
                $existing++;
                continue;
            }

            $new_id = self::create_page($definition);
            if ($new_id) {
                if (!empty($definition['option_key'])) {
                    update_option($definition['option_key'], $new_id, false);
                }
                $created++;
            }
        }

        self::ensure_jobs_page_hierarchy();

        return array(
            'created' => $created,
            'existing' => $existing,
        );
    }

    /**
     * Nest demo shortcode pages under the Jobs page so URLs are /jobs/{slug}/.
     *
     * @return int Number of pages updated.
     */
    public static function ensure_jobs_page_hierarchy()
    {
        $jobs_id = MJB_Page_Resolver::resolve_page_id('mjb_jobs', 'mjb_jobs_page_id');
        if (!$jobs_id) {
            $jobs_page = get_page_by_path('jobs');
            $jobs_id = $jobs_page ? intval($jobs_page->ID) : 0;
        }
        if (!$jobs_id) {
            return 0;
        }

        // Jobs board stays at top-level /jobs/.
        if (intval(get_post_field('post_parent', $jobs_id)) !== 0) {
            wp_update_post(array(
                'ID' => $jobs_id,
                'post_parent' => 0,
                'post_name' => 'jobs',
            ));
        }

        $updated = 0;

        foreach (self::get_page_definitions() as $definition) {
            if (empty($definition['parent']) || $definition['parent'] !== 'jobs') {
                continue;
            }

            $page_id = 0;
            if (!empty($definition['shortcode']) && !empty($definition['option_key'])) {
                $page_id = MJB_Page_Resolver::resolve_page_id($definition['shortcode'], $definition['option_key']);
            } elseif (!empty($definition['option_key'])) {
                $page_id = intval(get_option($definition['option_key']));
            }

            if (!$page_id) {
                $by_path = get_page_by_path($definition['slug']);
                if ($by_path) {
                    $page_id = intval($by_path->ID);
                    if (!empty($definition['option_key'])) {
                        update_option($definition['option_key'], $page_id, false);
                    }
                }
            }

            if (!$page_id) {
                continue;
            }

            $needs = array();
            if (intval(get_post_field('post_parent', $page_id)) !== $jobs_id) {
                $needs['post_parent'] = $jobs_id;
            }
            if (get_post_field('post_name', $page_id) !== $definition['slug']) {
                $needs['post_name'] = $definition['slug'];
            }

            if (!empty($needs)) {
                $needs['ID'] = $page_id;
                wp_update_post($needs);
                $updated++;
            }

            if (!empty($definition['option_key'])) {
                update_option($definition['option_key'], $page_id, false);
            }
        }

        return $updated;
    }

    /**
     * Create a single page from a definition.
     *
     * @param array $definition
     * @return int
     */
    public static function create_page($definition)
    {
        $content = '';
        if (!empty($definition['content'])) {
            $content = $definition['content'];
        } elseif (!empty($definition['shortcode'])) {
            $content = '[' . $definition['shortcode'] . ']';
        }

        $parent_id = 0;
        if (!empty($definition['parent']) && $definition['parent'] === 'jobs') {
            $parent_id = MJB_Page_Resolver::resolve_page_id('mjb_jobs', 'mjb_jobs_page_id');
            if (!$parent_id) {
                $jobs_page = get_page_by_path('jobs');
                $parent_id = $jobs_page ? intval($jobs_page->ID) : 0;
            }
        }

        $page_id = wp_insert_post(array(
            'post_title' => $definition['title'],
            'post_name' => sanitize_title($definition['slug']),
            'post_content' => $content,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_parent' => $parent_id,
        ), true);

        return (!$page_id || is_wp_error($page_id)) ? 0 : intval($page_id);
    }

    /**
     * Return page setup status rows for the admin UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_page_status_rows()
    {
        $rows = array();

        foreach (self::get_page_definitions() as $definition) {
            $page_id = 0;
            if (!empty($definition['shortcode'])) {
                $page_id = MJB_Page_Resolver::resolve_page_id($definition['shortcode'], $definition['option_key']);
            } elseif (!empty($definition['option_key'])) {
                $page_id = intval(get_option($definition['option_key']));
                if ($page_id && get_post_status($page_id) !== 'publish') {
                    $page_id = 0;
                }
            }

            $rows[] = array(
                'title' => $definition['title'],
                'shortcode' => !empty($definition['shortcode']) ? $definition['shortcode'] : '—',
                'slug' => $definition['slug'],
                'page_id' => $page_id,
                'status' => $page_id ? 'ready' : 'missing',
                'url' => $page_id ? get_permalink($page_id) : '',
                'edit_url' => $page_id ? get_edit_post_link($page_id, 'raw') : '',
            );
        }

        return $rows;
    }

    /**
     * Whether any required pages are still missing.
     *
     * @return bool
     */
    public static function has_missing_pages()
    {
        foreach (self::get_page_status_rows() as $row) {
            if ($row['status'] === 'missing') {
                return true;
            }
        }

        return false;
    }

    /**
     * Register the setup admin page.
     */
    public static function register_admin_page()
    {
        // Setup is rendered inside the tabbed admin shell.
    }

    /**
     * Handle create-pages form submission.
     */
    public static function ajax_create_pages()
    {
        self::handle_create_pages();
        wp_send_json_error(array(
            'message' => __('That action could not be completed.', 'modern-job-board'),
        ));
    }

    public static function handle_create_pages()
    {
        if (wp_doing_ajax() && !doing_action('wp_ajax_mjb_admin_setup')) {
            return;
        }

        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'create_setup_pages') {
            return;
        }

        if (!current_user_can('manage_options') || !check_admin_referer('mjb_create_setup_pages_nonce')) {
            return;
        }

        $result = self::create_missing_pages();
        $created = intval($result['created']);
        $existing = intval($result['existing']);
        $message = sprintf(
            /* translators: 1: pages created, 2: pages already configured */
            __('%1$d pages created. %2$d pages were already configured.', 'modern-job-board'),
            $created,
            $existing
        );

        if (wp_doing_ajax()) {
            wp_send_json_success(array(
                'message' => $message,
                'tab' => 'setup',
            ));
        }

        $redirect = add_query_arg(
            array(
                'page' => 'modern-job-board',
                'tab' => 'setup',
                'mjb_pages_created' => $created,
                'mjb_pages_existing' => $existing,
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle setup notice dismissal.
     */
    public static function handle_dismiss_notice()
    {
        if (!isset($_GET['mjb_dismiss_setup_notice'])) {
            return;
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'mjb_dismiss_setup_notice')) {
            return;
        }

        update_option(self::NOTICE_DISMISS_OPTION, 1, false);
        wp_safe_redirect(remove_query_arg(array('mjb_dismiss_setup_notice', '_wpnonce')));
        exit;
    }

    /**
     * Render admin notice when setup pages are missing.
     */
    public static function render_setup_notice()
    {
        if (!current_user_can('manage_options') || get_option(self::NOTICE_DISMISS_OPTION)) {
            return;
        }

        if (!self::has_missing_pages()) {
            return;
        }

        $setup_url = admin_url('admin.php?page=modern-job-board&tab=setup');
        $dismiss_url = wp_nonce_url(
            add_query_arg('mjb_dismiss_setup_notice', '1'),
            'mjb_dismiss_setup_notice'
        );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php esc_html_e('Modern Job Board setup is incomplete. Create the required frontend pages to enable dashboards, registration, and job search.', 'modern-job-board'); ?>
                <a href="<?php echo esc_url($setup_url); ?>"><?php esc_html_e('Open Setup Wizard', 'modern-job-board'); ?></a>
                |
                <a href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss', 'modern-job-board'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Render setup wizard tab content.
     */
    public static function render_setup_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['mjb_pages_created'])) {
            $created = intval($_GET['mjb_pages_created']);
            $existing = intval($_GET['mjb_pages_existing'] ?? 0);
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html(sprintf(
                    __('%1$d pages created. %2$d pages were already configured.', 'modern-job-board'),
                    $created,
                    $existing
                )) .
                '</p></div>';
        }

        $rows = self::get_page_status_rows();
        $missing_count = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'missing') {
                $missing_count++;
            }
        }
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--setup">
            <h2 class="mjb-section-title"><?php esc_html_e('Setup', 'modern-job-board'); ?></h2>
            <p class="mjb-tab-panel__lead"><?php esc_html_e('Create WordPress pages for each shortcode used by the job board frontend.', 'modern-job-board'); ?></p>

            <?php if ($missing_count > 0) : ?>
                <div class="notice notice-info">
                    <p><?php echo esc_html(sprintf(_n('%d required page is missing.', '%d required pages are missing.', $missing_count, 'modern-job-board'), $missing_count)); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('All required frontend pages are configured.', 'modern-job-board'); ?></p>
                </div>
            <?php endif; ?>

            <?php
            $wizard_headers = array(
                __('Page', 'modern-job-board'),
                __('Shortcode', 'modern-job-board'),
                __('Status', 'modern-job-board'),
                __('URL', 'modern-job-board'),
            );
            $wizard_grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--admin', count($wizard_headers));
            $wizard_grid->render_header($wizard_headers)->open_body();
            foreach ($rows as $row) {
                if ($row['status'] === 'ready') {
                    $status_html = '<span class="mjb-status-ready">' . esc_html__('Ready', 'modern-job-board') . '</span>';
                } else {
                    $status_html = '<span class="mjb-status-missing">' . esc_html__('Missing', 'modern-job-board') . '</span>';
                }

                if ($row['url']) {
                    $url_html = '<a href="' . esc_url($row['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View', 'modern-job-board') . '</a>';
                    if ($row['edit_url']) {
                        $url_html .= ' | <a href="' . esc_url($row['edit_url']) . '">' . esc_html__('Edit', 'modern-job-board') . '</a>';
                    }
                } else {
                    $url_html = '&mdash;';
                }

                $wizard_grid->open_row()
                    ->render_cell(esc_html($row['title']), $wizard_headers[0])
                    ->render_cell('<code>[' . esc_html($row['shortcode']) . ']</code>', $wizard_headers[1])
                    ->render_cell($status_html, $wizard_headers[2])
                    ->render_cell($url_html, $wizard_headers[3])
                    ->close_row();
            }
            $wizard_grid->close_body()->end();
            ?>

            <form method="post" action="" class="mjb-setup-form">
                <?php wp_nonce_field('mjb_create_setup_pages_nonce'); ?>
                <input type="hidden" name="mjb_action" value="create_setup_pages">
                <p>
                    <button type="submit" class="mjb-btn mjb-btn-primary">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('wand-sparkles', 16);
                        ?>
                        <?php esc_html_e('Create Missing Pages', 'modern-job-board'); ?>
                    </button>
                </p>
            </form>

            <p class="description">
                <?php
                printf(
                    /* translators: %s: Settings → Permalinks admin URL */
                    esc_html__('After creating pages, visit %s and click Save to refresh rewrite rules for pretty job search URLs.', 'modern-job-board'),
                    '<a href="' . esc_url(admin_url('options-permalink.php')) . '">' . esc_html__('Settings → Permalinks', 'modern-job-board') . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}