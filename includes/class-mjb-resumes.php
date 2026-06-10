<?php
/**
 * Modern Job Board Resume Storage & Protected Downloads
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Resumes
{
    const ALLOWED_EXTENSIONS = array('pdf', 'doc', 'docx');
    const MAX_FILE_SIZE = 5242880; // 5 MB

    /**
     * Initialize resume security hooks.
     */
    public function init()
    {
        add_action('init', array($this, 'handle_download_request'), 1);
        add_filter('upload_dir', array($this, 'custom_upload_dir'));
    }

    /**
     * Ensure the secure resume directory exists with protection files.
     */
    public static function ensure_secure_directory()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return;
        }

        $resume_dir = trailingslashit($upload_dir['basedir']) . 'mjb-resumes';

        if (!file_exists($resume_dir)) {
            wp_mkdir_p($resume_dir);
        }

        self::write_protection_files($resume_dir);
    }

    /**
     * Write index.php and .htaccess to block direct access.
     *
     * @param string $dir
     */
    private static function write_protection_files($dir)
    {
        $index_file = trailingslashit($dir) . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        $htaccess_file = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
            file_put_contents($htaccess_file, $rules);
        }
    }

    /**
     * Redirect uploads into the protected resume directory.
     *
     * @param array $path
     * @return array
     */
    public function custom_upload_dir($path)
    {
        if (empty($path['basedir']) || !self::is_resume_upload_context()) {
            return $path;
        }

        if (!empty($path['error'])) {
            return $path;
        }

        $subdir = isset($path['subdir']) ? $path['subdir'] : '';
        $resume_path = trailingslashit($path['basedir']) . 'mjb-resumes' . $subdir;
        $resume_url = trailingslashit($path['baseurl']) . 'mjb-resumes' . $subdir;

        if (!file_exists($resume_path)) {
            wp_mkdir_p($resume_path);
            self::write_protection_files(trailingslashit($path['basedir']) . 'mjb-resumes');
        }

        $path['path'] = $resume_path;
        $path['url'] = $resume_url;
        $path['subdir'] = '/mjb-resumes' . $subdir;

        return $path;
    }

    /**
     * Whether the current request is uploading a resume.
     *
     * @return bool
     */
    private static function is_resume_upload_context()
    {
        return (
            (isset($_POST['mjb_upload_resume']) && isset($_POST['mjb_resume_nonce']))
            || (isset($_POST['mjb_submit_application']) && isset($_FILES['candidate_resume']['name']) && !empty($_FILES['candidate_resume']['name']))
        );
    }

    /**
     * Validate an uploaded resume file.
     *
     * @param array $file
     * @return true|WP_Error
     */
    public static function validate_file($file)
    {
        if (empty($file['name']) || empty($file['tmp_name'])) {
            return new WP_Error('missing_file', __('No file was uploaded.', 'modern-job-board'));
        }

        if (!empty($file['size']) && intval($file['size']) > self::MAX_FILE_SIZE) {
            return new WP_Error('file_too_large', __('Resume file is too large. Maximum size is 5 MB.', 'modern-job-board'));
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ));

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (empty($check['ext']) || !in_array($check['ext'], self::ALLOWED_EXTENSIONS, true)) {
            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                return new WP_Error('invalid_type', __('Invalid resume file type. Allowed types: PDF, DOC, DOCX.', 'modern-job-board'));
            }
        }

        return true;
    }

    /**
     * Upload a resume file to the protected directory.
     *
     * @param array $file
     * @return array|WP_Error Keys: file, url
     */
    public static function upload_file($file, $context = 'application')
    {
        $file = apply_filters('mjb_resume_upload_file', $file, $context);

        $validation = self::validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        self::ensure_secure_directory();

        $uploaded = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes' => array(
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ));

        if (isset($uploaded['error'])) {
            return new WP_Error('upload_error', $uploaded['error']);
        }

        do_action('mjb_resume_uploaded', $uploaded, $context);

        return $uploaded;
    }

    /**
     * Get the file path stored on an application.
     *
     * @param int $application_id
     * @return string
     */
    public static function get_application_file_path($application_id)
    {
        $path = get_post_meta($application_id, '_candidate_resume_path', true);
        if ($path && file_exists($path)) {
            return $path;
        }

        $legacy_url = get_post_meta($application_id, '_candidate_resume', true);
        if ($legacy_url) {
            $upload_dir = wp_upload_dir();
            if (strpos($legacy_url, $upload_dir['baseurl']) === 0) {
                $legacy_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $legacy_url);
                if (file_exists($legacy_path)) {
                    return $legacy_path;
                }
            }
        }

        $resume_post_id = intval(get_post_meta($application_id, '_candidate_resume_id', true));
        if ($resume_post_id) {
            return self::get_resume_post_file_path($resume_post_id);
        }

        return '';
    }

    /**
     * Get file path from a resume post.
     *
     * @param int $resume_post_id
     * @return string
     */
    public static function get_resume_post_file_path($resume_post_id)
    {
        $path = get_post_meta($resume_post_id, '_resume_file_path', true);
        if ($path && file_exists($path)) {
            return $path;
        }

        $legacy_url = get_post_meta($resume_post_id, '_resume_file_url', true);
        if ($legacy_url) {
            $upload_dir = wp_upload_dir();
            if (strpos($legacy_url, $upload_dir['baseurl']) === 0) {
                $legacy_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $legacy_url);
                if (file_exists($legacy_path)) {
                    return $legacy_path;
                }
            }
        }

        return '';
    }

    /**
     * Build a protected download URL for an application resume.
     *
     * @param int $application_id
     * @return string
     */
    public static function get_application_download_url($application_id)
    {
        if (!self::get_application_file_path($application_id)) {
            return '';
        }

        return wp_nonce_url(
            add_query_arg(
                array(
                    'mjb_download' => 'application',
                    'mjb_id' => intval($application_id),
                ),
                home_url('/')
            ),
            'mjb_download_application_' . intval($application_id),
            'mjb_nonce'
        );
    }

    /**
     * Build a protected download URL for a candidate resume post.
     *
     * @param int $resume_post_id
     * @return string
     */
    public static function get_resume_post_download_url($resume_post_id)
    {
        if (!self::get_resume_post_file_path($resume_post_id)) {
            return '';
        }

        return wp_nonce_url(
            add_query_arg(
                array(
                    'mjb_download' => 'resume',
                    'mjb_id' => intval($resume_post_id),
                ),
                home_url('/')
            ),
            'mjb_download_resume_' . intval($resume_post_id),
            'mjb_nonce'
        );
    }

    /**
     * Resolve a resume URL for display (candidate profile or legacy attachment).
     *
     * @param int $resume_reference_id Resume post ID or legacy attachment ID.
     * @return string
     */
    public static function get_resume_display_url($resume_reference_id)
    {
        $resume_reference_id = intval($resume_reference_id);
        if (!$resume_reference_id) {
            return '';
        }

        $post_type = get_post_type($resume_reference_id);
        if ($post_type === 'mjb_resume') {
            return self::get_resume_post_download_url($resume_reference_id);
        }

        if ($post_type === 'attachment') {
            $file_path = get_attached_file($resume_reference_id);
            if ($file_path && file_exists($file_path)) {
                return wp_get_attachment_url($resume_reference_id);
            }
        }

        return '';
    }

    /**
     * Handle protected resume download requests.
     */
    public function handle_download_request()
    {
        if (empty($_GET['mjb_download']) || empty($_GET['mjb_id'])) {
            return;
        }

        $type = sanitize_key(wp_unslash($_GET['mjb_download']));
        $id = intval($_GET['mjb_id']);
        $nonce = isset($_GET['mjb_nonce']) ? sanitize_text_field(wp_unslash($_GET['mjb_nonce'])) : '';

        if ($type === 'application') {
            if (!$nonce || !wp_verify_nonce($nonce, 'mjb_download_application_' . $id)) {
                wp_die(esc_html__('Invalid download link.', 'modern-job-board'), 403);
            }

            $allowed = apply_filters('mjb_resume_download_allowed', self::user_can_download_application($id), 'application', $id);
            if (!$allowed) {
                wp_die(esc_html__('You do not have permission to download this resume.', 'modern-job-board'), 403);
            }

            $file_path = self::get_application_file_path($id);
        } elseif ($type === 'resume') {
            if (!$nonce || !wp_verify_nonce($nonce, 'mjb_download_resume_' . $id)) {
                wp_die(esc_html__('Invalid download link.', 'modern-job-board'), 403);
            }

            $allowed = apply_filters('mjb_resume_download_allowed', self::user_can_download_resume_post($id), 'resume', $id);
            if (!$allowed) {
                wp_die(esc_html__('You do not have permission to download this resume.', 'modern-job-board'), 403);
            }

            $file_path = self::get_resume_post_file_path($id);
        } else {
            return;
        }

        if (empty($file_path) || !file_exists($file_path)) {
            wp_die(esc_html__('Resume file not found.', 'modern-job-board'), 404);
        }

        $filename = basename($file_path);
        $mime = wp_check_filetype($filename);
        $content_type = !empty($mime['type']) ? $mime['type'] : 'application/octet-stream';

        do_action('mjb_resume_downloaded', $type, $id, get_current_user_id());

        nocache_headers();
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }

    /**
     * Check whether the current user can download an application resume.
     *
     * @param int $application_id
     * @return bool
     */
    public static function user_can_download_application($application_id)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $job_id = intval(get_post_meta($application_id, '_job_applied_for', true));
        $job = $job_id ? get_post($job_id) : null;

        if (!$job || $job->post_type !== 'job_listing') {
            return false;
        }

        if (intval($job->post_author) !== $user_id) {
            return false;
        }

        if (get_option('mjb_paid_cv_access')) {
            return self::employer_has_cv_access($user_id, $application_id);
        }

        return true;
    }

    /**
     * Check whether the current user can download a resume post.
     *
     * @param int $resume_post_id
     * @return bool
     */
    public static function user_can_download_resume_post($resume_post_id)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $owner_id = intval(get_post_meta($resume_post_id, '_candidate_user_id', true));
        if (!$owner_id) {
            $owner_id = intval(get_post_field('post_author', $resume_post_id));
        }

        return $owner_id === $user_id;
    }

    /**
     * Determine whether an employer has paid access to a specific application.
     *
     * @param int $user_id
     * @param int $application_id
     * @return bool
     */
    public static function employer_has_cv_access($user_id, $application_id)
    {
        $expires = get_user_meta($user_id, '_mjb_cv_access_expires', true);
        if ($expires && intval($expires) > current_time('timestamp')) {
            return true;
        }

        $unlocked = get_user_meta($user_id, '_mjb_unlocked_applications', true);
        if (is_array($unlocked) && in_array(intval($application_id), array_map('intval', $unlocked), true)) {
            return true;
        }

        return false;
    }
}