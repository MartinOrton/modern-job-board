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
    const BIO_MAX = 500;
    const BIO_COMPLETE = 20;

    /**
     * Initialize Dashboard.
     */
    public function init()
    {
        add_shortcode('mjb_candidate_dashboard', array($this, 'output_dashboard'));
        add_action('init', array($this, 'handle_profile_update'));
        add_action('init', array($this, 'handle_resume_upload'));
        add_action('init', array($this, 'handle_resume_delete'));
        add_action('init', array($this, 'handle_photo_upload'));
        add_action('init', array($this, 'handle_photo_delete'));
        add_action('wp_ajax_mjb_candidate_layer', array($this, 'ajax_load_layer'));
        add_action('wp_ajax_mjb_candidate_profile', array($this, 'ajax_save_profile'));
        add_action('wp_ajax_mjb_upload_resume', array($this, 'ajax_upload_resume'));
        add_action('wp_ajax_mjb_delete_photo', array($this, 'ajax_delete_photo'));
        add_action('wp_ajax_mjb_delete_resume', array($this, 'ajax_delete_resume'));
        if (class_exists('MJB_Candidate_Account')) {
            MJB_Candidate_Account::init();
        }
    }

    /**
     * Handle Profile Update.
     */
    public function handle_profile_update()
    {
        if (!isset($_POST['mjb_update_profile']) || !isset($_POST['mjb_profile_nonce'])) {
            return;
        }

        // admin-ajax.php still runs init. A redirect here would replace the JSON response.
        if (wp_doing_ajax()) {
            return;
        }

        $result = $this->save_profile_request();
        $code = is_wp_error($result) ? $result->get_error_code() : 'success_profile';
        MJB_Notices::redirect(self::get_page_url(), $code);
    }

    /**
     * Save the profile form without leaving the page.
     */
    public function ajax_save_profile()
    {
        $result = $this->save_profile_request();
        if (is_wp_error($result)) {
            $code = $result->get_error_code();
            $data = array(
                'code' => $code,
                'message' => MJB_Notices::message($code),
            );
            if ($code === 'error_permission') {
                wp_send_json_error($data, is_user_logged_in() ? 403 : 401);
            }
            wp_send_json_error($data);
        }

        wp_send_json_success(array(
            'message' => MJB_Notices::message('success_profile'),
            'welcome' => $result['welcome'],
            'display' => $result['display'],
            'photo' => $result['photo'],
        ));
    }

    /**
     * Persist the posted profile. Shared by the full post and the AJAX save.
     *
     * @return array{welcome:string,display:string,photo:array<string,string>|null}|WP_Error
     */
    private function save_profile_request()
    {
        if (!isset($_POST['mjb_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_profile_nonce'])), 'mjb_profile_action')) {
            return new WP_Error('error_security', MJB_Notices::message('error_security'));
        }

        if (!is_user_logged_in()) {
            return new WP_Error('error_permission', MJB_Notices::message('error_permission'));
        }

        $user_id = get_current_user_id();
        $first_name = isset($_POST['mjb_first_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_first_name'])) : '';
        $last_name = isset($_POST['mjb_last_name']) ? sanitize_text_field(wp_unslash($_POST['mjb_last_name'])) : '';
        $headline = isset($_POST['mjb_headline']) ? sanitize_text_field(wp_unslash($_POST['mjb_headline'])) : '';
        $linkedin = isset($_POST['mjb_linkedin']) ? esc_url_raw(wp_unslash($_POST['mjb_linkedin'])) : '';
        $website = isset($_POST['mjb_website']) ? esc_url_raw(wp_unslash($_POST['mjb_website'])) : '';
        $phone_input = class_exists('MJB_Candidate_Registration')
            ? MJB_Candidate_Registration::sanitize_phone_submission()
            : array('phone' => '', 'country' => '');
        $phone = $phone_input['phone'];
        $phone_country = $phone_input['country'];
        $city = isset($_POST['mjb_city']) ? sanitize_text_field(wp_unslash($_POST['mjb_city'])) : '';
        $experience_company = isset($_POST['mjb_experience_company']) ? sanitize_text_field(wp_unslash($_POST['mjb_experience_company'])) : '';
        $is_current_role = !empty($_POST['mjb_is_current_role']) ? '1' : '0';
        $bio = isset($_POST['mjb_bio']) ? sanitize_textarea_field(wp_unslash($_POST['mjb_bio'])) : '';
        $bio = function_exists('mb_substr') ? mb_substr($bio, 0, self::BIO_MAX) : substr($bio, 0, self::BIO_MAX);
        $open_to_work = !empty($_POST['mjb_open_to_work']) ? '1' : '0';
        $remote_ok = !empty($_POST['mjb_remote_ok']) ? '1' : '0';
        $is_public = !empty($_POST['mjb_is_public']) ? '1' : '0';

        if ($first_name === '' || $last_name === '') {
            return new WP_Error('error_missing_fields', MJB_Notices::message('error_missing_fields'));
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
        if ($phone_country !== '') {
            update_user_meta($user_id, '_candidate_phone_country', $phone_country);
        } elseif ($phone === '') {
            delete_user_meta($user_id, '_candidate_phone_country');
        }
        update_user_meta($user_id, '_candidate_city', $city);
        update_user_meta($user_id, '_candidate_experience_company', $experience_company);
        update_user_meta($user_id, '_candidate_is_current_role', $is_current_role);
        update_user_meta($user_id, '_candidate_bio', $bio);
        update_user_meta($user_id, '_candidate_open_to_work', $open_to_work);
        update_user_meta($user_id, '_candidate_remote_ok', $remote_ok);
        update_user_meta($user_id, '_candidate_is_public', $is_public);

        $uploaded_photo = false;
        if (!empty($_FILES['mjb_photo']['name']) && class_exists('MJB_Private_Uploads')) {
            $original_name = sanitize_file_name(wp_unslash($_FILES['mjb_photo']['name']));
            $photo_upload = MJB_Private_Uploads::upload($_FILES['mjb_photo'], MJB_Private_Uploads::TYPE_CANDIDATE_PHOTO);
            if (is_wp_error($photo_upload)) {
                $code = $photo_upload->get_error_code() === 'invalid_type' ? 'error_invalid_photo' : 'error_photo_upload';
                return new WP_Error($code, MJB_Notices::message($code));
            }

            $old = get_user_meta($user_id, '_candidate_photo_path', true);
            if ($old) {
                MJB_Private_Uploads::delete($old);
            }

            update_user_meta($user_id, '_candidate_photo_path', $photo_upload['file']);
            update_user_meta($user_id, '_candidate_photo_name', $original_name);
            update_user_meta($user_id, '_candidate_photo_uploaded', time());
            if (!empty($photo_upload['relative'])) {
                update_user_meta($user_id, '_candidate_photo_relative', $photo_upload['relative']);
            } else {
                delete_user_meta($user_id, '_candidate_photo_relative');
            }
            if (!empty($photo_upload['url'])) {
                update_user_meta($user_id, '_candidate_photo_url', $photo_upload['url']);
            } else {
                delete_user_meta($user_id, '_candidate_photo_url');
            }
            $uploaded_photo = true;
        }

        do_action('mjb_candidate_profile_updated', $user_id, array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'headline' => $headline,
        ));

        $user = get_userdata($user_id);
        $profile = $user ? $this->load_profile($user_id, $user) : array();
        $photo = null;
        if ($uploaded_photo) {
            $photo = array(
                'url' => isset($profile['photo_url']) ? (string) $profile['photo_url'] : '',
                'name' => isset($profile['photo']['name']) ? (string) $profile['photo']['name'] : '',
                'meta' => isset($profile['photo']['meta']) ? (string) $profile['photo']['meta'] : '',
            );
        }

        return array(
            'welcome' => isset($profile['welcome']) ? (string) $profile['welcome'] : $first_name,
            'display' => isset($profile['display']) ? (string) $profile['display'] : trim($first_name . ' ' . $last_name),
            'photo' => $photo,
        );
    }

    /**
     * Handle Resume Upload.
     */
    public function handle_resume_upload()
    {
        if (!isset($_POST['mjb_upload_resume']) || !isset($_POST['mjb_resume_nonce'])) {
            return;
        }
        if (wp_doing_ajax() && !doing_action('wp_ajax_mjb_upload_resume')) {
            return;
        }

        $redirect_url = self::get_page_url();

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_resume_nonce'])), 'mjb_resume_action')) {
            $this->finish_request( 'error_security');
        }

        if (!is_user_logged_in()) {
            $this->finish_request( 'error_permission');
        }

        if (empty($_FILES['mjb_resume']['name'])) {
            $this->finish_request( 'error_resume_required');
        }

        $original_name = sanitize_file_name(wp_unslash($_FILES['mjb_resume']['name']));
        $uploaded = MJB_Resumes::upload_file($_FILES['mjb_resume'], 'candidate_profile');
        if (is_wp_error($uploaded)) {
            $code = $uploaded->get_error_code() === 'invalid_type' ? 'error_invalid_resume' : 'error_resume_upload';
            $this->finish_request( $code);
        }

        $user_id = get_current_user_id();
        $old_resume_id = intval(get_user_meta($user_id, '_candidate_resume_id', true));

        $resume_id = MJB_Resumes::create_resume_post($user_id, $uploaded, $original_name);
        if (is_wp_error($resume_id)) {
            MJB_Private_Uploads::delete($uploaded['file']);
            $this->finish_request( 'error_resume_upload');
        }

        // Only remove the previous profile resume when no application still needs it.
        if ($old_resume_id) {
            MJB_Resumes::maybe_retire_profile_resume($old_resume_id);
        }

        $this->finish_request('success_resume', array(
            'name' => $original_name,
            'resume_url' => class_exists('MJB_Resumes') ? MJB_Resumes::get_resume_display_url($resume_id) : '',
            'ext' => strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION)),
        ));
    }

    /**
     * Replace the profile photo from its own drop zone.
     */
    public function handle_photo_upload()
    {
        if (!isset($_POST['mjb_upload_photo']) || !isset($_POST['mjb_photo_nonce'])) {
            return;
        }
        if (wp_doing_ajax() && !doing_action('wp_ajax_mjb_upload_photo')) {
            return;
        }

        $redirect_url = self::get_page_url();

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_photo_nonce'])), 'mjb_photo_action')) {
            $this->finish_request( 'error_security');
        }

        if (!is_user_logged_in() || !class_exists('MJB_Private_Uploads')) {
            $this->finish_request( 'error_permission');
        }

        if (empty($_FILES['mjb_photo']['name'])) {
            $this->finish_request( 'error_photo_upload');
        }

        $original_name = sanitize_file_name(wp_unslash($_FILES['mjb_photo']['name']));
        $photo_upload = MJB_Private_Uploads::upload($_FILES['mjb_photo'], MJB_Private_Uploads::TYPE_CANDIDATE_PHOTO);
        if (is_wp_error($photo_upload)) {
            $code = $photo_upload->get_error_code() === 'invalid_type' ? 'error_invalid_photo' : 'error_photo_upload';
            $this->finish_request( $code);
        }

        $user_id = get_current_user_id();
        $old = get_user_meta($user_id, '_candidate_photo_path', true);
        if ($old) {
            MJB_Private_Uploads::delete($old);
        }

        update_user_meta($user_id, '_candidate_photo_path', $photo_upload['file']);
        update_user_meta($user_id, '_candidate_photo_name', $original_name);
        update_user_meta($user_id, '_candidate_photo_uploaded', time());
        if (!empty($photo_upload['relative'])) {
            update_user_meta($user_id, '_candidate_photo_relative', $photo_upload['relative']);
        } else {
            delete_user_meta($user_id, '_candidate_photo_relative');
        }
        if (!empty($photo_upload['url'])) {
            update_user_meta($user_id, '_candidate_photo_url', $photo_upload['url']);
        } else {
            delete_user_meta($user_id, '_candidate_photo_url');
        }

        $this->finish_request( 'success_photo');
    }

    /**
     * Remove the stored profile photo.
     */
    public function handle_photo_delete()
    {
        if (!isset($_POST['mjb_delete_photo']) || !isset($_POST['mjb_photo_delete_nonce'])) {
            return;
        }
        if (wp_doing_ajax() && !doing_action('wp_ajax_mjb_delete_photo')) {
            return;
        }

        $redirect_url = self::get_page_url();

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_photo_delete_nonce'])), 'mjb_photo_delete_action')) {
            $this->finish_request( 'error_security');
        }

        if (!is_user_logged_in()) {
            $this->finish_request( 'error_permission');
        }

        $user_id = get_current_user_id();
        $this->delete_profile_photo($user_id);
        $user = get_userdata($user_id);
        $profile = $user ? $this->load_profile($user_id, $user) : array();
        $this->finish_request('success_photo_deleted', array(
            'initials' => isset($profile['initials']) ? (string) $profile['initials'] : '',
        ));
    }

    /**
     * Upload a CV without leaving the dashboard.
     */
    public function ajax_upload_resume()
    {
        $this->handle_resume_upload();
    }

    /**
     * Remove the profile photo without leaving the dashboard.
     */
    public function ajax_delete_photo()
    {
        $this->handle_photo_delete();
    }

    /**
     * Remove the profile CV without leaving the dashboard.
     */
    public function ajax_delete_resume()
    {
        $this->handle_resume_delete();
    }

    /**
     * Remove the CV stored on the candidate profile.
     *
     * Applications already sent keep their own copy of the file.
     */
    public function handle_resume_delete()
    {
        if (!isset($_POST['mjb_delete_resume']) || !isset($_POST['mjb_resume_delete_nonce'])) {
            return;
        }
        if (wp_doing_ajax() && !doing_action('wp_ajax_mjb_delete_resume')) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_resume_delete_nonce'])), 'mjb_resume_delete_action')) {
            $this->finish_request('error_security');
        }

        if (!is_user_logged_in()) {
            $this->finish_request('error_permission');
        }

        $typed = isset($_POST['mjb_delete_confirm']) ? wp_unslash($_POST['mjb_delete_confirm']) : '';
        $confirmed = class_exists('MJB_Candidate_Account') && MJB_Candidate_Account::deletion_confirmed($typed);
        if (!$confirmed) {
            $this->finish_request('error_resume_delete_confirm');
        }

        $user_id = get_current_user_id();
        $resume_id = (int) get_user_meta($user_id, '_candidate_resume_id', true);
        delete_user_meta($user_id, '_candidate_resume_id');

        if ($resume_id > 0 && class_exists('MJB_Resumes')) {
            $owner = (int) get_post_meta($resume_id, '_candidate_user_id', true);
            if ($owner <= 0) {
                $owner = (int) get_post_field('post_author', $resume_id);
            }
            if ($owner === $user_id) {
                MJB_Resumes::maybe_retire_profile_resume($resume_id);
            }
        }

        $this->finish_request('success_resume_deleted');
    }

    /**
     * @param string               $code
     * @param array<string, mixed> $extra
     */
    private function finish_request($code, $extra = array())
    {
        $payload = array_merge(array(
            'code' => $code,
            'message' => class_exists('MJB_Notices') ? MJB_Notices::message($code) : '',
        ), $extra);
        if (wp_doing_ajax()) {
            if (strpos((string) $code, 'error_') === 0) {
                wp_send_json_error($payload);
            }
            wp_send_json_success($payload);
        }
        MJB_Notices::redirect(self::get_page_url(), $code);
    }

    /**
     * Delete the candidate's photo file and the meta that points at it.
     *
     * @param int $user_id
     */
    private function delete_profile_photo($user_id)
    {
        $user_id = (int) $user_id;
        $old = (string) get_user_meta($user_id, '_candidate_photo_path', true);
        if ($old !== '' && class_exists('MJB_Private_Uploads')) {
            MJB_Private_Uploads::delete($old);
        }
        delete_user_meta($user_id, '_candidate_photo_path');
        delete_user_meta($user_id, '_candidate_photo_relative');
        delete_user_meta($user_id, '_candidate_photo_url');
        delete_user_meta($user_id, '_candidate_photo_name');
        delete_user_meta($user_id, '_candidate_photo_uploaded');
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

        $profile = $this->load_profile($user_id, $user);
        $saved_jobs = class_exists('MJB_Saved_Jobs') ? MJB_Saved_Jobs::get_jobs($user_id) : null;
        $applications = $this->query_applications($user);
        $strength = $this->profile_strength($profile);
        $saved_count = is_array($saved_jobs) ? count($saved_jobs) : 0;
        $app_count = count($applications);
        $jobs_url = class_exists('MJB_Page_Resolver') ? MJB_Page_Resolver::get_jobs_page_url() : home_url('/jobs/');
        $login_url = class_exists('MJB_Page_Resolver')
            ? MJB_Page_Resolver::get_page_url('mjb_candidate_login', 'mjb_candidate_login_page_id', array(), '/jobs/candidate-login/')
            : home_url('/jobs/candidate-login/');
        $layer = (isset($_GET['mjb_panel']) && sanitize_key(wp_unslash($_GET['mjb_panel'])) === 'account') ? 'account' : 'home';

        ob_start();
        ?>
        <div class="mjb-portal-dashboard mjb-candidate-dashboard" data-bio-complete="<?php echo esc_attr((string) self::BIO_COMPLETE); ?>">
            <?php $this->render_identity_bar($profile, $jobs_url, wp_logout_url($login_url)); ?>
            <?php $this->render_nav($saved_count, $app_count, $layer); ?>

            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in MJB_Notices::render().
            echo MJB_Notices::render();
            ?>

            <div class="mjb-cd-stage" id="mjb-cd-stage" data-active="<?php echo esc_attr($layer); ?>">
                <div class="mjb-cd-layer<?php echo $layer === 'home' ? ' is-active' : ''; ?>" id="mjb-cd-layer-home" data-layer="home" data-loaded="<?php echo $layer === 'home' ? '1' : '0'; ?>"<?php echo $layer === 'home' ? '' : ' hidden'; ?>>
                    <?php
                    if ($layer === 'home') {
                        $this->render_home_layer($user_id, $user, $profile, $strength, $saved_jobs, $saved_count, $applications, $app_count, $jobs_url);
                    }
                    ?>
                </div>
                <div class="mjb-cd-layer<?php echo $layer === 'account' ? ' is-active' : ''; ?>" id="mjb-cd-layer-account" data-layer="account" data-loaded="<?php echo $layer === 'account' ? '1' : '0'; ?>"<?php echo $layer === 'account' ? '' : ' hidden'; ?>>
                    <?php
                    if ($layer === 'account' && class_exists('MJB_Candidate_Account')) {
                        MJB_Candidate_Account::render($user);
                    }
                    ?>
                </div>
                <div class="mjb-cd-layer-pending" id="mjb-cd-layer-pending" hidden></div>
            </div>
            <dialog class="mjb-cd-dialog mjb-cd-dialog--cv" id="mjb-cd-cv-dialog" aria-labelledby="mjb-cd-cv-title">
                <div class="mjb-cd-cv-head">
                    <h3 id="mjb-cd-cv-title"><?php esc_html_e('CV', 'modern-job-board'); ?></h3>
                    <button type="button" class="mjb-cd-sq" data-mjb-cv-close aria-label="<?php esc_attr_e('Close', 'modern-job-board'); ?>">
                        <?php echo $this->icon('x', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                </div>
                <iframe class="mjb-cd-cv-frame" id="mjb-cd-cv-frame" title="<?php esc_attr_e('CV preview', 'modern-job-board'); ?>" hidden></iframe>
                <p class="mjb-cd-cv-fallback" id="mjb-cd-cv-fallback" hidden>
                    <?php esc_html_e('Word documents open in your own app. Use Download to view this CV.', 'modern-job-board'); ?>
                </p>
            </dialog>
            <?php $this->render_cv_delete_dialog($profile); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Candidate fields used by the dashboard.
     *
     * @param int     $user_id
     * @param WP_User $user
     * @return array<string, mixed>
     */
    private function load_profile($user_id, $user)
    {
        $first_name = (string) get_user_meta($user_id, 'first_name', true);
        $last_name = (string) get_user_meta($user_id, 'last_name', true);
        $photo_path = (string) get_user_meta($user_id, '_candidate_photo_path', true);
        $photo_url = $photo_path && class_exists('MJB_Private_Uploads') ? MJB_Private_Uploads::get_public_url($photo_path) : '';
        if (!$photo_url) {
            $photo_url = (string) get_user_meta($user_id, '_candidate_photo_url', true);
        }

        $resume_id = (int) get_user_meta($user_id, '_candidate_resume_id', true);
        $resume_url = class_exists('MJB_Resumes') ? MJB_Resumes::get_resume_display_url($resume_id) : '';
        $phone_country = (string) get_user_meta($user_id, '_candidate_phone_country', true);

        $display = trim($first_name . ' ' . $last_name);
        if ($display === '' && $user) {
            $display = (string) $user->display_name;
        }

        return array(
            'user_id' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display' => $display,
            'welcome' => $first_name !== '' ? $first_name : $display,
            'initials' => $this->initials($first_name, $last_name, $display),
            'headline' => (string) get_user_meta($user_id, '_candidate_headline', true),
            'linkedin' => (string) get_user_meta($user_id, '_candidate_linkedin', true),
            'website' => (string) get_user_meta($user_id, '_candidate_website', true),
            'phone' => (string) get_user_meta($user_id, '_candidate_phone', true),
            'phone_iso' => (strlen($phone_country) === 2) ? $phone_country : 'auto',
            'city' => (string) get_user_meta($user_id, '_candidate_city', true),
            'company' => (string) get_user_meta($user_id, '_candidate_experience_company', true),
            'is_current_role' => (string) get_user_meta($user_id, '_candidate_is_current_role', true),
            'bio' => (string) get_user_meta($user_id, '_candidate_bio', true),
            'open_to_work' => (string) get_user_meta($user_id, '_candidate_open_to_work', true),
            'remote_ok' => (string) get_user_meta($user_id, '_candidate_remote_ok', true),
            'is_public' => (string) get_user_meta($user_id, '_candidate_is_public', true),
            'photo_url' => $photo_url,
            'has_photo' => $photo_path !== '' || $photo_url !== '',
            'photo' => $this->photo_file_meta($user_id, $photo_path),
            'resume_id' => $resume_id,
            'resume_url' => $resume_url,
            'has_resume' => $resume_url !== '',
            'resume' => $this->resume_file_meta($resume_id),
        );
    }

    /**
     * Two-letter mark from the candidate's name.
     *
     * @param string $first
     * @param string $last
     * @param string $display
     * @return string
     */
    private function initials($first, $last, $display)
    {
        $chars = '';
        if ($first !== '') {
            $chars .= function_exists('mb_substr') ? mb_substr($first, 0, 1) : substr($first, 0, 1);
        }
        if ($last !== '') {
            $chars .= function_exists('mb_substr') ? mb_substr($last, 0, 1) : substr($last, 0, 1);
        }
        if ($chars === '' && $display !== '') {
            $chars = function_exists('mb_substr') ? mb_substr($display, 0, 1) : substr($display, 0, 1);
        }
        return function_exists('mb_strtoupper') ? mb_strtoupper($chars) : strtoupper($chars);
    }

    /**
     * Filename, size, and upload date for the profile CV.
     *
     * @param int $resume_id
     * @return array{name:string,meta:string,ext:string}
     */
    private function resume_file_meta($resume_id)
    {
        $empty = array('name' => '', 'meta' => '', 'ext' => '');
        $resume_id = (int) $resume_id;
        if ($resume_id <= 0) {
            return $empty;
        }

        $post = get_post($resume_id);
        if (!$post) {
            return $empty;
        }

        $name = (string) $post->post_title;
        $author = get_userdata((int) $post->post_author);
        if ($author && $author->display_name !== '') {
            $suffix = ' - ' . $author->display_name;
            $suffix_len = strlen($suffix);
            if ($suffix_len > 0 && substr($name, -$suffix_len) === $suffix) {
                $name = substr($name, 0, -$suffix_len);
            }
        }
        if ($name === '') {
            $name = __('CV', 'modern-job-board');
        }

        $path = '';
        if ($post->post_type === 'mjb_resume' && class_exists('MJB_Resumes')) {
            $path = MJB_Resumes::get_resume_post_file_path($resume_id);
        } elseif ($post->post_type === 'attachment') {
            $path = (string) get_attached_file($resume_id);
        }

        $ext = '';
        $parts = array();
        if ($path !== '') {
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if ($ext !== '') {
                $parts[] = strtoupper($ext);
            }
            if (file_exists($path)) {
                $parts[] = size_format((int) filesize($path));
            }
        }
        $uploaded = get_the_date('', $post);
        if ($uploaded) {
            $parts[] = sprintf(
                /* translators: %s: upload date */
                __('Uploaded %s', 'modern-job-board'),
                $uploaded
            );
        }

        return array(
            'name' => $name,
            'meta' => implode(' · ', $parts),
            'ext' => $ext,
        );
    }

    /**
     * Filename, size, and upload date for the profile photo.
     *
     * @param int    $user_id
     * @param string $path
     * @return array{name:string,meta:string}
     */
    private function photo_file_meta($user_id, $path)
    {
        $empty = array('name' => '', 'meta' => '');
        $path = (string) $path;
        $name = (string) get_user_meta($user_id, '_candidate_photo_name', true);
        $ext = strtolower((string) pathinfo($path !== '' ? $path : $name, PATHINFO_EXTENSION));
        if ($name === '') {
            $name = __('Profile photo', 'modern-job-board');
            if ($ext !== '') {
                $name .= '.' . $ext;
            }
        }
        if ($path === '' && $name === '') {
            return $empty;
        }

        $parts = array();
        $label = $ext;
        if ($ext === 'jpg' || $ext === 'jpeg' || $ext === 'jpe') {
            $label = 'JPG';
        } elseif ($ext === 'png') {
            $label = 'PNG';
        } elseif ($ext === 'webp') {
            $label = 'WEBP';
        } elseif ($ext !== '') {
            $label = strtoupper($ext);
        }
        if ($label !== '') {
            $parts[] = $label;
        }
        if ($path !== '' && file_exists($path)) {
            $parts[] = size_format((int) filesize($path));
        }

        $uploaded = (int) get_user_meta($user_id, '_candidate_photo_uploaded', true);
        if ($uploaded <= 0 && $path !== '' && file_exists($path)) {
            $uploaded = (int) filemtime($path);
        }
        if ($uploaded > 0) {
            $parts[] = sprintf(
                /* translators: %s: upload date */
                __('Uploaded %s', 'modern-job-board'),
                function_exists('wp_date') ? wp_date(get_option('date_format'), $uploaded) : gmdate('F j, Y', $uploaded)
            );
        }

        return array(
            'name' => $name,
            'meta' => implode(' · ', $parts),
        );
    }

    /**
     * Profile checklist backed by stored candidate fields.
     *
     * @param array<string, mixed> $profile
     * @return array{items:array<int, array<string, mixed>>,done:int,total:int,left:int,percent:int,next:string}
     */
    private function profile_strength($profile)
    {
        $bio = trim((string) $profile['bio']);
        $bio_len = function_exists('mb_strlen') ? mb_strlen($bio) : strlen($bio);
        $items = array(
            array(
                'key' => 'name',
                'label' => __('First and last name', 'modern-job-board'),
                'done' => $profile['first_name'] !== '' && $profile['last_name'] !== '',
                'jump' => 'mjb_first_name',
            ),
            array(
                'key' => 'city',
                'label' => __('City', 'modern-job-board'),
                'done' => trim((string) $profile['city']) !== '',
                'jump' => 'mjb_city',
            ),
            array(
                'key' => 'phone',
                'label' => __('Phone number', 'modern-job-board'),
                'done' => trim((string) $profile['phone']) !== '',
                'jump' => 'mjb_phone_national',
            ),
            array(
                'key' => 'company',
                'label' => __('Company', 'modern-job-board'),
                'done' => trim((string) $profile['company']) !== '',
                'jump' => 'mjb_experience_company',
            ),
            array(
                'key' => 'title',
                'label' => __('Current job title', 'modern-job-board'),
                'done' => trim((string) $profile['headline']) !== '',
                'jump' => 'mjb_headline',
            ),
            array(
                'key' => 'bio',
                'label' => __('Short bio', 'modern-job-board'),
                'done' => $bio_len >= self::BIO_COMPLETE,
                'jump' => 'mjb_bio',
            ),
            array(
                'key' => 'prefs',
                'label' => __('Job preferences', 'modern-job-board'),
                'done' => true,
                'jump' => 'mjb_open_to_work',
            ),
            array(
                'key' => 'cv',
                'label' => __('CV uploaded', 'modern-job-board'),
                'done' => !empty($profile['has_resume']),
                'jump' => 'mjb-cd-cv',
            ),
        );

        $done = 0;
        $next = '';
        foreach ($items as $item) {
            if (!empty($item['done'])) {
                $done++;
            } elseif ($next === '') {
                $next = (string) $item['jump'];
            }
        }
        $total = count($items);

        return array(
            'items' => $items,
            'done' => $done,
            'total' => $total,
            'left' => $total - $done,
            'percent' => $total > 0 ? (int) round(100 * $done / $total) : 0,
            'next' => $next,
        );
    }

    /**
     * @param string $name
     * @param int    $size
     * @return string
     */
    private function icon($name, $size = 20)
    {
        if (!class_exists('MJB_Icons')) {
            return '';
        }
        return MJB_Icons::render($name, $size);
    }

    /**
     * Avatar mark or uploaded photo.
     *
     * @param array<string, mixed> $profile
     * @param string               $modifier
     */
    private function render_avatar($profile, $modifier = '')
    {
        $class = 'mjb-cd-avatar' . ($modifier !== '' ? ' ' . $modifier : '');
        echo '<span class="' . esc_attr($class) . '" id="mjb-cd-avatar" aria-hidden="true">';
        if (!empty($profile['photo_url'])) {
            echo '<img src="' . esc_url((string) $profile['photo_url']) . '" alt="">';
        } else {
            echo esc_html((string) $profile['initials']);
        }
        echo '</span>';
    }

    /**
     * @param array<string, mixed> $profile
     * @param string               $jobs_url
     * @param string               $logout_url
     */
    private function render_identity_bar($profile, $jobs_url, $logout_url)
    {
        ?>
        <div class="mjb-cd-top">
            <span class="mjb-cd-me">
                <?php $this->render_avatar($profile); ?>
                <span class="mjb-cd-me-name" id="mjb-cd-me-name"><?php echo esc_html((string) $profile['display']); ?></span>
            </span>
            <span class="mjb-cd-top-spacer"></span>
            <div class="mjb-cd-top-actions">
                <a class="btn btn-primary" href="<?php echo esc_url($jobs_url); ?>">
                    <?php echo $this->icon('search', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php esc_html_e('Browse jobs', 'modern-job-board'); ?>
                </a>
                <a class="btn btn-outline mjb-cd-icon-btn" href="<?php echo esc_url($logout_url); ?>" aria-label="<?php esc_attr_e('Sign out', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Sign out', 'modern-job-board'); ?>" data-tip-pos="left">
                    <?php echo $this->icon('log-out', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * In-page section nav. Account settings sits after the profile sections.
     *
     * @param int    $saved_count
     * @param int    $app_count
     * @param string $layer home|account
     */
    private function render_nav($saved_count, $app_count, $layer = 'home')
    {
        $tabs = array(
            array('id' => 'mjb-cd-overview', 'layer' => 'home', 'icon' => 'user', 'label' => __('Overview', 'modern-job-board'), 'count' => null),
            array('id' => 'mjb-cd-profile', 'layer' => 'home', 'icon' => 'list-checks', 'label' => __('Profile', 'modern-job-board'), 'count' => null),
            array('id' => 'mjb-cd-saved', 'layer' => 'home', 'icon' => 'bookmark', 'label' => __('Saved jobs', 'modern-job-board'), 'count' => $saved_count),
            array('id' => 'mjb-cd-applications', 'layer' => 'home', 'icon' => 'inbox', 'label' => __('Applications', 'modern-job-board'), 'count' => $app_count),
            array('id' => 'mjb-cd-cv', 'layer' => 'home', 'icon' => 'file-text', 'label' => __('CV', 'modern-job-board'), 'count' => null),
        );
        $account_on = $layer === 'account';
        ?>
        <div class="mjb-cd-navbar">
            <button class="mjb-cd-burger" id="mjb-cd-nav-toggle" type="button" aria-expanded="false" aria-controls="mjb-cd-nav">
                <?php echo $this->icon('menu', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span><?php esc_html_e('Menu', 'modern-job-board'); ?></span>
                <?php echo $this->icon('chevron-down', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
            <nav id="mjb-cd-nav" aria-label="<?php esc_attr_e('Your account', 'modern-job-board'); ?>">
                <?php foreach ($tabs as $index => $tab) : ?>
                    <?php $on = !$account_on && $index === 0; ?>
                    <a class="mjb-cd-tab<?php echo $on ? ' is-active' : ''; ?>" href="#<?php echo esc_attr($tab['id']); ?>" data-layer="<?php echo esc_attr($tab['layer']); ?>" data-section="<?php echo esc_attr($tab['id']); ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>>
                        <?php echo $this->icon($tab['icon'], 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo esc_html($tab['label']); ?>
                        <?php if ($tab['count'] !== null) : ?>
                            <span class="mjb-cd-count"><?php echo esc_html((string) $tab['count']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <span class="mjb-cd-nav-rule" aria-hidden="true"></span>
                <a class="mjb-cd-tab mjb-cd-tab--account<?php echo $account_on ? ' is-active' : ''; ?>" href="#mjb-cd-account" data-layer="account" data-section="mjb-cd-account"<?php echo $account_on ? ' aria-current="page"' : ''; ?>>
                    <?php echo $this->icon('settings', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php esc_html_e('Account settings', 'modern-job-board'); ?>
                </a>
            </nav>
        </div>
        <?php
    }

    /**
     * Overview, profile, CV, saved jobs, and applications. Account is a separate layer.
     *
     * @param int                  $user_id
     * @param WP_User              $user
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $strength
     * @param mixed                $saved_jobs
     * @param int                  $saved_count
     * @param array<int, WP_Post>  $applications
     * @param int                  $app_count
     * @param string               $jobs_url
     */
    private function render_home_layer($user_id, $user, $profile, $strength, $saved_jobs, $saved_count, $applications, $app_count, $jobs_url)
    {
        $this->render_overview($profile, $strength, $saved_count, $app_count);
        /**
         * Before profile form (e.g. completeness progress bar).
         *
         * @param int $user_id
         */
        do_action('mjb_candidate_dashboard_before_profile', $user_id);
        ?>
        <div class="mjb-cd-sec" id="mjb-cd-profile">
            <h2><?php echo $this->icon('list-checks', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Profile', 'modern-job-board'); ?></h2>
        </div>
        <div class="mjb-cd-cols mjb-cd-cols--main">
            <?php $this->render_profile_form($profile); ?>
            <aside class="mjb-cd-side">
                <?php $this->render_strength_panel($strength); ?>
                <?php $this->render_cv_panel($profile); ?>
            </aside>
        </div>
        <div class="mjb-cd-sec" id="mjb-cd-activity">
            <h2><?php echo $this->icon('inbox', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Activity', 'modern-job-board'); ?></h2>
        </div>
        <div class="mjb-cd-cols mjb-cd-cols--half">
            <?php $this->render_saved_panel($user_id, $saved_jobs, $saved_count, $jobs_url); ?>
            <?php $this->render_applications_panel($user, $applications, $app_count, $jobs_url); ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $strength
     * @param int                  $saved_count
     * @param int                  $app_count
     */
    private function render_overview($profile, $strength, $saved_count, $app_count)
    {
        $left = (int) $strength['left'];
        $visible = $profile['is_public'] === '1';
        $pct = (int) $strength['percent'];
        ?>
        <div class="mjb-cd-sec" id="mjb-cd-overview">
            <h2>
                <?php echo $this->icon('user', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: candidate first name or display name */
                        __('Welcome, %s', 'modern-job-board'),
                        '<span id="mjb-cd-welcome">' . esc_html((string) $profile['welcome']) . '</span>'
                    ),
                    array('span' => array('id' => true))
                );
                ?>
            </h2>
            <span class="mjb-cd-hint" id="mjb-cd-steps"<?php echo $left === 0 ? ' hidden' : ''; ?>>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: incomplete profile items */
                    _n('%d step to a complete profile', '%d steps to a complete profile', $left, 'modern-job-board'),
                    $left
                ));
                ?>
            </span>
        </div>

        <div class="mjb-cd-stats" role="group" aria-label="<?php esc_attr_e('Profile overview', 'modern-job-board'); ?>">
            <div class="mjb-cd-stat">
                <div class="mjb-cd-label"><?php esc_html_e('Profile strength', 'modern-job-board'); ?></div>
                <div class="mjb-cd-stat-row">
                    <span class="mjb-cd-num<?php echo $pct <= 0 ? ' is-zero' : ''; ?>" id="mjb-cd-pct"><?php echo esc_html((string) $pct); ?>%</span>
                    <svg class="mjb-cd-ring" viewBox="0 0 44 44" aria-hidden="true">
                        <circle class="mjb-cd-ring-track" cx="22" cy="22" r="18" stroke-width="5"></circle>
                        <circle class="mjb-cd-ring-arc" id="mjb-cd-ring" cx="22" cy="22" r="18" stroke-width="5" pathLength="100" stroke-dasharray="<?php echo esc_attr((string) $pct); ?> 100"></circle>
                    </svg>
                </div>
                <div class="mjb-cd-sub" id="mjb-cd-pct-sub"><?php $this->render_strength_sub($strength); ?></div>
            </div>
            <div class="mjb-cd-stat">
                <div class="mjb-cd-label"><?php esc_html_e('Saved jobs', 'modern-job-board'); ?></div>
                <div class="mjb-cd-stat-row">
                    <span class="mjb-cd-num<?php echo $saved_count <= 0 ? ' is-zero' : ''; ?>"><?php echo esc_html((string) $saved_count); ?></span>
                    <span class="mjb-cd-stat-ic" aria-hidden="true"><?php echo $this->icon('bookmark', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </div>
                <div class="mjb-cd-sub"><?php esc_html_e('Jobs you have bookmarked', 'modern-job-board'); ?></div>
            </div>
            <div class="mjb-cd-stat">
                <div class="mjb-cd-label"><?php esc_html_e('Applications', 'modern-job-board'); ?></div>
                <div class="mjb-cd-stat-row">
                    <span class="mjb-cd-num<?php echo $app_count <= 0 ? ' is-zero' : ''; ?>"><?php echo esc_html((string) $app_count); ?></span>
                    <span class="mjb-cd-stat-ic" aria-hidden="true"><?php echo $this->icon('inbox', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </div>
                <div class="mjb-cd-sub"><?php esc_html_e('Every application is tracked here', 'modern-job-board'); ?></div>
            </div>
            <div class="mjb-cd-stat">
                <div class="mjb-cd-label"><?php esc_html_e('Recruiter visibility', 'modern-job-board'); ?></div>
                <div class="mjb-cd-stat-row">
                    <span class="mjb-cd-num mjb-cd-num--word<?php echo $visible ? '' : ' is-off'; ?>" id="mjb-cd-vis"><?php echo esc_html($visible ? __('Visible', 'modern-job-board') : __('Hidden', 'modern-job-board')); ?></span>
                    <span class="mjb-cd-stat-ic<?php echo $visible ? '' : ' is-off'; ?>" id="mjb-cd-vis-ic" aria-hidden="true">
                        <span class="mjb-cd-vis-on"<?php echo $visible ? '' : ' hidden'; ?>><?php echo $this->icon('eye', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="mjb-cd-vis-off"<?php echo $visible ? ' hidden' : ''; ?>><?php echo $this->icon('eye-off', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    </span>
                </div>
                <div class="mjb-cd-sub" id="mjb-cd-vis-sub"><?php $this->render_visibility_sub($visible); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $strength
     */
    private function render_strength_sub($strength)
    {
        $left = (int) $strength['left'];
        if ($left <= 0) {
            echo '<strong>' . esc_html__('Complete', 'modern-job-board') . '</strong> — ' . esc_html__('recruiters see your full profile', 'modern-job-board');
            return;
        }
        $word = sprintf(
            /* translators: %d: incomplete profile items */
            _n('%d step', '%d steps', $left, 'modern-job-board'),
            $left
        );
        echo '<strong>' . esc_html($word) . '</strong> ' . esc_html__('left', 'modern-job-board');
        if (!empty($strength['next'])) {
            echo ' · <a href="#' . esc_attr((string) $strength['next']) . '" data-mjb-jump="' . esc_attr((string) $strength['next']) . '"><span class="mjb-cd-u">' . esc_html__('Finish now', 'modern-job-board') . '</span></a>';
        }
    }

    /**
     * @param bool $visible
     */
    private function render_visibility_sub($visible)
    {
        echo esc_html($visible
            ? __('Recruiters can find you in the talent pool', 'modern-job-board')
            : __('You are not listed in the talent pool', 'modern-job-board'));
        echo ' · <a href="#mjb_is_public" data-mjb-jump="mjb_is_public"><span class="mjb-cd-u">' . esc_html__('Change', 'modern-job-board') . '</span></a>';
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function render_profile_form($profile)
    {
        $bio_len = function_exists('mb_strlen') ? mb_strlen((string) $profile['bio']) : strlen((string) $profile['bio']);
        ?>
        <div class="mjb-cd-panel mjb-cd-profile-card">
        <form method="post" action="" id="mjb-cd-profile-form" class="mjb-form mjb-cd-form" enctype="multipart/form-data" novalidate>
            <?php wp_nonce_field('mjb_profile_action', 'mjb_profile_nonce'); ?>
            <input type="file" name="mjb_photo" id="mjb_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="mjb-cd-sr" tabindex="-1" aria-label="<?php esc_attr_e('Upload your photo', 'modern-job-board'); ?>">
            <div class="mjb-cd-panel-head">
                <h3><?php echo $this->icon('user', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Profile details', 'modern-job-board'); ?></h3>
                <span class="mjb-cd-spacer"></span>
                <span class="mjb-cd-req"><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Required', 'modern-job-board'); ?></span>
            </div>

            <fieldset class="mjb-cd-group">
                <legend><?php esc_html_e('About you', 'modern-job-board'); ?></legend>
                <div class="mjb-cd-fields">
                    <div class="mjb-field">
                        <label for="mjb_first_name"><?php esc_html_e('First name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input class="mjb-cd-input" type="text" name="mjb_first_name" id="mjb_first_name" value="<?php echo esc_attr((string) $profile['first_name']); ?>" required aria-required="true" autocomplete="given-name">
                    </div>
                    <div class="mjb-field">
                        <label for="mjb_last_name"><?php esc_html_e('Last name', 'modern-job-board'); ?><?php echo MJB_Shortcodes::required_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input class="mjb-cd-input" type="text" name="mjb_last_name" id="mjb_last_name" value="<?php echo esc_attr((string) $profile['last_name']); ?>" required aria-required="true" autocomplete="family-name">
                    </div>
                    <div class="mjb-field mjb-field-city">
                        <label for="mjb_city"><?php esc_html_e('City', 'modern-job-board'); ?></label>
                        <?php
                        if (class_exists('MJB_Search')) {
                            $city_markup = MJB_Search::render_autocomplete_field(array(
                                'name' => 'mjb_city',
                                'type' => 'geocity',
                                'value' => (string) $profile['city'],
                                'label' => '',
                                'placeholder' => __('Start typing a city…', 'modern-job-board'),
                                'free_text' => true,
                                'input_id' => 'mjb_city',
                                'prefer_country' => '',
                            ));
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped in render_autocomplete_field().
                            echo $city_markup;
                        } else {
                            ?>
                            <input class="mjb-cd-input" type="text" name="mjb_city" id="mjb_city" value="<?php echo esc_attr((string) $profile['city']); ?>" autocomplete="address-level2" placeholder="<?php esc_attr_e('Start typing a city…', 'modern-job-board'); ?>">
                            <?php
                        }
                        ?>
                    </div>
                    <div class="mjb-field mjb-field-phone">
                        <label for="mjb_phone_national"><?php esc_html_e('Phone number', 'modern-job-board'); ?></label>
                        <?php
                        if (class_exists('MJB_Candidate_Registration')) {
                            $phone_value = (string) $profile['phone'];
                            $phone_country = (string) $profile['phone_iso'];
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes its own markup.
                            echo MJB_Candidate_Registration::render_phone_field(array(
                                'id' => 'mjb_phone',
                                'name' => 'mjb_phone',
                                'country_name' => 'mjb_phone_country',
                                'value' => esc_attr($phone_value),
                                'country' => esc_attr($phone_country),
                            ));
                        }
                        ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mjb-cd-group">
                <legend><?php esc_html_e('Current role', 'modern-job-board'); ?></legend>
                <div class="mjb-cd-fields">
                    <div class="mjb-field">
                        <label for="mjb_headline"><?php esc_html_e('Job title', 'modern-job-board'); ?></label>
                        <input class="mjb-cd-input" type="text" name="mjb_headline" id="mjb_headline" value="<?php echo esc_attr((string) $profile['headline']); ?>" placeholder="<?php esc_attr_e('e.g. Senior accountant', 'modern-job-board'); ?>" autocomplete="organization-title">
                    </div>
                    <div class="mjb-field">
                        <label for="mjb_experience_company"><?php esc_html_e('Company', 'modern-job-board'); ?></label>
                        <input class="mjb-cd-input" type="text" name="mjb_experience_company" id="mjb_experience_company" value="<?php echo esc_attr((string) $profile['company']); ?>" autocomplete="organization">
                    </div>
                    <div class="mjb-field mjb-cd-field-full">
                        <label class="mjb-cd-checkline" for="mjb_is_current_role">
                            <input type="checkbox" name="mjb_is_current_role" id="mjb_is_current_role" value="1" <?php checked($profile['is_current_role'], '1'); ?>>
                            <?php esc_html_e('I currently work here', 'modern-job-board'); ?>
                        </label>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mjb-cd-group">
                <legend><?php esc_html_e('Links', 'modern-job-board'); ?></legend>
                <div class="mjb-cd-fields">
                    <div class="mjb-field">
                        <label for="mjb_linkedin"><?php esc_html_e('LinkedIn profile', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input class="mjb-cd-input" type="url" name="mjb_linkedin" id="mjb_linkedin" value="<?php echo esc_attr((string) $profile['linkedin']); ?>" placeholder="https://" autocomplete="url">
                    </div>
                    <div class="mjb-field">
                        <label for="mjb_website"><?php esc_html_e('Website URL', 'modern-job-board'); ?><?php echo MJB_Shortcodes::optional_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
                        <input class="mjb-cd-input" type="url" name="mjb_website" id="mjb_website" value="<?php echo esc_attr((string) $profile['website']); ?>" placeholder="https://" autocomplete="url">
                    </div>
                </div>
            </fieldset>

            <fieldset class="mjb-cd-group">
                <legend><?php esc_html_e('Short bio', 'modern-job-board'); ?></legend>
                <div class="mjb-field">
                    <label class="mjb-cd-sr" for="mjb_bio"><?php esc_html_e('Short bio', 'modern-job-board'); ?></label>
                    <textarea class="mjb-cd-input" name="mjb_bio" id="mjb_bio" maxlength="<?php echo esc_attr((string) self::BIO_MAX); ?>" rows="4" placeholder="<?php esc_attr_e('Two or three sentences on what you do and the kind of role you want next.', 'modern-job-board'); ?>"><?php echo esc_textarea((string) $profile['bio']); ?></textarea>
                    <div class="mjb-cd-field-hint">
                        <span><?php esc_html_e('Recruiters read this on your public profile.', 'modern-job-board'); ?></span>
                        <span class="mjb-cd-bio-count" id="mjb-cd-bio-count"><?php echo esc_html((string) $bio_len . ' / ' . self::BIO_MAX); ?></span>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mjb-cd-group">
                <legend><?php esc_html_e('Job preferences', 'modern-job-board'); ?></legend>
                <label class="mjb-cd-pref" for="mjb_open_to_work">
                    <span class="mjb-cd-pref-txt">
                        <b><?php esc_html_e('Open to work', 'modern-job-board'); ?></b>
                        <span><?php esc_html_e('When your profile is public, recruiters can email you.', 'modern-job-board'); ?></span>
                    </span>
                    <input class="mjb-cd-switch" type="checkbox" role="switch" name="mjb_open_to_work" id="mjb_open_to_work" value="1" <?php checked($profile['open_to_work'], '1'); ?>>
                </label>
                <label class="mjb-cd-pref" for="mjb_remote_ok">
                    <span class="mjb-cd-pref-txt">
                        <b><?php esc_html_e('Interested in remote roles', 'modern-job-board'); ?></b>
                        <span><?php esc_html_e('Saved on your profile.', 'modern-job-board'); ?></span>
                    </span>
                    <input class="mjb-cd-switch" type="checkbox" role="switch" name="mjb_remote_ok" id="mjb_remote_ok" value="1" <?php checked($profile['remote_ok'], '1'); ?>>
                </label>
                <label class="mjb-cd-pref" for="mjb_is_public">
                    <span class="mjb-cd-pref-txt">
                        <b><?php esc_html_e('Show my profile to recruiters', 'modern-job-board'); ?></b>
                        <span><?php esc_html_e('When on, you appear in the talent pool.', 'modern-job-board'); ?></span>
                    </span>
                    <input class="mjb-cd-switch" type="checkbox" role="switch" name="mjb_is_public" id="mjb_is_public" value="1" <?php checked($profile['is_public'], '1'); ?>>
                </label>
            </fieldset>

        </form>

            <?php $this->render_photo_section($profile); ?>

            <div class="mjb-cd-savebar" id="mjb-cd-savebar">
                <span class="mjb-cd-save-status" aria-live="polite"><span class="mjb-cd-dot" aria-hidden="true"></span><span id="mjb-cd-save-text"><?php esc_html_e('All changes saved', 'modern-job-board'); ?></span></span>
                <button class="btn btn-outline" type="reset" id="mjb-cd-discard" form="mjb-cd-profile-form"><?php esc_html_e('Discard', 'modern-job-board'); ?></button>
                <button class="btn btn-primary" type="submit" name="mjb_update_profile" id="mjb-cd-save" value="1" form="mjb-cd-profile-form">
                    <span class="mjb-cd-btn-spin" hidden></span>
                    <span class="mjb-cd-save-label"><?php esc_html_e('Save changes', 'modern-job-board'); ?></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Profile photo file row and drop zone. The file input lives on the profile
     * form so Save changes stores the photo after the upload animation.
     *
     * @param array<string, mixed> $profile
     */
    private function render_photo_section($profile)
    {
        $photo = isset($profile['photo']) && is_array($profile['photo']) ? $profile['photo'] : array('name' => '', 'meta' => '');
        $has_photo = !empty($profile['has_photo']);
        ?>
        <fieldset class="mjb-cd-group mjb-cd-photo-group">
            <legend><?php esc_html_e('Photo', 'modern-job-board'); ?></legend>
            <div id="mjb-cd-photo-saved"<?php echo $has_photo ? '' : ' hidden'; ?>>
                <div class="mjb-cd-file">
                    <span class="mjb-cd-ficon">
                        <img id="mjb-cd-photo-img" alt=""<?php echo empty($profile['photo_url']) ? ' hidden' : ' src="' . esc_url((string) $profile['photo_url']) . '"'; ?>>
                        <span id="mjb-cd-photo-glyph"<?php echo empty($profile['photo_url']) ? '' : ' hidden'; ?>><?php echo $this->icon('image', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    </span>
                    <div class="mjb-cd-file-txt">
                        <b id="mjb-cd-photo-name"><?php echo esc_html((string) $photo['name']); ?></b>
                        <span id="mjb-cd-photo-meta"><?php echo esc_html((string) $photo['meta']); ?></span>
                    </div>
                    <div class="mjb-cd-file-actions">
                        <a class="mjb-cd-sq" id="mjb-cd-photo-download" href="<?php echo empty($profile['photo_url']) ? '#' : esc_url((string) $profile['photo_url']); ?>" download="<?php echo esc_attr((string) $photo['name']); ?>" aria-label="<?php esc_attr_e('Download your photo', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Download', 'modern-job-board'); ?>"<?php echo empty($profile['photo_url']) ? ' hidden' : ''; ?>>
                            <?php echo $this->icon('download', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                        <form method="post" action="" class="mjb-cd-photo-delete">
                            <?php wp_nonce_field('mjb_photo_delete_action', 'mjb_photo_delete_nonce'); ?>
                            <button class="mjb-cd-sq mjb-cd-sq--danger" type="submit" name="mjb_delete_photo" value="1" aria-label="<?php esc_attr_e('Delete your photo', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Delete', 'modern-job-board'); ?>">
                                <?php echo $this->icon('trash-2', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div id="mjb-cd-photo-pending" hidden></div>
            <div class="mjb-cd-drop" id="mjb-cd-photo-drop" role="button" tabindex="0" aria-controls="mjb_photo">
                <div class="mjb-cd-drop-copy">
                    <?php echo $this->icon('upload', 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <b><?php esc_html_e('Drop a new photo or browse', 'modern-job-board'); ?></b>
                    <?php
                    $photo_hint = $has_photo
                        ? __('JPG, PNG, or WebP, up to 2 MB — replaces the current file', 'modern-job-board')
                        : __('JPG, PNG, or WebP, up to 2 MB', 'modern-job-board');
                    $photo_replace = __('JPG, PNG, or WebP, up to 2 MB — replaces the current file', 'modern-job-board');
                    ?>
                    <span id="mjb-cd-photo-hint" data-default="<?php echo esc_attr($photo_hint); ?>" data-replace="<?php echo esc_attr($photo_replace); ?>"><?php echo esc_html($photo_hint); ?></span>
                </div>
                <div class="mjb-cd-drop-status" hidden>
                    <span class="mjb-cd-upload-spin" aria-hidden="true"></span>
                    <b><?php esc_html_e('Uploading…', 'modern-job-board'); ?></b>
                </div>
            </div>
            <template id="mjb-cd-photo-row">
                <div class="mjb-cd-file">
                    <span class="mjb-cd-ficon">
                        <img data-photo-preview alt="" hidden>
                        <span data-photo-glyph><?php echo $this->icon('image', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    </span>
                    <div class="mjb-cd-file-txt">
                        <b data-photo-name></b>
                        <span data-photo-meta></span>
                    </div>
                    <div class="mjb-cd-file-actions">
                        <button class="mjb-cd-sq mjb-cd-sq--danger" type="button" id="mjb-cd-photo-clear" aria-label="<?php esc_attr_e('Remove this photo', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Remove', 'modern-job-board'); ?>">
                            <?php echo $this->icon('trash-2', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </button>
                    </div>
                </div>
            </template>
        </fieldset>
        <?php
    }

    /**
     * @param array<string, mixed> $strength
     */
    private function render_strength_panel($strength)
    {
        $done = (int) $strength['done'];
        $total = (int) $strength['total'];
        $pct = (int) $strength['percent'];
        ?>
        <div class="mjb-cd-panel">
            <div class="mjb-cd-panel-head mjb-cd-panel-head--flush">
                <h3><?php echo $this->icon('list-checks', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Profile strength', 'modern-job-board'); ?></h3>
                <span class="mjb-cd-spacer"></span>
                <span class="mjb-cd-meta" id="mjb-cd-chk-meta"><?php echo esc_html(sprintf('%1$d of %2$d', $done, $total)); ?></span>
            </div>
            <div class="mjb-cd-progress-wrap">
                <div class="mjb-cd-progress" id="mjb-cd-chk-bar" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $done); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr((string) $total); ?>" aria-label="<?php echo esc_attr(sprintf(
                    /* translators: 1: completed count, 2: total count */
                    __('%1$d of %2$d profile items complete', 'modern-job-board'),
                    $done,
                    $total
                )); ?>">
                    <i style="--w:<?php echo esc_attr((string) $pct); ?>%"></i>
                </div>
            </div>
            <div class="mjb-cd-panel-body">
                <?php foreach ($strength['items'] as $item) : ?>
                    <?php $ok = !empty($item['done']); ?>
                    <div class="mjb-cd-check<?php echo $ok ? ' is-done' : ''; ?>" data-check="<?php echo esc_attr((string) $item['key']); ?>">
                        <span class="mjb-cd-tick<?php echo $ok ? ' is-ok' : ' is-todo'; ?>" aria-hidden="true"><?php echo $this->icon('check', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <div class="mjb-cd-check-txt">
                            <?php echo esc_html((string) $item['label']); ?>
                            <span class="mjb-cd-sr"><?php echo esc_html($ok ? __('done', 'modern-job-board') : __('still needed', 'modern-job-board')); ?></span>
                        </div>
                        <a class="mjb-cd-act" href="#<?php echo esc_attr((string) $item['jump']); ?>" data-mjb-jump="<?php echo esc_attr((string) $item['jump']); ?>"<?php echo $ok ? ' hidden' : ''; ?>><span class="mjb-cd-u"><?php esc_html_e('Add', 'modern-job-board'); ?></span></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Confirmation dialog for removing the profile CV.
     *
     * @param array<string, mixed> $profile
     */
    private function render_cv_delete_dialog($profile)
    {
        $resume = isset($profile['resume']) && is_array($profile['resume']) ? $profile['resume'] : array('name' => '');
        $name = (string) ($resume['name'] ?? '');
        if ($name === '') {
            $name = __('CV', 'modern-job-board');
        }
        $word = class_exists('MJB_Candidate_Account') ? MJB_Candidate_Account::DELETE_WORD : 'delete';
        ?>
        <dialog class="mjb-cd-dialog" id="mjb-cd-cv-delete-dialog" aria-labelledby="mjb-cd-cv-delete-title">
            <button type="button" class="mjb-cd-sq mjb-cd-dialog-x" data-mjb-dialog-close aria-label="<?php esc_attr_e('Close', 'modern-job-board'); ?>">
                <?php echo $this->icon('x', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
            <form method="post" action="" id="mjb-cd-cv-delete-form">
                <?php wp_nonce_field('mjb_resume_delete_action', 'mjb_resume_delete_nonce'); ?>
                <input type="hidden" name="mjb_delete_resume" value="1">
                <div class="mjb-cd-dialog-body">
                    <div class="mjb-cd-dialog-ic" aria-hidden="true"><?php echo $this->icon('triangle-alert', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <h3 id="mjb-cd-cv-delete-title"><?php esc_html_e('Delete your CV?', 'modern-job-board'); ?></h3>
                    <p><?php echo wp_kses(
                        sprintf(
                            /* translators: %s: CV file name */
                            __('This removes <b id="mjb-cd-cv-delete-name">%s</b> from your profile.', 'modern-job-board'),
                            esc_html($name)
                        ),
                        array(
                            'b' => array('id' => array()),
                        )
                    ); ?></p>
                    <ul>
                        <li><?php esc_html_e('Your profile no longer has a CV on file', 'modern-job-board'); ?></li>
                        <li><?php esc_html_e('Recruiters keep applications you’ve already sent', 'modern-job-board'); ?></li>
                        <li><?php esc_html_e('You can upload a new CV afterwards', 'modern-job-board'); ?></li>
                    </ul>
                    <div class="mjb-field" id="mjb-cd-cv-delete-field">
                        <label for="mjb_cv_delete_confirm"><?php echo wp_kses(__('Type <b>DELETE</b> to confirm', 'modern-job-board'), array('b' => array())); ?></label>
                        <input class="mjb-cd-input" type="text" name="mjb_delete_confirm" id="mjb_cv_delete_confirm" autocomplete="off" autocapitalize="off" spellcheck="false" data-confirm-word="<?php echo esc_attr($word); ?>">
                        <p class="mjb-cd-pw-status is-error" id="mjb-cd-cv-delete-error" hidden></p>
                    </div>
                </div>
                <div class="mjb-cd-dialog-foot">
                    <button class="btn btn-outline" type="button" id="mjb-cd-cv-delete-cancel"><?php esc_html_e('Keep my CV', 'modern-job-board'); ?></button>
                    <button class="btn mjb-cd-btn-danger" type="submit" id="mjb-cd-cv-delete-submit" disabled><?php esc_html_e('Delete CV', 'modern-job-board'); ?></button>
                </div>
            </form>
        </dialog>
        <?php
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function render_cv_panel($profile)
    {
        $resume = isset($profile['resume']) && is_array($profile['resume']) ? $profile['resume'] : array('name' => '', 'meta' => '');
        ?>
        <div class="mjb-cd-panel" id="mjb-cd-cv" tabindex="-1">
            <div class="mjb-cd-panel-head mjb-cd-panel-head--flush">
                <h3><?php echo $this->icon('file-text', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('My CV', 'modern-job-board'); ?></h3>
                <span class="mjb-cd-spacer"></span>
                <?php if (!empty($profile['has_resume'])) : ?>
                    <span class="mjb-cd-chip" id="mjb-cd-cv-chip"><?php esc_html_e('On file', 'modern-job-board'); ?></span>
                <?php endif; ?>
            </div>
            <div class="mjb-cd-panel-body mjb-cd-panel-body--pad">
                <?php if (!empty($profile['has_resume'])) : ?>
                    <div class="mjb-cd-file" id="mjb-cd-cv-file">
                        <span class="mjb-cd-ficon" aria-hidden="true"><?php echo $this->icon('file-text', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <div class="mjb-cd-file-txt">
                            <b><?php echo esc_html((string) $resume['name']); ?></b>
                            <?php if (!empty($resume['meta'])) : ?>
                                <span><?php echo esc_html((string) $resume['meta']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($profile['resume_url'])) : ?>
                            <?php
                            $resume_ext = strtolower((string) ($resume['ext'] ?? ''));
                            $preview_url = add_query_arg('mjb_disposition', 'inline', (string) $profile['resume_url']);
                            ?>
                            <div class="mjb-cd-file-actions">
                                <button type="button" class="mjb-cd-sq" id="mjb-cd-cv-view" data-mjb-cv-url="<?php echo esc_url($preview_url); ?>" data-mjb-cv-name="<?php echo esc_attr((string) $resume['name']); ?>" data-mjb-cv-kind="<?php echo $resume_ext === 'pdf' ? 'pdf' : 'word'; ?>" aria-label="<?php esc_attr_e('View your CV', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('View', 'modern-job-board'); ?>">
                                    <?php echo $this->icon('eye', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </button>
                                <a class="mjb-cd-sq" id="mjb-cd-cv-download" href="<?php echo esc_url((string) $profile['resume_url']); ?>" aria-label="<?php esc_attr_e('Download your CV', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Download', 'modern-job-board'); ?>">
                                    <?php echo $this->icon('download', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </a>
                                <button type="button" class="mjb-cd-sq mjb-cd-sq--danger" id="mjb-cd-cv-delete" aria-label="<?php esc_attr_e('Delete your CV', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Delete', 'modern-job-board'); ?>">
                                    <?php echo $this->icon('trash-2', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" enctype="multipart/form-data" class="mjb-form mjb-cd-resume-form" novalidate>
                    <?php wp_nonce_field('mjb_resume_action', 'mjb_resume_nonce'); ?>
                    <label class="mjb-cd-drop" id="mjb-cd-drop">
                        <?php echo $this->icon('upload', 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <b><?php esc_html_e('Drop a new CV or browse', 'modern-job-board'); ?></b>
                        <?php
                        $cv_empty_hint = __('PDF, DOC, or DOCX, up to 5 MB', 'modern-job-board');
                        $cv_replace_hint = __('PDF, DOC, or DOCX, up to 5 MB — replaces the current file', 'modern-job-board');
                        ?>
                        <span id="mjb-cd-cv-hint" data-empty="<?php echo esc_attr($cv_empty_hint); ?>" data-replace="<?php echo esc_attr($cv_replace_hint); ?>"><?php echo esc_html(!empty($profile['has_resume']) ? $cv_replace_hint : $cv_empty_hint); ?></span>
                        <input type="file" name="mjb_resume" id="mjb_resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-label="<?php esc_attr_e('Upload your CV', 'modern-job-board'); ?>"<?php echo empty($profile['has_resume']) ? ' required aria-required="true"' : ''; ?>>
                    </label>
                    <button class="btn btn-primary mjb-cd-resume-submit" type="submit" name="mjb_upload_resume" value="1"><?php esc_html_e('Upload CV', 'modern-job-board'); ?></button>
                    <p class="mjb-cd-privacy">
                        <?php echo $this->icon('lock', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span><?php esc_html_e('Stored privately. It is not shown on your public profile.', 'modern-job-board'); ?></span>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * @param int        $user_id
     * @param array|null $jobs
     * @param int        $count
     * @param string     $jobs_url
     */
    private function render_saved_panel($user_id, $jobs, $count, $jobs_url)
    {
        ?>
        <div class="mjb-cd-panel" id="mjb-cd-saved">
            <div class="mjb-cd-panel-head">
                <h3><?php echo $this->icon('bookmark', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Saved jobs', 'modern-job-board'); ?></h3>
                <span class="mjb-cd-count"><?php echo esc_html((string) $count); ?></span>
            </div>
            <?php
            if (!class_exists('MJB_Saved_Jobs')) {
                echo '<div class="mjb-cd-empty"><p>' . esc_html__('Saved jobs are not available.', 'modern-job-board') . '</p></div>';
            } elseif (empty($jobs)) {
                echo '<div class="mjb-cd-empty">';
                echo '<div class="mjb-cd-empty-icon" aria-hidden="true">' . $this->icon('bookmark', 18) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<b>' . esc_html__('No saved jobs yet', 'modern-job-board') . '</b>';
                echo '<p>' . esc_html__('Bookmark any job to keep it here.', 'modern-job-board') . '</p>';
                echo '<a class="btn btn-outline" href="' . esc_url($jobs_url) . '">' . esc_html__('Browse jobs', 'modern-job-board') . '</a>';
                echo '</div>';
            } else {
                $this->render_saved_items($user_id, $jobs);
            }
            ?>
        </div>
        <?php
    }

    /**
     * @param int   $user_id
     * @param array $jobs
     */
    private function render_saved_items($user_id, $jobs)
    {
        unset($user_id);
        $dash_url = self::get_page_url();
        echo '<div class="mjb-cd-panel-body">';
        foreach ($jobs as $job) {
            $job_id = (int) $job->ID;
            $permalink = get_permalink($job_id);
            $title = get_the_title($job_id);
            $company = $this->job_company_name($job_id);
            $location = $this->job_location_label($job_id);
            $meta = $company !== '' ? $company : '';
            if ($location !== '' && $location !== '—') {
                $meta = $meta !== '' ? $meta . ' · ' . $location : $location;
            }

            echo '<div class="mjb-cd-item">';
            echo '<span class="mjb-cd-badge" aria-hidden="true">' . $this->icon('bookmark', 18) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<div class="mjb-cd-item-txt">';
            if ($permalink) {
                echo '<b><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></b>';
            } else {
                echo '<b>' . esc_html($title) . '</b>';
            }
            if ($meta !== '') {
                echo '<span>' . esc_html($meta) . '</span>';
            }
            echo '</div>';
            echo '<div class="mjb-cd-item-actions">';
            $apply = MJB_Saved_Jobs::get_apply_action($job_id);
            if (!empty($apply['disabled'])) {
                echo '<span class="mjb-cd-act is-disabled">' . esc_html($apply['label']) . '</span>';
            } elseif (!empty($apply['url']) && !empty($apply['external'])) {
                echo '<a class="mjb-cd-act" href="' . esc_url($apply['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($apply['label']) . '</a>';
            } elseif (!empty($apply['url'])) {
                echo '<a class="mjb-cd-act" href="' . esc_url($apply['url']) . '">' . esc_html($apply['label']) . '</a>';
            }
            $remove_url = MJB_Saved_Jobs::get_toggle_url($job_id, $dash_url);
            echo '<a class="mjb-cd-quiet" data-mjb-inline="save" href="' . esc_url($remove_url) . '">' . esc_html__('Remove', 'modern-job-board') . '</a>';
            echo '</div></div>';
        }
        echo '</div>';
    }

    /**
     * @param WP_User    $user
     * @param array|null $applications
     * @param int        $count
     * @param string     $jobs_url
     */
    private function render_applications_panel($user, $applications, $count, $jobs_url)
    {
        if ($applications === null) {
            $applications = $this->query_applications($user);
        }
        ?>
        <div class="mjb-cd-panel" id="mjb-cd-applications">
            <div class="mjb-cd-panel-head">
                <h3><?php echo $this->icon('inbox', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e('Applications', 'modern-job-board'); ?></h3>
                <span class="mjb-cd-count"><?php echo esc_html((string) $count); ?></span>
            </div>
            <?php if (empty($applications)) : ?>
                <div class="mjb-cd-empty">
                    <div class="mjb-cd-empty-icon" aria-hidden="true"><?php echo $this->icon('inbox', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <b><?php esc_html_e('You haven’t applied for anything yet', 'modern-job-board'); ?></b>
                    <p><?php esc_html_e('Each application shows up here with its status.', 'modern-job-board'); ?></p>
                    <a class="btn btn-primary" href="<?php echo esc_url($jobs_url); ?>"><?php esc_html_e('Browse jobs', 'modern-job-board'); ?></a>
                </div>
            <?php else : ?>
                <div class="mjb-cd-panel-body">
                    <?php foreach ($applications as $application) : ?>
                        <?php
                        $job_id = (int) get_post_meta($application->ID, '_job_applied_for', true);
                        $job = $job_id ? get_post($job_id) : null;
                        $job_title = $job ? get_the_title($job_id) : __('Unknown job', 'modern-job-board');
                        $job_link = ($job && $job->post_status === 'publish') ? get_permalink($job_id) : '';
                        $status = class_exists('MJB_Application_Status') ? MJB_Application_Status::get_status($application->ID) : '';
                        $status_label = class_exists('MJB_Application_Status') ? MJB_Application_Status::get_label($status) : $status;
                        $status_class = 'mjb-status-pill';
                        if (in_array($status, array('shortlisted', 'hired'), true)) {
                            $status_class .= ' mjb-status-pill--live';
                        } elseif ($status === 'rejected') {
                            $status_class .= ' mjb-status-pill--expired';
                        }
                        ?>
                        <div class="mjb-cd-item">
                            <span class="mjb-cd-badge" aria-hidden="true"><?php echo $this->icon('inbox', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <div class="mjb-cd-item-txt">
                                <?php if ($job_link) : ?>
                                    <b><a href="<?php echo esc_url($job_link); ?>"><?php echo esc_html($job_title); ?></a></b>
                                <?php else : ?>
                                    <b><?php echo esc_html($job_title); ?></b>
                                <?php endif; ?>
                                <span><?php echo esc_html(get_the_date('', $application->ID)); ?></span>
                            </div>
                            <span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param int $job_id
     * @return string
     */
    private function job_company_name($job_id)
    {
        $company = (string) get_post_meta($job_id, '_company_name', true);
        if ($company === '') {
            $company_id = (int) get_post_meta($job_id, '_company_id', true);
            if ($company_id > 0) {
                $company = (string) get_the_title($company_id);
            }
        }
        return $company;
    }

    /**
     * @param int $job_id
     * @return string
     */
    private function job_location_label($job_id)
    {
        $location = '';
        if (class_exists('MJB_Location') && method_exists('MJB_Location', 'format_job_location')) {
            $location = (string) MJB_Location::format_job_location($job_id);
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
        return $location;
    }

    /**
     * Applications submitted by this candidate.
     *
     * @param WP_User $user
     * @return array<int, WP_Post>
     */
    private function query_applications($user)
    {
        if (!$user || empty($user->user_email)) {
            return array();
        }

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

        return is_array($applications) ? $applications : array();
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

    /**
     * AJAX: load the home dashboard or the account layer.
     */
    public function ajax_load_layer()
    {
        check_ajax_referer('mjb_candidate_layer', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login_required'), 401);
        }
        $user = wp_get_current_user();
        if (!$user || !in_array('candidate', (array) $user->roles, true)) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        $layer = isset($_POST['layer']) ? sanitize_key(wp_unslash($_POST['layer'])) : '';
        if ($layer !== 'home' && $layer !== 'account') {
            wp_send_json_error(array('message' => 'invalid_layer'), 400);
        }

        ob_start();
        if ($layer === 'account' && class_exists('MJB_Candidate_Account')) {
            MJB_Candidate_Account::render($user);
        } else {
            $user_id = (int) $user->ID;
            $profile = $this->load_profile($user_id, $user);
            $saved_jobs = class_exists('MJB_Saved_Jobs') ? MJB_Saved_Jobs::get_jobs($user_id) : null;
            $applications = $this->query_applications($user);
            $strength = $this->profile_strength($profile);
            $jobs_url = class_exists('MJB_Page_Resolver') ? MJB_Page_Resolver::get_jobs_page_url() : home_url('/jobs/');
            $this->render_home_layer(
                $user_id,
                $user,
                $profile,
                $strength,
                $saved_jobs,
                is_array($saved_jobs) ? count($saved_jobs) : 0,
                $applications,
                count($applications),
                $jobs_url
            );
        }
        $html = ob_get_clean();
        wp_send_json_success(array(
            'layer' => $layer,
            'html' => $html,
        ));
    }

    /**
     * Shimmer placeholder matching a dashboard layer.
     *
     * @param string $layer home|account
     * @return string
     */
    public static function skeleton_html($layer)
    {
        if ($layer === 'account') {
            return self::account_skeleton_html();
        }
        return self::home_skeleton_html();
    }

    /**
     * @return string
     */
    public static function spinner_html()
    {
        return '<div class="mjb-cd-spin" role="status"><span class="mjb-spinner" aria-hidden="true"></span><span class="mjb-cd-sr">'
            . esc_html__('Loading…', 'modern-job-board')
            . '</span></div>';
    }

    /**
     * @return string
     */
    private static function home_skeleton_html()
    {
        $html = '<div class="mjb-cd-skeleton" aria-hidden="true">';
        $html .= '<div class="mjb-skeleton mjb-skeleton--heading"></div>';
        $html .= '<div class="mjb-cd-stats">';
        for ($i = 0; $i < 4; $i++) {
            $html .= '<div class="mjb-cd-stat"><span class="mjb-skeleton mjb-skeleton--stat-val"></span><span class="mjb-skeleton mjb-skeleton--stat-lbl"></span></div>';
        }
        $html .= '</div>';
        $html .= '<div class="mjb-skeleton mjb-skeleton--heading"></div>';
        $html .= '<div class="mjb-cd-cols mjb-cd-cols--main">';
        $html .= '<div class="mjb-cd-panel"><div class="mjb-cd-skel-pad">';
        for ($i = 0; $i < 6; $i++) {
            $html .= '<span class="mjb-skeleton mjb-skeleton--excerpt"></span>';
        }
        $html .= '</div></div>';
        $html .= '<div class="mjb-cd-panel"><div class="mjb-cd-skel-pad">';
        for ($i = 0; $i < 4; $i++) {
            $html .= '<span class="mjb-skeleton mjb-skeleton--excerpt"></span>';
        }
        $html .= '</div></div></div>';
        $html .= '<div class="mjb-cd-cols mjb-cd-cols--half">';
        for ($col = 0; $col < 2; $col++) {
            $html .= '<div class="mjb-cd-panel"><div class="mjb-cd-skel-pad">';
            for ($i = 0; $i < 3; $i++) {
                $html .= '<span class="mjb-skeleton mjb-skeleton--title"></span><span class="mjb-skeleton mjb-skeleton--excerpt-short"></span>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div></div>';
        return $html;
    }

    /**
     * @return string
     */
    private static function account_skeleton_html()
    {
        $html = '<div class="mjb-cd-skeleton" aria-hidden="true">';
        $html .= '<div class="mjb-skeleton mjb-skeleton--heading"></div>';
        $html .= '<div class="mjb-cd-cols mjb-cd-cols--main">';
        $html .= '<div class="mjb-cd-account-main">';
        for ($panel = 0; $panel < 2; $panel++) {
            $html .= '<div class="mjb-cd-panel"><div class="mjb-cd-skel-pad">';
            $html .= '<span class="mjb-skeleton mjb-skeleton--title"></span>';
            for ($i = 0; $i < 4; $i++) {
                $html .= '<span class="mjb-skeleton mjb-skeleton--excerpt"></span>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div><aside class="mjb-cd-side">';
        for ($i = 0; $i < 3; $i++) {
            $html .= '<div class="mjb-cd-panel"><div class="mjb-cd-skel-pad">';
            $html .= '<span class="mjb-skeleton mjb-skeleton--title"></span><span class="mjb-skeleton mjb-skeleton--excerpt"></span><span class="mjb-skeleton mjb-skeleton--btn"></span>';
            $html .= '</div></div>';
        }
        $html .= '</aside></div></div>';
        return $html;
    }
}
