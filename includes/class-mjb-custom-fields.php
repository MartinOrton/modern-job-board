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
            <h1><?php _e('Custom Fields Builder', 'modern-job-board'); ?></h1>

            <div style="display:flex; gap:20px;">
                <!-- List -->
                <div style="flex:1;">
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Label', 'modern-job-board'); ?></th>
                                <th><?php _e('Key', 'modern-job-board'); ?></th>
                                <th><?php _e('Type', 'modern-job-board'); ?></th>
                                <th><?php _e('Location', 'modern-job-board'); ?></th>
                                <th><?php _e('Actions', 'modern-job-board'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fields)): ?>
                                <tr>
                                    <td colspan="5"><?php _e('No custom fields defined.', 'modern-job-board'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fields as $index => $field): ?>
                                    <tr>
                                        <td><?php echo esc_html($field['label']); ?></td>
                                        <td><?php echo esc_html($field['key']); ?></td>
                                        <td><?php echo esc_html($field['type']); ?></td>
                                        <td><?php echo esc_html(ucfirst($field['location'])); ?></td>
                                        <td>
                                            <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete_field', 'index' => $index)), 'delete_field_' . $index); ?>"
                                                onclick="return confirm('<?php _e('Delete this field?', 'modern-job-board'); ?>');"
                                                class="button button-small delete"><?php _e('Delete', 'modern-job-board'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Form -->
                <div
                    style="flex:0 0 300px; background:#fff; padding:15px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h3><?php _e('Add New Field', 'modern-job-board'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('mjb_save_custom_field_nonce'); ?>
                        <input type="hidden" name="mjb_save_custom_field" value="1">

                        <p>
                            <label><?php _e('Label', 'modern-job-board'); ?></label>
                            <input type="text" name="field_label" class="widefat" required>
                        </p>
                        <p>
                            <label><?php _e('Type', 'modern-job-board'); ?></label>
                            <select name="field_type" class="widefat">
                                <option value="text"><?php _e('Text', 'modern-job-board'); ?></option>
                                <option value="textarea"><?php _e('Textarea', 'modern-job-board'); ?></option>
                                <option value="number"><?php _e('Number', 'modern-job-board'); ?></option>
                                <option value="select"><?php _e('Select', 'modern-job-board'); ?></option>
                                <option value="checkbox"><?php _e('Checkbox', 'modern-job-board'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php _e('Location', 'modern-job-board'); ?></label>
                            <select name="field_location" class="widefat">
                                <option value="job"><?php _e('Job Listing', 'modern-job-board'); ?></option>
                                <option value="application"><?php _e('Application', 'modern-job-board'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php _e('Options (for Select)', 'modern-job-board'); ?></label>
                            <textarea name="field_options" class="widefat"
                                placeholder="Option 1, Option 2, Option 3"></textarea>
                            <small><?php _e('Comma separated', 'modern-job-board'); ?></small>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="field_required" value="1">
                                <?php _e('Required?', 'modern-job-board'); ?>
                            </label>
                        </p>
                        <p>
                            <input type="submit" class="button button-primary"
                                value="<?php _e('Add Field', 'modern-job-board'); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
