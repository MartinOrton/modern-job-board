<?php
/**
 * Modern Job Board Candidate Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Candidate_Dashboard
{
    const PAGE_OPTION = 'mjb_candidate_dashboard_page_id';

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

        $redirect_url = self::get_page_url();

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

        do_action('mjb_candidate_profile_updated', $user_id, array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'headline' => $headline,
        ));

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

        $redirect_url = self::get_page_url();

        if (!wp_verify_nonce($_POST['mjb_resume_nonce'], 'mjb_resume_action')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        if (!is_user_logged_in()) {
            MJB_Notices::redirect($redirect_url, 'error_permission');
        }

        if (empty($_FILES['mjb_resume']['name'])) {
            MJB_Notices::redirect($redirect_url, 'error_resume_required');
        }

        $uploaded = MJB_Resumes::upload_file($_FILES['mjb_resume'], 'candidate_profile');
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
            <h2><?php esc_html_e('Candidate Dashboard', 'modern-job-board'); ?></h2>

            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in MJB_Notices::render().
            echo MJB_Notices::render();
            ?>

            <div class="mjb-dashboard-section">
                <h3><?php esc_html_e('Profile Details', 'modern-job-board'); ?></h3>
                <form method="post" action="" class="mjb-form">
                    <?php wp_nonce_field('mjb_profile_action', 'mjb_profile_nonce'); ?>

                    <p>
                        <label for="mjb_first_name"><?php esc_html_e('First Name', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_first_name" id="mjb_first_name"
                            value="<?php echo esc_attr($first_name); ?>" required>
                    </p>

                    <p>
                        <label for="mjb_last_name"><?php esc_html_e('Last Name', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_last_name" id="mjb_last_name" value="<?php echo esc_attr($last_name); ?>"
                            required>
                    </p>

                    <p>
                        <label for="mjb_headline"><?php esc_html_e('Professional Headline', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_headline" id="mjb_headline" value="<?php echo esc_attr($headline); ?>">
                    </p>

                    <p>
                        <input type="submit" name="mjb_update_profile"
                            value="<?php esc_attr_e('Update Profile', 'modern-job-board'); ?>">
                    </p>
                </form>
            </div>

            <hr>

            <div class="mjb-dashboard-section">
                <h3><?php esc_html_e('My Applications', 'modern-job-board'); ?></h3>
                <?php $this->output_my_applications($user); ?>
            </div>

            <hr>

            <div class="mjb-dashboard-section">
                <h3><?php esc_html_e('My Resume', 'modern-job-board'); ?></h3>

                <?php if ($resume_url): ?>
                    <p><strong><?php esc_html_e('Current Resume:', 'modern-job-board'); ?></strong> <a
                            href="<?php echo esc_url($resume_url); ?>"
                            target="_blank"><?php esc_html_e('View Resume', 'modern-job-board'); ?></a></p>
                <?php else: ?>
                    <p><?php esc_html_e('No resume uploaded yet.', 'modern-job-board'); ?></p>
                <?php endif; ?>

                <form method="post" action="" enctype="multipart/form-data" class="mjb-form">
                    <?php wp_nonce_field('mjb_resume_action', 'mjb_resume_nonce'); ?>
                    <p>
                        <label for="mjb_resume"><?php esc_html_e('Upload Resume (PDF/Docx)', 'modern-job-board'); ?></label>
                        <input type="file" name="mjb_resume" id="mjb_resume" accept=".pdf,.doc,.docx" required>
                    </p>
                    <p>
                        <input type="submit" name="mjb_upload_resume" value="<?php esc_attr_e('Upload Resume', 'modern-job-board'); ?>">
                    </p>
                </form>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Output the candidate's submitted applications.
     *
     * @param WP_User $user
     */
    private function output_my_applications($user)
    {
        $applications = get_posts(array(
            'post_type' => 'job_application',
            'post_status' => array('publish', 'pending', 'draft'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_candidate_email',
                    'value' => $user->user_email,
                ),
            ),
        ));

        if (empty($applications)) {
            echo '<p>' . esc_html__('You have not applied for any jobs yet.', 'modern-job-board') . '</p>';
            return;
        }

        $app_headers = array(
            __('Job', 'modern-job-board'),
            __('Applied', 'modern-job-board'),
            __('Status', 'modern-job-board'),
        );
        $apps_grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard', count($app_headers));
        $apps_grid->render_header($app_headers)->open_body();

        foreach ($applications as $application) {
            $job_id = intval(get_post_meta($application->ID, '_job_applied_for', true));
            $job = $job_id ? get_post($job_id) : null;
            $job_title = $job ? get_the_title($job_id) : __('Unknown job', 'modern-job-board');
            $job_link = $job && $job->post_status === 'publish' ? get_permalink($job_id) : '';

            if ($job_link) {
                $job_html = '<a href="' . esc_url($job_link) . '">' . esc_html($job_title) . '</a>';
            } else {
                $job_html = esc_html($job_title);
            }

            $apps_grid->open_row()
                ->render_cell($job_html, $app_headers[0])
                ->render_cell(esc_html(get_the_date('', $application->ID)), $app_headers[1])
                ->render_cell(esc_html(MJB_Application_Status::get_label(MJB_Application_Status::get_status($application->ID))), $app_headers[2])
                ->close_row();
        }

        $apps_grid->close_body()->end();
    }

    /**
     * Build a candidate dashboard URL with optional query arguments.
     *
     * @param array $query_args
     * @return string
     */
    public static function get_page_url($query_args = array())
    {
        return MJB_Page_Resolver::get_page_url('mjb_candidate_dashboard', self::PAGE_OPTION, $query_args, '/candidate-dashboard/');
    }
}