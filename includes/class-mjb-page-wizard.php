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
            ),
            array(
                'slug' => 'post-a-job',
                'title' => __('Post a Job', 'modern-job-board'),
                'shortcode' => 'mjb_job_form',
                'option_key' => 'mjb_job_form_page_id',
            ),
            array(
                'slug' => 'employer-dashboard',
                'title' => __('Employer Dashboard', 'modern-job-board'),
                'shortcode' => 'mjb_dashboard',
                'option_key' => 'mjb_employer_dashboard_page_id',
            ),
            array(
                'slug' => 'candidate-dashboard',
                'title' => __('Candidate Dashboard', 'modern-job-board'),
                'shortcode' => 'mjb_candidate_dashboard',
                'option_key' => 'mjb_candidate_dashboard_page_id',
            ),
            array(
                'slug' => 'employer-registration',
                'title' => __('Employer Registration', 'modern-job-board'),
                'shortcode' => 'mjb_employer_registration',
                'option_key' => 'mjb_employer_registration_page_id',
            ),
            array(
                'slug' => 'candidate-registration',
                'title' => __('Candidate Registration', 'modern-job-board'),
                'shortcode' => 'mjb_candidate_registration',
                'option_key' => 'mjb_candidate_registration_page_id',
            ),
        );
    }

    /**
     * Create any missing setup pages.
     *
     * @return array{created:int, existing:int}
     */
    public static function create_missing_pages()
    {
        $created = 0;
        $existing = 0;

        foreach (self::get_page_definitions() as $definition) {
            $page_id = MJB_Page_Resolver::resolve_page_id($definition['shortcode'], $definition['option_key']);
            if ($page_id) {
                $existing++;
                continue;
            }

            $new_id = self::create_page($definition);
            if ($new_id) {
                update_option($definition['option_key'], $new_id, false);
                $created++;
            }
        }

        return array(
            'created' => $created,
            'existing' => $existing,
        );
    }

    /**
     * Create a single page from a definition.
     *
     * @param array $definition
     * @return int
     */
    public static function create_page($definition)
    {
        $shortcode = $definition['shortcode'];
        $content = '[' . $shortcode . ']';

        $page_id = wp_insert_post(array(
            'post_title' => $definition['title'],
            'post_name' => sanitize_title($definition['slug']),
            'post_content' => $content,
            'post_type' => 'page',
            'post_status' => 'publish',
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
            $page_id = MJB_Page_Resolver::resolve_page_id($definition['shortcode'], $definition['option_key']);
            $rows[] = array(
                'title' => $definition['title'],
                'shortcode' => $definition['shortcode'],
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
        add_submenu_page(
            'modern-job-board',
            __('Setup', 'modern-job-board'),
            __('Setup', 'modern-job-board'),
            'manage_options',
            'mjb-setup',
            array(__CLASS__, 'render_setup_page')
        );
    }

    /**
     * Handle create-pages form submission.
     */
    public static function handle_create_pages()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'create_setup_pages') {
            return;
        }

        if (!current_user_can('manage_options') || !check_admin_referer('mjb_create_setup_pages_nonce')) {
            return;
        }

        $result = self::create_missing_pages();
        $redirect = add_query_arg(
            array(
                'page' => 'mjb-setup',
                'mjb_pages_created' => $result['created'],
                'mjb_pages_existing' => $result['existing'],
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

        $setup_url = admin_url('admin.php?page=mjb-setup');
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
     * Render setup wizard admin page.
     */
    public static function render_setup_page()
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
        <div class="wrap">
            <h1><?php esc_html_e('Modern Job Board Setup', 'modern-job-board'); ?></h1>
            <p><?php esc_html_e('Create WordPress pages for each shortcode used by the job board frontend.', 'modern-job-board'); ?></p>

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
                    $status_html = '<span style="color:#008a20;">' . esc_html__('Ready', 'modern-job-board') . '</span>';
                } else {
                    $status_html = '<span style="color:#b32d2e;">' . esc_html__('Missing', 'modern-job-board') . '</span>';
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

            <form method="post" action="" style="margin-top: 24px;">
                <?php wp_nonce_field('mjb_create_setup_pages_nonce'); ?>
                <input type="hidden" name="mjb_action" value="create_setup_pages">
                <p>
                    <input type="submit" class="button button-primary"
                        value="<?php esc_attr_e('Create Missing Pages', 'modern-job-board'); ?>">
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