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
        if (!isset($_POST['mjb_update_profile']) || !isset($_POST['mjb_profile_nonce'])) {
            return;
        }

        $redirect_url = get_permalink();

        if (!wp_verify_nonce($_POST['mjb_profile_nonce'], 'mjb_profile_action')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        if (!is_user_logged_in()) {
            MJB_Notices::redirect($redirect_url, 'error_permission');
        }

        $user_id = get_current_user_id();
        $first_name = isset($_POST['mjb_first_name']) ? sanitize_text_field($_POST['mjb_first_name']) : '';
        $last_name = isset($_POST['mjb_last_name']) ? sanitize_text_field($_POST['mjb_last_name']) : '';
        $headline = isset($_POST['mjb_headline']) ? sanitize_text_field($_POST['mjb_headline']) : '';

        if (empty($first_name) || empty($last_name)) {
            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
        }

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name
        ));

        update_user_meta($user_id, '_candidate_headline', $headline);

        MJB_Notices::redirect($redirect_url, 'success_profile');
    }

    /**
     * Handle Resume Upload.
     */
    public function handle_resume_upload()
    {
        if (!isset($_POST['mjb_upload_resume']) || !isset($_POST['mjb_resume_nonce'])) {
            return;
        }

        $redirect_url = get_permalink();

        if (!wp_verify_nonce($_POST['mjb_resume_nonce'], 'mjb_resume_action')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        if (!is_user_logged_in()) {
            MJB_Notices::redirect($redirect_url, 'error_permission');
        }

        if (empty($_FILES['mjb_resume']['name'])) {
            MJB_Notices::redirect($redirect_url, 'error_resume_required');
        }

        $uploaded = MJB_Resumes::upload_file($_FILES['mjb_resume']);
        if (is_wp_error($uploaded)) {
            $code = $uploaded->get_error_code() === 'invalid_type' ? 'error_invalid_resume' : 'error_resume_upload';
            MJB_Notices::redirect($redirect_url, $code);
        }

        $user_id = get_current_user_id();
        $resume_post = array(
            'post_title' => sanitize_file_name($_FILES['mjb_resume']['name']) . ' - ' . get_userdata($user_id)->display_name,
            'post_type' => 'mjb_resume',
            'post_status' => 'publish',
            'post_author' => $user_id,
        );

        $resume_id = wp_insert_post($resume_post);

        if (!$resume_id || is_wp_error($resume_id)) {
            MJB_Notices::redirect($redirect_url, 'error_resume_upload');
        }

        update_post_meta($resume_id, '_resume_file_path', $uploaded['file']);
        update_post_meta($resume_id, '_candidate_user_id', $user_id);
        update_user_meta($user_id, '_candidate_resume_id', $resume_id);

        MJB_Notices::redirect($redirect_url, 'success_resume');
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

        if (!in_array('candidate', (array) $user->roles, true)) {
            return '<p>' . __('This dashboard is for candidates only.', 'modern-job-board') . '</p>';
        }

        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $headline = get_user_meta($user_id, '_candidate_headline', true);
        $resume_id = get_user_meta($user_id, '_candidate_resume_id', true);
        $resume_url = MJB_Resumes::get_resume_display_url($resume_id);

        ob_start();
        ?>
        <div class="mjb-candidate-dashboard">
            <h2><?php _e('Candidate Dashboard', 'modern-job-board'); ?></h2>

            <?php echo MJB_Notices::render(); ?>

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