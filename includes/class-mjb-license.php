<?php
/**
 * Freemium plan + offline license key enforcement.
 *
 * Plans: free | pro | business | complete_site (complete_site = business features).
 * Remote license server is out of scope for P0.1 — keys are signed offline.
 *
 * @package ModernJobBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_License
{
    const OPTION_KEY  = 'mjb_license_key';
    const OPTION_PLAN = 'mjb_license_plan';
    const OPTION_META = 'mjb_license_meta';

    const PLAN_FREE     = 'free';
    const PLAN_PRO      = 'pro';
    const PLAN_BUSINESS = 'business';
    const PLAN_COMPLETE = 'complete_site';

    /** Free tier: max published job_listing posts. */
    const FREE_ACTIVE_JOB_LIMIT = 10;

    /**
     * HMAC secret for offline keys. Remote validation can replace this later.
     * Not a substitute for a license server — deters casual key fabrication only.
     */
    const KEY_SALT = 'mjb-offline-license-v1-4mation';

    /**
     * Bootstrap enforcement hooks.
     */
    public static function init()
    {
        add_action('transition_post_status', array(__CLASS__, 'guard_job_publish'), 10, 3);
        add_filter('wp_insert_post_empty_content', array(__CLASS__, 'allow_empty_job_title_check'), 10, 2);
        add_filter('wp_insert_post_data', array(__CLASS__, 'guard_insert_job_data'), 20, 2);

        add_action('admin_notices', array(__CLASS__, 'admin_plan_notice'));
        add_action('admin_notices', array('MJB_License_Commerce', 'maybe_render_generate_notice'));
        add_filter('mjb_pre_job_submission_data', array(__CLASS__, 'guard_frontend_job_submission'), 5, 2);
    }

    /**
     * Active plan slug.
     *
     * Priority: filter → constant MJB_LICENSE_PLAN → stored plan → free.
     *
     * @return string
     */
    public static function get_plan()
    {
        $plan = apply_filters('mjb_license_plan', null);
        if (is_string($plan) && self::is_valid_plan($plan)) {
            return $plan;
        }

        if (defined('MJB_LICENSE_PLAN') && self::is_valid_plan(MJB_LICENSE_PLAN)) {
            return MJB_LICENSE_PLAN;
        }

        $stored = get_option(self::OPTION_PLAN, self::PLAN_FREE);
        if (self::is_valid_plan($stored)) {
            return $stored;
        }

        return self::PLAN_FREE;
    }

    /**
     * @param string $plan
     * @return bool
     */
    public static function is_valid_plan($plan)
    {
        return in_array($plan, array(
            self::PLAN_FREE,
            self::PLAN_PRO,
            self::PLAN_BUSINESS,
            self::PLAN_COMPLETE,
        ), true);
    }

    /**
     * Human-readable plan name.
     *
     * @param string|null $plan
     * @return string
     */
    public static function get_plan_label($plan = null)
    {
        $plan = $plan === null ? self::get_plan() : $plan;
        $labels = array(
            self::PLAN_FREE     => __('Free', 'modern-job-board'),
            self::PLAN_PRO      => __('Pro', 'modern-job-board'),
            self::PLAN_BUSINESS => __('Business', 'modern-job-board'),
            self::PLAN_COMPLETE => __('Complete Site', 'modern-job-board'),
        );

        return isset($labels[$plan]) ? $labels[$plan] : $labels[self::PLAN_FREE];
    }

    /**
     * Whether plan is Pro or higher (unlimited jobs + monetization tools).
     *
     * @param string|null $plan
     * @return bool
     */
    public static function is_pro($plan = null)
    {
        $plan = $plan === null ? self::get_plan() : $plan;
        return in_array($plan, array(self::PLAN_PRO, self::PLAN_BUSINESS, self::PLAN_COMPLETE), true);
    }

    /**
     * Whether plan is Business or Complete Site.
     *
     * @param string|null $plan
     * @return bool
     */
    public static function is_business($plan = null)
    {
        $plan = $plan === null ? self::get_plan() : $plan;
        return in_array($plan, array(self::PLAN_BUSINESS, self::PLAN_COMPLETE), true);
    }

    /**
     * Feature capability map.
     *
     * @param string $feature
     * @return bool
     */
    public static function can($feature)
    {
        $feature = sanitize_key($feature);
        $allowed = false;

        switch ($feature) {
            case 'unlimited_jobs':
            case 'woocommerce':
            case 'custom_fields':
            case 'tools':
            case 'import_export':
                $allowed = self::is_pro();
                break;
            case 'rest_api':
            case 'xml_feed':
            case 'webhooks':
                $allowed = self::is_business();
                break;
            default:
                $allowed = true;
                break;
        }

        return (bool) apply_filters('mjb_license_can', $allowed, $feature, self::get_plan());
    }

    /**
     * Count published job listings.
     *
     * @return int
     */
    public static function count_active_jobs()
    {
        $counts = wp_count_posts('job_listing');
        if (!$counts || !isset($counts->publish)) {
            return 0;
        }

        return (int) $counts->publish;
    }

    /**
     * Free-tier limit (filterable).
     *
     * @return int
     */
    public static function get_free_job_limit()
    {
        return (int) apply_filters('mjb_free_active_job_limit', self::FREE_ACTIVE_JOB_LIMIT);
    }

    /**
     * Whether another published job is allowed.
     *
     * @param int $exclude_post_id When updating an already-published job, exclude it from the count.
     * @return bool
     */
    public static function can_publish_job($exclude_post_id = 0)
    {
        if (self::can('unlimited_jobs')) {
            return true;
        }

        $count = self::count_active_jobs();
        $exclude_post_id = (int) $exclude_post_id;
        if ($exclude_post_id > 0 && get_post_type($exclude_post_id) === 'job_listing' && get_post_status($exclude_post_id) === 'publish') {
            // Updating an existing published listing does not consume a new slot.
            return true;
        }

        return $count < self::get_free_job_limit();
    }

    /**
     * Block transition to publish when Free cap is reached.
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public static function guard_job_publish($new_status, $old_status, $post)
    {
        if (!$post instanceof WP_Post || $post->post_type !== 'job_listing') {
            return;
        }

        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        if (self::can_publish_job((int) $post->ID)) {
            return;
        }

        // Revert to pending and flag admin/frontend.
        remove_action('transition_post_status', array(__CLASS__, 'guard_job_publish'), 10);
        wp_update_post(array(
            'ID' => (int) $post->ID,
            'post_status' => 'pending',
        ));
        add_action('transition_post_status', array(__CLASS__, 'guard_job_publish'), 10, 3);

        update_option('mjb_license_job_cap_hit', 1, false);

        if (is_admin() && !wp_doing_ajax()) {
            set_transient('mjb_license_admin_notice', 'job_cap', 60);
        }
    }

    /**
     * Prevent new inserts that are immediately published over the Free cap.
     *
     * @param array $data
     * @param array $postarr
     * @return array
     */
    public static function guard_insert_job_data($data, $postarr)
    {
        if (($data['post_type'] ?? '') !== 'job_listing') {
            return $data;
        }

        if (($data['post_status'] ?? '') !== 'publish') {
            return $data;
        }

        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if (self::can_publish_job($post_id)) {
            return $data;
        }

        $data['post_status'] = 'pending';
        update_option('mjb_license_job_cap_hit', 1, false);

        return $data;
    }

    /**
     * No-op placeholder so other filters can chain; kept for extension points.
     *
     * @param bool  $maybe_empty
     * @param array $postarr
     * @return bool
     */
    public static function allow_empty_job_title_check($maybe_empty, $postarr)
    {
        return $maybe_empty;
    }

    /**
     * Frontend job form: force pending + notice context when Free cap blocks publish path.
     * Payment/publish flows still run later; cap is enforced on publish transitions.
     *
     * @param array $post_data
     * @param array $request
     * @return array
     */
    public static function guard_frontend_job_submission($post_data, $request)
    {
        unset($request);

        if (self::can('unlimited_jobs')) {
            return $post_data;
        }

        // New submissions on Free: never auto-publish past the cap.
        if (empty($post_data['ID']) && !self::can_publish_job(0)) {
            $post_data['post_status'] = 'pending';
            update_option('mjb_license_job_cap_hit', 1, false);
        }

        return $post_data;
    }

    /**
     * Admin notice when cap is hit or plan is free.
     */
    public static function admin_plan_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos((string) $screen->id, 'modern-job-board') === false) {
            // Also show on job_listing list when cap hit.
            if (!$screen || $screen->post_type !== 'job_listing') {
                if (get_transient('mjb_license_admin_notice') !== 'job_cap') {
                    return;
                }
            }
        }

        $notice = get_transient('mjb_license_admin_notice');
        if ($notice === 'job_cap') {
            delete_transient('mjb_license_admin_notice');
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo esc_html(sprintf(
                /* translators: %d: free plan job limit */
                __('Modern Job Board Free plan allows %d active (published) jobs. Upgrade to Pro for unlimited listings.', 'modern-job-board'),
                self::get_free_job_limit()
            ));
            echo ' <a href="' . esc_url(admin_url('admin.php?page=modern-job-board&tab=settings#mjb-license')) . '">';
            echo esc_html__('License settings', 'modern-job-board');
            echo '</a></p></div>';
            return;
        }

        // Soft Free-plan usage banner on MJB screens.
        if (!self::can('unlimited_jobs') && $screen && strpos((string) $screen->id, 'modern-job-board') !== false) {
            $count = self::count_active_jobs();
            $limit = self::get_free_job_limit();
            $class = $count >= $limit ? 'notice-warning' : 'notice-info';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>';
            echo esc_html(sprintf(
                /* translators: 1: plan label, 2: published count, 3: free limit */
                __('Modern Job Board plan: %1$s — %2$d / %3$d active jobs.', 'modern-job-board'),
                self::get_plan_label(),
                $count,
                $limit
            ));
            echo ' <a href="' . esc_url(admin_url('admin.php?page=modern-job-board&tab=settings#mjb-license')) . '">';
            echo esc_html__('Manage license', 'modern-job-board');
            echo '</a></p></div>';
        }
    }

    /**
     * Stored license key (raw).
     *
     * @return string
     */
    public static function get_key()
    {
        return (string) get_option(self::OPTION_KEY, '');
    }

    /**
     * Validate and activate an offline key. Format: MJB-{PLAN}-{YYYYMMDD|00000000}-{8 hex}.
     *
     * @param string $key
     * @return true|WP_Error
     */
    public static function activate_key($key)
    {
        $key = strtoupper(trim((string) $key));
        $key = preg_replace('/\s+/', '', $key);

        if ($key === '') {
            self::clear_license();
            return true;
        }

        $parsed = self::parse_key($key);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        if (!empty($parsed['expires']) && $parsed['expires'] !== '00000000') {
            $exp = strtotime($parsed['expires'] . ' UTC');
            if ($exp && $exp < time()) {
                return new WP_Error('mjb_license_expired', __('This license key has expired.', 'modern-job-board'));
            }
        }

        $expected = self::sign_key($parsed['plan_code'], $parsed['expires']);
        if (!hash_equals($expected, $parsed['checksum'])) {
            return new WP_Error('mjb_license_invalid', __('Invalid license key.', 'modern-job-board'));
        }

        $plan = self::plan_from_code($parsed['plan_code']);
        update_option(self::OPTION_KEY, $key, false);
        update_option(self::OPTION_PLAN, $plan, false);
        update_option(self::OPTION_META, array(
            'plan' => $plan,
            'expires' => $parsed['expires'],
            'activated_at' => time(),
        ), false);

        return true;
    }

    /**
     * Clear paid license → Free.
     */
    public static function clear_license()
    {
        delete_option(self::OPTION_KEY);
        update_option(self::OPTION_PLAN, self::PLAN_FREE, false);
        delete_option(self::OPTION_META);
    }

    /**
     * Generate an offline key for a plan (admin/dev tooling / sales).
     *
     * @param string $plan free|pro|business|complete_site
     * @param string $expires YYYYMMDD or 00000000
     * @return string|WP_Error
     */
    public static function generate_key($plan, $expires = '00000000')
    {
        $code = self::code_from_plan($plan);
        if ($code === '') {
            return new WP_Error('mjb_license_plan', __('Unknown plan.', 'modern-job-board'));
        }

        $expires = preg_replace('/\D/', '', (string) $expires);
        if (strlen($expires) !== 8) {
            $expires = '00000000';
        }

        $checksum = self::sign_key($code, $expires);
        return 'MJB-' . $code . '-' . $expires . '-' . strtoupper($checksum);
    }

    /**
     * @param string $key
     * @return array|WP_Error
     */
    private static function parse_key($key)
    {
        if (!preg_match('/^MJB-(FREE|PRO|BUS|CS)-(\d{8})-([A-F0-9]{8})$/', $key, $m)) {
            return new WP_Error(
                'mjb_license_format',
                __('License key format must be MJB-{PLAN}-{YYYYMMDD}-{CHECKSUM}.', 'modern-job-board')
            );
        }

        return array(
            'plan_code' => $m[1],
            'expires' => $m[2],
            'checksum' => $m[3],
        );
    }

    /**
     * @param string $plan_code
     * @param string $expires
     * @return string 8-char hex
     */
    private static function sign_key($plan_code, $expires)
    {
        $payload = $plan_code . '|' . $expires . '|' . self::KEY_SALT;
        return strtoupper(substr(hash_hmac('sha256', $payload, self::KEY_SALT), 0, 8));
    }

    /**
     * @param string $code
     * @return string
     */
    private static function plan_from_code($code)
    {
        $map = array(
            'FREE' => self::PLAN_FREE,
            'PRO'  => self::PLAN_PRO,
            'BUS'  => self::PLAN_BUSINESS,
            'CS'   => self::PLAN_COMPLETE,
        );
        return isset($map[$code]) ? $map[$code] : self::PLAN_FREE;
    }

    /**
     * @param string $plan
     * @return string
     */
    private static function code_from_plan($plan)
    {
        $map = array(
            self::PLAN_FREE     => 'FREE',
            self::PLAN_PRO      => 'PRO',
            self::PLAN_BUSINESS => 'BUS',
            self::PLAN_COMPLETE => 'CS',
        );
        return isset($map[$plan]) ? $map[$plan] : '';
    }

    /**
     * Upgrade CTA markup for admin locked features.
     *
     * @param string $feature
     * @return string
     */
    public static function upgrade_notice_html($feature = '')
    {
        $needed = self::is_business_feature($feature) ? self::PLAN_BUSINESS : self::PLAN_PRO;
        $label = self::get_plan_label($needed);

        $html = '<div class="notice notice-info inline mjb-license-lock"><p>';
        $html .= esc_html(sprintf(
            /* translators: %s: plan name e.g. Pro */
            __('This feature requires the %s plan or higher.', 'modern-job-board'),
            $label
        ));
        $html .= '</p>';
        if (class_exists('MJB_License_Commerce')) {
            $html .= MJB_License_Commerce::purchase_cta_html($needed);
        } else {
            $html .= '<p><a href="' . esc_url(admin_url('admin.php?page=modern-job-board&tab=settings#mjb-license')) . '">';
            $html .= esc_html__('Manage license', 'modern-job-board');
            $html .= '</a></p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param string $feature
     * @return bool
     */
    private static function is_business_feature($feature)
    {
        return in_array(sanitize_key($feature), array('rest_api', 'xml_feed', 'webhooks'), true);
    }
}
