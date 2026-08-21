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
        add_action('admin_init', array($this, 'handle_schedule_feed_save'));
        add_action('admin_init', array($this, 'handle_schedule_feed_delete'));
        add_action('admin_init', array($this, 'handle_schedule_feed_run'));
    }

    /**
     * Register Admin Page.
     */
    public function register_admin_page()
    {
        // Tools is rendered inside the tabbed admin shell.
    }

    /**
     * Build admin shell URL for the tools tab.
     *
     * @param string $tools_tab
     * @param array  $args
     * @return string
     */
    private function get_tools_tab_url($tools_tab = 'export', $args = array())
    {
        $query = array_merge(array(
            'page' => 'modern-job-board',
            'tab' => 'tools',
            'tools_tab' => $tools_tab,
        ), $args);

        return add_query_arg($query, admin_url('admin.php'));
    }

    /**
     * Render tools tab content.
     *
     * @param string $active_tab
     */
    public function render_tools_content($active_tab = 'export')
    {
        $active_tab = in_array($active_tab, array('export', 'import', 'schedules'), true) ? $active_tab : 'export';
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--tools">
            <h2 class="mjb-section-title"><?php esc_html_e('Tools', 'modern-job-board'); ?></h2>

            <div class="mjb-tools-subtabs" role="tablist" aria-label="<?php esc_attr_e('Import and export', 'modern-job-board'); ?>">
                <button type="button" class="mjb-tools-subtab<?php echo $active_tab === 'export' ? ' is-active' : ''; ?>" data-tools-tab="export" role="tab" aria-selected="<?php echo $active_tab === 'export' ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Export', 'modern-job-board'); ?>
                </button>
                <button type="button" class="mjb-tools-subtab<?php echo $active_tab === 'import' ? ' is-active' : ''; ?>" data-tools-tab="import" role="tab" aria-selected="<?php echo $active_tab === 'import' ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Import', 'modern-job-board'); ?>
                </button>
                <button type="button" class="mjb-tools-subtab<?php echo $active_tab === 'schedules' ? ' is-active' : ''; ?>" data-tools-tab="schedules" role="tab" aria-selected="<?php echo $active_tab === 'schedules' ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Schedules', 'modern-job-board'); ?>
                </button>
            </div>

            <!-- Export Tab -->
            <?php if ($active_tab == 'export'): ?>
                <div class="mjb-tools-card">
                    <h2><?php esc_html_e('Export Data', 'modern-job-board'); ?></h2>
                    <p><?php esc_html_e('Download your data in CSV format.', 'modern-job-board'); ?></p>

                    <hr>

                    <h3><?php esc_html_e('Job Listings', 'modern-job-board'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_export_jobs_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="export_jobs">
                        <p>
                            <button type="submit" class="mjb-btn mjb-btn-primary">
                                <?php esc_html_e('Export All Jobs to CSV', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>

                    <hr>

                    <h3><?php esc_html_e('Applications', 'modern-job-board'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_export_applications_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="export_applications">
                        <p>
                            <button type="submit" class="mjb-btn mjb-btn-outline">
                                <?php esc_html_e('Export All Applications to CSV', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>

                    <hr>

                    <h3><?php esc_html_e('Full board XML backup', 'modern-job-board'); ?></h3>
                    <p><?php esc_html_e('Export jobs, companies, applications, resumes, and taxonomies as one XML file for migration or disaster recovery.', 'modern-job-board'); ?></p>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_export_board_xml_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="export_board_xml">
                        <p>
                            <button type="submit" class="mjb-btn mjb-btn-outline">
                                <?php esc_html_e('Download XML backup', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Import Tab -->
            <?php if ($active_tab == 'import'): ?>
                <div class="mjb-tools-card">
                    <h2><?php esc_html_e('Import Jobs', 'modern-job-board'); ?></h2>
                    <p><?php esc_html_e('Upload a CSV file to bulk import job listings.', 'modern-job-board'); ?></p>
                    <p><strong><?php esc_html_e('Required Columns:', 'modern-job-board'); ?></strong>
                        <code>Title, Description, Location, Type, Company</code></p>

                    <?php
                    if (isset($_GET['imported'])) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d jobs imported successfully!', 'modern-job-board'), intval($_GET['imported']))) . '</p></div>';
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

                    <h3><?php esc_html_e('CSV Import', 'modern-job-board'); ?></h3>
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field('mjb_import_jobs_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="import_jobs">
                        <p>
                            <input type="file" name="import_file" accept=".csv" required>
                        </p>
                        <p>
                            <button type="submit" class="mjb-btn mjb-btn-primary">
                                <?php esc_html_e('Import Jobs from CSV', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>

                    <hr>

                    <h3><?php esc_html_e('XML / RSS Import', 'modern-job-board'); ?></h3>
                    <p><?php esc_html_e('Import jobs from an MJB XML feed or compatible RSS feed. Duplicate items (matched by GUID or link) are skipped.', 'modern-job-board'); ?></p>
                    <p>
                        <strong><?php esc_html_e('Supported fields:', 'modern-job-board'); ?></strong>
                        <code>title</code>, <code>description</code>, <code>content:encoded</code>,
                        <code>mjb:company</code>, <code>mjb:location</code>, <code>mjb:jobType</code>, <code>mjb:featured</code>
                    </p>

                    <form method="post" action="" enctype="multipart/form-data" class="mjb-tools-form-spaced">
                        <?php wp_nonce_field('mjb_import_jobs_xml_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="import_jobs_xml">
                        <p>
                            <input type="file" name="import_xml_file" accept=".xml,.rss,application/xml,text/xml" required>
                        </p>
                        <p>
                            <button type="submit" class="mjb-btn mjb-btn-outline">
                                <?php esc_html_e('Import Jobs from XML File', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>

                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_import_jobs_xml_url_nonce'); ?>
                        <input type="hidden" name="mjb_action" value="import_jobs_xml_url">
                        <p>
                            <label for="mjb_import_feed_url"><strong><?php esc_html_e('Remote Feed URL', 'modern-job-board'); ?></strong></label><br>
                            <input type="url" class="regular-text" id="mjb_import_feed_url" name="import_feed_url"
                                placeholder="https://example.com/feed/job-listings/" required>
                        </p>
                        <p>
                            <button type="submit" class="mjb-btn mjb-btn-outline">
                                <?php esc_html_e('Import Jobs from Feed URL', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($active_tab === 'schedules'): ?>
                <?php $this->render_schedules_content(); ?>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Render scheduled import feeds UI.
     */
    private function render_schedules_content()
    {
        $edit_id = isset($_GET['schedule_edit']) ? sanitize_key(wp_unslash($_GET['schedule_edit'])) : '';
        $editing = $edit_id !== '' ? MJB_Import_Scheduler::get_feed($edit_id) : null;
        $feeds = MJB_Import_Scheduler::get_feeds();
        ?>
        <div class="mjb-tools-card">
            <h2><?php esc_html_e('Scheduled XML Backfill', 'modern-job-board'); ?></h2>
            <p><?php esc_html_e('Automatically import jobs from remote XML/RSS feeds on a daily or weekly schedule.', 'modern-job-board'); ?></p>

            <?php
            if (isset($_GET['schedule_saved'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Scheduled feed saved.', 'modern-job-board') . '</p></div>';
            }
            if (isset($_GET['schedule_deleted'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Scheduled feed deleted.', 'modern-job-board') . '</p></div>';
            }
            if (isset($_GET['schedule_ran'])) {
                $imported = intval($_GET['schedule_imported'] ?? 0);
                $skipped = intval($_GET['schedule_skipped'] ?? 0);
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html(sprintf(
                        __('Feed import complete. %1$d jobs imported, %2$d skipped.', 'modern-job-board'),
                        $imported,
                        $skipped
                    )) .
                    '</p></div>';
            }
            if (isset($_GET['schedule_error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['schedule_error']))) . '</p></div>';
            }
            ?>

            <h3><?php echo $editing ? esc_html__('Edit Scheduled Feed', 'modern-job-board') : esc_html__('Add Scheduled Feed', 'modern-job-board'); ?></h3>
            <form method="post" action="" class="mjb-tools-form-spaced">
                <?php wp_nonce_field('mjb_schedule_feed_save_nonce'); ?>
                <input type="hidden" name="mjb_action" value="schedule_feed_save">
                <?php if ($editing): ?>
                    <input type="hidden" name="schedule_feed_id" value="<?php echo esc_attr($editing['id']); ?>">
                <?php endif; ?>
                <p>
                    <label for="mjb_schedule_name"><strong><?php esc_html_e('Feed Name', 'modern-job-board'); ?></strong></label><br>
                    <input type="text" class="regular-text" id="mjb_schedule_name" name="schedule_name"
                        value="<?php echo esc_attr($editing['name'] ?? ''); ?>" required>
                </p>
                <p>
                    <label for="mjb_schedule_url"><strong><?php esc_html_e('Feed URL', 'modern-job-board'); ?></strong></label><br>
                    <input type="url" class="regular-text" id="mjb_schedule_url" name="schedule_url"
                        value="<?php echo esc_attr($editing['url'] ?? ''); ?>"
                        placeholder="https://example.com/feed/job-listings/" required>
                </p>
                <p>
                    <label for="mjb_schedule_interval"><strong><?php esc_html_e('Schedule', 'modern-job-board'); ?></strong></label><br>
                    <select id="mjb_schedule_interval" name="schedule_interval">
                        <option value="daily" <?php selected(($editing['schedule'] ?? 'daily'), 'daily'); ?>><?php esc_html_e('Daily', 'modern-job-board'); ?></option>
                        <option value="weekly" <?php selected(($editing['schedule'] ?? ''), 'weekly'); ?>><?php esc_html_e('Weekly', 'modern-job-board'); ?></option>
                    </select>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="schedule_enabled" value="1" <?php checked(!isset($editing['enabled']) || !empty($editing['enabled'])); ?>>
                        <?php esc_html_e('Enabled', 'modern-job-board'); ?>
                    </label>
                </p>
                <p>
                    <button type="submit" class="mjb-btn mjb-btn-primary">
                        <?php echo $editing ? esc_html__('Update Feed', 'modern-job-board') : esc_html__('Add Feed', 'modern-job-board'); ?>
                    </button>
                    <?php if ($editing): ?>
                        <a class="mjb-btn mjb-btn-outline" href="<?php echo esc_url($this->get_tools_tab_url('schedules')); ?>">
                            <?php esc_html_e('Cancel', 'modern-job-board'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>

            <hr>

            <h3><?php esc_html_e('Scheduled Feeds', 'modern-job-board'); ?></h3>
            <?php if (empty($feeds)): ?>
                <p><?php esc_html_e('No scheduled feeds yet. Add a remote XML feed above to start automatic backfill.', 'modern-job-board'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('URL', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('Schedule', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('Status', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('Last Run', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('Last Result', 'modern-job-board'); ?></th>
                            <th><?php esc_html_e('Actions', 'modern-job-board'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeds as $feed): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($feed['name'] ?? ''); ?></strong><br>
                                    <span class="description"><?php echo !empty($feed['enabled']) ? esc_html__('Enabled', 'modern-job-board') : esc_html__('Disabled', 'modern-job-board'); ?></span>
                                </td>
                                <td><code><?php echo esc_html($feed['url'] ?? ''); ?></code></td>
                                <td><?php echo ($feed['schedule'] ?? 'daily') === 'weekly' ? esc_html__('Weekly', 'modern-job-board') : esc_html__('Daily', 'modern-job-board'); ?></td>
                                <td>
                                    <?php
                                    $status = $feed['last_status'] ?? '';
                                    if ($status === 'success') {
                                        esc_html_e('Success', 'modern-job-board');
                                    } elseif ($status === 'error') {
                                        esc_html_e('Error', 'modern-job-board');
                                    } else {
                                        esc_html_e('Never run', 'modern-job-board');
                                    }
                                    ?>
                                </td>
                                <td><?php echo !empty($feed['last_run']) ? esc_html($feed['last_run']) : '—'; ?></td>
                                <td>
                                    <?php if (($feed['last_status'] ?? '') === 'success'): ?>
                                        <?php
                                        echo esc_html(sprintf(
                                            __('%1$d imported, %2$d skipped', 'modern-job-board'),
                                            intval($feed['last_imported'] ?? 0),
                                            intval($feed['last_skipped'] ?? 0)
                                        ));
                                        ?>
                                    <?php elseif (($feed['last_status'] ?? '') === 'error'): ?>
                                        <span class="description"><?php echo esc_html($feed['last_error'] ?? ''); ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="mjb-btn mjb-btn-outline" href="<?php echo esc_url($this->get_tools_tab_url('schedules', array('schedule_edit' => $feed['id']))); ?>">
                                        <?php esc_html_e('Edit', 'modern-job-board'); ?>
                                    </a>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field('mjb_schedule_feed_run_nonce'); ?>
                                        <input type="hidden" name="mjb_action" value="schedule_feed_run">
                                        <input type="hidden" name="schedule_feed_id" value="<?php echo esc_attr($feed['id']); ?>">
                                        <button type="submit" class="mjb-btn mjb-btn-outline"><?php esc_html_e('Run Now', 'modern-job-board'); ?></button>
                                    </form>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Delete this scheduled feed?', 'modern-job-board')); ?>');">
                                        <?php wp_nonce_field('mjb_schedule_feed_delete_nonce'); ?>
                                        <input type="hidden" name="mjb_action" value="schedule_feed_delete">
                                        <input type="hidden" name="schedule_feed_id" value="<?php echo esc_attr($feed['id']); ?>">
                                        <button type="submit" class="mjb-btn mjb-btn-outline"><?php esc_html_e('Delete', 'modern-job-board'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                    $location_terms = wp_get_post_terms($post_id, 'job_location');
                    $locations = array();
                    if (!empty($location_terms) && !is_wp_error($location_terms)) {
                        foreach ($location_terms as $location_term) {
                            $locations[] = MJB_Location::format_location_term($location_term);
                        }
                    }
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

                wp_safe_redirect($this->get_tools_tab_url('import', array('imported' => $count)));
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
        wp_safe_redirect($this->get_tools_tab_url('import', array(
            'xml_imported' => intval($result['imported']),
            'xml_skipped' => intval($result['skipped']),
        )));
        exit;
    }

    /**
     * Redirect to import tab with XML error message.
     *
     * @param string $message
     */
    private function redirect_with_xml_error($message)
    {
        wp_safe_redirect($this->get_tools_tab_url('import', array('xml_error' => $message)));
        exit;
    }

    /**
     * Handle scheduled feed create/update.
     */
    public function handle_schedule_feed_save()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'schedule_feed_save') {
            return;
        }

        if (!check_admin_referer('mjb_schedule_feed_save_nonce') || !current_user_can('manage_options')) {
            return;
        }

        $result = MJB_Import_Scheduler::save_feed(array(
            'id' => isset($_POST['schedule_feed_id']) ? sanitize_key(wp_unslash($_POST['schedule_feed_id'])) : '',
            'name' => isset($_POST['schedule_name']) ? sanitize_text_field(wp_unslash($_POST['schedule_name'])) : '',
            'url' => isset($_POST['schedule_url']) ? esc_url_raw(wp_unslash($_POST['schedule_url'])) : '',
            'schedule' => isset($_POST['schedule_interval']) ? sanitize_key(wp_unslash($_POST['schedule_interval'])) : 'daily',
            'enabled' => !empty($_POST['schedule_enabled']),
            'author_id' => get_current_user_id(),
        ));

        if (is_wp_error($result)) {
            wp_safe_redirect($this->get_tools_tab_url('schedules', array('schedule_error' => $result->get_error_message())));
            exit;
        }

        wp_safe_redirect($this->get_tools_tab_url('schedules', array('schedule_saved' => 1)));
        exit;
    }

    /**
     * Handle scheduled feed deletion.
     */
    public function handle_schedule_feed_delete()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'schedule_feed_delete') {
            return;
        }

        if (!check_admin_referer('mjb_schedule_feed_delete_nonce') || !current_user_can('manage_options')) {
            return;
        }

        $feed_id = isset($_POST['schedule_feed_id']) ? sanitize_key(wp_unslash($_POST['schedule_feed_id'])) : '';
        if ($feed_id !== '') {
            MJB_Import_Scheduler::delete_feed($feed_id);
        }

        wp_safe_redirect($this->get_tools_tab_url('schedules', array('schedule_deleted' => 1)));
        exit;
    }

    /**
     * Handle manual scheduled feed run.
     */
    public function handle_schedule_feed_run()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'schedule_feed_run') {
            return;
        }

        if (!check_admin_referer('mjb_schedule_feed_run_nonce') || !current_user_can('manage_options')) {
            return;
        }

        $feed_id = isset($_POST['schedule_feed_id']) ? sanitize_key(wp_unslash($_POST['schedule_feed_id'])) : '';
        if ($feed_id === '') {
            return;
        }

        $result = MJB_Import_Scheduler::run_feed($feed_id, true);
        if (is_wp_error($result)) {
            wp_safe_redirect($this->get_tools_tab_url('schedules', array('schedule_error' => $result->get_error_message())));
            exit;
        }

        wp_safe_redirect($this->get_tools_tab_url('schedules', array(
            'schedule_ran' => 1,
            'schedule_imported' => intval($result['imported']),
            'schedule_skipped' => intval($result['skipped']),
        )));
        exit;
    }
}
