<?php
/**
 * Private / brand file storage outside the plugin directory (never Media Library).
 *
 * Layout (under wp-content/ so plugin updates do not wipe user files):
 * - mjb-private/  CVs and other sensitive files. Web server denied; PHP download only.
 * - mjb-brand/    Logos and profile photos. Public URLs with hashed names.
 *
 * Legacy locations still resolved for reads: plugin-local dirs and uploads/*.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Private_Uploads
{
    const TIER_PRIVATE = 'private';
    const TIER_BRAND   = 'brand';

    const TYPE_RESUME          = 'resume';
    const TYPE_COMPANY_LOGO    = 'company_logo';
    const TYPE_CANDIDATE_PHOTO = 'candidate_photo';

    const PRIVATE_DIR = 'mjb-private';
    const BRAND_DIR   = 'mjb-brand';

    /** @var string|null Active upload type for the upload_dir filter. */
    private static $active_type = null;

    /**
     * Type → storage config.
     *
     * @return array
     */
    public static function type_map()
    {
        return array(
            self::TYPE_RESUME => array(
                'tier'       => self::TIER_PRIVATE,
                'subdir'     => 'resumes',
                'max_size'   => 5242880,
                'mimes'      => array(
                    'pdf'  => 'application/pdf',
                    'doc'  => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ),
                'extensions' => array('pdf', 'doc', 'docx'),
            ),
            self::TYPE_COMPANY_LOGO => array(
                'tier'       => self::TIER_BRAND,
                'subdir'     => 'logos',
                'max_size'   => 2097152,
                'mimes'      => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                ),
                'extensions' => array('jpg', 'jpeg', 'png', 'webp'),
            ),
            self::TYPE_CANDIDATE_PHOTO => array(
                'tier'       => self::TIER_BRAND,
                'subdir'     => 'photos',
                'max_size'   => 2097152,
                'mimes'      => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                ),
                'extensions' => array('jpg', 'jpeg', 'png', 'webp'),
            ),
        );
    }

    /**
     * Absolute filesystem path to the plugin root (trailing slash).
     * Kept for legacy path resolution only.
     *
     * @return string
     */
    public static function plugin_root_dir()
    {
        if (defined('MJB_PATH') && MJB_PATH) {
            return trailingslashit(wp_normalize_path(MJB_PATH));
        }

        return trailingslashit(wp_normalize_path(dirname(__DIR__) . '/'));
    }

    /**
     * Plugin root URL (trailing slash). Legacy brand URL helper.
     *
     * @return string
     */
    public static function plugin_root_url()
    {
        if (defined('MJB_URL') && MJB_URL) {
            return trailingslashit(MJB_URL);
        }

        return trailingslashit(plugins_url('', dirname(__DIR__) . '/modern-job-board.php'));
    }

    /**
     * Durable storage base under wp-content (trailing slash).
     *
     * @return string
     */
    public static function storage_base_dir()
    {
        if (defined('WP_CONTENT_DIR') && WP_CONTENT_DIR) {
            return trailingslashit(wp_normalize_path(WP_CONTENT_DIR));
        }

        return trailingslashit(wp_normalize_path(ABSPATH . 'wp-content'));
    }

    /**
     * Public base URL for storage under wp-content (trailing slash).
     *
     * @return string
     */
    public static function storage_base_url()
    {
        if (function_exists('content_url')) {
            return trailingslashit(content_url());
        }

        return trailingslashit(home_url('/wp-content'));
    }

    /**
     * Absolute path to mjb-private/ or mjb-brand/ under wp-content.
     *
     * @param string $tier TIER_PRIVATE|TIER_BRAND
     * @return string
     */
    public static function tier_root_dir($tier)
    {
        $name = $tier === self::TIER_BRAND ? self::BRAND_DIR : self::PRIVATE_DIR;
        return self::storage_base_dir() . $name;
    }

    /**
     * Base URL for a tier directory (brand only useful publicly).
     *
     * @param string $tier
     * @return string
     */
    public static function tier_root_url($tier)
    {
        $name = $tier === self::TIER_BRAND ? self::BRAND_DIR : self::PRIVATE_DIR;
        return self::storage_base_url() . $name;
    }

    /**
     * Hook upload_dir filter (called from plugin bootstrap).
     */
    public static function init()
    {
        add_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        // Ensure durable storage exists even if activation was skipped after deploy/sync.
        self::ensure_directories();
    }

    /**
     * Ensure storage roots and protection files exist under wp-content.
     */
    public static function ensure_directories()
    {
        self::ensure_private_root(self::tier_root_dir(self::TIER_PRIVATE));
        self::ensure_brand_root(self::tier_root_dir(self::TIER_BRAND));

        foreach (self::type_map() as $config) {
            $root = self::tier_root_dir($config['tier']);
            $dir = trailingslashit($root) . $config['subdir'];
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            self::write_index_file($dir);
        }
    }

    /**
     * @param string $dir Absolute path.
     */
    private static function ensure_private_root($dir)
    {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        self::write_index_file($dir);
        self::write_private_denials($dir);
    }

    /**
     * Apache + IIS denial files for the private tier.
     *
     * @param string $dir Absolute path.
     */
    private static function write_private_denials($dir)
    {
        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($htaccess, $rules);
        }

        // IIS: deny direct static access (nginx still needs server config — see REMOTE_SETUP.md).
        $webconfig = trailingslashit($dir) . 'web.config';
        if (!file_exists($webconfig)) {
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<configuration>\n"
                . "  <system.webServer>\n"
                . "    <security>\n"
                . "      <authorization>\n"
                . "        <remove users=\"*\" roles=\"\" verbs=\"\" />\n"
                . "        <add accessType=\"Deny\" users=\"*\" />\n"
                . "      </authorization>\n"
                . "    </security>\n"
                . "  </system.webServer>\n"
                . "</configuration>\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($webconfig, $xml);
        }
    }

    /**
     * Brand assets are publicly fetchable by hashed URL but never listed / never in Media Library.
     *
     * @param string $dir Absolute path.
     */
    private static function ensure_brand_root($dir)
    {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        self::write_index_file($dir);

        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "Options -Indexes\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($htaccess, $rules);
        }
    }

    /**
     * @param string $dir Absolute path.
     */
    private static function write_index_file($dir)
    {
        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * Redirect wp_handle_upload into durable storage directories.
     *
     * @param array $path
     * @return array
     */
    public static function filter_upload_dir($path)
    {
        if (empty(self::$active_type)) {
            return $path;
        }

        if (!empty($path['error'])) {
            return $path;
        }

        $map = self::type_map();
        if (!isset($map[self::$active_type])) {
            return $path;
        }

        $config = $map[self::$active_type];
        $tier_root = self::tier_root_dir($config['tier']);
        $tier_url  = self::tier_root_url($config['tier']);

        // Keep WP's date-based subdir (e.g. /2026/07) under the type folder.
        $date_subdir = isset($path['subdir']) ? $path['subdir'] : '';
        $subdir = '/' . $config['subdir'] . $date_subdir;

        $full_path = $tier_root . $subdir;
        $full_url  = $tier_url . $subdir;

        if (!file_exists($full_path)) {
            wp_mkdir_p($full_path);
            self::write_index_file($full_path);
            if ($config['tier'] === self::TIER_PRIVATE) {
                self::ensure_private_root($tier_root);
            } else {
                self::ensure_brand_root($tier_root);
            }
        }

        $path['path']    = $full_path;
        $path['url']     = $full_url;
        $path['subdir']  = $subdir;
        $path['basedir'] = $tier_root;
        $path['baseurl'] = $tier_url;

        return $path;
    }

    /**
     * Validate an uploaded file for a type.
     *
     * @param array  $file $_FILES element.
     * @param string $type One of TYPE_* constants.
     * @return true|WP_Error
     */
    public static function validate_file($file, $type)
    {
        $map = self::type_map();
        if (!isset($map[$type])) {
            return new WP_Error('invalid_type', __('Unknown upload type.', 'modern-job-board'));
        }

        $config = $map[$type];

        if (empty($file['name']) || empty($file['tmp_name'])) {
            return new WP_Error('missing_file', __('No file was uploaded.', 'modern-job-board'));
        }

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed. Please try again.', 'modern-job-board'));
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 && is_readable($file['tmp_name'])) {
            $measured = @filesize($file['tmp_name']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ($measured !== false) {
                $size = (int) $measured;
            }
        }

        if ($size <= 0) {
            return new WP_Error('missing_file', __('No file was uploaded.', 'modern-job-board'));
        }

        if ($size > (int) $config['max_size']) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: max size label */
                    __('File is too large. Maximum size is %s.', 'modern-job-board'),
                    size_format($config['max_size'])
                )
            );
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $config['mimes']);

        // Require WordPress real-type validation — do not trust client-supplied extension alone.
        if (empty($check['ext']) || !in_array($check['ext'], $config['extensions'], true)) {
            return new WP_Error(
                'invalid_type',
                sprintf(
                    /* translators: %s: allowed extensions list */
                    __('Invalid file type. Allowed: %s.', 'modern-job-board'),
                    strtoupper(implode(', ', $config['extensions']))
                )
            );
        }

        return true;
    }

    /**
     * Build an unguessable hashed filename for stored files (WPJB C8).
     *
     * @param string $extension Without dot.
     * @return string
     */
    public static function hashed_filename($extension = '')
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $extension));
        $entropy = function_exists('wp_generate_password')
            ? wp_generate_password(24, false, false)
            : bin2hex(random_bytes(12));
        $salt = function_exists('wp_salt') ? wp_salt('auth') : 'mjb';
        $hash = hash_hmac('sha256', $entropy . microtime(true), $salt);
        $name = substr($hash, 0, 40);
        return $extension !== '' ? $name . '.' . $extension : $name;
    }

    /**
     * Create a time-limited signature for a relative private path.
     *
     * @param string $relative Relative storage path.
     * @param int    $ttl      Seconds.
     * @return array{path:string,exp:int,sig:string}
     */
    public static function sign_path($relative, $ttl = 3600)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        $exp = time() + max(60, (int) $ttl);
        $salt = function_exists('wp_salt') ? wp_salt('auth') : 'mjb';
        $sig = hash_hmac('sha256', $relative . '|' . $exp, $salt);
        return array(
            'path' => $relative,
            'exp' => $exp,
            'sig' => $sig,
        );
    }

    /**
     * Verify a signed path token.
     *
     * @param string $relative
     * @param int    $exp
     * @param string $sig
     * @return bool
     */
    public static function verify_signed_path($relative, $exp, $sig)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        $exp = (int) $exp;
        if ($exp < time() || $sig === '' || $relative === '') {
            return false;
        }
        $salt = function_exists('wp_salt') ? wp_salt('auth') : 'mjb';
        $expected = hash_hmac('sha256', $relative . '|' . $exp, $salt);
        return hash_equals($expected, (string) $sig);
    }

    /**
     * Store an uploaded file in durable storage (never Media Library / never uploads root).
     *
     * @param array  $file $_FILES element.
     * @param string $type One of TYPE_* constants.
     * @return array|WP_Error { file, url, type, relative, tier }
     */
    public static function upload($file, $type)
    {
        $map = self::type_map();
        if (!isset($map[$type])) {
            return new WP_Error('invalid_type', __('Unknown upload type.', 'modern-job-board'));
        }

        $config = $map[$type];
        $validation = self::validate_file($file, $type);
        if (is_wp_error($validation)) {
            return $validation;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        self::ensure_directories();

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // C8: unguessable hashed filename (random entropy + HMAC fragment).
        $safe_name = self::hashed_filename($extension);
        $file['name'] = $safe_name;

        self::$active_type = $type;

        $uploaded = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'mimes'     => $config['mimes'],
                'unique_filename_callback' => static function ($dir, $name, $ext) {
                    return $name;
                },
            )
        );

        self::$active_type = null;

        if (isset($uploaded['error'])) {
            return new WP_Error('upload_error', $uploaded['error']);
        }

        $relative = self::absolute_to_relative($uploaded['file']);

        $result = array(
            'file'     => $uploaded['file'],
            'url'      => $config['tier'] === self::TIER_BRAND ? $uploaded['url'] : '',
            'type'     => $type,
            'relative' => $relative,
            'tier'     => $config['tier'],
        );

        /**
         * Fires after a private/brand upload succeeds.
         *
         * @param array  $result
         * @param string $type
         */
        do_action('mjb_private_file_uploaded', $result, $type);

        return $result;
    }

    /**
     * Copy an existing file into private/brand storage as a new independent object.
     *
     * Used when attaching a profile resume to an application so later profile
     * replacements do not break historical application downloads.
     *
     * @param string $source Absolute or stored relative path.
     * @param string $type   One of TYPE_* constants.
     * @return array|WP_Error { file, url, type, relative, tier }
     */
    public static function copy_as_type($source, $type)
    {
        $map = self::type_map();
        if (!isset($map[$type])) {
            return new WP_Error('invalid_type', __('Unknown upload type.', 'modern-job-board'));
        }

        $absolute = self::resolve_path($source);
        if ($absolute === '' || !file_exists($absolute) || !is_readable($absolute)) {
            return new WP_Error('missing_file', __('Source file not found.', 'modern-job-board'));
        }

        $config = $map[$type];
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        if (!in_array($extension, $config['extensions'], true)) {
            return new WP_Error('invalid_type', __('Invalid file type for copy.', 'modern-job-board'));
        }

        self::ensure_directories();

        $tier_root = self::tier_root_dir($config['tier']);
        $date_subdir = '/' . gmdate('Y') . '/' . gmdate('m');
        $subdir = '/' . $config['subdir'] . $date_subdir;
        $dest_dir = $tier_root . $subdir;

        if (!file_exists($dest_dir)) {
            wp_mkdir_p($dest_dir);
            self::write_index_file($dest_dir);
        }

        $safe_name = wp_generate_password(32, false, false) . '.' . $extension;
        $dest = trailingslashit($dest_dir) . $safe_name;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
        if (!@copy($absolute, $dest)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            return new WP_Error('copy_failed', __('Could not copy file.', 'modern-job-board'));
        }

        $relative = self::absolute_to_relative($dest);
        $url = '';
        if ($config['tier'] === self::TIER_BRAND) {
            $url = trailingslashit(self::tier_root_url(self::TIER_BRAND)) . ltrim(str_replace('\\', '/', substr(wp_normalize_path($dest), strlen(trailingslashit(wp_normalize_path($tier_root))))), '/');
        }

        return array(
            'file'     => $dest,
            'url'      => $url,
            'type'     => $type,
            'relative' => $relative,
            'tier'     => $config['tier'],
        );
    }

    /**
     * Convert absolute path under known storage roots to a portable relative path.
     *
     * Format: mjb-private/resumes/2026/07/hash.pdf or mjb-brand/logos/...
     *
     * @param string $absolute
     * @return string
     */
    public static function absolute_to_relative($absolute)
    {
        $absolute = wp_normalize_path($absolute);

        foreach (self::known_storage_bases() as $base) {
            $base = trailingslashit(wp_normalize_path($base));
            if (strpos($absolute, $base) === 0) {
                return ltrim(substr($absolute, strlen($base)), '/');
            }
        }

        return $absolute;
    }

    /**
     * Roots that may contain MJB-managed files (content, plugin legacy, uploads legacy).
     *
     * @return array<int, string>
     */
    public static function known_storage_bases()
    {
        $bases = array(
            self::storage_base_dir(),
            self::plugin_root_dir(),
        );

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['error'])) {
            $bases[] = trailingslashit(wp_normalize_path($upload_dir['basedir']));
        }

        return $bases;
    }

    /**
     * Allowed absolute path prefixes for download/delete (private, brand, legacy resumes).
     *
     * @return array<int, string>
     */
    public static function allowed_storage_prefixes()
    {
        $prefixes = array(
            trailingslashit(wp_normalize_path(self::tier_root_dir(self::TIER_PRIVATE))),
            trailingslashit(wp_normalize_path(self::tier_root_dir(self::TIER_BRAND))),
            trailingslashit(wp_normalize_path(self::plugin_root_dir() . self::PRIVATE_DIR)),
            trailingslashit(wp_normalize_path(self::plugin_root_dir() . self::BRAND_DIR)),
        );

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['error'])) {
            $base = trailingslashit(wp_normalize_path($upload_dir['basedir']));
            $prefixes[] = $base . self::PRIVATE_DIR . '/';
            $prefixes[] = $base . self::BRAND_DIR . '/';
            $prefixes[] = $base . 'mjb-resumes/';
        }

        return $prefixes;
    }

    /**
     * Whether an absolute path is under an allowed MJB storage root.
     *
     * @param string $absolute
     * @return bool
     */
    public static function path_is_under_allowed_storage($absolute)
    {
        $absolute = wp_normalize_path($absolute);
        if ($absolute === '') {
            return false;
        }

        // Resolve .. segments when realpath is available.
        $real = realpath($absolute);
        if ($real !== false) {
            $absolute = wp_normalize_path($real);
        }

        foreach (self::allowed_storage_prefixes() as $prefix) {
            $prefix = wp_normalize_path($prefix);
            if ($prefix !== '' && strpos($absolute, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a stored path to an absolute filesystem path under allowed roots only.
     *
     * @param string $stored
     * @return string Empty if not found or outside allowed storage.
     */
    public static function resolve_path($stored)
    {
        if (empty($stored)) {
            return '';
        }

        $stored = wp_normalize_path($stored);

        // Absolute path only accepted when under allowed storage (prevents poisoned meta LFI).
        if (file_exists($stored) && self::path_is_under_allowed_storage($stored)) {
            return $stored;
        }

        $candidates = array();
        $relative = ltrim($stored, '/');

        $candidates[] = self::storage_base_dir() . $relative;
        $candidates[] = self::plugin_root_dir() . $relative;

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['error'])) {
            $candidates[] = trailingslashit(wp_normalize_path($upload_dir['basedir'])) . $relative;
        }

        foreach ($candidates as $candidate) {
            $candidate = wp_normalize_path($candidate);
            if (file_exists($candidate) && self::path_is_under_allowed_storage($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Public URL for a brand-tier file. Empty for private-tier files.
     *
     * @param string $stored Absolute or relative path.
     * @return string
     */
    public static function get_public_url($stored)
    {
        $absolute = self::resolve_path($stored);
        if ($absolute === '') {
            return '';
        }

        $absolute = wp_normalize_path($absolute);

        $brand_candidates = array(
            array(
                'root' => trailingslashit(wp_normalize_path(self::tier_root_dir(self::TIER_BRAND))),
                'url'  => trailingslashit(self::tier_root_url(self::TIER_BRAND)),
            ),
            array(
                'root' => trailingslashit(wp_normalize_path(self::plugin_root_dir() . self::BRAND_DIR)),
                'url'  => trailingslashit(self::plugin_root_url() . self::BRAND_DIR),
            ),
        );

        foreach ($brand_candidates as $pair) {
            if (strpos($absolute, $pair['root']) === 0) {
                $relative = ltrim(substr($absolute, strlen($pair['root'])), '/');
                return $pair['url'] . str_replace('\\', '/', $relative);
            }
        }

        // Legacy brand files under uploads/mjb-brand/.
        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['error'])) {
            $legacy_brand = trailingslashit(wp_normalize_path($upload_dir['basedir'])) . self::BRAND_DIR . '/';
            if (strpos($absolute, $legacy_brand) === 0) {
                $relative = ltrim(substr($absolute, strlen($legacy_brand)), '/');
                return trailingslashit($upload_dir['baseurl']) . self::BRAND_DIR . '/' . str_replace('\\', '/', $relative);
            }
        }

        return '';
    }

    /**
     * Whether a path points under the private tier (content, plugin, or legacy uploads).
     *
     * @param string $stored
     * @return bool
     */
    public static function is_private_path($stored)
    {
        $absolute = self::resolve_path($stored);
        if ($absolute === '') {
            return false;
        }

        $absolute = wp_normalize_path($absolute);
        $private_roots = array(
            trailingslashit(wp_normalize_path(self::tier_root_dir(self::TIER_PRIVATE))),
            trailingslashit(wp_normalize_path(self::plugin_root_dir() . self::PRIVATE_DIR)),
        );

        foreach ($private_roots as $root) {
            if (strpos($absolute, $root) === 0) {
                return true;
            }
        }

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['error'])) {
            $base = trailingslashit(wp_normalize_path($upload_dir['basedir']));
            if (strpos($absolute, $base . self::PRIVATE_DIR) === 0) {
                return true;
            }
            if (strpos($absolute, $base . 'mjb-resumes') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a stored file if it lives under allowed MJB storage roots.
     *
     * @param string $stored
     * @return bool
     */
    public static function delete($stored)
    {
        $absolute = self::resolve_path($stored);
        if ($absolute === '' || !file_exists($absolute)) {
            return false;
        }

        if (!self::path_is_under_allowed_storage($absolute)) {
            return false;
        }

        return @unlink($absolute); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }
}
