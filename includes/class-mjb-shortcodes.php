<?php
/**
 * Modern Job Board Shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Shortcodes
{

    /**
     * Initialize Shortcodes.
     */
    public function init()
    {
        add_shortcode('mjb_jobs', array($this, 'output_jobs'));
        add_shortcode('mjb_job_form', array($this, 'output_job_form'));
        add_shortcode('mjb_jobs', array($this, 'output_jobs'));
        add_shortcode('mjb_job_form', array($this, 'output_job_form'));
        // Dashboard shortcode moved to MJB_Dashboard class
    }

    /**
     * Output Job Listings.
     */
    public function output_jobs($atts)
    {
        ob_start();

        // Search Form
        ?>
        <form id="mjb-job-filter" class="mjb-job-filter" method="GET" action="">
            <div class="mjb-filter-row">
                <input type="text" name="search_keywords" placeholder="<?php _e('Keywords...', 'modern-job-board'); ?>" value="<?php echo isset($_GET['search_keywords']) ? esc_attr($_GET['search_keywords']) : ''; ?>">
                <input type="text" name="search_location" placeholder="<?php _e('Location...', 'modern-job-board'); ?>" value="<?php echo isset($_GET['search_location']) ? esc_attr($_GET['search_location']) : ''; ?>">
                
                <?php
                wp_dropdown_categories(array(
                    'taxonomy' => 'job_category',
                    'name' => 'search_category',
                    'show_option_all' => __('All Categories', 'modern-job-board'),
                    'value_field' => 'slug',
                    'selected' => isset($_GET['search_category']) ? $_GET['search_category'] : '',
                    'hierarchical' => true,
                ));

                wp_dropdown_categories(array(
                    'taxonomy' => 'job_type',
                    'name' => 'search_type',
                    'show_option_all' => __('All Job Types', 'modern-job-board'),
                    'value_field' => 'slug',
                    'selected' => isset($_GET['search_type']) ? $_GET['search_type'] : '',
                ));
                ?>
                <input type="submit" value="<?php _e('Search', 'modern-job-board'); ?>">
            </div>
            <div class="mjb-loader" style="display:none;"><?php _e('Loading...', 'modern-job-board'); ?></div>
        </form>
        <?php

        // Query jobs
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        );

        // Apply Search Filters Manual (Shortcode context)
        // Note: Ideally we'd use MJB_Search logic here, but for consistency with inline args:
        if (!empty($_GET['search_keywords'])) {
            $args['s'] = sanitize_text_field($_GET['search_keywords']);
        }

        $tax_query = array();
        if (!empty($_GET['search_location'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_location',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_location']),
                'operator' => 'IN' // Location is usually strictly matched or 'LIKE' if text? WP Tax query is strict terms. 
                // If using text input for location, user might type "New York" but slug is "new-york". 
                // Real implementation needs smart matching. For now, we assume slug or strict match for simplicity or improve MJB_Search later.
                // Let's rely on basic term match for now.
            );
        }
        if (!empty($_GET['search_category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_category']),
            );
        }
        if (!empty($_GET['search_type'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_type',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_type']),
            );
        }

        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        $jobs = new WP_Query($args);

        echo '<div id="mjb-jobs-list">';
        self::render_job_loop($jobs);
        echo '</div>';

        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render Job Loop HTML.
     * Static helper for reuse in AJAX.
     */
    public static function render_job_loop($jobs)
    {
        if ($jobs->have_posts()) {
            echo '<div class="mjb-job-list">';
            while ($jobs->have_posts()) {
                $jobs->the_post();
                
                $featured = get_post_meta(get_the_ID(), '_featured', true);
                $featured_class = $featured ? ' mjb-featured' : '';
                
                echo '<div class="mjb-job-item' . esc_attr($featured_class) . '">';
                echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
                echo '<div class="mjb-job-meta">';
                echo '<span>' . get_the_term_list(get_the_ID(), 'job_type', '', ', ') . '</span>';
                echo '<span>' . get_the_term_list(get_the_ID(), 'job_location', '', ', ') . '</span>';
                
                // Show Company if available
                $company_name = get_post_meta(get_the_ID(), '_company_name', true);
                if ($company_name) {
                    echo '<span> | ' . esc_html($company_name) . '</span>';
                }
                
                // Show Expiration Date? Optional.
                $expires = get_post_meta(get_the_ID(), '_job_expires', true);
                if ($expires) {
                     echo '<span class="mjb-meta-right">' . sprintf(__('Exp: %s', 'modern-job-board'), date_i18n(get_option('date_format'), strtotime($expires))) . '</span>';
                }

                echo '</div>'; // .mjb-job-meta
                echo '</div>'; // .mjb-job-item
            }
            echo '</div>'; // .mjb-job-list
        } else {
            echo '<p>' . __('No jobs found.', 'modern-job-board') . '</p>';
        }
    }

    /**
     * Output Job Submission Form.
     */
    /**
     * Output Job Submission Form.
     */
    public function output_job_form($atts)
    {
        ob_start();

        // Check for Edit Actions
        $job_id = 0;
        $job_title = '';
        $job_description = '';
        $company_name = '';

        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['job_id'])) {
            if (!is_user_logged_in()) {
                echo '<p>' . __('You must be logged in to edit a job.', 'modern-job-board') . '</p>';
                return ob_get_clean();
            }

            $job_id = intval($_GET['job_id']);
            $job = get_post($job_id);

            // Verify ownership
            if (!$job || $job->post_type !== 'job_listing' || intval($job->post_author) !== get_current_user_id()) {
                echo '<p>' . __('Invalid job or permission denied.', 'modern-job-board') . '</p>';
                return ob_get_clean();
            }

            $job_title = $job->post_title;
            $job_description = $job->post_content;
            $selected_company_id = get_post_meta($job_id, '_company_id', true);
            // Fallback to text name if no ID
            $company_name = get_post_meta($job_id, '_company_name', true);
        }
        
        $user_companies = $this->get_user_companies(get_current_user_id());
        $selected_company_id = isset($selected_company_id) ? $selected_company_id : '';
        // If editing and we only have text name (legacy), we might not match a company ID. That's fine.



        if (isset($_POST['mjb_submit_job']) && isset($_POST['mjb_job_nonce']) && wp_verify_nonce($_POST['mjb_job_nonce'], 'mjb_submit_job')) {
            $this->handle_job_submission($job_id);
            // If submitted, get updated values? Or redirect? For simplicity, we handle submission logic below.
        }
        ?>
        <form method="post" class="mjb-job-form" enctype="multipart/form-data">
            <?php wp_nonce_field('mjb_submit_job', 'mjb_job_nonce'); ?>
            <?php if ($job_id): ?>
                <input type="hidden" name="job_id" value="<?php echo esc_attr($job_id); ?>">
            <?php endif; ?>

            <p>
                <label for="job_title"><?php _e('Job Title', 'modern-job-board'); ?></label>
                <input type="text" name="job_title" id="job_title" value="<?php echo esc_attr($job_title); ?>" required>
            </p>
            <p>
                <label for="job_description"><?php _e('Description', 'modern-job-board'); ?></label>
                <textarea name="job_description" id="job_description"
                    required><?php echo esc_textarea($job_description); ?></textarea>
            </p>
            <p>
                <label for="company_selection"><?php _e('Company', 'modern-job-board'); ?></label>
                <select name="company_selection" id="company_selection" required onchange="toggleCompanyInput()">
                    <option value="new"><?php _e('Create New Company', 'modern-job-board'); ?></option>
                    <?php foreach ($user_companies as $company) : ?>
                        <option value="<?php echo esc_attr($company->ID); ?>" <?php selected($selected_company_id, $company->ID); ?>>
                            <?php echo esc_html($company->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p id="new-company-field" style="<?php echo $selected_company_id ? 'display:none;' : ''; ?>">
                <label for="new_company_name"><?php _e('New Company Name', 'modern-job-board'); ?></label>
                <input type="text" name="new_company_name" id="new_company_name" value="<?php echo empty($selected_company_id) ? esc_attr($company_name) : ''; ?>">
            </p>
            
            <!-- Application Method -->
            <p>
                <label><?php _e('Application Method', 'modern-job-board'); ?></label><br>
                <label>
                    <input type="radio" name="application_method" value="internal" checked onclick="toggleApplicationMethod()"> 
                    <?php _e('Email (Internal Form)', 'modern-job-board'); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="application_method" value="external" onclick="toggleApplicationMethod()"> 
                    <?php _e('External URL', 'modern-job-board'); ?>
                </label>
            </p>

            <p id="app-email-field">
                <label for="application_email"><?php _e('Notification Email', 'modern-job-board'); ?></label>
                <input type="email" name="application_email" id="application_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
            </p>

            <p id="app-url-field" style="display:none;">
                <label for="application_url"><?php _e('External Application URL', 'modern-job-board'); ?></label>
                <input type="url" name="application_url" id="application_url" placeholder="https://...">
            </p>

            <script>
                function toggleCompanyInput() {
                    var select = document.getElementById('company_selection');
                    var input = document.getElementById('new-company-field');
                    if (select.value === 'new') {
                        input.style.display = 'block';
                        document.getElementById('new_company_name').required = true;
                    } else {
                        input.style.display = 'none';
                        document.getElementById('new_company_name').required = false;
                    }
                }

                function toggleApplicationMethod() {
                    var method = document.querySelector('input[name="application_method"]:checked').value;
                    var emailField = document.getElementById('app-email-field');
                    var urlField = document.getElementById('app-url-field');
                    
                    if (method === 'internal') {
                        emailField.style.display = 'block';
                        urlField.style.display = 'none';
                        document.getElementById('application_email').required = true;
                        document.getElementById('application_url').required = false;
                    } else {
                        emailField.style.display = 'none';
                        urlField.style.display = 'block';
                        document.getElementById('application_email').required = false;
                        document.getElementById('application_url').required = true;
                    }
                }

                // Run on load
                window.onload = function() { 
                    toggleCompanyInput(); 
                    toggleApplicationMethod();
                };
            </script>
            
            <!-- Custom Fields -->
            <?php
            global $mjb_custom_fields;
            if (isset($mjb_custom_fields)) {
                $fields = $mjb_custom_fields->get_fields('job');
                foreach ($fields as $field) {
                    $value = $job_id ? get_post_meta($job_id, '_mjb_' . $field['key'], true) : '';
                    echo '<p>';
                    echo '<label>' . esc_html($field['label']) . '</label>';
                    
                    if ($field['type'] === 'text' || $field['type'] === 'number') {
                        echo '<input type="' . esc_attr($field['type']) . '" name="mjb_field_' . esc_attr($field['key']) . '" value="' . esc_attr($value) . '" ' . ($field['required'] ? 'required' : '') . '>';
                    } elseif ($field['type'] === 'textarea') {
                        echo '<textarea name="mjb_field_' . esc_attr($field['key']) . '" ' . ($field['required'] ? 'required' : '') . '>' . esc_textarea($value) . '</textarea>';
                    } elseif ($field['type'] === 'select') {
                        echo '<select name="mjb_field_' . esc_attr($field['key']) . '" ' . ($field['required'] ? 'required' : '') . '>';
                        $options = explode(',', $field['options']);
                        foreach ($options as $opt) {
                            $opt = trim($opt);
                            echo '<option value="' . esc_attr($opt) . '" ' . selected($value, $opt, false) . '>' . esc_html($opt) . '</option>';
                        }
                        echo '</select>';
                    } elseif ($field['type'] === 'checkbox') {
                         echo '<input type="checkbox" name="mjb_field_' . esc_attr($field['key']) . '" value="1" ' . checked(1, $value, false) . '>';
                    }
                    echo '</p>';
                }
            }
            ?>

            <p>
                <input type="submit" name="mjb_submit_job"
                    value="<?php echo $job_id ? __('Update Job', 'modern-job-board') : __('Submit Job', 'modern-job-board'); ?>">
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle Job Submission.
     */
    private function handle_job_submission($existing_job_id = 0)
    {
        $title = sanitize_text_field($_POST['job_title']);
        $description = wp_kses_post($_POST['job_description']);
        
        // Handle Company Logic
        $company_selection = sanitize_text_field($_POST['company_selection']);
        $company_id = 0;
        $company_name_text = '';

        if ($company_selection === 'new') {
            $company_name_text = sanitize_text_field($_POST['new_company_name']);
            if (empty($company_name_text)) {
                // Error handling should be better, but for now:
                return; 
            }
            
            // Create new Company
            $company_post = array(
                'post_title' => $company_name_text,
                'post_type' => 'company',
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            );
            $company_id = wp_insert_post($company_post);
        } else {
            $company_id = intval($company_selection);
            // Verify ownership
            $company_post = get_post($company_id);
            if (!$company_post || $company_post->post_type !== 'company' || intval($company_post->post_author) !== get_current_user_id()) {
                // Invalid company selected
                 return;
            }
            $company_name_text = $company_post->post_title;
        }

        // Check if updating via HIDDEN input (overrides argument if present)
        if (isset($_POST['job_id']) && intval($_POST['job_id']) > 0) {
            $existing_job_id = intval($_POST['job_id']);
            // Re-verify ownership for security (double check)
            $job = get_post($existing_job_id);
            if (!$job || intval($job->post_author) !== get_current_user_id()) {
                return;
            }
        }

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_type' => 'job_listing',
            'post_status' => 'pending', // Always reset to pending on edit? Or keep status? Let's keep status if edit.
        );

        if ($existing_job_id) {
            $post_data['ID'] = $existing_job_id;
            // Don't change status if editing, unless we want to re-review. Let's keep current status for now for simplicity, or pending if logic requires.
            // Actually, usually edits require re-approval. Let's set to pending.
            $post_data['post_status'] = 'pending';
            $post_id = wp_update_post($post_data);
            $message = __('Job updated successfully! It is pending review.', 'modern-job-board');
        } else {
            $post_data['post_status'] = 'pending';
            $post_id = wp_insert_post($post_data);
            $message = __('Job submitted successfully! It is pending review.', 'modern-job-board');

            // Set Expiration Date
            $duration = get_option('mjb_listing_duration', 30);
            $expires = date('Y-m-d', strtotime("+$duration days"));
            update_post_meta($post_id, '_job_expires', $expires);
        }

        if ($post_id) {
            update_post_meta($post_id, '_company_name', $company_name_text); // Legacy/Fallback
            if ($company_id) {
                update_post_meta($post_id, '_company_id', $company_id);
            }
            
            // Save Application Method
            if (isset($_POST['application_method'])) {
                update_post_meta($post_id, '_application_method', sanitize_text_field($_POST['application_method']));
            }
            if (isset($_POST['application_email'])) {
                update_post_meta($post_id, '_application_email', sanitize_email($_POST['application_email']));
            }
            if (isset($_POST['application_url'])) {
                update_post_meta($post_id, '_application_url', esc_url_raw($_POST['application_url']));
            }
            
            // Send Notification
            global $mjb_emails;
            if (isset($mjb_emails)) {
                $mjb_emails->send_new_job_notification($post_id);
            }

            // Save Custom Fields
            global $mjb_custom_fields;
            if (isset($mjb_custom_fields)) {
                $fields = $mjb_custom_fields->get_fields('job');
                foreach ($fields as $field) {
                    $key = 'mjb_field_' . $field['key'];
                    if (isset($_POST[$key])) {
                        $val = sanitize_text_field($_POST[$key]);
                        update_post_meta($post_id, '_mjb_' . $field['key'], $val);
                    } else {
                        // Checkbox unchecked
                        if ($field['type'] === 'checkbox') {
                             update_post_meta($post_id, '_mjb_' . $field['key'], 0);
                        }
                    }
                }
            }
            
            // Payment Logic
            $payment_required = get_option('mjb_payment_required');
            $product_id = get_option('mjb_submission_product_id');
            
            if ($payment_required) {
                $user_id = get_current_user_id();
                $credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $credits = $credits ? intval($credits) : 0;

                // Check for Credits
                if ($credits > 0) {
                     // Use Credit
                     $new_credits = $credits - 1;
                     update_user_meta($user_id, '_mjb_job_credits', $new_credits);
                     
                     // Publish Job immediately (bypass pending_payment)
                     // If previously set to pending, update to publish.
                     wp_update_post(array(
                         'ID' => $post_id,
                         'post_status' => 'publish'
                     ));
                     
                     // Optional: Add note that credit was used
                     update_post_meta($post_id, '_mjb_credit_used', true);
                     
                     $message = __('Job submitted successfully using a job credit!', 'modern-job-board');
                     echo '<p class="mjb-success">' . $message . '</p>';
                     return; // Skip payment redirect
                }

                // If no credits, proceed to Pay-Per-Post
                if ($product_id && function_exists('wc_get_cart_url')) {
                    if ($post_data['post_status'] === 'pending') { // Only if we just set it to pending
                         // Update status to pending_payment
                         $update = array('ID' => $post_id, 'post_status' => 'pending_payment');
                         wp_update_post($update);
                         
                         $cart_url = wc_get_cart_url();
                         $redirect_url = add_query_arg(array(
                             'add-to-cart' => $product_id,
                             'mjb_job_id' => $post_id
                         ), $cart_url);
                         
                         echo '<script>window.location.href="' . esc_url($redirect_url) . '";</script>';
                         exit;
                    }
                }
            }

            echo '<p class="mjb-success">' . $message . '</p>';
        }
    }

    /**
     * Get User Companies.
     */
    private function get_user_companies($user_id)
    {
        $args = array(
            'post_type' => 'company',
            'post_status' => 'publish', // Companies should be published to be selected? Or pending allowed? let's say publish.
            'posts_per_page' => -1,
            'author' => $user_id,
        );
        $user_companies = get_posts($args);
        return $user_companies;
    }

    /**
     * Output Employer Dashboard.
     */
    /**
     * Output Employer Dashboard.
     * Note: This is now handled by MJB_Dashboard class, so this method is deprecated/removed or delegates.
     * But since we registered the shortcode in MJB_Shortcodes initially, we should remove it from here if we want MJB_Dashboard to handle it.
     * OR we update this method to delegate.
     * 
     * To avoid conflict, I will remove the registration from init() above and remove this method.
     */
    // Removing method logic as it is superseded.
}
