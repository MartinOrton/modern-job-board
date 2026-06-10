<?php
/**
 * Modern Job Board Custom Fields
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Custom_Fields
{
    private $option_name = 'mjb_custom_fields_config';

    /**
     * Initialize.
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_init', array($this, 'handle_save_logic'));
    }

    /**
     * Register Admin Page.
     */
    public function register_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Custom Fields', 'modern-job-board'),
            __('Custom Fields', 'modern-job-board'),
            'manage_options',
            'mjb-custom-fields',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Get Fields.
     */
    public function get_fields($location = 'all')
    {
        $fields = get_option($this->option_name, array());
        if ($location === 'all') {
            return $fields;
        }
        $filtered = array();
        foreach ($fields as $field) {
            if (isset($field['location']) && $field['location'] === $location) {
                $filtered[] = $field;
            }
        }
        return $filtered;
    }

    /**
     * Handle Save Logic.
     */
    public function handle_save_logic()
    {
        if (isset($_POST['mjb_save_custom_field']) && check_admin_referer('mjb_save_custom_field_nonce')) {
            $fields = $this->get_fields();

            $new_field = array(
                'label' => sanitize_text_field($_POST['field_label']),
                'key' => sanitize_title($_POST['field_label']), // Auto-generate key from label
                'type' => sanitize_text_field($_POST['field_type']),
                'location' => sanitize_text_field($_POST['field_location']),
                'required' => isset($_POST['field_required']) ? 1 : 0,
                'options' => sanitize_textarea_field($_POST['field_options']), // For select types
            );

            // Append
            $fields[] = $new_field;
            update_option($this->option_name, $fields);

            wp_redirect(add_query_arg('message', 'saved', admin_url('edit.php?post_type=job_listing&page=mjb-custom-fields')));
            exit;
        }

        // Handle Delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_field' && isset($_GET['index']) && check_admin_referer('delete_field_' . $_GET['index'])) {
            $fields = $this->get_fields();
            $index = intval($_GET['index']);
            if (isset($fields[$index])) {
                unset($fields[$index]);
                update_option($this->option_name, array_values($fields)); // Re-index
            }
            wp_redirect(add_query_arg('message', 'deleted', admin_url('edit.php?post_type=job_listing&page=mjb-custom-fields')));
            exit;
        }
    }

    /**
     * Render Admin Page.
     */
    public function render_admin_page()
    {
        $fields = $this->get_fields();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Custom Fields Builder', 'modern-job-board'); ?></h1>

            <div style="display:flex; gap:20px;">
                <!-- List -->
                <div style="flex:1;">
                    <?php
                    $field_headers = array(
                        __('Label', 'modern-job-board'),
                        __('Key', 'modern-job-board'),
                        __('Type', 'modern-job-board'),
                        __('Location', 'modern-job-board'),
                        __('Actions', 'modern-job-board'),
                    );
                    $fields_grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--admin', count($field_headers));
                    $fields_grid->render_header($field_headers)->open_body();
                    if (empty($fields)) {
                        $fields_grid->render_empty_row(__('No custom fields defined.', 'modern-job-board'));
                    } else {
                        foreach ($fields as $index => $field) {
                            $delete_url = wp_nonce_url(add_query_arg(array('action' => 'delete_field', 'index' => $index)), 'delete_field_' . $index);
                            $actions_html = '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this field?', 'modern-job-board')) . '\');" class="button button-small delete">' . esc_html__('Delete', 'modern-job-board') . '</a>';
                            $fields_grid->open_row()
                                ->render_cell(esc_html($field['label']), $field_headers[0])
                                ->render_cell(esc_html($field['key']), $field_headers[1])
                                ->render_cell(esc_html($field['type']), $field_headers[2])
                                ->render_cell(esc_html(ucfirst($field['location'])), $field_headers[3])
                                ->render_cell($actions_html, $field_headers[4])
                                ->close_row();
                        }
                    }
                    $fields_grid->close_body()->end();
                    ?>
                </div>

                <!-- Add Form -->
                <div
                    style="flex:0 0 300px; background:#fff; padding:15px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h3><?php esc_html_e('Add New Field', 'modern-job-board'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_save_custom_field_nonce'); ?>
                        <input type="hidden" name="mjb_save_custom_field" value="1">

                        <p>
                            <label><?php esc_html_e('Label', 'modern-job-board'); ?></label>
                            <input type="text" name="field_label" class="widefat" required>
                        </p>
                        <p>
                            <label><?php esc_html_e('Type', 'modern-job-board'); ?></label>
                            <select name="field_type" class="widefat">
                                <option value="text"><?php esc_html_e('Text', 'modern-job-board'); ?></option>
                                <option value="textarea"><?php esc_html_e('Textarea', 'modern-job-board'); ?></option>
                                <option value="number"><?php esc_html_e('Number', 'modern-job-board'); ?></option>
                                <option value="select"><?php esc_html_e('Select', 'modern-job-board'); ?></option>
                                <option value="checkbox"><?php esc_html_e('Checkbox', 'modern-job-board'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php esc_html_e('Location', 'modern-job-board'); ?></label>
                            <select name="field_location" class="widefat">
                                <option value="job"><?php esc_html_e('Job Listing', 'modern-job-board'); ?></option>
                                <option value="application"><?php esc_html_e('Application', 'modern-job-board'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php esc_html_e('Options (for Select)', 'modern-job-board'); ?></label>
                            <textarea name="field_options" class="widefat"
                                placeholder="Option 1, Option 2, Option 3"></textarea>
                            <small><?php esc_html_e('Comma separated', 'modern-job-board'); ?></small>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="field_required" value="1">
                                <?php esc_html_e('Required?', 'modern-job-board'); ?>
                            </label>
                        </p>
                        <p>
                            <input type="submit" class="button button-primary"
                                value="<?php esc_attr_e('Add Field', 'modern-job-board'); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
