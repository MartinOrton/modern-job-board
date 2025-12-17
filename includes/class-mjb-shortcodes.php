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
        // Query jobs
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        );

        // Apply Search Filters
        $search = new MJB_Search();
        // Create a temporary WP_Query to use methods or manually apply args (MJB_Search uses pre_get_posts which targets the main query, for shortcodes we need to manually build args or use a custom method).
        // Refactoring MJB_Search slightly to allow arg modification would be cleaner, but for now we can duplicate logic or instantiate a query.

        // Actually, MJB_Search::apply_search_criteria expects a WP_Query object.
        $jobs = new WP_Query();

        // Let's modify args directly for the Custom Query
        if (!empty($_GET['search_keywords'])) {
            $args['s'] = sanitize_text_field($_GET['search_keywords']);
        }

        $tax_query = array();
        if (!empty($_GET['search_location'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_location',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_location']),
            );
        }
        if (!empty($_GET['search_category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['search_category']),
            );
        }
        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        $jobs = new WP_Query($args);

        if ($jobs->have_posts()) {
            echo '<div class="mjb-job-list">';
            while ($jobs->have_posts()) {
                $jobs->the_post();
                // This is a simple list for now, ideally we'd load a template part
                echo '<div class="mjb-job-item">';
                echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
                echo '<div class="mjb-job-meta">';
                echo '<span>' . get_the_term_list(get_the_ID(), 'job_type', '', ', ') . '</span>';
                echo '<span>' . get_the_term_list(get_the_ID(), 'job_location', '', ', ') . '</span>';
                echo '</div>'; // .mjb-job-meta
                echo '</div>'; // .mjb-job-item
            }
            echo '</div>'; // .mjb-job-list
            wp_reset_postdata();
        } else {
            echo '<p>' . __('No jobs found.', 'modern-job-board') . '</p>';
        }

        return ob_get_clean();
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
                // Run on load
                window.onload = function() { toggleCompanyInput(); };
            </script>
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
        }

        if ($post_id) {
            update_post_meta($post_id, '_company_name', $company_name_text); // Legacy/Fallback
            if ($company_id) {
                update_post_meta($post_id, '_company_id', $company_id);
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
