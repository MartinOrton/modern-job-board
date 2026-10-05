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
        add_action('wp_ajax_mjb_admin_custom_field', array($this, 'ajax_save'));
    }

    /**
     * Save or delete a custom field without leaving the admin screen.
     */
    public function ajax_save()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to manage custom fields.', 'modern-job-board'),
            ));
        }

        $this->handle_save_logic();

        wp_send_json_error(array(
            'message' => __('That action could not be completed.', 'modern-job-board'),
        ));
    }

    /**
     * @return bool
     */
    private function skip_ajax_init()
    {
        return wp_doing_ajax() && !doing_action('wp_ajax_mjb_admin_custom_field');
    }

    /**
     * @param string $message
     * @param bool   $is_error
     * @param array  $args
     */
    private function finish_custom_field($message, $is_error, $args)
    {
        if (wp_doing_ajax()) {
            $payload = array(
                'message' => $message,
                'tab' => 'custom-fields',
            );
            if ($is_error) {
                wp_send_json_error($payload);
            }
            wp_send_json_success($payload);
        }

        wp_safe_redirect(add_query_arg(
            array_merge(
                array(
                    'page' => 'modern-job-board',
                    'tab' => 'custom-fields',
                ),
                $args
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Register Admin Page.
     */
    public function register_admin_page()
    {
        // Custom Fields is rendered inside the tabbed admin shell.
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
        if ($this->skip_ajax_init()) {
            return;
        }

        if (isset($_POST['mjb_delete_custom_field'], $_POST['index'])) {
            $index = intval($_POST['index']);
            if (!current_user_can('manage_options') || !check_admin_referer('delete_field_' . $index)) {
                $this->finish_custom_field(
                    __('You do not have permission to manage custom fields.', 'modern-job-board'),
                    true,
                    array()
                );
            }

            $fields = $this->get_fields();
            if (isset($fields[$index])) {
                unset($fields[$index]);
                update_option($this->option_name, array_values($fields));
            }

            $this->finish_custom_field(
                __('Custom field deleted.', 'modern-job-board'),
                false,
                array('message' => 'deleted')
            );
        }

        if (isset($_POST['mjb_save_custom_field']) && check_admin_referer('mjb_save_custom_field_nonce')) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to manage custom fields.', 'modern-job-board'), 403);
            }

            $fields = $this->get_fields();

            $new_field = array(
                'label' => sanitize_text_field(wp_unslash($_POST['field_label'])),
                'key' => sanitize_title(wp_unslash($_POST['field_label'])), // Auto-generate key from label
                'type' => sanitize_text_field(wp_unslash($_POST['field_type'])),
                'location' => sanitize_text_field(wp_unslash($_POST['field_location'])),
                'required' => isset($_POST['field_required']) ? 1 : 0,
                'options' => sanitize_textarea_field(wp_unslash($_POST['field_options'])), // For select types
            );

            // Append
            $fields[] = $new_field;
            update_option($this->option_name, $fields);

            $this->finish_custom_field(
                __('Custom field saved.', 'modern-job-board'),
                false,
                array('message' => 'saved')
            );
        }

        // Handle Delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_field' && isset($_GET['index']) && check_admin_referer('delete_field_' . $_GET['index'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to manage custom fields.', 'modern-job-board'), 403);
            }

            $fields = $this->get_fields();
            $index = intval($_GET['index']);
            if (isset($fields[$index])) {
                unset($fields[$index]);
                update_option($this->option_name, array_values($fields)); // Re-index
            }
            wp_safe_redirect(add_query_arg(
                array(
                    'page' => 'modern-job-board',
                    'tab' => 'custom-fields',
                    'message' => 'deleted',
                ),
                admin_url('admin.php')
            ));
            exit;
        }
    }

    /**
     * Render tab content.
     */
    public function render_admin_content()
    {
        $fields = $this->get_fields();

        if (isset($_GET['message']) && $_GET['message'] === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Custom field saved.', 'modern-job-board') . '</p></div>';
        }
        if (isset($_GET['message']) && $_GET['message'] === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Custom field deleted.', 'modern-job-board') . '</p></div>';
        }
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--custom-fields">
            <h2 class="mjb-section-title"><?php esc_html_e('Custom Fields Builder', 'modern-job-board'); ?></h2>

            <div class="mjb-custom-fields-layout">
                <!-- List -->
                <div class="mjb-custom-fields-list">
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
                            $delete_url = wp_nonce_url(add_query_arg(array(
                                'page' => 'modern-job-board',
                                'tab' => 'custom-fields',
                                'action' => 'delete_field',
                                'index' => $index,
                            ), admin_url('admin.php')), 'delete_field_' . $index);
                            $actions_html = '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this field?', 'modern-job-board')) . '\');" class="mjb-btn mjb-btn-outline mjb-btn--sm delete">' . esc_html__('Delete', 'modern-job-board') . '</a>';
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
                <div class="mjb-custom-fields-panel">
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
                            <button type="submit" class="mjb-btn mjb-btn-primary">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                echo MJB_Icons::render('plus', 16);
                                ?>
                                <?php esc_html_e('Add Field', 'modern-job-board'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
