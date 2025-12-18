<?php
/**
 * Modern Job Board Candidate Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Candidate_Dashboard
{

    /**
     * Initialize Dashboard.
     */
    public function init()
    {
        add_shortcode('mjb_candidate_dashboard', array($this, 'output_dashboard'));
        add_action('init', array($this, 'handle_profile_update'));
        add_action('init', array($this, 'handle_resume_upload'));
    }

    /**
     * Handle Profile Update.
     */
    public function handle_profile_update()
    {
        if (isset($_POST['mjb_update_profile']) && isset($_POST['mjb_profile_nonce'])) {
            if (!wp_verify_nonce($_POST['mjb_profile_nonce'], 'mjb_profile_action')) {
                return;
            }

            if (!is_user_logged_in()) {
                return;
            }

            $user_id = get_current_user_id();
            $first_name = sanitize_text_field($_POST['mjb_first_name']);
            $last_name = sanitize_text_field($_POST['mjb_last_name']);
            $headline = sanitize_text_field($_POST['mjb_headline']);

            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name
            ));

            update_user_meta($user_id, '_candidate_headline', $headline);

            // Redirect to avoid form resubmission
            wp_redirect(add_query_arg('updated', 'true', get_permalink()));
            exit;
        }
    }

    /**
     * Handle Resume Upload.
     */
    public function handle_resume_upload()
    {
        if (isset($_POST['mjb_upload_resume']) && isset($_POST['mjb_resume_nonce'])) {
            if (!wp_verify_nonce($_POST['mjb_resume_nonce'], 'mjb_resume_action')) {
                return;
            }

            if (!is_user_logged_in()) {
                return;
            }

            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $uploadedfile = $_FILES['mjb_resume'];
            $upload_overrides = array('test_form' => false);

            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $user_id = get_current_user_id();

                // Prepare attachment data
                $attachment = array(
                    'guid' => $movefile['url'],
                    'post_mime_type' => $movefile['type'],
                    'post_title' => basename($movefile['file']),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );

                // Insert the attachment.
                $attach_id = wp_insert_attachment($attachment, $movefile['file']);

                // Generate attachment metadata and update the database record.
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);

                // Start: Security - Protect File (Basic) - Ensure it's not browseable if not intended
                // For now, standard media library is fine as per plan.

                // Update User Meta
                update_user_meta($user_id, '_candidate_resume_id', $attach_id);

                wp_redirect(add_query_arg('resume_updated', 'true', get_permalink()));
                exit;
            } else {
                // Handle error
                wp_redirect(add_query_arg('resume_error', 'true', get_permalink()));
                exit;
            }
        }
    }

    /**
     * Output Dashboard.
     */
    public function output_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . sprintf(__('Please <a href="%s">login</a> to view your dashboard.', 'modern-job-board'), wp_login_url(get_permalink())) . '</p>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!in_array('candidate', (array) $user->roles)) {
            return '<p>' . __('This dashboard is for candidates only.', 'modern-job-board') . '</p>';
        }

        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $headline = get_user_meta($user_id, '_candidate_headline', true);
        $resume_id = get_user_meta($user_id, '_candidate_resume_id', true);
        $resume_url = $resume_id ? wp_get_attachment_url($resume_id) : '';

        ob_start();
        ?>
        <div class="mjb-candidate-dashboard">
            <h2><?php _e('Candidate Dashboard', 'modern-job-board'); ?></h2>

            <?php if (isset($_GET['updated']) && $_GET['updated'] == 'true'): ?>
                <div class="mjb-message success"><?php _e('Profile updated successfully.', 'modern-job-board'); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['resume_updated']) && $_GET['resume_updated'] == 'true'): ?>
                <div class="mjb-message success"><?php _e('Resume uploaded successfully.', 'modern-job-board'); ?></div>
            <?php endif; ?>

            <div class="mjb-dashboard-section">
                <h3><?php _e('Profile Details', 'modern-job-board'); ?></h3>
                <form method="post" action="" class="mjb-form">
                    <?php wp_nonce_field('mjb_profile_action', 'mjb_profile_nonce'); ?>

                    <p>
                        <label for="mjb_first_name"><?php _e('First Name', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_first_name" id="mjb_first_name"
                            value="<?php echo esc_attr($first_name); ?>" required>
                    </p>

                    <p>
                        <label for="mjb_last_name"><?php _e('Last Name', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_last_name" id="mjb_last_name" value="<?php echo esc_attr($last_name); ?>"
                            required>
                    </p>

                    <p>
                        <label for="mjb_headline"><?php _e('Professional Headline', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_headline" id="mjb_headline" value="<?php echo esc_attr($headline); ?>">
                    </p>

                    <p>
                        <input type="submit" name="mjb_update_profile"
                            value="<?php _e('Update Profile', 'modern-job-board'); ?>">
                    </p>
                </form>
            </div>

            <hr>

            <div class="mjb-dashboard-section">
                <h3><?php _e('My Resume', 'modern-job-board'); ?></h3>

                <?php if ($resume_url): ?>
                    <p><strong><?php _e('Current Resume:', 'modern-job-board'); ?></strong> <a
                            href="<?php echo esc_url($resume_url); ?>"
                            target="_blank"><?php _e('View Resume', 'modern-job-board'); ?></a></p>
                <?php else: ?>
                    <p><?php _e('No resume uploaded yet.', 'modern-job-board'); ?></p>
                <?php endif; ?>

                <form method="post" action="" enctype="multipart/form-data" class="mjb-form">
                    <?php wp_nonce_field('mjb_resume_action', 'mjb_resume_nonce'); ?>
                    <p>
                        <label for="mjb_resume"><?php _e('Upload Resume (PDF/Docx)', 'modern-job-board'); ?></label>
                        <input type="file" name="mjb_resume" id="mjb_resume" accept=".pdf,.doc,.docx" required>
                    </p>
                    <p>
                        <input type="submit" name="mjb_upload_resume" value="<?php _e('Upload Resume', 'modern-job-board'); ?>">
                    </p>
                </form>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
