<?php
/**
 * Modern Job Board Tools (Import/Export)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Tools
{
    /**
     * Initialize Tools.
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_init', array($this, 'handle_export_jobs'));
        add_action('admin_init', array($this, 'handle_export_applications'));
        add_action('admin_init', array($this, 'handle_import_jobs'));
        add_action('admin_init', array($this, 'handle_import_jobs_xml'));
        add_action('admin_init', array($this, 'handle_import_jobs_xml_url'));
    }

    /**
     * Register Admin Page.
     */
    public function register_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Tools', 'modern-job-board'),
            __('Tools', 'modern-job-board'),
            'manage_options',
            'mjb-tools',
            array($this, 'render_tools_page')
        );
    }

    /**
     * Render Tools Page.
     */
    public function render_tools_page()
    {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'export';
        ?>
        <div class="wrap">
            <h1><?php _e('Modern Job Board Tools', 'modern-job-board'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('edit.php?post_type=job_listing&page=mjb-tools&tab=export'); ?>"
                    class="nav-tab <?php echo $active_tab == 'export' ? 'nav-tab-active' : ''; ?>"><?php _e('Export', 'modern-job-board'); ?></a>
                <a href="<?php echo admin_url('edit.php?post_type=job_listing&page=mjb-tools&tab=import'); ?>"
                    class="nav-tab <?php echo $active_tab == 'import' ? 'nav-tab-active' : ''; ?>"><?php _e('Import', 'modern-job-board'); ?></a>
            </h2>

            <!-- Export Tab -->
            <?php if ($active_tab == 'export'): ?>
                <div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
                    <h2><?php _e('Export Data', 'modern-job-board'); ?></h2>
                    <p><?php _e('Download your data in CSV format.', 'modern-job-board'); ?></p>

                    <hr>

                    <h3><?php _e('Job Listings', 'modern-job-board'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_export_jobs_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="export_jobs">
                        <p>
                            <input type="submit" class="button button-primary"
                                value="<?php _e('Export All Jobs to CSV', 'modern-job-board'); ?>">
                        </p>
                    </form>

                    <hr>

                    <h3><?php _e('Applications', 'modern-job-board'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_export_applications_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="export_applications">
                        <p>
                            <input type="submit" class="button button-secondary"
                                value="<?php _e('Export All Applications to CSV', 'modern-job-board'); ?>">
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Import Tab -->
            <?php if ($active_tab == 'import'): ?>
                <div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
                    <h2><?php _e('Import Jobs', 'modern-job-board'); ?></h2>
                    <p><?php _e('Upload a CSV file to bulk import job listings.', 'modern-job-board'); ?></p>
                    <p><strong><?php _e('Required Columns:', 'modern-job-board'); ?></strong>
                        <code>Title, Description, Location, Type, Company</code></p>

                    <?php
                    if (isset($_GET['imported'])) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d jobs imported successfully!', 'modern-job-board'), intval($_GET['imported'])) . '</p></div>';
                    }
                    if (isset($_GET['xml_imported'])) {
                        $skipped = intval($_GET['xml_skipped'] ?? 0);
                        echo '<div class="notice notice-success is-dismissible"><p>' .
                            esc_html(sprintf(
                                __('%1$d jobs imported from XML. %2$d items skipped (duplicates or invalid rows).', 'modern-job-board'),
                                intval($_GET['xml_imported']),
                                $skipped
                            )) .
                            '</p></div>';
                    }
                    if (isset($_GET['xml_error'])) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['xml_error']))) . '</p></div>';
                    }
                    ?>

                    <h3><?php _e('CSV Import', 'modern-job-board'); ?></h3>
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field('mjb_import_jobs_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="import_jobs">
                        <p>
                            <input type="file" name="import_file" accept=".csv" required>
                        </p>
                        <p>
                            <input type="submit" class="button button-primary"
                                value="<?php _e('Import Jobs from CSV', 'modern-job-board'); ?>">
                        </p>
                    </form>

                    <hr>

                    <h3><?php _e('XML / RSS Import', 'modern-job-board'); ?></h3>
                    <p><?php _e('Import jobs from an MJB XML feed or compatible RSS feed. Duplicate items (matched by GUID or link) are skipped.', 'modern-job-board'); ?></p>
                    <p>
                        <strong><?php _e('Supported fields:', 'modern-job-board'); ?></strong>
                        <code>title</code>, <code>description</code>, <code>content:encoded</code>,
                        <code>mjb:company</code>, <code>mjb:location</code>, <code>mjb:jobType</code>, <code>mjb:featured</code>
                    </p>

                    <form method="post" action="" enctype="multipart/form-data" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('mjb_import_jobs_xml_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="import_jobs_xml">
                        <p>
                            <input type="file" name="import_xml_file" accept=".xml,.rss,application/xml,text/xml" required>
                        </p>
                        <p>
                            <input type="submit" class="button button-secondary"
                                value="<?php _e('Import Jobs from XML File', 'modern-job-board'); ?>">
                        </p>
                    </form>

                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_import_jobs_xml_url_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="import_jobs_xml_url">
                        <p>
                            <label for="mjb_import_feed_url"><strong><?php _e('Remote Feed URL', 'modern-job-board'); ?></strong></label><br>
                            <input type="url" class="regular-text" id="mjb_import_feed_url" name="import_feed_url"
                                placeholder="https://example.com/feed/job-listings/" required>
                        </p>
                        <p>
                            <input type="submit" class="button button-secondary"
                                value="<?php _e('Import Jobs from Feed URL', 'modern-job-board'); ?>">
                        </p>
                    </form>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Handle Export Jobs.
     */
    public function handle_export_jobs()
    {
        if (isset($_POST['mjb_action']) && $_POST['mjb_action'] == 'export_jobs' && check_admin_referer('mjb_export_jobs_nonce') && current_user_can('manage_options')) {
            $filename = 'jobs-export-' . date('Y-m-d') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // Header
            fputcsv($output, array('ID', 'Title', 'Date', 'Status', 'Author', 'Location', 'Type', 'Category', 'Company'));

            $args = array(
                'post_type' => 'job_listing',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'pending', 'draft', 'expired'),
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();

                    // Taxonomies
                    $locations = wp_get_post_terms($post_id, 'job_location', array('fields' => 'names'));
                    $types = wp_get_post_terms($post_id, 'job_type', array('fields' => 'names'));
                    $categories = wp_get_post_terms($post_id, 'job_category', array('fields' => 'names'));

                    // Company
                    $company = '';
                    $company_id = get_post_meta($post_id, '_company_id', true);
                    if ($company_id) {
                        $company = get_the_title($company_id);
                    }

                    fputcsv($output, array(
                        $post_id,
                        get_the_title(),
                        get_the_date('Y-m-d H:i:s'),
                        get_post_status(),
                        get_the_author_meta('user_login'),
                        implode(', ', $locations),
                        implode(', ', $types),
                        implode(', ', $categories),
                        $company
                    ));
                }
            }

            fclose($output);
            exit;
        }
    }

    /**
     * Handle Export Applications.
     */
    public function handle_export_applications()
    {
        if (isset($_POST['mjb_action']) && $_POST['mjb_action'] == 'export_applications' && check_admin_referer('mjb_export_applications_nonce') && current_user_can('manage_options')) {
            $filename = 'applications-export-' . date('Y-m-d') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // Header
            fputcsv($output, array('ID', 'Job ID', 'Job Title', 'Date', 'Candidate Name', 'Candidate Email', 'Message', 'Admin Link'));

            $args = array(
                'post_type' => 'job_application',
                'posts_per_page' => -1,
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $app_id = get_the_ID();
                    $job_id = get_post_meta($app_id, '_job_applied_for', true);
                    $job_title = $job_id ? get_the_title($job_id) : 'N/A';

                    fputcsv($output, array(
                        $app_id,
                        $job_id,
                        $job_title,
                        get_the_date('Y-m-d H:i:s'),
                        get_post_meta($app_id, '_candidate_name', true),
                        get_post_meta($app_id, '_candidate_email', true),
                        wp_strip_all_tags(get_the_content()), // Message often in content
                        get_edit_post_link($app_id, 'raw')
                    ));
                }
            }

            fclose($output);
            exit;
        }
    }

    /**
     * Handle Import Jobs.
     */
    public function handle_import_jobs()
    {
        if (isset($_POST['mjb_action']) && $_POST['mjb_action'] == 'import_jobs' && check_admin_referer('mjb_import_jobs_nonce') && current_user_can('manage_options')) {
            if (!empty($_FILES['import_file']['tmp_name'])) {
                $file = $_FILES['import_file']['tmp_name'];

                $handle = fopen($file, 'r');
                if ($handle === false) {
                    return;
                }

                $header = fgetcsv($handle); // Skip header row
                $count = 0;

                // Expected Headers (rough check, not strict for this v1)
                // Title, Description, Location, Type, Company

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 2) {
                        continue;
                    }

                    $post_id = MJB_Job_Importer::import_job(array(
                        'title' => $row[0] ?? '',
                        'description' => $row[1] ?? '',
                        'location' => $row[2] ?? '',
                        'type' => $row[3] ?? '',
                        'company' => $row[4] ?? '',
                    ));

                    if ($post_id) {
                        $count++;
                    }
                }

                fclose($handle);

                wp_safe_redirect(admin_url('edit.php?post_type=job_listing&page=mjb-tools&tab=import&imported=' . $count));
                exit;
            }
        }
    }

    /**
     * Handle XML file import.
     */
    public function handle_import_jobs_xml()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'import_jobs_xml') {
            return;
        }

        if (!check_admin_referer('mjb_import_jobs_xml_nonce') || !current_user_can('manage_options')) {
            return;
        }

        if (empty($_FILES['import_xml_file']['tmp_name'])) {
            return;
        }

        $xml = file_get_contents($_FILES['import_xml_file']['tmp_name']);
        $this->redirect_after_xml_import($xml);
    }

    /**
     * Handle remote XML feed URL import.
     */
    public function handle_import_jobs_xml_url()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'import_jobs_xml_url') {
            return;
        }

        if (!check_admin_referer('mjb_import_jobs_xml_url_nonce') || !current_user_can('manage_options')) {
            return;
        }

        $url = isset($_POST['import_feed_url']) ? esc_url_raw(wp_unslash($_POST['import_feed_url'])) : '';
        if ($url === '') {
            return;
        }

        $result = MJB_Xml_Importer::import_from_url($url);
        if (is_wp_error($result)) {
            $this->redirect_with_xml_error($result->get_error_message());
        }

        $this->redirect_with_xml_result($result);
    }

    /**
     * Parse XML string and redirect with import results.
     *
     * @param string $xml
     */
    private function redirect_after_xml_import($xml)
    {
        $parsed = MJB_Xml_Importer::parse_xml_string($xml);
        if (is_wp_error($parsed)) {
            $this->redirect_with_xml_error($parsed->get_error_message());
        }

        $result = MJB_Xml_Importer::import_jobs($parsed);
        $this->redirect_with_xml_result($result);
    }

    /**
     * Redirect to import tab with XML success counts.
     *
     * @param array $result
     */
    private function redirect_with_xml_result($result)
    {
        wp_safe_redirect(admin_url('edit.php?post_type=job_listing&page=mjb-tools&tab=import&xml_imported=' . intval($result['imported']) . '&xml_skipped=' . intval($result['skipped'])));
        exit;
    }

    /**
     * Redirect to import tab with XML error message.
     *
     * @param string $message
     */
    private function redirect_with_xml_error($message)
    {
        wp_safe_redirect(admin_url('edit.php?post_type=job_listing&page=mjb-tools&tab=import&xml_error=' . rawurlencode($message)));
        exit;
    }
}
