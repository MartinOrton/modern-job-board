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
        add_shortcode('mjb_dashboard', array($this, 'output_dashboard'));
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
    public function output_job_form($atts)
    {
        // Basic form implementation
        ob_start();
        if (isset($_POST['mjb_submit_job']) && isset($_POST['mjb_job_nonce']) && wp_verify_nonce($_POST['mjb_job_nonce'], 'mjb_submit_job')) {
            $this->handle_job_submission();
        }
        ?>
        <form method="post" class="mjb-job-form" enctype="multipart/form-data">
            <?php wp_nonce_field('mjb_submit_job', 'mjb_job_nonce'); ?>
            <p>
                <label for="job_title"><?php _e('Job Title', 'modern-job-board'); ?></label>
                <input type="text" name="job_title" id="job_title" required>
            </p>
            <p>
                <label for="job_description"><?php _e('Description', 'modern-job-board'); ?></label>
                <textarea name="job_description" id="job_description" required></textarea>
            </p>
            <p>
                <label for="company_name"><?php _e('Company Name', 'modern-job-board'); ?></label>
                <input type="text" name="company_name" id="company_name" required>
            </p>
            <p>
                <input type="submit" name="mjb_submit_job" value="<?php _e('Submit Job', 'modern-job-board'); ?>">
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle Job Submission.
     */
    private function handle_job_submission()
    {
        $title = sanitize_text_field($_POST['job_title']);
        $description = wp_kses_post($_POST['job_description']);
        $company = sanitize_text_field($_POST['company_name']);

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_type' => 'job_listing',
            'post_status' => 'pending', // Pending review
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            update_post_meta($post_id, '_company_name', $company);
            echo '<p class="mjb-success">' . __('Job submitted successfully! It is pending review.', 'modern-job-board') . '</p>';
        }
    }

    /**
     * Output Employer Dashboard.
     */
    public function output_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to view the dashboard.', 'modern-job-board') . '</p>';
        }
        // Placeholder for dashboard
        return '<div class="mjb-dashboard"><h3>' . __('Employer Dashboard', 'modern-job-board') . '</h3><p>' . __('Manage your jobs here.', 'modern-job-board') . '</p></div>';
    }
}
