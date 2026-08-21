<?php
/**
 * Candidate saved / bookmarked jobs.
 *
 * @package ModernJobBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Saved_Jobs
{
    const META_KEY = '_mjb_saved_jobs';

    /**
     * Bootstrap handlers.
     */
    public static function init()
    {
        add_action('admin_post_mjb_toggle_saved_job', array(__CLASS__, 'handle_toggle'));
        add_action('admin_post_nopriv_mjb_toggle_saved_job', array(__CLASS__, 'handle_toggle_guest'));
    }

    /**
     * @param int $user_id
     * @return int[]
     */
    public static function get_ids($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }

        $ids = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($ids)) {
            return array();
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Persist id list (empty array clears the list).
     *
     * @param int   $user_id
     * @param int[] $ids
     */
    public static function set_ids($user_id, $ids)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (empty($ids)) {
            delete_user_meta($user_id, self::META_KEY);
            return;
        }

        update_user_meta($user_id, self::META_KEY, $ids);
    }

    /**
     * @param int $user_id
     * @param int $job_id
     * @return bool
     */
    public static function is_saved($user_id, $job_id)
    {
        return in_array((int) $job_id, self::get_ids($user_id), true);
    }

    /**
     * @param int $user_id
     * @param int $job_id
     * @return bool True if saved after toggle; false if unsaved.
     */
    public static function toggle($user_id, $job_id)
    {
        $user_id = (int) $user_id;
        $job_id = (int) $job_id;
        $ids = self::get_ids($user_id);

        if (in_array($job_id, $ids, true)) {
            $ids = array_values(array_diff($ids, array($job_id)));
            self::set_ids($user_id, $ids);
            return false;
        }

        $ids[] = $job_id;
        self::set_ids($user_id, $ids);
        return true;
    }

    /**
     * Remove a job from the saved list (no-op if not saved).
     *
     * @param int $user_id
     * @param int $job_id
     * @return bool True if it was saved and is now removed.
     */
    public static function remove($user_id, $job_id)
    {
        $user_id = (int) $user_id;
        $job_id = (int) $job_id;
        $ids = self::get_ids($user_id);
        if (!in_array($job_id, $ids, true)) {
            return false;
        }

        self::set_ids($user_id, array_values(array_diff($ids, array($job_id))));
        return true;
    }

    /**
     * Drop IDs that are no longer published job listings; return remaining IDs.
     *
     * @param int $user_id
     * @return int[]
     */
    public static function prune($user_id)
    {
        $user_id = (int) $user_id;
        $ids = self::get_ids($user_id);
        if (empty($ids)) {
            return array();
        }

        $valid = array();
        foreach ($ids as $job_id) {
            $job = get_post($job_id);
            if ($job && $job->post_type === 'job_listing' && $job->post_status === 'publish') {
                $valid[] = $job_id;
            }
        }

        if (count($valid) !== count($ids)) {
            self::set_ids($user_id, $valid);
        }

        return $valid;
    }

    /**
     * Published job posts currently saved by the user (most recently saved first).
     * Prunes stale IDs as a side effect.
     *
     * @param int $user_id
     * @return WP_Post[]
     */
    public static function get_jobs($user_id)
    {
        $ids = self::prune($user_id);
        if (empty($ids)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'post__in' => $ids,
            'posts_per_page' => count($ids),
            'orderby' => 'post__in',
            'no_found_rows' => true,
        ));

        // Newest saves first (meta is append order).
        return array_reverse($posts);
    }

    /**
     * Whether the candidate has already applied to this job.
     *
     * @param int $user_id
     * @param int $job_id
     * @return bool
     */
    public static function user_has_applied($user_id, $job_id)
    {
        $user = get_userdata((int) $user_id);
        if (!$user || !is_email($user->user_email)) {
            return false;
        }

        if (class_exists('MJB_Application_Guard')) {
            return MJB_Application_Guard::has_duplicate_application((int) $job_id, $user->user_email);
        }

        return false;
    }

    /**
     * Apply / external apply URL for a saved job row.
     *
     * @param int $job_id
     * @return array{url:string,external:bool,label:string,disabled:bool}
     */
    public static function get_apply_action($job_id)
    {
        $job_id = (int) $job_id;
        $method = get_post_meta($job_id, '_application_method', true);
        $app_url = get_post_meta($job_id, '_application_url', true);
        $is_external = ($method === 'external' && !empty($app_url));

        if ($is_external) {
            return array(
                'url' => $app_url,
                'external' => true,
                'label' => __('Apply', 'modern-job-board'),
                'disabled' => false,
            );
        }

        if (is_user_logged_in() && self::user_has_applied(get_current_user_id(), $job_id)) {
            return array(
                'url' => '',
                'external' => false,
                'label' => __('Applied', 'modern-job-board'),
                'disabled' => true,
            );
        }

        $url = class_exists('MJB_Applications')
            ? MJB_Applications::get_apply_url($job_id)
            : get_permalink($job_id);

        return array(
            'url' => $url ?: '',
            'external' => false,
            'label' => __('Apply', 'modern-job-board'),
            'disabled' => ($url === '' || $url === false),
        );
    }

    /**
     * Build toggle URL (logged-in users).
     * Pretty: /jobs/save/{id}/?_wpnonce=…[&redirect_to=…]
     *
     * @param int         $job_id
     * @param string|null $redirect_to Optional return URL after toggle.
     * @return string
     */
    public static function get_toggle_url($job_id, $redirect_to = null)
    {
        $job_id = (int) $job_id;

        if (class_exists('MJB_Pretty_Urls')) {
            return MJB_Pretty_Urls::front_save_url($job_id, $redirect_to);
        }

        $args = array(
            'action' => 'mjb_toggle_saved_job',
            'job_id' => $job_id,
        );
        if (is_string($redirect_to) && $redirect_to !== '') {
            $args['redirect_to'] = $redirect_to;
        }

        return wp_nonce_url(
            add_query_arg($args, admin_url('admin-post.php')),
            'mjb_toggle_saved_job_' . $job_id
        );
    }

    /**
     * Guest: send to candidate login, then back to the job.
     * Pretty: /jobs/candidate-login/save/?redirect_to=…
     *
     * @param int $job_id
     * @return string
     */
    public static function get_login_url_for_save($job_id)
    {
        $job_id = (int) $job_id;
        if (class_exists('MJB_Pretty_Urls')) {
            return MJB_Pretty_Urls::login_to_save_url($job_id);
        }

        $redirect = $job_id > 0 ? get_permalink($job_id) : home_url('/');
        return MJB_Page_Resolver::get_page_url(
            'mjb_candidate_login',
            'mjb_candidate_login_page_id',
            array(
                'redirect_to' => $redirect,
                'mjb_notice' => 'error_login_to_save',
            ),
            '/jobs/candidate-login/'
        );
    }

    /**
     * Safe post-toggle redirect (query, referer, or job permalink).
     *
     * @param int $job_id
     * @return string
     */
    public static function resolve_toggle_redirect($job_id)
    {
        $job_id = (int) $job_id;
        $fallback = $job_id > 0 ? get_permalink($job_id) : MJB_Page_Resolver::get_jobs_page_url();
        if (!$fallback) {
            $fallback = home_url('/');
        }

        $candidate = '';
        if (!empty($_REQUEST['redirect_to'])) {
            $candidate = esc_url_raw(wp_unslash($_REQUEST['redirect_to']));
        }
        if ($candidate === '') {
            $referer = wp_get_referer();
            if (is_string($referer) && $referer !== '') {
                $candidate = $referer;
            }
        }

        if ($candidate !== '') {
            $validated = wp_validate_redirect($candidate, false);
            if ($validated) {
                return $validated;
            }
        }

        return $fallback;
    }

    /**
     * Guest hits save without auth.
     */
    public static function handle_toggle_guest()
    {
        $job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        wp_safe_redirect(self::get_login_url_for_save($job_id));
        exit;
    }

    /**
     * Logged-in toggle.
     */
    public static function handle_toggle()
    {
        $job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        $redirect = self::resolve_toggle_redirect($job_id);

        if ($job_id <= 0 || !wp_verify_nonce(
            isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '',
            'mjb_toggle_saved_job_' . $job_id
        )) {
            MJB_Notices::redirect($redirect ?: home_url('/'), 'error_security');
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(self::get_login_url_for_save($job_id));
            exit;
        }

        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing' || $job->post_status !== 'publish') {
            // Still allow remove if the listing went away while saved.
            if (self::is_saved(get_current_user_id(), $job_id)) {
                self::remove(get_current_user_id(), $job_id);
                MJB_Notices::redirect($redirect, 'success_job_unsaved');
            }
            MJB_Notices::redirect($redirect, 'error_invalid_job');
        }

        $saved = self::toggle(get_current_user_id(), $job_id);
        MJB_Notices::redirect($redirect, $saved ? 'success_job_saved' : 'success_job_unsaved');
    }
}
