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

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_profile_nonce'])), 'mjb_profile_action')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        if (!is_user_logged_in()) {
            MJB_Notices::redirect($redirect_url, 'error_permission');
        }

        $user_id = get_current_user_id();
        $first_name = isset($_POST['mjb_first_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_first_name'])) : '';
        $last_name = isset($_POST['mjb_last_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_last_name'])) : '';
        $headline = isset($_POST['mjb_headline']) ? sanitize_text_field(wp_unslash($_POST['mjb_headline'])) : '';
        $linkedin = isset($_POST['mjb_linkedin']) ? esc_url_raw(wp_unslash($_POST['mjb_linkedin'])) : '';
        $website = isset($_POST['mjb_website']) ? esc_url_raw(wp_unslash($_POST['mjb_website'])) : '';
        $phone = isset($_POST['mjb_phone']) ? sanitize_text_field(wp_unslash($_POST['mjb_phone'])) : '';
        $city = isset($_POST['mjb_city']) ? sanitize_text_field(wp_unslash($_POST['mjb_city'])) : '';
        $experience_company = isset($_POST['mjb_experience_company']) ? sanitize_text_field(wp_unslash($_POST['mjb_experience_company'])) : '';
        $is_current_role = !empty($_POST['mjb_is_current_role']) ? '1' : '0';
        $bio = isset($_POST['mjb_bio']) ? sanitize_textarea_field(wp_unslash($_POST['mjb_bio'])) : '';
        $open_to_work = !empty($_POST['mjb_open_to_work']) ? '1' : '0';
        $remote_ok = !empty($_POST['mjb_remote_ok']) ? '1' : '0';
        $is_public = !empty($_POST['mjb_is_public']) ? '1' : '0';

        if (empty($first_name) || empty($last_name)) {
            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
        }

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
        ));

        update_user_meta($user_id, '_candidate_headline', $headline);
        update_user_meta($user_id, '_candidate_linkedin', $linkedin);
        update_user_meta($user_id, '_candidate_website', $website);
        update_user_meta($user_id, '_candidate_phone', $phone);
        update_user_meta($user_id, '_candidate_city', $city);
        update_user_meta($user_id, '_candidate_experience_company', $experience_company);
        update_user_meta($user_id, '_candidate_is_current_role', $is_current_role);
        update_user_meta($user_id, '_candidate_bio', $bio);
        update_user_meta($user_id, '_candidate_open_to_work', $open_to_work);
        update_user_meta($user_id, '_candidate_remote_ok', $remote_ok);
        update_user_meta($user_id, '_candidate_is_public', $is_public);

        if (!empty($_FILES['mjb_photo']['name'])) {
            $photo_upload = MJB_Private_Uploads::upload($_FILES['mjb_photo'], MJB_Private_Uploads::TYPE_CANDIDATE_PHOTO);
            if (is_wp_error($photo_upload)) {
                $code = $photo_upload->get_error_code() === 'invalid_type' ? 'error_invalid_photo' : 'error_photo_upload';
                MJB_Notices::redirect($redirect_url, $code);
            }

            $old = get_user_meta($user_id, '_candidate_photo_path', true);
            if ($old) {
                MJB_Private_Uploads::delete($old);
            }

            update_user_meta($user_id, '_candidate_photo_path', $photo_upload['file']);
            if (!empty($photo_upload['relative'])) {
                update_user_meta($user_id, '_candidate_photo_relative', $photo_upload['relative']);
            }
            if (!empty($photo_upload['url'])) {
                update_user_meta($user_id, '_candidate_photo_url', $photo_upload['url']);
            }
        }

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

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_resume_nonce'])), 'mjb_resume_action')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        if (!is_user_logged_in()) {
            MJB_Notices::redirect($redirect_url, 'error_permission');
        }

        if (empty($_FILES['mjb_resume']['name'])) {
            MJB_Notices::redirect($redirect_url, 'error_resume_required');
        }

        $original_name = sanitize_file_name(wp_unslash($_FILES['mjb_resume']['name']));
        $uploaded = MJB_Resumes::upload_file($_FILES['mjb_resume'], 'candidate_profile');
        if (is_wp_error($uploaded)) {
            $code = $uploaded->get_error_code() === 'invalid_type' ? 'error_invalid_resume' : 'error_resume_upload';
            MJB_Notices::redirect($redirect_url, $code);
        }

        $user_id = get_current_user_id();
        $old_resume_id = intval(get_user_meta($user_id, '_candidate_resume_id', true));

        $resume_id = MJB_Resumes::create_resume_post($user_id, $uploaded, $original_name);
        if (is_wp_error($resume_id)) {
            MJB_Private_Uploads::delete($uploaded['file']);
            MJB_Notices::redirect($redirect_url, 'error_resume_upload');
        }

        // Only remove the previous profile resume when no application still needs it.
        if ($old_resume_id) {
            MJB_Resumes::maybe_retire_profile_resume($old_resume_id);
        }

        MJB_Notices::redirect($redirect_url, 'success_resume');
    }

    /**
     * Output Dashboard.
     *
     * @param array $atts
     * @return string
     */
    public function output_dashboard($atts)
    {
        unset($atts);

        if (!is_user_logged_in()) {
            return '<p>' . sprintf(
                /* translators: %s: login URL */
                __('Please <a href="%s">login</a> to view your dashboard.', 'modern-job-board'),
                esc_url(
                    class_exists('MJB_Login') && method_exists('MJB_Login', 'get_frontend_login_url')
                        ? MJB_Login::get_frontend_login_url('candidate', get_permalink())
                        : home_url('/jobs/candidate-login/')
                )
            ) . '</p>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!in_array('candidate', (array) $user->roles, true)) {
            return '<p>' . esc_html__('This dashboard is for candidates only.', 'modern-job-board') . '</p>';
        }

        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $headline = get_user_meta($user_id, '_candidate_headline', true);
        $linkedin = get_user_meta($user_id, '_candidate_linkedin', true);
        $website = get_user_meta($user_id, '_candidate_website', true);
        $phone = get_user_meta($user_id, '_candidate_phone', true);
        $city = get_user_meta($user_id, '_candidate_city', true);
        $experience_company = get_user_meta($user_id, '_candidate_experience_company', true);
        $is_current_role = get_user_meta($user_id, '_candidate_is_current_role', true);
        $bio = get_user_meta($user_id, '_candidate_bio', true);
        $open_to_work = get_user_meta($user_id, '_candidate_open_to_work', true);
        $remote_ok = get_user_meta($user_id, '_candidate_remote_ok', true);
        $is_public = get_user_meta($user_id, '_candidate_is_public', true);
        $photo_path = get_user_meta($user_id, '_candidate_photo_path', true);
        $photo_url = $photo_path ? MJB_Private_Uploads::get_public_url($photo_path) : '';
        if (!$photo_url) {
            $photo_url = get_user_meta($user_id, '_candidate_photo_url', true);
        }

        $resume_id = get_user_meta($user_id, '_candidate_resume_id', true);
        $resume_url = MJB_Resumes::get_resume_display_url($resume_id);

        ob_start();
        ?>
        <div class="mjb-candidate-dashboard">
            <h2><?php esc_html_e('Candidate Dashboard', 'modern-job-board'); ?></h2>

            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in MJB_Notices::render().
            echo MJB_Notices::render();

            /**
             * Before profile form (e.g. completeness progress bar).
             *
             * @param int $user_id
             */
            do_action('mjb_candidate_dashboard_before_profile', $user_id);
            ?>

            <div class="mjb-dashboard-section">
                <h3><?php esc_html_e('Profile Details', 'modern-job-board'); ?></h3>
                <form method="post" action="" class="mjb-form" enctype="multipart/form-data" novalidate>
                    <?php wp_nonce_field('mjb_profile_action', 'mjb_profile_nonce'); ?>

                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
                    echo MJB_Shortcodes::required_fields_note();
                    ?>

                    <p>
                        <label for="mjb_first_name"><?php esc_html_e('First Name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input type="text" name="mjb_first_name" id="mjb_first_name"
                            value="<?php echo esc_attr($first_name); ?>" required aria-required="true">
                    </p>

                    <p>
                        <label for="mjb_last_name"><?php esc_html_e('Last Name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input type="text" name="mjb_last_name" id="mjb_last_name" value="<?php echo esc_attr($last_name); ?>"
                            required aria-required="true">
                    </p>

                    <p>
                        <label for="mjb_headline"><?php esc_html_e('Title', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_headline" id="mjb_headline" value="<?php echo esc_attr($headline); ?>">
                    </p>

                    <p>
                        <label for="mjb_experience_company"><?php esc_html_e('Company name', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_experience_company" id="mjb_experience_company" value="<?php echo esc_attr($experience_company); ?>">
                    </p>

                    <p class="mjb-checkbox-row">
                        <label for="mjb_is_current_role">
                            <input type="checkbox" name="mjb_is_current_role" id="mjb_is_current_role" value="1" <?php checked($is_current_role, '1'); ?>>
                            <?php esc_html_e('Currently working in this role', 'modern-job-board'); ?>
                        </label>
                    </p>

                    <p>
                        <label for="mjb_city"><?php esc_html_e('City', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_city" id="mjb_city" value="<?php echo esc_attr($city); ?>">
                    </p>

                    <p>
                        <label for="mjb_phone"><?php esc_html_e('Phone number', 'modern-job-board'); ?></label>
                        <input type="text" name="mjb_phone" id="mjb_phone" value="<?php echo esc_attr($phone); ?>">
                    </p>

                    <p>
                        <label for="mjb_linkedin"><?php esc_html_e('LinkedIn profile', 'modern-job-board'); ?></label>
                        <input type="url" name="mjb_linkedin" id="mjb_linkedin" value="<?php echo esc_attr($linkedin); ?>">
                    </p>

                    <p>
                        <label for="mjb_website"><?php esc_html_e('Website URL', 'modern-job-board'); ?></label>
                        <input type="url" name="mjb_website" id="mjb_website" value="<?php echo esc_attr($website); ?>">
                    </p>

                    <p>
                        <label for="mjb_bio"><?php esc_html_e('Short bio', 'modern-job-board'); ?></label>
                        <textarea name="mjb_bio" id="mjb_bio" rows="3"><?php echo esc_textarea($bio); ?></textarea>
                    </p>

                    <p class="mjb-checkbox-row">
                        <label for="mjb_open_to_work">
                            <input type="checkbox" name="mjb_open_to_work" id="mjb_open_to_work" value="1" <?php checked($open_to_work, '1'); ?>>
                            <?php esc_html_e('Open to work', 'modern-job-board'); ?>
                        </label>
                    </p>

                    <p class="mjb-checkbox-row">
                        <label for="mjb_remote_ok">
                            <input type="checkbox" name="mjb_remote_ok" id="mjb_remote_ok" value="1" <?php checked($remote_ok, '1'); ?>>
                            <?php esc_html_e('Interested in remote opportunities', 'modern-job-board'); ?>
                        </label>
                    </p>

                    <p class="mjb-checkbox-row">
                        <label for="mjb_is_public">
                            <input type="checkbox" name="mjb_is_public" id="mjb_is_public" value="1" <?php checked($is_public, '1'); ?>>
                            <?php esc_html_e('Display my profile to recruiters', 'modern-job-board'); ?>
                        </label>
                    </p>

                    <?php if ($photo_url) : ?>
                        <p class="mjb-photo-preview">
                            <img src="<?php echo esc_url($photo_url); ?>" alt="" width="72" height="72" style="border-radius:50%;object-fit:cover;">
                        </p>
                    <?php endif; ?>

                    <p>
                        <label for="mjb_photo"><?php esc_html_e('Photo', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input type="file" name="mjb_photo" id="mjb_photo" accept="image/jpeg,image/png,image/webp">
                    </p>

                    <p>
                        <input type="submit" name="mjb_update_profile"
                            value="<?php esc_attr_e('Update Profile', 'modern-job-board'); ?>">
                    </p>
                </form>
            </div>

            <hr>

            <div class="mjb-dashboard-section">
                <h3><?php esc_html_e('Saved jobs', 'modern-job-board'); ?></h3>
                <?php $this->output_saved_jobs($user_id); ?>
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
                            target="_blank" rel="noopener noreferrer"><?php esc_html_e('Download Resume', 'modern-job-board'); ?></a></p>
                <?php else: ?>
                    <p><?php esc_html_e('No resume uploaded yet.', 'modern-job-board'); ?></p>
                <?php endif; ?>

                <form method="post" action="" enctype="multipart/form-data" class="mjb-form" novalidate>
                    <?php wp_nonce_field('mjb_resume_action', 'mjb_resume_nonce'); ?>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
                    echo MJB_Shortcodes::required_fields_note();
                    ?>
                    <p>
                        <label for="mjb_resume"><?php esc_html_e('Upload Resume (PDF/Docx)', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input type="file" name="mjb_resume" id="mjb_resume" accept=".pdf,.doc,.docx" required aria-required="true">
                        <span class="mjb-field-hint"><?php esc_html_e('Stored privately in the plugin (not the Media Library).', 'modern-job-board'); ?></span>
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
     * Output the candidate's bookmarked jobs with apply / remove actions.
     *
     * @param int $user_id
     */
    private function output_saved_jobs($user_id)
    {
        if (!class_exists('MJB_Saved_Jobs')) {
            echo '<p>' . esc_html__('Saved jobs are not available.', 'modern-job-board') . '</p>';
            return;
        }

        $jobs = MJB_Saved_Jobs::get_jobs($user_id);
        if (empty($jobs)) {
            $jobs_url = MJB_Page_Resolver::get_jobs_page_url();
            echo '<p>' . esc_html__('You have not saved any jobs yet.', 'modern-job-board') . '</p>';
            echo '<p><a class="btn btn-outline btn-sm" href="' . esc_url($jobs_url) . '">' . esc_html__('Browse jobs', 'modern-job-board') . '</a></p>';
            return;
        }

        $dash_url = self::get_page_url();
        $headers = array(
            __('Job', 'modern-job-board'),
            __('Company', 'modern-job-board'),
            __('Location', 'modern-job-board'),
            __('Actions', 'modern-job-board'),
        );
        $grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard mjb-data-grid--saved-jobs', count($headers));
        $grid->render_header($headers)->open_body();

        foreach ($jobs as $job) {
            $job_id = (int) $job->ID;
            $permalink = get_permalink($job_id);
            $title = get_the_title($job_id);
            $company = get_post_meta($job_id, '_company_name', true);
            if ($company === '' && class_exists('MJB_Search')) {
                $company_id = (int) get_post_meta($job_id, '_company_id', true);
                if ($company_id > 0) {
                    $company = get_the_title($company_id);
                }
            }

            $location = '';
            if (class_exists('MJB_Location') && method_exists('MJB_Location', 'format_job_location')) {
                $location = MJB_Location::format_job_location($job_id);
            }
            if ($location === '') {
                $terms = get_the_terms($job_id, 'job_location');
                if (is_array($terms) && !empty($terms) && !is_wp_error($terms)) {
                    $names = array();
                    foreach ($terms as $term) {
                        $names[] = $term->name;
                    }
                    $location = implode(', ', $names);
                }
            }
            if ($location === '') {
                $location = '—';
            }

            $job_html = $permalink
                ? '<a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a>'
                : esc_html($title);

            $apply = MJB_Saved_Jobs::get_apply_action($job_id);
            $remove_url = MJB_Saved_Jobs::get_toggle_url($job_id, $dash_url);

            $actions = '<div class="mjb-saved-job-actions">';
            if (!empty($apply['disabled'])) {
                $actions .= '<span class="btn btn-outline btn-sm is-disabled" aria-disabled="true">' . esc_html($apply['label']) . '</span>';
            } elseif (!empty($apply['url'])) {
                $target = !empty($apply['external']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                $actions .= '<a class="btn btn-primary btn-sm" href="' . esc_url($apply['url']) . '"' . $target . '>'
                    . esc_html($apply['label']) . '</a>';
            }
            if ($permalink) {
                $actions .= ' <a class="btn btn-outline btn-sm" href="' . esc_url($permalink) . '">'
                    . esc_html__('View', 'modern-job-board') . '</a>';
            }
            $actions .= ' <a class="btn btn-outline btn-sm mjb-saved-job-actions__remove" href="' . esc_url($remove_url) . '">'
                . esc_html__('Remove', 'modern-job-board') . '</a>';
            $actions .= '</div>';

            $grid->open_row()
                ->render_cell($job_html, $headers[0])
                ->render_cell(esc_html($company !== '' ? $company : '—'), $headers[1])
                ->render_cell(esc_html($location), $headers[2])
                ->render_cell($actions, $headers[3])
                ->close_row();
        }

        $grid->close_body()->end();
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
        return MJB_Page_Resolver::get_page_url('mjb_candidate_dashboard', self::PAGE_OPTION, $query_args, '/jobs/candidate-dashboard/');
    }
}
