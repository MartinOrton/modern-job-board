<?php
/**
 * Modern Job Board Resume Storage & Protected Downloads
 *
 * Resumes are stored via MJB_Private_Uploads (never in the Media Library).
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
        // upload_dir filter is owned by MJB_Private_Uploads.
    }

    /**
     * Ensure the secure resume directory exists with protection files.
     */
    public static function ensure_secure_directory()
    {
        if (class_exists('MJB_Private_Uploads')) {
            MJB_Private_Uploads::ensure_directories();
            return;
        }

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
     * Write index.php and .htaccess to block direct access (legacy path).
     *
     * @param string $dir
     */
    private static function write_protection_files($dir)
    {
        $index_file = trailingslashit($dir) . 'index.php';
        if (!file_exists($index_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        $htaccess_file = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($htaccess_file, $rules);
        }
    }

    /**
     * Validate an uploaded resume file.
     *
     * @param array $file
     * @return true|WP_Error
     */
    public static function validate_file($file)
    {
        if (class_exists('MJB_Private_Uploads')) {
            return MJB_Private_Uploads::validate_file($file, MJB_Private_Uploads::TYPE_RESUME);
        }

        if (empty($file['name']) || empty($file['tmp_name'])) {
            return new WP_Error('missing_file', __('No file was uploaded.', 'modern-job-board'));
        }

        $size = isset($file['size']) ? intval($file['size']) : 0;
        if ($size <= 0 && !empty($file['tmp_name']) && is_readable($file['tmp_name'])) {
            $measured = @filesize($file['tmp_name']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ($measured !== false) {
                $size = intval($measured);
            }
        }
        if ($size <= 0) {
            return new WP_Error('missing_file', __('No file was uploaded.', 'modern-job-board'));
        }
        if ($size > self::MAX_FILE_SIZE) {
            return new WP_Error('file_too_large', __('Resume file is too large. Maximum size is 5 MB.', 'modern-job-board'));
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ));

        if (empty($check['ext']) || !in_array($check['ext'], self::ALLOWED_EXTENSIONS, true)) {
            return new WP_Error('invalid_type', __('Invalid resume file type. Allowed types: PDF, DOC, DOCX.', 'modern-job-board'));
        }

        return true;
    }

    /**
     * Upload a resume file to the protected directory (never Media Library).
     *
     * @param array  $file
     * @param string $context Optional context label for filters/actions.
     * @return array|WP_Error Keys: file, url, relative
     */
    public static function upload_file($file, $context = 'application')
    {
        $file = apply_filters('mjb_resume_upload_file', $file, $context);

        if (class_exists('MJB_Private_Uploads')) {
            $uploaded = MJB_Private_Uploads::upload($file, MJB_Private_Uploads::TYPE_RESUME);
            if (is_wp_error($uploaded)) {
                return $uploaded;
            }

            do_action('mjb_resume_uploaded', $uploaded, $context);

            return array(
                'file'     => $uploaded['file'],
                'url'      => '',
                'relative' => isset($uploaded['relative']) ? $uploaded['relative'] : '',
            );
        }

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
     * Create an mjb_resume post for a candidate from an uploaded file result.
     *
     * @param int    $user_id
     * @param array  $uploaded Result from upload_file().
     * @param string $original_name Optional original filename for the post title.
     * @return int|WP_Error Resume post ID.
     */
    public static function create_resume_post($user_id, $uploaded, $original_name = '')
    {
        $user_id = intval($user_id);
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('Invalid user.', 'modern-job-board'));
        }

        $title_name = $original_name !== '' ? $original_name : basename($uploaded['file']);
        $resume_post = array(
            'post_title'  => sanitize_file_name($title_name) . ' - ' . $user->display_name,
            'post_type'   => 'mjb_resume',
            'post_status' => 'publish',
            'post_author' => $user_id,
        );

        $resume_id = wp_insert_post($resume_post, true);
        if (!$resume_id || is_wp_error($resume_id)) {
            return is_wp_error($resume_id) ? $resume_id : new WP_Error('resume_create_failed', __('Could not save resume.', 'modern-job-board'));
        }

        $resume_id = intval($resume_id);
        $path = isset($uploaded['file']) ? $uploaded['file'] : '';
        $relative = isset($uploaded['relative']) ? $uploaded['relative'] : '';

        update_post_meta($resume_id, '_resume_file_path', $path);
        if ($relative !== '') {
            update_post_meta($resume_id, '_resume_file_relative', $relative);
        }
        update_post_meta($resume_id, '_candidate_user_id', $user_id);
        update_user_meta($user_id, '_candidate_resume_id', $resume_id);

        return $resume_id;
    }

    /**
     * Copy a candidate profile resume into a new private file for one application.
     *
     * Never returns the profile file path itself — applications must own an
     * independent copy so later profile replacements cannot break downloads.
     *
     * @param int $resume_post_id Profile mjb_resume post ID.
     * @return array|WP_Error {
     *     @type string $path           Path to store on the application (prefer relative).
     *     @type string $relative       Relative path under storage root (may be empty).
     *     @type int    $resume_post_id Source profile resume post (informational).
     * }
     */
    public static function copy_profile_resume_for_application($resume_post_id)
    {
        $resume_post_id = intval($resume_post_id);
        if ($resume_post_id <= 0) {
            return new WP_Error(
                'error_resume_required',
                __('Upload a resume on your candidate profile before applying.', 'modern-job-board')
            );
        }

        $source_path = self::get_resume_post_file_path($resume_post_id);
        if ($source_path === '') {
            return new WP_Error(
                'error_resume_required',
                __('Upload a resume on your candidate profile before applying.', 'modern-job-board')
            );
        }

        if (!class_exists('MJB_Private_Uploads')) {
            return new WP_Error(
                'error_resume_copy',
                __('Could not attach your resume to this application. Please try again.', 'modern-job-board')
            );
        }

        $copied = MJB_Private_Uploads::copy_as_type($source_path, MJB_Private_Uploads::TYPE_RESUME);
        if (is_wp_error($copied)) {
            return new WP_Error(
                'error_resume_copy',
                __('Could not attach your resume to this application. Please try again.', 'modern-job-board'),
                array('original' => $copied)
            );
        }

        $relative = isset($copied['relative']) ? (string) $copied['relative'] : '';
        $path = $relative !== '' ? $relative : (isset($copied['file']) ? (string) $copied['file'] : '');
        if ($path === '') {
            return new WP_Error(
                'error_resume_copy',
                __('Could not attach your resume to this application. Please try again.', 'modern-job-board')
            );
        }

        return array(
            'path' => $path,
            'relative' => $relative,
            'resume_post_id' => $resume_post_id,
        );
    }

    /**
     * Whether any job application still references this resume file or profile post.
     *
     * Used when replacing a profile resume so we do not delete files still needed
     * by historical applications (including legacy shared profile paths).
     *
     * @param int    $resume_post_id Profile resume post ID (0 if none).
     * @param string $file_path      Absolute or relative path for the old file.
     * @return bool
     */
    public static function is_resume_still_referenced($resume_post_id, $file_path = '')
    {
        $resume_post_id = intval($resume_post_id);
        $file_path = is_string($file_path) ? $file_path : '';

        $absolute = '';
        $relative = '';
        if ($file_path !== '' && class_exists('MJB_Private_Uploads')) {
            $absolute = MJB_Private_Uploads::resolve_path($file_path);
            if ($absolute !== '') {
                $relative = MJB_Private_Uploads::absolute_to_relative($absolute);
            }
        } elseif ($file_path !== '' && file_exists($file_path)) {
            $absolute = wp_normalize_path($file_path);
        }

        $meta_query = array('relation' => 'OR');
        if ($resume_post_id > 0) {
            $meta_query[] = array(
                'key' => '_candidate_resume_id',
                'value' => $resume_post_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
        }
        if ($file_path !== '') {
            $meta_query[] = array(
                'key' => '_candidate_resume_path',
                'value' => $file_path,
                'compare' => '=',
            );
        }
        if ($absolute !== '') {
            $meta_query[] = array(
                'key' => '_candidate_resume_path',
                'value' => $absolute,
                'compare' => '=',
            );
        }
        if ($relative !== '') {
            $meta_query[] = array(
                'key' => '_candidate_resume_path',
                'value' => $relative,
                'compare' => '=',
            );
            $meta_query[] = array(
                'key' => '_candidate_resume_relative',
                'value' => $relative,
                'compare' => '=',
            );
        }

        // Only the relation key means nothing to match.
        if (count($meta_query) < 2) {
            return false;
        }

        $found = get_posts(array(
            'post_type' => 'job_application',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ));

        return !empty($found);
    }

    /**
     * Retire a previous profile resume only when no application still needs it.
     *
     * @param int $resume_post_id
     * @return void
     */
    public static function maybe_retire_profile_resume($resume_post_id)
    {
        $resume_post_id = intval($resume_post_id);
        if ($resume_post_id <= 0) {
            return;
        }

        $old_path = self::get_resume_post_file_path($resume_post_id);
        if (self::is_resume_still_referenced($resume_post_id, $old_path)) {
            // Leave the post and file for historical application downloads.
            return;
        }

        if ($old_path && class_exists('MJB_Private_Uploads')) {
            MJB_Private_Uploads::delete($old_path);
        }

        wp_delete_post($resume_post_id, true);
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
        if ($path) {
            $resolved = class_exists('MJB_Private_Uploads')
                ? MJB_Private_Uploads::resolve_path($path)
                : (file_exists($path) ? $path : '');
            if ($resolved) {
                return $resolved;
            }
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
        if ($path) {
            $resolved = class_exists('MJB_Private_Uploads')
                ? MJB_Private_Uploads::resolve_path($path)
                : (file_exists($path) ? $path : '');
            if ($resolved) {
                return $resolved;
            }
        }

        $relative = get_post_meta($resume_post_id, '_resume_file_relative', true);
        if ($relative && class_exists('MJB_Private_Uploads')) {
            $resolved = MJB_Private_Uploads::resolve_path($relative);
            if ($resolved) {
                return $resolved;
            }
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
                MJB_Page_Resolver::get_front_action_base_url()
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
                MJB_Page_Resolver::get_front_action_base_url()
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

        // Never stream arbitrary paths from poisoned meta — only MJB storage roots.
        if (class_exists('MJB_Private_Uploads') && !MJB_Private_Uploads::path_is_under_allowed_storage($file_path)) {
            wp_die(esc_html__('Resume file not found.', 'modern-job-board'), 404);
        }

        $filename = sanitize_file_name(basename($file_path));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'resume.bin';
        }
        $mime = wp_check_filetype($filename);
        $content_type = !empty($mime['type']) ? $mime['type'] : 'application/octet-stream';

        do_action('mjb_resume_downloaded', $type, $id, get_current_user_id());

        nocache_headers();
        header('Content-Type: ' . $content_type);
        // Prevent header injection via poisoned basenames; ASCII fallback + RFC 5987.
        header('Content-Disposition: attachment; filename="' . str_replace(array('"', '\\'), '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . (string) filesize($file_path));
        header('X-Content-Type-Options: nosniff');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
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
